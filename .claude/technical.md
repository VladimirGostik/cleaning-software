<!-- inogile:context-version=3 -->
# CleanMaster — Technical map

Companion to `business.md`. Captures **relationships, flows, and the *why* behind non-obvious decisions** an LLM/contributor needs before writing code. Generic conventions live in `CLAUDE.md` (`## Architecture invariants`); this file is the relationship map + rationale, not an inventory.

## Project type

laravel-be + inertia-fe

## Stack snapshot

Laravel 13 / PHP 8.5 (Sail) · Inertia 3 + Vue 3 + TypeScript · PostgreSQL 18 · Spatie laravel-data / permission (teams=`tenant_id`) / activitylog / medialibrary / query-builder / typescript-transformer · Tailwind 4 + DaisyUI 5.

## Domains

### identity-tenancy

The spine every other domain hangs off. **Not** a `BelongsToTenant` model itself — it *defines* tenancy.

- **Core:** `App\Models\User` (global identity, UUID, `locale`, `is_active`) + `App\Models\Tenant` (firma, UUID, VAT-payer flag, subscription_plan) + `App\Models\TenantMembership` (User × Tenant pivot, `is_active`, `joined_at`).
- **Satellites:**
  - `App\Http\Middleware\TenantContextMiddleware` — resolves active tenant from session `active_tenant_id` (or first active membership), binds `app('current_tenant_id')`, calls `PermissionRegistrar::setPermissionsTeamId($tenantId)`. Without it the global scope and permission lookups have no tenant.
  - `App\Models\Role` extends `Spatie\Permission\Models\Role` + `LogsActivity` + `search` scope. Per-tenant via `tenant_id` team key.
  - `App\Http\Middleware\HandleInertiaRequests` — shares `tenant {active, available}` + `can {…}` to every page.
  - `App\Http\Controllers\Auth\*` — login / logout / forgot-password / reset-password.
- **Flow (login):** `POST /login` → `Auth\AuthController@login` (`LoginData` DTO) → session auth → `TenantContextMiddleware` binds tenant on subsequent requests → `/dashboard`.
- **Depends on:** nothing (root).
- **Depended on by:** **every** domain model via `tenant_id` FK + the per-tenant permission scope.
- **If you change Core, check:** `TenantContextMiddleware`, `HandleInertiaRequests::share`, `App\Scopes\TenantScope`, every seeder that calls `setPermissionsTeamId`.
- **Keywords:** tenant, firma, membership, Vlastník, active_tenant_id, teams.

### subscription-plans

Per-tenant feature gating (entitlement, distinct from Spatie RBAC).

- **Core:** `config/subscription.php` — static matrix (4 tiers: Free/Starter/Pro/Enterprise); keyed by `SubscriptionPlanEnum` value; each plan has `features` array (FeatureEnum values) + `quotas` map (feature → int|null). `App\Enums\SubscriptionPlanEnum` (Free|Starter|Pro|Enterprise) + `App\Enums\FeatureEnum` (Clients|Objects|Quotes|Contracts|Schedule|Invoices|Employees|Reports|MobileAccess|MultiUser). Both `#[TypeScript]`. `App\Contracts\ChecksFeatures` interface (DIP seam) + `App\Services\ConfigFeatureChecker` final impl.
- **Satellites:**
  - `App\Http\Middleware\RequiresTenantFeature` — `'feature'` alias in bootstrap/app.php. Usage: `Route::...->middleware('feature:clients')`. Resolves tenant from `app('current_tenant_id')`, uses `FeatureEnum::tryFrom`, aborts 403 (`app.feature.locked`) if plan lacks feature.
  - `App\Models\Tenant` — `subscription_plan` cast to `SubscriptionPlanEnum` + thin `hasFeature(FeatureEnum): bool` accessor (delegates to bound `ChecksFeatures`).
  - Translations `lang/{sk,en,uk}/app.php` — `subscription_plan.*` labels + `feature.locked` 403 message.
- **Flow:** route-level `->middleware('feature:quotes')` → `RequiresTenantFeature` checks tenant plan's feature list against `FeatureEnum::tryFrom($param)` → allow 200 or abort 403.
- **Depends on:** `identity-tenancy` (reads `current_tenant_id`, writes to Tenant model).
- **Depended on by (future):** entity-limit enforcement at write-time (quota checks in services, not built).
- **If you change Core, check:** `TenantFactory` (default Free + `pro()`/`enterprise()` states), `UserSeeder` (demo tenant plan = Pro), language files, any route gated `->middleware('feature:...')`.
- **Keywords:** plan, feature, quota, entitlement, Free/Starter/Pro/Enterprise, gating, SubscriptionPlanEnum, FeatureEnum, ChecksFeatures, ConfigFeatureChecker.

