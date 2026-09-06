# CleanMaster

Internal tool for the owner's cleaning companies (SK/CZ). Rebuilt on the canonical inogile `vue-skeleton`
(branch `rebuild`); business modules are ported from branch `main` phase by phase per
`.claude/plans/port-from-cleaning-software.md` (read ONLY when a parent passes it as `plan_path`).

## Stack
- Backend: Laravel 13, PHP 8.5 (Docker: `docker compose exec app php artisan ...`)
- Frontend: Inertia v3 + Vue 3 + TypeScript, Tailwind 4 + DaisyUI 5
- DB: PostgreSQL 16 (compose service `postgres`), Redis 7
- Main packages: Spatie Data 4, Permission 7, Activitylog 5, MediaLibrary 11, QueryBuilder 7 + `App\Utils\AllowedFilter`,
  TypeScript Transformer 3, Laravel Boost 2, Scribe; PHPUnit 12; Pint, PHPStan (Larastan), ESLint, Prettier, vue-tsc, Lefthook

## Stack target

**Decisive signal for architect-be-agent and be-agent. Overrides `composer.json` version.**

- **Target Laravel version:** 13
- **Greenfield (no production):** yes
- **Legacy patterns allowed (Repository / FormRequest / JsonResource):** no
- **Last verified:** 2026-09-06

Rules:
- `Target: 13` + `Legacy: no` -> always `laravel-13-conventions` skill, no FormRequest / JsonResource / Repository.

## Auth profile

profile: rbac-full

Governs how the `laravel-13-conventions` skill generates controllers, policies, seeders and routes. reviewer-agent reads this value on every PR and reports `auth-profile-violation` if the diff violates the profile. Detail: `skills/laravel-13-conventions/references/auth-profiles.md`.

## Conventions (key)
- UUIDv7 PK via `App\Concerns\HasUuids`
- Spatie Data DTOs (no FormRequest, no `$request->validate()`)
- Inertia + Spatie Data toArray response (no JsonResource)
- `final readonly class` services with `declare(strict_types=1)`
- `DB::transaction` in service, NOT in controller
- Spatie Permission via `App\Enums\PermissionEnum`, `#[Authorize]` per controller method, Policy per business model
- Spatie Activitylog on business models
- `env()` outside `config/` forbidden
- `php artisan typescript:transform` after every change in `app/Data/` or `app/Enums/`
- Reuse skeleton components: `AppLayout`, `DataTable`, `ConfirmDeleteModal`, `Pagination`, `PermissionManager`, `Forms/*` — never reinvent
- New entities via `/scaffold-module` (canonical Resource Recipe)

## Quick links
- Business logic and flows -> `.claude/business.md`
- Technical architecture and modules -> `.claude/technical.md`
- Active plans -> `.claude/plans/<slug>.md` **(read ONLY on explicit instruction, never auto-glob)**
- Skeleton conventions in detail -> `AGENTS.md`

## Working with this repo

```bash
docker compose up -d                      # app :8000, vite :5173, postgres :5432, redis :6379
docker compose exec app php artisan test --compact
# Tests run on Postgres DB `cleanmaster_admin_testing` (phpunit.xml force=true; never the dev DB).
# Created automatically on first volume init (docker/postgres/init-testing-db.sql); on an existing volume:
docker compose exec postgres psql -U postgres -c 'CREATE DATABASE cleanmaster_admin_testing;'
docker compose exec app php artisan migrate:fresh --seed
docker compose exec app php artisan typescript:transform
docker compose exec app vendor/bin/pint --dirty --format agent
docker compose exec app vendor/bin/phpstan analyse
docker compose exec app pnpm lint:js && docker compose exec app pnpm lint:prettier && docker compose exec app pnpm typecheck
```

Login (canonical skeleton admin, never change): `admin@example.com` / `password`.
Vite is started directly from `node_modules/.bin/vite` in `compose.yml` (pnpm 11 deps check bypass); lefthook build recorded in `pnpm-workspace.yaml`.

## Modules

- auth (FE) — Pages/Auth/{Login,ForgotPassword,ResetPassword}.vue, standalone cards, FormProvider + Precognition, string URLs.
- auth (BE) — session login/logout, forgot/reset password, Sanctum Bearer POST /api/auth/login|logout; login/logout/failed logged to Activitylog.
- dashboard (FE) — Pages/Dashboard.vue welcome card.
- dashboard (BE) — placeholder GET / route, no props.
- profile (FE) — Pages/Profile/Show.vue, two useForm('put') forms, locale select from shared languages.
- profile (BE) — self-service name/email/locale + password change (web + API), ProfileService.
- users (FE) — Pages/Users/{Index,Form}.vue, DataTable filters, CheckboxGroup roles, can.*Users gating.
- users (BE) — CRUD + autocomplete (web + Sanctum API mirror), QueryBuilder filters via App\Utils\AllowedFilter, UserPolicy on flat strings, is_active flag (not enforced at login).
- roles (FE) — Pages/Roles/{Index,Form}.vue, PermissionManager, system-role guard.
- roles (BE) — CRUD over App\Models\Role (UUID, LogsActivity), permission grouping by resource word, SYSTEM_ROLES=['admin'] guard, RolePolicy.
- audit-logs (FE) — Pages/AuditLogs/{Index,Show}.vue, read-only DataTable + JSON diff.
- audit-logs (BE) — read-only viewer over Spatie Activity (ActivityPolicy, view audit logs), causer/subject/date filters.
- media (FE) — Pages/Media/{Index,Show}.vue; FileUploadInput/RichTextEditorInput → POST|DELETE /uploads.
- media (BE) — read-only MediaLibrary viewer (MediaPolicy, view media) + TemporaryUpload staging (POST|DELETE /uploads, upload files, moveToModel, OwnedTemporaryMedia rule, daily purge).
- localisation (FE) — AppLayout language dropdown → <a href="/language/{locale}"> (full reload) → SupportedLanguage {sk,en}.
- localisation (BE) — SupportedLanguage {sk,en}, LocaleMiddleware, GET /language/{locale}, JSON translations resources/lang/*/app.json.
- api-docs (BE) — Scribe 5 at /docs (auth + view api docs), Spatie-Data-aware strategies, api/* only.
- shell (FE) — Layouts/AppLayout.vue (BE navigation, ICONS map, toasts, language switcher), Layouts/Header.vue, Components/DataTable/*, Components/Forms/*, ConfirmDeleteModal + useDeleteConfirm, types/*, utils/{date,bytes}.ts, vue-i18n from resources/lang/*/app.json, DaisyUI app-theme OKLCH tokens.

