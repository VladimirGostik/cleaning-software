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

- **Core:** `App\Models\User` (global identity, UUID, `locale`, `is_active`, `ownedTenants() HasMany`) + `App\Models\Tenant` (firma, UUID, VAT-payer flag, `is_vat_payer`, `vat_rate`, `iban`, `swift_bic`, `registration_info`, `invoice_number_format`, `owner_id` FK to User ON DELETE CASCADE, `interface() HasOne`) + `App\Models\TenantMembership` (User × Tenant pivot, `is_active`, `joined_at`, `first_name`, `last_name`, `phone`, `position` [employee profile], `LogsActivity`).
- **Satellites:**
  - `App\Models\TenantInterface` — 1:1 to Tenant (bigint PK, not UUID). Stores `color` (TenantColorEnum), `invoice_template` (InvoiceTemplateEnum), `invoice_number_format`, invoice defaults: `default_constant_symbol`, `default_payment_type`, `default_currency`, `default_rounding_mode`, `recurring_default_state` (draft|issued).
  - `App\Http\Middleware\TenantContextMiddleware` — resolves active tenant, binds `app('current_tenant_id')`, calls `PermissionRegistrar::setPermissionsTeamId`.
  - `App\Models\Role` extends Spatie's + `LogsActivity` + `search` scope. Per-tenant.
  - `App\Http\Middleware\HandleInertiaRequests` — shares `tenant {active, available}` + `can {…}` + `tenantColors`.
  - `App\Http\Controllers\Auth\*` — login / logout / forgot-password / reset-password.
  - `App\Http\Controllers\Api\MeController` — returns `MeData` (userId, activeTenantId, permissions).
  - `App\Data\Auth\MeData` DTO (`#[TypeScript]`).
- **Flow (login):** `POST /login` → session auth → `TenantContextMiddleware` binds tenant → `/dashboard`.
- **Flow (authorization):** FE navigates → `GET /api/me` → `useAuthorization()` store → `can(permission)` (single axis).
- **Depends on:** nothing (root).
- **Depended on by:** **every** domain model.
- **If you change Core, check:** `TenantContextMiddleware`, `HandleInertiaRequests`, `App\Scopes\TenantScope`, all seeders calling `setPermissionsTeamId`, `MeController`, `TenantFactory`, Tenant model, TenantInterface, User model, InvoiceService snapshots, InvoiceSettingsService, InvoiceController prefill, RecurringInvoiceController prefill, Settings/Invoicing.vue.
- **Keywords:** tenant, firma, membership, Vlastník, active_tenant_id, teams, /api/me, tenantColors, swift_bic, invoice defaults.

### clients

First implemented business domain layer (CRUD, filtering, soft-delete, multi-contact, type enum).

- **Core:** `App\Models\Client` (UUID, tenant_id FK) + `App\Services\ClientService`.
- **Satellites:**
  - `App\Models\ClientContact` — N:1 to Client.
  - DTOs: ClientIndexFilterData, ClientListItemData, ClientDetailData, ClientStoreData, ClientUpdateData.
  - `App\Policies\ClientPolicy`.
  - `App\Enums\ClientTypeEnum` — Corporate | Private.
  - FE: Clients/Index + Show, ClientFormDrawer, useClientFilters.
- **Flow (list):** `GET /clients` → ClientService::paginate → Inertia.
- **Flow (write):** POST|PUT → ClientService → syncContacts diff-apply.
- **Depends on:** `identity-tenancy`.
- **Depended on by:** `objects`, `quotes`, `invoices`, `contracts`.
- **If you change Core, check:** ClientController, ClientPolicy, routes, lang keys, objects domain.
- **Keywords:** klient, kontakt, IČO, DIČ, Corporate, Private.

### objects

