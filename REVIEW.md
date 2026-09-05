# Review Checks — CleanMaster

Review rules for the CleanMaster project. Synced with `laravel-13-conventions/references/review-checks.md` §"Distilled house rules" (master copy).

**Stack target:** Laravel 13, Inertia 3, Vue 3, TypeScript, PostgreSQL 18. **Auth profile:** rbac-full (Policies + `#[Authorize]` per action). **Project type:** laravel-be + inertia-fe. **Greenfield:** yes (no production users, free to refactor schema).

## Distilled house rules — target `CLAUDE.md` §"Review rules" (master copy)

**Laravel 13:**

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

## Per-project enforcement

- **Project initialized:** 2026-06-05
- **Last review rules sync:** 2026-09-05 (this run, from master)
- **Auth profile:** rbac-full — Policies gate every action. Controllers use `#[Authorize($ability, $modelOrParam)]` attribute (method-level for per-action permissions, class-level only when ALL actions share one ability). `PermissionEnum` centralizes all permission strings. **Never** use `role()` checks in FE or BE code; always use `Can` component + `useAuthorization().allows(permission, feature)`.
- **Multi-tenancy:** row-level via `tenant_id` FK. Every domain model MUST have `tenant_id` UUID FK + `BelongsToTenant` trait + `TenantScope` global scope. `TenantContextMiddleware` resolves active tenant from session or first active membership. **CRITICAL:** `setPermissionsTeamId($tenantId)` must be called before any role/permission lookup outside HTTP requests (jobs, console, seeders, tests). Forgetting this is the most common multi-tenant bug.
- **Feature gating:** distinct from RBAC. Account (User) subscription_plan unlocks features. Routes use `->middleware('feature:quotes')` to gate. Tenant resolves features through owner's plan. FE checks `useAuthorization().hasFeature()` before rendering. **Both axes AND together** (permission + feature must both pass).
- **Soft-delete + partial unique:** clients (tenant_id, ico) WHERE deleted_at IS NULL AND ico IS NOT NULL. IČO unique only among *active* clients; resurrected rows don't block re-use. All soft-deleted queries filter by default via global scope.
- **PDF generation:** spatie/laravel-pdf chrome driver requires real arm64 Chromium installed via Playwright in Docker (`/usr/local/bin/chromium-real`). On first pull, run `docker compose build --no-cache laravel.test`. Blade-based templates (Invoice: 3 variants, Quote: 1, Contract: 1). Tests mock `RendersInvoicePdf`/`RendersContractPdf` interfaces to avoid browser in CI.
- **TypeScript code generation:** `#[TypeScript]` attribute on all DTOs in `app/Data/` + all Enums in `app/Enums/`. `php artisan typescript:transform` generates `resources/js/types/generated.d.ts`. FE imports from there, never hand-edits. Props to Inertia must be Data DTO / `::collect(...)` / scalar only.
- **Atomic transactions:** Multi-step operations (register, issue invoice, generate recurring invoice) wrapped in `DB::transaction` in Service (NEVER Controller). Events/jobs dispatched inside transaction must implement `ShouldDispatchAfterCommit`. Jobs must bind tenant context via `setPermissionsTeamId` on worker.
- **Email auto-verified on register:** no email-verification flow. RegistrationService calls `$user->forceFill(['email_verified_at' => now()])->save()` post-creation.
- **Spatie Data DTO boundary:** HTTP controllers accept DTO params with `#[Validation]` attribute validation. No inline `$request->validate()`. No FormRequest. No JsonResource. Single DTO per action pair (store/update share one DTO).
- **i18n via locale keys:** Every user-facing string via `__('<ns>.<key>')` from `lang/{sk,en,uk}/*.php` files. Validation messages auto-lookup by snake_case field name; override via DTO `messages()`. Logs stay English. SK/EN/UA all shipped.
- **Enum + permission centralization:** `PermissionEnum`, `SubscriptionPlanEnum`, `FeatureEnum`, `JobStatusEnum`, `InvoiceStatusEnum`, `ContractStatusEnum`, `QuoteStatusEnum`, `RecurringInvoiceStatusEnum`, `JobTypeEnum`, `TaskFrequencyEnum`, `NotificationTypeEnum`, `ClientTypeEnum`, `ObjectTypeEnum`, `InvoiceTypeEnum`, `InvoiceTemplateEnum`, `PaymentTypeEnum`, `CurrencyEnum`, `RoundingModeEnum`, `ContractCategoryEnum`, `ContractTermTypeEnum`, `EmploymentContractTypeEnum`, `RecurringFrequencyEnum`, `RecurringDefaultStateEnum`, `RecurringInvoiceStatusEnum`, `TenantColorEnum`, `InvitationStatusEnum`, `SupportedLanguage`. All `#[TypeScript]`. No magic status/permission strings in code. Backed enums for fixed value sets (never string keys).
