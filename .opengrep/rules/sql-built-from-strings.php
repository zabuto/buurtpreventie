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

// ruleid: sql-built-from-strings
$queryBuilder->select('m.' . $field);

// ruleid: sql-built-from-strings
$queryBuilder->addSelect('COUNT(' . $column . ')');

// ruleid: sql-built-from-strings
$queryBuilder->from($entity . ' m');

// ruleid: sql-built-from-strings
$queryBuilder->join("r.$relation", 'p');

// ruleid: sql-built-from-strings
$queryBuilder->innerJoin('r.' . $relation, 'p');

// ruleid: sql-built-from-strings
$queryBuilder->leftJoin(\sprintf('r.%s', $relation), 'p');

// ruleid: sql-built-from-strings
$queryBuilder->groupBy('r.' . $field);

// ruleid: sql-built-from-strings
$queryBuilder->addGroupBy("r.$field");

// ruleid: sql-built-from-strings
$queryBuilder->set('m.name', "'" . $name . "'");

// ruleid: sql-built-from-strings
$entityManager->createQuery(\sprintf('SELECT m FROM %s m WHERE m.name = %s', Member::class, $name));

// ok: sql-built-from-strings
$entityManager->createQuery(\sprintf('SELECT m FROM %s m WHERE m.id = :id', Member::class));

// ok: sql-built-from-strings
$entityManager->createQuery(\sprintf('SELECT r FROM %s r WHERE EXISTS (SELECT 1 FROM %s p WHERE p.round = r)', Round::class, Participation::class));

// ok: sql-built-from-strings
$queryBuilder->select('COUNT(m.id)')->from(Member::class, 'm')->where($condition);

// ok: sql-built-from-strings
$queryBuilder->set('m.name', ':name')->setParameter('name', $name);
