<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to ensure the best experience when building Laravel applications.

## Foundational Context

This application is a Laravel application and its main Laravel ecosystems package & versions are below. You are an expert with them all. Ensure you abide by these specific packages & versions.

- php - 8.5
- inertiajs/inertia-laravel (INERTIA_LARAVEL) - v3
- laravel/framework (LARAVEL) - v13
- laravel/prompts (PROMPTS) - v0
- larastan/larastan (LARASTAN) - v3
- laravel/boost (BOOST) - v2
- laravel/mcp (MCP) - v0
- laravel/pail (PAIL) - v1
- laravel/pint (PINT) - v1
- phpunit/phpunit (PHPUNIT) - v12

## Skills Activation

This project has domain-specific skills available in `**/skills/**`. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If the user doesn't see a frontend change reflected in the UI, it could mean they need to run `npm run build`, `npm run dev`, or `composer run dev`. Ask them.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

## Replies

- Be concise in your explanations - focus on what's important rather than explaining obvious details.

=== boost rules ===

# Laravel Boost

## Tools

- Laravel Boost is an MCP server with tools designed specifically for this application. Prefer Boost tools over manual alternatives like shell commands or file reads.
- Use `database-query` to run read-only queries against the database instead of writing raw SQL in tinker.
- Use `database-schema` to inspect table structure before writing migrations or models.
- Use `get-absolute-url` to resolve the correct scheme, domain, and port for project URLs. Always use this before sharing a URL with the user.
- Use `browser-logs` to read browser logs, errors, and exceptions. Only recent logs are useful, ignore old entries.

## Searching Documentation (IMPORTANT)

- Always use `search-docs` before making code changes. Do not skip this step. It returns version-specific docs based on installed packages automatically.
- Pass a `packages` array to scope results when you know which packages are relevant.
- Use multiple broad, topic-based queries: `['rate limiting', 'routing rate limiting', 'routing']`. Expect the most relevant results first.
- Do not add package names to queries because package info is already shared. Use `test resource table`, not `filament 4 test resource table`.

### Search Syntax

1. Use words for auto-stemmed AND logic: `rate limit` matches both "rate" AND "limit".
2. Use `"quoted phrases"` for exact position matching: `"infinite scroll"` requires adjacent words in order.
3. Combine words and phrases for mixed queries: `middleware "rate limit"`.
4. Use multiple queries for OR logic: `queries=["authentication", "middleware"]`.

## Artisan

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`). Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.
- Inspect routes with `php artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `php artisan config:show app.name`, `php artisan config:show database.default`. Or read config files directly from the `config/` directory.
- To check environment variables, read the `.env` file directly.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `php artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `php artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Use TitleCase for Enum keys: `FavoritePerson`, `BestLake`, `Monthly`.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== deployments rules ===

# Deployment