Physical cleaning locations, central entity in client → object → (quote → contract → invoice/schedule) chain. **Visibility scoped by permission + assigned jobs** (cleaners see only objects they're assigned jobs at; managers see all).

- **Core:** `App\Models\CleaningObject` (UUID, tenant_id FK, `client_id` FK restrictOnDelete, ObjectTypeEnum, address, access info, special_instructions, area_sqm, floor, is_active flag, GPS reserved for Phase 2, SoftDeletes trait kept for client-cascade use, `jobs() HasMany`) + `App\Services\ObjectService` (`deactivate()` sets `is_active = false` in transaction, replaces delete; `paginate($filter, $actor)` + `optionsVisibleTo($actor)` for scoped queries).
- **Satellites:**
  - `App\Enums\ObjectTypeEnum` — Office | Apartment | House | CommonAreas.
  - DTOs: ObjectIndexFilterData, ObjectListItemData, ObjectDetailData, ObjectStoreData (client_id Rule::exists with tenant scope), ObjectUpdateData, ObjectOptionData.
  - `App\Policies\ObjectPolicy` — instance methods (view, update, delete) **enforce `isVisibleTo(user)`**.
  - FE: Objects/Index + Show, ObjectFormDrawer, useObjectFilters. Show.vue has deactivate action (hidden if already inactive) + reactivation via edit drawer. Index hides client filter when `clients.length === 0` (own-only actors). Optional hint `objects.own_only_hint`.
  - Route: `POST /objects/{object}/deactivate` (was `DELETE /objects/{object}` soft-delete).
- **Flow (list):** GET `/objects` → ObjectController@index → ObjectService::paginate($filter, $actor) (wraps `CleaningObject::query()->visibleTo($actor)` + eager-loads client). Clients prop only for `view all objects` holders.
- **Flow (show):** GET `/objects/{object}` → ObjectPolicy::view checks `can(view objects) && $object->isVisibleTo($user)`. Clients prop only when `can('update', $object)`.
- **Flow (deactivate):** POST `/objects/{object}/deactivate` (guard 'delete', route.show) → ObjectService::deactivate (is_active=false) → redirect to objects.show with flash `app.objects.deactivated`.
- **Visibility scoping:**
  - `App\Models\CleaningObject::scopeVisibleTo(Builder, User)` — query scope. Actor holds `ViewAllObjects` → sees all; else only objects reachable via `whereHas('jobs', assigned_membership_id === actor->activeMembershipId())`.
  - `isVisibleTo(User)` — record scope. Same logic via `visibleTo($actor)->whereKey($id)->exists()`.
  - Lives in model only (opt-in scope), not global scope (cron paths have no actor).
- **Depends on:** `identity-tenancy`, `clients`.
- **Depended on by:** `quotes`, `contracts`, `schedules`, `invoices`.
- **If you change Core, check:** ObjectController (list: paginate + clients gating; show: clients gating; policy checks), ObjectPolicy (isVisibleTo in view/update/delete), routes (POST /deactivate, no DELETE), ObjectService (paginate + optionsVisibleTo using scopeVisibleTo), CleaningObject model (scopeVisibleTo + isVisibleTo + jobs relation), lang keys (objects.deactivate*, objects.own_only_hint), RoleTemplatesSeeder (ViewAllObjects assignment), Client::destroy (still soft-deletes via cascade), JobService::generateForBreakdown guard (skips generation for inactive objects), Quote/Invoice/Contract/Schedule object pickers (note: not scoped; reside behind create abilities no own-only role holds), tests (CleanerActorScopingTest + ObjectPolicyTest).
- **Keywords:** lokality, umiestnenie, prístupové kódy, office, apartment, house, common_areas, central entity, deactivation, is_active, visibility, scope, owner-all, membership-own.

### quotes

Proposal / price quote workflow. **Two kinds:** itemized (line items with per-row VAT rate + discount, converts to invoice/contract) or document-only (archive file + metadata, no conversions). Clientless support via snapshot customer fields.

- **Core:** `App\Models\Quote` (UUID, tenant_id FK, nullable `client_id` FK + mutual-exclusive snapshot: `customer_name`, `customer_email`, `customer_street`, `customer_city`, `customer_postal_code`, `cleaning_object_id` nullable FK, `number` optional max 50, `issue_date`, `valid_until`, `kind` QuoteKindEnum [itemized|document, immutable], status QuoteStatusEnum, sent_at/accepted_at/rejected_at, implements HasMedia + InteractsWithMedia [document collection]) + `App\Models\QuoteItem` (UUID, cascadeOnDelete, description, quantity, unit_price, discount_percent, vat_rate, line_base/line_vat/line_total, only on itemized). `App\Services\QuoteService` (paginate/create/update/delete/send/accept/reject/duplicate/attachToClient/uploadDocument/convertToInvoice/convertToContract, NotificationRecipientResolver). **NO QuoteNumberService** (auto-numbering removed; number is manual).
- **Satellites:**
  - `App\Enums\QuoteStatusEnum` — Draft|Sent|Accepted|Rejected|Expired. Validators exclude conversions for document kind.
  - `App\Enums\QuoteKindEnum` — Itemized|Document (`#[TypeScript]`). Immutable after create.
  - DTOs: QuoteUpsertData (client_id + snapshot fields mutually exclusive, prohibits validation), QuoteItemData (itemized only), QuoteListItemData, QuoteDetailData, QuoteIndexFilterData, QuoteAttachClientData (new, client_id + optional cleaning_object_id), QuoteDocumentUploadData (new, multipart file field).
  - `App\Data\Media\MediaFileData` (new, reusable: id, name, url [private disk, policy-gated route], mimetype, size) — used for document downloads.
  - `App\Policies\QuotePolicy` + new `attachClient(client_id IS NULL && kind === Itemized)`, `uploadDocument(kind === Document)`.
  - `App\Http\Controllers\QuoteController` — 16 routes (+attachClient, +document upload/download).
  - `App\Console\Commands\ExpireQuotes` — daily scheduled (itemized only).
  - `App\Services\Pdf\QuotePdfService` — chrome driver. Itemized: single Blade template. Document: N/A (serves uploaded file).
  - `config/documents.php` (new) — MEDIA_DISK default local (s3 in prod), max 10240 KB, mimes: application/pdf, image/jpeg/png/webp, validated mimetypes: (content sniff, not extension).
  - `App\Enums\PermissionEnum` — existing ViewQuotes/CreateQuotes/EditQuotes/SendQuotes/ApproveQuotes/DeleteQuotes/DuplicateQuotes/ConvertToInvoice/ConvertToContract (attach-client reuses EditQuotes).
  - Routes feature-gated `feature:quotes`. New: `POST /quotes/{quote}/attach-client`, `POST /quotes/{quote}/document`, `GET /quotes/{quote}/pdf` (policy-gated download).
  - FE: Quotes/{Index,Create,Edit,Show}, QuoteForm (kind selector, conditional items|document fields), QuoteItemsEditor, QuoteSubjectPicker (clientless mode), QuoteStatusBadge, useQuoteFilters, useInvoiceTotals + InvoiceVatRecap (shared). FileDropInput.vue (new, generic upload primitive, no HTTP). AppLayout nav.
  - Tests: 7 files — 57 tests (up from 50) covering itemized/document kinds, clientless mode, attachment, upload, policy, command.
- **Flow (create itemized):** GET `/quotes/create` → kind=itemized → props → QuoteForm → POST → QuoteService::create (resolve client|snapshot, compute VAT recap, persist kind=itemized).
- **Flow (create document):** GET `/quotes/create` → kind=document → props → QuoteForm (file field, no items) → POST → QuoteService::create (kind=document, items ignored).
- **Flow (attach client):** POST `/quotes/{id}/attach-client` (guard client_id IS NULL + kind=itemized) → QuoteService::attachToClient (client + opt object, clear snapshot).
- **Flow (upload document):** POST `/quotes/{id}/document` (multipart, guard kind=document) → Quote->addMedia(file)->toMediaCollection('document') → replace existing.
- **Flow (download document):** GET `/quotes/{id}/pdf` → policy check + MediaFileData return (route via policy gate, disk private) OR itemized: render Blade.
- **Flow (send, itemized only):** POST `/quotes/{id}/send` (guard kind=itemized) → status Draft→Sent, stamp sent_at, dispatch QuoteSent to view permissions holders.
- **Flow (accept/reject, itemized only):** POST `/quotes/{id}/accept|reject` (guard kind=itemized) → status Sent→Accepted|Rejected.
- **Flow (convert, itemized + accepted only):** POST `/quotes/{id}/convert-invoice|convert-contract` (guard kind=itemized + canBeConverted) → delegate to InvoiceService|ContractService (set backlink quote_id).
- **Flow (expiry cron, itemized only):** `app:expire-quotes` daily → itemized Draft|Sent past valid_until → Expired + QuoteExpired notification.
- **Depends on:** `identity-tenancy`, `clients`, `objects`.
- **Depended on by:** `invoices` (optional quote_id backlink), `contracts` (optional quote_id backlink), `schedules` (itemized Quote converts → WorkBreakdown).
- **If you change Core, check:** QuoteController (16 actions, kind selector in create/edit), QuotePolicy (attachClient + uploadDocument guards), routes (attachClient, document upload/download), QuoteService (attachToClient, uploadDocument, convertToInvoice/convertToContract only for itemized, kind immutability check on update), InvoiceService::create + ContractService::create (when quote_id passed), QuoteItem creation (skipped for kind=document), lang keys, RoleTemplatesSeeder, Quote model (HasMedia, InteractsWithMedia, kind enum cast), ExpireQuotes command (itemized filter), QuotePdfService (kind branch logic).
- **Keywords:** cenová ponuka, itemized/document, clientless nástrelná, line items, VAT rate, discount, attach-client, upload document, status, send, accept, reject, convert, quote_id backlink, expire cron, MediaLibrary, private disk.

### invitation-accept

Token-based invitation acceptance (guest-accessible). Turns Pending TenantInvitation into active membership + role, with user upsert. **The only way to join after bootstrap.**

- **Core:** `App\Http\Controllers\InvitationController` (show/accept on GET|POST /invitations/{token}, guest) + `App\Services\InvitationAcceptService` — resolve + accept (in DB::transaction).
- **Satellites:**
  - `App\Data\Invitations\AcceptInvitationData` — password, ?name.
  - `resources/js/Pages/Invitations/Accept.vue` — 4 states: expired / wrong_user / existing_user / new_user.
  - `TenantInvitation::isAcceptable()` + `markAccepted()`.
  - Tests in tests/Feature/InvitationAcceptTest.php.
- **Flow:** GET /invitations/{token} → resolve (no tenant scope) → state branch: logged-in same-email → auto-accept; otherwise Accept.vue → POST → accept transaction: existing user Hash::check password; new user create (email_verified_at forceFill) → setPermissionsTeamId → membership create|reactivate → assign role → markAccepted → post-commit Auth::login + session active_tenant_id → /dashboard.
- **Depends on:** `identity-tenancy`.
- **Depended on by:** add-tenant.
- **If you change Core, check:** routes (guest), TenantInvitation::isAcceptable semantics (7d expiry), TenantController, partial unique index, Accept.vue state.
- **Keywords:** pozvánka, invitation, token, accept, membership reactivation, same-email auto-accept, user upsert, InvitationCreated mail.

### invoices

Invoicing domain (three subject modes, historical snapshot, SK compliance + VAT breakdown + deposit, PDF/QR, lifecycle, optional recurring generation).

- **Core:** `App\Models\Invoice` (UUID, tenant_id FK, subject mode [client_id|cleaning_object_id|NULL] + snapshot + VAT + SK fields + status + optional quote_id FK + optional recurring_invoice_id FK) + `App\Models\InvoiceItem` (UUID, cascadeOnDelete, description, quantity, unit_price, discount_percent, vat_rate, line_base/line_vat/line_total). `App\Services\InvoiceService` (paginate/create/update/issue/markPaid/cancel/duplicate, syncItems, computeTotals). `App\Services\InvoiceNumberService` — next(Tenant, DateTimeInterface): string, variableSymbol(string): ?string.
- **Satellites:**
  - `App\Models\InvoiceNumberSequence` — bigint PK, per-tenant per-year.
  - `App\Enums\{InvoiceStatusEnum, InvoiceTypeEnum, InvoiceTemplateEnum, PaymentTypeEnum, CurrencyEnum, RoundingModeEnum}` — `#[TypeScript]`. PaymentTypeEnum: Transfer|Cash|Card|CashOnDelivery|Other. CurrencyEnum: EUR|CZK|USD (uppercase ISO 4217). RoundingModeEnum: None|Document|Cash005 with round(float): float method.
  - DTOs: InvoiceUpsertData, InvoiceIssueData, InvoiceItemData, InvoiceListItemData, InvoiceDetailData, InvoiceSupplierData, InvoiceSettingsData, InvoiceIndexFilterData, VatBreakdownLineData. Single UpsertData for store+update. Tenant-scoped exists checks. InvoiceUpsertData: float deposit (default 0), SK-standard: constant_symbol, specific_symbol, PaymentTypeEnum, CurrencyEnum, RoundingModeEnum, header_text, footer_text, optional quote_id. InvoiceDetailData: deposit string, balance_due accessor, vat_breakdown array. VatBreakdownLineData: rate/base/vat/total per VAT rate. InvoiceSupplierData: swift snapshot of Tenant.swift_bic.
  - `App\Data\Objects\ObjectOptionData` — lightweight: id, name, client_id.
  - `App\Policies\InvoicePolicy` — viewAny/view → ViewInvoices; create → CreateInvoices; update/issue/markPaid → EditInvoices; cancel/delete → CancelInvoices.
  - `App\Http\Controllers\{InvoiceController, InvoiceSettingsController}` — thin, `#[Authorize]` per action. Index/Create/Show/Edit/Destroy + issue/pay/cancel/duplicate (POST). Create/Edit return objects, vatRateOptions, paymentTypeOptions, currencyOptions, roundingModeOptions, invoiceDefaults (from TenantInterface).
  - `App\Services\InvoiceSettingsService` — updates Tenant + TenantInterface in transaction.
  - `App\Services\Pdf\InvoicePdfService` — chrome driver (arm64 Chromium). Blade views resources/views/pdf/invoices/{classic,modern,minimal}.blade.php.
  - `App\Services\Pdf\PayBySquareService` — Pay-by-Square QR (engazan/pay-by-square + bacon/bacon-qr-code). Amount = invoice->balance_due (deposit reduces). Currency guard: null if currency !== EUR. Optional BIC from invoice->supplier_swift.
  - `App\Notifications\InvoiceIssued` — mail-only, #[Tries(3)], #[Backoff([10,30,60])], #[Timeout(120)], afterCommit(). Via: ['mail']. Loads Invoice via withTrashed(), calls RendersInvoicePdf::render(), attaches PDF. failed() logs exception. Replaced old SendInvoiceEmail job + InvoiceIssuedMail.
  - `App\Listeners\StampInvoiceSentAt` — handles NotificationSent, stamps Invoice.sent_at when notification instanceof InvoiceIssued. Registered via Event::listen.
  - `App\Console\Commands\MarkOverdueInvoices` — daily scheduled, flips Issued past due → Overdue (scope-bypassed). Dispatches InvoiceOverdue notification.
  - Routes feature-gated `feature:invoices`.
  - FE: Invoices/{Index,Create,Edit,Show}, components (InvoiceForm, InvoiceItemsEditor, InvoiceStatusBadge, InvoiceSubjectPicker, InvoiceVatRecap), Settings/Invoicing.vue, composables (useInvoiceFilters, useInvoiceTotals).
  - Tests: 6+ files covering service, policy, controller, command, PDF, VAT calc, SK fields.
- **Flow (create from blank):** GET `/invoices/create` (feature:invoices, #[Authorize]) → props: clients, objects (opt), vatRateOptions, paymentTypeOptions, currencyOptions, roundingModeOptions, invoiceDefaults (from TenantInterface) → InvoiceForm → POST → InvoiceService::create (resolve subject, compute VAT recap, persist) → redirect /invoices/{id}.
- **Flow (issue):** POST `/invoices/{id}/issue` (guard Draft) → InvoiceService::issue → status Draft→Issued, auto|manual number, freeze snapshot (customer/object/supplier data, SK fields, VAT breakdown), dispatch InvoiceIssued (queued, PDF attached).
- **Depends on:** `identity-tenancy`, `clients`, `objects`, `quotes` (optional backlink).
- **Depended on by:** `recurring-invoices` (template-driven generation).
- **If you change Core, check:** InvoiceController (10+ actions), InvoicePolicy, InvoiceService (issue snapshot, convertToInvoice), InvoiceSettingsService, lang keys, RoleTemplatesSeeder, Invoice model relations.
- **Keywords:** faktúra, mesačná/jednorazová/špeciálna, draft/issued/paid/overdue, VAT breakdown, deposit, balance_due, SK-compliance (KS/ŠS/payment_type/currency/rounding/swift), quote_id backlink, Pay-by-Square QR.

### recurring-invoices

Template-driven automatic invoice generation on a schedule. Model + service + queued job + daily cron + settings integration.

- **Core:** `App\Models\RecurringInvoice` (UUID, tenant_id FK, subject mode + template SK fields: constant_symbol/payment_type/currency/rounding_mode/header_text/footer_text/deposit, frequency enum + start_date + termination mode [forever|until end_date|up to occurrences_limit], auto_issue flag, occurrences_generated counter, next_run_at date, status RecurringInvoiceStatusEnum) + `App\Models\RecurringInvoiceItem` (UUID, cascadeOnDelete, per-item vat_rate/discount_percent). `App\Services\RecurringInvoiceService` (create/update/paginate + pause/resume/cancel/delete + generateInvoiceFromTemplate).
- **Satellites:**
  - `App\Enums\{RecurringFrequencyEnum, RecurringInvoiceStatusEnum, RecurringDefaultStateEnum}` — `#[TypeScript]`. Frequency: Monthly|Every2Months|Quarterly|SemiAnnually|Annually with monthsInterval() + nextRunDate(). Status: Active|Paused|Completed|Cancelled. DefaultState: Draft|Issued.
  - DTOs: RecurringInvoiceUpsertData, RecurringInvoiceItemData, RecurringInvoiceListItemData, RecurringInvoiceDetailData. UpsertData: subject mode, items (per-item vat_rate/discount), frequency + termination mode (3-way radio: forever|end_date|occurrences_limit), auto_issue + SK fields + deposit, prefilled from TenantInterface defaults.
  - `App\Policies\RecurringInvoicePolicy` — gates viewAny/view/create/update/delete.
  - `App\Http\Controllers\RecurringInvoiceController` — thin, `#[Authorize]`. Routes: recurring-invoices.*, feature-gated feature:invoices.
  - `App\Jobs\GenerateRecurringInvoiceJob` (queued, ShouldBeUnique, 3 retries): binds tenant + setPermissionsTeamId on worker; generates invoice via RecurringInvoiceService::generateInvoiceFromTemplate() (maps per-item vat_rate/discount, passes template SK fields + deposit, delegates to InvoiceService::create, links via recurring_invoice_id), optionally auto-issues (per RecurringInvoice.auto_issue OR tenant recurring_default_state), increments occurrences_generated, advances next_run_at, marks Completed on limit/end_date.
  - `App\Console\Commands\GenerateRecurringInvoices` — finds Active rows with next_run_at <= today, dispatches one job per row.
  - `TenantInterface` new columns: recurring_default_state (RecurringDefaultStateEnum).
  - Routes feature-gated `feature:invoices`.
  - FE: full CRUD (RecurringInvoices/{Index,Create,Edit,Show}) + components (Form with 3-way radio + SK fields/deposit + per-item VAT-rate/discount cols, ItemsEditor, Filters, Status badge) + settings. Settings page: recurring_default_state toggle + invoice defaults from Settings.
  - Tests: 6 files covering frequency logic, service lifecycle, job idempotency, command scheduling, controller authorization, settings integration, VAT calc, SK fields.
- **Flow (create):** GET `/recurring-invoices/create` (feature:invoices, #[Authorize]) → props → RecurringInvoiceForm (3-way radio) → POST → RecurringInvoiceService::create (resolve subject, compute next_run_at, persist) → redirect.
- **Flow (generation):** Daily `app:generate-recurring-invoices` finds Active with next_run_at <= today → dispatches GenerateRecurringInvoiceJob per row → job calls RecurringInvoiceService::generateInvoiceFromTemplate() → optionally auto-issues per auto_issue flag OR tenant recurring_default_state → increments occurrences_generated + advances next_run_at → marks Completed on limit/end_date.
- **Depends on:** `identity-tenancy`, `invoices` (generation delegates), `clients`, `objects`.
- **Depended on by:** nothing (leaf).
- **If you change Core, check:** RecurringInvoiceController (8 actions), RecurringInvoicePolicy, RecurringInvoiceService (generateInvoiceFromTemplate), GenerateRecurringInvoiceJob (tenant + permissions), GenerateRecurringInvoices command, TenantInterface (recurring_default_state + defaults), InvoiceService::create (when recurring_invoice_id passed), lang keys, RoleTemplatesSeeder, Settings/Invoicing page.
- **Keywords:** recurring invoice, template, frequency, termination mode, auto_issue, SK fields, deposit, generate job, cron.

### contracts

Polymorphic contract workflow (service agreements + employment) with templates + token substitution + PDF generation + lifecycle/expiry.

- **Core:** `App\Models\Contract` (UUID, tenant_id FK, polymorphic contractable_type/contractable_id targets CleaningObject or TenantMembership, contract_template_id nullable FK, category ContractCategoryEnum, term_type ContractTermTypeEnum, body text [snapshot, frozen post-sign], status ContractStatusEnum, valid_from date, end_date nullable date, signed_at/terminated_at timestamps, optional quote_id FK for audit) + `App\Models\EmploymentContract` (child HasOne, type EmploymentContractTypeEnum, position, salary, hours, probation_end_date — cascades). `App\Services\ContractService` (paginate/create/update/sign/terminate/delete, cross-field guards). `PlaceholderResolverService` — tenant-safe resolve(body, contractable) token substitution + catalogFor(kind).
- **Satellites:**
  - `App\Models\ContractTemplate` (UUID, tenant_id FK, category ContractCategoryEnum, body text, is_active flag). Per-tenant reusable. Full CRUD via ContractTemplateController.
  - `App\Enums\{ContractCategoryEnum, ContractStatusEnum, ContractTermTypeEnum, EmploymentContractTypeEnum}` — `#[TypeScript]`. Category: ServiceAgreement|Employment|NDA|GDPR|Other. Status: Draft|Active|Expired|Terminated. TermType: Fixed|Indefinite. EmploymentContractType: DPP|DPČ|TPP|Živnosť.
  - DTOs: ContractUpsertData, EmploymentContractData, ContractListItemData, ContractDetailData, ContractTemplateUpsertData, ContractTemplateListItemData, PlaceholderTokenData.
  - `App\Policies\{ContractPolicy, ContractTemplatePolicy}` — gates all actions.
  - `App\Http\Controllers\{ContractController, ContractTemplateController}` — thin, `#[Authorize]`. Full CRUD + POST sign/terminate/downloadPdf + GET pdf. Template CRUD separate.
  - `App\Services\Pdf\ContractPdfService` — chrome driver (arm64 Chromium). Blade-based, mirrors invoice PDF infrastructure.
  - `App\Console\Commands\CheckContractExpiry` — daily scheduled. Fixed-term Active past end_date → Expired + ContractExpired notification; upcoming 30/14/7d → ContractExpiring notification + structured log.
  - `App\Enums\PermissionEnum` — added ViewContracts, CreateContracts, EditContracts, TerminateContracts, DeleteContracts, ViewContractTemplates, CreateContractTemplates, EditContractTemplates, DeleteContractTemplates.
  - Routes feature-gated `feature:contracts`.
  - FE: full CRUD (Contracts/{Index,Create,Edit,Show}, ContractTemplates/{Index,Create,Edit,Show}) + components (ContractForm [category-based fields], ContractTemplateForm [body editor + token-insert], EmploymentContractFields, status badges, filters). AppLayout nav: "Zmluvy" + "Šablóny zmlúv".
  - Tests: 6+ files covering service, policy, controller, command, PDF, lifecycle/expiry.
- **Flow (create):** GET `/contracts/create` (feature:contracts, #[Authorize]) → props: templates (by category), polymorphic subject options → ContractForm (category selects fields) → POST → ContractService::create (resolve subject + template [opt], render body tokens via PlaceholderResolverService::resolve(), persist) → redirect.
- **Flow (sign):** POST `/contracts/{id}/sign` → guard Draft + canBeSigned → status Draft→Active, stamp signed_at.
- **Flow (terminate):** POST `/contracts/{id}/terminate` → guard Active + canBeTerminated → status Active→Terminated, stamp terminated_at.
- **Flow (expiry cron):** `app:check-contract-expiry` daily → fixed-term Active past end_date → Expired + ContractExpired notification; upcoming 30/14/7d → ContractExpiring + structured log.
- **Depends on:** `identity-tenancy`, `objects` (optional polymorphic), `employees` (optional polymorphic), `quotes` (optional backlink).
- **Depended on by:** `schedules` (ScheduledJob optional FK, WorkBreakdown ties to service agreement).
- **If you change Core, check:** ContractController (7+ actions), ContractTemplateController (5 actions), ContractPolicy, ContractService (sign/terminate guards, token resolution), PlaceholderResolverService, CheckContractExpiry, lang keys, RoleTemplatesSeeder.
- **Keywords:** zmluva, service agreement, employment, template, placeholder, token, sign, terminate, lifecycle, expiry cron.

### employees

Management layer over TenantMembership (employee = User + TenantMembership, NO new core model). CRUD: list/create/edit/show/deactivate. Extended TenantMembership with profile fields + employmentContract morphMany. **Deactivation unassigns all future scheduled jobs.**

- **Core:** `App\Services\EmployeeService` (paginate/create/update/deactivate). **Security guards:** permission-grant intersect with actor's own (prevent escalation), role-assignment subset guard (assigned role perms ⊆ actor's). Deactivation calls `WorkBreakdownService::unassignFutureForMembership(membership)` to clear future job assignments. `TenantMembership` extended: nullable first_name/last_name/phone/position, LogsActivity, employmentContract morphMany.
- **Satellites:**
  - `App\Enums\PermissionEnum` — ViewEmployees, CreateEmployees, EditEmployees, AssignEmployees, DeleteEmployees.
  - DTOs: EmployeeIndexFilterData, EmployeeListItemData, EmployeeDetailData, EmployeeUpsertData. UpsertData: email, role_name, optional EmploymentContractData. ListItemData: row + full_name, status. DetailData: profile + role + permission overrides array + optional employmentContract.
  - `App\Policies\TenantMembershipPolicy` (rbac-full: viewAny/view→ViewEmployees, create→CreateEmployees, update→EditEmployees, delete→DeleteEmployees).
  - `App\Http\Controllers\EmployeeController` (thin, `#[Authorize]`). Routes: employees.{index,create,store,show,edit,update} + POST employees.deactivate, whereUuid. Optional draft EmploymentContract on employment data.
  - Invitation (InvitationCreated mail) only for newly-created users, deferred via afterCommit.
  - `RoleTemplatesSeeder` — Admin (const ADMIN_ROLE): all permissions; Vedúca: view+assign employees (+ schedule/objects/complaints/photos); Interná upratovačka: view schedule + view objects (no all-perms, enforces own-only scoping).
  - FE: Pages/Employees/{Index,Create,Edit,Show} (separate pages, mirror Contracts) + components EmployeeForm (reuses EmploymentContractFields), EmployeeFiltersBar, PermissionCheckboxGroups, EmployeeStatusBadge. AppLayout nav "Zamestnanci".
  - Tests: 3 files (EmployeeServiceTest, EmployeePolicyTest, EmployeeControllerTest) covering escalation guards, permission intersect, service update, policy, deactivation.
- **Flow (create):** POST `/employees` → EmployeeService::create → new-user: create (auto-verified), dispatch InvitationCreated mail; or link existing → assign role within tenant (subset guard: role perms ⊆ actor perms) → optional draft EmploymentContract.
- **Flow (edit):** PUT `/employees/{employee}` → EmployeeService::update → update profile + role + permission overrides (intersect guard: grants ⊆ actor's own).
- **Flow (deactivate):** POST `/employees/{employee}/deactivate` → EmployeeService::deactivate → is_active=false → `WorkBreakdownService::unassignFutureForMembership()` clears future job assignments (prevents cleaner from appearing on scheduled work after leaving).
- **Depends on:** `identity-tenancy`, `contracts` (optional EmploymentContract morphMany).
- **Depended on by:** `schedules` (ScheduledJob optional assigned_membership_id FK; deactivation triggers unassign hook).
- **If you change Core, check:** EmployeeService (escalation guards, deactivation hook unassignFutureForMembership call), TenantMembershipPolicy, TenantMembership (profile columns, employmentContract, LogsActivity), RoleTemplatesSeeder (ADMIN_ROLE constant, Vedúca/Interná upratovačka perms, ViewAllObjects/ViewAllSchedule assignments), EmployeeController (permission intersect before role assign), lang keys (role names: employee_role.admin, employee_role.interna_upratovacka), WorkBreakdownService::unassignFutureForMembership hook call.
- **Keywords:** zamestnanec, profil, úloha, role assignment, permission grants, deactivation, interna_upratovacka, admin.

### notifications

Two-audience native Laravel notification system. **Internal tenant notifications** stored in DatabaseNotification (per-tenant via custom channel + manual filter). **External mail-only** notifications (InvoiceIssued, InvitationCreated).

- **Core:** `NotificationService` (paginate/bell/markRead/markAllRead/updatePreferences, manual tenant scoping). `NotificationRecipientResolver` — resolves users by permission, calls setPermissionsTeamId first.
- **Satellites:**
  - `App\Notifications\BaseTenantNotification` abstract for internal subclasses.
  - `App\Notifications\{InvoiceOverdue, ContractExpiring, ContractExpired, QuoteSent, QuoteExpiring, QuoteExpired}` — tenant-audience (internal DB via custom TenantDatabaseChannel; optional mail per users.notification_preferences JSON).
  - `App\Notifications\{InvoiceIssued, InvitationCreated}` — mail-only (no DB row, no prefs).
  - `App\Notifications\Channels\TenantDatabaseChannel` — custom channel, injects tenant_id on every write. Registered in AppServiceProvider.
  - `App\Enums\NotificationTypeEnum` — 8 types, `#[TypeScript]`. Maps notification class to enum value + label.
  - `App\Policies\NotificationPolicy` (viewAny + record-level update with tenant check).
  - `App\Http\Controllers\NotificationController` (thin) — index/read/read-all, `#[Authorize]`.
  - Web routes: notifications.{index,read,read-all} + settings.notifications (GET/PUT).
  - API: GET `/api/notifications/bell` → NotificationBellData (unreadCount, recent).
  - FE: useNotificationsStore Pinia (60s poll, fetchBell, markReadLocally, reset), NotificationBellService, 4 bell components (Bell/Item/TypeBadge/PreferenceRow), Notifications/Index + Settings/Notifications, AppLayout bell integration. Permissions: view notifications (Vlastník/Vedúca/Sekretárka/Účtovníčka), configure notifications (PermissionEnum, not role-seeded, allows per-user prefs).
  - Crons wired: app:mark-overdue-invoices → InvoiceOverdue; app:check-contract-expiry → ContractExpired/ContractExpiring; app:expire-quotes → QuoteExpired/QuoteExpiring. QuoteService::send → QuoteSent.
  - Tests: 6 files in tests/Feature/Notifications/.
- **Flow (internal notification):** Cron or action triggers (e.g., InvoiceOverdue). NotificationRecipientResolver::usersWithPermission($tenantId, 'view invoices') returns User list (with setPermissionsTeamId called first). Send NotificationClass::send($users) → TenantDatabaseChannel writes DatabaseNotification rows with tenant_id + type + data → FE polls /api/notifications/bell every 60s, displays in bell + notification centre.
- **Flow (mail notification):** InvoiceIssued or InvitationCreated queued (afterCommit). InvoiceIssued renders PDF, attaches. StampInvoiceSentAt listener on NotificationSent stamps invoice.sent_at. InvitationCreated sent to invitee email (not tenant-scoped).
- **Depends on:** `identity-tenancy`.
- **Depended on by:** `invoices`, `quotes`, `contracts`, `employees`.
- **If you change Core, check:** TenantDatabaseChannel (tenant_id injection), NotificationService (tenant scoping), NotificationRecipientResolver (permission lookup), NotificationPolicy, NotificationController, all cron commands (dispatch call), lang keys, StampInvoiceSentAt listener registration, handleInertiaRequests (viewNotifications in can prop).
- **Keywords:** notification, database channel, bell, preferences, tenant-scoped, mail-only, cron-triggered, permission-based recipient, NotificationTypeEnum.

### schedules

Three-layer work breakdown + scheduled jobs system. Work breakdown (Rozpis prác) from service agreement contract; scheduled jobs (Zákazky) on rolling 30d horizon with **permission-based + membership-scoped visibility** (owner-only and cleaners see their own; managers see all).

- **Core:** `App\Models\WorkBreakdown` (UUID, tenant_id FK, cleaning_object_id FK, contract_id HasOne, polymorphic to service_agreement contracts) + `App\Models\WorkBreakdownTask` (UUID, tenant_id FK, child per quote item, frequency: one_time/weekly_1x/seasonal, cascade delete, no logging) + `App\Models\ScheduledJob` (UUID, tenant_id FK, table cleaning_jobs [avoid jobs/Job collision], cleaning_object_id FK, assigned_membership_id nullable FK [tenant+active scope], optional work_breakdown_task_id FK, optional contract_id FK backlink, optional invoice_id FK [Phase 2]). All BelongsToTenant + HasUuids. `App\Services\JobService` (paginate($filter, $actor)/create/update, assign(job, ?membershipId) [single nullable FK], cancel/complete/unapprove lifecycle, unassignFutureForMembership(membership) [called by EmployeeService::deactivate]).
- **Satellites:**
  - `App\Enums\{JobTypeEnum, JobStatusEnum, TaskFrequencyEnum}` — `#[TypeScript]`. JobType: Regular|OneOff|Special. JobStatus: Planned|Unassigned|InProgress|Completed|Unapproved|Cancelled with canTransitionTo() matrix + isEditable(). TaskFrequency: OneTime|Weekly1x|Seasonal with nextRunDate().
  - DTOs: ScheduledJobUpsertData, ScheduledJobListItemData, ScheduledJobDetailData, WorkBreakdownDetailData. DetailData: full job + eager CleaningObject.client + AssignedMembership.user + read-only WorkBreakdownDetailData array.
  - `App\Policies\ScheduledJobPolicy` (rbac-full: viewAny/view→ViewSchedule, create→CreateSchedule, update→EditSchedule, assign→AssignCleaners, cancel/delete→EditSchedule; **instance checks also enforce `isVisibleTo(user)`**).
  - `App\Http\Controllers\ScheduledJobController` (thin, `#[Authorize]`). Routes: jobs.{index,create,store,show,edit,update,assign,cancel}, whereUuid. `index` + `create`/`edit` call `JobService::paginate($filter, $actor)` + `optionsVisibleTo($actor)`. `show` gates `membershipOptions` on `can('assign')`.
  - `App\Models\ScheduledJob::scopeVisibleTo(Builder, User)` + `isVisibleTo(User)` — query + record scoping. Checks: actor holds `ViewAllSchedule` permission → sees all; else only rows where `assigned_membership_id === actor->activeMembershipId()`.
  - `App\Services\WorkBreakdownService` — generateFromContract(Contract) (idempotent, creates breakdown + task per item on service agreement sign). unassignFutureForMembership(membership) (called by EmployeeService::deactivate).
  - `App\Jobs\GenerateScheduledJobsJob` (queued, ShouldBeUnique, afterCommit; binds tenant + setPermissionsTeamId on worker; rolling 30d horizon via config/scheduling.php).
  - `App\Console\Commands\GenerateScheduledJobs` — daily, finds active breakdowns, dispatches one job per.
  - `App\Enums\PermissionEnum` — ViewSchedule, CreateSchedule, EditSchedule, AssignCleaners, **ViewAllSchedule** (breadth modifier; requires ViewSchedule to be useful).
  - FE: @fullcalendar/vue3 v6.1.21. Pages Schedule/{Index,Create,Edit,Show}; components Schedule/{JobStatusBadge,JobTypeBadge,JobFiltersBar,JobList,JobCalendar [fullcalendar month/week read-only],JobForm,JobAssignPanel,WorkBreakdownView [read-only]}; composable useJobFilters (+ calendar view state). Index = calendar⇄list toggle + filters. Show = detail + assign panel + cancel + read-only breakdown. Objects/Show.vue gained read-only Rozpis section + WorkBreakdownDetailData array.
  - Tests: 7 files covering JobStatusEnum, WorkBreakdownGeneration, JobService, GenerateScheduledJobs, GenerateScheduledJobsCommand, ScheduledJobPolicy, ScheduledJobController; 17+ new tests in CleanerActorScopingTest.
- **Operational chain:** Quote → Contract (ServiceAgreement) sign → ContractService::sign() calls WorkBreakdownService::generateFromContract(Contract) synchronously (idempotent). → Daily app:generate-scheduled-jobs command dispatches GenerateScheduledJobsJob (queued, ShouldBeUnique, afterCommit; rolling 30d horizon via config/scheduling.php horizon_days). **Guard:** JobService::generateForBreakdown() returns 0 if the breakdown's CleaningObject is missing or is_active === false, preventing job materialization at deactivated locations.
- **Flow (assign):** POST `/jobs/{id}/assign` (ScheduledJobUpsertData with membershipId) → JobService::assign → update assigned_membership_id + status.
- **Flow (cancel):** POST `/jobs/{id}/cancel` → JobService::cancel → status → Cancelled.
- **Depends on:** `identity-tenancy`, `objects` (visibility depends on object reachability via jobs), `contracts` (optional backlink), `employees` (TenantMembership scope for assignment).
- **Depended on by:** nothing (leaf).
- **If you change Core, check:** ScheduledJobController (scoping + membershipOptions gating), ScheduledJobPolicy (isVisibleTo in all instance methods), JobService (paginate + optionsVisibleTo using scopeVisibleTo), ScheduledJob model (scopeVisibleTo + isVisibleTo logic, activeMembershipId call), WorkBreakdownService (generateFromContract idempotency), ContractService::sign (generateFromContract call), GenerateScheduledJobsJob (cron path has no actor, unchanged), EmployeeService::deactivate (unassignFutureForMembership call), lang keys, RoleTemplatesSeeder (ViewAllSchedule permission assignment), Objects/Show.vue (read-only Rozpis section), tests (CleanerActorScopingTest + SchedldJobPolicyTest + RoleTemplatesSeederTest scoping assertions).
- **Keywords:** rozpis prác, zákazka, úloha, frekvencia, priradenie, calendár, status, assign, unassign, visibility, scope, owner-only, membership.

## Cross-cutting

- **Multi-tenancy backbone** — every domain model has tenant_id FK + BelongsToTenant trait + TenantScope global scope. TenantContextMiddleware resolves active tenant. setPermissionsTeamId must be called before role/permission lookups outside HTTP (jobs, console, tests).
- **RBAC via Spatie permission + Policy** — per-tenant roles bundle flat permission strings. Each domain model has a Policy gating actions on permission strings. Controllers use #[Authorize] attribute per action. PermissionEnum centralizes all permission strings. Single authorization axis: RBAC permission only. Never use role() checks in FE; always use Can component + useAuthorization().allows(permission).
- **Notifications via native Laravel system** — internal notifications (InvoiceOverdue, ContractExpiring, etc.) dispatch via custom TenantDatabaseChannel, stored in DatabaseNotification with tenant_id. External notifications (InvoiceIssued, InvitationCreated) go mail-only. NotificationRecipientResolver resolves users by permission (always calls setPermissionsTeamId first). Crons trigger notifications; FE polls bell every 60s.
- **PDF generation via spatie/laravel-pdf chrome driver** — real arm64 Chromium installed via Playwright in Docker. Blade-based templates (Invoice: 3 templates; Quote: 1; Contract: 1). Pay-by-Square QR via engazan/pay-by-square + bacon/bacon-qr-code (IBAN+amount+VS+due_date, EUR-only). Tests mock the interface to avoid browser dependency.
- **MediaLibrary for file uploads (private disk, policy-gated)** — `Quote` model first to use `App\Models\Behaviors\HasMedia` + `InteractsWithMedia` for document-only archive quotes. Convention: (1) always private disk (config/documents.php MEDIA_DISK), never public; (2) mimes validated by content-sniff (mimetypes: pdf, jpeg, png, webp); (3) download strictly via policy-gated route → MediaFileData DTO (never getFullUrl()); (4) FE primitive: `FileDropInput.vue` (presentational, HTTP-free, emits file for parent to handle). Phase 2 will extend to cleaning photos, contract attachments, complaints. Tests mock Spatie\MediaLibrary facade to avoid filesystem dependency.
- **TypeScript code generation** — #[TypeScript] attribute on all DTOs + Enums. php artisan typescript:transform generates resources/js/types/generated.d.ts. FE imports types from there, never hand-edits. Props passed to Inertia must be DTO collections or Data instances (never raw arrays/Models).
- **Soft-delete + partial unique** — clients (tenant_id, ico) WHERE deleted_at IS NULL AND ico IS NOT NULL. Clients can be resurrected; IČO unique only among active clients. Queries explicitly filter soft-deleted by default via global scope.
- **Atomic transactions** — multi-step domain operations (create owner, create invoice + mark paid, generate recurring invoice) wrapped in DB::transaction in the Service (NEVER in Controller). Events dispatched inside transaction must implement ShouldDispatchAfterCommit. Jobs must bind tenant context + setPermissionsTeamId on worker.
- **Spatie Data DTO boundary** — HTTP controllers accept DTO params, validate via #[Validation] attributes on DTO properties, pass to Service. No inline $request->validate() in Controller. No FormRequest. No JsonResource. DTOs describe both input + output shapes (single DTO per action pair: store/update share one DTO).
- **i18n via locale keys** — every user-facing string via __('<ns>.<key>') from lang/{sk,en,uk}/*.php files. Validation messages auto-lookup by snake_case field name; override via DTO messages(). Logs stay English. SK/EN/UA all shipped.
- **Enum + permission string centralization** — PermissionEnum, JobStatusEnum, etc. all #[TypeScript]. No magic status strings in code. Backed enums for fixed value sets. Permissions are flat strings (verb+resource: `view clients`, `create invoices`).

## Layer contracts

- **HTTP ↔ Service** — Controllers hand a Spatie Data DTO to the service, never an array. Controllers are thin: type-hint DTO param + service in constructor, return Inertia::render or to_route()->with('flash.success', …).
- **Service ↔ Model** — DB::transaction lives in the Service, NEVER the Controller. Services are final readonly class XxxService. Multi-model bootstrap (register) also in Service.
- **BE ↔ FE (Inertia, page render)** — page prop shape = the DTO's generated TS namespace. Shared props also follow the DTO rule. Enums shared via #[TypeScript] → generated.d.ts. Flash via flash.success session key.
- **BE ↔ FE (API: /api/me)** — returns MeData(userId, activeTenantId, permissions). Session-auth XHR. FE authorization store hydrates from this shape.
- **BE ↔ FE (API: /api/notifications/bell)** — returns NotificationBellData(unreadCount, recent). Session-auth XHR. FE Pinia store polls every 60s.
- **Auth gate** — BE: business models gated by **Policy + #[Authorize] per controller method** (rbac-full). FE: <Can permission> component + useAuthorization() composable. Single axis: RBAC permission only.
- **Bootstrap (create first account)** — `php artisan app:create-owner` command creates User→Tenant→TenantInterface→TenantMembership→Roles in DB::transaction. Interactive or flag-based (flags visible in ps/history). Sets user password + creates owner role assignment. All subsequent accounts join via invitation flow.

## Gotchas

- **`notifications` table exception to multi-tenancy invariant** — DatabaseNotification is framework model: does NOT use BelongsToTenant, HasUuids, or TenantScope. Tenant isolation via three layers: (1) TenantDatabaseChannel::buildPayload() injects tenant_id on write; (2) NotificationService filters ->where('tenant_id', $tenantId); (3) NotificationPolicy::update checks tenant_id === app('current_tenant_id'). Gate::policy(DatabaseNotification::class, NotificationPolicy::class) registered explicitly.
- **App-wide morph map in AppServiceProvider** — Relation::morphMap(['cleaning_object' => CleaningObject::class, 'tenant_membership' => TenantMembership::class]) stores short strings instead of FQCNs. Impact: activity_log.subject_type stores 'cleaning_object' instead of FQCN. Contract's contractable_type follows map. **Important:** new polymorphic targets MUST be added to morphMap. Queries comparing against FQCNs will break; use getMorphClass() or map keys.
- **PDF generation requires Chromium — Docker rebuild on pull** — spatie/laravel-pdf chrome driver needs real Chromium. **arm64 constraint:** Google Chrome + Puppeteer Chrome-for-Testing are x86_64-only on Linux. Sail image installs Chromium via **Playwright** (docker/8.5/Dockerfile), symlinked to /usr/local/bin/chromium-real. On first pull, run `docker compose build --no-cache laravel.test`. `.env` requires `LARAVEL_PDF_DRIVER=chrome` + `CHROMIUM_PATH=/usr/local/bin/chromium-real`.
- **Bootstrap atomic scope** — `RegistrationService::createOwner()` + `addTenant()` both wrap role setup in `DB::transaction`. `createOwner()` calls `bootstrapTenant()` (which calls `RoleTemplatesSeeder::seedForTenant()` + assigns Admin role) inside the transaction. Post-transaction, redirect + auto-login (session-only). Do NOT move role assignment outside.
- **RoleTemplatesSeeder now static** — refactored to static seedForTenant(Tenant). Outside HTTP (jobs/console), always call setPermissionsTeamId() *before* using seeder or querying roles.
- **TenantInterface uses bigint PK, not UUID** — deliberate exception. Settings never in URLs/DTOs. 1:1 relation enforced at DB level (unique tenant_id FK). Always create via TenantInterface::create() when bootstrapping.
- **Email auto-verified on bootstrap** — no email-verification flow exists. `RegistrationService::createOwner()` calls `$user->forceFill(['email_verified_at' => now()])->save()` post-user-creation, inside the transaction.
- **TenantInvitation partial unique + soft-delete** — migration creates unique(['tenant_id', 'email'], [], where: "status = 'pending' AND deleted_at IS NULL"). Pending invites unique per tenant; soft-delete allows re-invite.
- **Soft-delete + partial unique** — clients (tenant_id, ico) WHERE deleted_at IS NULL AND ico IS NOT NULL. IČO unique among *active* clients; resurrected rows don't block re-use.
- **Permission team scope outside HTTP** — forgetting setPermissionsTeamId in seeders/jobs/tests silently returns empty permissions. Most common multi-tenant bug.
- **`activity_log` morph columns are strings** — not UUIDs, so polymorphic causer/subject works across bigint (Role) + UUID (User/Client) models.
- **Spatie permission migration is UUID-patched** — the published migration has model_morph_key + team_foreign_key as uuid(), but permissions.id/roles.id stay bigint (Spatie internal, never in URLs/DTOs).
- **Spatie Data v4 ↔ typescript-transformer v3 mismatch** — DataTypeScriptTransformer references removed class. Use Spatie\LaravelTypeScriptTransformer\LaravelData\Transformers\DataClassTransformer directly.
- **Activitylog v5 namespace shift** — …\Models\Concerns\LogsActivity (not Traits), …\Support\LogOptions; dontSubmitEmptyLogs() → dontLogEmptyChanges().
- **Auth page palette** — Pages/Auth/Login.vue + Invitations/Accept.vue use --auth-* CSS custom properties (brown/amber gradient, not cleanmaster theme). Tokenized in resources/css/app.css (:62–107).
- **`usePageProps()` cast** — Inertia v3 augmentation: cast once inside composable (usePage().props as unknown as SharedProps).
- **Ziggy not installed; route() unavailable** — Project does not include `tightenco/ziggy`. Global `route()` function does not exist in FE code. Use plain string URLs (e.g., `/invitations/${token}` not `route('invitations.accept', {token})`). This is project-wide convention; do not raise it as a bug.
- **IČO lookup mock is hardcoded** — IcoLookupService::lookup() checks static SKMap. Replace with ARES API when ready (flag // TODO swap for ARES API).
- **CleaningObject PHP class name avoids `Object` global** — model is CleaningObject, table is objects, routes use objects. Explicit Gate::policy(CleaningObject::class, ObjectPolicy::class) in AppServiceProvider required (auto-discovery looks for CleaningObjectPolicy).
- **Rule::exists tenant-scope on ObjectStoreData** — client_id field includes Rule::exists('clients', 'id')->where('tenant_id', $tenantId). Closes cross-tenant leak.
- **Object deactivation: Client deletion still soft-deletes** — When a Client is deleted, cascading deletes soft-delete all its objects. Objects are deactivated via is_active=false for direct user action, but client cascade still uses SoftDeletes. This is deliberately kept; 4 tests in ClientServiceObjectCascadeTest assert the behavior. Schema has both mechanisms.
- **Object picker filtering is inconsistent** — Quotes/Contracts/Schedule edit pickers filter on is_active=true; Invoices/RecurringInvoices do not. A record pointing at a now-inactive object loses its selection in edit mode (pickers won't show the object). Pre-existing but now more common post-deactivation feature. New pickers should default to is_active filtering; override only with rationale.
- **restrictOnDelete guards on object FKs never fire** — All 5 FKs referencing objects use restrictOnDelete(), preventing hard deletes. However, no forceDelete() exists anywhere in app/. Hard-delete scenario never occurs; guards are insurance against future mistakes.
- **MediaLibrary + filesystem atomicity gap** — `QuoteService::uploadDocument()` calls `$quote->addMedia($file)->toMediaCollection('document')` **without an explicit DB::transaction**. Reviewed and accepted as low-severity: MediaLibrary's toMediaCollection() is the atomic unit (Media table write + disk write are coordinated by Spatie). A DB::transaction cannot wrap the filesystem write anyway — if disk write fails post-commit, the Media row exists orphaned, but rollback is impossible. Intentional gap to document: Phase 2 services (photos, complaints) should NOT copy this as a template without understanding the architectural trade-off (consistency vs. simplicity).
- **RBAC widening W2 — Employee quota removed** — Starter plan previously limited MultiUser quota to 5 team members; now all accounts can add unlimited employees. `EmployeeService::create()` no longer checks quota. Accepted for internal tool use-case. If re-adoption of per-account quotas occurs, add `ChecksFeatures::canCreateEmployee(User, Tenant)` guard back to EmployeeController@store.
- **`php artisan app:create-owner --password=` exposes password in shell history + ps aux** — The `--password` flag stores the plaintext password into the shell's history file (~/.bash_history) and makes it visible via `ps aux` while the command is running. Accepted known limitation; the command **defaults to interactive prompts** when the flag is omitted, which is the safe path. Recommendation: never script `app:create-owner` with the `--password` flag into CI/CD or startup scripts; use the interactive mode or inject password via stdin piping in controlled environments. Document this in deployment procedures.

## Verification status

- **Project type:** laravel-be + inertia-fe (line 6, confirmed)
- **Last full scan:** 2026-09-05 (initial full rebuild, all routes + models + services + all modules verified)
- **Last delta:** 2026-09-05 (SaaS→internal-tool repositioning: removed subscription/feature-gating, deleted Landing/Register/Signup/FeatureEnum/SubscriptionPlanEnum/ChecksFeatures, unwrapped all feature:* route groups, updated identity-tenancy [removed subscription_plan], reconciled all 9 affected domain blocks, added Ziggy gotcha + W1/W2 authorization widening + create-owner shell history gotchas)
- **Open TODO verify:** 0
- **Reference inventory:** `.claude/inventory.md` (not generated; opt-in via `/spec-sync --full --with-inventory`)
