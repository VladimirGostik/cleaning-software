# CleanMaster — Business context

Source of truth: `docs/cleanmaster-technicka-specifikacia-v1.md.docx` (Slovak, 2312 lines, v1.0 dated 2026-05-02).

## What

Multi-tenant SaaS for small/medium cleaning companies. Targets SK + CZ markets. No specialized cleaning-segment software exists locally; competitors are EN-only (Jobber, ZenMaid) or generic SK invoicing (SuperFaktúra, FLOWii).

## Who pays

Cleaning company **owner** (Vlastník). One owner can run multiple cleaning companies (tenants) from a single login.

## Three pillars

1. **Documents** — quotes (cenové ponuky), client contracts, document templates.
2. **Employees** — registration, schedule, photo documentation, employment contracts.
3. **Invoicing** — monthly, one-off, special-period invoices with VAT compliance.

## Use-cases (Phase 1, implemented)

- **Register new company** — non-authenticated user submits email + password + company details (IČO, name, address, VAT payer) + list of co-founder invites (email+role). Registration service spins up new Tenant (Free plan), bootstraps permissions, creates token-based invitations (email dispatch not yet built — token URL must be shared manually). Registrant auto-verified, auto-logged-in, sees welcome overlay on Dashboard.
- **Add another company** — authenticated Vlastník clicks "Pridať novú firmu", enters name + IČO + optional leader email, creates new Tenant (Free plan). Can copy color settings from previous company. Switches active tenant (session-bound `active_tenant_id`).
- **Look up company by IČO** — register form auto-fills company name + VAT status from registry (currently mock; TODO ARES). User can override before submitting.
- **Accept invitation** — invitee opens `/invitations/{token}`. Existing account → confirms password (or auto-accepted if already logged in with the invited email); new person → sets name + password (account created on Free plan, auto-verified). Membership created (or reactivated), invited role assigned within that tenant, invitation marked Accepted, user logged in with the inviting tenant active. Expired/used invitations show an expired state; a logged-in user with a different email is told the invite belongs to someone else.
- **Manage cleaning objects** — Sekretárka (full CRUD) and Vedúca (view) manage physical locations (office/apartment/house/common areas) assigned to each client. Each object holds access info (codes, key box, key count), special instructions, area/floor metadata, and active flag. Objects are the **central entity** in the quotes → contracts → invoicing chain. Feature-gated (Starter/Pro/Enterprise tiers).
- **Create and manage invoices** — Účtovníčka (Accountant) creates invoices (monthly, one-off, special-period types). Three subject modes: linked to a client (bytový podnik), linked to a client + object (work at a location), or standalone with manual customer fields (ad-hoc/nacenenie). At creation, a snapshot of customer/object/supplier data (IČO, DIČ, VAT, address, IBAN) is captured and frozen at issue — Client edits post-issue don't affect invoice display/PDF. Per-tenant auto-numbering with manual override, format configurable (`FA-{YYYY}-{XXXX}`, presets + custom). Issue sets `issued_at`, finalizes snapshot (no edits after). If non-VAT-payer, VAT rows hidden; PDF prints non-payer clause. PDF templates selectable (Classic/Modern/Minimal Blade views); tenant default stored in settings, overridable per invoice. Pay-by-Square QR generated when issued + IBAN present. Email send queued job (up to 3 retries). Lifecycle: Draft (edit/delete allowed) → Issued (immutable snapshot, PDF downloadable) → Paid / Overdue (daily cron checks due date) → Paid. Cancel (Issued|Overdue) creates credit note (dobropis) with negated items, own number, linked back to original. Features: variable_symbol derived from number (digits only), delivery_date distinct from issue_date (Slovak law §74), supplier_registration_info (Obchodný zákonník §3a zápis v registri) printed in PDF footer. Feature-gated (Pro/Enterprise tiers only). Permission-gated: `view invoices` (Účtovníčka view-only), `create|edit invoices` (Sekretárka + Účtovníčka for full crud), `cancel invoices` (storno/dobropis authority), `ManageBillingSettings` (owner only).

## Roles (defaults — owner can customize per tenant)

| Role | Default permissions |
|---|---|
| Vlastník (Owner) | All. Only role that manages subscription + permissions. |
| Vedúca upratovačka (Supervisor) | Schedule (full), Employees (view, assign), Complaints (full), Photos (full), Locations (view). No finance. |
| Upratovačka (Cleaner) | Own schedule, check-in/out, upload photos, report absence. |
| Sekretárka (Secretary) | Clients/Locations (full), Quotes (full), Contracts (view+create), Templates (full), Notifications (view). No finance, no employees. |
| Účtovníčka (Accountant) | Invoices (full), Contracts (view), Clients (view), VAT settings (view). |
| Zákazník (Customer) | Customer portal: own schedule, breakdown, invoices, complaints, photos. |

