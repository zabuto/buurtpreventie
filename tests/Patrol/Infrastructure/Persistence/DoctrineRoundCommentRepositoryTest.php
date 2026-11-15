<?php

declare(strict_types=1);

namespace App\Tests\Patrol\Infrastructure\Persistence;

use App\Patrol\Domain\Round;
use App\Patrol\Domain\RoundComment;
use App\Patrol\Domain\RoundStart;
use App\Patrol\Domain\StaffingRule;
use App\Patrol\Infrastructure\Persistence\DoctrineRoundCommentRepository;
use App\Shared\Domain\NotFound;
use App\Tests\Database\TestDatabase;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;

#[Group('database')]
final class DoctrineRoundCommentRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;
    private DoctrineRoundCommentRepository $repository;

    #[Override]
    protected function setUp(): void
    {
        TestDatabase::require();
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        // @phpstan-ignore method.alreadyNarrowedType (PHPStan reads the type from the container, Mago does not)
        $this->assertInstanceOf(EntityManagerInterface::class, $entityManager);
        $this->entityManager = $entityManager;
        $this->repository = new DoctrineRoundCommentRepository($entityManager);
    }

    #[Test]
    public function it_brings_a_comment_back_by_id(): void
    {
        $comment = RoundComment::post($this->round(), Uuid::v7(), 'Ik neem een zaklamp mee.', new DateTimeImmutable('2026-11-15T12:00:00+00:00'));
        $this->repository->add($comment);
        $this->entityManager->flush();
        $this->entityManager->clear();

        $read = $this->repository->get($comment->id());

        $this->assertSame('Ik neem een zaklamp mee.', $read->text());
    }

    #[Test]
    public function it_reports_an_unknown_comment(): void
    {
        $this->expectException(NotFound::class);

        $this->repository->get(Uuid::v7());
    }

    #[Test]
    public function it_lists_the_comments_of_one_round_oldest_first(): void
    {
        $round = $this->round();
        $later = RoundComment::post($round, Uuid::v7(), 'Tot vanavond.', new DateTimeImmutable('2026-11-15T13:00:00+00:00'));
        $earlier = RoundComment::post($round, Uuid::v7(), 'Wie loopt er mee?', new DateTimeImmutable('2026-11-15T12:00:00+00:00'));
        $onAnotherRound = RoundComment::post($this->round(), Uuid::v7(), 'Andere ronde.', new DateTimeImmutable('2026-11-15T12:30:00+00:00'));
        foreach ([$later, $earlier, $onAnotherRound] as $comment) {
            $this->repository->add($comment);
        }
        $this->entityManager->flush();

        $found = $this->repository->forRound($round);

        $this->assertSame(
            [$earlier->id()->toRfc4122(), $later->id()->toRfc4122()],
            \array_map(static fn(RoundComment $comment): string => $comment->id()->toRfc4122(), $found),
        );
    }

    #[Test]
    public function it_removes_a_comment(): void
    {
        $comment = RoundComment::post($this->round(), Uuid::v7(), 'Vergissing.', new DateTimeImmutable('2026-11-15T12:00:00+00:00'));
        $this->repository->add($comment);
        $this->entityManager->flush();

        $this->repository->remove($comment);
        $this->entityManager->flush();

        $this->expectException(NotFound::class);
        $this->repository->get($comment->id());
    }

    private function round(): Round
    {
        $round = Round::plan(
            new RoundStart(new DateTimeImmutable('2026-11-20T21:00:00+00:00')),
            Uuid::v7(),
            new StaffingRule(minimumWalkers: 2, maximumWalkers: 7),
            new DateTimeImmutable('2026-11-01T12:00:00+00:00'),
        );
        $this->entityManager->persist($round);

        return $round;
    }
}
