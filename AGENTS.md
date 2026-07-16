# AGENTS.md — FlightPHP Active Record

Guidance for AI agents and contributors working in this repository.

This package follows the same philosophy as [flightphp/core](https://github.com/flightphp/core). Ideologies below are adapted from core’s project guidelines and applied where they fit an ORM plugin (not the framework kernel).

## Overview

**flightphp/active-record** is a micro Active Record library: map a database row to a PHP object with chainable queries, relations, events, and PDO/mysqli adapters.

- **Package:** `flightphp/active-record` (Packagist)
- **License:** MIT
- **PHP:** `>=7.4` (PHP 8+ supported; do not require 8-only syntax)
- **Runtime dependencies:** none (only PHP itself)
- **Namespace:** `flight\` (PSR-4 → `src/`)
- **Docs:** https://docs.flightphp.com/en/v3/awesome-plugins/active-record
- **README:** [README.md](./README.md) (short intro; full API is in the docs)

Works standalone or with [Flight PHP](https://docs.flightphp.com). It does **not** require Flight core at runtime.

## Project guidelines

These are the Flight ecosystem rules that apply to this repo. Prefer them over inventing framework-heavy patterns.

1. **PHP 7.4 must stay supported.** PHP 8+ is fine, but avoid PHP 8-only features (union types, constructor property promotion, `match`, named arguments in library code, mixed union returns, etc.) unless the project explicitly raises the minimum version.

2. **Stay dependency-free at runtime.** Do not add Composer runtime dependencies, polyfills, or interface-only packages “for cleanliness.” Dev tools (PHPUnit, PHPCS, runway, coverage-check) are fine under `require-dev`.

3. **Simple and fast.** Flight projects prioritize performance and a small surface area. Prefer fewer allocations, fewer queries (e.g. eager load over N+1), and straightforward SQL building over clever abstractions.

4. **Do not bloat the library.** New capability should earn its place. Prefer a small, composable API over a kitchen-sink ORM. If something is large or optional (CLI scaffolding, etc.), keep it in a clear corner (`commands/`, runway) rather than growing `ActiveRecord` forever.

5. **This is not Eloquent / Doctrine / Laravel / Yii.** It is a micro Active Record: chainable conditions, light relations, events, and adapters. Do not smuggle in migrations, unit-of-work, attribute mapping layers, repositories-as-required-pattern, nested graph loaders, or other large-framework defaults unless there is a clear, minimal design that fits existing style.

6. **New features must be documented and tested.** Public behavior needs tests (this repo enforces **100%** `src/` line coverage) and accurate docs/docblocks. User-facing API changes should remain valid against the [public docs](https://docs.flightphp.com/en/v3/awesome-plugins/active-record).

7. **Simplicity over cleverness.** Magic (`__call`, `__get`, `__set`) already exists for the query/relation ergonomics—do not add more layers of indirection without a strong reason. Prefer readable condition building and explicit relation config.

8. **Extensibility without core bloat.** Prefer extension points users already have: subclass methods (events), `$relations`, `DatabaseInterface` adapters, `setCustomData`, chainable query methods. Avoid hard-wiring Flight framework services into the library.

## Repository layout

```
src/
  ActiveRecord.php          # Main abstract model — CRUD, queries, relations, eager load, events
  ActiveRecordData.php      # OPERATORS, SQL_PARTS, DEFAULT_SQL_EXPRESSIONS, EVENTS
  Base.php                  # Attribute bag ($data) with magic __get/__set
  Expressions.php           # SQL fragment value object
  WrapExpressions.php       # Parenthesized expression groups (OR/AND wraps)
  database/
    DatabaseInterface.php
    DatabaseStatementInterface.php
    pdo/                    # PdoAdapter, PdoStatementAdapter
    mysqli/                 # MysqliAdapter, MysqliStatementAdapter
  commands/
    RecordCommand.php       # runway CLI: make:record (scaffold model from table schema)
tests/
  ActiveRecordTest.php
  ActiveRecordPdoIntegrationTest.php
  ActiveRecordMysqliTest.php
  EagerLoadingTest.php
  ExpressionsTest.php
  WrapExpressionsTest.php
  commands/RecordCommandTest.php
  classes/                  # Fixtures: User, Contact, QueryCountingAdapter
records/                    # Empty placeholder (app models go under runway app_root)
```

`coverage/` and `clover.xml` are generated (gitignored). Do not hand-edit them.

## Core architecture (read before changing behavior)

### Model lifecycle

1. User subclasses `flight\ActiveRecord` and passes a DB connection + table name (or config) in the constructor.
2. Raw `PDO` / `mysqli` is wrapped by `transformAndPersistConnection()` into a `DatabaseInterface` adapter.
3. Property assignment goes through `__set` → `$data` + `$dirty` (unless the key is an SQL expression or relation).
4. Query methods (`eq`, `like`, `orderBy`, …) are magic via `__call` → `ActiveRecordData::OPERATORS` / `SQL_PARTS`.
5. `find` / `findAll` / `insert` / `update` / `delete` build SQL with named placeholders (`:phN` from `ActiveRecordData::PREFIX`), then execute through the adapter.
6. Events (`beforeFind`, `afterInsert`, etc.) are optional protected methods on the subclass, listed in `ActiveRecordData::EVENTS`.

### Relationships

```php
protected array $relations = [
    'contacts' => [ self::HAS_MANY, Contact::class, 'user_id' ],
    'contact'  => [ self::HAS_ONE,  Contact::class, 'user_id', [/* optional callbacks */] ],
    'user'     => [ self::BELONGS_TO, User::class, 'user_id', [], 'back_ref_name' ],
];
```

- `HAS_MANY` / `HAS_ONE`: third element is the **foreign key on the related table**.
- `BELONGS_TO`: third element is the **local key** on the current model.
- Optional 4th: callback map when resolving the relation (`eq`, `where`, `order`, …).
- Optional 5th: back-reference property name.

**Lazy load:** `$user->contacts` → `getRelation()`.  
**Eager load:** `$user->with('contacts')->findAll()` → `loadEagerRelations()` batches with `IN (...)`.

Eager-load limitations (do not “fix” without an intentional, minimal design):

- No nested paths like `with(['contacts.addresses'])`
- No closure constraints on `with()`
- Unknown relation names throw `Exception`

### Database adapters

| Input type | Adapter |
|------------|---------|
| `PDO` | `flight\database\pdo\PdoAdapter` |
| `mysqli` | `flight\database\mysqli\MysqliAdapter` |
| `DatabaseInterface` | used as-is |

mysqli converts named placeholders to `?`. Keep SQL/placeholder logic driver-agnostic when possible; put driver quirks in the adapter layer.

### runway command

`src/commands/RecordCommand.php` implements `make:record` for [flightphp/runway](https://docs.flightphp.com/awesome-plugins/runway). It introspects a table and writes a record class under `app_root` from `.runway-config.json`. Tests live under `tests/commands/`.

## Development & testing

```bash
composer install

composer test              # phpunit (random order, stop on failure)
composer test-coverage     # HTML + clover; enforces 100% via coverage-check
composer beautify          # phpcbf --standard=phpcs.xml
composer phpcs             # phpcs -n --standard=phpcs.xml
```

**Coverage:** `src/` is expected to stay at **100%** line coverage. New branches need tests. Run `composer test-coverage` before considering work complete.

- PHPUnit: `phpunit.xml` — suite = `tests/`, coverage = `src/`
- Style: **PSR-12** via `phpcs.xml` on `src/` and `tests/` (this package uses PSR-12, not core’s PSR-1)
- Prefer `declare(strict_types=1);`
- Use **strict comparisons** (`===`, `!==`)

### Testing conventions

- Prefer **SQLite in-memory** (`sqlite::memory:`) for integration tests (`ext-pdo_sqlite` is required-dev).
- Model fixtures: `tests/classes/` (`User`, `Contact`, …). Match real relation configs when testing relations/eager load.
- Use `QueryCountingAdapter` to assert query counts (N+1 vs eager load).
- Keep PDO vs mysqli differences in their own tests/adapters.
- Fixtures are often `require_once`’d in `setUpBeforeClass`—follow that pattern unless you also add proper `autoload-dev`.

## Coding standards (library-specific)

Apply the project guidelines first; then these details when editing:

1. **Chainable API.** Query builders and fluent mutators return `$this` / `self`.
2. **Security.**
   - Condition **values** (`eq`, `in`, `like`, …) are bound as parameters — safe for untrusted data.
   - **Identifiers** go through `escapeIdentifier()` (delimiter-escaped per engine). Do not weaken this.
   - `where()`, `having()`, `select()`, `order()`/`orderBy()`, `group()`, and `join()` **ON** accept raw SQL — never pass untrusted input. Prefer `eq()`/`in()`/… for filters and `orderByColumn()` for untrusted sort columns.
   - Simple table names / `table alias` / `table AS alias` in `join()` are auto-quoted; complex join sources stay raw for BC.
   - `copyFrom()`/`dirty()` keys become column names — only pass trusted keys.
3. **Dirty tracking.** Inserts/updates persist `$dirty` only. Preserve that contract when changing assignment or `save()`.
4. **Events.** Use `processEvent` and the names in `ActiveRecordData::EVENTS`. Keep hook signatures compatible with docs.
5. **Public API is the contract.** Prefer additive changes. Keep README/docs examples working. New safe helpers (e.g. `orderByColumn`) are fine; do not change semantics of existing raw SQL methods for complex expressions.
6. **Performance-conscious defaults.** Eager load exists to cut N+1; avoid designs that force per-row queries without an opt-in path.
7. **No framework lock-in.** Do not require `Flight::` inside library code; examples in docs may show Flight registration for apps.

## Documentation sources

| Resource | Use for |
|----------|---------|
| [Active Record docs (v3)](https://docs.flightphp.com/en/v3/awesome-plugins/active-record) | Full public API, events, relations, eager loading, connections |
| [README.md](./README.md) | Install + basic example |
| [flightphp/core copilot instructions](https://github.com/flightphp/core/blob/master/.github/copilot-instructions.md) | Shared Flight ecosystem philosophy |
| Packagist `flightphp/active-record` | Version / install metadata |

User-visible behavior changes should stay aligned with the docs site when possible; at minimum keep in-repo comments and tests accurate.

## Common tasks (agent playbook)

| Goal | Where to look |
|------|----------------|
| Query operator / SQL part mapping | `ActiveRecordData.php`, `__call` in `ActiveRecord.php` |
| find / findAll / insert / update / save / delete | `ActiveRecord.php` public methods |
| Relation lazy load | `getRelation()` |
| Eager load | `with()`, `loadEagerRelations()`, `assignEagerLoadedRelations()` |
| Events | `processEvent()`, `ActiveRecordData::EVENTS` |
| PDO/mysqli bugs | `src/database/**` + matching tests |
| Scaffold CLI | `src/commands/RecordCommand.php` |
| N+1 regression | `tests/EagerLoadingTest.php` + `QueryCountingAdapter` |

## PR / contribution checklist

- [ ] Change fits **project guidelines** (simple, fast, no bloat, PHP 7.4-safe, no new runtime deps)
- [ ] `composer test` passes
- [ ] `composer test-coverage` still meets 100%
- [ ] `composer beautify` && `composer phpcs` clean
- [ ] Public API covered by tests; docs/docblocks updated if behavior changed
- [ ] No secrets or local `.runway-config.json` credentials committed (gitignored)

## Out of scope / non-goals

- Becoming a full ORM (migrations, schema builder, unit of work, nested eager-load graphs, polymorphic relations by default)
- Runtime dependency on Flight core or other frameworks
- Dropping PHP 7.4 support without an explicit project decision
- “Just like Laravel Eloquent” feature parity