Permissions, not roles, drive UI: a cleaner with no permission for "Reklamácie" simply doesn't see the section. Roles are bundles only.

## Permissions

Flat Spatie permission strings, **scoped per tenant** (teams = `tenant_id`). Format: `<verb> <resource>`. Roles bundle them via `RoleTemplatesSeeder` (static `seedForTenant()` method, called at company creation + registration). The owner can re-bundle per tenant. Verbs in use: `view`, `create`, `edit`, `delete`.

**Implemented:**

- `view clients` / `create clients` / `edit clients` / `delete clients` — Vlastník (all), Sekretárka (all), Účtovníčka (view only). Enforced by `ClientPolicy` + `#[Authorize]` on `ClientController`.
- `view objects` / `create objects` / `edit objects` / `delete objects` — Vlastník (all), Sekretárka (full CRUD), Vedúca upratovačka (view only), Upratovačka (view only). Enforced by `ObjectPolicy` + `#[Authorize]` on `ObjectController`. Feature-gated `objects` plan.
- `view invoices` / `create invoices` / `edit invoices` / `cancel invoices` — Vlastník (all), Sekretárka (create/edit), Účtovníčka (create/edit/cancel/view). `ManageBillingSettings` — Vlastník only (invoice settings: template, number format, IBAN, VAT, registration_info). Enforced by `InvoicePolicy` + `#[Authorize]` on `InvoiceController`/`InvoiceSettingsController`. Feature-gated `invoices` plan (Pro/Enterprise).

**Seeded (no-op role bundles, UI not yet built):**

- All 6 spec roles have full permission bundles for future domains (objects, quotes, contracts, schedule, invoices, employees, complaints, photos, templates, notifications, VAT settings, subscription management). Each role's permission set defined in `RoleTemplatesSeeder::ROLE_PERMISSIONS` map.

**Spec-planned (seeded as role bundles, UI not yet built):** objects, quotes, contracts, schedule/jobs, invoices, employees, complaints, photos, templates, notifications, VAT settings, subscription, permission management. See the per-role defaults table above and the roadmap in `technical.md › Known gaps`.

Only Vlastník manages subscription + the permission bundles themselves.

## Authorization model (two axes)

Access = `user/permission axis` AND `plan/feature axis`.

- **User/permission axis** — per-tenant Spatie RBAC. Roles bundle permissions; owner customizes per tenant. Example: a Sekretárka with no "edit clients" permission simply cannot. Enforced: BE Policy + `#[Authorize]` + FE `Can` component.
- **Plan/feature axis** — account (User) subscription tier unlocks modules. A tenant's features resolve through its **owner's plan**. Free plan = no Invoices access, no matter a user's permission string. Enforced: BE `RequiresTenantFeature` middleware + FE `useAuthorization().hasFeature()` + `Can` component.
- **Both must pass.** A Vlastník (owner) on a Free plan cannot access Invoices (fails plan axis). A Sekretárka on a Pro plan cannot access Invoices (fails permission axis).
- **Render by capability, not by role.** UI conditionals never check `.roles()` or `.hasRole()`. Always use `Can` component or `useAuthorization().allows(permission, feature)` composite check. Roles are custom per tenant; capabilities are the contract.

## Subscription plans (entitlement layer)

Separate from RBAC permissions (which gate **users**). Plans gate **accounts (Users)** — what modules/features/quotas each account's tier unlocks, shared across all tenants the account owns. Not in scope: per-account plan edits (hardcoded 4-tier matrix in `config/subscription.php`), UI, enforcement of entity limits at write-time.

**Tiers** (from `SubscriptionPlanEnum`):
- `Free` — trial/demo. No modules unlocked. Can own max 1 tenant. `MultiUser` quota = 1 (owner only). 
- `Starter` — basic. Clients + Objects unlocked. Can own max 2 tenants. `MultiUser` quota = 5.
- `Pro` — full. Clients + Objects + Quotes + Contracts + Schedule + Invoices + Employees + Reports unlocked. Can own max 3 tenants. `MultiUser` quota = 20.
- `Enterprise` — all + unlimited. Every feature unlocked. Can own unlimited tenants. `MultiUser` = unlimited (null).

**Tenant-creation quota:** When a Vlastník clicks "Pridať novú firmu", the system checks if their account plan allows more tenants (`ChecksFeatures::canCreateTenant(User)` returns false if limit reached). If limit reached, FE disables "Pridať novú firmu" affordance + shows `app.tenant.limit_reached` error on POST. **New rule:** each account is limited by its plan tier's `max_tenants` config value (Free=1, Starter=2, Pro=3, Enterprise=null).

