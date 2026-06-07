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

- **Core:** `App\Models\User` (global identity, UUID, `locale`, `is_active`, `subscription_plan` SubscriptionPlanEnum, `ownedTenants() HasMany`) + `App\Models\Tenant` (firma, UUID, VAT-payer flag, `owner_id` FK to User ON DELETE CASCADE, `interface() HasOne`) + `App\Models\TenantMembership` (User × Tenant pivot, `is_active`, `joined_at`).
- **Satellites:**
  - `App\Models\TenantInterface` — 1:1 to Tenant (bigint PK, deliberately NOT UUID; settings never in URLs/DTOs). Stores `color` (TenantColorEnum cast). Cascades on delete. Tenant→interface() HasOne.
  - `App\Http\Middleware\TenantContextMiddleware` — resolves active tenant from session `active_tenant_id` (or first active membership), binds `app('current_tenant_id')`, calls `PermissionRegistrar::setPermissionsTeamId($tenantId)`. Without it the global scope and permission lookups have no tenant.
  - `App\Models\Role` extends `Spatie\Permission\Models\Role` + `LogsActivity` + `search` scope. Per-tenant via `tenant_id` team key.
  - `App\Http\Middleware\HandleInertiaRequests` — shares `tenant {active, available}` + `can {…}` + `tenantColors` (TenantColorEnum::options()) + `flash.justRegistered` (boolean) to every page.
  - `App\Http\Controllers\Auth\*` — login / logout / forgot-password / reset-password.
  - `App\Http\Controllers\Api\MeController` — returns `MeData` (userId, activeTenantId, permissions, features). Single `__invoke` endpoint, no `#[Authorize]` by design (self-endpoint, `auth` middleware is the gate).
  - `App\Data\Auth\MeData` DTO (`#[TypeScript]`): userId, activeTenantId (?string), permissions (list), features (list). Generated to `resources/js/types/generated.d.ts`.
  - `App\Http\Middleware\RequiresTenantFeature` and `routes/api.php` registered in `bootstrap/app.php` (api middleware group appends EncryptCookies → AddQueuedCookies → StartSession → LocaleMiddleware → TenantContextMiddleware → throttle:api).
