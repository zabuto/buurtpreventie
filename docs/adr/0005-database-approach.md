# ADR 0005: one schema on MariaDB and MySQL, search keys from PHP, locks for the last spot

- Status: accepted
- Date: 2026-11-15

## Context

The app runs on shared hosting, where the host picks the database: MariaDB on one, MySQL on the
next, and a version upgrade without notice. Both must work from the same code and the same
migrations, without a feature only one of them has.

Members search each other by name, and walkers pick meeting points and outcomes from a list, so
"José", "Jose" and "JOSE" must find each other and must not exist side by side as two items. The two
databases disagree on which characters compare equal: `utf8mb4_unicode_ci` on MariaDB and
`utf8mb4_0900_ai_ci` on MySQL treat `ß` and ligatures differently.

A round has a maximum number of walkers. Two walkers who take the last spot at the same moment must
not both get it, and a sign-up must never vanish because the last walker withdrew and the round was
deleted at the same time.

The modules meet only through use cases or interfaces in `Shared\Domain`
([architecture.md](../architecture.md#rule-4-modules-and-shared)); the schema must not undo that.

## Decision

### Two databases, one schema

- **One set of migrations** for MariaDB 10.11 and MySQL 8.4. Migrations are generated locally on
  MariaDB and checked by hand. CI runs them from an empty database on both, then
  `doctrine:schema:validate`, every `down()` and up again, and the database tests. Migrations only
  add, and each has a working `down()`.
- **`utf8mb4` with `utf8mb4_unicode_ci`** as the default table options and `charset: utf8mb4` on the
  connection. Without it, MySQL picks `utf8mb4_0900_ai_ci` and MariaDB `utf8mb4_general_ci`.
- **No JSON columns.** MariaDB stores `JSON` as `LONGTEXT` with a check, MySQL has a type of its own,
  so one migration would give two schemas. The changed fields of an audit entry are text from
  `json_encode`, roles a `simple_array`.
- **No spatial types.** Coordinates are two `DECIMAL(9,6)` columns in the embeddable `Coordinates`,
  in WGS84 with a range check.
- **Identifiers are `BINARY(16)` UUIDs.** Doctrine declares the type `uuid` as `BINARY(16)` on both;
  `UuidColumnTest` keeps it from becoming MariaDB's own `UUID` type.

### No foreign key between modules

- A reference to another module is the UUID of the member, never a Doctrine relation:
  `Participation.memberId` and `onBehalfOf`, `RoundReport.authorId`, `RoundComment.authorId`,
  `Account.memberId`, `ApiToken.memberId` and `AuditEntry.actorId`. Within a module, relations and
  foreign keys stay.
- Every such column has an index of its own, since the database adds one only for a foreign key.
  `UuidColumnIndexTest` requires an index on every UUID column that is not the primary key.
- A member is never deleted: erasing anonymises the member, so every `memberId` keeps pointing at an
  existing row.

### Search keys from PHP

- **`LookupKey` builds the key of a name in PHP**: decomposed (`Normalizer::FORM_D`), combining marks
  removed, punctuation and whitespace runs turned into one space, trimmed and lower-cased with
  `mb_strtolower`. A letter that does not decompose (`ø`, `ł`, `æ`, `ß`) stays, so "Søren" does not
  find "Soren". Invalid UTF-8 is refused. The key is the same on both databases.
- **The key has a column of its own** with `utf8mb4_bin` and an index; the name as typed sits next to
  it and is what the app shows.
- **`EmailAddress`** trims and lower-cases and keeps accents: with and without an accent are two
  mailboxes. The address is stored only in that form, in a `utf8mb4_bin` column.
- **Unique for members: e-mail address and display name, not the full name.** Two members may share a
  full name. The display name is unique on its key, so "Jan H." and "jan h" clash. An erased member
  has no display name; a unique index in InnoDB allows any number of `NULL` keys on both databases.
- **Meeting points and outcomes keep a unique name on the same key**, archived items included, since
  neither database has a partial index. A name that exists archived gives an error that names the
  item, so the admin restores it.
- **Search is "contains"**: `LIKE` on the key. The term is escaped with `!` as the escape character,
  so a `%` or `_` matches only itself. `LookupKey` already turns both into a space, so neither ever
  reaches the query as a wildcard; the escape keeps that a property of the query. The index helps
  only for "starts with", which makes no difference at a few hundred members.
- `composer.json` requires `ext-intl` and `ext-mbstring`, so Composer refuses a host without them.

### Clashes and locks

- **A handler checks first; the unique index catches the race.** `add()` and `update()` on the member
  and catalogue repositories flush at once instead of at the commit, so a unique index another
  request took in the meantime surfaces inside the handler. The repository turns it into the same
  error the check gives (`EmailAddressTaken`, `DisplayNameTaken`, `MeetingPointNameTaken`,
  `OutcomeNameTaken`). A failed flush closes the entity manager, so the catalogue repositories read
  the item holding the name through the connection, with a locking read that sees the latest commit.
- **Signing up and withdrawing lock the round first.** `RoundRepository::getForUpdate()` reads the
  round with its walkers in one `SELECT ... FOR UPDATE` as the first query of the transaction. The
  second sign-up for the last spot waits for the first, counts it and gets "round is full"; a sign-up
  that waited for a withdrawal that deleted the round gets "not found".
- **The count is a locking read too.** InnoDB in `REPEATABLE READ` fixes the snapshot at the first
  plain `SELECT` of a transaction. A plain count after an earlier read misses the walker another
  transaction committed in the meantime; a locking read always sees the latest commit. The query
  refreshes walkers the entity manager loaded earlier (`Query::HINT_REFRESH`).
- **A second sign-up of the same member is a successful no-op**, backed by a unique key on round and
  member.
- **The last active admin is guarded with a lock on the admins.** `activeAdminsForUpdate()` reads the
  active admins with `FOR UPDATE`, so two admins who take each other's role at the same moment cannot
  both see the other as the admin who remains. The roles are a `simple_array` without an index, so
  InnoDB locks every member row it reads: role changes, deactivations and erasures wait for each
  other. They are rare and short.

### Tests

- Database tests carry the group `database`. `dama/doctrine-test-bundle` rolls each test back; a
  PHPUnit extension builds the test database from the migrations once per run, because both
  databases commit every DDL statement at once.
- Every repository method with a query has an integration test on both databases; `find()` and
  `findBy()` do not. Raw SQL lives only in `<Module>\Infrastructure\Persistence\`, which the Opengrep
  rule `sql-outside-repository` enforces.
- **The concurrency test commits for real.** One PHP process cannot wait on two connections, so the
  test runs the first action in a transaction it keeps open, starts the second in a process of its
  own, waits until that process waits for the lock, then commits and checks the outcome. Waiting is
  seen in `information_schema.PROCESSLIST`: a `FOR UPDATE` query of another session that stays there
  waits for a lock. The process list shows the sessions of the own user without the `PROCESS`
  privilege the InnoDB lock tables need, which a host or CI user may lack. Without the lock or with a
  plain count, the second sign-up joins in this order and the test fails.

## Consequences

- A migration that works on only one database fails in CI, not at a host.
- Equality of names is the same on every installation, and testable in memory.
- The key columns double the storage of every name; at a few hundred members that is nothing.
- Search uses no full-text index and scans the keys; enough for one association, not for thousands of
  members.
- The schema does not stop a member reference from pointing at nothing; erasing instead of deleting
  and the handlers keep it valid.
- Locks make the last spot and the last admin safe, at the cost of short waits on a busy round and of
  role changes waiting for each other.
- **Accepted risk: the admin lock covers every member row.** While an admin changes roles, deactivates
  or erases a member, every other write to a member (consent, open round notifications, a change to
  name or contact details) waits for that transaction, which lasts milliseconds over a few hundred rows. Two such
  transactions can deadlock; InnoDB then aborts one and that request fails with an error instead of
  hanging. When waits or deadlocks show up in the log, the fix is an indexed admin column or a single
  lock row, so the lock covers only the admins.
- The concurrency test needs a real database and a second PHP process; it skips without a database,
  and fails in CI with `REQUIRE_DATABASE=1`.
