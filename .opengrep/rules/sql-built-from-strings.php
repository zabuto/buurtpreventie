<?php

// ruleid: sql-built-from-strings
$connection->executeQuery('SELECT * FROM member WHERE email = "' . $email . '"');

// ruleid: sql-built-from-strings
$connection->executeStatement("DELETE FROM round WHERE id = {$id}");

// ruleid: sql-built-from-strings
$connection->fetchAssociative(\sprintf('SELECT * FROM round WHERE starts_at > %s', $from));

// ruleid: sql-built-from-strings
$entityManager->createQuery('SELECT r FROM App\Patrol\Domain\Round r WHERE r.id = ' . $id);

// ruleid: sql-built-from-strings
$queryBuilder->andWhere("r.status = '$status'");

// ok: sql-built-from-strings
$connection->executeQuery('SELECT * FROM member WHERE email = ?', [$email]);

// ok: sql-built-from-strings
$queryBuilder->andWhere('r.status = :status')->setParameter('status', $status);

// ok: sql-built-from-strings
$logger->info('Imported ' . $count . ' rounds');
