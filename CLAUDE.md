# CleanMaster

Internal tool for the owner's cleaning companies (SK/CZ). Rebuilt on the canonical inogile `vue-skeleton`
(branch `rebuild`); business modules are ported from branch `main` phase by phase per
`.claude/plans/port-from-cleaning-software.md` (read ONLY when a parent passes it as `plan_path`).

## Stack
- Backend: Laravel 13, PHP 8.5 (Docker: `docker compose exec app php artisan ...`)
- Frontend: Inertia v3 + Vue 3 + TypeScript, Tailwind 4 + DaisyUI 5
- DB: PostgreSQL 16 (compose service `postgres`), Redis 7
- Mail: Mailpit (compose service `:8025`), SMTP in dev/test
- Queue: Database driver, background `queue` service (prod: supervisor) for InvoiceIssued + GenerateRecurringInvoiceJob + GenerateScheduledJobsJob
- PDF/QR: spatie/laravel-pdf (chrome driver), chrome-php/chrome (pure-PHP DevTools), Alpine apk Chromium (arm64 native), pay-by-square + bacon QR code
- Calendar: @fullcalendar/vue3 v6.1.21 (FullCalendar month/week view for schedule jobs)
- Main packages: Spatie Data 4, Permission 7 (teams mode), Activitylog 5, MediaLibrary 11, QueryBuilder 7 + `App\Utils\AllowedFilter`,
  TypeScript Transformer 3, Laravel Boost 2, Scribe; PHPUnit 12; Pint, PHPStan (Larastan), ESLint, Prettier, vue-tsc, Lefthook

## Stack target

**Decisive signal for architect-be-agent and be-agent. Overrides `composer.json` version.**

- **Target Laravel version:** 13
- **Greenfield (no production):** yes
- **Legacy patterns allowed (Repository / FormRequest / JsonResource):** no
- **Last verified:** 2026-09-06 (Phase 5 Quotes complete)

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
docker compose build app queue vite && docker compose up -d # rebuild app/queue on code change (phase 4: Chromium apk)
# Services: app :8000, vite :5173, postgres :5432, redis :6379, mailpit :8025, queue (background worker)

docker compose exec app php artisan test --compact
# Tests run on Postgres DB `cleanmaster_admin_testing` (phpunit.xml force=true; never the dev DB).
# Created automatically on first volume init (docker/postgres/init-testing-db.sql); on an existing volume:
docker compose exec postgres psql -U postgres -c 'CREATE DATABASE cleanmaster_admin_testing;'

docker compose exec app php artisan migrate:fresh --seed
docker compose exec app php artisan typescript:transform
docker compose exec app vendor/bin/pint --dirty --format agent
docker compose exec app vendor/bin/phpstan analyse --memory-limit=1G
docker compose exec app pnpm lint:js && docker compose exec app pnpm lint:prettier && docker compose exec app pnpm typecheck

# Phase 4: Queue + mail + schedule
docker compose logs -f queue                              # watch background job worker
docker compose exec app php artisan schedule:run          # trigger daily crons manually (MarkOverdueInvoices, GenerateRecurringInvoices)
# Browse Mailpit (queued invoices) at http://localhost:8025
```

Login (canonical skeleton admin, never change): `admin@example.com` / `password`.
Vite is started directly from `node_modules/.bin/vite` in `compose.yml` (pnpm 11 deps check bypass); lefthook build recorded in `pnpm-workspace.yaml`.
Phase 4 Chromium: Alpine apk `/usr/bin/chromium-browser` (no Playwright Node download); rebuild image after pull if CHROMIUM_PATH env changes.

## Bootstrap (Phase 2 + Phase 4 supplier data)

No public registration. First account via Artisan; subsequent tenants + team members via web UI (invitations).

```bash
./vendor/bin/sail artisan app:create-owner                                                    # interactive prompts
# or with flags (password in flags visible in ps/history; use prompts when possible):
./vendor/bin/sail artisan app:create-owner \
  --name="Ján Novák" \
  --email="owner@example.com" \
  --password=SecurePassword123 \
  --company="Demo Cleaning s.r.o." \
  --ico="12345678" \
  --address-line="Hlavná 1" \
  --city="Bratislava" \
  --postal-code="811 01" \
  --country="SK" \
  --dic="2012345678" \
  --vat-number="SK2012345678" \
  --vat-payer \
  --contact-email="fakturacia@demo.sk" \
  --contact-phone="+421900000000" \
  --iban="SK8975000000000123456789" \
  --swift="TATRSKBX"
