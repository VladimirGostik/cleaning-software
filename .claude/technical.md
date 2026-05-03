# CleanMaster — Technical context

Companion to `business.md`. This file documents non-obvious technical decisions an LLM/contributor needs to know **before writing code**. Generic conventions belong in `CLAUDE.md`'s "Architecture invariants" section; this file captures the *why*.

## Why these versions

- **PHP 8.5 in Docker** — host has 8.4. Laravel 13 requires `^8.5`. We chose Docker over `brew install php@8.5` so the toolchain is reproducible and CI matches local without per-developer setup. Composer/Pint/PHPStan/artisan all run inside `php:8.5-cli` via `docker compose run --rm app …`.
- **Vite 7 (not Vite 8)** — Vite 8 ships with Rolldown which has a known issue importing DaisyUI 5's CSS through the Tailwind 4 plugin (`Unknown file extension ".css"` from Node ESM loader). Vite 7 is stable.
- **DaisyUI 5 + Tailwind 4** — declared via `@plugin 'daisyui'` in `resources/css/app.css`, theme via `@plugin 'daisyui/theme' { name: 'cleanmaster'; … }` with OKLCH tokens. Tailwind 4 no longer needs `tailwind.config.js`; everything is CSS-driven via `@theme` and `@source` directives.
- **Spatie Activitylog v5** — note the namespace shift: `Spatie\Activitylog\Models\Concerns\LogsActivity` (not `Traits`), `Spatie\Activitylog\Support\LogOptions` (not root). Method `dontSubmitEmptyLogs()` was renamed to `dontLogEmptyChanges()`. Keep `activity_log.id` as bigint and `causer_id`/`subject_id` as **strings** to support polymorphic morphs across mixed-PK models (Role bigint, User UUID).
- **Spatie Laravel-Data v4 + Laravel-TypeScript-Transformer v3** — version mismatch: `Spatie\LaravelData\Support\TypeScriptTransformer\DataTypeScriptTransformer` references `Spatie\LaravelTypeScriptTransformer\Transformers\DtoTransformer` which was removed. Use `Spatie\LaravelTypeScriptTransformer\LaravelData\Transformers\DataClassTransformer` directly instead. See `app/Providers/TypeScriptTransformerServiceProvider.php`.
- **`spatie/laravel-typescript-transformer` v3** — does not ship a `config/typescript-transformer.php`; configuration happens via the published `App\Providers\TypeScriptTransformerServiceProvider`. The config-file paths some docs reference are outdated.

## Multi-tenancy implementation choices

We deliberately **did not** install `stancl/tenancy` or another multi-tenancy package because the spec says: *"Tenant izolácia je riešená cez BelongsToTenant trait s global scope — každý query automaticky filtruje podľa tenant_id."* So:

- `App\Concerns\BelongsToTenant` (trait) auto-applies `App\Scopes\TenantScope` and auto-fills `tenant_id` on `creating` from `app('current_tenant_id')`.
- `App\Http\Middleware\TenantContextMiddleware` populates `app('current_tenant_id')` per request from the session and tells `Spatie\Permission\PermissionRegistrar::setPermissionsTeamId()` so role/permission lookups stay scoped.
- The User model itself does **not** use `BelongsToTenant`. Users are global; tenancy is expressed via `tenant_memberships`.

To bypass the global scope (rare — typically only in jobs/console where there is no active user): `Model::withoutGlobalScope(\App\Scopes\TenantScope::class)`.

## Spatie Permission with teams = tenant_id

Configured in `config/permission.php`:
```php
'role'             => App\Models\Role::class,
'team_foreign_key' => 'tenant_id',
'teams'            => true,
```

UUID model_id was wired by editing the published migration `2026_05_03_194335_create_permission_tables.php`: `model_morph_key` and `team_foreign_key` columns are `uuid()` not `unsignedBigInteger()`. The `permissions.id` and `roles.id` columns themselves remain bigint (Spatie internal — never exposed in URLs or DTOs).

In code, **always** call `app(PermissionRegistrar::class)->setPermissionsTeamId($tenantId)` before role/permission ops outside an HTTP request (seeders, queue workers, console commands, tests). Forgetting this returns "permission not found" errors.

## DTO / Service / Controller pattern