**Note:** On port from `main` (Phase 2+): modules for Clients, Objects, Quotes, Invoices, RecurringInvoices, Contracts, ContractTemplates, Employees, Schedule/Jobs, Notifications will be added per `.claude/plans/port-from-cleaning-software.md` phase order.

## Lint
lint.tools: [pint, phpstan, vue-tsc, eslint, prettier]
lint.runner: docker
lint.asked: true

## Deployment Status
- **Deployed to production:** no
- **Last verified:** 2026-09-06

## Review rules

Synced with `laravel-13-conventions/references/review-checks.md` §"Distilled house rules" (master copy). See root `REVIEW.md` for the canonical list.

**Laravel 13 distilled house rules:**

1. Validation via Spatie Data attribute DTOs — no FormRequest, no `$request->validate()`, no JsonResource.
2. UUIDv7 PKs (`HasUuids`) — never `$table->id()` / `bigIncrements`.
3. Controllers thin — no business logic, no `DB::transaction` (service owns both); authorization per `## Auth profile`.
4. `Inertia::render` props: Data DTO / `::collect(...)` / scalar only — never bare array / Model / paginator without `->through(...)`.
5. Every user-facing message via `__('<ns>.<key>')` from lang files (validation = auto-lookup, override only per-field via `messages()`); never literal strings. Logs stay English.
6. Response DTOs minimal — no speculative fields without a named consumer.
7. Backed enums for fixed value sets — no magic status/permission strings.
8. `env()` only in `config/`; URLs via named route + `route()`.
9. Eager-load accessed relations (`with` / `withCount`); no `Model::all()` in request paths.
10. Events/jobs dispatched inside a transaction: `ShouldDispatchAfterCommit` / `->afterCommit()`.

**Per-project enforcement:**

- **Auth profile:** rbac-full — Policies gate every action. Controllers use `#[Authorize($ability, $modelOrParam)]` attribute. Single authorization axis: RBAC permission only . Never use `role()` checks; always use `Can` component + `useAuthorization().allows(permission)`.
- **Multi-tenancy:** row-level via `tenant_id` FK. Every domain model MUST have `tenant_id` UUID FK + `BelongsToTenant` trait + `TenantScope` global scope. **CRITICAL:** `setPermissionsTeamId($tenantId)` must be called before any role/permission lookup outside HTTP requests (jobs, console, seeders, tests).
- **TypeScript code generation:** `#[TypeScript]` attribute on all DTOs + Enums. `php artisan typescript:transform` generates `resources/js/types/generated.d.ts`. FE imports from there.
- **Atomic transactions:** Multi-step operations (create owner, issue invoice, generate recurring invoice) wrapped in `DB::transaction` in Service (NEVER Controller). Events/jobs inside transaction must implement `ShouldDispatchAfterCommit`.
- **Spatie Data DTO boundary:** Controllers accept DTO params with `#[Validation]` attributes. No inline `$request->validate()`. No FormRequest. No JsonResource.
- **i18n via locale keys:** Every user-facing string via `__('<ns>.<key>')` from `lang/{sk,en,uk}/*.php`. Logs stay English.

## Rules for agents
1. Hot context = these 3 files (CLAUDE.md, .claude/business.md, .claude/technical.md). Do not read anything else from `.claude/` by default.
2. **Read plans in `.claude/plans/<slug>.md` ONLY if the parent provides an explicit `plan_path`.** NEVER `Glob .claude/plans/*.md`.
3. On ambiguity STOP, NEVER guess. When porting from `main`, the source code on `main` is the spec — when it and business.md disagree, ask.
4. Large features (`plan_size: file`) MUST have a plan approved by a human.
5. **Skill load — architect-as-dirigent**: architect agents eager-load skills themselves in Step 0. Implementation agents (`be-agent`, `fe-agent`, `tester-agent`, `reviewer-agent`, `devops-agent`, `docs-agent`) load **only those skills** explicitly passed to them in the `## Skills (for executor)` section of the prompt.
6. **Every domain model** obeys the multi-tenancy invariant (tenant = firma): `tenant_id` UUID FK + `BelongsToTenant` + per-tenant permission scope (Spatie teams). Non-negotiable once the tenancy phase lands.
