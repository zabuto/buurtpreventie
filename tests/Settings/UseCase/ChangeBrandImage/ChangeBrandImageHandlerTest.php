<?php

declare(strict_types=1);

namespace App\Tests\Settings\UseCase\ChangeBrandImage;

use App\Settings\Domain\BrandImageFile;
use App\Settings\Domain\BrandImageKind;
use App\Settings\Domain\Setting;
use App\Settings\Domain\UnsupportedImage;
use App\Settings\UseCase\ChangeBrandImage\ChangeBrandImage;
use App\Settings\UseCase\ChangeBrandImage\ChangeBrandImageHandler;
use App\Shared\Domain\AccessDenied;
use App\Shared\Domain\AuditedAction;
use App\Shared\Domain\Authorization;
use App\Tests\Settings\Fake\InMemoryBrandImages;
use App\Tests\Settings\Fake\InMemorySettingRepository;
use App\Tests\Settings\Fake\TestImages;
use App\Tests\Shared\Fake\FakeAuthorization;
use App\Tests\Shared\Fake\ImmediateTransaction;
use App\Tests\Shared\Fake\RecordingAuditTrail;
use Override;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Uid\Uuid;

final class ChangeBrandImageHandlerTest extends TestCase
{
    private InMemorySettingRepository $settings;
    private InMemoryBrandImages $images;
    private RecordingAuditTrail $auditTrail;
    private ImmediateTransaction $transaction;

    #[Override]
    protected function setUp(): void
    {
        $this->settings = new InMemorySettingRepository();
        $this->images = new InMemoryBrandImages();
        $this->auditTrail = new RecordingAuditTrail();
        $this->transaction = new ImmediateTransaction();
    }

    #[Test]
    public function it_stores_every_size_and_points_the_setting_at_their_version(): void
    {
        $upload = TestImages::png(600, 200);
        $version = BrandImageFile::versionOf($upload);

        $this->handler()(new ChangeBrandImage('logo', $upload));

        $this->assertSame(
            [$version, ['logo-128-' . $version . '.png', 'logo-512-' . $version . '.png']],
            [$this->settings->find('brand.logo')?->value(), $this->images->names()],
        );
    }

    #[Test]
    public function it_removes_the_files_of_the_previous_image_after_the_commit(): void
    {
        $old = $this->images->store(BrandImageKind::Icon, TestImages::png(512, 512));
        $this->settings->add(Setting::of('brand.icon', $old));
        $upload = TestImages::png(600, 600);
        $new = BrandImageFile::versionOf($upload);

        $this->handler()(new ChangeBrandImage('icon', $upload));

        $this->assertSame(
            ['icon-180-' . $new . '.png', 'icon-192-' . $new . '.png', 'icon-32-' . $new . '.png', 'icon-512-' . $new . '.png'],
            $this->images->names(),
        );
    }

    #[Test]
    public function it_keeps_the_previous_image_when_the_change_is_rolled_back(): void
    {
        $old = $this->images->store(BrandImageKind::Logo, TestImages::png(300, 100));
        $this->settings->add(Setting::of('brand.logo', $old));

        try {
            $this->transaction->run(function (): never {
                $this->handler()(new ChangeBrandImage('logo', TestImages::png(600, 200)));

                throw new RuntimeException('A later step of the same transaction fails.');
            });
        } catch (RuntimeException $exception) {
            $this->assertSame('A later step of the same transaction fails.', $exception->getMessage());
        }

        $this->assertContains('logo-512-' . $old . '.png', $this->images->names());
    }

    /**
     * Uploading the same image twice gives the same names, so removing the previous files would remove the current ones.
     */
    #[Test]
    public function it_keeps_the_files_when_the_same_image_is_uploaded_again(): void
    {
        $upload = TestImages::png(300, 100);
        $this->handler()(new ChangeBrandImage('logo', $upload));

        $this->handler()(new ChangeBrandImage('logo', $upload));

        $this->assertCount(2, $this->images->names());
    }

    #[Test]
    public function it_records_the_previous_and_the_new_version(): void
    {
        $this->settings->add(Setting::of('brand.logo', '0123456789abcdef'));
        $upload = TestImages::png(300, 100);

        $this->handler()(new ChangeBrandImage('logo', $upload));

        $this->assertEquals(
            [new AuditedAction('setting.image_changed', 'settings', Uuid::fromString('00000000-0000-0000-0000-000000000000'), [
                'brand.logo' => ['old' => '0123456789abcdef', 'new' => BrandImageFile::versionOf($upload)],
            ])],
            $this->auditTrail->recorded,
        );
    }

    #[Test]
    public function it_records_nothing_when_the_same_image_is_uploaded_again(): void
    {
        $upload = TestImages::png(300, 100);
        $this->settings->add(Setting::of('brand.logo', BrandImageFile::versionOf($upload)));

        $this->handler()(new ChangeBrandImage('logo', $upload));

        $this->assertSame([], $this->auditTrail->recorded);
    }

    #[Test]
    public function it_changes_nothing_for_a_refused_image(): void
    {
        $refusal = null;
        try {
            $this->handler()(new ChangeBrandImage('icon', TestImages::png(600, 500)));
        } catch (UnsupportedImage $exception) {
            $refusal = $exception;
        }

        $this->assertInstanceOf(UnsupportedImage::class, $refusal);
        $this->assertSame([null, [], []], [$this->settings->find('brand.icon'), $this->images->names(), $this->auditTrail->recorded]);
    }

    #[Test]
    public function it_stores_nothing_when_access_is_denied(): void
    {
        $refusal = null;
        try {
            $this->handler(FakeAuthorization::refusing())(new ChangeBrandImage('logo', TestImages::png(300, 100)));
        } catch (AccessDenied $exception) {
            $refusal = $exception;
        }

        $this->assertInstanceOf(AccessDenied::class, $refusal);
        $this->assertSame([null, []], [$this->settings->find('brand.logo'), $this->images->names()]);
    }

    private function handler(?Authorization $authorization = null): ChangeBrandImageHandler
    {
        return new ChangeBrandImageHandler(
            $this->settings,
            $this->images,
            $authorization ?? FakeAuthorization::granting(),
            $this->auditTrail,
            $this->transaction,
        );
    }
}