- **Controllers** are thin — type-hint a DTO param, type-hint a service in the constructor, return `redirect()->with(...)` or `Inertia::render(...)`.
- **DTOs** live in `app/Data/`, extend `Spatie\LaravelData\Data`, are `final`, validation via attributes (`#[Required]`, `#[Email]`, `#[Min]`). Carry a `#[TypeScript]` attribute so they are picked up by `php artisan typescript:transform`.
- **Services** live in `app/Services/`, are `final readonly class XxxService`. Bind as **singletons** in `AppServiceProvider::register()` (only when one is added — none yet for v0.1 base).
- **Models** use `#[Fillable]`, `#[Hidden]`, `#[Cast]` PHP attributes, declare `final`, are `declare(strict_types=1)`. `casts(): array` method preferred over `$casts` property.
- **`activity_log` morph columns are strings**, not UUIDs — explicit decision so polymorphic `causer`/`subject` works for both bigint Spatie models and UUID domain models.

## Frontend type plumbing

Why `usePageProps()` composable instead of typed `usePage<SharedProps>()`:

We tried Inertia v3's published augmentation pattern (`declare module '@inertiajs/core' { interface InertiaConfig { sharedPageProps: SharedProps } }`). vue-tsc did not pick it up reliably across imports. Instead we cast inside one composable: `usePage().props as unknown as SharedProps`. Single cast, fully typed downstream. If Inertia's typing improves we can revert.

`useTranslate()` returns `t(key)` that flat-looks up `props.translations[key]` (server flattens nested keys with `Arr::dot()`), falls back to the key itself.

## Build / dev workflow inside Docker

- `php artisan serve` runs as the default `command` of the `app` service, so `docker compose up app` exposes port 8000.
- Vite dev server: `docker compose run --rm --service-ports app pnpm dev` (binds 5173 with `host: '0.0.0.0'` per `vite.config.js`).
- `pnpm build` produces `public/build/{manifest.json, assets/*}` for production.

## Quality-gate baseline

- **Pint** — passes on full repo. Canonical L13 preset enforces strict types, final classes, ordered imports.
- **PHPStan** — level 5 baseline (was max but the published Spatie permission migration carries unavoidable `mixed` types from `config()` lookups). The two excluded migrations are vendor-shipped untyped code. Aim is to ramp to level 8 then max as we add explicit types in domain code.
- **vue-tsc** — strict, passes on the v0.1 base. Adds `@/types` re-exports for shared types.
- **ESLint flat config** — bans `ref()` for application logic via `no-restricted-syntax` (rationale in `eslint.config.js`).
- **Prettier** — formats `resources/**/*.{ts,vue,css,json}`. 110-char line width.
- **Lefthook** — `pre-commit`: pint, phpstan, eslint, prettier, ts-transform; `pre-push`: vue-tsc.
- **php artisan test** — 3 passing tests; sqlite in-memory.

## Migration strategy

`Deployment Status: not deployed`. We re-run `migrate:fresh --seed` whenever schema changes. When the first production deploy ships, switch to additive migrations and bump `Last verified` in `CLAUDE.md`.

## Demo state

`UserSeeder` creates: User `admin@example.com` / `password` → TenantMembership → Tenant `Demo Cleaning s.r.o.` (IČO 12345678, VAT-payer, plan `premium`). After seed, sets `permissions team_id` to the tenant and runs `RoleTemplatesSeeder` which creates the 6 default roles per spec, assigns Vlastník to admin.

## Known gaps to close in /feature passes

In rough dependency order:

1. **Tenant CRUD** (`/feature` — list, create, edit, switch). Touches AppLayout tenant switcher.
2. **User invite + Profile + change-password.**
3. **Client + Object** (CRUD). First domain modules. Will pull in DataTable, Pagination, ConfirmDeleteModal as shared components.
4. **Quote** (CRUD with line items, PDF, send-to-client flow, status enum).
5. **Contract** (polymorphic, change log, expiry notifications, types incl. employee).
6. **Schedule + Job** (calendar, drag-drop, recurrence from work breakdown).
7. **Absence** + scheduling impact notification.
8. **Invoice** (line items, VAT logic, Pay-by-Square QR, status transitions, PDF, send).
9. **DocumentTemplate** (upload + download per type enum).
10. **Notification settings + log** (config UI + delivery channels).
11. **Audit log** read UI surfacing the existing Spatie ActivityLog rows.
12. **Subscription / Plan** enforcement (entity limits, feature gating).
13. **Mobile + customer portal scaffolding** (Phase 2 — separate Vite entry points or separate apps).