### clients

The only fully-implemented business domain.

- **Core:** `App\Models\Client` + `App\Services\ClientService` — `paginate(ClientIndexFilterData): LengthAwarePaginator`, `create(ClientStoreData): Client`, `update(Client, ClientUpdateData): Client`, `delete(Client): void`.
- **Satellites:**
  - `App\Models\ClientContact` — N:1 to Client, FK `client_id ON DELETE CASCADE`. Holds `email`/`phone`/`is_primary`. **Client itself has no email/phone column** — both read from the primary contact.
  - `App\Data\Clients\*` — `ClientIndexFilterData` (filters: search, type), `ClientListItemData` (row + derived `primary_contact_email`/`primary_contact_phone`), `ClientDetailData` (address + contacts array, zero email/phone fields), `ClientStoreData`, `ClientUpdateData`, `ClientContactData`.
  - `App\Policies\ClientPolicy` — gates viewAny/view/create/update/delete on `view|create|edit|delete clients` permissions (references `PermissionEnum`).
  - `App\Enums\ClientTypeEnum` — `Corporate` (IČO required) | `Private` (IČO optional). `#[TypeScript]`. Methods: `label()` (i18n key), `options()` (select list).
  - FE: `Clients/Index` + `Clients/Show` Inertia pages, shared `ClientFormDrawer` (side-drawer create/edit, no separate routes), `useClientFilters` composable, generic `EmptyState` + `PageHeader`.
- **Flow (list):** `GET /clients` → `ClientController@index` (`#[Authorize('viewAny', Client)]`) → `ClientService::paginate` (Spatie QueryBuilder: filters search/type, sorts name/created_at) → `ClientListItemData::collect(..., PaginatedDataCollection)` → Inertia `Clients/Index`.
- **Flow (write):** `POST|PUT /clients` → `ClientController@store|update` (DTO) → `ClientService` in `DB::transaction` → `syncContacts` diff-apply (eager-load, diff incoming IDs, soft-delete missing, update/create matched) → redirect with `flash.success`.
- **Depends on:** `identity-tenancy` (tenant_id FK, permission gate).
- **Depended on by (planned):** Object → Quote → Contract → Invoice all FK to Client (not yet built).
- **If you change Core, check:** `ClientController` (5 actions), `ClientPolicy`, `routes/web.php:38` (`Route::resource('clients')->except(create,edit)`), `lang/{sk,en,uk}/app.php` `clients.*` keys.
- **Keywords:** klient, kontakt, IČO, DIČ, IČ DPH, Corporate, Private, primary contact.

## Cross-cutting

- **Multi-tenancy global scope** — `App\Concerns\BelongsToTenant` auto-applies `App\Scopes\TenantScope` and auto-fills `tenant_id` on `creating` from `app('current_tenant_id')`. Reach: every domain query. Bypass (jobs/console only): `Model::withoutGlobalScope(TenantScope::class)`. We deliberately did **not** install `stancl/tenancy` — spec mandates the trait+scope approach.
- **Permissions (Spatie teams=tenant_id)** — `config/permission.php`: `teams=true`, `team_foreign_key='tenant_id'`, `'role'=>App\Models\Role::class`. Flat strings via `App\Enums\PermissionEnum` (canonical source of truth — all code references `PermissionEnum::Xxx->value`). Reach: every Policy + `#[Authorize]` site. **Outside HTTP (seeder/job/console/test) you MUST `setPermissionsTeamId($tenantId)` first** or lookups return "permission not found".
- **Feature gating (entitlement layer)** — `ChecksFeatures` interface + `ConfigFeatureChecker` reads `config/subscription.php` plan matrix. Stacks **on top of** RBAC permissions: plan gates the tenant ("does your tier unlock Invoices?"), Spatie gates the user ("can you view invoices?"). Middleware `RequiresTenantFeature` enforces plan entitlements at the route level. DIP interface allows future `PennantFeatureChecker` or DB-backed adapter without caller changes. Reach: any route gated `->middleware('feature:xxx')`. Quota enforcement (entity count vs. limit) is caller responsibility, checker is stateless.
- **Activitylog** — `LogsActivity` on Role (+ Client/ClientContact). Auth events (Login/Logout/Failed/Lockout/PasswordReset/PasswordChanged/Registered/Verified) are logged asynchronously via `App\Listeners\AuthEventListener` (implements `ShouldQueue`, `#[Tries(3)]`). Writes to `activity_log`. Morph columns `subject_id`/`causer_id` are **strings** to span mixed-PK models (Role bigint, User/Client UUID).
- **MediaLibrary** — `media` table present, `model_id` UUID. No collections wired on domain models yet (reserved for DocumentTemplate / photos).
- **i18n** — SK (default) / EN / UA. `App\Enums\SupportedLanguage`, `lang/{sk,en,uk}/app.php`, `LocaleMiddleware` (order: user.locale → session → cookie → Accept-Language → SK). BE flattens nested keys via `Arr::dot()`; FE `useTranslate()` does flat `t(key)` lookup. Reach: every visible string.
- **Inertia shared props** — `HandleInertiaRequests::share`: `app, auth, tenant{active,available}, can{…}, flash, translations, locale, languages, canResetPassword`. `tenant.available` is a `DataCollection<TenantListItemData>` (typed DTO: id, name, is_active). Typed in `resources/js/types/index.d.ts`, read via `usePageProps()`. Reach: every page.
- **TypeScript transformer** — `php artisan typescript:transform` generates `resources/js/types/generated.d.ts` from `app/Data` + `app/Enums` (`#[TypeScript]`). Never hand-edit. Wired via `App\Providers\TypeScriptTransformerServiceProvider` (no config file in v3).

