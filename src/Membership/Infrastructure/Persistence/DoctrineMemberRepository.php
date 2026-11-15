<?php

declare(strict_types=1);

namespace App\Membership\Infrastructure\Persistence;

use App\Membership\Domain\DisplayNameTaken;
use App\Membership\Domain\EmailAddressTaken;
use App\Membership\Domain\Member;
use App\Membership\Domain\MemberRepository;
use App\Shared\Domain\EmailAddress;
use App\Shared\Domain\LookupKey;
use App\Shared\Domain\NotFound;
use App\Shared\Domain\Role;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use Symfony\Component\Uid\Uuid;

final readonly class DoctrineMemberRepository implements MemberRepository
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    #[Override]
    public function get(Uuid $id): Member
    {
        return $this->entityManager->find(Member::class, $id) ?? throw NotFound::forId('member', $id);
    }

    #[Override]
    public function add(Member $member): void
    {
        $this->entityManager->persist($member);
        $this->write($member);
    }

    #[Override]
    public function update(Member $member): void
    {
        $this->write($member);
    }

    #[Override]
    public function getMany(array $ids): array
    {
        if ([] === $ids) {
            return [];
        }

        /** @var list<Member> */
        return $this->entityManager
            ->createQuery(\sprintf('SELECT m FROM %s m WHERE m.id IN (:ids)', Member::class))
            ->setParameter('ids', \array_map(static fn(Uuid $id): string => $id->toBinary(), $ids), ArrayParameterType::BINARY)
            ->getResult()
        ;
    }

    #[Override]
    public function activeWithOpenRoundNotifications(): array
    {
        /** @var list<Member> */
        return $this->entityManager
            ->createQuery(\sprintf('SELECT m FROM %s m WHERE m.active = true AND m.notifyOpenRounds = true', Member::class))
            ->getResult()
        ;
    }

    /**
     * The roles are a comma-separated list; the commas around it let the match take whole roles only. Without an index
     * on the roles, InnoDB locks every member row it reads, so role changes wait for each other: rare and short.
     */
    #[Override]
    public function activeAdminsForUpdate(): array
    {
        /** @var list<Member> */
        return $this->entityManager
            ->createQuery(\sprintf("SELECT m FROM %s m WHERE m.active = true AND CONCAT(',', m.roles, ',') LIKE :role", Member::class))
            ->setParameter('role', '%,' . Role::Admin->value . ',%')
            ->setLockMode(LockMode::PESSIMISTIC_WRITE)
            ->getResult()
        ;
    }

    #[Override]
    public function isEmailAddressTaken(EmailAddress $email, ?Uuid $except = null): bool
    {
        return $this->isTaken('m.email = :value', $email->value, $except);
    }

    #[Override]
    public function isDisplayNameTaken(LookupKey $displayName, ?Uuid $except = null): bool
    {
        return $this->isTaken('m.displayNameKey = :value', $displayName->value, $except);
    }

    /**
     * The term is escaped for LIKE with "!", so a "%" or "_" in it matches only itself; a backslash is no escape
     * character then. The lookup key already turns both into a space; the escape keeps that a property of this query.
     */
    #[Override]
    public function search(?LookupKey $term = null): array
    {
        $query = $this->entityManager
            ->createQueryBuilder()
            ->select('m')
            ->from(Member::class, 'm')
            ->where('m.erasedAt IS NULL')
            ->orderBy('m.displayNameKey')
            ->addOrderBy('m.id')
        ;

        if (null !== $term) {
            $query
                ->andWhere("m.nameKey LIKE :term ESCAPE '!' OR m.displayNameKey LIKE :term ESCAPE '!'")
                ->setParameter('term', '%' . \str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $term->value) . '%')
            ;
        }

        /** @var list<Member> */
        return $query->getQuery()->getResult();
    }

    /**
     * @param 'm.email = :value'|'m.displayNameKey = :value' $condition
     */
    private function isTaken(string $condition, string $value, ?Uuid $except): bool
    {
        $query = $this->entityManager
            ->createQueryBuilder()
            ->select('COUNT(m.id)')
            ->from(Member::class, 'm')
            ->where($condition)
            ->setParameter('value', $value)
        ;

        if (null !== $except) {
            $query->andWhere('m.id <> :except')->setParameter('except', $except, 'uuid');
        }

        return 0 < (int) $query->getQuery()->getSingleScalarResult();
    }

    /**
     * Flushes now rather than at the commit, so a unique index another member took in the meantime becomes the error
     * the handler would have given.
     *
     * @throws EmailAddressTaken
     * @throws DisplayNameTaken
     */
    private function write(Member $member): void
    {
        try {
            $this->entityManager->flush();
        } catch (UniqueConstraintViolationException $exception) {
            $email = $member->email();
            if (null !== $email && \str_contains($exception->getMessage(), 'member_email')) {
                throw EmailAddressTaken::forAddress($email);
            }

            if (\str_contains($exception->getMessage(), 'member_display_name_key')) {
                throw DisplayNameTaken::forName((string) $member->displayName());
            }

            throw $exception;
        }
    }
}
