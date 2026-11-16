<?php

declare(strict_types=1);

namespace App\Tests\Shared\Infrastructure\Notification;

use App\Membership\Domain\Member;
use App\Membership\Domain\MemberDetails;
use App\Membership\Domain\MemberRepository;
use App\Shared\Domain\EmailAddress;
use App\Shared\Domain\Notification;
use App\Shared\Domain\Notifier;
use App\Shared\Domain\Transaction;
use App\Tests\Database\TestDatabase;
use Doctrine\ORM\EntityManagerInterface;
use DomainException;
use Override;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * The notifier and transaction of the app as wired, on the database: a mail leaves after the commit and never for a
 * change that is rolled back. The test environment sends to the null transport, which keeps the mails for the
 * assertions.
 */
#[Group('database')]
final class AfterCommitDeliveryTest extends KernelTestCase
{
    private Member $jan;

    #[Override]
    protected function setUp(): void
    {
        TestDatabase::require();
        $suffix = \bin2hex(\random_bytes(6));
        $this->jan = Member::register(new MemberDetails(
            EmailAddress::fromString('jan' . $suffix . '@example.org'),
            'Jan Bakker ' . $suffix,
            'Jan B. ' . $suffix,
        ));
        self::service(MemberRepository::class)->add($this->jan);
        self::service(EntityManagerInterface::class)->flush();
    }

    #[Test]
    public function it_mails_once_the_transaction_has_committed(): void
    {
        $mailedDuringTheRun = self::service(Transaction::class)->run(function (): int {
            self::service(Notifier::class)->send($this->loginCode());

            return \count(self::getMailerMessages());
        });

        $this->assertSame(0, $mailedDuringTheRun);
        self::assertEmailCount(1);
    }

    #[Test]
    public function it_mails_nothing_when_the_transaction_rolls_back(): void
    {
        $refusal = new DomainException('The round is full.');

        $caught = null;
        try {
            self::service(Transaction::class)->run(function () use ($refusal): never {
                self::service(Notifier::class)->send($this->loginCode());

                throw $refusal;
            });
        } catch (DomainException $exception) {
            $caught = $exception;
        }

        $this->assertSame($refusal, $caught);
        self::assertEmailCount(0);
    }

    private function loginCode(): Notification
    {
        return new Notification($this->jan->id(), 'account.login_code', ['code' => '482 913', 'minutes' => 60]);
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $id
     *
     * @return T
     */
    private static function service(string $id): object
    {
        $service = self::getContainer()->get($id);
        \assert($service instanceof $id, 'The container gives the service under its own class or interface.');

        return $service;
    }
}