**Architecture:** `FeatureEnum` lists gatable features (Clients, Objects, …, MultiUser). `ChecksFeatures` interface (DIP seam) + `ConfigFeatureChecker` impl reads plan from account's `subscription_plan` field + `config/subscription.php` matrix. Middleware `RequiresTenantFeature` gate individual routes. Tenant model's `hasFeature(FeatureEnum): bool` accessor resolves tenant's features through `$tenant->owner->subscription_plan`. If plans later become editable per-account or we adopt `laravel/pennant`, a new adapter behind the same interface swaps in without caller changes.

## Phases

- **Fáza 1 (in scope)** — Admin Portal (AP). Web-only, desktop+tablet responsive.
- **Fáza 2 (later)** — MAPP cleaner mobile app (Capacitor), VAPP supervisor mobile app, ZP customer portal.

## Critical domain entities (Phase 1)

- `User` — global identity. UUID. `locale` (sk/en/uk), `is_active`, `subscription_plan` (Free/Starter/Pro/Enterprise). Owns 1+ tenants.
- `Tenant` — firma/cleaning company. UUIDv7. VAT-payer flag globally toggles VAT fields across all documents. Has `owner_id` FK to User (the paying account). Features resolved via owner's plan. ON DELETE CASCADE when owner is deleted.
- `TenantMembership` — pivot User × Tenant. Permission scope. **Deactivating** a membership only revokes access for that tenant; user still exists in others.
- `Client` — typ: `Corporate` (firemný — IČO/DIČ/IČ DPH required) or `Private` (private person, IČO optional). Has multiple contacts with primary flag. Soft-delete. Partial unique index on (tenant_id, ico) per tenant for active clients.
- `Object` — physical cleaning location (kancelária/byt/dom/spoločné priestory). The **central entity**: client → object → (quote → contract → invoices). Holds access info (codes, keys), special instructions.
- `Quote` (cenová ponuka) — items with name/description/frequency/unit/quantity/price. Status: Draft → Odoslaná → Schválená → Zamietnutá → Expirovaná. Auto-generates work breakdown.
- `Contract` — **polymorphic** (`contractable_type` + `contractable_id`) so it can target Object (client contract) or TenantMembership (employee contract) or future entities. Has `ContractCategory` enum (configurable per tenant). Type: doba určitá / doba neurčitá. Has change log (audit). Notifies 30/14/7 days before expiry.
- `EmploymentContract` — child of Contract. Type: DPP / DPČ / TPP / Živnosť.
- `WorkBreakdown` (rozpis prác) — generated from quote items. Drives schedule generation.
- `Job` (zákazka) — scheduled cleaning. Type: Pravidelná / Jednorazová / Špeciálna. Status: Plánovaná / Nepriradená / Prebiehajúca / Dokončená / Neschválená / Zrušená.
- `Absence` (neprítomnosť) — cleaner-reported time off. Triggers notification flow listing affected jobs.
- `Invoice` — Mesačná / Jednorazová / Špeciálna. Status: Draft → Vystavená → Uhradená / Po splatnosti / Stornovaná. Pay-by-Square QR per Slovak standard.
- `DocumentTemplate` — DOCX/PDF templates (zmluva s klientom, ponuka, DPP/DPČ/TPP, BOZP).
- `Notification` settings + log — DB / E-mail / Push channels. Configurable per role × type.

## Out of scope (Phase 1 + 2)

- IS EFA integration (e-fakturácia)
- Slovak accounting system integrations (Pohoda, Omega, Money S3)
- Automatic invoice generation (always button-driven)
- GPS verification of check-in/check-out (architecture must reserve fields, no implementation)
- Automatic substitute-cleaner assignment
- Route optimization

## Roadmap (architecture-aware)

GPS check-in/out, IS EFA integration, accounting system exports, payment matching, auto-fill templates (premium), customer-initiated one-off jobs, inventory module, reporting module, CZ-market expansion, route optimization. All require schema preparation now (e.g. invoices need `efa_status`/`efa_id` reserved, jobs need `gps_lat`/`gps_lng` columns, tenant needs `country_code`).

## Compliance / non-functional

- **GDPR** — EU storage, right to erasure, consent.
- **Slovak VAT** — IČ DPH on tenant flips global VAT visibility. Default rate 23% (configurable for legislation changes).
- **Multi-tenant isolation** — strict row-level. Tested via global scope on every domain query.
- **Languages** — SK / EN / UA across all platforms (UA reflects Ukrainian cleaning workforce).
- **Mobile (Phase 2)** — offline-first; sync on reconnect.
- **Dashboard** — async widgets; one widget failure ≠ whole dashboard failure.
