# ADR 0007: a hash chain over the audit log, keyed outside the database, locked as the last step

- Status: accepted
- Date: 2026-11-16

## Context

The audit log records who did what, when and through which channel ([architecture.md](../architecture.md#handler-and-domain)).
The app offers no way to change or remove an entry, but whoever can write to the database can: through an SQL
injection, a leaked database login, or a host's database tool. Without a check, such a change leaves no trace, and the
log is then worth no more than the database itself.

Erasing a member removes the member's name from the log, and the display name the member was added with. Old entries
are pruned after a retention period. Both are legitimate changes a check must accept.

The app runs on shared hosting, often without SSH, on MariaDB or MySQL, with a few hundred members and tens of
writes a minute at most. Requests that sign up for the same round lock that round ([ADR 0005](0005-database-approach.md)).

## Decision

### What the chain covers

- **Every entry takes the next position** (`chain_position`, unique) and a hash (`chain_hash`) over a fixed JSON list:
  version `1`, position, id, time in UTC in whole seconds, actor id or null, action, subject type and id, channel, the
  hash of the changed fields, and the hash of the entry before it. The position in the hash makes a renumbered or
  swapped entry show.
- **The changed fields have a hash of their own** (`changes_hash`) over id and the stored JSON. The chain hash covers
  that hash, not the fields, so erasing the fields leaves the chain intact.
- **The actor's name is outside the hash**, as erasing replaces it.
- **HMAC-SHA-256 with `AUDIT_SECRET`**, a key of its own outside the database and apart from `APP_SECRET`, so
  replacing `APP_SECRET` leaves the chain intact. Without the key, nobody can make a hash that fits after a change. A
  plain hash would also let anyone with a list of names recover erased fields from their hash.
- **No key, no entry.** The service that hashes refuses an empty key, and the container refuses a missing one, so the
  app never writes an entry the chain cannot check; every change fails instead.

### Writing

- **The entry waits for the end of the outermost transaction.** `Transaction::beforeCommit()` runs as its last step:
  lock the head (row `head` of `audit_chain`, `SELECT ... FOR UPDATE`), take the next position, sign, store, move the
  head. A rollback drops the step, so a failed transaction takes no position.
- **Last, so it cannot deadlock.** A transaction that held the head while it waited for a round could meet one that
  holds the round and waits for the head. Taken last, the head is never held while a transaction waits for another
  lock. It puts all writes in a row for the few milliseconds of the commit, which makes no difference at this scale.
- **The lock reads the committed row**, not the copy in the entity manager, which another process may have moved past.

### Erasing, pruning and old entries

- **Erasing a member** removes the name from the member's entries and the changed fields of every entry about the
  member. The hashes stay, so those entries count as intact, without content to check.
- **Pruning** removes the oldest entries up to a position and moves the anchor (row `anchor`) to the position and
  hash of the last one removed, in the same transaction. The check starts at the anchor.
- **Entries from before the chain** get a position by time and id, without a hash; the anchor and head start behind
  them. The check counts them apart.

### Checking

- **`app:audit:verify`** (`VerifyAuditChain`, for the system only) reads head, anchor and entries in one transaction,
  so from one snapshot, 500 entries at a time. It reports the chain intact through an entry, or the first position
  that does not hold: a changed value, a missing position, an entry inserted or moved, or a head that does not point
  at the last entry. It exits with 1 then, so a cron job or monitor can alert on it.
- **An entry whose hash does not fit is judged by what follows it.** When the next entry, or the head, links to its
  stored hash, a value of the entry changed afterwards; otherwise it was never part of the chain at that place.

## Consequences

- A change, removal or insertion in the database shows, for whoever cannot read `AUDIT_SECRET`.
- **Not covered: the newest entries.** Whoever can write to the database can remove the newest entries and set the
  head back to an earlier entry they know; nothing in the database shows that. The command prints the last position,
  so an admin can note it down outside the server and compare.
- **Not covered: whoever can read the key.** With access to the files, `AUDIT_SECRET` can be read and a fitting chain
  made. The chain guards against a database-only attacker, not a server-wide one.
- A lost `AUDIT_SECRET` leaves the chain impossible to check: it belongs in the backup, apart from the database
  backup. Replacing the key needs a key id per entry or a new anchor under the old key; that is not built.
- The content of an erased entry cannot be checked; an attacker who empties the changed fields of an entry makes it
  look erased. The time, actor, action and subject of that entry are checked all the same.
- Every write takes one more locking read and one update at the end of its transaction.
- Emptying the database for sample data removes head and anchor too; the next entry starts the chain afresh.
