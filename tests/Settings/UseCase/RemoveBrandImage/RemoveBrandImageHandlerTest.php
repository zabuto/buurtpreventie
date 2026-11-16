<?php

declare(strict_types=1);

namespace App\Tests\Settings\UseCase\RemoveBrandImage;

use App\Settings\Domain\BrandImageKind;
use App\Settings\Domain\Setting;
use App\Settings\UseCase\RemoveBrandImage\RemoveBrandImage;
use App\Settings\UseCase\RemoveBrandImage\RemoveBrandImageHandler;
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

final class RemoveBrandImageHandlerTest extends TestCase
{
    private InMemorySettingRepository $settings;
    private InMemoryBrandImages $images;
    private RecordingAuditTrail $auditTrail;
    private ImmediateTransaction $transaction;
    private string $version;

    #[Override]
    protected function setUp(): void
    {
        $this->settings = new InMemorySettingRepository();
        $this->images = new InMemoryBrandImages();
        $this->auditTrail = new RecordingAuditTrail();
        $this->transaction = new ImmediateTransaction();
        $this->version = $this->images->store(BrandImageKind::Logo, TestImages::png(300, 100));
        $this->settings->add(Setting::of('brand.logo', $this->version));
    }

    #[Test]
    public function it_restores_the_default_and_removes_the_files_after_the_commit(): void
    {
        $this->handler()(new RemoveBrandImage('logo'));

        $this->assertSame([null, []], [$this->settings->find('brand.logo'), $this->images->names()]);
    }

    #[Test]
    public function it_keeps_the_files_when_the_removal_is_rolled_back(): void
    {
        try {
            $this->transaction->run(function (): never {
                $this->handler()(new RemoveBrandImage('logo'));

                throw new RuntimeException('A later step of the same transaction fails.');
            });
        } catch (RuntimeException $exception) {
            $this->assertSame('A later step of the same transaction fails.', $exception->getMessage());
        }

        $this->assertCount(2, $this->images->names());
    }

    #[Test]
    public function it_records_the_version_that_went(): void
    {
        $this->handler()(new RemoveBrandImage('logo'));

        $this->assertEquals(
            [new AuditedAction('setting.image_changed', 'settings', Uuid::fromString('00000000-0000-0000-0000-000000000000'), [
                'brand.logo' => ['old' => $this->version, 'new' => null],
            ])],
            $this->auditTrail->recorded,
        );
    }

    #[Test]
    public function it_records_nothing_when_the_default_is_in_use(): void
    {
        $this->handler()(new RemoveBrandImage('icon'));

        $this->assertSame([], $this->auditTrail->recorded);
    }

    #[Test]
    public function it_removes_nothing_when_access_is_denied(): void
    {
        $refusal = null;
        try {
            $this->handler(FakeAuthorization::refusing())(new RemoveBrandImage('logo'));
        } catch (AccessDenied $exception) {
            $refusal = $exception;
        }

        $this->assertInstanceOf(AccessDenied::class, $refusal);
        $this->assertSame([$this->version, 2], [$this->settings->find('brand.logo')?->value(), \count($this->images->names())]);
    }

    private function handler(?Authorization $authorization = null): RemoveBrandImageHandler
    {
        return new RemoveBrandImageHandler(
            $this->settings,
            $this->images,
            $authorization ?? FakeAuthorization::granting(),
            $this->auditTrail,
            $this->transaction,
        );
    }
}