## Layer contracts

- **HTTP ↔ Service** — Controllers hand a Spatie Data DTO to the service, never an array. Controllers are thin: type-hint DTO param + service in constructor, return `Inertia::render` or `to_route()->with('flash.success', …)`.
- **Service ↔ Model** — `DB::transaction` lives in the Service, NEVER the Controller. Services are `final readonly class XxxService`.
- **BE ↔ FE (Inertia)** — page prop shape = the DTO's generated TS namespace. Shared props also follow the DTO rule: `tenant.available` is a `DataCollection<TenantListItemData>` (not raw arrays). Enums shared via `#[TypeScript]` → `generated.d.ts`. Flash via `flash.success` session key.
- **Auth gate** — business models are gated by **Policy + `#[Authorize]` per controller method** (rbac-full), not route middleware. Permission lookup is tenant-scoped — relies on `TenantContextMiddleware` having run.

## Gotchas

- **Soft-delete + partial unique** — `clients (tenant_id, ico) WHERE deleted_at IS NULL AND ico IS NOT NULL`. IČO unique per tenant among *active* clients; resurrected/soft-deleted rows don't block re-use. A plain `unique` would break this. `ClientService` catches the `QueryException` and rethrows as `ValidationException`.
- **Permission team scope outside HTTP** — forgetting `setPermissionsTeamId` in seeders/jobs/tests silently returns empty permissions. Most common multi-tenant bug.
- **`activity_log` morph columns are strings** — not UUIDs, so polymorphic causer/subject works across bigint (Role) + UUID (User/Client) models.
- **Spatie permission migration is UUID-patched** — the published `2026_05_03_194335_create_permission_tables.php` was edited: `model_morph_key` + `team_foreign_key` are `uuid()`, but `permissions.id`/`roles.id` stay bigint (Spatie internal, never in URLs/DTOs).
- **Spatie Data v4 ↔ typescript-transformer v3 mismatch** — `DataTypeScriptTransformer` references a removed class. Use `Spatie\LaravelTypeScriptTransformer\LaravelData\Transformers\DataClassTransformer` directly (see the service provider).
- **Activitylog v5 namespace shift** — `…\Models\Concerns\LogsActivity` (not `Traits`), `…\Support\LogOptions`; `dontSubmitEmptyLogs()` → `dontLogEmptyChanges()`.
- **Auth page palette** — `Pages/Auth/Login.vue` uses `--auth-*` CSS custom properties (brown/amber gradient, not `cleanmaster` DaisyUI theme tokens). Tokenized in `resources/css/app.css` (:62–107). Not the same as `Landing.vue`'s blue/slate or the main app's DaisyUI theme.
- **Landing page palette** — `Pages/Landing.vue` uses raw Tailwind blue/slate, NOT DaisyUI semantic tokens (fixed marketing brand, theme-independent). Don't "fix" it to use the `cleanmaster` theme.
- **`usePageProps()` cast** — Inertia v3 augmentation didn't type-check reliably across imports, so we cast once inside the composable (`usePage().props as unknown as SharedProps`). Revisit if Inertia typing improves.

