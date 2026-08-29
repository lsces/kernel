# Date/Time/Timezone — Reference

How date/time actually works across this stack today — storage, display modes, the render path,
and the adodb-derived formatting engine underneath it. For the history of *why* — bugs found,
investigation trails, wrong turns — see `CLAUDE.md`'s dated session log and the linked memories
instead; this file only tracks current behaviour and known gaps.

## Storage layer

**Firebird's own clock is local (BST), not UTC.** `CURRENT_TIMESTAMP` reads `Europe/Isle_of_Man`
wall-clock time, same as the OS. `BitDb::NOW()` (`kernel/includes/classes/BitDb.php`) knows this
and deliberately never uses Firebird's own `NOW()`/`CURRENT_TIMESTAMP` for firebird/pdo — it
substitutes `$gBitSystem->getUTCTimestamp()` (PHP's own `time()`, timezone-independent) instead.
So `entry_date`/`last_update_date`/`created`/`last_modified` defaults are genuinely correct UTC
throughout, despite the DB server's own clock not being UTC.

**Firebird 4's `WITH TIME ZONE` column type is deliberately avoided.** This stack sticks with
plain `TIMESTAMP` (naive, always-UTC-by-convention) and epoch-int (`I8`) columns — never the
newer tz-aware type. Don't introduce `WITH TIME ZONE` anywhere without checking first; it's a
known-bad experience specifically, not just an unfamiliar option.

### `liberty_content` vs `liberty_xref` — two schema generations, two conventions

`liberty_content` (`liberty/admin/schema_inc.php:21-38`): `content_id I4 PRIMARY`, and three
separate `I8` epoch-int date fields — `created`, `last_modified`, `event_time`.

`liberty_xref` (`liberty/admin/schema_inc.php:244-257`): `xref_id I8 PRIMARY`, `content_id I8
NOTNULL` (a genuine width mismatch against the `I4` it references — not yet an observed problem,
Firebird widens implicitly on join, but worth knowing), and four native Firebird `T` (TIMESTAMP)
columns: `entry_date`, `last_update_date`, `start_date`, `end_date`. This is why `liberty_content`
shows bare numbers in FlameRobin and `liberty_xref` shows real date/time — different storage
convention across the two schema generations, not a display quirk.

Column-by-column mapping:

- **`liberty_content.created` ↔ `liberty_xref.entry_date`** — same concept (creation stamp), same
  intended behaviour: always auto-stamped with the current UTC time by the object itself, never
  meant to be passed manually in normal operation. `LibertyXref::store()`
  (`liberty/includes/classes/LibertyXref.php:253-258`) accepts an explicit override, but that path
  exists specifically for historic imports backdating to a source record's real original
  timestamp — not a general-purpose parameter.
- **`liberty_content.last_modified` ↔ `liberty_xref.last_update_date`** — same pairing, same
  auto-not-manual rule, same import-backdating exception (`LibertyXref.php:260-263`).
- **`liberty_content.event_time`** has no direct xref equivalent — it's a user-facing, user-editable
  business timestamp ("when did this thing actually happen"), not an audit-trail stamp.
  `liberty_xref`'s closest analogue is `start_date`/`end_date`, but those describe a *validity
  window* (see `liberty/MANUAL.md`'s negative-`multiple` section for the stepping-xref pattern that
  uses them), not a single event instant — not really the same concept even though both are
  "a date the user sets."

### `LibertyXref::store()`'s `start_date`/`end_date` — dual parameter semantics (real footgun)

Unlike `entry_date`/`last_update_date` (plain UTC either way — `gmdate()` for an int, `NOW()`
default, no display-timezone involvement at all), `start_date`/`end_date`
(`LibertyXref.php:267-291`) mean two different things depending on caller input type:

- **Pass an int** (epoch) → clean UTC `gmdate('Y-m-d H:i:s', $d)` conversion.
- **Pass a string** (e.g. from a `start_Month/Day/Year/Hour/Minute` form submission) → assumed to
  be in the *viewer's current display timezone*, run through
  `$gBitSystem->mServerTimestamp->getUTCFromDisplayDate()` — subject to `'Local'` mode's
  single-instant-offset limitation (see below).

Know which one you're passing. A caller that doesn't realise this distinction can silently get a
value reinterpreted through a viewer's display offset when it expected a literal UTC conversion,
or vice versa.

### `I8` epoch-seconds is the dominant convention codebase-wide — `liberty_xref` is the outlier

Audited every package's `schema_inc.php` (2026-08-29) on the assumption there might be a handful
of stray `I8` timestamp columns outside `liberty_content`. There are far more than a handful —
`I8` epoch-seconds is the established norm almost everywhere, and `liberty_xref`'s native
`TIMESTAMP` columns are the exception, not a pattern that's spreading:

| Package | `I8` date/time columns |
|---|---|
| `liberty` (beyond `liberty_content`) | `last_modified` (×2 tables), `last_hit`, `liberty_process_queue`'s `queue_date`/`begin_date`/`end_date` |
| `users` | `provpass_expires`, `registration_date`, `created`, `connect_time`, `last_login` (×2 tables), `current_login`, `pass_due`, `last_get` |
| `newsletters` | `subscribed_date`, `unsubscribe_date`, `error_date`, `queue_date` (×2 tables), `send_date`, `begin_date`, `sent_date`, `last_read_date`, `last_sent` |
| `messages` | `msg_date` |
| `fisheye` | `photo_date` |
| `rss` | `last_updated` (×2 tables) |
| `themes` | `cache_time` |
| `articles` | `created` |
| `languages` | `created`, `last_modified` |
| `tags` | `tagged_on` |
| `stats` | `last` (and `stats_day`, possibly a day-bucket key rather than a true timestamp — not confirmed) |

So "switch to proper `TIMESTAMP` fields," if ever undertaken, isn't a `liberty_content`-scoped
change — it's a whole-codebase schema migration touching roughly a dozen packages. Conversely,
bringing `liberty_xref` *into* the `I8` convention (rather than the other way round) is the
smaller, norm-consistent direction — see the Year-2038 finding below for why `I8` specifically
(not `TIMESTAMP`) is the safe target either way.

### Real, active bug: `I4`-typed date columns overflow in 2038 — not theoretical, already hit

Separate from the `I8`/`TIMESTAMP` convention question above: several packages use plain `I4`
(32-bit signed integer) for columns that get set to a *future* date, which genuinely overflows —
max signed 32-bit value is 2,147,483,647 = 19 January 2038. Past that, the value cannot be stored
at all, not a rounding or display issue. Found by the same schema audit, searching for `I4`
instead of `I8`:

| Package | `I4` date/time columns | Risk |
|---|---|---|
| `blogs` | `publish_date`, `expire_date`, `date_added` | **Confirmed hit live** — setting a blog post's `expire_date` past 2038 overflows |
| `articles` | `publish_date`, `expire_date` | Same schema shape as `blogs` (clearly copied from it) — same latent bug, not yet hit |
| `search` | `last_update`, `last_updated` | Lower risk — always stamped "now," not user-set to a future date |
| `boards` | `track_date`, `notify_date` | `notify_date` may be user/system-set to a future reminder — worth checking |

**`I8` (64-bit) has no such limit anywhere in this codebase** — the whole `liberty_content` family
is already safe. Native `TIMESTAMP` would also be 2038-safe (Firebird's range is 0001–9999 AD, no
epoch dependency at all) but is the larger convention-change direction, not the minimal fix for
this specific active bug — `I8` widening is the fix actually being shipped.

**Fix written 2026-08-29, `admin/upgrades/5.0.1.php` in each of `blogs`, `search`, `articles`,
`boards`** (all four were still at base version `5.0.0`, so `5.0.1` is each package's first real
upgrade) — `schema_inc.php` updated to `I8` and a matching `ALTER TABLE ... ALTER COLUMN ... TYPE
BIGINT` upgrade script added for: `blogs.blog_posts.publish_date`/`expire_date`,
`blogs.blogs_posts_map.date_added`, `search.search_index.last_update`,
`search.search_syllable.last_used`/`last_updated`, `articles.articles.publish_date`/`expire_date`,
`boards.boards_tracking.track_date`/`notify_date`. Verified via the real installer upgrade flow
(admin packages page, not manual `isql`) on desktop's `myhomecloud` 2026-08-29 — applied cleanly,
all ten target columns confirmed `BIGINT`. Committed and pushed to all four packages' repos.
**Not yet deployed to srv9/srv10** — desktop-only so far, needs `server-pull-all.sh blogs search
articles boards` (then the installer's own upgrade-detection, which runs automatically) when
ready to go live. Considered and rejected: doing this via adodb's own datadict `ChangeTableSQL()`
instead of raw SQL for portability — traced its implementation and found it silently generates no
SQL at all when given the plain field-definition string every other schema/upgrade file in this
codebase uses (the diffing logic only runs `if (is_array($flds))`; `createTableSQL()` parses that
string via `_genFields()` first, but `applyUpgrade()`'s `'ALTER'` case never does the equivalent
before calling `changeTableSQL()`) — a real, seemingly-never-exercised gap in `BitInstaller.php`
(zero working `'ALTER'`/`'DATADICT'` examples exist anywhere in this codebase, only `'CREATE'`).
Not fixed — this stack only ever runs Firebird in practice, so raw `'QUERY'`/`'SQL92'` (already
proven, matching the one existing precedent in `liberty/admin/upgrades/5.0.2.php`) was the
pragmatic call rather than debugging blind on a code path nothing else here has ever used.

**`content_id` widening deliberately deferred, real lesson learned testing it**: `blog_posts`/
`blogs`/`search_index` etc. all have their own `content_id` (`I4`) referencing `liberty_content
.content_id` (`I4 PRIMARY`) — same 32-bit ceiling, longer runway (~2.1 billion rows) than the date
columns, looked like a reasonable one-more-thing to fix while touching these tables. It isn't a
small addition: **Firebird's `ALTER COLUMN TYPE` will widen `INTEGER`→`BIGINT` but refuses to
narrow `BIGINT`→`INTEGER` at all** ("Conversion from base type BIGINT to INTEGER is not
supported") — there is no simple way back once a referencing column is widened. Attempting it
live also surfaced that a `content_id` FK can't be recreated pointing at a still-`INTEGER`
`liberty_content.content_id` ("partner index segment has incompatible data type") — so widening
even one leaf table's `content_id` is not actually independent of every other table that
references `liberty_content`; it's a coupled, whole-codebase change or nothing, not something to
slip in package-by-package. Reverted by restoring desktop's `myhomecloud` from that morning's
local backup (`firebird-restore myhomecloud`) rather than trying to hand-reconstruct the original
state. Left as `I4` in all four packages' `schema_inc.php`/upgrade scripts for now — a real future
item, but its own separate, carefully-scoped piece of work, not a rider on the date-column fix.

## Display modes: `UTC` / `Local` / `Fixed`

`BitDate::get_display_offset()` (`kernel/includes/classes/BitDate.php:61-84`) reads
`$gBitUser->getPreference('site_display_utc', "Local")` — default `'Local'` for any account that
hasn't explicitly switched to `'Fixed'` in Preferences. This applies equally to anonymous
visitors: they get a real `RolePermUser` loaded for `ANONYMOUS_USER_ID`
(`users/includes/bit_setup_inc.php:128`), never null, and since nobody can ever save a preference
*as* the anonymous account, they always fall through to the `'Local'` default.

**`'Local'` mode**: `tz_offset` cookie, set client-side by `BitBase.init()`
(`themes/js/bitweaver.js:71-78`, called unconditionally at module load,
`themes/js/bitweaver.js:1264`, on every page that loads this near-universal script) —
`self.setCookie("tz_offset", -(self.DATE.getTimezoneOffset() * 60))`. This genuinely fires for any
JS-enabled browser from the second page load onward (an earlier investigation wrongly concluded
this cookie was never set anywhere — it is; the earlier grep just missed `themes/js/`).

**The permanent limitation of `'Local'` mode, not a bug to chase**: JS's `getTimezoneOffset()`
only ever returns a raw minutes-from-UTC number for the instant it's called — no IANA zone
identity attached, no way to derive correct DST behaviour for any date other than "right now." The
cookie refreshes each page load with *today's* offset, then that single number gets applied
blindly to every timestamp shown on the page, regardless of which date it's actually from (a July
record viewed in December gets December's GMT offset, not July's BST — off by an hour). There is
no way to recover a browser visitor's actual named timezone without them being logged in and
having manually set `'Fixed'`. **Explicitly out of scope to fix** — accept it, don't rebuild
around it.

**`'Fixed'` mode**: a real IANA zone name in `site_display_timezone`. This is the only mode with
genuine per-date DST correctness, and the only mode real (logged-in) users should be steered
toward if timezone accuracy matters to them.

### Where rendering actually happens

The real per-page date-rendering path used by virtually every `.tpl` template is the
`bit_date_format` Smarty modifier (`themes/smartyplugins/modifier.bit_date_format.php`), not
`BitDate` called directly:

- **`'Fixed'` mode** (lines 40-51): its own separate conversion — `date_default_timezone_set(
  $gBitUser->getPreference('site_display_timezone', 'UTC') )` then a plain `new DateTime($pString)`.
  This is **structurally necessary**, not just careless duplication — see "The adodb formatting
  engine" below for why.
- **`'Local'`/default `'UTC'`** (lines 52-60): `$gBitSystem->get_display_offset()`, a thin proxy
  (`kernel/includes/classes/BitSystem.php:2544-2545`) into `BitDate::get_display_offset()` — the
  tz_offset-cookie-or-zero path above.

## `RoleUser::getUserTimezone()` — the real user's own zone, kernel-level

`users/includes/classes/RoleUser.php`, added right after `defaults()` (next to that method's own
dead/commented-out `site_display_timezone` default block, which had already flagged this exact
gap without anyone following through):

```php
public function getUserTimezone(): \DateTimeZone {
	$tzName = $this->getPreference( 'site_display_timezone', 'UTC' );
	if( empty( $tzName ) ) { $tzName = 'UTC'; }
	try {
		return new \DateTimeZone( $tzName );
	} catch ( \Exception $e ) {
		return new \DateTimeZone( 'UTC' );
	}
}
```

Available on `$gBitUser` from any package, no include needed. **Use this instead of hardcoding a
place name anywhere code needs a real named zone** (parsing offset-less wall-clock input,
calendar-day bucketing for aggregation, anything that wants "the actual logged-in user's own
zone" rather than a display-mode-dependent offset). `UTC` fallback, never a hardcoded place.

First real caller: the health package had 20 `new DateTimeZone('Europe/London')` call sites
across its import and display code (Samsung/HealthForYou data), all switched to
`$gBitUser->getUserTimezone()` — see `health/CLAUDE.md`'s matching 2026-08-29 entry for the
worked example, including the distinction between "bucketing an already-UTC epoch into local
calendar days" (most call sites) and "genuinely parsing offset-less wall-clock input"
(`ImportWT.php`, the one real exception — both want the real user's zone, just for different
reasons).

## The adodb formatting engine — what it is, and its real limits

`BitDate::strftime()` → `date()` → `_getDate()` (`BitDate.php:594`) is built on adodb's
`adodb-time.inc.php` (symlinked in via `externals/adodb`, the real repo lives at
`~/Development/adodb`, not inside the bitweaver checkout itself). When `$is_gmt` isn't explicitly
passed `true`, `_getDate()` calls `adodb_get_gmt_diff(false,false,false)`
(`adodb-time.inc.php:726-756`) — whose live code path (the `DateTimeZone`-based branch is dead,
gated behind `ADODB_TEST_DATES` which is never defined) computes `mktime(...) - gmmktime(...)`,
i.e. **reads PHP's ambient default timezone**. There is no other parameter anywhere in this chain
to receive a zone. This is *why* the Smarty modifier's `'Fixed'` branch calls
`date_default_timezone_set()` — it's the only channel by which a Fixed-mode user's chosen zone
ever reaches this formatter. Removing it without giving the formatter chain a real
`DateTimeZone` parameter would silently degrade every Fixed-mode user to the server's own
configured PHP timezone.

**adodb's own file header undercuts the reason it was ever pulled in**:
`@deprecated 5.22.6 Use 64-bit PHP native functions instead` — it existed to work around PHP's
*old* 32-bit-signed-integer `date()`/`mktime()` year-2038 limitation, moot on any 64-bit PHP build
(this stack's). Its own documented/tested range is "100 A.D. to 3000 A.D." (years below 100 hit
ambiguous 2-digit-year conversion) — **it was never actually built or tested for genuine BC
dates**, despite the impression its size/complexity gives. Tested directly: native `DateTime`
parses/round-trips negative-year dates (`-0100-01-01`) fine, at least as well as adodb.

**The one real, measurable difference**: adodb documents and correctly implements the 1582
Julian→Gregorian calendar jump (`adodb_mktime(Oct 15 1582) - adodb_mktime(Oct 4 1582) == 1 day`).
Native `DateTime` does not — `(new DateTime('1582-10-15'))->diff(new DateTime('1582-10-04'))`
gives 11 days, pure proleptic Gregorian, no historical correction at all. But adodb's correction is
hardcoded to the *Catholic-Europe* 1582 transition — Britain/Isle of Man didn't adopt Gregorian
until 1752 — so adodb's own "historical accuracy" is also wrong for genuinely British/Manx
genealogical records. Neither library gets this specific case right out of the box.

## Genealogical dates belong in webtrees, not here

`webtrees` (this machine's genealogy application, ported to Firebird via `illuminate-firebird` —
see `[[project_webtrees]]` memory) fully stripped adodb (confirmed: no reference anywhere in its
`composer.json` or code). It replaced it not with bare `DateTime`, but with
`fisharebest/ext-calendar` (composer, v2.6.0, written by webtrees' own lead developer) — a portable
pure-PHP reimplementation of PHP's compiled `calendar` extension
(`gregoriantojd()`/`jdtogregorian()`-equivalent), used because the real compiled extension isn't
reliably available across hosts. On top of that, webtrees built its own `app/Date/` class
hierarchy — `GregorianDate`, `JulianDate`, `JewishDate`, `HijriDate`, `RomanDate`, `JalaliDate`,
`FrenchDate` (French Republican calendar), all extending `AbstractCalendarDate` — every date
explicitly tagged with *which calendar it was recorded in* (matching GEDCOM's own
`@#DJULIAN@`/`@#DHEBREW@`/etc. convention), converted to/from a Julian Day Number as the universal
calendar-agnostic interchange value. `convertToCalendar()` does explicit, deliberate conversion —
never silent normalization.

**Working conclusion**: `DateTime` is, in practice, another "post-1970-shaped" tool — it parses
old dates without erroring, but has no real multi-calendar-system awareness. Part of why webtrees
was ported to run on Firebird in the first place may have been exactly this — to keep genuinely
historical/genealogical material in the tool that actually handles it properly, rather than ever
needing `liberty_content`/`BitDate` to become calendar-system-aware itself. Under that framing,
`BitDate`'s real job is narrower than it first looks: modern audit-trail timestamps and business
timestamps that are inherently recent/ongoing (`event_time` — food/health data, never genuinely
ancient) — a domain `DateTime` is entirely adequate for.

## Open — not yet started

**Strip adodb from `BitDate` in favour of `DateTime`**, now that the genealogical-accuracy
requirement is understood to be webtrees' job, not this file's. Requires, in order:
1. Give `BitDate`'s formatter chain (`strftime()`/`date()`/`_getDate()`) a real `DateTimeZone`
   parameter, replacing the ambient-global-mutation trick.
2. Once that exists, the Smarty modifier's `'Fixed'` branch can drop `date_default_timezone_set()`
   entirely and use `$gBitUser->getUserTimezone()` directly.

Not scoped in detail yet — every real call site of `strftime()`/`date()`/`_getDate()` across the
codebase needs mapping first, since some may depend on the current `$is_gmt`-boolean-only
behaviour in ways not yet audited.
