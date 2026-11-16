<?php

declare(strict_types=1);

namespace App\Tests\SampleData\Infrastructure\Console;

use App\Audit\UseCase\VerifyAuditChain\VerifyAuditChain;
use App\Audit\UseCase\VerifyAuditChain\VerifyAuditChainHandler;
use App\Audit\UseCase\VerifyAuditChain\VerifyAuditChainOutput;
use App\Shared\Domain\Channel;
use App\Shared\Infrastructure\Security\RunAs;
use App\Tests\Database\TestDatabase;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\Clock;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * What each set leaves in the settings and the audit chain, loaded inside the transaction the test rolls back, like
 * LoadFixturesCommandTest. The uploads land in UPLOAD_DIR; their content is fixed, so every load writes the same files.
 */
#[Group('database')]
final class LoadedSettingsAndChainTest extends KernelTestCase
{
    private Connection $connection;
    private ClockInterface $realClock;

    #[Override]
    protected function setUp(): void
    {
        // Before the database check, so tearDown() can restore the clock after a skip.
        $this->realClock = Clock::get();
        TestDatabase::require();
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        // @phpstan-ignore method.alreadyNarrowedType (PHPStan reads the type from the container, Mago does not)
        $this->assertInstanceOf(EntityManagerInterface::class, $entityManager);
        $this->connection = $entityManager->getConnection();
    }

    #[Override]
    protected function tearDown(): void
    {
        Clock::set($this->realClock);

        parent::tearDown();
    }

    /**
     * A second association through the same settings and uploads an admin uses, without a change to the code.
     */
    #[Test]
    public function it_brands_the_demo_set_as_an_association_of_its_own(): void
    {
        $this->load('demo', '2026-11-15T12:00:00+01:00');

        $settings = $this->settings();
        $this->assertSame(
            [
                'association.name' => 'Buurtpreventie Voorbeeldwijk',
                'association.short_name' => 'Voorbeeldwijk',
                'brand.accent' => '#a14f00',
                'brand.accent_dark' => '#f0a04b',
                'brand.primary' => '#2f6b3a',
                'brand.primary_dark' => '#9ad3a5',
                'contact.name' => 'Fatima el Amrani',
                'logo uploaded' => true,
                'icon uploaded' => true,
            ],
            [
                ...\array_intersect_key($settings, \array_flip([
                    'association.name',
                    'association.short_name',
                    'brand.primary',
                    'brand.accent',
                    'brand.primary_dark',
                    'brand.accent_dark',
                    'contact.name',
                ])),
                'logo uploaded' => 1 === \preg_match('/^[0-9a-f]{16}$/', $settings['brand.logo'] ?? ''),
                'icon uploaded' => 1 === \preg_match('/^[0-9a-f]{16}$/', $settings['brand.icon'] ?? ''),
            ],
        );
    }

    /**
     * The longest name the setting takes and a short name of two letters, so every screen and mail meets both ends.
     */
    #[Test]
    public function it_gives_the_edge_set_a_name_at_the_limit_and_a_very_short_name(): void
    {
        $this->load('edge', '2026-11-15T12:00:00+01:00');

        $settings = $this->settings();
        $this->assertSame(
            [100, 'BP'],
            [\mb_strlen($settings['association.name'] ?? ''), $settings['association.short_name'] ?? null],
        );
    }

    /**
     * Loading empties the log, its head and anchor included, and writes every entry through the use cases, so the
     * chain starts afresh and holds.
     */
    #[DataProvider('sets')]
    #[Test]
    public function it_leaves_an_intact_audit_chain_after_loading(string $set): void
    {
        $this->load($set, '2026-11-15T12:00:00+01:00');

        $verified = $this->verifiedChain();
        $this->assertSame(
            [true, 0, $this->number('SELECT COUNT(*) FROM audit_entry')],
            [$verified->intact, $verified->beforeChain, $verified->checked],
        );
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function sets(): iterable
    {
        yield 'demo' => ['demo'];
        yield 'test' => ['test'];
        yield 'edge' => ['edge'];
    }

    private function load(string $set, string $now): void
    {
        Clock::set(new MockClock(new DateTimeImmutable($now)));
        $kernel = self::$kernel;
        \assert(null !== $kernel, 'setUp boots the kernel; booting another would leave the rolled-back connection.');

        $tester = new CommandTester(new Application($kernel)->find('app:fixtures:load'));
        $tester->execute(['set' => $set], ['interactive' => false]);

        $tester->assertCommandIsSuccessful($tester->getDisplay());
    }

    /**
     * @return array<string, string>
     */
    private function settings(): array
    {
        $settings = [];
        foreach ($this->connection->fetchAllNumeric('SELECT name, value FROM setting ORDER BY name') as [$name, $value]) {
            $settings[self::text($name)] = self::text($value);
        }

        return $settings;
    }

    private function verifiedChain(): VerifyAuditChainOutput
    {
        $handler = self::getContainer()->get(VerifyAuditChainHandler::class);
        $runAs = self::getContainer()->get(RunAs::class);
        // @phpstan-ignore method.alreadyNarrowedType (PHPStan reads the type from the container, Mago does not)
        $this->assertInstanceOf(VerifyAuditChainHandler::class, $handler);
        // @phpstan-ignore method.alreadyNarrowedType (PHPStan reads the type from the container, Mago does not)
        $this->assertInstanceOf(RunAs::class, $runAs);

        return $runAs->system(Channel::Console, static fn(): VerifyAuditChainOutput => $handler(new VerifyAuditChain()));
    }

    private function number(string $sql): int
    {
        $count = $this->connection->fetchOne($sql);
        $this->assertIsNumeric($count);

        return (int) $count;
    }

    private static function text(mixed $value): string
    {
        self::assertIsString($value);

        return $value;
    }
}