## Why these versions / decisions

- **PHP 8.5 in Docker/Sail** — host has 8.4; L13 requires `^8.5`. Reproducible toolchain, CI matches local.
- **Vite 7 (not 8)** — Vite 8's Rolldown chokes importing DaisyUI 5 CSS through the Tailwind 4 plugin (`Unknown file extension ".css"`).
- **DaisyUI 5 + Tailwind 4** — `@plugin 'daisyui'` + `@plugin 'daisyui/theme' { name:'cleanmaster'; … }` (OKLCH) in `resources/css/app.css`. No `tailwind.config.js` — CSS-driven via `@theme`/`@source`.
- **Design handoff** — Claude Design JSX prototypes in `docs/design-handoff/`. Source of truth for *visuals only*; re-implement in Vue 3 + Tailwind 4, do **not** copy the React structure or `cm-*` classes. `styles.css` there is reference-only, never imported.

## Build / dev workflow (Sail)

- Whole stack: `./vendor/bin/sail up -d` (laravel.test, pgsql, mailpit, minio). App → http://localhost, Mailpit → :8025, MinIO → :8900.
- Vite HMR: `./vendor/bin/sail pnpm dev` (:5173, `host:'0.0.0.0'`). Prod build: `./vendor/bin/sail pnpm build` → `public/build/{manifest.json, assets/*}`.

## Quality-gate baseline

- **Pint** — passes; canonical L13 preset (strict types, final classes, ordered imports).
- **PHPStan** — level 5 baseline (the published Spatie permission migration carries unavoidable `mixed` from `config()`). Ramp toward max as domain code gains explicit types.
- **vue-tsc** — strict, passes.
- **ESLint** — bans `ref()` for app logic (`no-restricted-syntax`). **Prettier** — `resources/**/*.{ts,vue,css,json}`, 110-char.
- **Lefthook** — pre-commit: pint, phpstan, eslint, prettier, ts-transform; pre-push: vue-tsc.
- **Tests** — PHPUnit, sqlite in-memory; small passing suite on the v0.1 base.

## Migration strategy

Not deployed (`CLAUDE.md › Deployment Status`). Re-run `migrate:fresh --seed` on any schema change. On first production deploy, switch to additive migrations and bump `Last verified`.

## Demo state

`UserSeeder`: User `admin@example.com` / `password` → TenantMembership → Tenant `Demo Cleaning s.r.o.` (IČO 12345678, VAT-payer, subscription plan `Pro`). Sets permission `team_id` to the tenant, runs `RoleTemplatesSeeder` (6 spec roles per tenant), assigns Vlastník to admin.

## Known gaps to close in /feature passes (rough dependency order)

1. **Tenant CRUD** — list/create/edit/switch. Touches AppLayout tenant switcher.
2. **User invite + Profile + change-password.**
3. **Object** — CRUD, polymorphic address, access info, special instructions, FK to Client. The central entity (client → object → quote → contract → invoice).
4. **Quote** — line items, status enum, PDF, send-to-client, auto work breakdown.
5. **Contract** — polymorphic (`contractable`), change log, expiry notifications (30/14/7d), employee-contract child.
6. **Schedule + Job** — calendar, drag-drop, recurrence from work breakdown.
7. **Absence** — cleaner-reported, scheduling-impact notification.
8. **Invoice** — line items, VAT logic, Pay-by-Square QR, status transitions, PDF, send.
9. **DocumentTemplate** — upload/download per type enum (MediaLibrary).
10. **Notification settings + log** — channels (DB/email/push), per role × type.
11. **Audit log read UI** — surface existing Spatie ActivityLog rows.
12. **Subscription / Plan enforcement** — **gating engine done** (config + enums + middleware); entity-limit enforcement at write-time (quota checks in service layer) + UI still pending.
13. **Mobile + customer portal scaffolding** (Phase 2).

## Verification status

- Last full scan: 2026-06-05 (`/init` full re-init — schema + routes via Boost MCP, source grep)
- Last delta: 2026-06-06 (subscription-plans: SubscriptionPlanEnum, FeatureEnum, config matrix, ChecksFeatures interface, ConfigFeatureChecker, RequiresTenantFeature middleware, Tenant enum cast + accessor, AppServiceProvider binding, bootstrap alias, seed alignment)
- Open `TODO verify`: 0
- Reference inventory: `.claude/inventory.md` (not generated; opt-in via `/spec-sync --full --with-inventory`)
