# Port brief: cleaning-software -> cleanmaster-admin

Source: branch `main` of THIS repo @ commit `05f0237` (889 tests green, PHPStan 0). Use `git show main:<path>` / `git checkout main -- <path>` to pull files.
Target: this repo, forked from `vue-skeleton` (Users, Roles/PermissionManager, AuditLogs, Media,
Profile, localisation, Precognition wired, Scribe). Skeleton has NO multi-tenancy.

User decisions (do not re-deliberate):
- Fresh skeleton is the new base; port modules INTO it. Never sync back.
- Phase 1 scope: tenancy, clients, objects, invoices + settings, recurring invoices, quotes,
  contracts + templates, employees + schedule (incl. cleaner actor scoping). Notifications later.
- Login must look identical to source `resources/js/Pages/Auth/Login.vue` (brown/amber `--auth-*`
  palette); app visual = source DaisyUI `cleanmaster` theme (OKLCH tokens in `resources/css/app.css`).
- Roles: Admin (all companies), Interná upratovačka in active use; Vedúca/Sekretárka/Účtovníčka/
  Zákazník seeded from spec but unused. Target 5-role model later adds Prémiový zákazník,
  Externá upratovačka. Scoping is permission-based (`view all objects` / `view all schedule`).
- Auth profile rbac-full. Subscription/feature gating does NOT exist and must not be ported.
- Invoicing may become a separate service later: keep `InvoiceService` boundaries clean.

Phase order (each = one /feature with approved file plan; run /init here first):
1. Visual: theme tokens, auth palette, Login/ForgotPassword/ResetPassword look, AppLayout branding.
   Keep skeleton components (AppLayout, DataTable, ConfirmDeleteModal, Pagination, PermissionManager).
2. Tenancy foundation: Tenant, TenantMembership, TenantInterface (bigint PK, deliberate), Spatie
   teams mode (`team_foreign_key = tenant_id`, UUID-patched migration), TenantContextMiddleware,
   TenantScope + BelongsToTenant, tenant switcher + add-tenant, TenantInvitation + accept flow,
   `app:create-owner`, RoleTemplatesSeeder per tenant. Adapt skeleton Users/Roles/AuditLogs/Media
   to per-tenant scope (activity_log morph columns as strings). Morph map in AppServiceProvider.
3. Clients (multi-contact, soft-delete, partial unique ico) + Objects (deactivation, not delete).
4. Invoices (snapshot columns, per-item VAT, numbering with lockForUpdate, credit notes,
   SK fields, 3 Blade PDF templates via spatie/laravel-pdf chrome driver -> Dockerfile needs
   arm64 Chromium via Playwright, Pay-by-Square QR, InvoiceIssued mail) + Settings/Invoicing
   + Recurring invoices (job, command, lifecycle).
5. Quotes (itemized|document kinds, clientless snapshot, attach-client, MediaLibrary private disk,
   expiry command, convert to invoice/contract).
6. Contracts + ContractTemplates (placeholders, employment contracts, expiry command, PDF).
7. Employees (TenantMembership management, escalation guards) + Schedule (WorkBreakdown,
   ScheduledJob on table cleaning_jobs, generate command/job, assign) + cleaner actor scoping.

Port rules:
- BE: copy models/DTOs/services/policies/migrations/tests from source, adapt to skeleton
  conventions (`App\Utils\AllowedFilter`, skeleton HasUuids, Actions dir if used). Do not port
  the source's bespoke generic components where the skeleton ships one (DataTable, Pagination).
- FE: rebuild pages on skeleton DataTable/Forms; port domain components (InvoiceForm,
  ItemsEditor, QuoteForm, JobCalendar...) as-is where they have no skeleton equivalent.
- i18n: source `lang/{sk,en,uk}/app.php` keys carry over; skeleton ships SK/EN, add UK.
- Ziggy: check whether the skeleton ships `route()` in FE; source used plain string URLs.
- Known source defects NOT to port: Pagination.vue prev/next reading `links.prev` (skeleton
  has its own), `employee_role.*` lang keys never matching role names.
