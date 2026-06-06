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

Flat Spatie permission strings, **scoped per tenant** (teams = `tenant_id`). Format: `<verb> <resource>`. Roles bundle them via `RoleTemplatesSeeder`; the owner can re-bundle per tenant. Verbs in use: `view`, `create`, `edit`, `delete`.

**Implemented:**

- `view clients` / `create clients` / `edit clients` / `delete clients` — Vlastník (all), Sekretárka (all), Účtovníčka (view only). Enforced by `ClientPolicy` + `#[Authorize]` on `ClientController`.

**Spec-planned (seeded as role bundles, UI not yet built):** objects, quotes, contracts, schedule/jobs, invoices, employees, complaints, photos, templates, notifications, VAT settings, subscription, permission management. See the per-role defaults table above and the roadmap in `technical.md › Known gaps`.

Only Vlastník manages subscription + the permission bundles themselves.

## Authorization model (two axes)

Access = `user/permission axis` AND `plan/feature axis`.

- **User/permission axis** — per-tenant Spatie RBAC. Roles bundle permissions; owner customizes per tenant. Example: a Sekretárka with no "edit clients" permission simply cannot. Enforced: BE Policy + `#[Authorize]` + FE `Can` component.
- **Plan/feature axis** — tenant's subscription tier unlocks modules. Free plan = no Invoices access, no matter a user's permission string. Enforced: BE `RequiresTenantFeature` middleware + FE `useAuthorization().hasFeature()` + `Can` component.
- **Both must pass.** A Vlastník in a Free plan cannot access Invoices (fails plan axis). A Sekretárka in a Pro plan cannot access Invoices (fails permission axis).
- **Render by capability, not by role.** UI conditionals never check `.roles()` or `.hasRole()`. Always use `Can` component or `useAuthorization().allows(permission, feature)` composite check. Roles are custom per tenant; capabilities are the contract.

## Subscription plans (entitlement layer)

Separate from RBAC permissions (which gate **users**). Plans gate **tenants** — what modules/features/quotas the company's tier unlocks. Not in scope: per-tenant plan edits (hardcoded 4-tier matrix in `config/subscription.php`), UI, enforcement of entity limits at write-time.

**Tiers** (from `SubscriptionPlanEnum`):
- `Free` — trial/demo. No modules unlocked. `MultiUser` quota = 1 (owner only). 
- `Starter` — basic. Clients + Objects. `MultiUser` quota = 5.
- `Pro` — full (demo tenant default). Clients + Objects + Quotes + Contracts + Schedule + Invoices + Employees + Reports. `MultiUser` quota = 20.
- `Enterprise` — all + unlimited. Every feature + `MultiUser` = unlimited (null).

**Architecture:** `FeatureEnum` lists gatable features (Clients, Objects, …, MultiUser). `ChecksFeatures` interface (DIP seam) + `ConfigFeatureChecker` impl reads plan from `config/subscription.php`. Middleware `RequiresTenantFeature` gate individual routes. Tenant model has thin `hasFeature(FeatureEnum): bool` accessor. If plans later become editable per-tenant or we adopt `laravel/pennant`, a new adapter behind the same interface swaps in without caller changes.

## Phases

- **Fáza 1 (in scope)** — Admin Portal (AP). Web-only, desktop+tablet responsive.
- **Fáza 2 (later)** — MAPP cleaner mobile app (Capacitor), VAPP supervisor mobile app, ZP customer portal.

## Critical domain entities (Phase 1)

- `Tenant` — firma/cleaning company. UUIDv7. VAT-payer flag globally toggles VAT fields across all documents. Subscription plan + entity limits.
- `User` — global identity. UUID. `locale` (sk/en/uk), `is_active`.
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