- **Flow (login):** `POST /login` → `Auth\AuthController@login` (`LoginData` DTO) → session auth → `TenantContextMiddleware` binds tenant on subsequent requests → `/dashboard`.
- **Flow (capabilities):** FE navigates → `Inertia.router.on('before')` guard syncs store via `useAuthorization().ensureLoaded()` → `GET /api/me` (session-auth XHR) → returns MeData (permissions = active-tenant Spatie names, features = plan list, accountPlan = owner's SubscriptionPlanEnum value, remainingTenantSlots = int|null) → store updates → `allows(permission, feature)` AND-gate re-evaluates on each render.
- **Depends on:** nothing (root).
- **Depended on by:** **every** domain model via `tenant_id` FK + the per-tenant permission scope.
- **If you change Core, check:** `TenantContextMiddleware`, `HandleInertiaRequests::share`, `App\Scopes\TenantScope`, every seeder that calls `setPermissionsTeamId`, `MeController` (permissions + accountPlan + remainingTenantSlots hydration), `TenantFactory` (interface creation, owner assignment), Tenant model (owner relation), User model (subscription_plan cast, ownedTenants relation), RegistrationService::register (sets owner_id on new tenant).
- **Keywords:** tenant, firma, membership, Vlastník, active_tenant_id, teams, /api/me, capabilities, tenantColors, justRegistered.

### subscription-plans

Per-account feature gating (entitlement, distinct from Spatie RBAC). Account's plan → tenant features + tenant-creation quota.

- **Core:** `config/subscription.php` — static matrix (4 tiers: Free/Starter/Pro/Enterprise); keyed by `SubscriptionPlanEnum` value; each plan has `max_tenants` (int|null), `features` array (FeatureEnum values), `quotas` map (feature → int|null). `App\Enums\SubscriptionPlanEnum` (Free|Starter|Pro|Enterprise) + `App\Enums\FeatureEnum` (Clients|Objects|Quotes|Contracts|Schedule|Invoices|Employees|Reports|MobileAccess|MultiUser). Both `#[TypeScript]`. `App\Contracts\ChecksFeatures` interface (DIP seam) + `App\Services\ConfigFeatureChecker` final impl.
- **Satellites:**
  - `App\Http\Middleware\RequiresTenantFeature` — `'feature'` alias in bootstrap/app.php. Usage: `Route::...->middleware('feature:clients')`. Resolves tenant from `app('current_tenant_id')`, loads owner, checks `$tenant->owner->subscription_plan` against `config/subscription.php`, aborts 403 (`app.feature.locked`) if plan lacks feature.
  - `App\Models\Tenant` — `hasFeature(FeatureEnum): bool` accessor (delegates to bound `ChecksFeatures::hasFeature($this, $feature)` which reads `$this->owner->subscription_plan`).
  - `App\Models\User` — `subscription_plan` cast to `SubscriptionPlanEnum` + `ownedTenants() HasMany` relation (default eager-loaded in `ConfigFeatureChecker::canCreateTenant`).
  - `ChecksFeatures::maxTenants(User): ?int` — returns the account's `max_tenants` from plan config. null = unlimited.
  - `ChecksFeatures::canCreateTenant(User): bool` — returns true if `User::ownedTenants()->count() < maxTenants(user)`.
  - `ChecksFeatures::featuresFor(Tenant): list<string>` — returns all feature enum values (as strings) for the tenant owner's plan. Used by FE capabilities store + `MeController`.
  - Translations `lang/{sk,en,uk}/app.php` — `subscription_plan.*` labels + `feature.locked` 403 message + `tenant.limit_reached` error.
- **Flow (feature check):** route-level `->middleware('feature:quotes')` → `RequiresTenantFeature` loads tenant + owner, checks owner's plan's feature list against `FeatureEnum::tryFrom($param)` → allow 200 or abort 403.
- **Flow (add-tenant quota):** `TenantController@store` calls `ChecksFeatures::canCreateTenant($user)` before creating new tenant. If false, aborts 403 with `app.tenant.limit_reached`.
- **Depends on:** `identity-tenancy` (reads `current_tenant_id` + User subscription_plan, Tenant owner relation).
- **Depended on by:** `identity-tenancy` (MeController calls `maxTenants` + `canCreateTenant` + `featuresFor`); every route gated `->middleware('feature:...')`; TenantController; FE capabilities layer.
- **If you change Core, check:** `config/subscription.php` (max_tenants matrix), `UserFactory` (default Free + states for other tiers), `UserSeeder` (demo account plans), `TenantFactory` (owner_id assignment), `TenantController@store` (canCreateTenant check), language files, any route gated `->middleware('feature:...')`, `MeController` (maxTenants + remainingTenantSlots hydration), `ConfigFeatureChecker::planConfig` (loads owner).
- **Keywords:** plan, feature, quota, entitlement, Free/Starter/Pro/Enterprise, gating, SubscriptionPlanEnum, FeatureEnum, ChecksFeatures, ConfigFeatureChecker, featuresFor.

### register-flow-fe

Web registration onboarding (3-step wizard → atomic backend transaction → auto-login + welcome overlay).

- **Core:** `Pages/Auth/Register.vue` 3-step machine (account → company → invites) + `RegistrationService::register(RegisterData)` on BE (atomic via `DB::transaction`). New User created with subscription_plan (default Free per spec), new Tenant created with owner_id = the new User, email auto-verified, user auto-logged-in, `active_tenant_id` set in session, Vlastník role assigned.
- **Satellites:**
  - `RegisterData` DTO (`#[TypeScript]`) — name, email, password, `company: CompanyData`, `invites: array<InviteData>`. CompanyData: name, ico, dic, vat_number, is_vat_payer, address_line, city, postal_code, country. InviteData: email, role_name.
  - `Pages/Auth/{RegisterHero, RegisterStepAccount, RegisterStepCompany, RegisterStepInvites, RegisterWelcome}.vue` — step components + hero banner (auth page palette: --auth-* not cleanmaster theme).
  - `Components/Forms/PasswordStrengthBar.vue` — visual strength indicator on account step.
  - `App\Http\Controllers\Auth\RegisterController` — `showRegister()` GET (renders page), `register()` POST with `RegisterData` DTO + throttle:register.
  - `RoleTemplatesSeeder` — refactored static `seedForTenant(Tenant $tenant)` method (called by `RegistrationService::bootstrapTenant()`, shared with add-tenant flow). Creates 6 spec roles + permissions per tenant.
  - `IcoLookupService` (BE) — synchronous lookup, currently hardcoded SKMap, `TODO swap for ARES API`.
  - `useIcoLookup()` composable (FE) — debounced 400ms lookup, calls `IcoLookupService.ts`, yields company name + VAT status to form.
  - `ColorSwatchPicker` component (company step) — choose 8 TenantColorEnum presets (hex palette), stored in TenantInterface.
  - Dashboard flash `justRegistered` (boolean) → `Pages/Dashboard.vue` renders welcome overlay (Option C: overlay on existing dashboard, not separate route/page).
  - `AppLayout` tenant dropdown now lists `tenant.available` + "Pridať novú firmu" link (→ AddTenantModal).
  - Rate limiter `register` added to `AppServiceProvider`.
- **Flow:** GET `/register` → RegisterController→showRegister() → Register.vue mounted, user enters 3 steps → POST `/register` (RegistrationService::register() transaction: User create with subscription_plan=Free, Tenant create with owner_id=the new User, TenantInterface create, TenantMembership create, seed roles, assign Vlastník, create invitations) → auto-login + redirect `/dashboard` with flash justRegistered=true → Dashboard overlay renders welcome → "Continue" dismisses overlay.
- **Depends on:** `identity-tenancy` (User subscription_plan field, Tenant owner_id FK, TenantMembership, RoleTemplatesSeeder), `subscription-plans` (new user defaults to Free plan).
- **Depended on by:** nothing (entry point).
- **If you change Core, check:** `RegisterController` route (throttle:register limiter), `RegistrationService::register` + `::bootstrapTenant` transaction boundary + owner_id assignment, `RoleTemplatesSeeder::seedForTenant` static signature, `TenantInterface` creation, Tenant model `owner() BelongsTo` relation, User model `subscription_plan` cast, `HandleInertiaRequests` (tenantColors shared prop, justRegistered flash), AppLayout tenant dropdown, Dashboard welcome logic.
- **Keywords:** register, wizard, onboarding, co-founder, invite, atomic, email_verified_at forceFill, TenantInterface, tenantColors, RoleTemplatesSeeder::seedForTenant static.

### ico-lookup

Guest-accessible synchronous company registry lookup (mock → TODO ARES API).

- **Core:** `GET /api/icos/{ico}` endpoint (guest-accessible, throttled, whereNumber validation) returns `IcoLookupData` DTO (ico, name, vat_status).
- **Satellites:**
  - `App\Http\Controllers\Api\IcoLookupController` — single action, throttle:ico-lookup.
  - `App\Services\IcoLookupService::lookup(string $ico): IcoLookupData` — current impl: hardcoded SKMap (demo IČOs map to company names + VAT status). Flags `TODO swap for ARES API` (Slovakia's official company registry) when user-facing.
  - `IcoLookupData` DTO (`#[TypeScript]`) — ico (string), name (?string), vat_status (?bool). Generated to resources/js/types/generated.d.ts.
  - `resources/js/services/IcoLookupService.ts` — async `lookup(ico): Promise<IcoLookupData>` over XHR.
  - `resources/js/composables/useIcoLookup.ts` — debounced 400ms wrapper, call `lookup()` as user types, yields name + VAT status. Cancellable on unmount.
  - Rate limiter `ico-lookup` added to `AppServiceProvider`.
  - FE integration: Register.vue CompanyStep uses `useIcoLookup` to populate name + vat_status on IČO input blur.
- **Flow (register company step):** User enters IČO → debounced 400ms → `useIcoLookup()` calls `IcoLookupService.lookup()` → `GET /api/icos/{ico}` (throttled, guest OK) → returns IcoLookupData → form name + vat_status fields auto-populate → user can override before POST /register.
- **Depends on:** nothing (stateless lookup service).
- **Depended on by:** `register-flow-fe` (company step auto-fill), `AddTenantModal` (optional).
- **If you change Core, check:** `IcoLookupController` route + throttle:ico-lookup limiter, `IcoLookupService::lookup` (when swapping mock for ARES), FE `useIcoLookup` debounce params (400ms), RegisterStepCompany form binding.
- **Keywords:** IČO, company lookup, registry, ARES TODO, mock, guest-accessible, throttled, debounced.

### clients

First implemented business domain layer (CRUD, filtering, soft-delete, multi-contact, type enum).

- **Core:** `App\Models\Client` (UUID, tenant_id FK, BelongsToTenant, SoftDeletes) + `App\Services\ClientService` — `paginate(ClientIndexFilterData): LengthAwarePaginator`, `create(ClientStoreData): Client`, `update(Client, ClientUpdateData): Client`, `delete(Client): void`.
- **Satellites:**
  - `App\Models\ClientContact` — N:1 to Client, FK `client_id ON DELETE CASCADE`. Holds `email`/`phone`/`is_primary`. **Client itself has no email/phone column** — both read from the primary contact.
  - `App\Data\Clients\*` — `ClientIndexFilterData` (filters: search, type), `ClientListItemData` (row + derived `primary_contact_email`/`primary_contact_phone`), `ClientDetailData` (address + contacts array, zero email/phone fields), `ClientStoreData`, `ClientUpdateData`, `ClientContactData`.
  - `App\Policies\ClientPolicy` — gates viewAny/view/create/update/delete on `view|create|edit|delete clients` permissions (references `PermissionEnum`).
  - `App\Enums\ClientTypeEnum` — `Corporate` (IČO required) | `Private` (IČO optional). `#[TypeScript]`. Methods: `label()` (i18n key), `options()` (select list).
  - FE: `Clients/Index` + `Clients/Show` Inertia pages, shared `ClientFormDrawer` (side-drawer create/edit, no separate routes), `useClientFilters` composable, generic `EmptyState` + `PageHeader`.
- **Flow (list):** `GET /clients` → `ClientController@index` (`#[Authorize('viewAny', Client)]`) → `ClientService::paginate` (Spatie QueryBuilder: filters search/type, sorts name/created_at) → `ClientListItemData::collect(..., PaginatedDataCollection)` → Inertia `Clients/Index`.
- **Flow (write):** `POST|PUT /clients` → `ClientController@store|update` (DTO) → `ClientService` in `DB::transaction` → `syncContacts` diff-apply (eager-load, diff incoming IDs, soft-delete missing, update/create matched) → redirect with `flash.success`.
- **Depends on:** `identity-tenancy` (tenant_id FK, permission gate).
- **Depended on by:** `objects` (Client HasMany CleaningObject via `client_id` FK). Quote → Contract → Invoice will also FK to Client (not yet built).
- **If you change Core, check:** `ClientController` (5 actions), `ClientPolicy`, `routes/web.php` (explicit routes: `clients.index`, `clients.show`, `clients.store`, `clients.update`, `clients.destroy`), `lang/{sk,en,uk}/app.php` `clients.*` keys, `objects` domain if Client model changes relation shape.
- **Keywords:** klient, kontakt, IČO, DIČ, IČ DPH, Corporate, Private, primary contact.

### objects

Physical cleaning locations (office/apartment/house/common areas), assigned to clients. Central entity in the chain client → object → (quote → contract → invoice). Holds access info and metadata.

- **Core:** `App\Models\CleaningObject` (PHP class name avoids `Object` global collision; table = `objects`). UUIDv7, tenant_id FK, BelongsToTenant, SoftDeletes, LogsActivity. Direct `client_id` FK (restrictOnDelete; soft-cascade in ClientService::delete via explicit soft-delete call). Reassignable client_id via ObjectUpdateData. Fields: name, type (ObjectTypeEnum: office/apartment/house/common_areas), address (street/city/postal_code/country), access info (access_code/key_box_code/key_count), special_instructions, area_sqm, floor, is_active. GPS columns (gps_lat/gps_lng) reserved in migration for Phase 2, NOT exposed in DTOs. `App\Services\ObjectService` — paginate/create/update/delete, Spatie QueryBuilder filters (search/type/client_id/is_active).
- **Satellites:**
  - `App\Enums\ObjectTypeEnum` — Office | Apartment | House | CommonAreas. `#[TypeScript]`. Methods: `label()` (i18n key), `options()` (select list).
  - `App\Data\Objects\*` — `ObjectIndexFilterData` (filters: search, type, client_id, is_active, per_page), `ObjectListItemData` (row data), `ObjectDetailData` (full object + client relation), `ObjectStoreData` (client_id via `Rule::exists(...)->where('tenant_id', $tenantId)` — closes cross-tenant leak), `ObjectUpdateData` (incl client_id reassignment).
  - `App\Policies\ObjectPolicy` — gates viewAny/view/create/update/delete on `view|create|edit|delete objects` permissions (references `PermissionEnum`).
  - FE: `Objects/Index` + `Objects/Show` Inertia pages, shared `ObjectFormDrawer` (side-drawer create/edit, no separate routes), `useObjectFilters` composable (state mgmt for filter input), `ObjectTypeBadge` component.
  - Routes feature-gated `feature:objects` (plan axis): objects.index/show/store/update/destroy.
- **Flow (list):** `GET /objects` (middleware `feature:objects` checks plan) → `ObjectController@index` (`#[Authorize('viewAny', CleaningObject)]`) → `ObjectService::paginate` (Spatie QueryBuilder: filters search/type/client_id/is_active, sorts name/created_at, eager-loads client) → `ObjectListItemData::collect(...)` → Inertia `Objects/Index`.
- **Flow (write):** `POST|PUT /objects` (middleware `feature:objects`) → `ObjectController@store|update` (DTO) → `ObjectService` in `DB::transaction` → update + load client → `ObjectDetailData` render or redirect with `flash.success`.
- **Depends on:** `identity-tenancy` (tenant_id FK, permission gate), `subscription-plans` (route middleware `feature:objects` checks plan).
- **Depended on by (planned):** Quote FK to Object (and Client) — generates work breakdown. Contract may polymorphically bind to Object (future). Schedule/Job binds to Object. Invoice references Job (via Object).
- **If you change Core, check:** `ObjectController` (5 actions), `ObjectPolicy`, `routes/web.php` (feature:objects middleware + 5 named routes), `lang/{sk,en,uk}/app.php` `object_type.*` keys, `RoleTemplatesSeeder` (Sekretárka/Vedúca/Upratovačka/Účtovníčka object perms), Client::destroy soft-delete logic (if it cascade-deletes objects), `AppServiceProvider` explicit policy binding (CleaningObject → ObjectPolicy).
- **Keywords:** lokality, umiestnenie, prístupové kódy, pristup, office, apartment, house, common_areas, access_code, key_box_code, gps (reserved), client FK, central entity, feature:objects.

### capabilities

Client-side authorization + entitlement layer. Queries server for runtime permission + feature check (two-axis AND model: user/permission axis AND plan/feature axis) + remaining tenant-creation quota.

- **Core:** `resources/js/stores/capabilities.ts` (Pinia Options store) — state: permissions, features, accountPlan, remainingTenantSlots, loaded (bool); actions: fetch, ensureLoaded, reset. Hydrated async from `GET /api/me`.
- **Satellites:**
  - `resources/js/services/MeService.ts` — `GET /api/me` over window.axios (session-auth XHR, returns `MeData` from BE DTO with accountPlan + remainingTenantSlots).
  - `resources/js/composables/useAuthorization.ts` — read-only composable `can(permission)`, `hasFeature(feature)`, `allows(permission, feature)` (AND semantics), `canCreateTenant(): bool` (checks remainingTenantSlots > 0). Reads from store.
  - `resources/js/Components/Can.vue` — declarative slot-based guard: `<Can permission feature>` (both optional, both AND if present) + `#fallback`. Render conditionally or hide element.
  - `resources/js/lib/routeRequirements.ts` — path-prefix → {permission?, feature?} map for route-level early guard.
  - `AppLayout.vue` tenant dropdown — conditionally disables "Pridať novú firmu" affordance if `!useAuthorization().canCreateTenant()`.
- **Flow (page load):** App.vue mounted → `useAuthorization().ensureLoaded()` (sync cache check, fail-open on first nav) → Inertia `router.on('before')` reads store, denies redirect to /dashboard if lacking capability. `navigate` hook async-awaits `ensureLoaded()`. `AppLayout.vue` logout calls store `reset()`.
- **Flow (add tenant UX):** AppLayout tenant dropdown displays active tenant + available tenants + "Pridať novú firmu" link. Link disabled (grayed) if `remainingTenantSlots <= 0`. Click → AddTenantModal dialog. Submit → `TenantController@store` server-side canCreateTenant check (403 if over limit, FE shows `app.tenant.limit_reached`). On success, tenant added to list, store re-synced on next navigation.
- **Depends on:** `identity-tenancy` (MeController, /api/me endpoint); `subscription-plans` (features list, max_tenants quota).
- **Depended on by:** every page that needs UI capability gating (client-side render condition via `Can` or composable `can()`); AppLayout (canCreateTenant disabling).
- **If you change Core, check:** `MeController` (accountPlan + remainingTenantSlots hydration = maxTenants - ownedTenants.count), `ChecksFeatures::maxTenants` (plan config lookup), `routes/api.php` (/api/me route), FE store (state shape), FE composable `canCreateTenant`, AppLayout tenant dropdown UX disable logic.
- **Keywords:** permission, feature, can, allows, capability, entitlement, two-axis AND, render-by-capability, Can component, useAuthorization.

- **Multi-tenancy global scope** — `App\Concerns\BelongsToTenant` auto-applies `App\Scopes\TenantScope` and auto-fills `tenant_id` on `creating` from `app('current_tenant_id')`. Reach: every domain query. Bypass (jobs/console only): `Model::withoutGlobalScope(TenantScope::class)`. We deliberately did **not** install `stancl/tenancy` — spec mandates the trait+scope approach.
- **Permissions (Spatie teams=tenant_id)** — `config/permission.php`: `teams=true`, `team_foreign_key='tenant_id'`, `'role'=>App\Models\Role::class`. Flat strings via `App\Enums\PermissionEnum` (canonical source of truth — all code references `PermissionEnum::Xxx->value`). BE Policy + Controller `#[Authorize]` gating; FE `Can` component + `useAuthorization().can()` composable read from Pinia store (hydrated from `/api/me`). Reach: every Policy + `#[Authorize]` site + every FE render conditional. **Outside HTTP (seeder/job/console/test) you MUST `setPermissionsTeamId($tenantId)` first** or lookups return "permission not found".
- **Feature gating (entitlement layer)** — `ChecksFeatures` interface + `ConfigFeatureChecker` reads `config/subscription.php` plan matrix. Stacks **on top of** RBAC permissions: plan gates the tenant ("does your tier unlock Invoices?"), Spatie gates the user ("can you view invoices?"). Both must pass (AND logic). BE: Middleware `RequiresTenantFeature` enforces plan entitlements at route level. FE: capabilities store + `useAuthorization().hasFeature()` + `Can` component gate UI. DIP interface allows future `PennantFeatureChecker` or DB-backed adapter without caller changes. Reach: any route gated `->middleware('feature:xxx')` + any FE component checking `hasFeature()`. Quota enforcement (entity count vs. limit) is caller responsibility, checker is stateless.
- **Activitylog** — `LogsActivity` on Role (+ Client/ClientContact). Auth events (Login/Logout/Failed/Lockout/PasswordReset/PasswordChanged/Registered/Verified) are logged asynchronously via `App\Listeners\AuthEventListener` (implements `ShouldQueue`, `#[Tries(3)]`). Writes to `activity_log`. Morph columns `subject_id`/`causer_id` are **strings** to span mixed-PK models (Role bigint, User/Client UUID).
- **MediaLibrary** — `media` table present, `model_id` UUID. No collections wired on domain models yet (reserved for DocumentTemplate / photos).
- **i18n** — SK (default) / EN / UA. `App\Enums\SupportedLanguage`, `lang/{sk,en,uk}/app.php`, `LocaleMiddleware` (order: user.locale → session → cookie → Accept-Language → SK). BE flattens nested keys via `Arr::dot()`; FE `useTranslate()` does flat `t(key)` lookup. Reach: every visible string.
- **Inertia shared props** — `HandleInertiaRequests::share`: `app, auth, tenant{active,available}, can{…}, flash, translations, locale, languages, canResetPassword`. `tenant.available` is a `DataCollection<TenantListItemData>` (typed DTO: id, name, is_active). Typed in `resources/js/types/index.d.ts`, read via `usePageProps()`. Reach: every page.
- **TypeScript transformer** — `php artisan typescript:transform` generates `resources/js/types/generated.d.ts` from `app/Data` + `app/Enums` (`#[TypeScript]`). Never hand-edit. Wired via `App\Providers\TypeScriptTransformerServiceProvider` (no config file in v3).

## Layer contracts

- **HTTP ↔ Service** — Controllers hand a Spatie Data DTO to the service, never an array. Controllers are thin: type-hint DTO param + service in constructor, return `Inertia::render` or `to_route()->with('flash.success', …)`.
- **Service ↔ Model** — `DB::transaction` lives in the Service, NEVER the Controller. Services are `final readonly class XxxService`. Multi-model bootstrap (register) also in Service.
- **BE ↔ FE (Inertia, page render)** — page prop shape = the DTO's generated TS namespace. Shared props also follow the DTO rule: `tenant.available` is a `DataCollection<TenantListItemData>` (not raw arrays). Enums shared via `#[TypeScript]` → `generated.d.ts`. Flash via `flash.success` session key. Inertia provides new-registered user with `justRegistered: boolean` flash to trigger welcome overlay.
- **BE ↔ FE (API: /api/me)** — `GET /api/me` returns `MeData(userId, activeTenantId, permissions: list<string>, features: list<string>)`. Session-auth XHR (cookies). FE capabilities store hydrates from this shape; UI conditionals read from store via `useAuthorization()` composable + `Can` component.
- **BE ↔ FE (API: /api/icos/{ico})** — `GET /api/icos/{ico}` returns `IcoLookupData(ico, name?, vat_status?)` over JSON. Guest-accessible, throttled. FE composable `useIcoLookup` debounces + cancels on unmount.
- **Auth gate** — BE: business models gated by **Policy + `#[Authorize]` per controller method** (rbac-full), not route middleware. FE: `<Can permission feature>` component + `useAuthorization()` composable conditionally render. Both axes (permission + feature) AND together; missing either → deny render / deny route access. Permission lookup is tenant-scoped — relies on `TenantContextMiddleware` having run.
- **Registration (atomic)** — `POST /register` collects all 3 steps client-side (account + company + invites), sends single `RegisterData` DTO, RegistrationService wraps User→Tenant→TenantInterface→TenantMembership→Roles→Invitations in `DB::transaction`. User auto-login + `active_tenant_id` session binding happens post-commit (outside transaction).

## Gotchas

- **Registration atomic scope — no role assignment outside transaction** — `RegistrationService::register()` calls `RoleTemplatesSeeder::seedForTenant()` and assigns Vlastník role *inside* the `DB::transaction`. Post-transaction, the request redirects + auto-logs in (session-only). Do NOT move role assignment outside the transaction; it must happen before user can be queried for permissions in later requests.
- **RoleTemplatesSeeder now static** — refactored from seeder to `static seedForTenant(Tenant)` (called from RegistrationService and add-tenant flow). Outside HTTP (jobs/console), always call `setPermissionsTeamId()` *before* using the seeder or querying roles. Inside RegistrationService transaction, it's already set.
- **TenantInterface uses bigint PK, not UUID** — deliberate exception. Settings models never appear in URLs/DTOs, so bigint is safe. The 1:1 relation is enforced at DB level (unique tenant_id FK). Always create via `TenantInterface::create()` when bootstrapping a tenant; the RegistrationService does this automatically.
- **Email auto-verified on register** — no email-verification flow exists in this SaaS. `RegistrationService::register()` calls `$user->forceFill(['email_verified_at' => now()])->save()` post-creation (email_verified_at is NOT in fillable; forceFill bypasses guards). This is intentional (spec).
- **TenantInvitation partial unique + soft-delete** — migration creates `unique(['tenant_id', 'email'], [], where: "status = 'pending' AND deleted_at IS NULL")`. Pending invites are unique per tenant; soft-deleting one allows re-inviting the same email. Accepting an invite will change status to Accepted (future feature); multiple Pending rows with same email should never coexist.
- **Soft-delete + partial unique** — `clients (tenant_id, ico) WHERE deleted_at IS NULL AND ico IS NOT NULL`. IČO unique per tenant among *active* clients; resurrected/soft-deleted rows don't block re-use. A plain `unique` would break this. `ClientService` catches the `QueryException` and rethrows as `ValidationException`.
- **Permission team scope outside HTTP** — forgetting `setPermissionsTeamId` in seeders/jobs/tests silently returns empty permissions. Most common multi-tenant bug.
- **`activity_log` morph columns are strings** — not UUIDs, so polymorphic causer/subject works across bigint (Role) + UUID (User/Client/TenantInvitation) models.
- **Spatie permission migration is UUID-patched** — the published `2026_05_03_194335_create_permission_tables.php` was edited: `model_morph_key` + `team_foreign_key` are `uuid()`, but `permissions.id`/`roles.id` stay bigint (Spatie internal, never in URLs/DTOs).
- **Spatie Data v4 ↔ typescript-transformer v3 mismatch** — `DataTypeScriptTransformer` references a removed class. Use `Spatie\LaravelTypeScriptTransformer\LaravelData\Transformers\DataClassTransformer` directly (see the service provider).
- **Activitylog v5 namespace shift** — `…\Models\Concerns\LogsActivity` (not `Traits`), `…\Support\LogOptions`; `dontSubmitEmptyLogs()` → `dontLogEmptyChanges()`.
- **Auth page palette** — `Pages/Auth/Register.vue` + `Login.vue` use `--auth-*` CSS custom properties (brown/amber gradient, not `cleanmaster` DaisyUI theme tokens). Tokenized in `resources/css/app.css` (:62–107). Not the same as `Landing.vue`'s blue/slate or the main app's DaisyUI theme.
- **Landing page palette** — `Pages/Landing.vue` uses raw Tailwind blue/slate, NOT DaisyUI semantic tokens (fixed marketing brand, theme-independent). Don't "fix" it to use the `cleanmaster` theme.
- **`usePageProps()` cast** — Inertia v3 augmentation didn't type-check reliably across imports, so we cast once inside the composable (`usePage().props as unknown as SharedProps`). Revisit if Inertia typing improves.
- **IČO lookup mock is hardcoded** — `IcoLookupService::lookup()` currently checks a static SKMap. Replace with ARES API when ready (flag says `// TODO swap for ARES API`). No unit tests on the mock; API integration tests will be needed post-swap.
- **Subscription plan moved from Tenant to User** — Tenant no longer has a `subscription_plan` column. Plans belong to the **account** (User). Each Tenant has an `owner_id` FK to the User who created it. Feature gating via `$tenant->owner->subscription_plan`, not `$tenant->subscription_plan`. `ConfigFeatureChecker::hasFeature($tenant, $feature)` eagerly loads owner and reads its plan. Tenant quota (max tenants) checked via `ChecksFeatures::canCreateTenant($user)` before POST /tenants. ON DELETE CASCADE when owner is deleted.
- **CleaningObject PHP class name avoids `Object` global** — the model is `CleaningObject` (not `Object`) to avoid collision with PHP's `Object` builtin. The DB table is `objects`, routes use `objects`, permissions use `objects`, only the PHP class name differs. Explicit `Gate::policy(CleaningObject::class, ObjectPolicy::class)` in `AppServiceProvider` required because auto-discovery looks for `CleaningObjectPolicy`, not `ObjectPolicy`.
- **Rule::exists tenant-scope on ObjectStoreData** — `client_id` field includes `Rule::exists('clients', 'id')->where('tenant_id', $tenantId)` in `rules()` method. This closes the cross-tenant leak: a user from tenant A cannot POST `/objects` with a client_id from tenant B, because the Exists rule filters by the resolved `$tenantId` (from `app('current_tenant_id')`). TenantScope applies at query time; DTO validation applies at HTTP boundary.

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
3. **Quote** — line items, status enum, PDF, send-to-client, auto work breakdown.
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
- Last delta: 2026-06-07 (feat: Objects (lokality) module — CleaningObject model UUID + tenant_id + BelongsToTenant + SoftDeletes + LogsActivity, ObjectTypeEnum (office/apartment/house/common_areas), ObjectService CRUD + QueryBuilder filters (search/type/client_id/is_active), ObjectPolicy #[Authorize] gates on permissions, routes feature:objects middleware plan-gated (Starter+), DTOs ObjectStoreData/ObjectUpdateData/ObjectDetailData/ObjectListItemData/ObjectIndexFilterData with Rule::exists tenant-scope for client_id validation, FE Objects/Index + Objects/Show pages + ObjectFormDrawer side-drawer + useObjectFilters composable + ObjectTypeBadge component, RoleTemplatesSeeder assigns object perms to Vlastník/Sekretárka/Vedúca/Upratovačka/Účtovníčka, explicit Gate::policy binding in AppServiceProvider for CleaningObject→ObjectPolicy naming mismatch, i18n keys for object_type enum labels)
- Open `TODO verify`: 1 (IČO lookup service mock — ARES API swap pending)
- Reference inventory: `.claude/inventory.md` (not generated; opt-in via `/spec-sync --full --with-inventory`)