- Laravel can be deployed using [Laravel Cloud](https://cloud.laravel.com/), which is the fastest way to deploy and scale production Laravel applications.

=== inertia-laravel/core rules ===

# Inertia

- Inertia creates fully client-side rendered SPAs without modern SPA complexity, leveraging existing server-side patterns.
- Components live in `resources/js/Pages` (unless specified in `vite.config.js`). Use `Inertia::render()` for server-side routing instead of Blade views.
- ALWAYS use `search-docs` tool for version-specific Inertia documentation and updated code examples.

# Inertia v3

- Use all Inertia features from v1, v2, and v3. Check the documentation before making changes to ensure the correct approach.
- New v3 features: standalone HTTP requests (`useHttp` hook), optimistic updates with automatic rollback, layout props (`useLayoutProps` hook), instant visits, simplified SSR via `@inertiajs/vite` plugin, custom exception handling for error pages.
- Carried over from v2: deferred props, infinite scroll, merging props, polling, prefetching, once props, flash data.
- When using deferred props, add an empty state with a pulsing or animated skeleton.
- Axios has been removed. Use the built-in XHR client with interceptors, or install Axios separately if needed.
- `Inertia::lazy()` / `LazyProp` has been removed. Use `Inertia::optional()` instead.
- Prop types (`Inertia::optional()`, `Inertia::defer()`, `Inertia::merge()`) work inside nested arrays with dot-notation paths.
- SSR works automatically in Vite dev mode with `@inertiajs/vite` - no separate Node.js server needed during development.
- Event renames: `invalid` is now `httpException`, `exception` is now `networkError`.
- `router.cancel()` replaced by `router.cancelAll()`.
- The `future` configuration namespace has been removed - all v2 future options are now always enabled.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `php artisan list` and check their parameters with `php artisan [command] --help`.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `php artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

## Vite Error

- If you receive an "Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest" error, you can run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== phpunit/core rules ===

# PHPUnit

- This application uses PHPUnit for testing. All tests must be written as PHPUnit classes. Use `php artisan make:test --phpunit {name}` to create a new test.
- If you see a test using "Pest", convert it to PHPUnit.
- Every time a test has been updated, run that singular test.
- When the tests relating to your feature are passing, ask the user if they would like to also run the entire test suite to make sure everything is still passing.
- Tests should cover all happy paths, failure paths, and edge cases.
- You must not remove any tests or test files from the tests directory without approval. These are not temporary or helper files; these are core to the application.

## Running Tests

- Run the minimal number of tests, using an appropriate filter, before finalizing.
- To run all tests: `php artisan test --compact`.
- To run all tests in a file: `php artisan test --compact tests/Feature/ExampleTest.php`.
- To filter on a particular test name: `php artisan test --compact --filter=testName` (recommended after making a change to a related file).

</laravel-boost-guidelines>

# CleanMaster

Multi-tenant SaaS for cleaning companies (SK/CZ market). Phase 1 = web Admin Portal. Phase 2 = mobile (cleaner + supervisor) + customer portal. Spec lives in `docs/cleanmaster-technicka-specifikacia-v1.md.docx`. Working summary in `.claude/business.md` and `.claude/technical.md`.

## Stack target

**Decisive signal for architect-be-agent and be-agent. Overrides `composer.json` version.**

- **Target Laravel version:** 13
- **Greenfield (no production):** yes
- **Legacy patterns allowed (Repository / FormRequest / JsonResource):** no
- **Last verified:** 2026-06-05

Rule: `Target 13` + `Legacy no` → always `laravel-13-conventions` skill. No FormRequest, no JsonResource, no Repository. Spatie Data DTO at the controller boundary; Inertia render / DTO `toArray` for responses.

Stack detail:

- PHP 8.5 (Sail `php:8.5` image; host has 8.4)
- Laravel 13 + Inertia 3 (server + Vue 3 client) + Vue 3 + TypeScript
- Tailwind 4 + DaisyUI 5 (custom `cleanmaster` theme, OKLCH tokens)
- PostgreSQL 18 (Sail)
- Spatie: laravel-data, laravel-permission (with **teams** = `tenant_id`), laravel-activitylog, laravel-medialibrary, laravel-typescript-transformer, laravel-query-builder
- Knuckleswtf Scribe (config published, not yet wired)
- PDF generation: `spatie/laravel-pdf` with the **`chrome` driver** (`chrome-php/chrome`, pure-PHP DevTools — no Node at runtime). Real arm64 Chromium installed via Playwright in `docker/8.5/Dockerfile`, stable symlink `/usr/local/bin/chromium-real`. Config: `config/laravel-pdf.php` (`LARAVEL_PDF_DRIVER=chrome`, `CHROMIUM_PATH` env, `--no-sandbox --disable-gpu --disable-dev-shm-usage`). `docker-compose.yml` build context = `./docker/8.5`. NOTE: Google Chrome / Puppeteer Chrome-for-Testing are x86_64-only on Linux — Playwright is the only real arm64 Chromium source.
- Pay-by-Square QR: `engazan/pay-by-square` + `bacon/bacon-qr-code`
- Larastan, Laravel Pint, Laravel Boost, Laravel Pail
- ESLint flat config (with `no-restricted-syntax` ban on Vue `ref` for app logic), Prettier, vue-tsc, Lefthook

## Auth profile

profile: rbac-full

Granular flat permission strings (`view clients`, `create clients`, …) + a Policy per business model + per-method gating via the `#[Authorize]` controller attribute. Permissions and roles are **per-tenant** (Spatie teams = `tenant_id`). `reviewer-agent` reads this value on every PR and reports `auth-profile-violation` if the diff route-gates a business model instead of policy-gating it, or skips the per-tenant team scope. Detail: `skills/laravel-13-conventions/references/auth-profiles.md`.

## Quick links

- Business logic, roles, domain rules, permissions → `.claude/business.md`
- Technical relationship map, domains, layer contracts, gotchas → `.claude/technical.md`
- Active plans → `.claude/plans/<slug>.md` **(read ONLY when a parent passes an explicit `plan_path`; never auto-glob)**
- Full product spec (Slovak, 2312 lines) → `docs/cleanmaster-technicka-specifikacia-v1.md.docx`

## Architecture invariants (read before writing any model)

1. **Multi-tenancy = row-level via `tenant_id`.** Every domain model (clients, objects, quotes, contracts, schedule, invoices, …) MUST: have a `tenant_id` UUID FK; use `App\Concerns\BelongsToTenant` trait; reference `App\Scopes\TenantScope` global scope. The trait is excluded from `User`, `TenantMembership`, `Tenant` themselves.
2. **Active tenant resolution.** `App\Http\Middleware\TenantContextMiddleware` resolves the active tenant from the user's session (`active_tenant_id`) or first active membership and binds it as `app('current_tenant_id')` plus passes it to `Spatie\Permission\PermissionRegistrar::setPermissionsTeamId()`. The global scope reads `current_tenant_id` from the container.
3. **Permissions are per-tenant.** Spatie permission `teams` mode is enabled with `team_foreign_key = tenant_id`. Roles and `model_has_roles` rows are scoped per tenant. Always call `setPermissionsTeamId($tenantId)` before role/permission lookups in seeders, console commands, jobs.
4. **`App\Models\Role`** extends `Spatie\Permission\Models\Role` and adds `LogsActivity` + a `search` scope. Configured as `'role' => App\Models\Role::class` in `config/permission.php`.
5. **UUIDv7 primary keys** for all domain models via `App\Concerns\HasUuids`. User, Tenant, TenantMembership all UUID. Spatie permissions/roles keep bigint PKs (Spatie internal); only their `model_id` and `team_foreign_key` columns are UUID. `activity_log` uses `string` morph columns to support mixed PK types.
6. **Permission strings are flat** (`view clients`, `create clients`, …). Role templates per spec live in `database/seeders/RoleTemplatesSeeder.php` and are seeded **per tenant** via `UserSeeder` after tenant creation.
7. **i18n** = SK (default) / EN / UA. `App\Enums\SupportedLanguage`. Translations in `lang/{sk,en,uk}/app.php`. `LocaleMiddleware` resolves order: user.locale → session → cookie → Accept-Language → SK default.
8. **Inertia shared props** (`HandleInertiaRequests::share`): `app, auth, tenant {active, available}, can {…}, flash, translations, locale, languages, canResetPassword`. Type definition in `resources/js/types/index.d.ts`. Vue components access via `usePageProps()` composable (typed wrapper around `usePage().props`).
9. **TypeScript types** auto-generated by `php artisan typescript:transform` into `resources/js/types/generated.d.ts` from `app/Data` + `app/Enums`. Never hand-edit.
10. **`final readonly`** services in `app/Services/`. DTOs `final` in `app/Data/`. Controllers thin → DTO → Service. Naming: `XxxStoreData`, `XxxUpdateData`, `XxxIndexFilterData`, `XxxListItemData`, `XxxDetailData`.

## Demo credentials (seeded)

Five demo accounts, each owns ≥1 tenant with demo clients seeded:

1. `admin@example.com` / `password` — **Pro plan** → owns `Demo Cleaning s.r.o.` (IČO 12345678, VAT payer SK1234567890). Vlastník role. (Original admin account, extended to Pro tier.)
2. `free@demo.sk` / `password` — **Free plan** (max 1 tenant) → owns `Demo Free s.r.o.` (IČO 10000001, non-VAT payer).
3. `starter@demo.sk` / `password` — **Starter plan** (max 2 tenants, Clients + Objects) → owns `Demo Starter s.r.o.` (IČO 10000002, non-VAT payer).
4. `pro@demo.sk` / `password` — **Pro plan** (max 3 tenants, full features) → owns `Demo Pro s.r.o.` (IČO 10000003, non-VAT payer).
5. `enterprise@demo.sk` / `password` — **Enterprise plan** (unlimited tenants) → owns `Demo Enterprise s.r.o.` (IČO 10000004, VAT payer SK9999999999).

## Working with this repo

Local stack runs via **Laravel Sail** (PHP 8.5 + Postgres 18 + Mailpit + MinIO). Optional shell alias: `alias sail='./vendor/bin/sail'`.

```bash
# Bring up the whole stack (laravel.test, pgsql, mailpit, minio)
./vendor/bin/sail up -d

# Run any artisan / composer / pnpm command inside the container
./vendor/bin/sail composer install
./vendor/bin/sail artisan migrate:fresh --seed
./vendor/bin/sail artisan typescript:transform
./vendor/bin/sail pnpm install
./vendor/bin/sail pnpm build
./vendor/bin/sail pnpm exec vue-tsc --noEmit

# Dev server (HMR)
./vendor/bin/sail pnpm dev     # vite dev: http://localhost:5173

# Endpoints
# App         → http://localhost
# Mailpit UI  → http://localhost:8025
# MinIO UI    → http://localhost:8900   (sail / password)
```

If a port (80/5432/1025/8025/9000/8900) is occupied, override via `APP_PORT`, `FORWARD_DB_PORT`, `FORWARD_MAILPIT_PORT`, `FORWARD_MINIO_PORT` in `.env`.

## Lint

```yaml
lint.tools: [pint, phpstan, vue-tsc, eslint, prettier]
lint.runner: docker
lint.asked: true
lint.notes: |
  - PHPStan currently at level 5 (baseline). Climb toward `max` as code stabilises.
  - Pint runs canonical L13 preset (declare_strict_types, final_class, ordered_imports).
  - ESLint enforces no-restricted-syntax: ban Vue `ref()` for app logic; allow only for unavoidable imperative DOM access.
  - Prettier formats resources/**/*.{ts,vue,css,json} on commit via Lefthook.
```

## Deployment Status

- **Deployed to production:** no
- **Last verified:** 2026-05-07

(Migration strategy: free to refactor schema. No production users. Re-run `migrate:fresh --seed` whenever schema changes.)

## Modules implemented

- **Auth** — login / forgot-password / reset-password (Inertia + Spatie permission teams).
- **Landing** — public marketing page at `/` (Inertia `Pages/Landing.vue`). Auth users redirect to `/dashboard`. Visual design source: `docs/design-handoff/project/screens/landing.jsx`. Tailwind 4 + heroicons; no DaisyUI utilities (custom marketing palette: blue-600 primary, slate neutrals).
- **Register / Onboarding** — 3-step wizard (account credentials + company IČO/address/VAT + co-founder invites). Atomic `POST /register` transaction via `RegistrationService::register()` with `DB::transaction`. Email auto-verified (no verification flow exists). New tenant defaults to Free plan. RegistrationService calls shared `bootstrapTenant()` (also used by add-tenant flow) which seeds `RoleTemplatesSeeder::seedForTenant()` (now static method). FE: `Pages/Auth/Register.vue` 3-step state machine, `useIcoLookup` composable (debounced 400ms lookup), `ColorSwatchPicker` for tenant color choice, `PasswordStrengthBar`. Dashboard welcome overlay (via flash `justRegistered` boolean) → Option C (no separate route).
- **Add tenant** — POST `/tenants` (auth-only, no TenantPolicy — deliberate D4a exception, self-service, new tenant has no roles at creation → circular RBAC problem). Switches active tenant. Can copy tenant color from current. Optional leader email creates TenantInvitation. AppLayout tenant dropdown + "Pridať novú firmu" affordance.
- **Tenant invitations** — TenantInvitation model (Pending status, soft-delete, partial unique (tenant_id,email) WHERE status='pending'), sits at tenant level. Created at register (co-founders) and add-tenant (optional leader). Token + email + role_name stored; expires in 7d. Logged via `LogsActivity`.
- **Invitation accept** — `GET|POST /invitations/{token}` (guest-accessible). `InvitationController@show` resolves 4 FE states (expired / wrong_user / existing_user / new_user) + same-email auto-accept for logged-in users. `InvitationAcceptService::accept()` in `DB::transaction`: existing-user password check (or `skipPasswordCheck`), new-user creation (Free plan, auto-verified), membership create/reactivate, role assignment (per-tenant team scope), `markAccepted()`. Auto-login + `active_tenant_id` session binding. FE: `Pages/Invitations/Accept.vue`. **No invitation email is dispatched yet** — accept page reachable via URL/token only (email dispatch TBD).
- **Tenant settings** — TenantInterface 1:1 model (bigint PK, NOT UUID — deliberate: settings never in URLs/DTOs) stores tenant color (TenantColorEnum, 8 hex presets) + default invoice template (InvoiceTemplateEnum). Cascades on delete. Tenant->interface() HasOne. Shared to FE in `tenantColors` Inertia prop.
- **IČO lookup** — `GET /api/icos/{ico}` (guest-accessible, throttled) mock service → hardcoded SKMap (TODO swap for ARES/FinStat). Returns company name + VAT status. FE: `IcoLookupService.ts` + `useIcoLookup` composable (debounced, cancellable).
- **Clients** — CRUD (list + detail/edit via side drawer). Soft-delete. Multi-contact with primary flag (email/phone on contacts, not client model). Type enum (Corporate/Private). Permission-gated. QueryBuilder filters (search, type). Pagination. Generic `EmptyState` + `PageHeader` components.
- **Subscription plans / Feature gating** — per-account entitlement engine (4 tiers: Free/Starter/Pro/Enterprise). User holds `subscription_plan` field. Tenant resolves features through owner's plan via `ConfigFeatureChecker::planConfig($tenant)` (loads owner eagerly). `config/subscription.php` matrix + `SubscriptionPlanEnum` + `FeatureEnum` + `ChecksFeatures` interface + `ConfigFeatureChecker` impl. Middleware `RequiresTenantFeature` gates routes. Tenant-creation quota: each account can own `max_tenants[plan]` tenants (Free=1, Starter=2, Pro=3, Enterprise=null). `ChecksFeatures::canCreateTenant(User)` checks before POST /tenants. Distinct layer from Spatie RBAC permissions (plan-level vs. user-level).
- **Capabilities system** — two-axis authorization (user/permission axis AND plan/feature axis). `GET /api/me` returns MeData (permissions, features). FE: Pinia store + `useAuthorization()` composable + `Can` component for declarative UI gating. `can(permission)` + `hasFeature(feature)` + `allows(permission, feature)` AND semantics.
- **Objects** — CRUD (list + detail/edit via side drawer). Soft-delete. Physical cleaning location (office/apartment/house/common areas). Central entity in client → object → (quote → contract → invoice) chain. Access info (codes, keys), special instructions, area, floor. Feature-gated (`objects` plan). Type enum (ObjectTypeEnum). Permission-gated via ObjectPolicy. QueryBuilder filters (search, type, client_id, is_active). Pagination. Generic components reused from Clients module.
- **Invoices** — CRUD (list/create/edit/show/delete Draft). Three subject modes: client-linked, object-linked, standalone (manual customer fields). Historical snapshot at creation (customer/object/supplier flat columns, frozen at issue per spec D2–D3). Per-tenant per-year numbering with lockForUpdate; configurable format (4 presets + custom, `{X+}` placeholder); manual override at issue time. SK accounting compliance (D11): delivery_date, registration_info, non-payer clause. Lifecycle: Draft → Issued (manual or auto number) → Paid / Overdue → Paid. Cancel (Issued|Overdue) creates credit note (dobropis, negated items, own number, linked via `credited_invoice_id`). PDF generation via `spatie/laravel-pdf` `chrome` driver (`chrome-php/chrome`, arm64 Chromium via Playwright, 3 templates: Classic/Modern/Minimal, Blade-based); Pay-by-Square QR via `engazan/pay-by-square` + `bacon/bacon-qr-code`. Email send via `SendInvoiceEmail` queued job (`#[Tries(3)]`, `#[Timeout(120)]`). Daily `app:mark-overdue-invoices` command (Issued past due → Overdue). Feature-gated (`feature:invoices`), permission-gated (view/create/edit/cancel invoices, ManageBillingSettings). Invoice settings page (default template, IBAN, VAT rate, number format, registration_info).
- **Recurring Invoices** — Template-driven automatic invoice generation on a schedule. `RecurringInvoice` model (UUID, tenant_id, BelongsToTenant, SoftDeletes, LogsActivity) + `RecurringInvoiceItem` child (UUID, cascadeOnDelete, no soft-delete). Frequency enum (monthly/every_2_months/quarterly/semi_annually/annually) with `monthsInterval()` + `nextRunDate()` helpers. Three termination modes: forever / until end_date / up to occurrences_limit (UI: 3-way radio for mutual exclusivity). Lifecycle: Active → Paused (manual pause/resume) → Completed (auto on termination) / Cancelled (manual cancel). `RecurringInvoiceService` — `create/update/paginate` + lifecycle actions (pause/resume/cancel/delete) + `generateInvoiceFromTemplate()` (delegates to existing `InvoiceService::create`, links generated invoice via `recurring_invoice_id` on `invoices` table — additive FK). `GenerateRecurringInvoiceJob` (queued, ShouldBeUnique, 3 retries): binds tenant context + permissions, generates invoice, optionally auto-issues (per `RecurringInvoice.auto_issue` OR tenant `recurring_default_state` enum), increments `occurrences_generated`, advances `next_run_at`, marks Completed on limit/end_date. Daily `app:generate-recurring-invoices` command finds Active rows with `next_run_at <= today` + dispatches one job per row. `RecurringInvoicePolicy` + flat permissions (view/create/edit/delete recurring_invoices). Tenant settings (`TenantInterface`) gained `recurring_default_state` (enum: draft/issued). FE: full CRUD pages + components (Form/ItemsEditor/Filters/Status badge) + settings toggle. Feature-gated (`feature:invoices` — inherited from parent invoices module). Tests: 6 files covering frequency logic, service lifecycle, job idempotency, command scheduling, controller authorization, settings integration.

## Out of scope for v0.1 base scaffold (defer to /feature)

- PermissionManager UI
- Profile page + change-password
- Audit log read UI
- Scribe API docs route + lockdown
- `app:demo` artisan command
- Generic `useFilters`, `useDeleteConfirm`, `useToast` composables
- Domain modules: Quote, Contract, Schedule, Employee, Template, Notification, etc.
- Invitation email dispatch (Mailable/Notification on invitation create — accept flow itself is DONE, reachable via token URL).
- Automatic payment matching (Invoice payment tracking = manual button v0; automatic matching Phase 2 per spec). **NOTE:** Recurring invoices now auto-generate on schedule; ad-hoc/manual invoices remain button-driven.

## Rules for agents

1. Hot context = these 3 files (`CLAUDE.md`, `.claude/business.md`, `.claude/technical.md`). Do not read anything else from `.claude/` by default.
2. **Read plans in `.claude/plans/<slug>.md` ONLY if the parent passes an explicit `plan_path`.** NEVER `Glob .claude/plans/*.md`.
3. On ambiguity STOP, NEVER guess. The product is spec-driven — when code and spec disagree, ask.
4. Large features (`plan_size: file`) MUST have a plan approved by a human.
5. **Skill load — architect-as-dirigent:** architect agents (`architect-be-agent`, `architect-fe-agent`) eager-load skills in Step 0. Implementation agents (`be-agent`, `fe-agent`, `tester-agent`, `reviewer-agent`, `devops-agent`, `docs-agent`) load **only** the skills passed to them in the `## Skills (for executor)` section of their prompt.
6. **Every new domain model** obeys the multi-tenancy invariant (see `## Architecture invariants` #1–#3): `tenant_id` UUID FK + `BelongsToTenant` + per-tenant permission scope. This is non-negotiable and the most common review failure.

