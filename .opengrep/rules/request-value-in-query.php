<?php

$sort = $request->query->get('sort');
// ruleid: request-value-in-query
$rows = $connection->fetchAllAssociative('SELECT id FROM round ORDER BY ' . $sort);

$filter = $request->toArray()['filter'];
// ruleid: request-value-in-query
$query = $entityManager->createQuery("SELECT r FROM Round r WHERE {$filter}");

// ruleid: request-value-in-query
$builder->select('r')->from(Round::class, 'r')->andWhere($request->get('where'));

$direction = $request->getPayload()->get('direction');
// ruleid: request-value-in-query
$builder->orderBy('r.startsAt', $direction);

// ruleid: request-value-in-query
$builder->leftJoin('r.walkers', 'w', 'WITH', 'w.memberId = ' . $_GET['member']);

// ruleid: request-value-in-query
$builder->set('r.note', $request->request->get('note'));

// ruleid: request-value-in-query
$connection->executeStatement(\sprintf('DELETE FROM %s', $request->attributes->get('table')));

// ok: request-value-in-query
$rows = $connection->fetchAllAssociative('SELECT id FROM round WHERE status = ?', [$request->query->get('status')]);

// ok: request-value-in-query
$builder->andWhere('r.id = :id')->setParameter('id', $request->get('id'));

// ok: request-value-in-query
$builder->set('r.note', ':note')->setParameter('note', $request->request->get('note'));

// ok: request-value-in-query
$builder->setMaxResults((int) $request->query->get('limit'));

// ok: request-value-in-query
$builder->orderBy('r.startsAt', $request->query->get('order') === 'desc' ? 'DESC' : 'ASC');

// ok: request-value-in-query
$this->members->update($this->container->get(MemberRepository::class)->ofId($memberId));

// ok: request-value-in-query
$session->set('locale', $request->getLocale());