```

Creates User (owner) → Tenant (firma, company, optionally seeded with supplier data) → TenantMembership + RoleTemplatesSeeder 6 role bundles (Admin assigned). Supplier fields optional at bootstrap; if omitted, owner is redirected to Settings→Invoicing to complete profile before any invoice can be issued (drafts allowed on incomplete profile).

**Workflows:**
- **Add another company** — "Pridať novú firmu" modal: name, IČO, optional colour, optional leader email → new Tenant + session switch + dashboard.
- **Invite team member** — email on add-tenant or Users/Create → InvitationCreated mail (7d token) → GET /invitations/{token} → form (password if new account; password if existing account) → POST accepts → logged in, active tenant = inviting tenant.
- **Switch company** — sidebar TenantSwitcher dropdown → POST /tenants/{id}/switch → dashboard (shared props refresh).
- **Remove from tenant** — DELETE membership (removes TenantMembership, revokes roles, User row kept globally).

Authorization: per-tenant (Spatie teams = tenant_id). Login requires is_active=true + hasActiveMembership. Mid-session loss → next request logs out web user (RequireActiveTenant middleware).

**Phase 7 demo cleaner:** ScheduleDemoSeeder creates signed contract → work breakdown → 30-day jobs (September 2026 horizon). Cleaner account: `cleaner@example.com` / `password`, Interná upratovačka role (own-only scoping via absent "all" permissions), 3 assigned jobs (Planned status). Use this account to test schedule index/show, job assignment, calendar view, work-breakdown visibility, Objects access via job linkage (D3 reachability).

## Modules

- auth (FE) — Pages/Auth/{Login,ForgotPassword,ResetPassword}.vue + Components/Auth/{AuthShell, AuthHero, AuthTextField, AuthPasswordField, AuthCheckboxField, AuthSubmitButton, AuthLanguageSwitcher}, brown/amber hero + white form panel, FormProvider + Precognition, string URLs.
- auth (BE) — session login/logout, forgot/reset password, Sanctum Bearer POST /api/auth/login|logout; is_active + hasActiveMembership guards; login/logout/failed logged to Activitylog.
- tenancy (FE) — Components/Tenants/{TenantSwitcher,AddTenantModal,TenantColorDot}, Forms/ColorSwatchPicker, Composables/{usePageProps,useAuthorization,useTenantTheme}, Can component, Pages/Invitations/Accept + Components/Invitations/{InvitationAcceptForm,InvitationBlockedNotice}.
- tenancy (BE) — Tenant/TenantMembership/TenantInterface/TenantInvitation models (UUIDv7 PKs, BelongsToTenant scope). TenantContextMiddleware (D5 resolution: X-Tenant-Id header → session → first active membership). RequireActiveTenant middleware (D4 guards). RegistrationService (createOwner, addTenant, bootstrapTenant), InvitationAcceptService (resolve, accept). RoleTemplatesSeeder per-tenant role bundles (6 roles). Routes: POST /tenants (auth-only), POST /tenants/{id}/switch (TenantPolicy), GET|POST /invitations/{token} (guest, throttle 5/min).
- clients (FE) — Pages/Clients/{Index,Show}.vue, Components/Clients/{ClientTypeBadge,ClientForm,ContactsListField,ClientFormDrawer,ClientDetailCard,ClientContactsList,ClientObjectsTable}.vue, drawer-based CRUD with contact editor (primary auto-promotion), delete soft-deletes client + contacts + all objects.
- clients (BE) — Client/ClientContact models (UUIDv7, SoftDeletes, LogsActivity), ClientService (paginate/create/update with syncContacts, delete with soft-delete cascade of all objects + contacts), ClientPolicy (RBAC full), ClientController with #[NavItem], ClientTypeEnum (#[TypeScript]). Routes: GET|POST|PUT|DELETE /clients{,/{client}}.
- objects (FE) — Pages/Objects/{Index,Show}.vue, Components/Objects/{ObjectTypeBadge,ObjectStatusBadge,ObjectForm,ObjectFormDrawer,ObjectDetailCard,ObjectAccessCard}.vue, drawer-based CRUD with deactivation (is_active toggle), dedicated reactivate endpoint, ObjectAccessCard (sensitive data warning).
- objects (BE) — CleaningObject model (UUIDv7, hybrid is_active+SoftDeletes per D1 override, LogsActivity, withTrashed client relation), ObjectService (paginate with visibleTo actor scoping D2 fail-closed, deactivate action, reactivate action), ObjectPolicy with isVisibleTo guards (D2), ObjectController with #[NavItem], ObjectTypeEnum (#[TypeScript]). Routes: GET|POST|PUT /objects{,/{object}}, POST /objects/{object}/deactivate, POST /objects/{object}/reactivate. Permissions: ViewObjects/CreateObjects/EditObjects/DeleteObjects + ViewAllObjects breadth modifier.
- quotes (FE) — Pages/Quotes/{Index,Create,Edit,Show}.vue, Components/Quotes/{QuoteStatusBadge,QuoteKindBadge,QuoteRoughBadge,QuoteDocumentUpload,QuoteAttachClientPanel,QuoteSubjectPicker,QuoteItemsEditor}.vue, reuses InvoiceItemsEditor + InvoiceTotalsPanel patterns, FileUploadInput (temporary upload contract), attach-client panel conditionally shown (clientless only), QuoteActionsCard + QuoteLinksCard updated for contract conversion (phase 6).
- quotes (BE) — Quote/QuoteItem models (UUIDv7, HasMedia document collection on private DOCUMENTS_DISK, SoftDeletes, LogsActivity, quote_id FK on contracts via HasMany), QuoteStatusEnum/QuoteKindEnum (#[TypeScript]), QuoteService (paginate/create/update/send/accept/reject/attachClient/duplicate/delete/convertToInvoice/convertToContract), DocumentTotalsCalculator extracted (line + totals math, shared by Quote + Invoice, VAT breakdown), RendersQuotePdf contract, QuotePdfService (chrome driver), ExpireQuotes command (daily cron). Events: QuoteSent/QuoteExpired/QuoteExpiring (ShouldDispatchAfterCommit). Rules: ObjectBelongsToClient (DataAwareRule), TemporaryMediaConstraints (mime/size validation). Routes: GET|POST /quotes, GET|PUT|DELETE /quotes/{quote}, POST /quotes/{quote}/{send|accept|reject|duplicate|attach-client|convert-to-invoice|convert-to-contract}, GET /quotes/{quote}/pdf. Policies: QuotePolicy (permission-only), QuotePolicy::convertToContract (CreateContracts permission). Permissions: ViewQuotes/CreateQuotes/EditQuotes/SendQuotes/ApproveQuotes/DeleteQuotes (duplicate separate). Enums: QuoteStatusEnum (draft/sent/accepted/rejected/expired), QuoteKindEnum (itemized/document). Phase 6: convertToContract action + QuoteDetailData.contracts array.
- invoices (FE) — Pages/Invoices/{Index,Create,Edit,Show}.vue, Components/Invoices/{InvoiceStatusBadge,InvoiceTypeBadge,InvoiceItemsEditor,InvoiceSubjectPicker,InvoiceTotalsPanel,InvoiceStatsCards,InvoiceForm,InvoiceFormSummary,InvoiceIssueModal,InvoicePartiesBlock,InvoiceMetaGrid,InvoicePaymentInfo,InvoiceActionsCard,InvoiceLinksCard,InvoiceVatRecap,InvoiceItemsTable,SupplierIncompleteAlert,InvoiceSettingsForm,InvoiceSettingsDrawer}.vue, Composables/useInvoiceSettingsDrawer, composable useInvoiceTotals, utils money.ts. SupplierIncompleteAlert banner on Create/Edit/Show + RecurringInvoices Create/Edit (from supplier_missing_fields). InvoiceSettingsDrawer accessible from Invoices Index header + banner CTA. Phase 5: InvoiceLinksCard shows quote_id backlink (source quote link).
- invoices (BE) — Invoice/InvoiceItem/InvoiceNumberSequence models (UUIDv7, SoftDeletes, LogsActivity, HasPdfFilename trait), InvoiceService (paginate/stats/create/update/issue with supplier guard/markPaid/cancel/duplicate/delete/send, uses DocumentTotalsCalculator), InvoiceNumberService (lockForUpdate numbering), InvoiceSettingsService, contracts RendersInvoicePdf/GeneratesPaymentQr, InvoicePdfService (chrome driver 3 templates), PayBySquareService (QR generation), InvoiceIssued notification (queued, retries), StampInvoiceSentAt listener, InvoiceMarkedOverdue event, MarkOverdueInvoices command. Phase 5: quote_id FK (nullOnDelete), quote() relation, InvoiceDetailData gains quote_id/quote_number. Enums: InvoiceStatusEnum, InvoiceTypeEnum, InvoiceTemplateEnum, PaymentTypeEnum, CurrencyEnum, RoundingModeEnum. Routes: GET|POST /invoices, GET|PUT|DELETE /invoices/{invoice}, POST /invoices/{invoice}/issue/pay/cancel/duplicate/send, GET /invoices/{invoice}/pdf. Policies: InvoicePolicy (per PermissionEnum cases). Permissions: ViewInvoices/CreateInvoices/EditInvoices/CancelInvoices.
- recurring-invoices (FE) — Pages/RecurringInvoices/{Index,Create,Edit,Show}.vue, Components/RecurringInvoices/{RecurringStatusBadge,RecurringFrequencyBadge,RecurringInvoiceForm,RecurringCustomerCard,RecurringScheduleCard,RecurringGeneratedInvoicesCard,RecurringActionsCard}.vue.
- recurring-invoices (BE) — RecurringInvoice/RecurringInvoiceItem models (UUIDv7, SoftDeletes, LogsActivity), RecurringInvoiceService (paginate/create/update/delete/pause/resume/cancel, generateInvoiceFromTemplate), GenerateRecurringInvoiceJob (queued, ShouldBeUnique, D2b: skip auto-issue + warn if supplier incomplete, advance schedule anyway), GenerateRecurringInvoices command. Enums: RecurringFrequencyEnum (monthsInterval, nextRunDate), RecurringInvoiceStatusEnum, RecurringDefaultStateEnum. Routes: GET|POST /recurring-invoices, GET|PUT|DELETE /recurring-invoices/{recurringInvoice}, POST /recurring-invoices/{recurringInvoice}/pause|resume|cancel. Policies: RecurringInvoicePolicy. Permissions: ViewRecurringInvoices/CreateRecurringInvoices/EditRecurringInvoices/DeleteRecurringInvoices.
- settings-invoicing (FE) — Pages/Settings/Invoicing.vue, Components/Invoices/{InvoiceSettingsSupplierCard,InvoiceSettingsBankCard,InvoiceNumberFormatField,InvoiceTemplatePicker,InvoiceTemplateThumbnail,InvoiceSettingsDefaultsCard,InvoiceSettingsForm,InvoiceSettingsDrawer}.vue. InvoiceSettingsForm extracted for reuse (page + drawer). Drawer accessible from Invoices Index header button + banner CTA, allows quick profile completion without navigating away from invoice creation flow.
- settings-invoicing (BE) — InvoiceSettingsService, TenantInterface extended (invoice_template, recurring_default_state, default_constant_symbol/payment_type/currency/rounding_mode), Tenant extended (supplier columns: dic, vat_number, address_line, city, postal_code, country, contact_email, contact_phone, swift_bic). Routes: GET|PUT /settings/invoicing, GET /settings/invoicing/preview/{template}. Permission: ManageBillingSettings (owner only).
- contract-templates (FE) — Pages/ContractTemplates/{Index,Create,Edit,Show}.vue, component ContractTemplateForm (body editor + token list).
- contract-templates (BE) — ContractTemplate model (UUIDv7, BelongsToTenant, SoftDeletes, LogsActivity), ContractTemplateService (paginate/create/update/delete), Enums: ContractCategoryEnum. Routes: GET|POST /contract-templates, GET|PUT|DELETE /contract-templates/{contractTemplate}. Policies: ContractTemplatePolicy. Permissions: ViewContractTemplates/CreateContractTemplates/EditContractTemplates/DeleteContractTemplates.
- contracts (FE) — Pages/Contracts/{Index,Create,Edit,Show}.vue, components ContractStatusBadge/ContractCategoryBadge/ContractTermBadge, ContractBodyEditor (TextareaInput.insertAtCursor), PlaceholderTokenList, ContractBodyPreview, ContractSubjectFields, EmploymentContractFields, ContractForm, ContractTerminateModal, detail cards (ContractPartiesCard/TermCard/EmploymentCard/ActionsCard/LinksCard).
- contracts (BE) — Contract/EmploymentContract models (UUIDv7, BelongsToTenant, SoftDeletes, LogsActivity, polymorphic contractable, quote_id FK), ContractService (paginate/create/update/sign/terminate/delete), PlaceholderResolverService (token resolution), ContractPdfService (RendersContractPdf, chrome driver). Enums: ContractStatusEnum/ContractTermTypeEnum/EmploymentContractTypeEnum/ContractableTypeEnum. Events: ContractSigned/ContractExpired/ContractExpiring. Command: CheckContractExpiry (daily cron). Routes: GET|POST /contracts, GET|PUT|DELETE /contracts/{contract}, POST /contracts/{contract}/sign, POST /contracts/{contract}/terminate, GET /contracts/{contract}/pdf. Policies: ContractPolicy. Permissions: ViewContracts/CreateContracts/EditContracts/TerminateContracts/DeleteContracts. Quote integration: Quote::contracts() HasMany, QuoteService::convertToContract, QuoteDetailData.contracts, QuotePolicy::convertToContract.
- employees (FE) — Pages/Employees/{Index,Create,Edit,Show}.vue, Components/Employees/{EmployeeForm,EmployeeFiltersBar,EmployeeStatusBadge,EmployeeRoleModal}, EmploymentContractFields reuse. AppLayout nav "Zamestnanci" order 20.
- employees (BE) — EmployeeService (paginate/create/update/deactivate over TenantMembership), TenantMembership extended (profile fields first_name/last_name/phone/position, LogsActivity), RoleAssignmentGuard (escalation prevention 422), TenantMembershipPolicy (rbac-full instance checks), EmployeeController with #[NavItem] IdentificationIcon order 20. Routes: GET|POST /employees, GET|PUT|DELETE /employees/{employee}, POST /employees/{employee}/deactivate, POST /employees/{employee}/role. Permissions: ViewEmployees/CreateEmployees/EditEmployees/AssignEmployees/DeleteEmployees. Users module kept (nav settings order 15, both write tenant_memberships). Nullable User.password (invitation flow for new employees).
- schedule (FE) — Pages/Schedule/{Index,Create,Edit,Show}.vue, Components/Schedule/{JobStatusBadge,JobTypeBadge,JobFiltersBar,JobList,JobCalendar (FullCalendar v6.1.21 month/week),JobForm,JobAssignPanel,WorkBreakdownView}, TimeInput.vue, Objects/Show gained read-only Rozpis prác card. Composable useJobCalendar. AppLayout nav "Rozvrh" order 32 + ICONS CalendarDaysIcon.
- schedule (BE) — WorkBreakdown/WorkBreakdownTask/ScheduledJob (table `cleaning_jobs`, BelongsToTenant, LogsActivity, SoftDeletes) models, enums (TaskFrequencyEnum 8 values + recurrence, JobStatusEnum 6 states + matrix, JobTypeEnum 3 types, all #[TypeScript]), WorkBreakdownService (generateFromContract idempotent), JobService (paginate/create/update/assign/cancel/complete/unapprove/unassignFutureForMembership with actor scoping), ScheduledJob::scopeVisibleTo/isVisibleTo (cleaner own-only), CleaningObject::scopeVisibleTo/isVisibleTo (any job reachability D3 override), listener GenerateWorkBreakdownFromSignedContract (ContractSigned → generate → dispatch GenerateScheduledJobsJob afterCommit), GenerateScheduledJobsJob (queued ShouldBeUnique, rolling 30d), GenerateScheduledJobsCommand (daily cron), config/scheduling.php, ScheduleDemoSeeder. ScheduledJobPolicy (rbac-full + scopeVisibleTo), ScheduledJobController with #[NavItem] CalendarDaysIcon order 32. Routes: GET|POST /jobs, GET|PUT|DELETE /jobs/{job}, POST /jobs/{job}/{assign|cancel|complete|unapprove}, GET /jobs/calendar. Permissions: ViewSchedule/CreateSchedule/EditSchedule/AssignCleaners.
- dashboard (FE) — Pages/Dashboard.vue welcome card.
- dashboard (BE) — placeholder GET / route, no props.
- profile (FE) — Pages/Profile/Show.vue, two useForm('put') forms, locale select from shared languages (now sk/en/uk).
- profile (BE) — self-service name/email/locale + password change (web + API), ProfileService, LocaleMiddleware binding.
- users (FE) — Pages/Users/{Index,Form}.vue, DataTable filters (tenant-scoped members), CheckboxGroup roles, allows(...) gating.
- users (BE) — CRUD + autocomplete (tenant members only), QueryBuilder filters, UserPolicy, create-or-link by email, RoleAssignmentGuard escalation checks, TenantMembership pivot scope.
- roles (FE) — Pages/Roles/{Index,Form}.vue, PermissionManager (now PermissionGroupData typed), system-role guard.
- roles (BE) — CRUD per-tenant (Role::inTenant), PermissionEnum (53 cases), permission grouping by resource, SYSTEM_ROLES guard, RolePolicy.
- audit-logs (FE) — Pages/AuditLogs/{Index,Show}.vue, read-only DataTable + JSON diff.
- audit-logs (BE) — read-only viewer over App\Models\Activity (Activity::visibleInTenant scope, tenant_id nullable for login events), filters + policy.
- media (FE) — Pages/Media/{Index,Show}.vue; FileUploadInput/RichTextEditorInput → POST|DELETE /uploads.
- media (BE) — read-only MediaLibrary viewer (App\Models\Media tenant_id NOT NULL), TemporaryUpload staging (tenant_id FK), OwnedTemporaryMedia rule, moveToModel contract, daily purge.
- localisation (FE) — AppLayout language dropdown (sk/en/uk data-driven from shared languages), GET /language/{locale} full reload.
- localisation (BE) — SupportedLanguage enum (sk/en/uk, #[TypeScript]), LocaleMiddleware (user.locale → session → cookie → default sk), JSON translations resources/lang/{sk,en,uk}/{app,validation}.json.
- api-me (BE) — GET /api/me (Sanctum + tenant.required) returns MeData (userId, activeTenantId, permissions per team scope), reserved for mobile app phase 2.
- api-docs (BE) — Scribe 5 at /docs (auth + view api docs), Spatie-Data-aware strategies, api/* only.
- shell (FE) — Layouts/AppLayout.vue (dark sidebar + BrandMark, gradient, TenantSwitcher + AddTenantModal, colour override --color-primary, BE navigation), Layouts/Header.vue, Components/{BrandMark, DataTable/*, Forms/*, Auth/*, Tenants/*, Can, PermissionManager, SideDrawer, EmptyState}, ConfirmDeleteModal + useDeleteConfirm (+ confirmVariant prop phase 4), types/index.d.ts (SharedProps collapse), vue-i18n, DaisyUI app-theme OKLCH tokens.

**Note:** Phases 1–7 complete (2026-09-06). Phase 8+ deferred: Notifications listener wiring (InvoiceOverdue, ContractExpiring, QuoteSent/Expiring events zero-listener phase 7; mobile portal (cleaner + supervisor), customer portal, analytics, integrations. All 7 implemented domains: tenant-scoped (BelongsToTenant), policy-gated (RBAC-full), logged (LogsActivity), soft-deleted where appropriate (no soft-delete on WorkBreakdownTask, only cascade). Cleaner role (Interná upratovačka) has own-only scoping via absent "all" permissions + ScheduledJob::scopeVisibleTo / CleaningObject::scopeVisibleTo (D3 override: any assigned job reachability).

## Lint
lint.tools: [pint, phpstan, vue-tsc, eslint, prettier]
lint.runner: docker
lint.asked: true
lint.notes: |
  - PHPStan at level max with phpstan-baseline.neon (190 entries, regenerated 2026-09-06 after phase 4 invoicing; +2 existing PendingCommand pattern). Run with `--memory-limit=1G` to avoid OOM.
  Baseline burn-down deferred Phase 3+ (targets: test closures ~60, AllowedFilter generics, QueryBuilder chains, Scribe strategies).

## Deployment Status
- **Deployed to production:** no
- **Last verified:** 2026-09-06 (Phase 7 complete: Employees + Schedule + Cleaner scoping, 912 tests, ScheduleDemoSeeder)

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
