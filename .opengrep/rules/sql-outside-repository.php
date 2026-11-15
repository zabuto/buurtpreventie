<?php

// ruleid: sql-outside-repository
$connection->executeQuery('SELECT COUNT(*) FROM member');

// ruleid: sql-outside-repository
$connection->executeStatement('DELETE FROM round WHERE id = ?', [$id]);

// ruleid: sql-outside-repository
$connection->fetchAssociative('SELECT * FROM member WHERE id = ?', [$id]);

// ruleid: sql-outside-repository
$connection->fetchOne('SELECT COUNT(*) FROM round');

// ruleid: sql-outside-repository
$entityManager->createQuery('SELECT m FROM App\Membership\Domain\Member m');

// ruleid: sql-outside-repository
$entityManager->createNativeQuery('SELECT * FROM member', $mapping);

// ruleid: sql-outside-repository
$entityManager->createQueryBuilder();

// ok: sql-outside-repository
$members->search($term);

// ok: sql-outside-repository
$entityManager->find(Member::class, $id);

// ok: sql-outside-repository
$queryBuilder->setParameter('id', $id);
