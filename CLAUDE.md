# Kernel Package — Developer Notes

Session log — decisions, bugs found, and why things are the way they are. No `MANUAL.md`
counterpart yet (kernel's own architecture reference doesn't exist as a separate file — this file
carries both history and current-mechanism notes for now).

Backfilled 2026-08-22 from real work already done and documented elsewhere (other packages'
`CLAUDE.md` files, Claude's own memory system) — kernel never had its own doc file before this,
so fixes that genuinely originated here had nowhere to land and got cross-referenced from
whichever package's session surfaced them instead. Entries below keep their original dates; this
file is new, the history isn't.

## 2026-07-31 — transaction shutdown safety net (`bit_shutdown_handler()`)

Built chasing an intermittent wiki "page not found": `BitPage::store()` (see `wiki/CLAUDE.md`)
wraps its save in `StartTrans()`/`CompleteTrans()` with no `RollbackTrans()` fallback — an
exception escaping mid-save (e.g. from a nested `LibertyMime::store()`) left Firebird holding an
open transaction on `wiki_pages`/`liberty_content` indefinitely, no error surfaced anywhere,
symptom only visible as pages silently 404ing until Firebird was restarted.

**Fix, `kernel/includes/bit_error_inc.php`**: `bit_shutdown_handler()` (registered via PHP's
`register_shutdown_function()`) checks `$gBitDb->mDb->transOff` on every request end — fatal
errors, aborted connections, and timeouts all reach a shutdown function even though they skip any
try/catch in the code that opened the transaction. If a transaction was left open, it gets rolled
back there instead of orphaned. **Extended 2026-08-19** (`726d077`) to also check
`$gBitInstaller->mDb->mDb->transOff` — the installer runs before `$gBitDb` is set, using its own
separate connection, so the original handler never covered it (see `project_installer_
transaction_atomicity` memory for the fuller install-cycle atomicity work this was one piece of).

**Gotcha that nearly broke the first version**: check `transOff`, not `transCnt`. This project's
adodb PDO Firebird driver (`externals/adodb/drivers/adodb-pdo.inc.php`) delegates
`beginTrans()` to an internal `$this->_driver` sub-object, so `transCnt` increments there, not on
`$gBitDb->mDb` itself — reading `$gBitDb->mDb->transCnt` from outside always shows 0, even with a
genuinely open transaction. `transOff` doesn't have this problem: `ADOConnection::StartTrans()`
(the base class) sets it directly on whichever object `StartTrans()` was called on. Confirmed
empirically, not assumed from adodb's docs/source — the first draft of this handler checked
`transCnt` and silently never fired; only caught by smoke-testing (print both properties, trigger
a real fatal). See `reference_adodb_pdo_transcnt` memory. Relevant to anyone writing kernel-level
DB code, not just this one handler — the same "outer counter reads 0" trap applies to any future
code trying to detect an open transaction from outside the code that opened it.

**Also fixed the same investigation** (`eddd95d`'s own diff): a fatal error reaching
`bit_shutdown_handler()` now sends a real `HTTP/1.0 500` header before printing "Internal Server
Error", rather than whatever default PHP would otherwise emit.

## 2026-08-11 — `BitBase::__destruct()` unset `mDb` too early, crashed the APCu object cache

Bitweaver's kernel has an APCu-backed singleton object cache (`BitBase::storeInCache()`/
`loadFromCache()`, gated by `BIT_CACHE_OBJECTS`, default `false`). Found live-testing whether the
cache actually reads back on a request (it does — an apparent "`Hits: 0` always" reading in the
APCu admin panel turned out to be a display artifact of `storeInCache()`'s unconditional
`apcu_store()` resetting each entry's own hit-counter on every write, not proof the read never
happened; confirmed via xdebug stepping through `BitSingleton::loadSingleton()` →
`loadFromCache()`).

**Real bug found along the way**: `BitBase::__destruct()` did `unset($this->mDb)` **before**
calling `storeInCache()` — but `storeInCache()` → `getCacheUuid()` → `getCacheKey()` needs a live
`mDb` for any `LibertyContent` subclass whose `getCacheKey()` calls a DB-querying `isValid()`
(`StockAssembly`/`StockMovement`/`StockComponent`/`Contact` — see `liberty/CLAUDE.md`'s
"isValid()" section for why those four specifically have that override). `__sleep()` already
unsets `mDb` at the correct point (called internally by `apcu_store()`/`apcu_add()` during
serialisation) — the eager unset in `__destruct()` was both redundant and the actual crash cause,
`Call to a member function getOne() on null`. **Already live in production, unrelated to
anything done that session**: srv10's php-fpm log showed 4032 occurrences via
`Contact::isValid()` and 13 via `StockMovement::isValid()`, dating back to the 2026-08-10 deploy
of the four classes' `isValid()` fix (which is what first gave `getCacheKey()` a reason to need
`mDb` at destruct time — the destructor bug was latent long before that, just never triggered).

**Fix**: removed the premature `unset()` from `__destruct()` (`893a876`), deployed to srv9+srv10
same session, confirmed no new occurrences in the live php-fpm log afterward. Full chain in
`project_apcu_object_cache_stale_assets` memory (also covers an unrelated, earlier themes-package
cache-poisoning bug in the same investigation).

## 2026-08-22 — `BitDate` mutated the global ambient timezone in display conversions

Found chasing a Food report ("today's breakfast has migrated an hour"). `BitDate::
getDisplayDateFromUTC()`/`getUTCFromDisplayDate()` used to call `date_default_timezone_set()` to
do the UTC↔local conversion, with no restore afterward — silently mutating PHP's *global* ambient
default timezone for the rest of the request. Not just a `BitDate`-internal issue: any later bare
`strtotime()`/`date()`/`mktime()` call anywhere in the same request — any package, not just the
one that happened to call `BitDate` first — would silently re-interpret its input against
whatever timezone the last `BitDate` call left behind, rather than the server's real default.

**Fix** (`3a71379`): construct a `DateTimeZone` directly and pass it into `DateTime`'s
constructor instead of touching global state at all. `gmmktime()` (immune to this class of bug by
construction, since it never consults the ambient timezone) is now the house convention for any
callsite doing day-boundary arithmetic in the same request as a `BitDate` call — see
`food/MANUAL.md`'s "Time storage" section for a worked example (`edit_assembly.php`'s day-start
computation). Deployed to desktop + srv9; **srv10 still needs this** — its `kernel` checkout was
last confirmed at `8620d29`, before this fix. Full investigation in `project_food_bst_timestamp_
fix` and `reference_firebird_clock_and_bitweaver_tz` memories.

**Why this had nowhere to land before today**: no `kernel/CLAUDE.md` existed, so this fix
originally went into the top-level `bitweaver/CLAUDE.md`'s session log instead (see that file's
2026-08-22 entry, now trimmed to a pointer here).
