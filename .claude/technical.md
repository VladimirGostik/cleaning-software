<!-- inogile:context-version=3 -->
# Technical relationship map — cleaning-software @ main (vue-skeleton fork)

## Project type

laravel-be + inertia-fe (phase 2 tenancy complete: multi-tenant row-level scoping, tenant switcher, invitations)

## Stack snapshot

**Backend:** Laravel 13.7 (PHP 8.5) + Inertia 3.0.6 (Vue 3 + TS + Tailwind 4 + DaisyUI 5) · Spatie Data 4.22 / Permission 7.4.1 (teams=true, team_foreign_key=tenant_id) / Activitylog 5.0 (uuid morphs) / MediaLibrary 11.22 / QueryBuilder 7.2 / TypeScript-Transformer 3 · Sanctum 4.3.2 (Bearer API + uuidMorphs fix applied 2026-09-06) · Scribe 5.9 · league/flysystem-aws-s3-v3 ^3 (documents disk `s3`, unverified against a real bucket) · symfony/html-sanitizer ^8 (DocumentHtmlSanitizer) · Postgres 16 (compose) · PHPUnit 12 on Postgres `cleanmaster_admin_testing` (phpunit.xml force=true, init SQL docker/postgres/init-testing-db.sql) · test count: see CLAUDE.md · Docker: `docker compose exec app php artisan …`

**Mobile + AI (Phase 11):** laravel/ai ^0.7.2 (Anthropic provider, `AI_MODEL` default `claude-sonnet-5-5`, `ANTHROPIC_API_KEY` backend only) · laravel/mcp ^0.7 (`/mcp` web server + `cleanmaster` local stdio server) · kreait/laravel-firebase ^7.2 (FCM via `FirebasePushSender`, `FIREBASE_CREDENTIALS`) · qpdf binary (`QpdfMerger`, bulk invoice PDF).

**Frontend:** @inertiajs/vue3 ^3.0.3 + Vue ^3.5.33 + TS ^6 strict + Vite 8 + laravel-vite-plugin ^3 · NO Pinia · NO Ziggy (0 route() calls; all URLs string literals) · i18n = vue-i18n ^11 (legacy:false), messages bundled from resources/lang/{sk,en}/app.json + locale from Inertia prop (sk default; sk/en/uk) · Theme: DaisyUI 5 themes:false + [data-theme='app-theme'] OKLCH token block in resources/css/app.css; Instrument Sans via bunny.net · Icons @heroicons/vue · Dates date-fns (utils/date.ts) · @tiptap 3.23.x (starter-kit, link, image, table, text-align, text-style, highlight; the document editor under Components/Documents) · ESLint 10 flat (no ref ban yet), Prettier, Lefthook pre-commit + pre-push vue-tsc. FE test runner: vitest ^4 + happy-dom (`pnpm test`, pure TS specs, no @vue/test-utils).

## Domains

### tenancy — multi-tenant isolation, invitations, team management (Phase 2, 2026-09-06)

**Core:**
- App\Models\Tenant — UUIDv7; traits HasFactory, HasUuids, InteractsWithMedia, LogsActivity (logOnly owner_id,name,ico,dic,vat_number,is_vat_payer,vat_rate,iban,is_active,signature_media_id), SoftDeletes. Columns: owner_id FK → users (restrictOnDelete), name, ico (nullable), dic (nullable), vat_number (nullable), is_vat_payer (bool), vat_rate (decimal), iban (nullable), swift_bic (nullable), invoice_number_format, registration_info, address_line, city, postal_code, country, contact_email, contact_phone, representative_name (nullable, signatory printed on contract text; edited via InvoiceSettingsData / Settings->Invoicing), **signature_media_id** FK (nullable, bigint) → media (nullOnDelete), is_active, timestamps, soft_deletes. Relations: owner BelongsTo User, interface HasOne TenantInterface, memberships HasMany TenantMembership, members BelongsToMany User (via tenant_memberships, pivot is_active/joined_at), signatureMedia BelongsTo Media (signature_media_id). Methods: `registerMediaCollections(): void` → `$this->addMediaCollection('signature')->useDisk(config('invoicing.signature.disk', 'local'))` (NOT singleFile — superseded rows retained so issued invoices keep resolving), `missingSupplierFields(): array` (list required fields still empty: name/address_line/city/postal_code/ico + dic/vat_number when is_vat_payer; returns [] when complete; FE contract for banner label keys), `hasCompleteSupplierProfile(): bool` (shortcut === [] guard).
- App\Models\TenantMembership — UUIDv7 pivot; traits HasFactory, HasUuids, LogsActivity (logOnly is_active,position,first_name,last_name). Columns: user_id FK (restrictOnDelete), tenant_id FK (restrictOnDelete), is_active (bool), joined_at (timestamp), first_name (nullable), last_name (nullable), phone (nullable), position (nullable), timestamps. Unique (user_id, tenant_id); indices (tenant_id, is_active), (user_id). NOT BelongsToTenant (the pivot defines tenancy itself). Relations: user BelongsTo User, tenant BelongsTo Tenant.
- App\Models\TenantInterface — UUIDv7 settings per tenant (D1); traits HasUuids, LogsActivity (logOnly color). Columns: tenant_id FK unique (restrictOnDelete), color (TenantColorEnum nullable, integer in DB), invoice_template (InvoiceTemplateEnum, default 'classic'), recurring_default_state (RecurringInvoiceStatusEnum, default 'draft'), default_constant_symbol (varchar 50 nullable), default_payment_type (PaymentTypeEnum, default 'bank_transfer'), default_currency (CurrencyEnum, default 'EUR'), default_rounding_mode (RoundingModeEnum, default 'half_up'), invoice_number_format (varchar 255, default '{number}'), vat_rate (decimal 5,2, default 20.00), **default_header_text (text nullable, phase 10)**, **default_footer_text (text nullable, phase 10)**, timestamps. Relation: tenant BelongsTo.
- App\Models\TenantInvitation — UUIDv7; traits BelongsToTenant, HasFactory, HasUuids, LogsActivity (logOnly email,role_name,status,expires_at,accepted_at, never token), SoftDeletes. Columns: tenant_id FK (restrictOnDelete), invited_by_user_id FK (nullOnDelete), email, role_name (100), token (64 unique), status (InvitationStatusEnum), expires_at, accepted_at (nullable), timestamps, soft_deletes. Indices (tenant_id, status); partial unique (tenant_id, email) WHERE deleted_at IS NULL AND status='pending'. Relations: invitedBy BelongsTo User, tenant BelongsTo (inherited scope). Methods: isAcceptable() (checks expiry + pending status), markAccepted().
- App\Services\RegistrationService (final readonly) — `createOwner(name, email, password, companyName, ico, ?TenantSupplierProfileData $supplier = null): User` → DB::transaction, User→Tenant (with supplier data if provided)→TenantInterface→TenantMembership→RoleTemplatesSeeder→Admin role assignment. `addTenant(User, AddTenantData): Tenant` → bootstrapTenant with null supplier (D1: redirect to settings.invoicing instead). `private bootstrapTenant(User, name, ico, color?, ?TenantSupplierProfileData $supplier = null): Tenant` — atomically seeds Tenant (supplier columns from DTO or defaults) + interface + membership + team-scoped role bundle.
- App\Services\InvitationAcceptService — `resolve(token): TenantInvitation` (withoutGlobalScope), `accept(invitation, AcceptInvitationData, skipPasswordCheck?): User` — transaction: abort if non-acceptable (410), hash-check existing user (or create+verify new user), setPermissionsTeamId(invitation.tenant_id), create/reactivate membership, assign role, markAccepted, return user. **Critical:** role lookup happens inside the invitation's tenant context, not session tenant.
- App\Services\RoleAssignmentGuard (final readonly) — `assertAssignable(User $actor, iterable<Role> $roles): void` — validates each role's permission set ⊆ actor's permissions; throws ValidationException if violated (privilege escalation guard for UserService::create/update).
- App\Http\Middleware\TenantContextMiddleware — app middleware (appended after LocaleMiddleware on web; **prepended before `SubstituteBindings` via `bootstrap/app.php` `prependToPriorityList`**, so route-model binding runs under the resolved tenant — security fix `6f08aa9`, absent on `main`). `handle` resolves tenantId per D5: X-Tenant-Id header (if uuid + active membership, else 403 `tenant_forbidden`) → session active_tenant_id → first active membership (**for a `mobile` token: first membership whose `TenantVisibility::mobileAccess` is true**). If resolved: `app()->instance('current_tenant_id', $id)`, `setPermissionsTeamId($id)`, session bind. **Phase 11:** `mobile` token in a resolved tenant without `access mobile app` → 403 `mobile_forbidden`. Unauth users pass through (no binding).
- App\Http\Middleware\RequireActiveTenant — route middleware (`tenant.required`). Authenticated + no bound tenant → web: `Auth::logout()`, invalidate session, redirect login with error flash; API: 403 JSON `no_active_tenant`. Logout route still reachable (not gated).
- App\Scopes\TenantScope — global scope on BelongsToTenant models. When `app()->bound('current_tenant_id')`: `where tenant_id = ?`; else unfiltered (D6: console/jobs/seeders run intentionally unbound). **Phase 11 exceptions to the BelongsToTenant invariant:** `Task` (own `TaskScope`, nullable `tenant_id`, see domain `tasks`), `Notification` (nullable `tenant_id` for system messages), `DeviceToken` (per person, no `tenant_id`, see domain `push`).
- App\Concerns\BelongsToTenant — trait: `bootBelongsToTenant()` adds TenantScope + creating hook fills tenant_id from container; `tenant(): BelongsTo<Tenant,$this>`.

**Satellites (BE):**
- DTOs: Tenants/{AddTenantData (#[TypeScript]: full supplier profile fields + colour + copy_settings), TenantListItemData, TenantSupplierProfileData, TenantSwitchData (#[TypeScript]: redirect_to relative path optional, regex validator rejects absolute/proto URLs)}, Invitations/{AcceptInvitationData, InvitationAcceptPageData}, Auth/MeData, PermissionGroupData (grouped permission list for forms). TenantSupplierProfileData (#[TypeScript]): full supplier identity input (address_line, city, postal_code, country, dic, vat_number, is_vat_payer, contact_email, contact_phone, iban, swift_bic; name not included, handled by Tenant directly); two constants IBAN_PATTERN and SWIFT_BIC_PATTERN (regex, reused by InvoiceSettingsData for single-source-of-truth); `toTenantAttributes()` returns dict for Tenant::create; used by RegistrationService::createOwner + CreateOwner command + AddTenantData::toSupplierProfile(). AddTenantData: name, ico (required), dic/vat_number/is_vat_payer/address_line/city/postal_code/country/contact_email/contact_phone/iban/swift_bic (optional), colour (nullable TenantColorEnum), copy_settings (bool); `toSupplierProfile()` method converts to TenantSupplierProfileData. TenantSwitchData: optional redirect_to param for POST /tenants/{id}/switch (used to redirect after switch instead of dashboard default).
- Enums: PermissionEnum (64 backed string cases, #[TypeScript]), TenantColorEnum (8 hex, #[TypeScript]), InvitationStatusEnum (pending/accepted/revoked/expired), InvitationAcceptStateEnum (4 states for FE: expired/wrong_user/existing_user/new_user), SupportedLanguage (now sk/en/uk with `getDefault()`, `getDisplayName()`, `isSupported()`).
- Factories: TenantFactory (forOwner), TenantMembershipFactory (withProfile, inactive states), TenantInvitationFactory (accepted, expired, revoked states), TenantInterfaceFactory.
- Seeders: PermissionSeeder (global catalogue from PermissionEnum), RoleTemplatesSeeder (per-tenant role bundles: Admin=all, Vedúca, Sekretárka, Účtovníčka, Interná upratovačka, Zákazník; `seedForTenant(Tenant)` idempotent, called post-bootstrap).
- Policies: TenantPolicy::create(User) = `users.is_platform_admin` (a new tenant has no roles yet, so a permission check would be circular; the flag replaces the former D4a no-policy exception, 2026-10-31); TenantPolicy::switchTo(User, Tenant) = active membership + active tenant.
- Notifications: InvitationCreated (ShouldQueue, afterCommit, #[Tries(3)], mail-only, token + tenant_name + role_name in body).
- Commands: CreateOwner (interactive prompts or flags: --name, --email, --password, --company, --ico + 11 optional supplier flags --address-line, --city, --postal-code, --country (default SK), --dic, --vat-number, --vat-payer (value-less), --contact-email, --contact-phone, --iban, --swift; validates via TenantSupplierProfileData::validateAndCreate before any DB write; calls RegistrationService::createOwner with built DTO).
- Routes: POST /tenants (`#[Authorize('create', Tenant::class)]` -> TenantPolicy::create, 403 unless platform admin; accepts AddTenantData), POST /tenants/{tenant}/switch (TenantPolicy, accepts TenantSwitchData with optional redirect_to param, redirects to $data->redirect_to ?? '/'), GET|POST /invitations/{token} (guest, throttle:invitation-accept on POST), GET /api/me (in the `/api` group, see Cross-cutting "Mobile API surface"; returns MeData with active tenant + permissions per team scope).
- Middleware: TenantContextMiddleware (app-level, D5 resolution), RequireActiveTenant (route `tenant.required`), plus bootstrap/app.php registers aliases.

**FE Satellites (apply via FE agent plan phase-2-tenancy-fe.md):**
- Composables: usePageProps (ComputedRef<SharedProps>), useAuthorization (allows(PermissionEnum)), useTenantTheme (themeStyle for --color-primary override).
- Components: TenantColorDot, TenantSwitcher (dropdown), AddTenantModal, ColorSwatchPicker, Can (permission guard).
- Pages: Invitations/Accept (4 states: expired/wrong_user/existing_user/new_user), Users/Index/Form (tenant members only, updated filters/forms).
- Shared props: tenant{active,available}, tenantColors, can (camelCase keys per PermissionEnum::sharedKey).

**Flow:**
- **Create owner (bootstrap):** `php artisan app:create-owner` → prompts name/email/password/company/ico → RegistrationService::createOwner → User + Tenant + TenantInterface + TenantMembership + seeded roles + Admin role assigned → logs "created successfully". Bootstrap flow only.
- **Add tenant:** Platform admin only (`users.is_platform_admin`, others 403; shared prop `canCreateTenant` hides the TenantSwitcher entry + AddTenantModal trigger otherwise; `app:create-owner` bypasses the route) POST /tenants (AddTenantData: name, ico required; full supplier profile optional: dic, vat_number, is_vat_payer, address_line, city, postal_code, country=SK, contact_email, contact_phone, iban, swift_bic; colour optional, copy_settings bool) → RegistrationService::addTenant(user, data) → resolves source tenant if copy_settings true, colour defaults to source colour if set else data.colour → bootstrapTenant(user, name, ico, colour, data.toSupplierProfile()) → new Tenant (owned by actor, populated with supplier fields from DTO) → TenantInterface → active membership (actor + Admin role) → if copy_settings: copyInvoiceDefaults(source, target) copies invoice_template/recurring_default_state/default_constant_symbol/default_payment_type/default_currency/default_rounding_mode from TenantInterface + invoice_number_format/vat_rate from Tenant (NOT supplier identity) → session switch to new tenant → TenantController::store: if tenant.hasCompleteSupplierProfile() → redirect dashboard with `tenant_created` flash, else → redirect to_route('settings.invoicing') with `tenant_created_complete_supplier` flash (D1: onboard actor to complete profile before invoicing). Gated by TenantPolicy::create. No leader invitation created.
- **Switch tenant:** User POST /tenants/{tenant}/switch → TenantPolicy::switchTo gates (membership + active) → session bind active_tenant_id → Inertia redirect dashboard → shared props refresh (tenant, can, navigation).
- **Accept invitation:** Guest or logged-in GET /invitations/{token} → resolves InvitationAcceptPageData (4 states) → InvitationAcceptForm if form-state; POST /invitations/{token} (AcceptInvitationData: password, name optional, throttle 5/min) → InvitationAcceptService::accept (existing: Hash::check; new: create+verify) → Auth::login → session bind inviting tenant → redirect dashboard + flash. Expired/accepted/revoked → 410. Unknown token → 404.
- **API /api/me:** GET (auth:sanctum + tenant.context) → MeData{userId, activeTenantId, permissions[]} (per resolved tenant team scope via PermissionRegistrar::setPermissionsTeamId).

**Depends on:** Spatie Permission (teams=true, team_foreign_key=tenant_id), Spatie Activitylog, mailer (InvitationCreated notification).

**Depended on by:** Every tenant-scoped model (identity, auth, audit-logs, media, localisation) inherits BelongsToTenant + TenantScope; all services call setPermissionsTeamId; controllers gate via TenantPolicy / model policies + app()-bound current_tenant_id.

**If you change Core, check:**
- PermissionEnum consumers: PermissionSeeder, RoleTemplatesSeeder, PermissionGroupData, PermissionManager (FE), Inertia shared can keys, #[Authorize] routes, NavItem permissions.
- TenantColorEnum consumers: TenantInterface casting, AddTenantModal (FE), Inertia tenantColors prop, CSS --color-primary override (FE).
- TenantSupplierProfileData::IBAN_PATTERN / SWIFT_BIC_PATTERN regex constants: AddTenantData validation, InvoiceSettingsData validation (keep in sync).
- InvitationStatusEnum/InvitationAcceptStateEnum: InvitationAcceptPageData (FE mapping), InvitationController template, tests/Feature/Tenancy/InvitationAcceptTest.php.
- Invitation token route: regex constraint, throttle middleware, controller 410/404 guards.
- setPermissionsTeamId call sites: TenantContextMiddleware, RegistrationService, InvitationAcceptService, UserService, tests/TestCase::actingAsTenantUser, every job/command (D6 discipline).
- TenantScope unfiltered behaviour (D6): tests/Feature/Tenancy/TenantScopeIsolationTest.php assert; console commands + seeders bind explicit tenant context.

**Keywords (SK):** firma, tenancy, member, pozvánka, invitation, tenant switcher, multi-tenant, tenant isolation, shared-props, team scope, setPermissionsTeamId, tenant_id FK, BelongsToTenant, TenantScope, active membership, is_active.

### identity — users, roles, permissions, self-service profile

**Core:**
- App\Models\User — UUIDv7 via App\Concerns\HasUuids; traits HasRoles, HasApiTokens, LogsActivity (logOnly name,email,locale,is_active), Notifiable, CanResetPassword. Columns: name, email(unique), password, locale(2, default sk), is_active(bool, default true), is_platform_admin(bool, default false; NOT in #[Fillable], never in shared props; gates global document templates + POST /tenants; set via `app:grant-platform-admin {email}`, by `app:create-owner` when no platform admin exists yet, and by UserSeeder for admin@example.com), email_verified_at, timestamps. No SoftDeletes; global identity (no tenant_id). Relations: memberships HasMany TenantMembership, tenants BelongsToMany Tenant (via tenant_memberships, pivot is_active/joined_at), ownedTenants HasMany Tenant (owner_id). Methods: hasActiveMembership(?tenantId): bool (exists-query for "has at least one active membership").
- App\Models\Role extends Spatie Role — UUIDv7 via HasUuids + LogsActivity (logOnly name,guard_name,tenant_id) + scopeInTenant(Builder, string) (no global scope; app queries use inTenant() explicitly). Methods: isSystem(): bool (= name === RoleTemplatesSeeder::ADMIN_ROLE). Per-tenant.
- App\Models\Permission extends Spatie Permission — UUIDv7 via HasUuids. Global catalogue (not per-tenant); only roles are per-tenant.
- Both registered config/permission.php (teams=true, team_foreign_key=tenant_id, uuid team columns).
- App\Services\UserService (final readonly, ctor DatabaseManager, PermissionRegistrar, RoleAssignmentGuard): `create(CreateUserData, User $actor): User` — transaction, setPermissionsTeamId(current_tenant_id), look up or create User by email, membership create/reactivate (is_active from DTO), `$guard->assertAssignable($actor, roles)`, syncRoles in tenant scope, return fresh User. `update(User, UpdateUserData, User $actor): User` — name/email/locale on User, is_active on membership, roles guard + syncRoles in tenant scope. `delete(User): void` — syncRoles([]), syncPermissions([]), delete tenant membership (User row never hard-deleted in phase 2).
- App\Services\RoleService: `const SYSTEM_ROLES = [RoleTemplatesSeeder::ADMIN_ROLE]` · `create(name, permissions): Role` (check uniqueness inTenant + not in SYSTEM_ROLES) · `update(Role, name, permissions)` · `delete(Role)` (block SYSTEM_ROLES + Spatie guard) · `getPermissionsGrouped(): list<PermissionGroupData>` (from PermissionEnum cases grouped by group(), each item has id/name/label per PermissionItemData).
- App\Services\ProfileService::updateProfile(User, UpdateProfileData): User — name/email/locale on User, session('locale') + app()->setLocale(). Locale sync via app()->setLocale() (user.locale consumer on login via LocaleMiddleware).
- App\Enums\PermissionEnum (#[TypeScript], backed string) — 64 cases (Phase 11 added `access mobile app` in group `mobile`, five `tasks` cases incl. breadth `view all tasks`, and `view expenses` / `manage expenses` in group `expenses`), grouped into 15 groups (employees, roles, audit_logs, api_docs, media, billing; phases 3+ add clients, objects, quotes, contracts, schedule, invoices, recurring_invoices, notifications, document_templates). 2026-10-31: `*_contract_templates` renamed to `view|create|edit|delete document_templates` (migration 2026_10_31_000400 renames the permission rows in place, keeping role attachments); new in the employees group: `view employee_personal_data`, `view employee_documents`, `manage employee_documents`. Methods: label(), group(), groupLabel(), sharedKey() (Str::camel($value)), values(). Key list in Phase 2 plan §3.1.

**Satellites (FE):**
- Composables: usePageProps() (ComputedRef<SharedProps>), useAuthorization() (allows(PermissionEnum)).
- Components: Can (permission guard), PermissionManager (translated, groups: PermissionGroupData[]).
- Pages/Users/Index.vue — props {users: Paginator<UserListItemData>; filters?: Record<string,unknown>; filterOptions:{roles: RoleListItemData[]}} · QueryBuilder member-only filter is_active (on membership pivot) · Filters search/role/is_active (membership-scoped) · DataTable · Edit/DELETE gated `allows('edit employees')` / `allows('delete employees')`, create gated `allows('create employees')`. **Phase 2 change:** users are now active+inactive **members of the active tenant** (not globally all users); `is_active` filter label → `membership_active` (semantic = membership status, not User.is_active).
- Pages/Users/Form.vue — props {user?: UserListItemData; roles: RoleListItemData[]} · useForm(post|put, /users[/{id}], {name,email,password,password_confirmation,is_active,roles: string[]}) · Create: password required only if new email (DTO Rule::requiredIf); existing email → link (password ignored). ToggleInput field="is_active" label="membership_active". Roles: `Rule::exists('roles','name')->where('tenant_id', current_tenant_id)` + subset escalation guard → error on roles field. CreateUserData has `email` unique-check removed (link-existing flow); UpdateUserData validates email unique except self.
- Pages/Roles/Index.vue — props {roles: Paginator<RoleDetailData>} · Per-tenant roles (RoleController QueryBuilder::for(Role::inTenant($tenantId))) · canDeleteRow=!row.is_system · CREATE/EDIT/DELETE gated `allows('create roles')` etc. System role badge → `system_role`.
- Pages/Roles/Form.vue — props {role?: RoleDetailData; permissions: PermissionGroupData[]} · useForm(post|put, /roles[/{id}], {name,permissions: string[]}) · PermissionManager now receives grouped data structure (no client-side grouping; render by group + group checkbox + items) · system role: name disabled + alert-warning · Admin blocks delete.
- Pages/Profile/Show.vue — no page props, reads usePageProps().value.auth.user (ComputedRef, never destructure) · two useForm('put') forms (profile name/email/locale, password change) · locale select from shared languages (now includes uk).
- AppLayout language dropdown → <a href="/language/{locale}"> (full reload) → LocaleMiddleware resolves: user.locale → session → cookie → Accept-Language → sk. Languages list now data-driven from shared languages (includes uk).

**DTOs in:**
- CreateUserData (#[Required] name/email, ?string password [Rule::requiredIf new email], is_active=true, roles=[]), UpdateUserData (name/email/locale, is_active, roles=[]).
- CreateRoleData (#[Required, Max(100)] name, roles=[]), UpdateRoleData (name, permissions=[]).
- UpdateProfileData (name, email [unique except self], locale), ChangePasswordData (current_password [Hash::check], password [confirmed]).
- PermissionGroupData (#[TypeScript]: group, group_label, permissions: PermissionItemData[]) — PermissionItemData (id, name: PermissionEnum, label).

**DTOs out:**
- UserListItemData (id, name, email, roles: string[], is_active from membership in active tenant), UserAutocompleteItemData, RoleListItemData (id, name, counts, is_system), RoleDetailData (extends RoleListItemData, + permissions: string[]). Dead: UserIndexFilterData, RoleIndexFilterData.

**Policies:**
- UserPolicy (#[Authorize] gates): viewAny → `can('view employees')`, view → `can('view employees')` + record-level isMemberOf(current_tenant), update → `can('edit employees')` + isMemberOf, delete → `can('delete employees')` + isMemberOf + not self + not tenant owner.
- RolePolicy: viewAny/view → `can('view roles')` + `tenant_id === current_tenant_id`, create/update → `can('create|edit roles')` + tenant check, delete → `can('delete roles')` + tenant check + not SYSTEM_ROLES.
- Helper User::isMemberOf(tenantId): bool (any status); delete asserts User::isNotOwnerOfTenant(tenantId).

**Factories & Seeders:**
- UserFactory states unverified(), inactive(). TenantMembershipFactory states withProfile(), inactive(). UserSeeder creates admin@example.com/password via RegistrationService::createOwner (D3: canonical path, now owns "Demo Cleaning s.r.o."). No 5 faker users (spec chose "no demo accounts").

**Flow:**
- GET /users → UserController@index (#[Authorize('viewAny', PermissionEnum::ViewEmployees->value)]) → QueryBuilder::for(User::whereHas('memberships', fn ($q) => $q->where('tenant_id', current_tenant_id)->with(...)) + AllowedFilter filters (search/role/is_active membership-scoped) → ->through(UserListItemData::fromModel) → Inertia render Users/Index {users, filters, filterOptions{roles: RoleListItemData[]}}.
- POST /users (+Precognition) → @store(CreateUserData, $actor) → UserService::create($data, $actor) → User (new or existing email) + TenantMembership (create/reactivate) + role assignment (setPermissionsTeamId + assertAssignable guards) + return. PUT /users/{user} → @update(UpdateUserData, User, $actor) → UserService::update. DELETE /users/{user} → UserService::delete (membership removed, roles revoked, User row kept).
- GET /users/autocomplete?q= → UserController@autocomplete → whereHas('memberships', current_tenant) + ilike search → UserAutocompleteItemData[]; private const AUTOCOMPLETE_MIN_CHARS=2.
- GET /roles → RoleController@index (#[Authorize(PermissionEnum::ViewRoles->value)]) → QueryBuilder::for(Role::inTenant(current_tenant_id)) → Inertia render Roles/Index {roles: Paginator<RoleDetailData>}. POST /roles → @store(CreateRoleData) → RoleService::create → Roles/Form {role?: RoleDetailData, permissions: PermissionGroupData[]}.
- GET /profile → Inertia render Profile/Show (reads usePageProps().value.auth.user). PUT /profile → ProfileService::updateProfile. PUT /profile/password → Hash::check + update inline in controller (web + API).
- API mirror (Sanctum): GET /api/users → same member-only query; GET|PUT|DELETE /api/users/{user}, GET|PUT /api/profile, PUT /api/profile/password → same services, JSON UserListItemData (paginated). **Phase 11:** these routes sit in the `/api` group that requires `ability:mobile` (a web or MCP token gets 403).

**Depends on:** tenancy (TenantMembership, tenant_id FK, setPermissionsTeamId discipline), auth layer (#[Authorize], Spatie can() per team scope), App\Utils\AllowedFilter, Activitylog, i18n (PermissionEnum labels).

**Depended on by:** auth (User hasActiveMembership check at login), audit-logs (causer_type=User subject_type=User/Role), media (temporary_uploads.user_id), navigation (NavigationRegistry::canAccess checks can(permission)), tenancy (role templates, user invitations), Inertia shared can + auth.user + languages.

**If you change Core, check:**
- PermissionEnum changes: PermissionSeeder (findOrCreate per case), RoleTemplatesSeeder (role permission bundles), PermissionGroupData structure, PermissionManager (FE groups), HandleInertiaRequests shared can map (useAuthorization().allows(permission)), #[NavItem(permission: PermissionEnum::*->value)], policies can() checks, tests/Support/CreatesUsers.php userWithPermission().
- User model changes (columns, relations): app/Models/User.php (Fillable, relations, hasActiveMembership), UserListItemData (roles, is_active reads membership), UserFactory, tests/Feature/Auth (login guards is_active + hasActiveMembership), TenantMembership foreign key.
- Role model changes (per-tenant scoping): config/permission.php teams/team_foreign_key, RolePolicy (inTenant guards), RoleController/RoleService (inTenant queries), Role.php (isSystem, inTenant scope, logOnly fields).
- User.is_active enforcement: AuthController login/Api/AuthController login guards + RequireActiveTenant middleware (D4).
- Membership soft-revocation vs hard-delete: UserService::delete vs TenantMembership soft-delete strategy (currently hard-delete membership, never hard-delete User).

**Keywords:** user, tenant member, role (per-tenant), permission (global catalogue, per-team assignment), admin, is_active flag (User state), membership.is_active (tenancy state), locale, syncRoles (per-team), setPermissionsTeamId, autocomplete (tenant-scoped), profile, change password, PermissionEnum, hasActiveMembership, RoleAssignmentGuard (escalation prevention).

### auth — session login, password reset, Sanctum tokens, auth audit

**Core:**
- No model/service; controllers call Auth::attempt + multi-guard checks. Web: AuthController::showLogin/login(LoginData, Request)/logout. API: Api\AuthController::login(LoginData, Request)/logout (Sanctum Bearer). Password reset: PasswordResetLinkController::create|store(PasswordResetLinkData), NewPasswordController::create|store(NewPasswordData). Middleware guest/auth/auth:sanctum.
- **Phase 11 API tokens:** `POST /api/auth/login` (group: HandlePrecognitiveRequests + locale.api) issues `createToken('mobile', ['mobile'])` only when `TenantPermissionSnapshot::hasMobileAccess($user)` (permission `access mobile app` in some active tenant); otherwise `Auth::logout()` + 422 on `email` with `mobile_forbidden`. `POST /api/auth/logout` (auth:sanctum, locale.api) — no ability gate. MCP tokens are a separate kind: `ProfileMcpTokenController` → `McpTokenService::create` issues ability `['mcp']`, expiry 180 days, max 10 per user; `findOwnMcpToken` resolves only own tokens whose abilities are exactly `['mcp']` (a `mobile` token is not found there).
- **Login guards (D4):** Auth::attempt(['email' => …, 'password' => …, 'is_active' => true], remember) so inactive users fail indistinguishably from bad credentials + audit fires Failed event; then `if (! $user->hasActiveMembership()) { Auth::logout(); throw ValidationException::withMessages(['email' => [__('app.no_active_tenant')]]); }` (indistinguishable from no membership, deliberate per D4). API login same guards. Failure indistinguishable from bad credentials (failure still fires Failed event for audit).
- App\Listeners\LogAuthenticationActivity — handleLogin|handleLogout|handleFailed → activity()->log('login'|'logout'|'login_failed') with ip (get_client_ip()), user_agent, email (event may not have user for login_failed). Wired AppServiceProvider (3 events: Login, Logout, Failed only). No attempt limit (no rate limiting per gotcha 9; future hardening).
- RequireActiveTenant middleware (route `tenant.required`) ensures every authenticated HTTP request has a bound tenant via TenantContextMiddleware (D4). Mid-session membership deactivation → next request logs out web user / returns 403 API (transparent to user).
- Session invalidation on logout via `session()->invalidate()` + `regenerateToken()` (CSRF).

**Satellites (FE):**
- Pages/Auth/Login.vue — props {canResetPassword}; useForm('post','/login',{email,password,remember}), onFinish reset('password') · FormProvider + new Components/Auth/{AuthShell, AuthHero, AuthTextField, AuthPasswordField, AuthCheckboxField, AuthSubmitButton, AuthLanguageSwitcher}.
- Pages/Auth/ForgotPassword.vue — {status?}; POST /forgot-password · same Auth components + FormProvider.
- Pages/Auth/ResetPassword.vue — {token,email}; POST /reset-password · same Auth components + FormProvider.
- Layout: AuthShell wraps all three pages (brown/amber hero left 60 % desktop, white form panel, language switcher below divider).
- Components/BrandMark.vue — sparkle-broom SVG used in AuthShell top-left + AppLayout sidebar/mobile navbar, size via class fallthrough.
- AppLayout.logout() → router.post('/logout') (AppLayout.vue:284).

**DTOs:**
- LoginData (email, password, remember=false), PasswordResetLinkData, NewPasswordData, AuthTokenData {token, user: UserListItemData}.

**Flow:**
- GET /login → Auth/Login {canResetPassword}. POST /login (+Precognition) → LoginData → Auth::attempt → 302 dashboard | ValidationException on email __('app.invalid_credentials'). POST /logout → login. GET /forgot-password → Auth/ForgotPassword {status}; POST → back()->with('status'). GET /reset-password/{token} → Auth/ResetPassword {email, token}; POST /reset-password → Password::reset → login. POST /api/auth/login → JSON AuthTokenData; POST /api/auth/logout → 204.

**Depends on:** identity (User.is_active, hasActiveMembership), tenancy (RequireActiveTenant middleware, TenantContextMiddleware), audit-logs (listener), localisation (LocaleMiddleware reads user.locale).

**Depended on by:** every authenticated route (behind auth/auth:sanctum); RequireActiveTenant gates HTTP; HandleInertiaRequests (auth.user prop).

**If you change Core, check:**
- Login guards: AuthController.php login method (is_active + hasActiveMembership + ValidationException), Api/AuthController.php (same), tests/Feature/Auth/AuthControllerTest.php (guards), tests/Feature/Tenancy/RequireActiveTenantTest.php (mid-session loss).
- app/Data/AuthTokenData.php, AppServiceProvider.php listener registration, app/Listeners/LogAuthenticationActivity.php (ip → get_client_ip()), app/Support/helpers.php get_client_ip().
- FE login page consumes `canResetPassword` prop (remains auth-page-only, not shared).

**Keywords:** login, logout, sanctum, bearer token, password reset, forgot password, remember, guest, session regenerate, login_failed, is_active guard, hasActiveMembership, tenant-required.

### audit-logs — Activitylog viewer

**Core:**
- App\Models\Activity extends Spatie\Activitylog\Models\Activity (D2) — `final class`, booted() creating hook sets `tenant_id ??= app('current_tenant_id')` (nullable for login/logout before tenant bound). **Phase 11:** non-persisted `public bool $keepNullTenant` makes the hook skip the fill, so activity on global task rows (Task.tenant_id NULL) stays tenant-less even while a tenant is bound. Columns: morph subject/causer (uuid), batch_uuid (nullable), **tenant_id (uuid nullable, new)**, created_at, etc. Methods: `scopeVisibleInTenant(Builder, $tenantId): Builder` (tenant_id = ? OR tenant_id IS NULL AND causer is member of tenant); `isVisibleInTenant($tenantId): bool` (same predicate, single row). Config activitylog.php activity_model → `App\Models\Activity`, clean_after_days 365.
- No service — QueryBuilder inline in AuditLogController@index: filters search (description/log_name/causer name+email), subject_type, created_at (date); sorts -created_at, description, causer_name (leftJoin users as causer_user); per_page. Results via Activity::visibleInTenant(current_tenant_id).
- ActivityPolicy (viewAny|view → `can('view audit logs')`) gated per-record via `isVisibleInTenant` + record-level check. Registered explicitly in AuthServiceProvider::$policies.
- Writers: User/Role/TenantMembership LogsActivity (logOnlyDirty, dontLogEmptyChanges where applicable), LogAuthenticationActivity (3 events: login/logout/login_failed; tenant_id set iff authenticated user + bound tenant, else null).
- Morph columns uuid (nullableUuidMorphs subject/causer). Vendor Media (bigint id) never logged (gotcha 3).

**Satellites (FE):**
- Pages/AuditLogs/Index.vue — props {activities: Paginator<ActivityLogListItemData>; filters?: Record<string,unknown>; filterOptions?} · Filters subject_type (~), created_at (date), causer name/email search · DataTable · rows linked → /audit-logs/{id} · read-only (no mutations).
- Pages/AuditLogs/Show.vue — props {activity: ActivityLogDetailData} · renders attribute_changes/properties as <pre>.

**DTOs:**
- ActivityLogListItemData::fromModel(Activity) — id, description, log_name, causer_name/email (if exists), subject_type (class_basename), created_at, causedBy.
- ActivityLogDetailData::fromModel — extends ListItemData + properties (JSON), attribute_changes (old/new per $activity->changes()).
- Dead: ActivityLogIndexFilterData.

**Flow:**
- GET /audit-logs → AuditLogController@index (#[Authorize('view audit logs')]) → QueryBuilder::for(Activity::visibleInTenant(current_tenant_id)) + AllowedFilter filters → ->through(ActivityLogListItemData::fromModel) → Inertia render AuditLogs/Index {activities, filters}.
- GET /audit-logs/{activity} → AuditLogController@show (Activity model binding, #[Authorize('view audit logs')]) → ActivityPolicy::view (check visibleInTenant) → Inertia render AuditLogs/Show {activity: ActivityLogDetailData}.

**Depends on:** identity (causer = User or null), tenancy (tenant_id filtering, visibleInTenant scope).

**Depended on by:** nothing (sink). Shared can (via PermissionEnum::ViewAuditLogs → HandleInertiaRequests.php).

**If you change Core, check:**
- Any new LogsActivity model must have uuid PK — database/migrations/2026_04_30_123027_create_activity_log_table.php (nullableUuidMorphs); app/Providers/AuthServiceProvider.php; AuditLogController.php:22 NavItem.

**Keywords:** activity log, audit, causer, subject, LogsActivity, batch_uuid, login_failed, attribute_changes.

### media — MediaLibrary viewer + temporary uploads

**Core:**
- App\Models\Media extends Spatie\MediaLibrary\MediaCollections\Models\Media (D1-new) — `final class`, booted() creating hook: for `model_type` = Task it copies the owner's `tenant_id` (NULL for personal tasks) and throws RuntimeException('media.owner_missing') when the owner is not found; for other owners `tenant_id ??= app('current_tenant_id')` (throws RuntimeException('media.tenant_context_missing') when unbound). Columns: morph model_id (uuid), **tenant_id (uuid, NOT NULL except Task owners since migration 2026_11_02_000200; DB CHECK `media_tenant_required_unless_task`: NULL allowed only when model_type = App\Models\Task)** + index, disk public per config/media-library.php, 10 MB max. Methods: `scopeInTenant(Builder, $tenantId): Builder` (tenant_id = ?). Config media-library.php media_model → `App\Models\Media`.
- App\Models\TemporaryUpload — UUID PK, #[Fillable(['session_id','user_id','tenant_id'])], implements HasMedia, collection default; traits HasFactory, BelongsToTenant. FKs: user_id → users (nullOnDelete), **tenant_id → tenants (restrictOnDelete, new)**. TenantScope applied. user(): BelongsTo.
- App\Services\TemporaryUploadService (final readonly, DB::transaction): `store(UploadedFile, ?User, string $sessionId): Media` (posts to /uploads, current_tenant_id bound required) · `moveToModel(HasMedia $model, string $collection, string $uuid, ?User $user, string $sessionId): Media` (tenant + ownership checks; callers: QuoteService create/update, InvoiceSettingsService::update for Tenant `signature`) · `delete(string $uuid, ?User, string $sessionId): void` · `purgeOlderThan(int $hours=24): int` (runs unbound in console, sees all tenants intentionally).
- App\Services\MediaService (final readonly): `index(MediaIndexFilterData): LengthAwarePaginator<Media>` (QueryBuilder Media::inTenant) · `show(Media): MediaDetailData` (policy gate).
- App\Services\MediaUrlResolver::resolve(?string $modelType, string|int|null $modelId): array{label,url} — reads config/media-urls.php (no tenant_id reference today, consumers MediaListItemData pass resolved URL).

**Satellites (FE):**
- Pages/Media/Index.vue — {media: Paginator<MediaListItemData>; filters?} · DataTable · formatBytes.
- Pages/Media/Show.vue — {media: MediaDetailData} · image preview.
- FileUploadInput.vue (form component) — posts to /uploads (hard-coded :41) → 201 {uuid,name,file_name,mime_type,size,url}; DELETE /uploads/{uuid} → 204; v-model uuid|uuid[]. RichTextEditorInput reuses endpoint for inline images.

**DTOs:**
- MediaListItemData::fromModel (app(MediaUrlResolver::class)), MediaDetailData, MediaIndexFilterData (#[MapInputName('filter.*')]), StoreTemporaryUploadData (file|max:10240).

**Policies:**
- MediaPolicy (viewAny|view → `can('view media')`) — viewAny via enum, view(User, Media) via enum + `media->tenant_id === current_tenant_id`. Registered explicitly in AuthServiceProvider::$policies.
- TemporaryUploadPolicy (create|delete → `can('upload files')`) — class-level gate; ownership + tenant checks in service via OwnedTemporaryMedia rule (validates uuid + user_id + tenant_id match).
- App\Rules\OwnedTemporaryMedia (validates form field holds uuid of a TemporaryUpload owned by actor in current tenant).

**Commands:**
- App\Console\Commands\PurgeTemporaryUploadsCommand (app:purge-temporary-uploads {--hours=24}) daily in routes/console.php (runs unbound, purges all tenants).

**Flow:**
- POST /uploads (multipart file, inside tenant.required) → #[Authorize('upload files')] → TemporaryUploadService::store → 201 JSON {uuid,name,file_name,mime_type,size,url}. DELETE /uploads/{uuid} → 204. Consumer contract: form DTO holds uuid validated by OwnedTemporaryMedia → service calls moveToModel($owner,'collection',$uuid) (tenant + ownership checks inline).
- GET /media → MediaController@index (#[Authorize('view media')]) → QueryBuilder::for(Media::inTenant(current_tenant_id)) + AllowedFilter filters → Inertia render Media/Index {media: Paginator<MediaListItemData>, filters}.
- GET /media/{media} → MediaController@show (Model binding with tenant scope implied, #[Authorize('view media')]) → MediaPolicy::view (tenant_id check) → Inertia render Media/Show {media: MediaDetailData}.

**Depends on:** tenancy (Media.tenant_id, TemporaryUpload tenant FK + TenantScope, inTenant scope, Tenant now HasMedia owner), identity (user_id FKs), auth, AllowedFilter, config/media-urls.php.

**Media collections in use (verified registerMediaCollections/addMediaCollection grep, 2026-10-31):** TemporaryUpload `default` (public MEDIA_DISK, 24h purge) · Document `file` + `pdf` (both singleFile, disk config('documents.disk') = env DOCUMENTS_DISK, default local; replaces Quote `document`, Quote is no longer HasMedia) · Tenant `signature` (multi-row, config('invoicing.signature.disk')) · Invoice `efa_xml` (written by EInvoiceService::submit via addMediaFromString, disk = invoicing.signature.disk) · Task `attachments` (2026-11-01, multi-row, not singleFile, disk config('tasks.attachments.disk') = env DOCUMENTS_DISK; the Task owner implements HasMedia, see Cross-cutting MediaLibrary). Client / CleaningObject / TenantMembership / Contract / DocumentTemplate have NO media collection of their own: employee files, quote files and the signed-contract PDF snapshot all hang off a `Document` (morph `documentable`). Invoice PDFs and unsigned-contract / quote PDFs are NOT stored (rendered per request). MediaService::index and MediaPolicy::view exclude `model_type` Document and Task (documents alias to FQCN; ID scans must not show up for holders of `view media`; Task media would bypass `Task::isVisibleTo`), so quote files and task attachments also left this viewer.

**Depended on by:** documents (Document `file`/`pdf` collections; EmployeeDocumentService streams them), Quote file upload (moveToModel onto the Quote's Document), Tenant signature (Invoice signature snapshots reference media id), Invoice efa_xml, FileUploadInput (FE form component), task attachments (Task `attachments`, written directly by TaskAttachmentService, no staging), any future stored attachments (use moveToModel + OwnedTemporaryMedia + private DOCUMENTS_DISK).

**If you change Core, check:**
- config/media-urls.php: **User::class => users.edit (param user), Tenant::class => ['name' => 'settings.invoicing']** (no 'param'; MediaUrlResolver builds `route($name, $params)` with optional param). Quote and Invoice owners are NOT mapped (media viewer shows no owner link for them) — add an entry when a new media owner (e.g. Contract) is introduced.
- TemporaryUploadService: store (app('current_tenant_id') required), moveToModel (OwnedTemporaryMedia validation, tenant + ownership checks), purgeOlderThan (unbound console, all tenants).
- OwnedTemporaryMedia rule: validates uuid + user_id + tenant_id match current context.
- MediaPolicy: tenant_id === current_tenant_id guard on view.
- Tenant::registerMediaCollections(): adds 'signature' collection with config('invoicing.signature.disk') (NOT singleFile — superseded rows retained).
- FileUploadInput.vue (FE): posts to /uploads (hard-coded, inside tenant.required group), deletes to /uploads/{uuid}.
- Media::creating Task branch (owner lookup, `media.owner_missing`) and migration 2026_11_02_000200 CHECK: a personal task's file must stay tenant NULL. Tests: tests/Feature/Tasks/TaskAttachmentApiTest.php, tests/Feature/Media/MediaControllerTest.php.

**Keywords:** media, upload, temporary upload, HasMedia (Spatie), collection, moveToModel, OwnedTemporaryMedia, purge, tenant_id scope, media-urls, FileUploadInput, task attachment, media_tenant_required_unless_task, media.owner_missing.

### api-me — Sanctum API self-capability endpoint

**Core:**
- App\Http\Controllers\Api\MeController (invokable, `GET /api/me`, Sanctum auth) — #[Group('Auth')], #[Authenticated], #[Endpoint('Me', ...)], #[ResponseFromSpatieData(MeData::class, User::class)]. Returns current user's active tenant + permissions.
- App\Data\Auth\MeData (#[TypeScript]) — **Phase 11:** userId, name, email, locale (SupportedLanguage), activeTenantId: string (non-null: route behind tenant.required), permissions: PermissionEnum[] (derived from $user->getAllPermissions() filtered to active tenant team scope via PermissionRegistrar::setPermissionsTeamId before query; FE converts to union for type-safe checks), companies: list<MobileCompanyData> (tenant_id, name, color, mobile_access, permissions per company, built from `TenantPermissionSnapshot::for($user)` in MeController).
- Middleware: `/api` group order `auth:sanctum` → `locale.api` → `user.active` → `ability:mobile` → `tenant.context` → `tenant.required` (routes/api.php; Phase 11 — previously `['auth:sanctum', 'tenant.context', 'tenant.required']`).
- No policy gate (returns caller's own capabilities, documented in controller).

**Flow:**
- GET /api/me (Sanctum Bearer) — resolves user via Sanctum, TenantContextMiddleware (D5: X-Tenant-Id header precedence → session → first active membership), setPermissionsTeamId(resolved), getAllPermissions()->pluck('name') → map to PermissionEnum cases, return MeData. On foreign tenant header → 403 `tenant_forbidden`; on zero memberships → 403 `no_active_tenant`.

**Depends on:** tenancy (active tenant resolution), identity (user permissions per team).

**Depended on by:** mobile app (repo `cleaning-software-app`; consumer of MeData.companies and permissions — consumer code not verified in this repo).

**Keywords:** api, sanctum, bearer, me, capabilities, tenant context, permissions per team.

### tasks — firm and global tasks, assignment, reminders, attachments (Phase 11, 2026-11-01; attachments on feat/task-attachments)

**Core:**
- App\Models\Task — UUIDv7 (HasUuids), LogsActivity, SoftDeletes, `#[ScopedBy(TaskScope::class)]`, **HasMedia** (collection `attachments`, see the Attachments bullet). **Not** BelongsToTenant: `tenant_id` nullable; NULL = global task owned by `created_by_user_id`. Columns (migration 2026_11_01_000200): tenant_id FK nullable → tenants (restrictOnDelete), created_by_user_id FK → users (restrictOnDelete), assigned_membership_id FK nullable → tenant_memberships (restrictOnDelete), status (TaskStatusEnum open/done/cancelled, default open), priority, due_at, remind_at, reminded_at, completed_at, source (TaskSourceEnum manual/voice/assistant/mcp), taskable_type + taskable_id (polymorphic, no FK). Indexes (tenant_id, status, due_at), (tenant_id, assigned_membership_id), (taskable_type, taskable_id).
- App\Scopes\TaskScope (fail-closed): bound tenant's rows OR the authenticated user's own global rows (`tenant_id IS NULL AND created_by_user_id = auth id`); neither bound → `1 = 0`.
- `Task::unscopedForSystem()` — the single scope bypass (`withoutGlobalScope(TaskScope::class)`). Callers: `TaskService::findForSystem`, `TaskService::sendReminder`, `RemindTasks`. Listeners and the confirm path load via `findForSystem`.
- Task::scopeVisibleTo(User) / isVisibleTo(User): global rows only for their creator; firm rows need `view tasks`; `view all tasks` widens to every firm row; otherwise created-by-me or assigned to my active membership. `isGlobal()` = `tenant_id === null`.
- App\Services\TaskService (final) — paginate, listAcrossCompanies, formContext, detail, assertMayCreate, create, update, complete, reopen, assign, delete, findForSystem, unassignForMembership(membership, onlyOpen = true), sendReminder.
- App\Policies\TaskPolicy — viewAny, viewAnyAcrossCompanies, view, create(?tenantId), assignIn, update, delete (second layer for global ownership), deleteAttachment(User, Task, Media) + mayDeleteAttachment (global: creator only; firm: task visible AND (uploader OR `delete tasks`)). Adding attachments uses `update`; listing and download use `view`.
- Enums: TaskStatusEnum, TaskPriorityEnum (low/normal/high), TaskSourceEnum (manual/voice/assistant/mcp).
- Events: TaskAssigned, TaskDue (both `ShouldDispatchAfterCommit`). Listeners NotifyTaskAssigned / NotifyTaskDue → TaskAssignedNotification / TaskDueNotification (extend TaskNotification → BaseTenantNotification, queued).
- **Attachments (2026-11-01, uncommitted):** App\Services\TaskAttachmentService (final readonly): `list(Task, User, string $downloadRoute): TaskAttachmentListData` (oldest first, capped at `max_count`, not paginated; `can_add` = `update` policy); `add(Task, UploadedFile, User): Media` (DB transaction, `lockForUpdate` on the task row, count check → 422 on `file`, `usingName(cleanName)`, `usingFileName('{uuid}.{ext}')`, custom properties `uploaded_by_user_id` + `uploaded_by_name`, canonical `mime_type` saved after the Media Library write); `find(Task, uuid)` (`firstOrFail` on the task's `attachments` collection); `delete(Task, Media, User)` (`Gate::forUser` `deleteAttachment`, hard delete in a transaction, audit); `stream(Media)` (inline for jpeg/png/webp/pdf, `nosniff`, `Cache-Control: private, no-store`); `static cleanName(string)` (path tail, invisible characters, Cc, trim Z, 120 code points keeping a 2–11 character extension). App\Rules\TaskAttachmentFile: `static resolve(UploadedFile): ?array{mime, extension}` is the only owner of the type table (config/tasks.php `attachments.types`, zip and OLE fallbacks by client extension); `detectedMimes()` feeds `Task::registerMediaCollections` `acceptsMimeTypes`. Collection not singleFile, disk `config('tasks.attachments.disk')`.

**Satellites:**
- DTOs in: TaskUpsertData, TaskAssignData, TaskParseData. Out: TaskListItemData, TaskDetailData, TaskFormContextData, TaskDraftData, TaskCanData, TaskSubjectData, TaskMemberOptionData. Attachments: out TaskAttachmentData {id (media uuid), name, mime_type, size, previewable, uploaded_by_name, created_at, download_url, can_delete}, TaskAttachmentListData {attachments, can_add, constraints}, TaskAttachmentConstraintsData (`fromConfig()`); in TaskAttachmentUploadData (`file`, messages map to `app.task_attachment_too_large`). `TaskDetailData` gains `attachments_count: int`, passed in as an argument.
- Command: App\Console\Commands\RemindTasks (`app:remind-tasks`, hourly, withoutOverlapping): open tasks with `remind_at <= now` and `reminded_at IS NULL` → `sendReminder` (claims the row with `UPDATE ... reminded_at`, dispatches TaskDue only when the claim succeeds).
- Controllers: web TaskController (`/tasks`); API Api\TaskController + Api\TaskParseController (`/api/tasks`, see assistant); attachments: web App\Http\Controllers\TaskAttachmentController (redirects) and Api\TaskAttachmentController (JSON, Scribe `#[ResponseFromSpatieData]` on list and upload).
- MCP tools: list_tasks, get_task, create_task, update_task, complete_task, assign_task, delete_global_task (excluded from the assistant toolbox).
- FE attachments (web, Inertia): Pages/Tasks/Show.vue (prop `attachments: TaskAttachmentListData`; `useTaskAttachmentUpload`; disables Complete, Reopen, Edit and Delete while uploading; `router.on('before')` asks before leaving during an upload), Components/Tasks/TaskAttachmentsCard.vue (queue rows, image grid, file rows, delete via `useDeleteConfirm`), TaskAttachmentPreviewModal.vue (`<dialog>`), Components/Forms/FilePickerButton.vue, Composables/useTaskAttachmentUpload.ts (one `router.post` per file, `forceFormData`, `only: ['attachments']`, `preserveState`), utils/taskAttachments.ts (accept list, rejection, camera names). The mobile client is in repo `cleaning-software-app`, not in this repo.

**Flow:**
- Create: TaskUpsertData → TaskPolicy::create (tenant or global) → TaskService::create (created_by = actor; `assigned_membership_id` prohibited when tenant_id null — DTO rule) → LogsActivity.
- Assign: TaskAssignData → membership must belong to the task's tenant → TaskService::assign → TaskAssigned (after commit) unless the actor assigns to self.
- Reminder: `app:remind-tasks` → TaskService::sendReminder → TaskDue (after commit) → NotifyTaskDue → TaskDueNotification.
- Membership removal: EmployeeService (deactivate) and UserService (`unassignForMembership(onlyOpen: true)` on deactivate, `onlyOpen: false` on delete) release assignments so the restrict FK does not block.
- Routes web: GET /tasks, GET|DELETE /tasks/{task}, POST /tasks/{task}/complete|reopen; with the write permission group: POST /tasks, PUT|PATCH /tasks/{task}, POST /tasks/{task}/assign; attachments: GET|POST /tasks/{task}/attachments (POST in the `task-attachments` throttle), GET|DELETE /tasks/{task}/attachments/{attachment}. API under `/api` (ability:mobile): GET|POST /tasks, GET /tasks/form-context, GET|PUT|PATCH|DELETE /tasks/{task}, POST /tasks/{task}/complete|reopen|assign, POST /tasks/parse, GET|POST /tasks/{task}/attachments, GET|DELETE /tasks/{task}/attachments/{attachment} (`{attachment}` = media uuid).

**Attachment flow:** POST → `TaskAttachmentUploadData` (`file`: required, file, max 10240 kB, `TaskAttachmentFile`) → controller `#[Authorize('update', 'task')]` → `TaskAttachmentService::add` (row lock, count ≤ 20, store) → `Media::creating` takes the owner's tenant_id → audit `attachment_added`. DELETE → `#[Authorize('view', 'task')]` → `find` (404 for another task or a soft-deleted task, since the binding excludes trashed tasks) → `deleteAttachment` → hard delete → audit `attachment_deleted`. Task soft delete keeps the files on disk; no purge job.

**Depends on:** identity (`view|create|edit|delete tasks`, `view all tasks`), tenancy (tenant_id NOT scoped by BelongsToTenant; memberships FK), notifications (TaskAssigned/TaskDue channels), audit (LogsActivity; Activity keepNullTenant), pending-actions (`delete_task` prepare; global tasks refused by ActionConfirmationService::describe), media (Task `attachments`: tenant NULL rule, DOCUMENTS_DISK), rate limiter `task-attachments` (AppServiceProvider, 60/min per user).

**Depended on by:** EmployeeService, UserService (unassign), ActionConfirmationService (`delete_task` via findForSystem), NotifyTaskAssigned, NotifyTaskDue, RemindTasks, MCP task tools, AssistantTurn (write map for create/update/complete/assign), TaskService::detail (sole caller of `TaskDetailData::fromModel`; web, API and MCP `get_task` responses), TaskController::show (`attachments` prop).

**If you change Core, check:**
- `Task::unscopedForSystem()` callers: TaskService (findForSystem, sendReminder), RemindTasks, DeleteGlobalTaskTool via findForSystem. Any new bypass is a review finding.
- TaskScope / scopeVisibleTo / isVisibleTo: tests/Unit/Scopes/TaskScopeTest.php, tests/Feature/Tasks/TaskScopingTest.php, tests/Feature/Tasks/GlobalTaskTest.php.
- TaskSourceEnum consumers: CreateTaskTool (`mcp`), assistant path (`assistant`), TaskParseService.
- AssistantTurn::WRITES keys (create_task, update_task, complete_task, assign_task) — tool names must match.
- TaskStatusEnum / `status` values: TaskService transitions, ListTasksTool filters, FE TaskStatusBadge.
- `TaskDetailData::fromModel($task, $can, $attachmentsCount)`: sole caller TaskService::detail, which counts the `attachments` collection. Never `loadCount`: a loaded `*_count` attribute changes the `getAttributes()` fingerprint and cancels a pending `delete_task`.
- TaskAttachmentService / TaskAttachmentFile / config/tasks.php: callers are the web and API attachment controllers only. Media hook and migration CHECK: see the media domain. Tests: tests/Feature/Tasks/TaskAttachmentApiTest.php, tests/Feature/Tasks/TaskAttachmentWebTest.php, tests/Unit/Services/TaskAttachmentNameTest.php, tests/Feature/Mcp/McpToolsReadTest.php (only `attachments_count`), tests/Feature/Mcp/ActionConfirmationTest.php (count does not cancel `delete_task`).

**Keywords (SK):** úloha, task, globálna úloha, global task, priorita, pripomienka, reminder, remind_at, assign, priradiť, TaskScope, unscopedForSystem, created_by_user_id, view all tasks, príloha, attachment, attachments_count, TaskAttachmentService, cleanName.

### expenses — expense ledger, receipts, bank CSV import, AI proposals (Phase 11, 2026-11-01)

**Core:**
- App\Models\Expense — UUIDv7, BelongsToTenant (tenant_id NOT NULL, restrictOnDelete), InteractsWithMedia (receipt), LogsActivity, SoftDeletes. Columns (migration 2026_11_01_000500): created_by_user_id FK → users (restrictOnDelete), status (ExpenseStatusEnum draft/confirmed, default draft), category (ExpenseCategoryEnum, 12 values), source (ExpenseSourceEnum manual/receipt/bank_csv/mcp), date, amount, currency, supplier_name, external_hash (partial unique per tenant `WHERE external_hash IS NOT NULL AND deleted_at IS NULL` — CSV dedupe).
- App\Services\ExpenseService (final) — paginate, listAcrossCompanies, formContext, detail, assertMayCreate, create/update (take sessionId for receipt staging), confirm (draft → confirmed, ExpenseConfirmData), delete, attachReceipt, analyze, analysisContext. `analyze` returns an ExpenseDraftData proposal and persists nothing.
- App\Services\ExpenseImportService — preview(UploadedFile) and import(BankCsvImportData). Row cap 100 (BankCsvImportData `Max(100)`); preview rows capped by `ai.csv_preview_max_rows` (100); AI categorisation in batches of `ai.categorize_batch` (50). Mapping per tenant in `tenant_interfaces.bank_csv_mapping` (migration 2026_11_01_000600).
- App\Services\Expenses\BankStatementParser (+ ParsedStatement, StatementExtraction, StatementRow) — CSV parsing.
- AI port: `AnalyzesExpenses` contract → `LaravelAiExpenseAnalyzer` (bound in AppServiceProvider).
- App\Policies\ExpensePolicy — `view expenses` / `manage expenses`.
- Enums: ExpenseStatusEnum, ExpenseCategoryEnum, ExpenseSourceEnum.

**Satellites:**
- DTOs in: ExpenseUpsertData, ExpenseConfirmData, ExpenseAnalyzeData, ExpenseReceiptUploadData (mime list from `config/expenses.php` `receipt.allowed_mimes`, max 10240 KB default), BankCsvImportData, BankCsvMappingData, ExpenseImportUploadData. Out: ExpenseListItemData, ExpenseDetailData, ExpenseFormContextData, ExpenseDraftData, ExpenseProposalData, ExpenseCategoryProposalData, ExpenseImportPreviewData, ExpenseImportRowData, ExpenseImportResultData, ExpenseRowData.
- Controllers: web ExpenseController (`/expenses`, import page, receipt, analyze, import preview/store); API Api\ExpenseController (`/api/expenses`).
- Storage: receipts are Media on `config('expenses.disk')` = env `DOCUMENTS_DISK` (default local).
- Throttle `ai-analyze` (20/min) on analyze and import preview (both web and API).
- MCP tools: list_expenses, create_expense, update_expense. Delete is prepare-only (`delete_expense` via pending-actions).

**Flow:**
- Receipt/text analyze: ExpenseAnalyzeData → AnalyzesExpenses (sync AI call, see Cross-cutting "Sync AI calls") → ExpenseDraftData proposal → user saves via store (draft) → confirm → confirmed.
- Bank CSV: preview (AI-categorised rows, ≤ 100) → import (`external_hash` dedupe, existing hashes skipped) → ExpenseImportResultData.
- MCP/assistant creates expenses with `source = mcp` (CreateExpenseTool) and drafts stay `draft` until confirmed.

**Depends on:** tenancy (BelongsToTenant), identity (`view|manage expenses`), media (receipt storage), AI (AnalyzesExpenses binding, `ai.*` config), audit (LogsActivity).

**Depended on by:** Api\ExpenseController, ExpenseController (web), CreateExpenseTool / UpdateExpenseTool / ListExpensesTool, ActionConfirmationService (`delete_expense`), AssistantTurn (create/update expense writes).

**If you change Core, check:**
- ExpenseStatusEnum / ExpenseSourceEnum consumers: ExpenseService::confirm, ExpenseImportService, ExpenseDetailData, FE ExpenseStatusBadge.
- external_hash partial unique index (migration 2026_11_01_000500): ExpenseImportService dedupe, tests/Feature/Expenses/BankCsvImportTest.php.
- ExpenseReceiptUploadData mime/size list ↔ config/expenses.php ↔ FE FileInput (`resources/js/Components/Forms/FileInput.vue`).

**Keywords (SK):** výdavok, expense, účtenka, receipt, bankový výpis, CSV import, kategória, draft, confirm, potvrdiť, external_hash, AI analýza, Expense ledger.

### pending-actions — two-step confirmation of irreversible actions (Phase 11, 2026-11-01)

**Core:**
- App\Models\PendingAction — UUIDv7, tenant_id FK (restrictOnDelete), user_id FK → users (restrictOnDelete), subject morph (subject_type + uuid subject_id, no FK), action (PendingActionEnum), payload (jsonb), payload_hash, token_hash (char 64, unique, nullable), subject_hash, preview, subject_label, customer, total, email, channel (ActionChannelEnum mcp/assistant), status (PendingActionStatusEnum pending/executed/cancelled/expired), expires_at, executed_at, confirmed_via (ConfirmationChannelEnum chat/app). Indexes (user_id, status, expires_at), (subject_type, subject_id).
- App\Services\Ai\ActionConfirmationService (final readonly):
  - `prepare(User, tenantId, PendingActionEnum, Model $subject, payload, ActionChannelEnum)` → PendingActionPreparedData. Checks subject class + tenant, `Gate::forUser($user)->authorize($action->ability(), $subject)` inside ToolContext, preconditions (cancellable / editable / signable), describes the action, stores payload_hash + subject_hash. Token (random 40 chars, sha256 stored) only for channel `mcp`.
  - `resolveToken(User, token)` — sha256 lookup; must belong to the user, be pending and unexpired.
  - `execute(User, PendingAction, ConfirmationChannelEnum)` — transaction + `lockForUpdate`; assertExecutable (owner, pending, not expired, channel in allowed list, mobile token needs `access mobile app`); subject re-resolved (Task via findForSystem, tenant-checked); subject fingerprint mismatch or deleted subject → `PendingActionSubjectChangedException`, row cancelled; Gate re-authorised; payload_hash mismatch → 422 `status`; then status Executed + perform().
  - `cancel(User, PendingAction)`, `pendingFor(User, ?tenantIds)`, `expireStale()`.
- App\Enums\PendingActionEnum (9 cases): issue_invoice, send_invoice, send_quote, sign_contract, cancel_invoice, delete_invoice_draft, delete_quote, delete_task, delete_expense. Methods: subjectClass(), ability() (issue / send / sign / cancel / delete).
- Config `config/ai.php`:
  - `confirm_channels` per action: issue_invoice, send_invoice, send_quote = chat + app; sign_contract, cancel_invoice, delete_invoice_draft, delete_quote, delete_task, delete_expense = app only. Missing key → app only.
  - Rows prepared on the assistant channel are app-only regardless of config (PendingAction::allowedConfirmationChannels).
  - `pending_action_ttl_minutes` = 10 (row expiry). Not the MCP token lifetime (180 days, auth auth-domain).
- Policy: App\Policies\PendingActionPolicy — viewAny, view, confirm, cancel (owner-scoped).

**Satellites:**
- Exception: App\Exceptions\PendingActionSubjectChangedException.
- DTOs: PendingActionData (out; `confirm_in_app_only` flag), PendingActionPreparedData (MCP only, see Gotchas), ActionResultData (out after confirm).
- Controllers: web PendingActionController (`/pending-actions`, POST confirm/cancel, execute with `ConfirmationChannelEnum::App`); API Api\PendingActionController (`/api/pending-actions`, same).
- MCP: `prepare_action` (PrepareActionTool) returns the preview + one-time token; `confirm_action` (ConfirmActionTool) executes with `ConfirmationChannelEnum::Chat`.
- Command: `cleanup:pending-actions` (ExpirePendingActions, hourly) → `expireStale()`.
- Audit: each prepare / confirm / cancel / error writes a ToolAudit record (`ToolAudit::record`).

**Flow:**
1. Caller (MCP client or assistant) calls `prepare_action` → ActionConfirmationService::prepare → PendingAction (pending, preview with the company name, TTL 10 min). Assistant receives the row as `pending_action`.
2. Confirmation: MCP `confirm_action` with token (chat allowed only for issue/send actions); or app via web/API POST confirm (app channel, all actions).
3. Execution runs inside a transaction and re-checks fingerprint, payload hash, owner, state and permission. Stale subject cancels the row.

**Depends on:** tenancy (ToolContext binds tenant + team), identity (`access mobile app`, per-action Policy abilities), invoices / quotes / contracts / tasks / expenses services (perform()), audit (ToolAudit).

**Depended on by:** PrepareActionTool, ConfirmActionTool, PendingActionController (web + API), AssistantTurn (pending id), ExpirePendingActions, PendingActionData (FE Components/Ai/PendingActionRow.vue, Pages/PendingActions/Index.vue).

**If you change Core, check:**
- PendingActionEnum::subjectClass() / ability() ↔ Policies (InvoicePolicy, QuotePolicy, ContractPolicy, TaskPolicy, ExpensePolicy) — a missing ability is an authorisation bug at prepare and at execute.
- ActionConfirmationService::perform() match arms ↔ PendingActionEnum cases (LogicException on mismatch).
- `config/ai.php` confirm_channels keys ↔ PendingActionEnum values.
- fingerprint(): attributes + items (+ quote client name) + text document of contracts and quotes (decrypted body, title, frame flags, uploaded file uuid). Adding a column to Invoice/Quote/Contract changes the fingerprint automatically — check that existing pending rows are not expected to survive a deploy.
- tests/Feature/Mcp/ActionConfirmationTest.php (hash, expiry, channel, subject change).

**Keywords (SK):** potvrdenie, confirm_action, prepare_action, dvojkrok, two-step, pending action, token, expirácia, odoslať faktúru, vystaviť faktúru, storno, zmazať.

### push — device tokens and FCM notifications (Phase 11, 2026-11-01)

**Core:**
- App\Models\DeviceToken — UUIDv7, **per person**: user_id FK → users (restrictOnDelete); no tenant_id. personal_access_token_id FK nullable → personal_access_tokens (cascadeOnDelete: revoking the Sanctum token drops the device row). token string(255) unique, platform (DevicePlatformEnum android/ios), app_version (32, nullable), last_seen_at. Migration 2026_11_01_000400.
- App\Services\DeviceTokenService — register(User, DeviceRegisterData, ?patId), unregister(User, token), removeAllFor(User), removeAllIfNoActiveMembership(User), removeTokens(array), hasTokens(User), tokensFor(User).
- App\Notifications\Channels\FcmChannel — invoked from BaseTenantNotification::via when `pushEnabledFor(user)`. Sends only if the user has tokens AND `TenantPermissionSnapshot::hasMobileAccess($user, notification tenant)`. Builds PushMessage (title, body, route, tenantId, notificationId, badge from NotificationService::badgeFor). Exceptions are swallowed on purpose (a retry after partial multicast failure would re-send).
- App\Contracts\SendsPush → App\Services\Push\FirebasePushSender (AppServiceProvider binding). Multicast; returns unusable tokens (SenderIdMismatch deliberately excluded — wrong project credentials must not wipe tokens). **Skips quietly** with log `push.skipped` when `config('firebase.projects.{default}.credentials')` is empty.
- App\Services\Push\PushRoute — allowlist of mobile routes: `/tasks|invoices|quotes|expenses/{uuid}` and `/pending-actions`; anything else → `/notifications`.

**Satellites:**
- DTOs: DeviceRegisterData (token ≤ 255, app_version ≤ 32), DeviceUnregisterData, PushMessage, PushRoute (static).
- Controller: Api\DeviceController — POST /api/devices and DELETE /api/devices, inside `/api` (ability:mobile) and `throttle:devices` (10/min per user).
- Config: config/firebase.php (default project env `FIREBASE_PROJECT`, default `app`; credentials env `FIREBASE_CREDENTIALS`).
- Mobile side (not in this repo): FCM token on both platforms via `@capacitor-firebase/messaging` — TODO verify in `cleaning-software-app`.

**Flow:**
- Register: mobile app → POST /api/devices (token, platform, app_version) → DeviceTokenService::register, linked to the current Sanctum token.
- Send: domain event → NotifyX listener → notification queued (BaseTenantNotification, Tries 3) → channels: TenantDatabaseChannel, optional mail, FcmChannel → FirebasePushSender → invalid tokens removed.
- Cleanup: EmployeeService (deactivate) and UserService (membership removed) call `removeAllIfNoActiveMembership(user)`; logout / token revocation cascades the row.

**Depends on:** notifications (BaseTenantNotification, NotificationService::badgeFor), tenancy (TenantPermissionSnapshot::hasMobileAccess), auth (personal_access_tokens FK), config firebase (credentials), kreait/laravel-firebase.

**Depended on by:** FcmChannel, EmployeeService, UserService, Api\DeviceController.

**If you change Core, check:**
- PushRoute pattern ↔ mobile router paths (cross-repo; changing a route breaks deep links from pushes).
- DeviceToken per-person rule ↔ Rule 6 exception (Cross-cutting "Global task and per-person exceptions").
- tests/Feature/Push/{DeviceRegistrationTest,DeviceCleanupTest,PushDeliveryTest,FirebasePushSenderTest}.php.

**Keywords (SK):** push, FCM, device token, zariadenie, notifikácia, APNs, Firebase, PushRoute, deep link, badge, skipped.

### assistant — in-app AI assistant over the MCP tool layer (Phase 11, 2026-11-01)

**Core:**
- POST /api/assistant/messages (Api\AssistantController, `throttle:assistant` 30/min per user). Gate `actIn` on `Tenant` with `company_id` (403 when the token has no mobile access in that company). AssistantRequestData: company_id, messages (1..20, each ≤ 4000 chars, total ≤ 12000; last one from user).
- App\Services\AssistantService::reply → `ChatsWithTools` contract → App\Services\Ai\LaravelAiAssistant (AppServiceProvider binding). AiUnavailableException → 422 `ai`.
- LaravelAiAssistant: builds App\Ai\Agents\AssistantAgent (`#[MaxSteps(6)]` + `maxSteps()` from `ai.assistant.max_steps`; `#[MaxTokens(2048)]`), toolbox from App\Ai\Tools\AssistantToolbox::make(turn), history normalised (must start with user, roles alternate; client-sent "assistant" messages are not trusted as proof of effects), system instructions with the company list from `TenantPermissionSnapshot::forRequest`.
- App\Services\Ai\AssistantTurn — 45 s turn budget (`ai.assistant.turn_seconds`); records write effects (created/updated ids on allowlisted paths) and pending action ids in call order (first returned as `pending_action`; more than one → extra "open pending actions" link to /pending-actions).
- App\Ai\Tools\McpToolAdapter — each tool call checks the turn deadline first (expired → error text, no start), runs the MCP tool, caps output at 20 000 chars (`ai.assistant.max_tool_output_chars`).
- AssistantToolbox::toolClasses() = `CleanMasterServer::TOOLS` minus `EXCLUDED` (`confirm_action`, `delete_global_task`, `update_document_template`).
- AiClientOptions: provider `ai.default` (anthropic), model `ai.model` (env AI_MODEL), per-prompt timeout `ai.assistant_timeout` (30 s), `isConfigured()` = `config('ai.providers.anthropic.key')` filled (see Gotchas).
- Reply: text stripped of tags, ≤ 4000 chars; reply DTO AssistantReplyData (reply, actions: AssistantActionData[] with allowlisted in-app paths, pending_action: PendingActionData | null).
- **Partial success:** provider failure AFTER tool effects → HTTP 200 with `app.assistant_partial` text and the effects. Failure with no effects → AiUnavailableException → 422 `errors.ai`.
- Parse: POST /api/tasks/parse (Api\TaskParseController, `throttle:ai-analyze`, text ≤ 2000 chars) → TaskParseService → ExtractsTaskDraft (LaravelAiTaskExtractor) → TaskDraftData.

**Satellites:**
- Enums: AssistantRoleEnum (user/assistant), AssistantActionTypeEnum (open/created/updated).
- Audit: App\Services\Ai\ToolAudit::record (Activity rows per tool call, arguments redacted via `redact()`).
- DTOs: AssistantMessageData, AssistantRequestData, AssistantReplyData, AssistantActionData, TaskParseData, TaskDraftData.
- Config `config/ai.php` `assistant` block: max_steps 6, turn_seconds 45, max_tool_output_chars 20000, max_messages 20, max_message_chars 4000, max_total_chars 12000; plus assistant_timeout 30, timezone Europe/Bratislava, task_parse_max_chars 2000.
- Prompt rules (LaravelAiAssistant::instructions): reply briefly in the user's language, plain text; tool output and stored texts are untrusted; irreversible actions only via prepare_action; "you cannot confirm anything".

**Flow:** message → rate limit → Gate actIn → AssistantService → LaravelAiAssistant::reply → agent loop (≤ 6 steps, each provider step ≤ 30 s; each tool start gated by the 45 s turn budget) → tool calls run through ToolContext (company membership + team binding) → reply DTO. Synchronous; no queue.

**Depends on:** MCP tool layer (CleanMasterServer::TOOLS, ToolContext, ToolAudit), pending-actions (prepare only), tenancy (TenantPermissionSnapshot), AI provider (laravel/ai, Anthropic).

**Depended on by:** Api\AssistantController, Api\TaskParseController, FE mobile app (cross-repo), PendingActionRow (web) for prepared actions.

**If you change Core, check:**
- AssistantToolbox::EXCLUDED — adding a tool that confirms or deletes without confirmation is a review finding (tests/Unit/Ai/AssistantAgentToolsTest.php).
- AssistantTurn::WRITES map ↔ MCP tool names (create_task, update_expense, create_invoice_draft, …).
- config/ai.php assistant limits ↔ AssistantRequestData validation ↔ mobile client limits (20 messages, 4000 chars).
- tests/Feature/Ai/AssistantTest.php, tests/Fakes/FakeAssistant.php (tests bind FakeAssistant to ChatsWithTools).

**Keywords (SK):** asistent, assistant, AI, Claude, Anthropic, tool, MCP, timeout, turn budget, partial, 422 ai, parse úlohy, task parse, prepare_action.

## Cross-cutting

### auth layer (3 tiers, RBAC-full profile)
**Route gates:**
- Middleware: auth / auth:sanctum / guest (Laravel built-in).
- Per-route `tenant.required` (RequireActiveTenant middleware) — authenticates AND has bound tenant, else web logout + redirect / API 403. Routes: all app/* except logout (logout reachable to clear session).
- Per-route `permission:*` (PermissionMiddleware) — used ONLY for /docs* non-model route (config/scribe.php, routes/web.php:37).

**Per-action:**
- #[Authorize($ability, $modelOrParam)] attribute on every controller method (no route-level gates except permission: middleware). Ability = PermissionEnum::*->value (e.g., 'create clients', 'view audit logs'). Model = route-bound model or null (gate on PermissionEnum only, no model).
- Policies: AuthServiceProvider::$policies maps model → Policy. Every policy method (viewAny, view, create, update, delete) calls $user->can(PermissionEnum::*->value) [+ per-record tenant_id check where applicable]. Policies never use $user->hasRole (CLAUDE.md mandate: capabilities only, no role checks in UI/BE).

**Shared Inertia can map:**
- HandleInertiaRequests::share builds can: `collect(PermissionEnum::cases())->mapWithKeys(fn ($p) => [$p->sharedKey() => $user->can($p->value)])` (derived from the 56-case enum, not hand-maintained list).
- FE: useAuthorization().allows(PermissionEnum) → reads page.props.can[permissionKey(value)] === true (permissionKey mirrors Str::camel).
- <Can permission="create clients"> guard with optional #fallback slot.
- **Documented exception to the single permission axis:** `users.is_platform_admin` (boolean column, not in #[Fillable], never shared as a prop) gates global document templates (DocumentTemplatePolicy) and `POST /tenants` (TenantPolicy::create). Reason: a firm Admin role cannot gate cross-firm data, and `POST /tenants` would otherwise let any logged-in user create a firm and become its Admin. The shared prop `canCreateTenant` (= `can('create', Tenant::class)`) is the only FE exposure.
- Per-tenant team scope: Spatie Permission teams=true, team_foreign_key=tenant_id. Every role/permission check calls setPermissionsTeamId(current_tenant_id) first (TenantContextMiddleware, services, tests).

### Cross-tenant multi-read pattern (Dashboard only, Phase 9)
Dashboard is the ONLY cross-tenant read surface (all companies user is a member of, filtered by permission in each tenant). Pattern:
- **Permission snapshot (ADR-D3):** DashboardService::visibilityFor(User) loops through user's active memberships, calls `setPermissionsTeamId(tenantId)` + `$user->unsetRelation('roles')->unsetRelation('permissions')` per tenant to capture current permissions in each company (no relation cache carryover). Finally block restores original team_id + cache clear (critical: HandleInertiaRequests shared props evaluators run after, must read correct active-tenant permissions).
- **Query pattern:** DashboardMetricsQuery + DashboardAlertsQuery never touch TenantScope global scope; they receive pre-filtered id lists from visibility snapshots. Queries: `Model::query()->withoutGlobalScope(TenantScope::class)->whereIn('tenant_id', $ids)->...` (never `withoutGlobalScopes()`; keeps SoftDeletes). Result: row-level isolation preserved (no query can see data outside allowed tenant set), multi-tenant aggregation happens at query level (Postgres FILTER syntax for grouped counts).
- **No user param in queries:** Metrics/alerts queries are stateless aggregators receiving company ids only (no User passed). Consequence: system actions (cron, queued jobs) call services without User; user actions pass actor for row filtering. Dashboard service owns authorization; queries own aggregation.

### Activitylog reach
User, Role (logOnlyDirty, dontLogEmptyChanges), LogAuthenticationActivity (3 events). Morph columns uuid. Phase 3+: enabled on business models (Client, Object, Quote, Invoice, RecurringInvoice, DocumentTemplate, Document, Contract, ScheduledJob, TenantMembership, WorkBreakdown, InvoicePayment — all SoftDeletes domain models except WorkBreakdownTask which cascades).

### MediaLibrary
Six collections (reconciled 2026-11-01): TemporaryUpload::default (public disk, 24h purge), Document::file + Document::pdf (private `documents.disk`, singleFile; quote uploads, employee ID scans / files, signed-contract PDF snapshot), Tenant::signature (private, multi-row so issued invoices keep their snapshot), Invoice::efa_xml (private, written by EInvoiceService::submit). Clients, objects, DocumentTemplate have no stored files; Invoice and unsigned Contract/Quote PDFs are generated per request and returned as bytes. Employee files do NOT go through /uploads staging (staged files sit on the public disk up to 24h): multipart straight to EmployeeDocumentController. **Phase 5:** Quote document-kind quotes upload PDF/image via temporary upload contract (FE posts to /uploads, gets uuid, form sends document_uuid, DTO validates OwnedTemporaryMedia + TemporaryMediaConstraints, service moves via moveToModel + re-validates ownership/tenant; Media::move honours collection useDisk so file physically moves from public temp to private DOCUMENTS_DISK). A future stored-document feature should reuse `Document` (new `DocumentTypeEnum` case + policy) rather than adding HasMedia to another owner; non-sensitive uploads use the staging + moveToModel contract. **Task attachments (2026-11-01, uncommitted): deviation from the advice above.** `Task::attachments` (private `config('tasks.attachments.disk')`, multi-row, not singleFile) uses `Task implements HasMedia`, not `Document`: Document is BelongsToTenant with NOT NULL `tenant_id` and needs two rows per file, so it cannot hold personal-task files. Uploads are direct multipart to the task controllers (no staging); the Task owner is the one HasMedia owner whose media rows may have NULL `tenant_id` (DB CHECK).

### AllowedFilter + DataTable query contract
App\Utils\AllowedFilter extends Spatie AllowedFilter: search([...cols]), contains, dynamic (→ SymbolOperatorFilter, operators != <= >= ~ < > = between:), relationExact(name, relation, column), callbackClean, callbackWithOperator. Validators uuid()/date()/isoDateTime()/integer()/numeric()/boolean() log-and-skip. ILIKE on pgsql. FE counterpart Composables/useSpatieTableQuery.ts + Components/DataTable/* (filter[x]=<opPrefix><value> URL contract). Operator prefixes (filterOperators.ts ↔ SymbolOperators::parse, keep in sync): ~: contains, !=:, <:, <=:, >:, >=:, between: (from,to), none = '='. Operator sets by FilterType: string/text ~ = !=; number = != < <= > >= between; boolean = (tri-state); date/datetime = < <= > >= between; enum/select/autocomplete = != (+multiple → comma). Sub-components TableFilters, TableFilter, TableSearch (400 ms debounce → filter[search]), TablePagination (emits page, perPage; 10/25/50/100).

### Precognition + Forms
HandlePrecognitiveRequests around every POST|PUT mutation (web + api). AppServiceProvider::register (:25-27) binds PrecognitiveDataValidatorResolver (honours Precognition-Validate-Only), short-circuits DTO resolution during precognitive requests. FE: useForm(method, url, data) (Inertia 3 method-bound, Precognition built-in) inside FormProvider → form.submit() → BE store|update(DTO) → 422 → form.errors Record<field,string> | 204 + redirect with flash → toast.

### Spatie Data DTO boundary
DTOs injected as controller params, #[TypeScript] on all → resources/js/types/generated.d.ts (App.Data.*, App.Enums.*) via TypeScriptTransformerServiceProvider (app/Data + app/Enums, GlobalNamespaceWriter). DataValidatorResolver singleton swapped — custom resolvers must extend PrecognitiveDataValidatorResolver.

### Navigation (BE-driven discovery, persistent + Link-based)
App\Navigation\NavItem attribute (repeatable, method-level: label, route, icon, permission, policyModel, group, order) discovered by NavigationRegistry via router reflection; visibility Gate::forUser()->allows(permission, policyModel) or $user->can (:79-89); groups NavigationRegistry::GROUPS (settings). Shared as navigation: NavigationItemData[], rendered Layouts/AppLayout.vue:215-275. **FE persistent layout:** `createInertiaApp({ layout: (name) => (name.startsWith('Auth/') || name.startsWith('Invitations/') ? undefined : AppLayout) })` in app.ts wraps every page in AppLayout except Auth/* and Invitations/* (sidebars, bell, switcher persist, no remount-on-navigate). Pages no longer import/wrap AppLayout. Internal nav links use Inertia `<Link :href>` (from `@inertiajs/vue3`, renders `<a>` but intercepts clicks → Inertia visit, no full reload). Mobile drawer closes on `router.on('navigate')` to prevent drawer-left-open-after-click UX. Logout button moved from sidebar user card to footer above language switcher (`router.post('/logout')` unchanged). **Icon map:** hardcoded ICONS (HomeIcon, UsersIcon, ShieldCheckIcon, ClipboardDocumentListIcon, PhotoIcon, EnvelopeIcon, UserCircleIcon, Cog6ToothIcon, BellIcon, BellAlertIcon, IdentificationIcon, CalendarDaysIcon; unknown → HomeIcon) — every new NavItem icon must be added. translateLabel strips app. prefix then t(). Active = page.url.startsWith(href). Settings group children (order 10/15/20/25/30/40/50/60): Profile/Users/InvoiceSettings/NotificationSettings/DocumentTemplates/Roles/AuditLogs/Media. **Keep full-page reload ONLY for:** `/language/{locale}` (LocaleMiddleware via Get), `*.pdf` links (target="_blank"), media download_url, `mailto:`, `tel:`, absolute http, form 409 errors. On port: add NavItems for clients, objects, quotes, contracts, invoices, recurring invoices + ensure all internal links converted to `<Link>`.

### Dashboard UX gotchas (Phase 9)

**Carbon::diffInDays signed arithmetic:** DashboardAlertsQuery uses `$dueDate->diffInDays(today())` (signed int). Overdue invoices: due_date < today → positive int (days late). Expiring/unassigned: due_date >= today → zero or negative int (clamped >= 0 for display, "0 days" = today, "-1 days" → "1 day left"). Sort predicate: overdue_invoice uses `-days` (descending by days late, oldest first); expiring uses `+days` (ascending, soonest first). Logic: diffInDays(today()) returns future dates as negative; alerts sort corrects sign per type urgency (more urgent = earlier in list).

**MorphTo::constrain receives Builder, not Collection.** ContractExpiring query includes `->with(['contractable' => morphWith([TenantMembership::class => ['user:id,name,email']])])` (Eloquent eager-load syntax for morphs). Inside DashboardAlertData::fromContract, `$contract->contractable->label()` works (model instance loaded). For soft-deleted CleaningObject (maybe employee left, object deleted), label() returns '' (acceptable fallback). Never assume morphTo relations exist; always provide defaults or skip the relation in the alert.

**Dashboard metric tiles are interactive buttons, not static divs.** MetricStat.vue is `<button type="button">` not `<div>`, because tile click may POST a switch first (cross-company navigation). `<button :disabled="switchingTo !== null">` gates while switch in flight. Children (label, value, caption) are not interactive themselves (no nested buttons). AlertRow.vue same pattern: `<button>` wraps all content, single click handler. ADR-F1 consequence: no middle-click/open-in-new-tab on tiles (trade-off for unified switch+navigate UX).

**Alerts sorted by type rank, then urgency (sign-dependent).** DashboardAlertsQuery sorts: `[$type->rank() ASC, urgency ASC]` where urgency = `[-days for overdue, +days for others, 0 for supplier]`. Result: overdue invoices (rank 1) come first, sorted most-days-late first; then supplier (rank 2) static; then contracts/quotes/unassigned (ranks 3–5) each sorted soonest-first. Capped alerts: e.g., 3 overdue of 50 total (15/type), rest hidden → AlertsPanel section shows "3 items +47 more" (UI feedback that alerts are truncated, not end of list).

### PDF generation (spatie/laravel-pdf, chrome driver via Alpine apk) — Phase 4+

**Backend:**
- `config/laravel-pdf.php` (published + configured): driver `chrome` (env LARAVEL_PDF_DRIVER), chrome_binary `env('CHROMIUM_PATH', '/usr/bin/chromium-browser')`, no_sandbox true, custom_flags `['--disable-gpu', '--disable-dev-shm-usage']`, timeouts (30s startup, 30000ms, 5000ms operation).
- `spatie/laravel-pdf` with `chrome-php/chrome` (pure-PHP DevTools, no Node at runtime).
- Alpine Dockerfile: `apk add chromium nss freetype harfburst ca-certificates ttf-dejavu font-noto`, smoke test `chromium-browser --headless --version`.
- Contracts: `RendersInvoicePdf { public function render(Invoice $invoice): string; }` + `RendersQuotePdf { public function render(Quote $quote): string; }` + `RendersContractPdf { public function render(Contract $contract): string; }` (testable via mock).
- `QpdfMerger` (`MergesPdfDocuments`) joins several rendered invoice PDFs via the `qpdf` binary for GET /invoices/bulk/pdf (InvoiceBulkService::mergedPdf; single invoice skips merge).
- Services: `InvoicePdfService` renders `resources/views/pdf/invoices/{classic,modern,minimal}.blade.php` · `QuotePdfService` renders `resources/views/pdf/quotes/default.blade.php` · `ContractPdfService` renders through `DocumentPdfRenderer` -> `resources/views/pdf/documents/{default,header,footer}.blade.php` (shared by contract, quote text and template preview PDFs; CSS from `resources/css/document-body.css`) — all via chrome driver. Quote without a text Document still uses `pdf/quotes/default.blade.php`. `pdf/contracts/default.blade.php` was removed. Dockerfile(s) add `ttf-liberation` for document fonts.
- Routes: `GET /invoices/{invoice}/pdf` · `GET /quotes/{quote}/pdf` · `GET /contracts/{contract}/pdf` · `POST /contracts/generate-pdf` and `POST /settings/document-templates/preview-pdf` (inline PDF from unsaved text, throttle `document-preview` 30/min/user) (download routes Content-Disposition: attachment, filename sanitised via `HasPdfFilename::pdfFilenameBase()` + `HeaderUtils::makeDisposition`).
- Tests: mocked via `$this->mock(RendersInvoicePdf::class)` / `RendersQuotePdf::class` / `RendersContractPdf::class` (no real Chromium in test DB).

**Frontend:**
- Link: `<a :href="`/invoices/${id}/pdf`" target="_blank">` / `/quotes/${id}/pdf` / `/contracts/${id}/pdf` (native browser download).

### Queue (Laravel queue, database driver + queue compose service) — Phase 4

**Backend:**
- `QUEUE_CONNECTION=database` (dev + test); composed `queue` service in production.
- Queue table `jobs` pre-migrated.
- Notifications: `InvoiceIssued` implements `ShouldQueue`, `#[Tries(3)]` `#[Backoff([10,30,60])]` `#[Timeout(120)]`, `afterCommit()` in ctor (mail + PDF).
- Jobs: `GenerateRecurringInvoiceJob` (ShouldQueue, ShouldBeUnique, retries 3).
- Commands: `MarkOverdueInvoices` / `GenerateRecurringInvoices` scheduled daily via `routes/console.php`.
- Dev: `queue:work` runs in dedicated `queue` compose service (mirrors prod supervisor).
- Tests: `QUEUE_CONNECTION=sync` in phpunit.xml (jobs execute immediately).

**DevOps:**
- Compose: `queue` service (same image as `app`, php artisan queue:work).
- Supervisor (prod): `[program:scheduler]` runs `php artisan schedule:work` (daily crons).
- `.env.example`: `MAIL_MAILER=smtp`, `MAIL_HOST=mailpit`, `MAIL_PORT=1025` (dev Mailpit service).

### Cleaner actor scoping (final, Phase 7)

**Per-actor row visibility for Interná upratovačka role (no "all" permissions).**
- `ScheduledJob::scopeVisibleTo(Builder, User|TenantMembership $actor): Builder` — filters to jobs assigned to actor's current membership (WHERE assigned_membership_id = actor->current_membership_id). Returns empty for other roles' queries (they never call scopeVisibleTo; all-permission holders query without actor param).
- `ScheduledJob::isVisibleTo(User|TenantMembership $actor): bool` — predicate for single-row checks (policy gate on view/update/delete). Complements scopeVisibleTo (scope for list, predicate for instance).
- `CleaningObject::scopeVisibleTo(Builder, User|TenantMembership $actor): Builder` — filters to objects reachable via ANY assigned job, regardless of job status/date (D3 override: Vedúca assigns cleaner → cleaner can view object). Query: `WHERE id IN (SELECT DISTINCT cleaning_object_id FROM cleaning_jobs WHERE assigned_membership_id = ? AND deleted_at IS NULL)`. No date/status filter.
- `CleaningObject::isVisibleTo(User|TenantMembership $actor): bool` — predicate version (exists check via above subquery).
- `User::activeMembershipId(): ?string` — returns current active TenantMembership id in bound tenant context (for actor param in services).
- Service signatures: `JobService::paginate|create|update|assign|cancel(…, ?User $actor = null)` — actor param optional (null = system/admin context), services call `$model->isVisibleTo($actor)` for authorization (422 scope check before permission check is anti-pattern; permission check gates action, scope limits rows).
- `ObjectService::optionsVisibleTo(User $actor): array` — returns ObjectOptionData[] filtered via scopeVisibleTo (used by EmployeeService::create form, job create form).
- **Scope application discipline:** cron/queue/listener/console paths NEVER apply scopeVisibleTo (run unbound per D6 discipline; no actor context). Controllers apply via service calls (service receives $this->user() as actor). Tests: actingAsCleanerUser() helper binds membership context.
- **Zákazník (Customer) actor:** Phase 2 role template reserves; phase 8+ portal expected (zero implementation phase 7; objects/jobs/invoices all hidden via permission absence, no scopeVisibleTo needed).

### Mail (Mailpit in dev, smtp) — Phase 4

**Backend:**
- `.env.example`: `MAIL_MAILER=smtp`, `MAIL_HOST=mailpit`, `MAIL_PORT=1025`, `MAIL_SCHEME=null` (no TLS for Mailpit).
- Notifications: `InvoiceIssued` queued, via('mail') → customer_email, attach PDF.
- Dev: browse Mailpit UI at `http://localhost:8026` to view sent mails + attachments.
- Tests: `MAIL_MAILER=array` (phpunit.xml), Mail::fake() in tests.

### TypeScript code generation (php artisan typescript:transform) — Phase 2+

**All DTOs + Enums carry `#[TypeScript]` attribute.** Run after every schema change (columns) or DTO/Enum change to regenerate `resources/js/types/generated.d.ts`. FE imports App.Data.* and App.Enums.* from there (never hand-edit). Laravel TypeScript Transformer 3 (config/typescript-transformer.php): scans app/Data + app/Enums, GlobalNamespaceWriter outputs to resources/js/types/generated.d.ts.

### MergeValidationRules + Precognition — Phase 2+

**Validation ruleset merging.** `#[MergeValidationRules]` on DTOs that extend a parent DTO (e.g., `UpdateUserData` extends base) — merges parent rules via Spatie Data trait. Precognition (HandlePrecognitiveRequests middleware) wraps every POST|PUT mutation; FE useForm auto-detects Precognition support and debounces field validation requests (blurred field → POST with Validate-Only header → 422 on that field only). Cross-field rules (e.g., DTO closure on client_id existence) execute on full payload only (deferred until submit unless Precognition-Validate-Only happens to include that field).

### Localisation (BE + FE sync) — Phase 2 uk added

**Core:**
- App\Enums\SupportedLanguage (#[TypeScript], backed string) — {sk='sk', en='en', uk='uk'}. Methods: `getDefault(): self` (sk), `getDisplayName(): string`, `getForLanguageSwitcher(): array{value, label, flag}` (flag emoji 🇸🇰 / 🇬🇧 / 🇺🇦), `isSupported(string): bool`, `getCodes(): array`.
- LocaleMiddleware (web, appended after TenantContextMiddleware) — resolves user.locale → session → cookie → Accept-Language → sk default. Sets app()->setLocale($locale). GET /language/{locale} (unauthenticated, no DTO) sets cookie + session.
- Translations = JSON resources/lang/{sk,en,uk}/app.json (+ sk/validation.json, en/validation.json, uk/validation.json) loaded by AppServiceProvider::loadJsonTranslations as app.<key>. Flat snake_case keys for vue-i18n (not dotted). Phase 1 = 11 skeleton keys + auth branding (app_name, auth_hero_*, auth_welcome_back, footer_copy, auth_show_password, auth_hide_password). Phase 2 adds tenancy + employee keys (tenant_*, invitation_*, permission_*, permission_group_*, tenant_color_*, invitation_status_*, membership_active, system_role, close + FE-only keys).
- uk: ported from main branch language files where semantically identical keys exist; remaining skeleton-only keys (DataTable filters, editor, media) translated to Ukrainian; flagged for native review.

**FE Integration:**
- vue-i18n ^11 (legacy:false), messages bundled at build from resources/lang/{locale}/app.json, locale from Inertia `locale` prop.
- app.ts: import ukApp from '../lang/uk/app.json' + messages.uk.
- AppLayout language dropdown data-driven from shared languages (now 3 items: sk/en/uk) + lang attribute on links.
- router.on('navigate') switches locale via app.i18n.locale = new_locale (vue-i18n reactivity).
- FE locale change calls GET /language/{locale} (full reload for consistency).

### Scribe API docs
add_routes=false; routes in routes/web.php:37-49 under auth + permission:view api docs. Strategies App\Scribe\Strategies\BodyParameters\GetBodyParamsFromSpatieData, Responses\GetResponseFromSpatieData (#[ResponseFromSpatieData(dataClass, model, states, with, paginated)]), wired config/scribe.php:223+. Only api/* documented. On port: extend docs for business API endpoints (clients, objects, quotes, invoices, contracts).

### Event → Listener wiring (auto-discovery, no explicit registration) — Phase 8

**Backend:**
- Laravel 9+ auto-discovers listeners: `@handle($event)` methods are auto-wired (bootstrap/app.php has `withEvents(discover: true)` by default).
- Phase 8 listeners (Notify*) are auto-discovered only — NEVER register explicitly with `Event::listen()` (causes double-fire).
- Pre-existing bug (phase 4–7): AppServiceProvider had explicit `Event::listen(ContractSigned::class, GenerateWorkBreakdownFromSignedContract::class)` + `Event::listen(NotificationSent::class, StampInvoiceSentAt::class)` — both listeners were auto-discovered, causing double-fire. Phase 8 removed explicit registrations.
- Verify with `php artisan event:list --event=<EventName>` — each listener should appear ONCE as `@handle` entry, not duplicated.
- Design: sync listeners (Notify*, GenerateWorkBreakdownFromSignedContract) run immediately on event dispatch; queued work (Notification send jobs, GenerateScheduledJobsJob) dispatches on queue via ShouldQueue.

### App shell (FE) — Phase 2 tenant switcher + colour override

**Layout & styling:**
- Layouts/AppLayout.vue — DaisyUI drawer lg:drawer-open, :style="themeStyle" on root (D5: `{ '--color-primary': tenant.active?.color }` when colour set, else undefined for theme default) · data-theme="app-theme" · dark sidebar (--color-neutral background) with gradient BrandMark logo, white text nav, user card (gradient initial avatar) · nav source = BE navigation (NavigationItemData[]).
- Root drawer inherits `--color-primary` override (inline style), scopes colour to AppLayout + children (AddTenantModal). Auth pages + Invitations/Accept outside AppLayout → unaffected (kept amber).
- Sidebar (after Brand, before User info): TenantSwitcher (full width, `!tenant.available.length ? hidden`) + Add tenant button + divider.
- Mobile navbar (after Brand flex-1): TenantSwitcher compact variant (dot + chevron only).
- Layouts/Header.vue — props {title; breadcrumbs?} + #actions slot.

**Tenancy components (new):**
- Composables: usePageProps (ComputedRef<SharedProps>), useAuthorization (allows(PermissionEnum)), useTenantTheme (themeStyle computed from tenant.active?.color).
- Components/Tenants/TenantColorDot.vue — inline h-2.5 w-2.5 rounded-full; colour from enum or null → theme default.
- Components/Tenants/TenantSwitcher.vue (AppLayout child) — dropdown menu (sidebar full, mobile compact); trigger shows colour dot + name; menu lists available tenants (sorted name), "Add new company" button. Click switches via router.post(/tenants/{id}/switch); switching state shows spinner; blur focus to auto-close dropdown.
- Components/Tenants/AddTenantModal.vue — ConfirmDeleteModal pattern; form {name, ico, color optional, copy_settings toggle, leader_email optional} via useForm; color via ColorSwatchPicker; submit → tenant creation + redirect dashboard + toast; Esc/backdrop closes.
- Components/Forms/ColorSwatchPicker.vue — field-mode or v-model, role="radiogroup" swatches, selected border+ring, hover border. Colours from TenantColorEnum via prop.
- Components/Can.vue (new) — permission guard; requires App.Enums.PermissionEnum prop; `<slot v-if="allows(permission)" /><slot v-else name="fallback" />`.
- Components/PermissionManager.vue (updated) — no client grouping (was split(' '), capitalize); render groups from props PermissionGroupData[] (from BE) + group header + group checkbox + items checkbox.

**Language & navigation:**
- AppLayout language dropdown → links to GET /language/{locale} (3 items: sk/en/uk, flag emojis, lang attribute on <a>). Data-driven from shared languages (no hardcoded list).
- Navigation from shared navigation (NavigationItemData[]), icon resolution via ICONS map (expanded phase 2 from initial set).
- Flash watcher → toast (4s timeout).

**Foundational:**
- Layouts/Header.vue — props {title; breadcrumbs?} + #actions slot.
- ConfirmDeleteModal + useDeleteConfirm composable.
- Form components: FormProvider, TextInput, PasswordInput, SelectInput, CheckboxInput, CheckboxGroup, RadioGroup, ToggleInput, DateInput, NumberInput, TextareaInput, AutocompleteInput, FileUploadInput, RichTextEditorInput.
- Components/BrandMark.vue — sparkle-broom SVG icon (size via class).
- DaisyUI themes:false + [data-theme='app-theme'] OKLCH token block (Plus Jakarta Sans / JetBrains Mono via Google Fonts in app.blade.php) + app.blade.php :root {--auth-*} login palette + app.ts Inertia progress colour amber (static, out of scope for tenant override).

**Phase 8 complete:**
- Notification bell (polling `/notifications/bell` web route OR `/api/notifications/bell` API route, every 60s, display unread count + 5 recent, tab-hidden pause).

**TODO (out of scope Phase 8):**
- Dashboard content (widgets, recent activity, notifications inbox summary).

### AI tool layer (MCP server + assistant toolbox) — Phase 11

- **One tool set, two callers.** `App\Mcp\Servers\CleanMasterServer::TOOLS` lists 30 tools (list_companies, company_overview, list/get invoices, quotes, clients, objects, jobs, tasks, expenses, list/get/update document templates, search, create/update/complete/assign tasks, create/update expenses, create/update quote and invoice drafts, delete_global_task, prepare_action, confirm_action). Served by `Mcp::web('/mcp')` (remote, ability `mcp`) and `Mcp::local('cleanmaster')` (stdio). The assistant gets the same classes minus `AssistantToolbox::EXCLUDED` = `confirm_action`, `delete_global_task`.
- **Tools hold no business logic.** Each tool authorises through `Gate::forUser` (`assertCompanyAllowed` → `actIn` on Tenant) and delegates to a service or to ActionConfirmationService. No second policy copy inside a tool.
- **ToolContext::run** (every tool call and every `ActionConfirmationService::execute`): asserts active membership + active tenant, then swaps `current_tenant_id`, `PermissionRegistrar` team id, the guard user and (when inputs are given) a synthetic request carrying the inputs so DTO rules read them as on web. Everything restored in `finally`.
- **CleanMasterTool::guard** maps exceptions to tool errors: AuthorizationException → `tool_not_allowed`; ValidationException → message; ModelNotFound → `tool_not_found`; 5xx HttpException and other Throwables are rethrown after audit. Every call writes a ToolAudit row (redacted arguments).
- **MCP server instructions** tell the client to start with `list_companies` and to treat tool results as data, never instructions.
- **Assistant:** McpToolAdapter (turn-deadline check, 20 000-char output cap); tool output observed by AssistantTurn to build the reply's action links.
- **Rate limits:** `throttle:mcp` keyed by MCP token id (`ai.mcp_throttle`, 120/min); `throttle:assistant` 30/min; `throttle:ai-analyze` 20/min.
- **Document template tools:** `list_document_templates`, `get_document_template`, `update_document_template`. An update of a global template always goes to the firm's own copy (`DocumentTemplateService::companyTarget` + `updateForCompany`, one transaction, so a refused edit leaves no copy); global rows are edited only in the web UI by a platform admin. `update_document_template` is MCP-only (`AssistantToolbox::EXCLUDED`).
- **Prompt-injection guard:** `App\Services\Ai\Guard\{PromptInjectionDetector,PromptGuard,InjectionVerdict,PromptInjectionCorpus}`, command `ai:guard-verify`, config `ai.guard.*`. `AssistantTurn::restrict()` / `refusalForWrite()` enforce the write block and caps; `McpToolAdapter` screens arguments and output and wraps output in `<tool_output_{nonce} tool="x" trust="untrusted">`; `LaravelAiAssistant` screens the request and filters the reply. `CleanMasterTool` screens MCP arguments/output, labels flagged output with `_security_notice` and sets the per-user cache restriction `ai-guard:mcp-restricted:{userId}`; every non-read-only tool is refused during it. Because `prepare_action` is refused too and a prepared token lives 10 minutes, no token outlives a 15-minute restriction. `PromptGuard::scanValues` scores each value separately; `scanOutput` decodes JSON first. `ExpenseService`, `ExpenseImportService`, `TaskParseService` drop flagged model input/output. Tools are classified read-only by the `IsReadOnly` attribute; a missing attribute counts as a write.

### Two-step confirmation — Phase 11

- Irreversible actions (issue/send invoice, send quote, sign contract, cancel invoice, delete invoice draft / quote / task / expense) are never executed by a tool directly. `prepare_action` → PendingAction (preview with company name, payload_hash, subject_hash fingerprint, TTL `ai.pending_action_ttl_minutes` = 10).
- **Fingerprint:** sha256 over key-sorted attributes (updated_at excluded), line items (invoice / quote) and the quote's resolved client name. A changed or deleted subject at confirm time cancels the row (`PendingActionSubjectChangedException`).
- **Payload hash** over the key-sorted payload (jsonb does not keep key order); mismatch → 422 on `status`.
- **Token:** MCP prepares return a one-time random token (only sha256 stored; `PendingActionPreparedData` carries it and is deliberately not a Spatie Data). Assistant-channel rows carry no token and are app-only.
- **Channels:** `ai.confirm_channels` per action. Chat (MCP `confirm_action`) is allowed only for issue_invoice, send_invoice, send_quote; every other action is app-only (web or API `POST …/confirm`).
- **Single-use:** status Executed on success; Expired by `cleanup:pending-actions` hourly; Cancelled by user or subject change.
- **Assistant:** may prepare, never confirm. Its reply carries the pending action with `confirm_in_app_only` when the channel list is app-only.

### Mobile API surface (/api and /mcp) — Phase 11

- **`/api` group order (routes/api.php):** `auth:sanctum` → `locale.api` → `user.active` → `ability:mobile` → `tenant.context` → `tenant.required`. Login (`POST /api/auth/login`) sits outside the ability group (HandlePrecognitiveRequests + locale.api); logout is `auth:sanctum` + `locale.api` only.
- **Routes in the ability group:** /me, /dashboard, /notifications (bell, list, read-all, read), /invoices (+pdf), /quotes (+pdf), /devices (throttle `devices` 10/min), /assistant/messages (throttle `assistant`), /tasks (+parse under `ai-analyze`; +attachments: GET/POST `/tasks/{task}/attachments`, GET/DELETE `/{attachment}`, POST throttled `task-attachments`), /expenses (analyze + import preview under `ai-analyze`; import, store, show, update, delete, confirm, receipt), /pending-actions (list, confirm, cancel), /profile, /users.
- **`/mcp` group order (routes/ai.php):** `auth:sanctum` → `locale.api` → `user.active` → `ability:mcp` → `throttle:mcp`. Tokens with only `mcp` ability; a `mobile` token is rejected.
- **Tenant context before route binding:** `bootstrap/app.php` prepends TenantContextMiddleware before `SubstituteBindings`, so scoped route bindings (for example `/tasks/{task}` via TaskScope, `/pending-actions/{pendingAction}`) resolve under the caller's tenant. Security fix `6f08aa9`; `main` lacks it.
- **Tenant selection:** `X-Tenant-Id` header (must be a membership the caller holds, else 403 `tenant_forbidden`) → session → first membership (filtered by mobile access for mobile tokens). Mobile token in a company without `access mobile app` → 403 `mobile_forbidden`.
- **Errors:** validation → 422 with field keys (`errors.ai` when the provider is unavailable, `errors.status` for pending-action state, `errors.token` for bad MCP tokens); no active membership → 403 `no_active_tenant`.
- Scribe docs cover the `Assistant` group and ResponseFromSpatieData shapes.

### Global task and per-person exceptions (rule 6) — Phase 11

Rule 6 (`BelongsToTenant` + `tenant_id NOT NULL` on every domain model) has these deliberate exceptions on this branch:
- **Global tasks.** `tasks.tenant_id` is nullable; a NULL row is owned by `created_by_user_id` and is visible only to its creator. Isolation comes from `TaskScope` (fail-closed) plus the Policy as second layer. `Task::unscopedForSystem()` is the single bypass; queue and console loaders go through `TaskService::findForSystem`.
- **Activity for global rows.** `Activity::$keepNullTenant` stops the creating hook from filling `tenant_id` while a tenant is bound, so audit rows of global tasks stay tenant-less (visible to their causer through `visibleInTenant`).
- **Notifications.** `notifications.tenant_id` is nullable (migration 2026_11_01_000300) for system messages without a company. NotificationPolicy and bell scopes filter by recipient.
- **DeviceToken** has no tenant_id: a device belongs to a person; tenant visibility is checked at send time (`hasMobileAccess`).
- **Task attachments.** `media.tenant_id` may be NULL only for Task owners (DB CHECK `media_tenant_required_unless_task`, migration 2026_11_02_000200). The creating hook copies the owner's tenant_id, so a personal task's file is not filed under the bound company. The Media viewer excludes Task media.

### Sync AI calls (deviation) — Phase 11

- Plan intent was to keep long work off the request. The shipped AI calls (assistant reply, expense analyze, bank CSV categorisation, task parse) run **synchronously inside the HTTP request**: no `ShouldQueue`, no `dispatch` in `app/Ai`, `app/Services/Ai`, `app/Services/Expenses` (grep-verified).
- Bounds: `ai.assistant_timeout` 30 s per provider step; `ai.assistant.turn_seconds` 45 s gates the start of each tool call; `#[MaxSteps(6)]` caps steps (worst case ~6 × 30 s when a model keeps requesting tools); `ai.timeout` 30 s on the other AI calls; assistant provider failures without effects surface as 422 `errors.ai`.
- Transport: `docker/nginx/default.conf` `fastcgi_read_timeout 300` (≥ 90 s required). A proxy in front of nginx must not cut earlier — TODO verify at deploy.

### Mobile contract export (pnpm api-types:export) — Phase 11

- `scripts/export-api-types.mjs` copies `resources/js/types/generated.d.ts` (produced by `php artisan typescript:transform` from `#[TypeScript]` DTOs and enums) to `../cleaning-software-app/src/types/api.generated.d.ts` by default, or to a path passed as argument; prepends a "do not edit" header. Fails when the mobile checkout is missing and no path is given.
- The mobile repo is `git@github.com:VladimirGostik/cleaning-software-app.git` (not in this worktree).
- DTOs that must not reach the mobile types (for example PendingActionPreparedData with the token) are plain classes, not `#[TypeScript]`.

## Layer contracts

### HTTP ↔ Service
Controller receives DTO + route-bound model, calls final readonly service, returns Inertia::render / redirect-with-flash (success|error|info|status) / response()->json(Data). Exceptions: RoleService signals via InvalidArgumentException; profile password change and auth attempts inline in controllers; list queries live in controllers (User, Role, AuditLog) except MediaService::index. On port: all business model services follow same pattern (create/update/delete in service, list in controller).

### Service ↔ Model
Services own DB::transaction; models hold casts, LogsActivity options, scopes. No observers/events besides framework auth. On port: add business model observers + event dispatchers (QuoteSent, InvoiceIssued, InvoiceOverdue, ContractExpired, etc.).

### Service ↔ Job / Queue
No queued jobs today (QUEUE_CONNECTION sync in tests). Only schedule PurgeTemporaryUploadsCommand daily. On port: add queued jobs (InvoiceIssued mail, GenerateScheduledJobs, GenerateRecurringInvoiceJob) with ShouldDispatchAfterCommit.

### BE ↔ FE shared props (HandleInertiaRequests::share) — Phase 2 tenancy collapse + PermissionEnum

**Typed & delivered:**
- flash: {success?, error?, info?, status?, string?}
- auth: {user: {id, name, email, locale: SupportedLanguage} | null}
- tenant: {active: TenantListItemData | null, available: TenantListItemData[]} (Phase 2 new; tenant.available ordered by name, all with is_active=true)
- tenantColors: {value: TenantColorEnum, label: string}[] (Phase 2 new; 8 colour options for AddTenantModal)
- canCreateTenant: boolean (2026-10-31; platform admin only; TenantSwitcher hides "add company" otherwise)
- can: Record<string, boolean> (derived from PermissionEnum::cases, keyed by sharedKey = Str::camel, 56 keys; e.g., 'viewEmployees', 'createClients')
- languages: {value: SupportedLanguage, label: string, flag?: string}[] (now sk/en/uk; phase 2 adds uk)
- locale: string (current user.locale, set by LocaleMiddleware)
- navigation: NavigationItemData[] (BE-driven discovery via #[NavItem] attributes, resolved per user's can() checks)

**TS location:**
- Single `SharedProps` interface in resources/js/types/index.d.ts (D1: collapsed from two declare blocks). `declare module '@inertiajs/core' { interface InertiaConfig { sharedPageProps: SharedProps } }`.
- Import-free global augmentation: removed types/inertia.d.ts (D1).
- FE reads via usePageProps() computed (D3: never destructure at setup, always read .value in computed/template).

**Phase 2 changes:**
- auth.user.locale added (user.locale consumer on login via LocaleMiddleware).
- Dropped SharedCan (hand-enumerated keys rotted; now derived from PermissionEnum::sharedKey, unidirectional per-permission).
- Dropped canResetPassword (skeleton's page-prop-only Auth/Login, not shared — defer to phase 3+ if needed).
- Removed duplicate types/inertia.d.ts (D1).

### Per-page props (Inertia render)
**Phase 1 (skeleton):**
- Auth/Login {canResetPassword: bool}
- Auth/ForgotPassword {status?: string}
- Auth/ResetPassword {email: string, token: string}
- Dashboard {}
- Profile/Show {}

**Phase 2 (tenancy + identity):**
- Invitations/Accept {invitation: InvitationAcceptPageData {state: InvitationAcceptStateEnum, token, email?, tenant_name?, role_name?, invited_email?}}
- Users/Index {users: Paginator<UserListItemData>, filters?: Record<string, unknown>, filterOptions: {roles: RoleListItemData[]}}
- Users/Form {user?: UserListItemData, roles: RoleListItemData[]}
- Roles/Index {roles: Paginator<RoleDetailData>}
- Roles/Form {role?: RoleDetailData, permissions: PermissionGroupData[]}
- AuditLogs/Index {activities: Paginator<ActivityLogListItemData>, filters?: Record<string, unknown>, filterOptions?}
- AuditLogs/Show {activity: ActivityLogDetailData}
- Media/Index {media: Paginator<MediaListItemData>, filters?: Record<string, unknown>, filterOptions?}
- Media/Show {media: MediaDetailData}

**2026-11-01 (task attachments):**
- Tasks/Show {task: TaskDetailData, attachments: TaskAttachmentListData, context: TaskFormContextData} (attachment names and URLs are in the `attachments` prop only, not in `task`).

**2026-10-31 (documents, contracts, employees):**
- DocumentTemplates/Index {templates: Paginator<DocumentTemplateListItemData>, filters, canCreateGlobal} · Create {type, employment_type, isGlobal, tokens: DocumentTokenCatalogData, letterhead, footer} · Edit {template: DocumentTemplateEditData} (read-only when `!can.update`).
- Contracts/Create {context: ContractFormContextData, prefill: ContractCreatePrefillData} · Contracts/Edit (+encryptHistory) · Contracts/Show (+encryptHistory; ContractDetailData with `body` null = no text access).
- Clients/Show, Objects/Show, Employees/Show add {contracts: ContractListItemData[]|null (null without ViewContracts; newest 50), canCreateContract: bool}.
- Employees/Show adds {personal: EmployeePersonalData|null (masked national id), documents: EmployeeDocumentData[]|null, documentConstraints: EmployeeDocumentConstraintsData|null}; `employee.can.manage_documents`. Employees/Create + Edit add {personal: EmployeePersonalFormData|null (null hides the section; Edit sends the full national_id and logs `national_id_viewed`)}; Edit calls `Inertia::encryptHistory()`; logout calls `Inertia::clearHistory()`.
- Quotes/Show adds contractTemplates: DocumentTemplateOptionData[]; QuoteDetailData.text_document; Quotes/Create|Edit context adds templates/tokens/letterhead/footer.
- Settings/Invoicing: InvoiceSettingsData adds `representative_name`.
- Enum/type mirrors: ContractableTypeEnum gains `client`; new DocumentTypeEnum, DocumentTokenKindEnum, DocumentTokenGroupEnum, DocumentTemplateScopeEnum, DocumentTemplateStateEnum (all #[TypeScript], run `typescript:transform`).

### clients — Cleaning company customers (corporate + private) with contacts and address

**Core:**
- `App\Models\Client` — UUIDv7; traits BelongsToTenant, HasFactory, HasUuids, LogsActivity, SoftDeletes. Columns: tenant_id FK, `type` (ClientTypeEnum: corporate/private), name (255), nullable ico (32) + dic (32) + vat_number (32), bool is_vat_payer, address (street/city/postal_code/country), nullable note, **nullable peppol_participant_id (64, e-invoice recipient id; validated by `App\Rules\PeppolParticipantId` against config/efa.php participant_schemes; copied to invoices.customer_peppol_participant_id at invoice creation)**. Relations: `contacts(): HasMany<ClientContact>`, `primaryContact(): HasOne<ClientContact>` (where is_primary=true), `objects(): HasMany<CleaningObject>`. Activity log on changes (logOnly same list as columns; logOnlyDirty). Partial unique index `(tenant_id, ico)` WHERE deleted_at IS NULL AND ico IS NOT NULL.
- `App\Models\ClientContact` — UUIDv7 pivot; traits BelongsToTenant, HasFactory, HasUuids, LogsActivity, SoftDeletes. Columns: tenant_id FK, client_id FK, name (255), nullable position (255) + email (255, unique per client's contact set) + phone (64), bool is_primary. Relations: client BelongsTo, tenant BelongsTo. Index (client_id, is_primary).
- `App\Services\ClientService` (final readonly, ctor DatabaseManager) — `paginate(Request): LengthAwarePaginator` (QueryBuilder::for(Client) + AllowedFilter search/name/type/city/ico/created_at + defaultSort name + eager load contacts + primaryContact + counts → through ClientListItemData); `create(ClientUpsertData): Client` (transaction: Client::create + syncContacts); `update(Client, ClientUpsertData): Client`; `delete(Client): void` — **D1 override user decision:** transaction: soft-delete all client's objects via `$client->objects->each->delete()` (SoftDeletes global scope excludes trashed from queries; per-model logging), soft-delete contacts, soft-delete client. CleaningObject keeps both SoftDeletes (for this cascade) and is_active (for direct deactivation via ObjectService::deactivate). **D8 note:** IČO uniqueness enforced by DTO rule (Precognition-visible) + DB partial index (race constraint accepted for internal tool). `private syncContacts(Client, DataCollection): void` — existing-id validation, soft-delete outgoing, primary auto-promotion (none → first becomes primary).
- `App\Enums\ClientTypeEnum` (#[TypeScript], backed string) — `Corporate='corporate'`, `Private='private'`. Method: `label(): string` → `__('app.client_type_'.$this->value)`.

**Satellites (BE):**
- DTOs: `ClientContactData` (id, name, ?position, ?email, ?phone, is_primary); `ClientUpsertData` (type enum required, name required, ?ico requiredIf type=corporate, ?dic, ?vat_number, is_vat_payer, address fields, ?note, ?peppol_participant_id, contacts: DataCollection<ClientContactData>). Rules: ico unique per tenant (D8), at most one primary contact. `ClientListItemData` (id, type, name, ?ico, ?city, contacts_count, objects_count, ?primary_contact_email, ?primary_contact_phone, created_at). `ClientDetailData` (extends ListItemData + dic, vat_number, street, postal_code, country, note, peppol_participant_id, contacts: DataCollection<ClientContactData>). `ClientOptionData` (id, name) — for pickers.
- Enums: ClientTypeEnum (see Core above).
- Factories: ClientFactory (with `private()`, `withContacts(int $count)` states).
- Seeders: ClientSeeder (demo tenant 12345678 with 6 corporate + 3 private clients, 2–3 contacts each).
- Policies: `ClientPolicy` — viewAny/view → ViewClients; create → CreateClients; update → EditClients; delete → DeleteClients. Tenant isolation via TenantScope (no extra record check).
- Controllers: `ClientController` (ctor ClientService $clients), actions: `#[Authorize('viewAny')] index(Request)` → Clients/Index `[clients, filters]`; `#[Authorize('view')] show(Client)` → Clients/Show `[client: ClientDetailData, objects: ObjectListItemData[], contracts: ContractListItemData[]|null, canCreateContract: bool]` (load contacts, eager-load client on objects; contracts = ContractService::listFor: contracts on the client and on its objects); `#[Authorize('create')] store(ClientUpsertData)` → show + flash (2026-10-31: redirects to clients.show so a contract can be started from the new client); `#[Authorize('update')] update(ClientUpsertData, Client)` → show + flash; `#[Authorize('delete')] destroy(Client)` → index + flash. Routes: GET /clients, GET /clients/{client}, POST /clients (Precognition), PUT /clients/{client} (Precognition), DELETE /clients/{client} (all `whereUuid`).
- Navigation: #[NavItem] on index → `clients` nav (icon UserGroupIcon, permission ViewClients, order 30).

**FE Satellites:**
- Composables: none (ClientForm owns its state via useForm).
- Components: `ClientTypeBadge` (badge + icon per type), `ClientDetailCard` (info card: type/VAT/address), `ClientContactsList` (primary badge, contact rows with links), `ClientObjectsTable` (unpaginated table), `ClientForm` (form with RadioGroup type, fields, ContactsListField for array editor), `ClientFormDrawer` (side drawer wrapper, title based on create/edit), `ContactsListField` (custom field-mode: rows, add/remove, set primary via radio, per-row TextInput/email/phone with Precognition).
- Pages: `Clients/Index` (DataTable: columns name/type/ico/city/primary_contact_email/objects_count; filters search/name/type/city/ico/created_at; sort name/type/city/ico/created_at; empty state "clients_empty"; buttons view/delete; create drawer from #actions). `Clients/Show` (header title/breadcrumbs + edit/add-object/delete buttons; grid: left ClientDetailCard+note+ContactsList, right ClientObjectsTable; edit drawer preloaded; delete confirm cascade copy; add-object preselects client).
- i18n: `client_add`, `client_edit`, `client_delete`, `client_delete_confirm`, `client_delete_cascade_hint`, `client_type`, `client_name`, `client_ico`, `client_dic`, `client_vat_number`, `client_is_vat_payer`, `client_contacts`, `client_contact_add`, `client_contact_remove`, `client_contact_is_primary`, `client_no_contacts`, `client_objects`, `client_no_objects`, `client_no_note`, `client_customer_since`, `clients_empty`, `clients_empty_hint`, `client_object_add` (per phase-3 FE plan step 10).

**Flow:**
- **List:** GET /clients → ClientController@index + AllowedFilter + sort/page → Clients/Index {clients: Paginator<ClientListItemData>, filters: Record<string, unknown>}. Table rows: view → Show page; delete → confirm modal (cascade copy). Create drawer from button → POST /clients (Precognition on blur) → ClientService::create → 302 index + flash success.
- **Show:** GET /clients/{client} (whereUuid) → RMB TenantScope → ClientController@show + eager load contacts + objects → Clients/Show {client: ClientDetailData, objects: ObjectListItemData[]}. Drawer forms: edit PUT /clients/{id} → 302 show + flash; add-object opens Objects form with client preselected.
- **Update:** PUT /clients/{client} (Precognition) → same service, 302 show + flash.
- **Delete:** DELETE /clients/{client} → ClientPolicy::delete gates → ClientService::delete (transaction: soft-delete all objects, soft-delete contacts, soft-delete client; activity logged per model).

**Depends on:** tenancy (tenant_id FK, BelongsToTenant, TenantScope), objects (Client::objects() relation, ClientService::delete soft-deletes children).

**Depended on by:** objects (client_id FK, ObjectUpsertData rule), quotes (client_id FK, withTrashed; QuoteService::convertToContract requires client_id), invoices (client_id FK + customer snapshot incl. peppol id), recurring-invoices (client_id FK), contracts (Client is a direct contract subject since 2026-10-31: `Client::contracts(): MorphMany<Contract>`, morph alias `client`, category service_agreement/nda/gdpr/other; a client contract has no Rozpis prác or jobs; object contracts still resolve `client.*` tokens through DocumentContextBuilder), dashboard (people metrics clients count).

**If you change Core, check:**
- ClientService::delete cascade: objects soft-delete logic (loop with `->delete()` per-model for activity logging), contact soft-delete, activity logging per model (3 types: Client, ClientContact, CleaningObject).
- IČO uniqueness: DTO rule builder (Rule::unique 'clients' with tenant_id + whereNull deleted_at), partial DB index, test reuse-after-delete scenario.
- Contact primary: syncContacts logic (reset all → promote first if none), validation closure (at most one), test auto-promote.
- ClientTypeEnum.label() consumers: Badge (label t(clientTypeKey(type))), Form (type selector options), tests.
- ClientPolicy used in: ObjectController (clients list for picker), ObjectService (no direct policy call but objects depend on clients).

**Keywords (SK):** klient, podnikateľ, fyzická osoba, kontakt, IČO, DIČ, DPH, adresa, kontaktná osoba, primárny kontakt.

### objects — Cleaning locations per client with access info, contact persons, and deactivation

**Core:**
- `App\Models\CleaningObject` — table `objects`; UUIDv7; traits BelongsToTenant, HasFactory, HasUuids, LogsActivity, **SoftDeletes** (D1 override user decision: hybrid lifecycle — is_active for direct deactivation, deleted_at for client cascade). Columns: tenant_id FK, client_id FK, type (ObjectTypeEnum: office/apartment/house/common_areas), name (255), address (street/city/postal_code/country), nullable access_code (64) + key_box_code (64) + key_count (int) + special_instructions (text) + area_sqm (decimal:2) + floor (int) + gps_lat (10,7) + gps_lng (10,7) (GPS reserved, no UI), bool is_active (direct deactivation), timestamps + soft_deletes. Relations: `client(): BelongsTo` → `$this->belongsTo(Client::class)->withTrashed()` (keeps relation resolvable even if client is soft-deleted), `contacts(): HasMany<ObjectContact>` (ordered is_primary desc, name asc), `primaryContact(): HasOne<ObjectContact>` (where is_primary=true, convenience accessor). Scopes: `scopeVisibleTo(Builder, User $actor): Builder` — ViewAllObjects → unfiltered; else `$actor->activeMembershipId()` null → `whereRaw('1 = 0')` (fail-closed), else `whereHas('jobs', assigned_membership_id = membership)` (D3: any assigned job grants reachability, no status/date filter). `isVisibleTo(User): bool` — ViewAllObjects shortcut, else exists-check through the scope. Also `ObjectService::optionsVisibleTo(User $actor): array` (ObjectOptionData[] for pickers; used by ScheduledJobController). Activity log logOnly (no note column logged).
- `App\Models\ObjectContact` — table `object_contacts`; UUIDv7; traits BelongsToTenant, HasFactory, HasUuids, LogsActivity (logOnly name/position/email/phone/is_primary, logOnlyDirty, dontLogEmptyChanges), SoftDeletes. Columns: tenant_id FK (restrictOnDelete), cleaning_object_id FK (restrictOnDelete), name (255 required), position (255 nullable), email (255 nullable), phone (64 nullable), bool is_primary (default false), timestamps + soft_deletes. Index (cleaning_object_id, is_primary). Relations: `cleaningObject(): BelongsTo<CleaningObject>`. Mirrors ClientContact structure exactly.
- `App\Services\ObjectService` (final readonly, ctor DatabaseManager, ContactCollectionSynchronizer) — `paginate(Request, User $actor): LengthAwarePaginator` (QueryBuilder::for(CleaningObject::visibleTo($actor)) + computed gate $includeContacts = $actor->can('viewContacts', CleaningObject::class), conditionally withCount('contacts')+with('primaryContact') when gate=true + through ObjectListItemData::fromModel($obj, $includeContacts)); `create(ObjectUpsertData $data, User $actor): CleaningObject` (transaction: create object, if gate=$actor->can('viewContacts', ...) then sync contacts via ContactCollectionSynchronizer), `update(CleaningObject $object, ObjectUpsertData $data, User $actor): CleaningObject` (transaction: update object, if gate then sync contacts else ignore $data->contacts), `deactivate(CleaningObject): void` (transaction: set is_active=false, log activity, leave contacts untouched), `reactivate(CleaningObject): void` (transaction: set is_active=true, log activity).
- `App\Enums\ObjectTypeEnum` (#[TypeScript], backed string) — `Office='office'`, `Apartment='apartment'`, `House='house'`, `CommonAreas='common_areas'`. Method: `label(): string` → `__('app.object_type_'.$this->value)`.
- `App\Contracts\ContactPayload` — interface with readonly promoted properties: `?string $id`, `string $name`, `?string $position`, `?string $email`, `?string $phone`, `bool $is_primary`. Implemented by `ObjectContactData` + `ClientContactData` (parity).
- `App\Services\ContactCollectionSynchronizer` (final readonly) — `sync(HasMany $relation, DataCollection<ContactPayload> $contacts, string $invalidMessageKey): void`. Idempotent synchronizer: loads existing by id, rejects foreign ids, soft-deletes outgoing, auto-promotes first to primary when none marked, upserts remainder. Shared by ClientService + ObjectService (one rule, two call sites).
- `App\Rules\AtMostOnePrimaryContact` — ValidationRule (message key in ctor), validates array of `is_primary` — rejects count > 1.

**Satellites (BE):**
- DTOs: `ObjectContactData` (#[TypeScript]: id?, name required Max(255), position? Max(255), email? Email Max(255), phone? Max(64), is_primary=false). `ObjectUpsertData` (**changed:** is_active now required, not defaulted; added `DataCollection<ObjectContactData> $contacts` field with `AtMostOnePrimaryContact('app.object_contacts_multiple_primary')` rule; mirrors ClientUpsertData). Rules: client_id Rule::exists scoped to current_tenant_id() + whereNull deleted_at (live clients only). `ObjectListItemData` (id, type, name, ?city, is_active, client_id, ?client_name, ?area_sqm, **?contacts_count** null if gated, **?primary_contact_email** null if gated, **?primary_contact_phone** null if gated, created_at; gate determined by caller's includeContacts param). `ObjectDetailData` (extends ListItemData + street, postal_code, country, access_code, key_box_code, key_count, special_instructions, floor, **?DataCollection $contacts** null if gated, gps columns omitted).
- Enums: ObjectTypeEnum (see Core).
- Factories: CleaningObjectFactory (default client_id via Client::factory(), `inactive()` state, `withContacts(int $count=1)` state via afterCreating hook, creates N, promotes first to primary). ObjectContactFactory (name/position/email/phone, is_primary=false; states: `primary()` sets is_primary=true).
- Seeders: ObjectSeeder (demo tenant, 1–3 per client with withContacts(random_int(1,2)), last client's inactive object gets 1 contact so deactivation losslessness is visible).
- Policies: `ObjectPolicy` — viewAny → ViewObjects; view → ViewObjects && isVisibleTo($user); create → CreateObjects; update → EditObjects && isVisibleTo; delete (gates deactivate) → DeleteObjects && isVisibleTo; **viewContacts(User $user): bool** → `$user->can(PermissionEnum::ViewAllObjects->value)` (field-level gate, no model param, no per-row query). Tenant isolation + **own-only scoping** (D2) via isVisibleTo. Contacts gated separately via viewContacts class method.
- Controllers: `ObjectController` (ctor ObjectService), actions: `#[Authorize('viewAny')] index(Request, User $user)` → `$includeContacts = $user->can('viewContacts', CleaningObject::class)` + pass to service → Objects/Index `[objects, filters, filterOptions: {clients}]` (clients empty for own-only actors → filter hidden, button disabled); `#[Authorize('view')] show(CleaningObject, User $user)` → load contacts conditionally + ObjectDetailData::fromModel($object, $includeContacts) → Objects/Show `[object, clients: ClientOptionData[], contracts: ContractListItemData[]|null, canCreateContract: bool]` (clients empty if !can('update') → edit hidden); `#[Authorize('create')] store(ObjectUpsertData, User $user)` → pass $user to service → show + flash; `#[Authorize('update')] update(ObjectUpsertData, CleaningObject, User $user)` → pass $user to service → show + flash; `#[Authorize('delete')] deactivate(CleaningObject)` (POST to deactivate endpoint) → show + flash; `#[Authorize('update')] reactivate(CleaningObject)` (POST to reactivate endpoint) → show + flash. Routes: GET /objects, GET /objects/{object}, POST /objects (Precognition), PUT /objects/{object} (Precognition), POST /objects/{object}/deactivate, POST /objects/{object}/reactivate (all `whereUuid`). `private clientOptions(User $actor): array` — ViewAllObjects actors get all clients; own-only get empty (D2).
- Navigation: #[NavItem] on index → `objects` nav (icon BuildingOffice2Icon, permission ViewObjects, order 31).

**Contact visibility gate (field-level, fail-closed per Q1 decision):**
- **Signal on wire:** `contacts: null` means "not permitted to view"; `contacts: []` or `contacts_count: 0` means "permitted, none exist". Gate computed once per request, applied in DTO factory, conditional eager load is optimization only (gated actor query never touches object_contacts table).
- **Write side:** ObjectService::create/update only syncs contacts when actor passes viewContacts gate; else $data->contacts ignored and existing rows left intact. Form payload always accepts (no client-side delete workaround; service owns the gate).
- **FE consequence:** Index: `contacts_count === null` is the only gate signal (two email/phone fields also nullable for ordinary reasons, never as gate). Column hidden entirely when gated. Detail: `contacts === null` → no card rendered; `contacts === []` → card shown with empty state.

**FE Satellites:**
- Composables: none new (shared ContactsListField accessed via Components/Contacts).
- Components: `ObjectTypeBadge` (badge + icon per type: office/apartment/house/common_areas mapped to different colours/icons), `ObjectStatusBadge` (active green / inactive ghost), `ObjectDetailCard` (type/status badges, ?client link, area/floor, address, created_at), `ObjectAccessCard` (warning-bordered card: access_code/key_box_code/key_count with sensitive warning + caption), **moved to Contacts/:** `ContactsList` (props contacts, title, emptyLabel; renders table of contact rows, primary badge, mailto/tel links), `ContactsListField` (props field; editor with add/remove rows, primary radio, Precognition re-validation on setPrimary), `contact.ts` (ContactRow type), `ObjectForm` (form: client SelectInput required, type/name, address fields, access section, key_count/special_instructions, **contacts section** v-if canSeeContacts computed = allows('view all objects'), is_active toggle on edit), `ObjectFormDrawer`, `objectPayload.ts` helper (ObjectFormData interface incl. contacts: ContactRow[], function to map DetailData → payload incl. string→null conversion + contacts ??: [] to handle gated actors).
- Pages: `Objects/Index` (DataTable: columns name/type/client_name→link/city/**primary_contact_email** (shows email/phone, hidden when gated), area_sqm/is_active; filters search/name/type/client_id/is_active/city/created_at; sort name/type/city/is_active/created_at; info hint when !ViewAllObjects; empty state; buttons view/deactivate when active; create drawer pre-scoped if single client; create hidden when no client list). `Objects/Show` (header + edit/deactivate buttons; inactive banner with reactivate button when is_active=false; grid: left ObjectAccessCard+instructions, right ObjectDetailCard + **ContactsList card** v-if object.contacts!==null; deactivate/reactivate modals; edit drawer).
- i18n: existing object_* keys + new `object_contacts` (sk: "Kontaktné osoby"), `object_no_contacts` (sk: "Žiadne kontaktné osoby."), `object_contact_invalid` (validation: "Kontaktná osoba nepatrí tomuto objektu."), `object_contacts_multiple_primary` (validation: "Iba jedna kontaktná osoba môže byť hlavná."); shared with clients: `contact_add` (sk: "Pridať kontaktnú osobu"), `contact_remove` (sk: "Odstrániť kontakt"), `contact_is_primary` (sk: "Hlavný kontakt"), `name`, `position`, `email`, `phone`, `empty_dash` (sk/en/uk parity, 1100 keys each).

**Flow:**
- **List:** GET /objects (actor) → ObjectController@index + compute gate + visibleTo($actor) + AllowedFilter → Objects/Index {objects[...ListItemData with contacts_count=null when gated], filters, filterOptions: {clients}}. Own-only actors: clients = [], info hint shown, button hidden, primary_contact_email column absent. Table rows: view → Show; deactivate button (active only) → deactivate confirm modal.
- **Show:** GET /objects/{object} → compute gate, conditional load contacts, RMB TenantScope + Policy::view isVisibleTo gate → Objects/Show {object: DetailData(contacts=null when gated), clients: empty if !can('update'), ContactsList when contacts!==null}. Edit drawer (clients list provided, contacts editor shown if gate=true and readonly placeholder if gate=false). Reactivate button (is_active=false) → PUT /objects/{id} with full payload + is_active=true.
- **Create:** POST /objects (Precognition) → ObjectService::create(data, $actor) inside transaction, sync contacts if gate, return object + load relations → 302 index + flash.
- **Update:** PUT /objects/{object} (Precognition) → ObjectService::update(object, data, $actor) inside transaction, sync contacts if gate (else ignore payload), return object + load relations → 302 show + flash.
- **Deactivate:** POST /objects/{object}/deactivate → ObjectPolicy::delete gate + isVisibleTo → ObjectService::deactivate (is_active=false, activity logged, contacts untouched).
- **Reactivate:** POST /objects/{object}/reactivate → ObjectPolicy::update gate + isVisibleTo → ObjectService::reactivate (is_active=true, activity logged, contacts untouched).

**Cascade:**
- **Client delete:** ClientService::delete loads objects.contacts (nested eager load), soft-deletes each contact in transaction, then soft-deletes object.
- **Object deactivate/reactivate:** is_active orthogonal to deleted_at; contacts untouched, lossless.
- **Contact removed from payload:** soft-deleted (not hard-deleted), parity with clients.

**Depends on:** tenancy (tenant_id FK, BelongsToTenant, TenantScope), clients (client_id FK + Rule::exists to live clients, client() withTrashed relation, ClientService::delete soft-deletes objects + object contacts as cascade).

**Depended on by:** quotes (cleaning_object_id FK, ObjectBelongsToClient rule; convertToContract requires it), invoices + recurring-invoices (cleaning_object_id FK, object snapshot), contracts (service-agreement contractable via morph alias `cleaning_object`; `CleaningObject::contracts()` MorphMany; `object.*`/`client.*` document tokens; ContractController::formContext lists active objects), schedule (ScheduledJob.object_id FK, visibleTo reachability), ContractController::edit keeps the contract's current object in the picker even if inactive. ObjectPolicy::viewContacts gating pattern mirrors approach in dashboard + notifications phase 8+ (fail-closed field-level gates).

**If you change Core, check:**
- ObjectContact model: SoftDeletes + LogsActivity parity with ClientContact; indexes on (cleaning_object_id, is_primary) for list columns; foreign key restrictOnDelete (no DB cascade, soft cascade in service).
- ObjectService signatures: `paginate(Request, User $actor)`, `create(ObjectUpsertData, User $actor)`, `update(..., User $actor)` all require actor param for gate computation.
- ObjectPolicy::viewContacts: method signature (no model param), gate = ViewAllObjects exactly (no isVisibleTo re-check; validation-surface ownership: one owner per rule).
- ObjectDetailData::fromModel / ObjectListItemData::fromModel: both require `bool $includeContacts` param, not defaulted (fail-closed by making omission impossible to forget).
- ContactCollectionSynchronizer::sync callers: ObjectService::create/update (when gate) + ClientService (unchanged in phase-3, now shared). One rule (at most one primary, soft-delete outgoing), two call sites.
- ObjectService::deactivate/reactivate: no contact mutations (orthogonal).
- ClientService::delete: loads nested objects.contacts before cascade; each contact soft-deleted in transaction.
- ObjectController store/update: new User $actor parameter passed through.

**Keywords (SK):** objekt, lokalita, kancelária, byt, dom, spoločné priestory, prístupový kód, kľúč, deaktivácia, citlivé údaje, kontakty, správca, domovník, recepcia, kontakt na site, fail-closed gate, ViewAllObjects permission.

### quotes — Itemized + document quotes, clientless snapshot, status lifecycle, convertToInvoice (Phase 5, 2026-09-06)

**Core:**
- `App\Models\Quote` — UUIDv7; traits BelongsToTenant, HasFactory, HasPdfFilename, HasUuids, LogsActivity (logOnly status/kind/number/total/client_id/cleaning_object_id), SoftDeletes. Columns: tenant_id FK, nullable client_id FK → clients (withTrashed), nullable cleaning_object_id FK → objects (withTrashed), status (QuoteStatusEnum: draft/sent/accepted/rejected/expired), kind (QuoteKindEnum: itemized/document, immutable after create), nullable number (50 chars, partial unique index tenant_id + number WHERE deleted_at IS NULL AND number IS NOT NULL), nullable subject (255), issue_date (date), valid_until (date), nullable sent_at/accepted_at/rejected_at (timestamp), bool is_vat_payer, nullable vat_rate (decimal:5,2), currency (CurrencyEnum, default EUR), money columns (subtotal/vat_amount/total default 0, all decimal:12,2), json vat_breakdown nullable, **clientless snapshot** (customer_name nullable, customer_email/customer_street/customer_city/customer_postal_code nullable, mutually exclusive with client_id via DTO `prohibits`), nullable note (text), timestamps, soft_deletes. Indexes (tenant_id,status), (tenant_id,kind), (tenant_id,client_id), (tenant_id,valid_until). Relations: `client()->withTrashed()`, `cleaningObject()->withTrashed()`, `items(): HasMany<QuoteItem>` ordered position, `invoices(): HasMany<Invoice>` (quote_id FK, re-conversion allowed → many invoices per quote). Methods: `isEditable(): bool` (Draft), `canBeConverted(): bool` (Accepted), `isDocument(): bool` (kind === document). No longer HasMedia (2026-10-31): `document(): MorphOne<Document>` holds the optional text (type quote) and the uploaded file (Document media collection `file`, disk config('documents.disk')); `contracts(): HasMany`. See domains documents + quotes (text documents).
- `App\Models\QuoteItem` — UUIDv7; traits BelongsToTenant, HasFactory, HasUuids. Columns: tenant_id FK, quote_id FK (restrictOnDelete), description (255), frequency nullable (50, quote-only for phase 7 work-breakdown generation), quantity/unit_price (decimal:10,2), unit nullable (50), discount_percent/vat_rate (decimal:5,2), computed line_base/line_vat/line_total (decimal:12,2), position (smallInt). Index quote_id. Relation: `quote(): BelongsTo`.
- `App\Enums\QuoteStatusEnum` (#[TypeScript], backed string) — Draft|Sent|Accepted|Rejected|Expired; `canTransitionTo(self): bool` matrix; `label(): string`.
- `App\Enums\QuoteKindEnum` (#[TypeScript], backed string) — Itemized|Document; `label(): string`.
- `App\Services\QuoteService` (final readonly, ctor InvoiceService, TemporaryUploadService, DocumentTotalsCalculator, DatabaseManager, QuoteTextService, DocumentTemplateRenderer, DocumentHtmlSanitizer, DocumentTemplateCatalogue) — `paginate(Request): LengthAwarePaginator<QuoteListItemData>` (QueryBuilder AllowedFilter search across number/subject/customer_name/client.name + dynamic filters status/kind/client_id/issue_date/valid_until/total/created_at, default -created_at, eager load client/object/media), `create(QuoteUpsertData, ?User, string $sessionId): Quote` (transaction: itemized → syncItems + computeTotals; document + document_uuid → moveToModel), `update(Quote, QuoteUpsertData, ?User, string $sessionId): Quote` (guard isEditable), `send(Quote): Quote` (guard status transition + document kind; **422 on `status` while the text document has unresolved tokens**; transaction: resolves + sanitizes + freezes the text body, status Sent, dispatch QuoteSent), `accept/reject(Quote): Quote` (status guard + document guard; stamp accepted_at/rejected_at), `attachClient(Quote, string $clientId, ?string $objectId): Quote` (guard client_id IS NULL; nulls 5 snapshot columns; ObjectBelongsToClient rule), `duplicate(Quote): Quote` (transaction: Draft copy, number=null, issue_date=today, valid_until=today+config('quotes.default_validity_days'), copy items; document kind → copy the file media onto the new Document; itemized → copyTextDocument: Draft source copies the body, otherwise reseeds from the template if it still exists, else no text document), `delete(Quote): void` (guard isEditable; soft-deletes the Quote and its Document), `convertToInvoice(Quote): Invoice` (guard document kind + Accepted status; build InvoiceUpsertData mapping items with frequency suffix, snapshot clientless data if client_id IS NULL; transaction: create invoice via InvoiceService, update invoice.quote_id).
- `App\Services\DocumentTotalsCalculator` (final readonly, shared with invoices) — `line(qty, unitPrice, discountPercent, vatRate, isVatPayer): array{line_base, line_vat, line_total}` (base = round2(qty×price×(1−d/100)); vat = round2(base×rate/100, 0 if !payer); total = round2(base+vat)); `totals(lines iterable, isVatPayer, roundingMode=None): array{subtotal, vat_amount, total, rounding_amount, vat_breakdown|null}` (byte-for-byte invoice math, breakdown sorted rate desc, null when non-payer or empty).
- Contracts: `RendersQuotePdf { public function render(Quote $quote): string; }`.
- `App\Services\Pdf\QuotePdfService implements RendersQuotePdf` — loadMissing(['items','client','cleaningObject']); supplier from Tenant::withoutGlobalScopes; render `pdf.quotes.default` via chrome driver.
- `App\Console\Commands\ExpireQuotes` (app:expire-quotes, routes/console.php daily) — withoutGlobalScope TenantScope, itemized only, Draft|Sent past valid_until → Expired + QuoteExpired event; foreach config('quotes.expiring_notice_days') → QuoteExpiring event with daysLeft.

**Satellites (BE):**
- Enums: QuoteStatusEnum, QuoteKindEnum (see Core).
- DTOs: `QuoteItemData` (id?, description required, frequency? max 50, quantity≥0, unit?, unit_price≥0, discount_percent 0–100, vat_rate≥0, line_*?); `QuoteUpsertData` (#[MergeValidationRules], client_id XOR customer_name/email/street/city/postal_code via prohibits+required_without; cleaning_object_id; number unique-per-tenant; issue_date; valid_until≥issue_date; kind enum immutable on edit via Rule::in; subject?; note?; items required+min:1 for itemized, prohibited for document; document_uuid required for document kind on create, nullable on edit; currency enum; rules: ObjectBelongsToClient, OwnedTemporaryMedia, TemporaryMediaConstraints); `QuoteAttachClientData` (client_id required exists, object_id? exists + ObjectBelongsToClient); `QuoteListItemData` (id, number?, status, kind, subject?, customer_name = client?->name ?? $snapshot, client_id?, object_name?, currency, total formatted, issue_date, valid_until, has_document); `QuoteDetailData` (all scalars + client/object names + itemized QuoteItemData[] + VatBreakdownLineData[] + ?MediaFileData document + QuoteInvoiceLinkData[] invoices); `QuoteInvoiceLinkData` (id, number?, status InvoiceStatusEnum); `QuoteFormContextData` (ClientOptionData[] clients, ObjectOptionData[] objects, is_vat_payer, ?vat_rate, vat_rate_options from config('invoicing.vat_rates'), default_validity_days from config('quotes.default_validity_days')); `MediaFileData` (uuid, file_name, ?mime_type, size, download_url).
- Policies: QuotePolicy (viewAny/view/downloadPdf → ViewQuotes; create/duplicate → CreateQuotes; update/attachClient → EditQuotes; delete → DeleteQuotes; send → SendQuotes; accept/reject → ApproveQuotes; convertToInvoice → CreateInvoices — note Sekretárka lacks CreateInvoices by seed, hence cannot convert). Permission-only (state guards in service → 422).
- Controllers: QuoteController (use ProvidesSubjectOptions), #[NavItem] quotes index (DocumentDuplicateIcon, ViewQuotes, order 35). Routes: GET /quotes (index), GET /quotes/create, POST /quotes (Precognition), GET /quotes/{quote}, GET /quotes/{quote}/edit, PUT/PATCH /quotes/{quote} (Precognition), DELETE /quotes/{quote}, POST /quotes/{quote}/{send|accept|reject|duplicate} (action routes), POST /quotes/{quote}/attach-client (Precognition), POST /quotes/{quote}/convert-to-invoice, GET /quotes/{quote}/pdf (document: stream from media; itemized: render PDF). All routes whereUuid, inside auth+tenant.required.
- Events: `QuoteSent(string $tenantId, string $quoteId)`, `QuoteExpired(string $tenantId, string $quoteId)`, `QuoteExpiring(string $tenantId, string $quoteId, int $daysLeft)` — `implements ShouldDispatchAfterCommit` (zero listeners phase 5; phase 5 notifications subscribes).
- Factories: QuoteFactory (states numbered, sent, accepted, rejected, expired, vatPayer, withoutClient, document, forClient, forObject), QuoteItemFactory.
- Rules: `ObjectBelongsToClient` (DataAwareRule, reads client_id from payload; null passes; validates object belongs to client or 422 object_requires_client / object_not_of_client); `TemporaryMediaConstraints` (validates mime in whitelist + size ≤ KB from config('quotes.document.allowed_mimes'|max_size_kb), reads Media.mime_type which Spatie sniffed by content at upload; errors quote_document_invalid_type / quote_document_too_large).
- Config: `config/quotes.php` (document.disk default 'local'= storage/app/private, document.max_size_kb 10240, document.allowed_mimes, default_validity_days 30, expiring_notice_days [7,3,1]).

**FE Satellites:**
- Composables: none (form state via useForm).
- Components: `QuoteStatusBadge`, `QuoteKindBadge`, `QuoteRoughBadge` (badge for clientless), `QuoteDocumentUpload` (FileUploadInput + info; accepts pdf/image; v-model document_uuid), `QuoteAttachClientPanel` (appears only when client_id === null; form to attach + optional object; populated with ClientOptionData[] + ObjectOptionData[]), `QuoteItemsEditor` (reuses InvoiceItemsEditor pattern; adds optional frequency column), `QuoteSubjectPicker` (client/object/clientless radios; object options filtered by client), `QuoteFormSummary` (summary panel: dates + totals + VAT recap).
- Pages: `Quotes/{Index,Create,Edit,Show}` (Index = DataTable with filters/sort; Create = form; Edit = form + attach panel if clientless; Show = detail + attach panel if clientless + related invoices section).
- i18n: sk/en/uk keys (quotes, quote_status_*, quote_kind_*, quote_created/updated/sent/accepted/rejected/duplicated/client_attached/converted_to_invoice/deleted, quote_not_editable, quote_invalid_transition, quote_not_acceptable_for_conversion, quote_already_has_client, quote_document_*, object_requires_client, object_not_of_client).

**Flow:**
- **Create itemized:** POST /quotes (QuoteUpsertData: client or snapshot; itemized items + dates) → QuoteService::create (syncItems → computeTotals) → 302 show + flash.
- **Create document:** POST /quotes (kind=document, document_uuid staged) → moveToModel onto the quote's Document (`file`) → documents disk → 302 show.
- **Send:** POST /quotes/{id}/send (Draft/Sent) → status validation → dispatch QuoteSent event → 302 show.
- **Accept/Reject:** POST /quotes/{id}/accept|reject (Draft/Sent) → stamp accepted_at / rejected_at.
- **Attach client:** POST /quotes/{id}/attach-client (while client_id IS NULL, any status) → null 5 snapshot columns → 302 show.
- **Duplicate:** POST /quotes/{id}/duplicate (Draft only) → copy as Draft, number=null, items copied → 302 edit.
- **Delete:** DELETE /quotes/{id} (Draft only) → soft-delete.
- **Convert to invoice:** POST /quotes/{id}/convert-to-invoice (Accepted, not document) → build InvoiceUpsertData with frequency suffix on items, snapshot if clientless → InvoiceService::create → link quote_id → 302 invoices.show.
- **PDF:** GET /quotes/{id}/pdf (document: stream stored media; itemized: render via chrome driver) → Content-Disposition attachment.

**Depends on:** tenancy (tenant_id FK, BelongsToTenant, TenantScope), clients (client_id FK, withTrashed relation), objects (object_id FK + ObjectBelongsToClient rule, withTrashed relation), media (Quote HasMedia, private DOCUMENTS_DISK, temporary upload → moveToModel contract), invoices (convertToInvoice creates invoice, links quote_id).

**Depended on by:** invoices (quote_id FK, source link in show), contracts phase 6 (convertToContract, links via quote_id deferred).

**If you change Core, check:**
- QuoteStatusEnum transitions: Draft→{Sent, Expired}; Sent→{Accepted, Rejected, Expired}; Accepted/Rejected/Expired terminal. Document kind has no lifecycle (all actions blocked except view/pdf/delete).
- QuoteService::convertToInvoice: frequency suffix mapping ("item (frequency)" on convert), clientless snapshot carried, ItemizedOnly guard, AcceptedStatus guard.
- DocumentTotalsCalculator parity: invoice + quote math identical (line, total with VAT breakdown, rounding modes match).
- QuoteItem.frequency column: phase 7 consumer (work breakdown generation from accepted quotes).
- Media::move honours collection disk: staged file on public temp disk → moves to private DOCUMENTS_DISK on moveToModel.
- TemporaryMediaConstraints: validates against Media.mime_type (content-sniffed at upload, not filename).
- Precognition on cross-field rules (client_id + customer_name mutually exclusive, kind immutable): cross-field validation deferred to submit unless full payload sent.
- ExpireQuotes command: unbound iteration with SoftDelete scope exclusion (withoutGlobalScope), per-quote event dispatch.

**Keywords (SK):** cenová ponuka, nástrel, položková, dokumentová, platná do, prijatá, konverzia, dobropis, súpis.

### invoices — Draft / issue / pay / cancel + credit notes, SK VAT, Pay-by-Square QR (Phase 4, 2026-09-06; payments, bulk, EFA, Pohoda export added by 2026-10-31 reconcile)

**Core:**
- `App\Models\Invoice` — UUIDv7; traits BelongsToTenant, HasFactory, HasPdfFilename, HasUuids, LogsActivity (logOnlyDirty status/number/total/client_id/issued_at/paid_at/cancelled_at/sent_at), SoftDeletes. Columns: tenant_id FK, nullable client_id FK → clients (withTrashed), nullable cleaning_object_id FK → objects (withTrashed), nullable recurring_invoice_id FK → recurring_invoices (restrictOnDelete), nullable credited_invoice_id self-FK (restrictOnDelete), **nullable quote_id FK → quotes (nullOnDelete)** (audit backlink when generated from quote.convertToInvoice; invoice keeps snapshot so delete doesn't cascade; index quote_id), type (InvoiceTypeEnum: monthly/one_off/special), status (InvoiceStatusEnum: draft/issued/paid/overdue/cancelled), template (InvoiceTemplateEnum: classic/modern/minimal), nullable number (50 chars, partial unique index tenant_id + number WHERE deleted_at IS NULL AND number IS NOT NULL), variable_symbol (10 chars, derived from number digits only), dates (period_from/period_to/issue_date/delivery_date/due_date nullable except issue_date), timestamps issued_at/sent_at/paid_at/cancelled_at nullable, **customer snapshot** (customer_name required, representative/ico/dic/vat_number/street/city/postal_code/country/email nullable), **object snapshot** (object_name/street/city/postal_code nullable), **supplier snapshot** (supplier_name required, ico/dic/vat_number/iban/swift/address_line/city/postal_code/country/contact_email/contact_phone/registration_info nullable, snapshot at issue from Tenant), **signature snapshot:** nullable `supplier_signature_media_id` (bigint FK → media, nullOnDelete) stamped at issue from `tenants.signature_media_id` (draft resolves tenant's current signature live; issued renders snapshot forever), **issuer snapshot:** nullable `issued_by_name` (string 255) stamped at issue from actor's TenantMembership.display_name or __('app.invoice_issued_by_system') for unattended jobs, bool is_vat_payer, nullable vat_rate (decimal), money columns (subtotal/vat_amount/total/deposit/rounding_amount default 0, all decimal:12,2), json vat_breakdown (nullable, frozen at issue), **SK fields** (constant_symbol/specific_symbol/payment_type/currency/rounding_mode/header_text/footer_text all nullable except currency EUR default), nullable note, **payments:** `paid_amount` decimal(12,2) default 0 (sum of invoice_payments, maintained by InvoiceService::recomputePaymentState), **e-invoice (EFA/Peppol):** `efa_status` (EfaStatusEnum none/ready/generated/submitted/accepted/rejected/failed, NOT NULL default 'none'), efa_id, efa_submitted_at, efa_last_error, customer_peppol_participant_id (snapshot from client at create), supplier_peppol_participant_id (snapshot from tenant), timestamps, soft_deletes. Indexes (tenant_id,status), (tenant_id,due_date), (tenant_id,issue_date), (tenant_id,client_id), recurring_invoice_id, credited_invoice_id, quote_id. Relations: `client()->withTrashed()`, `cleaningObject()->withTrashed()`, `items(): HasMany<InvoiceItem>` ordered position, `payments(): HasMany<InvoicePayment>`, `quote(): BelongsTo<Quote>` (nullable, audit trail), `creditedInvoice(): BelongsTo`, `creditNote(): HasOne credited_invoice_id`, `recurringInvoice(): BelongsTo`, `supplierSignatureMedia(): BelongsTo<Media>`. Attributes/methods: `balance_due` (total − deposit − paid_amount), `amount_payable` (total − deposit), `isEditable()` (Draft), `canBeCancelled()` (Issued | Overdue), `isCreditNote()`, `isPartiallyPaid()`, `isSettled()` (|balance_due| < 0.005). Implements HasMedia: collection `efa_xml` on config('invoicing.signature.disk'). LogsActivity also logs efa_status.
- `App\Models\InvoicePayment` — UUIDv7, BelongsToTenant, LogsActivity, SoftDeletes. Columns: tenant_id, invoice_id (restrictOnDelete), amount decimal(12,2) (negative for credit-note balances), paid_on date, payment_type (PaymentTypeEnum), source (PaymentSourceEnum manual|bank_import, default manual), external_reference (128 nullable), note, recorded_by_user_id (nullable FK users restrictOnDelete). Indexes (tenant_id, invoice_id), (tenant_id, paid_on). Relations invoice, recordedBy.
- `App\Models\InvoiceItem` — UUIDv7; traits BelongsToTenant, HasFactory, HasUuids. Columns: tenant_id FK, invoice_id FK (restrictOnDelete), description, qty/unit/unit_price (decimal:10,2), discount_percent (decimal:5,2), vat_rate (decimal:5,2), computed line_base/line_vat/line_total (decimal:12,2), position (smallInt). Index invoice_id. Relation: `invoice(): BelongsTo`.
- `App\Models\InvoiceNumberSequence` — UUIDv7; traits BelongsToTenant, HasUuids. Columns: tenant_id FK, year (smallInt), last_number (int), timestamps. Unique (tenant_id, year). No activity logging (operational).
- `App\Services\InvoiceNumberService` — `next(Tenant, DateTimeInterface): string` (firstOrCreate + lockForUpdate + collision loop honouring `{YYYY}/{YY}/{MM}/{X+}` format placeholders), `variableSymbol(string): ?string` (digits max 10 chars).
- `App\Services\InvoiceService` (final readonly, ctor InvoiceNumberService, DatabaseManager, DocumentTotalsCalculator) — `paginate(Request): LengthAwarePaginator<InvoiceListItemData>` (QueryBuilder AllowedFilter search/number/status/type/client_id/customer_name/issue_date/due_date/total/created_at, sorts default -created_at, eager load client), `stats(): InvoiceStatsData` (issued this month / overdue / pending / recurring monthly), `create(InvoiceUpsertData): Invoice` (transaction: snapshot customer/object/supplier + syncItems via DocumentTotalsCalculator → computeTotals with VAT breakdown + rounding), `update(Invoice, InvoiceUpsertData): Invoice` (guard isEditable), `issue(Invoice, InvoiceIssueData, ?User $actor): Invoice` (guard Draft → guard supplier completeness: `$tenant->missingSupplierFields() !== []` → ValidationException key `supplier` + __('app.invoice_supplier_incomplete') before transaction, no sequence consumed; then tenant-scoped number exists check, set number/variable_symbol/status/issued_at/**supplier_signature_media_id from $tenant->signature_media_id**/**issued_by_name from actor's TenantMembership.display_name or __('app.invoice_issued_by_system')** via private `resolveIssuerName(string $tenantId, ?User $actor): string` helper), **`recordPayment(Invoice, InvoicePaymentData, ?User $actor): InvoicePayment` (replaces markPaid: guard canTransitionTo(Paid), lockForUpdate, amount > 0 and ≤ |balance_due| else 422 `amount`, create InvoicePayment source=manual, recomputePaymentState → paid_amount; settled → status Paid + paid_at)**, **`deletePayment(Invoice, InvoicePayment): void`** (422 when Cancelled; recompute; Paid reverts to Issued/Overdue by due_date when no longer settled), `cancel(Invoice, ?User $actor): Invoice` (transaction: mark Cancelled + create credit note with negated items/SK fields/**+ stamp both supplier_signature_media_id and issued_by_name on credit note** since credit note is born Issued with own number), `duplicate(Invoice): Invoice` (Draft copy, no number, **signature_media_id and issued_by_name both null** so draft resolves live), `delete(Invoice): void` (guard isEditable), `send(Invoice): void` (guard Issued + customer_email, dispatch InvoiceIssued queued notification).
- `App\Services\InvoiceBulkService` (final readonly; InvoiceService, RendersInvoicePdf, MergesPdfDocuments) — `pay(ids, paidOn, ?User)` (one full-balance recordPayment per invoice), `send(ids)`, `issue(ids, ?User)` (per-item ValidationException → BulkSkippedItemData with BulkSkipReasonEnum not_found/not_issued/no_email/not_draft/not_payable/supplier_incomplete/number_taken; returns BulkActionResultData), `mergedPdf(ids): string` (renders each via RendersInvoicePdf, single → as is, many → `MergesPdfDocuments::merge`). Limits config('invoicing.bulk'): max_ids 100, pdf_max_ids 10, merge_timeout_seconds 60, qpdf_binary 'qpdf' (exposed to FE as `bulkLimits` prop = InvoiceBulkLimitsData).
- `App\Services\Pdf\QpdfMerger implements MergesPdfDocuments` — shells out to the `qpdf` binary (installed in the app image, commit cdeed4e), temp files cleaned in finally, failure → PdfMergeFailedException. Bound in AppServiceProvider.
- `App\Services\Efa\EInvoiceService` (UblInvoiceXmlBuilder, SubmitsEInvoices, DatabaseManager) — `status(Invoice): EInvoiceStatusData` (readiness missing fields, canSubmit only when a non-null access point is bound), `xml(Invoice): string` (422 if Draft or not ready; flips efa_status none→ready), `submit(Invoice): Invoice` (lockForUpdate + efa_status→Submitted before provider call, restores on ValidationException, Failed + efa_last_error on Throwable, on success stores XML into media `efa_xml` and sets efa_id/efa_submitted_at). `SubmitsEInvoices` bound by `config('efa.driver')` (default `null` → NullEInvoiceAccessPoint; no live provider yet). `UblInvoiceXmlBuilder` builds Peppol BIS 3.0 UBL using config/efa.php maps (unit codes, payment means, participant schemes, mandatory_from 2027-01-01).
- `App\Services\Export\PohodaXmlBuilder` — `export(Tenant, PohodaExportData): string`, `preview(...): PohodaExportPreviewData`, `build(...)`; config/pohoda.php (max_invoices 2000, max_range_days 366, vat_band_map 23/19/5/0). Controllers: InvoiceExportController (`export` ability), InvoiceBulkController (bulkPay/bulkSend/bulkIssue/bulkPdf), EInvoiceController (downloadEfaXml → ViewInvoices, submitEfa → EditInvoices).
- `App\Services\InvoiceSettingsService` — see settings-invoicing.
- Contracts: `RendersInvoicePdf { public function render(Invoice $invoice): string; }`, `GeneratesPaymentQr { public function dataUri(Invoice $invoice): ?string; }`, **`ResolvesSupplierSignature { public function forInvoice(Invoice): ?string; public function forTenant(Tenant): ?string; }`** (returns base64 data:image/* URI or null, never calls current_tenant_id(), fail-closed on tenant_id mismatch or missing file).
- `App\Services\Pdf\InvoicePdfService implements RendersInvoicePdf` — chrome driver, Blade-based 3 templates (Classic/Modern/Minimal). **Classic alone rewritten with two-column dotted grid layout** (header: supplier left + bank fields, customer right + title + dates; items table full-width; note + QR/payment-strip grid; inline signature block at bottom). **Modern/Minimal unchanged, both include `pdf.invoices.partials.signature` partial**. Renders with `signatureDataUri` (resolved via ResolvesSupplierSignature::forInvoice). **Classic uses footerView('pdf.invoices.classic-footer')** with `margins(top: 12, right: 15, bottom: 22, left: 15 mm)` for full-width 2-row footer (bold "Vystavil:" label + phone/email inline SVG icons + dotted rule + page number right). **headerHtml('<div></div>')** suppresses Chromium's default header (date + title) when footer is set.
- `App\Services\Pdf\PayBySquareService implements GeneratesPaymentQr` — null unless Issued|Overdue + IBAN + EUR + balance_due > 0.
- **`App\Services\Pdf\SupplierSignatureResolver implements ResolvesSupplierSignature`** (final readonly, no constructor; uses Storage and Log facades) — `forInvoice($invoice)` loads media by `supplier_signature_media_id` with `collection_name = 'signature'` filter, or falls back to Tenant's signatureMedia (draft resolves live, issued uses snapshot) + encodes to data URI; `forTenant($tenant)` loads media from `$tenant->signatureMedia`; **private `encode(?Media, string $expectedTenantId)` fails closed:** null media → null, tenant_id mismatch → warn + null, file missing → warn + null, else base64 data URI. Bound in AppServiceProvider.
- `App\Notifications\InvoiceIssued` — queued (ShouldQueue, #[Tries(3)] #[Backoff] #[Timeout(120)]), afterCommit, mail-only, PDF attachment via RendersInvoicePdf, sent_at stamped by StampInvoiceSentAt listener on NotificationSent event.
- `App\Events\InvoiceMarkedOverdue` — domain event dispatched by MarkOverdueInvoices command (no phase-4 listeners; phase-5 notifications subscribes).
- `App\Console\Commands\MarkOverdueInvoices` — daily cron, flips Issued past due_date → Overdue, dispatches InvoiceMarkedOverdue.

**Satellites (BE):**
- **Helpers:** `format_quantity(float $quantity): string` — trims trailing zeros off 2-dp quantity: `111.00` → `111`, `1.50` → `1,5`, `0.25` → `0,25` (NBSP thousands separator per SK convention). Declared in `app/Support/helpers.php`, testable unit function (not inline blade), reusable once modern/minimal templates adopt quantity trimming.
- Enums: InvoiceStatusEnum (draft/issued/paid/overdue/cancelled, `canTransitionTo(self $to): bool`), InvoiceTypeEnum (monthly/one_off/special), InvoiceTemplateEnum (classic/modern/minimal, `view(): string`, **`footerView(): ?string`** → 'pdf.invoices.classic-footer' for Classic else null), PaymentTypeEnum (transfer/cash/card/cod/other), **CurrencyEnum** (EUR/CZK/USD uppercase, **`symbol(): string`** → '€'/'Kč'/'$', **`format(float $amount, int $maxDecimals = 2): string`** → `2 011,88 €` via number_format + NBSP separator + suffix, significant decimals preserved up to maxDecimals), RoundingModeEnum (none/document/cash_005, `round(float): float`).
- DTOs: `InvoiceUpsertData` (client_id nullable, object_id nullable, type/template/dates, customer_* snapshot fields, items, SK fields, payment fields, deposit), `InvoiceItemData` (description/qty/unit/unit_price/discount/vat_rate + line_* nullable), `InvoiceIssueData { ?string $number }`, `InvoiceListItemData` (id/number/status/type/customer_name/client_id/client_name/object_name/currency/total/balance_due/issue_date/due_date), `InvoiceDetailData` (full DTO + vat_breakdown array + qr_data_uri + `quote_id: string|null` + `quote_number: string|null` (backlink to source quote if generated via convertToInvoice) + `supplier_missing_fields: string[]` empty when Issued or status terminal, non-empty on Draft; vocabulary keys: name/address_line/city/postal_code/ico/dic/vat_number), `InvoiceSupplierData`, `VatBreakdownLineData`, `InvoiceStatCardData`, `InvoiceStatsData`, `InvoiceFormContextData` (clients/objects/is_vat_payer/defaults/recurring_default_state + `supplier_missing_fields: string[]` from tenant; mounted in Create/Edit/Show forms for banner), `InvoiceSettingsData` (supplier name/ico/dic/vat_number/is_vat_payer/address/contact + invoice defaults template/number_format + recurring defaults; **new:** `?string $signature_uuid`, `bool $remove_signature = false` mutually exclusive via `prohibited_if` validation), **`TenantSignatureData` (#[TypeScript], nullable on fromTenant)** (url: string route('settings.invoicing.signature'), file_name, mime_type, size, uploaded_at ISO 8601), **`InvoiceSignatureConstraintsData` (#[TypeScript])** (allowed_mimes: string[], max_size_kb: int, static fromConfig() reads config('invoicing.signature.*')), `ObjectOptionData` (for pickers).
- Policies: InvoicePolicy (viewAny/view/create/update/issue/**recordPayment/deletePayment**/send/cancel/delete/downloadPdf/**downloadEfaXml/submitEfa/bulkPay/bulkSend/bulkIssue/bulkPdf/export** per PermissionEnum — recordPayment/submitEfa/bulk write abilities need EditInvoices, read-type ones ViewInvoices).
- Controllers: InvoiceController (#[NavItem] invoices), InvoiceSettingsController (#[NavItem] invoicing_settings, ManageBillingSettings gate).
- Routes: GET|POST /invoices; GET|PUT|DELETE /invoices/{invoice}; POST /invoices/{invoice}/issue|pay (pay = recordPayment, InvoicePaymentData)|cancel|duplicate|send; DELETE /invoices/{invoice}/payments/{payment} (scopeBindings); GET /invoices/{invoice}/pdf; GET /invoices/{invoice}/efa-xml, POST /invoices/{invoice}/efa-submit; GET /invoices/export/pohoda{,/preview}; GET /invoices/bulk/pdf, POST /invoices/bulk/pay|send|issue (Precognition); GET|PUT /settings/invoicing (Precognition, DTO now includes signature_uuid + remove_signature); **GET /settings/invoicing/signature** (streams file inline from private disk, #[Authorize(ManageBillingSettings)], 404 if no signature or file missing); **GET /settings/invoicing/preview/{template}** (#[Authorize(ManageBillingSettings)], inlines tenant signature as data URI in footer, matches signature stream gate; **renders footer when `$template->footerView()` present**).
- Commands: MarkOverdueInvoices (routes/console.php), GenerateRecurringInvoices (routes/console.php).
- Factories: InvoiceFactory (states forClient/forObject/issued/paid/overdue/cancelled/vatPayer/nonVatPayer/withDeposit), InvoiceItemFactory.
- i18n keys: nav (invoices, invoicing_settings); enums; flash (invoice_created/updated/issued/paid/cancelled/duplicated/send_queued); errors (invoice_not_editable, invoice_not_draft, invoice_number_taken, invoice_cannot_mark_paid, invoice_cannot_cancel, invoice_no_customer_email, invoice_object_requires_client); PDF (40 keys: invoice_pdf_{title,customer,supplier,ico,…,payment_type,note,non_vat_payer_clause}), mail (subject/greeting/body).

**FE Satellites:**
- Composables: `useInvoiceTotals(items, isVatPayer, deposit, roundingMode)` → lines/subtotal/vatAmount/total/roundingAmount/balanceDue/vatBreakdown (mirrors BE math with RoundingModeEnum::round).
- Components: `InvoiceStatusBadge`, `InvoiceTypeBadge` (with credit-note variant), `InvoiceVatRecap`, `InvoiceTotalsPanel`, `InvoiceItemsEditor` (card-per-row custom field, FormProvider-aware, per-row quantity/unit/unit_price/discount/vat_rate grid), `InvoiceSubjectPicker` (client/object/standalone radios), `InvoiceItemsTable` (read-only), `InvoiceIssueModal` (Precognition form, number field), `InvoiceStatsCards` (4 cards: issued/overdue/pending/recurring), `InvoiceFormSummary` (summary panel for forms).
- Pages: `Invoices/{Index,Create,Edit,Show}` (Index with stats + DataTable + filters; Create/Edit with InvoiceForm; Show with parties/meta/items/totals/actions).
- Settings pages: `Settings/Invoicing` (supplier/bank/numbering/templates/defaults).
- i18n: 60+ FE keys (see phase4-invoicing-fe.md §"i18n").

**Money math (mirror `InvoiceService::computeTotals`):**
- `line_base = round2(qty * price * (1 - discount/100))`; `line_vat = round2(line_base * rate/100)` (0 if !isVatPayer); `line_total = round2(line_base + line_vat)`.
- `subtotal = Σline_base`; `vatAmount = Σline_vat`; `totalBeforeRounding = round2(subtotal + vatAmount)`.
- `roundAmount(amount, mode)`: none→amount; document→Math.round(amount); cash_005→round to 0.05.
- `total = round2(roundAmount(totalBeforeRounding, mode))`; `roundingAmount = round2(total - totalBeforeRounding)`.
- `balanceDue = round2(total - deposit)`; `vatBreakdown` grouped by rate desc, empty when !isVatPayer.
- **Snapshot timing:** customer/object/supplier columns written on create/update; `issue` freezes them (invoice becomes non-editable). Non-editable invoices can still be marked paid/overdue/cancelled.
- **Credit notes:** cancel (Issued|Overdue) creates Issued credit note with negated items/subtotal/vat/total/deposit/rounding/vat_breakdown + all SK fields copied + own number via `next(tenant, now())`, linked via `credited_invoice_id`.

**Lifecycle guards:**
- Draft (edit/delete/issue allowed). Issued (no edit; pay/cancel/send/pdf allowed). Paid/Overdue (no edit; cancel allowed). Cancelled (no further actions except view/pdf).
- Status transitions: Draft→Issued (issue action); Issued→Paid/Overdue/Cancelled (mark-paid / cron / cancel); Overdue→Paid/Cancelled; Paid/Cancelled terminal.

**Flow (all within auth + tenant.required):**
- **Create:** POST /invoices (Precognition) → InvoiceFormContextData renders form → InvoiceService::create (snapshot + items + totals) → 302 show + flash success.
- **Issue:** Draft invoice → InvoiceIssueModal or POST /invoices/{id}/issue (Precognition on number, InvoiceIssueData) → InvoiceService::issue (number assignment via lockForUpdate loop, status/issued_at/variable_symbol set) → 302 show + flash.
- **Pay:** Issued/Overdue → POST /invoices/{id}/pay (InvoicePaymentData: paid_on, amount, payment_type, note) → InvoiceService::recordPayment (partial payments allowed; settled → Paid + paid_at) → 302 show + flash `invoice_payment_recorded`. Payment removal via DELETE .../payments/{payment}.
- **Cancel:** Issued/Overdue → POST /invoices/{id}/cancel + confirm → InvoiceService::cancel (transaction: Cancelled, cancelled_at, credit note created) → 302 show + flash. Both invoices linked (original.credited_invoice_id ← note.id, note.credited_invoice_id ← original.id).
- **Send:** Issued + email → POST /invoices/{id}/send → guard + dispatch InvoiceIssued job → sent_at stamped by listener → 302 show + flash. PDF attachment via mocked (test) or real Chromium.
- **PDF:** GET /invoices/{id}/pdf (Content-Disposition: attachment) → RendersInvoicePdf renders via chrome driver, filename from invoice.pdfFilenameBase + `HeaderUtils::makeDisposition`.
- **Duplicate:** Draft only → POST /invoices/{id}/duplicate → InvoiceService::duplicate (copy without number/VS) → 302 edit + flash.

**Depends on:** tenancy (tenant_id FK, BelongsToTenant, TenantScope), clients (client_id FK + rule, withTrashed relation), objects (cleaning_object_id FK + rule, withTrashed relation), recurring-invoices (recurring_invoice_id FK), quotes (quote_id FK nullOnDelete, audit backlink, InvoiceService.create receives built InvoiceUpsertData from QuoteService.convertToInvoice), media (temporary upload → moveToModel contract for future PDF attachments phase 6+).

**Depended on by:** recurring-invoices (InvoiceService consumed by RecurringInvoiceService::generateInvoiceFromTemplate), quotes (quote_id backlink for source tracking), Inertia show page (quote_id/quote_number link to Quotes/Show).

**If you change Core, check:**
- InvoiceStatusEnum canTransitionTo matrix (Draft→Issued; Issued→Paid|Overdue|Cancelled; Overdue→Paid|Cancelled; Paid/Cancelled terminal) vs InvoiceService::recomputePaymentState (can revert Paid → Issued/Overdue when a payment is deleted).
- InvoiceBulkService skip-reason mapping (keys of ValidationException errors: customer_email, supplier, number, amount) vs InvoiceService messages; BulkSkipReasonEnum FE labels.
- EInvoiceService / UblInvoiceXmlBuilder readiness vs Peppol snapshot columns (Client.peppol_participant_id → invoices.customer_*, Tenant → supplier_*); config/efa.php assumptions flagged in-file as unconfirmed with accountant.
- InvoiceService::issue lockForUpdate loop + collision detection vs manual number override (InvoiceIssueData).
- InvoiceService::cancel credit-note generation (negated items + all SK fields copied, own number, two-way linked, **BOTH supplier_signature_media_id + issued_by_name stamped**).
- InvoiceService::issue and cancel **?User $actor parameter signature** (issue called from controller + GenerateRecurringInvoiceJob; cancel from controller).
- InvoiceService::duplicate **must NOT stamp signature_media_id or issued_by_name** (fresh draft resolves live).
- SupplierSignatureResolver: **never calls `current_tenant_id()`** (queue path unbound), fail-closed on tenant_id mismatch + missing file.
- InvoicePdfService **passes signatureDataUri to all three blade templates** (resolved via ResolvesSupplierSignature::forInvoice), footerView only on Classic.
- InvoicePdfService chrome driver setup (config/laravel-pdf.php CHROMIUM_PATH, env LARAVEL_PDF_DRIVER, docker apk packages).
- PayBySquareService null conditions (Issued|Overdue, IBAN, EUR, balance_due > 0).
- InvoiceIssued notification queued discipline (afterCommit, retries, timeout), attachment via contract interface (testable via mock), **renders PDF with signature via SupplierSignatureResolver**.
- computeTotals money math (line_base formula, vat_breakdown grouping, rounding modes, balanceDue logic).
- Snapshot timing (create/update write, issue freezes, non-editable invoice pay/cancel unaffected).
- MarkOverdueInvoices cron (withoutGlobalScope TenantScope, per-invoice event dispatch) + test idempotency.
- config/invoicing.php signature block (disk, max_size_kb, allowed_mimes) wired to TemporaryMediaConstraints + settings upload.

**Keywords (SK):** faktúra, návrh, vydaná, splatnosť, overdue, zaplatená, stornovaná, dobropis, číslovanie, DPH, jednotková sadzba, záloha, Pay-by-Square QR, zokrúhlenie, podpis, pečiatka, "vystavil".

### recurring-invoices — Template-driven auto-generation on schedule (Phase 4, 2026-09-06)

**Core:**
- `App\Models\RecurringInvoice` — UUIDv7; traits BelongsToTenant, HasFactory, HasUuids, LogsActivity (logOnlyDirty status/frequency/next_run_at/start_date/occurrences_generated), SoftDeletes. Columns: tenant_id FK, nullable client_id FK (withTrashed), nullable cleaning_object_id FK → objects (withTrashed), name (255), type (InvoiceTypeEnum, immutable after create), template nullable (InvoiceTemplateEnum), frequency (RecurringFrequencyEnum: monthly/every_2_months/quarterly/semi_annually/annually), day_of_month (1–28), status (RecurringInvoiceStatusEnum: active/paused/completed/cancelled), bool auto_issue, **bool auto_send (default false; DTO rule requires auto_issue)**, dates (start_date, end_date nullable), occurrences_limit nullable, occurrences_generated default 0, next_run_at/last_generated_at nullable, **customer snapshot** (customer_name/representative/ico/dic/vat_number/street/city/postal_code/country/email nullable, same as Invoice), **SK fields** (constant_symbol/payment_type/currency/rounding_mode/header_text/footer_text/deposit), due_days (14 default), nullable note, timestamps, soft_deletes. Relations: `client()->withTrashed()`, `cleaningObject()->withTrashed()`, `items(): HasMany<RecurringInvoiceItem>`, `generatedInvoices(): HasMany<Invoice>` (`recurring_invoice_id` FK). Methods: `isRunnable(): bool` (active + today ≤ next_run_at), `hasReachedLimit(): bool`, `hasReachedEndDate(Carbon): bool`.
- `App\Models\RecurringInvoiceItem` — UUIDv7; traits BelongsToTenant, HasFactory, HasUuids. Columns: tenant_id FK, recurring_invoice_id FK (restrictOnDelete), description, qty/unit/unit_price (decimal:10,2), discount_percent (decimal:5,2), vat_rate (decimal:5,2), position. Index recurring_invoice_id.
- `App\Services\RecurringInvoiceService` (final readonly, ctor InvoiceService, DatabaseManager) — `paginate(Request): LengthAwarePaginator<RecurringInvoiceListItemData>` (QueryBuilder AllowedFilter search/name/customer_name/status/frequency/client_id/next_run_at/created_at, default -created_at), `create/update/delete`, `pause/resume/cancel` (state transitions with 422 guards), `generateInvoiceFromTemplate(RecurringInvoice): Invoice` (build InvoiceUpsertData with per-item vat_rate/discount + SK fields + deposit + null period_from/period_to, call InvoiceService::create, link via recurring_invoice_id, optionally auto-issue per flag or tenant default), `resolveTenantDefaultState(tenantId): RecurringDefaultStateEnum`.
- `App\Jobs\GenerateRecurringInvoiceJob` (ShouldQueue, ShouldBeUnique) — queued from GenerateRecurringInvoices command, `uniqueId() = recurring_id`, `uniqueFor() = 3600`, idempotency guard (isRunnable && next_run_at ≤ today), binds `app()->instance('current_tenant_id')`, transaction: generate via service, (D2b) check `$tenant->missingSupplierFields()` before auto-issue; after a successful auto-issue, **if `auto_send` and customer_email present → `InvoiceService::send` (else Log::warning `recurring_invoice.auto_send.skipped_no_email`)**; — if supplier incomplete + auto_issue=true, skip issue (leave Draft + Log::warning) and advance schedule anyway; else if supplier complete, **call `InvoiceService::issue($invoice, new InvoiceIssueData(number: null), null)` with explicit null actor** (unattended path → issued_by_name = __('app.invoice_issued_by_system'), supplier_signature_media_id = tenant.signature_media_id), advance next_run_at/occurrences_generated/last_generated_at, mark Completed on limit/end-date.
- `App\Console\Commands\GenerateRecurringInvoices` — daily cron, finds Active rows with next_run_at ≤ today, dispatches job per row.

**Satellites (BE):**
- Enums: RecurringFrequencyEnum (monthly/every_2_months/quarterly/semi_annually/annually, `monthsInterval(): int`, `nextRunDate(Carbon $from, int $dayOfMonth): Carbon` — clamps to daysInMonth, startOfDay), RecurringInvoiceStatusEnum (active/paused/completed/cancelled, `isRunnable(): bool`), RecurringDefaultStateEnum (draft/issued).
- DTOs: `RecurringInvoiceUpsertData` (name/type/frequency/day_of_month/auto_issue/start_date/end_date/occurrences_limit, customer_* snapshot, SK fields, items, due_days; NO period_from/period_to — generated invoices get null periods), `RecurringInvoiceItemData` (description/qty/unit/unit_price/discount/vat_rate), `RecurringInvoiceListItemData`, `RecurringInvoiceDetailData` (full + lastGenerated invoice timestamp + generated invoices list).
- Policies: RecurringInvoicePolicy (per PermissionEnum cases).
- Controllers: RecurringInvoiceController (#[NavItem] recurring_invoices).
- Factories: RecurringInvoiceFactory (states active/paused/completed/cancelled/dueToday), RecurringInvoiceItemFactory.
- i18n keys: nav recurring_invoices; enum labels (frequency, status); flash; errors (termination validation: both end_date + limit, can't pause completed/cancelled, can't resume non-paused).

**FE Satellites:**
- Components: `RecurringStatusBadge`, `RecurringFrequencyBadge`, `RecurringInvoiceForm` (name, subject picker shared with invoices, frequency radio + day-of-month + 3-way termination radio, template, items editor shared, SK fields, deposit), form renders `InvoiceFormSummary` (dates set to start_date + due_days).
- Pages: `RecurringInvoices/{Index,Create,Edit,Show}`.
- i18n: recurring_* keys (§phase4-invoicing-fe.md).

**Frequency logic:**
- `RecurringFrequencyEnum::monthsInterval()` returns interval (1, 2, 3, 6, 12).
- `RecurringFrequencyEnum::nextRunDate(from, dayOfMonth)` anchors on startOfMonth (month-end clamp via `daysInMonth`, e.g., day 28 in Feb → last day of Feb).

**Lifecycle:**
- Active (scheduled, next_run_at populated, job dispatches at due time).
- Paused (manual pause, next_run_at frozen, resume restarts from last next_run_at).
- Completed (auto on termination limit reached or end_date passed, or manual complete via cancel if reached limit first).
- Cancelled (manual cancel terminal).

**Flow:**
- Create (form) → RecurringInvoiceService::create → Active with computed next_run_at.
- Daily `app:generate-recurring-invoices` command → finds Active + due → dispatches GenerateRecurringInvoiceJob per row.
- Job (ShouldBeUnique 3600s window) → generateInvoiceFromTemplate (calls InvoiceService::create) → optionally auto-issue per flag or tenant default → links via recurring_invoice_id → advance next_run_at → mark Completed if limit/end-date reached.

**Depends on:** invoices (InvoiceService::create/issue), tenancy (tenant_id FK, BelongsToTenant).

**If you change Core, check:**
- RecurringFrequencyEnum nextRunDate month-end clamp (Feb day 28 → last Feb).
- GenerateRecurringInvoiceJob uniqueness window (3600s, idempotency guard on isRunnable + next_run_at ≤ today).
- RecurringInvoiceService generateInvoiceFromTemplate: per-item vat_rate/discount + SK fields snapshot + null period_from/period_to on generated invoice + auto-issue logic (flag vs tenant default).
- Termination logic: end_date vs occurrences_limit (both provided = 422), Completed state, can't pause completed/cancelled.

**Keywords (SK):** opakovaná faktúra, harmonogram, frekvencia, výtvorenosť, podmienky ukončenia, automatické vydanie.

### settings-invoicing — Tenant supplier data + invoice defaults (Phase 4, 2026-09-06)

**Core:**
- `App\Models\TenantInterface` (existing, extended in Phase 4) — adds columns to phase 2 baseline: `invoice_template` (InvoiceTemplateEnum, default 'classic'), `recurring_default_state` (RecurringDefaultStateEnum, default 'draft'), `default_constant_symbol` (10 chars nullable), `default_payment_type` (PaymentTypeEnum, default 'transfer'), `default_currency` (CurrencyEnum, default 'EUR'), `default_rounding_mode` (RoundingModeEnum, default 'none'). Extended logOnly with these six fields. Later additions: `default_header_text`/`default_footer_text` (text nullable, 2026-09-20), **`invoice_mail_subject` (255) / `invoice_mail_body` (text) nullable templates with `{{invoice.*}}` tokens (2026-10-12), consumed by `Notifications\InvoiceIssued` through PlaceholderResolverService::invoiceMailVariables; blank → built-in lang subject/view**.
- `App\Models\Tenant` (extended) — adds supplier columns: `dic` (32 nullable), `vat_number` (32 nullable), `address_line` (255 nullable), `city` (255 nullable), `postal_code` (16 nullable), `country` (2 nullable), `contact_email` (255 nullable), `contact_phone` (30 nullable), `swift_bic` (11 nullable, SWIFT-BIC format), **`peppol_participant_id` (64 nullable; supplier Peppol id, snapshot onto invoices.supplier_peppol_participant_id)**. Extended logOnly + @property docblocks. Existing: `name` (255), `ico` (20 nullable), `is_vat_payer` (bool), `iban` (34 nullable), `invoice_number_format` (255, default 'FA-{YYYY}-{XXXX}'), `registration_info` (text nullable), `vat_rate` (decimal:5,2).
- `App\Services\InvoiceSettingsService` (final readonly, ctor TemporaryUploadService) — `update(Tenant, InvoiceSettingsData, ?User $actor, string $sessionId): void` (transaction: write Tenant supplier + invoicing fields, write TenantInterface invoice defaults + recurring defaults + default header/footer + invoice_mail_subject/body (blank → null) + peppol_participant_id; **if $data->signature_uuid: moveToModel($tenant, 'signature', $uuid, $actor, $sessionId) then update signature_media_id; else if $data->remove_signature: update signature_media_id = null**).

**Satellites (BE):**
- DTOs: `InvoiceSettingsData` (supplier: name/ico/dic/vat_number/is_vat_payer/address_line/city/postal_code/country/contact_email/contact_phone; invoicing: invoice_template/invoice_number_format/iban/swift_bic/vat_rate/registration_info; recurring: recurring_default_state; defaults: default_constant_symbol/default_payment_type/default_currency/default_rounding_mode, **default_header_text/default_footer_text (nullable text, phase 10)**; **signature: ?string $signature_uuid, bool $remove_signature = false** mutually exclusive `prohibited_if` validation). Rules: country 2 chars, IBAN format, SWIFT-BIC format, number format with {X+} placeholder validation, constant symbol digits only, vat_rate 0–100, **signature constraints (mime/size) injected from config/invoicing.php**, default text fields max 5000 chars.
- Policies: no policy (owner-only gate via ManageBillingSettings permission).
- Controllers: `InvoiceSettingsController` (final readonly, ctor InvoiceSettingsService) — `#[Authorize(ManageBillingSettings)]` on show/update/signature, `#[NavItem(invoicing_settings, ManageBillingSettings, settings group)]`. Routes: GET /settings/invoicing → InvoiceSettingsData::fromTenant(current_tenant) + **TenantSignatureData::fromTenant($tenant) if present** + **InvoiceSignatureConstraintsData::fromConfig()**; PUT /settings/invoicing (Precognition) → InvoiceSettingsService::update($tenant, $data, $request->user(), $request->session()->getId()); **GET /settings/invoicing/signature** (streams file inline, #[Authorize(ManageBillingSettings)], 404 if absent or file missing).

**FE Satellites:**
- Pages: `Settings/Invoicing` (supplier section, bank section, number format preset/custom, template picker, defaults, recurring state).
- Components: `InvoiceSettingsSupplierCard`, `InvoiceSettingsBankCard`, `InvoiceNumberFormatField` (presets + custom with live example), `InvoiceTemplatePicker` + `InvoiceTemplateThumbnail` (with preview iframe), `InvoiceSettingsDefaultsCard`, **`InvoiceSettingsSignatureCard` + `InvoiceSignaturePreview` (plain img, not iframe)**.
- i18n: settings keys (§phase4-invoicing-fe.md).

**Defaults propagation:**
- New Invoice form: `InvoiceFormContextData::fromTenant` loads interface.default_* → `context.defaults` → form initializes SK fields + deposit from defaults.
- New RecurringInvoice form: same propagation + `context.recurring_default_state` used in hint.
- Settings page: GET shows current values (fromTenant), PUT persists changes. Next invoice forms receive updated defaults.

**Number format:**
- Presets: `FA-{YYYY}-{XXXX}`, `{YYYY}{XXXX}`, `{YYYY}/{XXX}`, `{YY}{MM}{XXX}`.
- Custom: regex `{X+}` placeholder (required, caught by validation).
- Placeholder substitution in `InvoiceNumberService::next`: `{YYYY}` → year, `{YY}` → last 2 digits, `{MM}` → month zero-padded, `{X+}` → sequence number zero-padded to length of X run.
- InvoiceTemplatePicker live preview: substitutes placeholders in real-time.

**Depends on:** tenancy (Tenant/TenantInterface models), invoices (defaults used in forms).

**If you change Core, check:**
- TenantInterface logOnly: six new fields added (invoice_template, recurring_default_state, default_*).
- Tenant logOnly: 11 new supplier fields + existing (name, ico, is_vat_payer, iban, invoice_number_format, registration_info, vat_rate, **signature_media_id**).
- InvoiceSettingsService::update: transaction scope, Tenant + TenantInterface both updated, **TemporaryUploadService::moveToModel called when signature_uuid present**.
- InvoiceSettingsController::show: returns **TenantSignatureData + InvoiceSignatureConstraintsData** in props (FE card setup).
- InvoiceSettingsController::signature: reads current tenant's signatureMedia directly, streams the file inline for GET.
- Number format validation regex (must include {X+}).
- Defaults propagation in InvoiceFormContextData::fromTenant (stale defaults if not re-fetched on page load).
- config/invoicing.php signature block (disk, max_size_kb, allowed_mimes) consistency with TemporaryMediaConstraints validation rule.

**Keywords (SK):** nastavenia, dodávateľ, číslovanie, šablóna, SK polia, záloha, podpis, pečiatka, "nahradiť", "odstrániť".

### BE ↔ FE API (Sanctum, routes/api.php) — Phase 2 tenancy + /api/me

**Web SPA (Inertia) note:**
- No Sanctum on web SPA — routes/web.php uses session auth only.
- API routes (routes/api.php) reserved for mobile/partner integrations (bearer tokens).

**API endpoints (Sanctum Bearer tokens, auth:sanctum):**
- POST /api/auth/login (guest) → LoginData {email, password, remember} → AuthTokenData {token, user: UserListItemData}.
- POST /api/auth/logout (auth:sanctum) → 204.
- **GET /api/me (auth:sanctum + tenant.context + tenant.required)** — returns MeData {userId, activeTenantId, permissions: PermissionEnum[]}} (Phase 2 new; mobile app capabilities polling path).
- GET|PUT /api/profile (auth:sanctum + tenant.context + tenant.required) → UserListItemData / 204.
- PUT /api/profile/password (auth:sanctum + tenant.context + tenant.required) → 204.
- GET /api/users (auth:sanctum + tenant.context + tenant.required) → Paginator<UserListItemData> (member-only, tenant-scoped).
- GET|PUT|DELETE /api/users/{user} (auth:sanctum + tenant.context + tenant.required) → UserListItemData / 204.
- Precognition on all POST|PUT mutations (HandlePrecognitiveRequests middleware).

**On port:**
- Phase 5: POST /api/notifications/bell (auth:sanctum + tenant.required) for 60s polling (notifications).
- Phase 3+: /api/clients*, /api/objects*, /api/quotes*, /api/invoices*, /api/contracts* mirrors web routes.

### BE ↔ FE Enums — Phase 2 PermissionEnum + tenancy enums

**Generated from #[TypeScript] → App.Enums.* union in resources/js/types/generated.d.ts:**
- PermissionEnum (64 backed string cases, grouped by resource: employees/roles/audit_logs/api_docs/media/billing + 9 groups phase 3+) — methods: label(), group(), groupLabel(), sharedKey() (Str::camel), values(). Labels NOT shipped in generated.d.ts (FE uses t() for localized labels from app.json; BE Spatie Permission stores value).
- TenantColorEnum (8 backed hex cases: a16207, ea580c, ca8a04, eab308, ca8a04, 16a34a, 0891b2, 0284c7) — methods: label(), options(). Labels translated via t('app.tenant_color_<hex>').
- InvitationStatusEnum (pending/accepted/revoked/expired) — label().
- InvitationAcceptStateEnum (expired/wrong_user/existing_user/new_user) — FE-only states for Accept page rendering (not persisted; 4-way dispatch from backend hints).
- SupportedLanguage (sk/en/uk) — methods: getDefault(), getDisplayName(), getForLanguageSwitcher(), isSupported(), getCodes(). Labels and flags bundled.

**Phase 3+ on port:**
- JobStatusEnum, JobTypeEnum, TaskFrequencyEnum (schedule).
- ObjectTypeEnum, ClientTypeEnum (clients/objects).
- QuoteKindEnum, ContractCategoryEnum, ContractTermTypeEnum, PaymentTypeEnum, RoundingModeEnum, EmploymentContractTypeEnum, InvoiceTemplateEnum, InvoiceStatusEnum, RecurringInvoiceFrequencyEnum, etc.

**FE pattern:**
- Never destructure enum values; always use App.Enums.PermissionEnum for type-safe checks.
- useAuthorization().allows(PermissionEnum.ViewClients) → read page.props.can[permissionKey(value)].

### FE Authorization — Phase 2 useAuthorization + Can component

**Derived from PermissionEnum:**
- BE: HandleInertiaRequests.share builds can: `collect(PermissionEnum::cases())->mapWithKeys(fn ($p) => [$p->sharedKey() => $user->can($p->value)])`.
- FE: useAuthorization().allows(App.Enums.PermissionEnum.ViewClients) → permissionKey('view clients') → 'viewClients' → page.props.can['viewClients'] === true.

**Guard component:**
- Components/Can.vue (required permission: App.Enums.PermissionEnum prop) — `<slot v-if="allows(...)" /><slot v-else name="fallback" />`.
- Never use $page.props.can.viewUsers directly (string keys, no type safety). Always use useAuthorization().allows(enum) or <Can> component.

**BE-filtered navigation:**
- NavigationRegistry discovers #[NavItem] on controllers with permission: PermissionEnum::*->value.
- Visible in nav only if user->can(permission).
- FE renders from shared navigation (filtered by BE, no client-side re-filtering needed).

**No role checks (mandate):**
- Neither RBAC check ($user->hasRole) nor role-based rendering.
- Only permission-based authorization (single axis: user/permission).

### BE ↔ FE Notifications bell (web vs. API) — Phase 8

**Web route (session auth, browser poll):**
- `GET /notifications/bell` (web route, auth + tenant.required middleware) → `NotificationController::bell()` → `NotificationService::bell(User, tenantId): NotificationBellData`
- Returns JSON `{ unread_count: number, recent: NotificationListItemData[] }` (5 latest, including read).
- FE `useNotificationBell` composable polls every 60s, ref-counted subscribers (only one poller per tab), stops on 401/403/419 (auth loss), pauses when tab hidden (battery optimization).

**API route (Sanctum, header-based tenant):**
- `GET /api/notifications/bell` (Sanctum Bearer token, no session; X-Tenant-Id header required) → `Api\NotificationBellController` (invokable) → same service method.
- Returns same NotificationBellData JSON.
- Reserved for mobile phase 9 (Capacitor app polling via Bearer token + tenant header).
- `/api/*` NOT stateful: browser cookies do NOT authenticate (no `statefulApi()` in bootstrap/app.php). Reasons: (a) SPA uses session for web routes only, (b) mobile uses Bearer tokens. Dual routes eliminate need for statefulApi() CSRF overhead.

**DTO contract:**
- `NotificationBellData (unread_count, recent: NotificationListItemData[])`
- `NotificationListItemData (id, type: NotificationTypeEnum, title, body, url, read_at, created_at)` — timestamps ISO-8601, url can be null.

### BE ↔ mobile API (JSON, Sanctum) — Phase 11

- Responses built from Spatie Data (`toArray`); `MeData` carries `companies: list<MobileCompanyData>` (tenant_id, name, color, mobile_access, permissions).
- Assistant reply: `AssistantReplyData` {reply, actions: AssistantActionData[] {type, label, url}, pending_action: PendingActionData|null}. Pending action: `PendingActionData` {id, action, status, preview, tenant_id, tenant_name, subject_label, customer, total, email, expires_at, confirm_in_app_only}. Confirm result: `ActionResultData` {action, subject_label, url, message}.
- Push deep links: `PushRoute` allowlist (`/tasks/{uuid}`, `/invoices/{uuid}`, `/quotes/{uuid}`, `/expenses/{uuid}`, `/pending-actions`), else `/notifications`.
- `GET /api/dashboard` (Api\DashboardController, `#[ResponseFromSpatieData(DashboardData::class)]`) returns the same `DashboardData` that DashboardService builds for the web dashboard.
- Task attachments: `TaskAttachmentListData` {attachments: TaskAttachmentData[], can_add, constraints: TaskAttachmentConstraintsData}; `TaskAttachmentData` {id (media uuid), name, mime_type, size, previewable, uploaded_by_name, created_at, download_url (relative path, route `api.tasks.attachments.show`), can_delete}. POST 201 returns `TaskAttachmentData`; DELETE 204; download is a stream, not JSON. Names and URLs are never sent to MCP or the assistant (only `attachments_count` in TaskDetailData).

### BE ↔ FCM payload — Phase 11

- `PushMessage` {title, body, route, tenantId, notificationId, badge}. Sent as multicast per user's device tokens; tokens reported unusable are deleted from `device_tokens`.

## Gotchas

### Backend (updated Phase 2: 8 resolved, 18 active/new)

1. ~~Spatie Permission teams OFF~~ — **FIXED 2026-09-06:** teams=true, team_foreign_key='tenant_id' (uuid columns). RoleTemplatesSeeder::seedForTenant(Tenant) creates role bundles per tenant. setPermissionsTeamId discipline enforced in TenantContextMiddleware, RegistrationService, InvitationAcceptService, UserService, tests/TestCase::actingAsTenantUser, every job/command. `setPermissionsTeamId` NOT called before role/permission checks in D6 console contexts = intentional (commands/seeders run unbound on purpose; bind explicitly when needed).

2. ~~Sanctum personal_access_tokens.tokenable_id is bigint (morphs) vs users.id uuid~~ — **FIXED 2026-09-06:** uuidMorphs('tokenable') applied. Tests run on Postgres.

3. ~~activity_log morph columns uuid~~ — **FIXED 2026-09-06:** D1 decision: TenantInterface UUID (uuid PK, LogsActivity on it). App\Models\Activity (D2) custom hook, tenant_id nullable. uuid morphs stay (nullableUuidMorphs). Vendor Media (bigint id) must never be logged (gotcha 3-old: moot now — Media has no LogsActivity, only on domain models).

4. ~~No PermissionEnum~~ — **FIXED 2026-09-06:** App\Enums\PermissionEnum (53 backed string cases, #[TypeScript], methods label/group/groupLabel/sharedKey/values). PermissionSeeder seeds global catalogue (not per-tenant; roles are). RoleTemplatesSeeder uses enum values. HandleInertiaRequests builds can from enum. Policies use enum values. FE useAuthorization().allows(enum) + permissionKey(value) → Str::camel mapping. Tests (CreatesUsers, etc.) call setPermissionsTeamId before role checks.

5. ~~PermissionMiddleware vs #[Authorize]~~ — **CLARIFIED 2026-09-06:** keep permission: middleware ONLY for /docs* (non-model route); all model CRUD use #[Authorize] attribute (RBAC-full profile). Routes middleware group: POST /logout, then tenant.required gates all app/* except logout; api/* gates auth:sanctum + tenant.context + tenant.required.

6. ~~is_active not enforced at login~~ — **FIXED 2026-09-06:** D4 guards: AuthController/Api/AuthController login includes `is_active => true` in Auth::attempt (failure = bad credentials + fires Failed audit) + `hasActiveMembership()` check (none = ValidationException 'no_active_tenant' indistinguishable from membership loss). RequireActiveTenant middleware ensures bound tenant on all authenticated HTTP requests (web logout + redirect / API 403). Mid-session membership deactivation = next request logs out (transparent).

7. ~~config/media-urls.php:5,16 references non-existent App\Models\EmailTemplate~~ — Still open (not a tenancy blocker; phase 3+ will address).

8. ~~Auth-event audit covers 3 of 7 events; request()->ip() used directly~~ — **FIXED 2026-09-06:** LogAuthenticationActivity covers 3 events (login/logout/login_failed); get_client_ip() helper in app/Support/helpers.php (reachable IP-proxy aware per laravel-13-conventions/references/rate-limiting.md). 7-event full audit (attempt, throttled, verified, failed, login, logout, logout, other) deferred post-bootstrap (gotcha-noted for clarity).

9. ~~No rate limiters on login / forgot / reset / api login~~ — **PARTIAL 2026-09-06:** Invitation-accept throttle = `throttle:invitation-accept` (5/min per IP via RateLimiter config). Other auth routes (login, forgot, reset) still open (no rate limiter; future hardening, gotcha-noted).

10. ~~UserSeeder seeds admin@example.com/password + 5 faker users~~ — **FIXED 2026-09-06:** D3: UserSeeder calls RegistrationService::createOwner('Admin', 'admin@example.com', 'password', 'Demo Cleaning s.r.o.', '12345678'), creating canonical User → Tenant → TenantMembership → Admin role assignment. No 5 faker users (spec chose "no demo accounts"). Bootstrap path = `php artisan app:create-owner` (interactive or flag-based).

11. ~~Dead code: DTOs UserIndexFilterData, RoleIndexFilterData, ActivityLogIndexFilterData, LanguageSwitchData~~ — **FIXED 2026-09-06:** OwnedTemporaryMedia has no callers yet (phase 3+ will consume via Quote document upload, Invoice PDF, etc.; by design, noted). AllowedFilter.php fixed (2026-09-06 prior). Dead DTOs removed from phase 2 codebase.

12. ~~Roles/Form permissions prop is a bare grouped array (TS any)~~ — **FIXED 2026-09-06:** PermissionGroupData (#[TypeScript], id/group/group_label/permissions: PermissionItemData[]) types form prop.

13. ~~Temporary upload response is a bare array~~ — Still a bare array (not a DTO; API response minimalism rule). FileUploadInput.vue:41 hard-codes POST /uploads (intentional single endpoint).

14. ~~SupportedLanguage only sk/en~~ — **FIXED 2026-09-06:** Added uk (Ukrainian). Translations JSON resources/lang/{sk,en,uk}/app.json + {sk,en,uk}/validation.json. Flat snake_case keys (vue-i18n legacy:false compatible). FE app.ts imports all three; AppLayout language dropdown renders all three.

15. db:table artisan fails in container (intl missing) — use `docker compose exec postgres psql` directly.

16. ~~No morph map~~ — **FIXED 2026-09-06:** AppServiceProvider.php: `Relation::morphMap(['tenant_membership' => TenantMembership::class])` (current only consumer; enforceMorphMap deferred when all models mapped).

17. ~~No preventLazyLoading, no SoftDeletes~~ — **FIXED 2026-09-06:** Domain models on phase 2 port (Tenant, TenantMembership, TenantInterface, TenantInvitation + existing User, Role, Activity, Media, TemporaryUpload) have: SoftDeletes where appropriate (all except User/TenantMembership which use is_active flag or are pivots), BelongsToTenant where appropriate (all except User, Tenant, TenantMembership, TenantInterface, Role, Permission, Activity), TenantScope global scope. Phase 3+ models inherit same pattern. preventLazyLoading deferred (no prod user data yet; enable when customers live).

18. PHPStan level max against `phpstan-baseline.neon` (188 entries, regenerated 2026-09-06 after tenancy fixes). Run with `--memory-limit=1G` to avoid OOM. Baseline burn-down deferred Phase 3+ (targets: test closures, AllowedFilter generics, QueryBuilder chains, Scribe strategies).

19. `compose.yml` injects `DB_CONNECTION=pgsql`, `DB_DATABASE=cleanmaster_admin` into container. PHPUnit `<env>` blocks need `force="true"` to override compose env (phpdotenv reads `$_SERVER`). Testing DB `cleanmaster_admin_testing` auto-created by docker/postgres/init-testing-db.sql on first volume init.

20. **NEW (Phase 2):** D8 FK restrictOnDelete (no cascades). Tenants are soft-deleted, so cascades never fire anyway. Owners/tenants with historical data cannot be deleted (deactivate instead). Explicit guard: UserService::delete asserts not owner; TenantMembership::delete keeps User row.

21. **NEW (Phase 2):** D10 invitation token 64-char random, plaintext unique index, 7d expiry, single-use. Hashing deferred (low-risk internal tool, noted for future hardening).

22. **NEW (Phase 2):** Inertia shared props typing (D1): single `SharedProps` interface in types/index.d.ts (collapsed from two blocks). Import-free global augmentation — no types/inertia.d.ts file. FE reads via usePageProps() ComputedRef, never destructure at setup (D3).

### Frontend (Phase 2: 10 resolved, 3 active/new)

1. ~~Three-way i18n mismatch~~ — **RESOLVED 2026-09-06:** Skeleton pattern kept (build-time vue-i18n from JSON). BE loads resources/lang/{sk,en,uk}/app.json + validation.json via AppServiceProvider::loadJsonTranslations as app.<key>. FE app.ts imports all three locales, uses useI18n().t(key, fallback) throughout (no shared translations prop). LocaleMiddleware + GET /language/{locale} handle switching (full reload). No reintroduction of translations shared prop.

2. ~~URLs stay string literals~~ — Ziggy absent; all routes hardcoded. Skeleton DataTable route-name="..." (NOT a prop; falls through as DOM attr) — removed from Users/Index phase 2.

3. ~~usePageProps() / useAuthorization() / <Can> do not exist~~ — **ADDED 2026-09-06:** usePageProps (ComputedRef<SharedProps>), useAuthorization (allows(PermissionEnum)), Can component (permission guard). No Pinia (will add phase 5+ for notifications polling). Shared can auto-derived from PermissionEnum::sharedKey (Str::camel), unidirectional per-permission (no hand-maintained list). Shared props include tenant{active,available}, tenantColors, auth.user.locale, languages (3 items incl uk), navigation. Single types/index.d.ts SharedProps interface (collapsed D1); no types/inertia.d.ts file.

4. ~~Navigation BE-driven~~ — **CLARIFIED 2026-09-06:** #[NavItem] discovery on controllers per PermissionEnum. Tenant switcher + "Pridať novú firmu" button explicit sidebar component (TenantSwitcher). FE navigation dropdown renders shared navigation (BE-filtered, no client-side re-filtering). Language dropdown data-driven from shared languages (no hardcoded list).

5. ~~Theme declaration differs~~ — **RESOLVED 2026-09-06:** Kept `app-theme`, overwrite OKLCH tokens + Plus Jakarta Sans/JetBrains Mono via Google Fonts. Auth pages reskinned (AuthShell + hero + fields + language switcher). Phase 2 tenant colour adds inline --color-primary on AppLayout root (scope to AppLayout + AddTenantModal; auth pages unaffected, kept amber).

6. ~~Pagination~~ — **RESOLVED 2026-09-06:** DataTable expects LengthAwarePaginator (->paginate()->through(DTO::class)); Pagination.vue dropped. Phase 3+ domain pages follow same pattern.

7. ~~Form inputs~~ — **RESOLVED 2026-09-06:** FormProvider/useFormContext/useFieldError exist (field= integration). ColorSwatchPicker ported (field-mode + v-model dual). FileUploadInput works (multipart to /uploads, uuid validation, moveToModel contract). EmptyState + PageHeader → mapped to skeleton components. No ESLint ref ban (follow skeleton style).

8. Precognition auto (HandlePrecognitiveRequests on all mutations) — Cross-field DTO rules (quote clientless prohibits, contract term/end_date conditionals) execute under Validate-Only partial payloads (by design; if gotchas arise, noted).

9. ~~Minor defects~~ — **RESOLVED 2026-09-06:** FormErrorsAlert removed, FormActions defaults fixed. **NEW:** PermissionManager now receives grouped data (PermissionGroupData[]) + renders groups/items. SharedProps collapse complete (D1, single types/index.d.ts). Users/Index filters prop fixed (filters: Record<string, unknown>, BE sends filters not query).

10. **NEW (Phase 2):** `invitation_join_as` i18n key uses vue-i18n `{role}` placeholder (FE-side interpolation). All i18n keys snake_case (app.json flat keys). Three locales now rendered via shared languages (sk/en/uk, flags, lang attr).

11. **NEW (Phase 2):** FormActions (form component) gains optional cancel emit (cancelHref becomes optional; modal uses emit('cancel') as button). Backwards-compatible; existing pages unchanged.

### Phase 4 additions (7 new gotchas)

23. **RecurringFrequencyEnum::nextRunDate month-end clamp.** `nextRunDate(from, dayOfMonth)` clamps Feb 28 (not 30/31) → last Feb day, avoiding `startOfDay()` overflow. Fixed in initial port.

24. **PDF filename sanitisation.** `GET /invoices/{id}/pdf` uses `Invoice::pdfFilenameBase` → `HeaderUtils::makeDisposition('attachment', filename)` to prevent directory traversal. Invoice number null → filename `'draft.pdf'`.

25. **`?->` null-safe before `??` coalescing is redundant.** PHPStan `nullsafe.neverNull`: e.g., `$invoice->client?->name ?? 'Unknown'` flips to `$invoice->client->name ?? 'Unknown'` (if client exists, name is string or null, not another object). House style: use `$invoice->client?->name` alone (no coalesce when the property itself is nullable string).

26. **Tailwind v4 has no safelist.** Never build class names dynamically (e.g., `'badge-' + status`). Hardcode all variants in components (badge-error, badge-success, badge-warning, badge-ghost, badge-primary) or use ternary maps (TailwindCSS limitation — all classes must appear in source code).

27. **Spatie QueryBuilder throws InvalidSortQuery on disallowed sorts.** Allowed sorts must match FE DataTable column definitions exactly. If column changes name or becomes unsortable (date field with date_range operator), update both BE `allowedSorts` and FE `filters.config` simultaneously (no global error handler per spec).

28. **TenantInterface always exists post-bootstrap.** `app('current_tenant_id')` is safe to read in tenant-scoped contexts; TenantInterface is unguarded (no auth check). If tenant needs interface customization, update via `InvoiceSettingsService::update` (owned by ManageBillingSettings permission).

29. **Send disabled without customer e-mail.** `POST /invoices/{id}/send` guards `customer_email` presence; FE button `:disabled="!customer_email"` with `:title="t('invoice_no_customer_email')"`. User-facing message on 422 via DTO `messages()` override or inline validation error.

30. **php artisan test needs 512M CLI memory limit.** `php artisan test --compact` runs on Postgres; if OOM: `php -d memory_limit=512M vendor/bin/phpunit` or `MEMORY_LIMIT=512M php artisan test`. Dev Docker env already sets 512M. On macOS host, `composer.json` scripts can set via `php -d`.

31. **Invoice supplier profile completeness guard.** `InvoiceService::issue()` throws ValidationException key `supplier` if `tenant->missingSupplierFields() !== []` before any transaction. Drafts always allowed (create/update on incomplete tenant succeeds); only issue is gated. RecurringInvoice auto-issue (D2b) checks completeness: if missing + auto_issue flag, generates Draft + logs warning + advances schedule (does NOT throw). Fixed fields: name, address_line, city, postal_code, ico (required always); dic, vat_number only when is_vat_payer=true. Banner + drawer (FE slice F) prompts user to Settings→Invoicing to complete profile (no CTA enforcement on incomplete tenant; authorization gate only prevents issue).

32. **Invoice settings drawer protocol (FE slice b3b4a10).** `InvoiceSettingsDrawer` fetches `/settings/invoicing` with `X-Inertia` + `X-Inertia-Version` headers (undocumented Inertia protocol detail, may break on version upgrade). 409 response (asset version mismatch) triggers hard reload; success reloads partial page (`router.reload({ only: [...] })`). Drawer is lightweight modal, not a full route, so it shares parent page context but owns form submission.

### Phase 5 additions (8 new gotchas)

33. **Quote has no auto-numbering.** Unlike invoices (which auto-assign number on issue), quotes use optional manual `number` field (max 50 chars, immutable after create, nullable on create/edit). Uniqueness enforced per tenant + partial index. Reason: quotes are informal proposals, not tax documents; customers prefer sequential patterns but some skip numbering entirely. Spec: clientless quotes can carry numbers or not. Rough quote show page displays `#<number>` only if set. Converted invoices get invoice.number (separate sequence).

34. **Quote convertToInvoice requires Accepted status.** Only Accepted quotes can convert (Draft/Sent/Rejected/Expired block → 422 `app.quote_not_acceptable_for_conversion`). Document-kind quotes never convertible (422 `app.quote_document_not_convertible` on attempt). Re-conversion allowed — single quote can generate multiple invoices (Quote::invoices HasMany FK, not one-to-one). Invoice always carries quote_id + quote_number (nullable FK, nullOnDelete for audit trail).

35. **Clientless quote snapshot fields are mutually exclusive with client_id.** DTO `prohibits` rule: if `client_id` set, all 5 snapshot columns (customer_name/email/street/city/postal_code) must be null + quote shows client.name in display, not snapshot. If `client_id` null, snapshot fields are required (customer_name) or optional (rest). `attachClient` action nulls all 5 snapshot columns and sets client_id + optional object_id. Precognition validation: cross-field check happens on full submit, not per-field blur (trade-off for partial-payload Precognition support).

36. **Document quotes have no lifecycle.** Document kind `kind=document` (immutable after create): no send/accept/reject/duplicate/convert actions. Show page displays status=Draft always + no action buttons. Delete allowed (soft). Reason: document quotes are archive-only (e.g., scanned legacy proposals), not workflow objects. Kind flag created immutable via DTO Rule::in on edit (existing quotes cannot change kind).

### Phase 6 additions (5 new gotchas)

41. **Contract text is re-resolved on every Draft save (reworked 2026-10-31).** `ContractService::create/update` run the body through DocumentTemplateRenderer (single pass, `e()` on values). A value that is present replaces its token; a `text` token with no value stays as the literal `{{key}}` (shown as a chip, never stored as `<mark>`), so re-resolving cannot erase a chip; `optional` tokens with no value are removed (empty paragraph dropped). Sign is refused (422 `status`) while tokens remain. On sign, blocks are expanded and frozen; template edits never reach existing contracts (text is copied at generation).

42. **EmploymentContract has strict (restrict, not cascade) FK on contract_id.** Parent Contract soft-delete does NOT cascade-delete EmploymentContract (soft-delete parent prevents cascade anyway; no `forceDelete` path exists in phase 6). Result: deleting a Draft employment contract requires deleting its employment child first OR allowing the service to handle it via `updateOrCreate(['contract_id'], […])` when employment data absent. Current code: `employment()->delete()` called in `ContractService::update` when category changes from employment→other; delete action checks `isEditable()` before soft-delete (service guards authorization).

43. **Contract number is free text, not unique.** Unlike invoices (auto-numbered per tenant per year, unique), contracts use optional `number` field (max 50 chars, no uniqueness, no auto-assignment). Reason: quotes converted to contracts copy the quote's number; re-conversion of the same quote must not collide on uniqueness. Contracts can have duplicate numbers (reference_number use case: customer PO numbers, multi-contract grouping). Show page uses `{{ number }}` when set; list displays both title + number in separate columns.

44. **ContractableTypeEnum morph aliases must stay in sync with AppServiceProvider::morphMap.** `ContractableTypeEnum.modelClass() → CleaningObject | TenantMembership | Client`. Relation::morphMap(['cleaning_object', 'tenant_membership', 'client']) must match enum values exactly (the `client` alias is new: migration 000400 rewrote `activity_log.subject_type` `App\Models\Client` → `client`). FE mirror: CONTRACT_CATEGORY_CONTRACTABLE_TYPES (employment→[tenant_membership], service_agreement→[cleaning_object, client], nda/gdpr/other→all); BE authority is `ContractCategoryEnum::allowedContractableTypes()` + `assertCategoryMatchesContractable()` (422 on mismatch).

45. **Sekretárka can edit/sign/terminate contracts (D2 user decision).** Role seeder (RoleTemplatesSeeder) grants Sekretárka EditContracts + TerminateContracts permissions. Test `test_secretary_can_edit_sign_and_terminate` in tests/Feature/Contracts confirms workflow: Sekretárka creates Draft → edits → signs → terminates. Permits full contract lifecycle management (view/create/edit/terminate/delete contracts + view/create/edit/delete document templates for firm rows) per user decision D2 (EditContracts permission gates both edit action + sign via `ContractPolicy::sign`).

37. **Quote document upload via temporary-file contract.** FE posts file to `POST /uploads` (separate endpoint, TemporaryUploadPolicy + upload files permission), gets uuid + metadata back. Form sends `document_uuid` in QuoteUpsertData. DTO validates OwnedTemporaryMedia (user/session/tenant ownership) + TemporaryMediaConstraints (mime whitelist from config('quotes.document.allowed_mimes'), size ≤ config('quotes.document.max_size_kb')). Service calls `TemporaryUploadService::moveToModel($document, 'file', $uuid, $user, $sessionId)` ($document = the quote's Document, created on demand) inside transaction (tenant+ownership re-checked). `Media::move()` physically relocates file from public temp disk to private DOCUMENTS_DISK (no URL exposed, route-gated access only). On re-upload, `singleFile()` replaces (media count stays 1). Staged uploads live ≤24h before cron purge (skeleton-wide, not quote-specific; known gap).

### Phase 10 additions (Classic PDF fidelity, 2 new gotchas)

46. **Classic invoice PDF footer header suppression.** Spatie/Laravel PDF's ChromeDriver enables Chromium's `displayHeaderFooter` as soon as a header OR footer is supplied (not just when both present). Without an explicit `headerTemplate`, Chromium stamps its own default header (render date + document title) into the top margin — invisible when margin is 0, appears once margins are added (12mm top margin in Classic). `headerHtml('<div></div>')` sets an empty but non-null template, suppressing the default. Modern/Minimal templates have no footer, so headerHtml is not called (no header suppression needed). Applied only to Classic (InvoicePdfService checks footerView before calling headerHtml).

47. **Classic PDF layout accepted limitations.** Bank block (IBAN/SWIFT/symbols) in left-column header is top-stacked rather than bottom-pinned (CSS nested table height:100% does not reliably stretch in Chromium print context; bounded to one layout attempt per spec, fallback documented). Empty band remains between signature block and QR/payment-strip wrapper (wrapper sits outside the grid so the strip can span full width atomically and survive page breaks via page-break-inside:avoid). Both are visual trade-offs vs pixel-perfect perfection — acceptable for internal tool, noted for future owner review.

38. **DocumentTotalsCalculator extracted for quotes + invoices.** Both models need identical VAT line math + breakdown grouping. Extracted to final readonly service (line + totals methods). InvoiceService::syncItems + computeTotals delegate to it. QuoteService::syncItems + computeTotals delegate to it. Parity test proves identical output for same inputs (QuoteTotalsParityTest). RoundingModeEnum::round applies to invoices only (None enum case when rounding not applicable); quotes ignore rounding (treated as None).

39. **convertToContract deferred to Phase 6.** Quote::contracts() HasMany relation NOT added; Quote.convertToContract() method absent; POST /quotes/{id}/convert-to-contract route missing; QuotePolicy::convertToContract() missing; ContractService::createFromQuote() not implemented. Phase 6 adds polymorphic subject + backlink. Phase 5 job: add contract lifecycle starting from accepted quotes (scope Phase 6+ plan).

40. **Quote item frequency is phase 7 work-breakdown input.** QuoteItem.frequency column (nullable 50 chars, e.g., "weekly_1x", "seasonal") stays unused in phase 5 (no consumer). Phase 7 work breakdown generation reads it when accepted quote is converted to ServiceAgreement contract, decomposing items into WorkBreakdownTask frequency recurrence. If missing (null), defaults to "one_time" task.

### document-templates — Global + firm text templates, token catalogue, single-pass renderer (2026-10-31; replaces contract-templates)

**Core:**
- App\Models\DocumentTemplate — UUIDv7; traits HasFactory, HasUuids, LogsActivity (logOnly name,type,employment_type,is_active,parent_id; logOnlyDirty), SoftDeletes; `#[ScopedBy([TenantOrGlobalScope])]`, deliberately NOT BelongsToTenant (its creating hook would fill tenant_id, and TenantScope would hide global rows) — the one model exempt from CLAUDE.md rule 6. Columns: tenant_id nullable FK (NULL = global, restrictOnDelete), parent_id nullable self-FK (firm override of a global row), type (DocumentTypeEnum 32), employment_type nullable (EmploymentContractTypeEnum), name, body (HTML with `{{key}}` tokens), base_body + base_hash (override only: parent text at customize/acknowledge time), show_header, show_footer, is_active, soft_deletes. Partial unique `(tenant_id, parent_id) WHERE parent_id IS NOT NULL AND deleted_at IS NULL`; CHECK `parent_id IS NULL OR tenant_id IS NOT NULL`; indexes (tenant_id,type), (tenant_id,is_active). Methods: templateScope() (Global/Override/Firm), state() (Base/Customized/BaseChanged by base_hash vs parent body; null for firm rows), scopeActive, parent() (withoutGlobalScopes+withTrashed), overrides(), tenant().
- App\Scopes\TenantOrGlobalScope — `tenant_id = current OR tenant_id IS NULL`; **no-op when `current_tenant_id` is unbound** (console/jobs/seeders see every firm's rows, same fail-open convention as TenantScope D6).
- App\Services\Documents\DocumentTemplateService (final readonly, DatabaseManager + renderer/catalogue/sanitizer/DocumentPdfRenderer): `paginate(Request)` (QueryBuilder, filters search/type/scope/is_active, sorts name/type/updated_at), `options(DocumentTypeEnum, ?EmploymentContractTypeEnum): list<DocumentTemplate>` (active; employment_type null or equal; **hides a global row when the current firm holds an override of it**), `create(DocumentTemplateUpsertData, ?Tenant)` (null tenant = global), `update`, `delete`, `customize(global, Tenant)` (copies body into an override with base_body/base_hash; unique-violation returns the existing override), `reset(override)` (soft-deletes the override, so the firm falls back to the global), `acknowledge(override)` (refreshes base_body/base_hash from parent = "keep my version"), `preview(DocumentTemplatePreviewData, Tenant): DocumentRenderData` and `previewPdf(...)` — **sample data only, never a real record**. prepareBody sanitizes and 422s unknown tokens for the type (error on `body`).
- App\Services\Documents\DocumentTemplateCatalogue — token catalogue (kinds text/optional/flag/block; groups tenant/contract/client/object/employee/employment/customer/quote/conditions/blocks) with availability per DocumentTypeEnum, `forType`, `keysForType`, `kindOf`, `labelOf`, `catalog(): DocumentTokenCatalogData`, `skeleton(type, withQuoteBlocks)` (blank-contract body: block.parties [+ block.employment_terms] + block.signatures; used by ContractService/EmployeeService/QuoteService fallbacks so no-template documents print like the old Blade).
- App\Services\Documents\DocumentTemplateRenderer — `render(html, DocumentRenderContext)` single pass with `e()` on every value; `unresolvedTokens`, `violations(type, html)` (unknown/unavailable keys), `highlightMissing` (draft PDF only: leftover tokens become `<mark>(label)</mark>` at render, never stored). Value present -> replaced; `text` kind null -> token stays verbatim (shows as a chip in the editor); unknown key -> verbatim; `optional` null -> removed and an emptied paragraph is dropped; `flag` toggles conditional paragraphs; `block` expands `resources/views/documents/blocks/{parties,employment-terms,signatures,quote-items,quote-totals}.blade.php` (must survive the sanitizer unchanged; contract bodies store expanded blocks).
- App\Services\Documents\DocumentContextBuilder — `forContract(subject, Tenant, ContractGenerateData, ?Quote)`, `forQuote(Quote, Tenant)`, `sample(type, ?employmentType)` -> DocumentRenderContext (values, flags, blocks). Every build runs inside DocumentLocale.
- App\Services\Documents\DocumentLocale — `run(callable)`: sets `config('documents.locale')` (env DOCUMENTS_LOCALE, default `sk`) for blocks, token values and PDF labels, restores the previous locale in `finally`; independent of UI, CLI and queue locale. Editor + catalogue labels stay in the UI locale. Invoice PDFs are NOT routed through it.
- App\Services\Documents\DocumentHtmlSanitizer (symfony/html-sanitizer allowlist) + DocumentStyleAttributeSanitizer (CSS allowlist) — split by reason to change. DocumentFrame (static `letterhead(Tenant)`, `footer(Tenant)`), DocumentRenderContext, DocumentPdfRenderer (HTML + frame -> bytes via pdf.documents.default).

**Satellites (BE):**
- Enums (#[TypeScript]): DocumentTypeEnum (service_agreement/employment/nda/gdpr/other/quote + 7 `employee_*` file types; `templatable()`, `employeeFiles()`, `isEmployeeFile()`, `fromCategory(ContractCategoryEnum)`), DocumentTokenKindEnum, DocumentTokenGroupEnum, DocumentTemplateScopeEnum (global/override/firm), DocumentTemplateStateEnum (base/customized/base_changed).
- DTOs (app/Data/DocumentTemplates, app/Data/Documents): DocumentTemplateUpsertData, DocumentTemplatePreviewData, DocumentTemplateListItemData, DocumentTemplateOptionData, DocumentTemplateEditData (+ DocumentTemplateCanData), DocumentTokenData, DocumentTokenCatalogData, DocumentLetterheadData, DocumentRenderData {html, missing_tokens, unknown_tokens, letterhead, footer}.
- Policy DocumentTemplatePolicy: viewAny/view/preview -> ViewDocumentTemplates (**read-only viewers of a global row may preview**); create -> CreateDocumentTemplates; **createGlobal and update/delete of a global row (tenant_id NULL) -> `users.is_platform_admin`** (a firm Admin gets 403 even as owner of a firm they created); update of firm rows -> EditDocumentTemplates; delete of firm rows -> DeleteDocumentTemplates and parent_id NULL (overrides leave via reset); customize -> CreateDocumentTemplates + global + active + no existing override for this firm; reset -> override + EditDocumentTemplates; acknowledge -> override in BaseChanged + EditDocumentTemplates.
- Controller DocumentTemplateController: #[NavItem(label 'app.document_templates', route 'document-templates.index', icon RectangleStackIcon, permission view document_templates, group settings, order 30)] on index. Routes under `/settings/document-templates` (names `document-templates.*`): GET index|create|{t}/edit; POST store (firm, Precognition) · POST /global store-global (Precognition) · PUT|PATCH update · DELETE destroy · POST {t}/customize (redirects to the new override's edit) · POST {t}/reset · POST {t}/acknowledge · POST /preview -> JSON DocumentRenderData · POST /preview-pdf -> inline PDF (both `throttle:document-preview`, 30/min/user; no id so Create works unsaved).
- Config config/documents.php: disk (DOCUMENTS_DISK), employee_files {max_size_kb 10240, allowed_mimes pdf/jpeg/png/webp}, locale (DOCUMENTS_LOCALE), max_body_length 200000.
- Seeder DocumentTemplateSeeder (global skeleton templates, called from DatabaseSeeder; tokens + headings only, no legal wording). Factory DocumentTemplateFactory.
- Commands: `app:grant-platform-admin {email}`.

**FE Satellites:**
- Pages: DocumentTemplates/{Index,Create,Edit} (Edit is read-only when `!can.update`; Create query `?type=&employment_type=&global=1`). Components/DocumentTemplates/{DocumentTemplateWorkspace,MetaFields,Preview,BaseChanged,ScopeBadge,StateBadge,DocumentTypeBadge}; shared Components/Documents/{DocumentEditor,DocumentEditorToolbar,DocumentFrame,DocumentFrameLabel,DocumentFrameToggles,DocumentSheet,DocumentTokenEditor,DocumentTokenPicker,DocumentMissingTokens,DocumentPdfPreviewModal} + editor/{documentEditorKit,documentToolbar,extensions/*}. Composables useDocumentEditor, useDocumentTemplatePreview (debounced 600 ms, sample only), useDocumentPdfPreview, useFullscreenWorkspace. utils/http.ts (postJson/postBlob, XSRF from the cookie, not the meta tag), utils/text.ts. vitest + happy-dom (130 tests, pure TS).
- Editor picker sits beside the desk from `min-[1440px]`, below that a floating toggle panel.
- `resources/css/document-body.css` is shared by editor and PDF. Signature tables are selected by `table:has(td[style*='border-top'])` because ProseMirror overwrites the table `style` in the editor DOM; stored HTML keeps it (editor/extensions/tableStyle.ts keeps raw `style` on table/row/cell nodes).
- `DocumentSheet` is the ONLY `v-html` in resources/js (server-sanitized HTML only; unsaved `form.body` is shown by Tiptap, never through the sheet).

**Flow:**
- Platform admin: Settings -> Šablóny dokumentov -> create global (POST /global) or edit global row. Firm Admin/Sekretárka: sees global + own rows; global -> customize -> own override (edit, preview, PDF); later global edit -> override state base_changed -> acknowledge (keep) or reset (back to global text). Global edits reach firms without an override immediately; existing contracts never change (text is copied at generation).

**Depends on:** tenancy (tenant_id, current_tenant_id), identity (users.is_platform_admin), roles (document_templates permissions), PDF generation (chrome driver), EmploymentContractTypeEnum.

**Depended on by:** documents (Document.document_template_id nullOnDelete, `template()` withTrashed), contracts (Contract.document_template_id, options for the create flow, `ContractService::buildHtml`), quotes (QuoteService::syncDocument, QuoteFormContextData.templates), employees (EmployeeService::employmentTemplate picks by employment_type), QuoteService::convertToContract, ContractTextService.

**If you change Core, check:**
- DocumentTemplateCatalogue keys vs `DocumentTokenData` FE group keys (`document_token_group_<group>` lang keys) and DocumentContextBuilder::forContract/forQuote/sample (every catalogue key must have a value source or stay verbatim by design).
- Blocks vs DocumentHtmlSanitizer: `sanitize(block) === block` (DocumentTemplateRendererTest); blocks use only p/headings/lists/tables + inline style.
- TenantOrGlobalScope consumers: any query on DocumentTemplate from a job/console sees all firms.
- DocumentTemplatePolicy global-row branch vs FE `can.*` (DocumentTemplateEditData.can).
- Token syntax: stored `{{key}}` without spaces (parser tolerates spaces, serializer never emits them); FE `templateTokenHtml.ts` round trip is byte-stable (spec).
- FE <-> BE enum/helper mirror: `CONTRACT_CATEGORY_CONTRACTABLE_TYPES` vs `ContractCategoryEnum::allowedContractableTypes()`.

**Keywords (SK):** šablóna dokumentu, globálna šablóna, vlastná verzia šablóny, premenná, token, blok, podmienka, predvoľba hlavičky a pätky, náhľad, platform admin.

### documents — Polymorphic stored text and files (quote text, contract text, employee files; 2026-10-31)

**Core:**
- App\Models\Document — UUIDv7; traits BelongsToTenant, HasFactory, HasUuids, InteractsWithMedia, LogsActivity (logOnly type,title,document_template_id; **body excluded**), SoftDeletes (except employee files, see below). Columns: tenant_id FK restrict, `documentable` uuid morph (Contract | Quote | TenantMembership; FQCN for Contract/Quote, alias `tenant_membership`), document_template_id nullable FK (nullOnDelete), type (DocumentTypeEnum), title, **body text nullable with `encrypted` cast** (resolved employment text contains rodné číslo; never queried by SQL; key rotation via APP_PREVIOUS_KEYS), show_header, show_footer (default true), timestamps, soft_deletes; index (tenant_id,type). Media collections `file` and `pdf` (both singleFile) on `config('documents.disk')`. Relations: documentable() MorphTo, template() withTrashed. Contract::document() and Quote::document() are MorphOne; TenantMembership::documents() MorphMany.
- App\Services\Documents\ContractTextService — `canReadText(Contract, User)` (membership-subject contracts need ViewEmployeePersonalData), `displayBody(Contract)` (expands any remaining `{{block.*}}` for display), `freezeBlocks(Contract)` (writes the expanded body back; called inside ContractService::sign), `missingTokens`, `tokens`, `terms(Contract): ContractGenerateData`. **Blocks are frozen on sign and for non-draft contracts** (migration 2026_10_31_000500, forward-only, no `down`).
- App\Services\Documents\QuoteTextService — `hasText`, `isFrozen` (status != Draft), `resolvedBody` (draft: re-render against current quote data; non-draft: stored body), `missingTokens`, `tokens`, `tenant`.
- App\Services\Documents\EmployeeDocumentService (final readonly): `attach(TenantMembership, EmployeeDocumentUploadData): Document` (transaction; Document with type employee_*, show_header/footer false, media `file`), `delete(Document)` (**forceDelete, file included — hard delete, no undo**: a soft-deleted ID scan would stay in the bucket), `stream(Document, User): StreamedResponse` (activity event `downloaded`, `Content-Disposition: attachment`, `X-Content-Type-Options: nosniff`; no temporary URLs, no shareable link).
- App\Listeners\StoreSignedContractPdf (queued, #[Tries(3)], #[Backoff([10,30,60])], on ContractSigned): binds `current_tenant_id` + Spatie team id (queue workers start unbound and `Media::creating` throws) and restores both in `finally`, loads Contract withoutGlobalScope(TenantScope), idempotent (skips when the `pdf` media exists), stores `RendersContractPdf::renderFresh()` bytes. `ContractPdfService::render` serves the stored `pdf` media when present, so later firm edits never change a signed PDF.

**Satellites (BE):**
- Policy DocumentPolicy (employee files only): attach -> ManageEmployeeDocuments + ViewEmployees + same tenant; download -> ViewEmployeeDocuments + ViewEmployees + employee-file type + same tenant; delete -> Manage + ViewEmployees + employee-file type. Seeded to Admin only (cleaners and everyone else: none). Migration 2026_10_31_000400 attaches the 3 new permissions to existing Admin roles and `view employee_personal_data` to Účtovníčka (targeted SQL, customized roles untouched).
- Controller EmployeeDocumentController: POST /employees/{employee}/documents (multipart, `employees.documents.store`), GET .../{document}/download, DELETE .../{document} (all whereUuid, scoped binding so a Document must belong to that employee; foreign employee -> 404). DTO EmployeeDocumentUploadData (type must be employee_*; file rule `mimetypes:` from config = sniffed content type, `max:` kb), EmployeeDocumentData (download_url), EmployeeDocumentConstraintsData.
- Disk: DOCUMENTS_DISK `local` in dev/tests, `s3` intended for production (`league/flysystem-aws-s3-v3`, s3 disk `throw => true`; credentials env only). **S3 has NOT been exercised against a real bucket** (tests use local disk / Storage::fake) — manual check pending.

**Depends on:** tenancy, media (MediaLibrary Document collections, Media viewer exclusion), document-templates, contracts, quotes, employees.

**Depended on by:** contracts (body, header/footer flags, signed PDF), quotes (text + uploaded file), employees (files card).

**If you change Core, check:**
- Encrypted `body` cast: any SQL/LIKE on documents.body is impossible; migrations touching bodies must decrypt via `Crypt::decryptString` (see 000200 and 000500).
- MediaService::index + MediaPolicy::view exclusion of `Document` morph (uses `(new Document)->getMorphClass()`).
- Contract/Quote delete: their Document is soft-deleted in the owner's transaction (ContractService::delete / QuoteService::delete), employee-file documents are force-deleted.
- StoreSignedContractPdf tenant binding vs Media::creating.
- DocumentPolicy::isEmployeeFile vs `DocumentTypeEnum::isEmployeeFile()`.

**Keywords (SK):** dokument, súbor zamestnanca, občiansky preukaz, povolenie, podpísaná zmluva PDF, šifrované telo, S3, súkromný disk.

### contracts — Service agreements, employment contracts, lifecycle, document-based text (Phase 6, 2026-09-06; reworked 2026-10-31)

**Core:**
- App\Models\Contract — UUIDv7; traits BelongsToTenant, HasFactory, HasPdfFilename, HasUuids, LogsActivity (logOnly status,signed_at,terminated_at,number,title,contractable_id), SoftDeletes. Columns: tenant_id FK, **document_template_id** FK (nullOnDelete, replaces contract_template_id; `documentTemplate()` withTrashed), quote_id FK (nullOnDelete, `quote()` withTrashed), uuidMorphs('contractable') (**three subjects**: aliases `cleaning_object`, `tenant_membership`, `client` registered in AppServiceProvider `Relation::morphMap`; several contracts per subject allowed), category, status, term_type, title (255), number (50, nullable, free text), valid_from, end_date (required when fixed), signed_at, terminated_at, termination_reason, notes, timestamps, soft_deletes. **`contracts.body` is gone** — the text lives in `document(): MorphOne<Document>` (type from `DocumentTypeEnum::fromCategory`, encrypted body, show_header/show_footer). Relations: documentTemplate, quote, contractable (MorphTo), employmentContract HasOne, document. Methods: isEditable() / canBeSigned() (Draft), canBeTerminated() (Active), contractableLabel().
- App\Models\EmploymentContract — unchanged 1:1 child (UUIDv7, BelongsToTenant only, restrict FK, unique contract_id).
- App\Services\ContractService (final readonly; ctor adds ContractTextService, renderer, catalogue, sanitizer, DocumentContextBuilder, DocumentPdfRenderer): `paginate(Request, User $actor)`; `listFor(CleaningObject|Client|TenantMembership, User): ?array` (null without ViewContracts; newest 50; **a Client lists contracts on the client AND on its objects**); `canCreateFor(subject, User)` (membership subject also needs ViewEmployeePersonalData); `generate(ContractGenerateData, User): DocumentRenderData` and `generatePdf(...)` (resolve template or skeleton against the subject, blocks expanded, missing values stay as literal `{{key}}`); `create(ContractUpsertData, User $actor, ?Quote)` (asserts category<->subject, actor-may-see for employee subjects, transaction: Contract + Document + optional EmploymentContract; body re-resolved on save so chips survive); `update(Contract, ContractUpsertData, User)` (Draft only); `sign(Contract)` — guard canBeSigned, **422 on `status` while unresolved tokens remain**, transaction: freezeBlocks, status Active + signed_at, ContractSigned afterCommit; `terminate`, `delete` (Draft only, Document soft-deleted).
- **Category <-> subject** (`ContractCategoryEnum::allowedContractableTypes()`): employment -> tenant_membership; service_agreement -> cleaning_object, client; nda/gdpr/other -> all three. Mismatch = 422 `contractable_type`. WorkBreakdownService returns null for non-object subjects (client contract signs without Rozpis prác or jobs); DashboardMetricsQuery/DashboardAlertsQuery count `whereIn contractable_type [cleaning_object, client]` for service agreements.
- **Employee-subject gate (fail-closed):** ContractPolicy viewBody/update/sign/delete/downloadPdf additionally need `ViewEmployeePersonalData` when contractable is a membership (resolved text can contain rodné číslo). `ContractDetailData.body` is null without it; `ContractFormContextData.memberships` is [] without it; ContractService create/generate throw AuthorizationException. `can_be_signed` / `is_editable` on list AND detail DTOs fold in missing tokens + this permission.
- App\Services\PlaceholderResolverService now only serves **invoice mail** (`resolve` plain-text, `invoiceMailVariables`, `invoiceMailCatalog`); `variablesFor`/`catalog` and PlaceholderCatalogData were removed (replaced by DocumentTemplateCatalogue + DocumentContextBuilder). Consumers: InvoiceSettingsController, Notifications\InvoiceIssued.
- Events ContractSigned/ContractExpired/ContractExpiring (ShouldDispatchAfterCommit); listeners (auto-discovered): ContractSigned -> GenerateWorkBreakdownFromSignedContract (sync) AND StoreSignedContractPdf (queued); ContractExpired/Expiring -> Notify*.

**Satellites (BE):**
- DTOs: ContractUpsertData (title, number, category, term_type, contractable_type/id, document_template_id nullable, body max 200000, show_header, show_footer, valid_from, end_date, notes, ?employment; rules: subject exists in tenant, template usable (App\Rules\UsableDocumentTemplate)), ContractGenerateData (template id or body, either/neither = skeleton; plus subject, terms, employment, quote_id, frame flags), ContractCreatePrefillData (invalid/foreign query values come back null, never 422), ContractFormContextData {objects, clients, memberships, templates: DocumentTemplateOptionData[], tokens: DocumentTokenCatalogData, letterhead, footer}, ContractDetailData (body ?string sanitized HTML, show_header/footer, letterhead, footer, missing_tokens, tokens, document_template_id/name, quote link, flags), ContractListItemData, EmploymentContractUpsertData/EmploymentContractData, ContractTerminateData, MembershipOptionData.
- Enums: ContractStatusEnum, ContractTermTypeEnum, EmploymentContractTypeEnum, ContractCategoryEnum (`allowedContractableTypes()` replaces `expectedContractableType()`), ContractableTypeEnum (cleaning_object|tenant_membership|client; modelClass/table/label).
- Policy ContractPolicy: viewAny/view -> ViewContracts; viewBody/downloadPdf -> ViewContracts + text gate; create -> CreateContracts; update -> EditContracts + isEditable + text gate; sign -> EditContracts + canBeSigned + text gate; terminate -> TerminateContracts + canBeTerminated (stays available without the text gate); delete -> DeleteContracts + isEditable + text gate.
- Controller ContractController (#[NavItem] contracts order 36): index, `create` (prefill query `contractable_type, contractable_id, category, employment_type, document_template_id`), store, **generate / generate-pdf** (POST, `throttle:document-preview`), show + edit call `Inertia::encryptHistory()`, update, destroy, sign, terminate, pdf. Routes: GET|POST /contracts, GET /contracts/create, POST /contracts/generate, POST /contracts/generate-pdf, GET|PUT|PATCH|DELETE /contracts/{contract}, GET /contracts/{contract}/edit, POST .../sign, POST .../terminate, GET .../pdf.
- Commands CheckContractExpiry, config/contracts.php, factories (ContractFactory forObject/forMembership/forClient/fromQuote), pdf view now `pdf.documents.default` via DocumentPdfRenderer.
- Migrations: 2026_10_31_000100 (document_templates; copies contract_templates, swaps contracts.contract_template_id -> document_template_id, drops contract_templates), 000200 (documents; wraps every existing contract body — soft-deleted included — as an encrypted paragraph body prefixed with `{{block.parties}}` and suffixed with `{{block.signatures}}`, moves Quote `document` media onto Documents, drops contracts.body), 000400 (permission rename/insert + `activity_log.subject_type` `App\Models\Client` -> `client`), 000500 (freeze blocks in non-draft contracts). All forward-only data migrations; 000100/000200 have `down()`, 000500 has none.

**FE Satellites:**
- Pages Contracts/{Index,Create,Edit,Show}. **Create = two steps** in one `useForm` inside ContractForm: ContractSetupStep (subject, category, template select, dates, employment) then ContractDocumentStep (A4 sheet = preview and editor, frame toggles, PDF button, missing-token notice); Edit opens on step 2. `useContractDocument` owns generate / isEdited / isStale / derived `missing` list; an edited body asks before regenerate. Show: document in a full-width row; `body === null` -> no-access alert (no sheet, no Edit/Sign/Delete/PDF; Terminate stays); Sign button only when `can_be_signed`.
- Components: ContractStatusBadge/CategoryBadge/TermBadge, ContractSubjectFields (radio of allowed subject types; `lockedLabel` for prefill), EmploymentContractFields, ContractForm, ContractTerminateModal, ContractPartiesCard/TermCard/EmploymentCard/ActionsCard/LinksCard (client link added), RelatedContractsCard (on Clients/Objects/Employees Show; rendered when `contracts !== null || canCreateContract`), StatusErrorAlert (shows `errors.status`; used on Contracts/Show + Index and Quotes/Show). Removed: ContractBodyEditor, ContractBodyPreview.
- Utils: `CONTRACT_CATEGORY_CONTRACTABLE_TYPES` (Record<category, type[]>, must mirror `allowedContractableTypes()`), `PlaceholderTokenList` stays for invoice mail only.

**Flow:**
- Create from a Client/Object/Employee Show: "Vytvoriť zmluvu" link with prefill -> step 1 (subject locked) -> Continue: Precognition on step-1 fields, POST /contracts/generate -> resolved sheet with chips for missing values -> edit -> POST /contracts -> Show (Draft). Sign refused (422 on `status`, Sign hidden) while chips remain. Sign -> Active, blocks frozen, ContractSigned -> work breakdown (object subjects) + signed PDF snapshot (queued).
- PDF: GET /contracts/{id}/pdf (stored snapshot once signed, otherwise rendered fresh; drafts show leftover tokens as marks).

**Depends on:** tenancy, document-templates (template options, renderer, catalogue, context builder), documents (text + signed PDF), clients / objects / employees (subjects), quotes (backlink), employees permission `view employee_personal_data`.

**Depended on by:** Quote::contracts(), QuoteService::convertToContract (now redirects to `contracts.edit` so the converted draft is reviewed first), EmployeeService::create, TenantMembership::employmentContracts(), Client::contracts() / CleaningObject::contracts() (MorphMany), schedule (GenerateWorkBreakdownFromSignedContract), dashboard (contracts metrics + contract_expiring alerts, `whereIn` over object+client), notifications.

**If you change Core, check:**
- ContractStatusEnum matrix vs sign/terminate/delete/update guards; CheckContractExpiry filter.
- `allowedContractableTypes()` vs FE `CONTRACT_CATEGORY_CONTRACTABLE_TYPES` and `AppServiceProvider` morph map vs ContractableTypeEnum values.
- ContractPolicy text gate vs ContractDetailData / ContractListItemData flags (`can_be_signed`, `is_editable`) and ContractService::assertActorMaySee*.
- DashboardAlertsQuery:218 / DashboardMetricsQuery:267 `whereIn` subject types when a new subject type is added.
- Blocks frozen on sign: ContractTextService::freezeBlocks vs migration 000500 logic.
- StoreSignedContractPdf idempotency vs ContractPdfService::render stored-media branch.
- Quote.withTrashed() on Contract.quote(); EmploymentContract restrict FK.

**Keywords (SK):** zmluva, zmluvná strana, predmet zmluvy, klient ako zmluvná strana, pracovná zmluva, podpis, podpísané PDF, zamrazenie blokov, chýbajúce premenné, ukončenie.

### quotes — Contract conversion (Phase 6) and text documents (2026-10-31)

Updated from phase 5:
- Quote::contracts() HasMany -> [Contract] (quote_id nullOnDelete). Loaded in QuoteDetailData via eager load; QuoteDetailData.contracts: QuoteContractLinkData[] (id, title, number, status).
- QuoteService::convertToContract(Quote, QuoteConvertToContractData, User $actor): Contract — requires Accepted + itemized + client_id + cleaning_object_id; optional `document_template_id` (`DocumentTemplate::query()->findOrFail`, no type filter at service level), body = template body or `DocumentTemplateCatalogue::skeleton(ServiceAgreement, true)` (quote blocks); delegates to ContractService::create($upsert, $actor, $quote). Re-conversion allowed. **Controller now redirects to `contracts.edit`** (review first), not `contracts.show`.
- QuoteController::show prop contractTemplates: DocumentTemplateOptionData[] (service_agreement type via DocumentTemplateService::options; empty unless Accepted + itemized + can create contracts). QuotePolicy::convertToContract -> CreateContracts. Errors: quote_document_not_convertible, quote_not_acceptable_for_conversion, quote_client_required_for_contract, quote_object_required_for_contract.
- **Quote text document (new):** `Quote::document(): MorphOne<Document>`; Quote is **no longer HasMedia**. Itemized quote may carry a text Document (type quote, body kept raw with tokens, sanitized on save). QuoteUpsertData adds `document_template_id`, `document_body` (max 200000, keeps tokens), `document_show_header`, `document_show_footer` (prohibited when kind = document; "no template" sends both null and deletes the text document). QuoteService::syncDocument validates tokens via renderer->violations (422 on `document_body`). **Draft text follows the quote data** (QuoteTextService::resolvedBody re-renders at PDF time); **QuoteService::send resolves and freezes the body in one transaction and returns 422 on `status` while tokens remain**; non-draft quotes keep the stored text. No text document -> existing `pdf/quotes/default.blade.php` prints as before. Duplicate: Draft source copies the body, otherwise reseeds from the template if it still exists, else no text document.
- QuoteDetailData adds `text_document: QuoteTextDocumentData|null` {id, template id/name, body (resolved; raw for the editor while Draft), show_header/footer, is_frozen, missing_tokens, tokens, letterhead, footer}; `document: MediaFileData|null` keeps its shape. QuoteFormContextData adds templates: QuoteTemplateOptionData[] {id,name,scope,body raw}, tokens, letterhead, footer. QuoteService ctor adds QuoteTextService, DocumentTemplateRenderer, DocumentHtmlSanitizer, DocumentTemplateCatalogue.
- **Quote file storage (reworked):** uploaded document-kind files now live on the quote's Document, collection `file`, disk `config('documents.disk')` (env DOCUMENTS_DISK, default local = private); upload still goes FE `/uploads` -> `document_uuid` -> `TemporaryUploadService::moveToModel($document, 'file', ...)`. config/quotes.php keeps `document.disk` (same env) / max_size_kb / allowed_mimes for the DTO constraints. Itemized quote PDFs (QuotePdfService) are rendered on demand, never stored. QuoteService::convertToInvoice seeds invoice header_text/footer_text from TenantInterface defaults.
- FE: Components/Quotes/{QuoteTextDocumentCard (form: template select, frame toggles, status line, "Upraviť text"; itemized only), QuoteTextDocumentWorkspace (fullscreen editor overlay: role=dialog, Esc closes), QuoteTextDocumentPanel (Show; full-width row via DocumentSheet)}; composables useQuoteTextDocument (pick/confirm overwrite), QuoteConvertToContractModal renamed `document_template_id`. Send button disabled while `text_document.missing_tokens` non-empty; StatusErrorAlert covers the race. Preview = existing `GET /quotes/{quote}/pdf` after save.
- Phase 7 update: QuoteItem.frequency (TaskFrequencyEnum) feeds work-breakdown generation when an Accepted quote is converted.

### employees — Employee management over TenantMembership (Phase 7, 2026-09-06)

**Core:**
- App\Services\EmployeeService (final readonly, ctor DatabaseManager, PermissionRegistrar, RoleAssignmentGuard): `paginate(Request): LengthAwarePaginator<EmployeeListItemData>` (QueryBuilder TenantMembership with eager load user, filters search name/email/first_name/last_name/role, is_active boolean, sorts -joined_at, default); `create(EmployeeStoreData, User $actor): TenantMembership` — resolves single `role_name` via `Role::inTenant`, `assertAssignable($actor,[role])` + `assertPermissionsGrantable($actor, permissions)` before the transaction; transaction: lookup User by email or create (password null, locale default, is_active), 422 `already_member` if active membership exists, else create/reactivate membership with profile fields, `syncRoles([role])` + `syncPermissions(direct overrides)`, **if `employment` DTO present: creates an Employment-category Contract via `ContractService::create(ContractUpsertData::from([...]))` (title `<display_name> — employment_contract_default_title`, term indefinite, contractable = this membership, template = DocumentTemplateService::options(Employment, employment_type) preferring an exact employment_type match (a TPP employee never gets the živnosť template), body = template body or the employment skeleton, `$actor` passed through so the employee-subject gate applies, valid_from today, employment child from DTO)**, new user only → `RegistrationService::createInvitation(tenant, user, email, role_name)` (InvitationCreated mail); `update(TenantMembership, EmployeeUpdateData, User $actor): TenantMembership` — profile fields (first/last/phone/position) + syncRoles + syncPermissions with the same guards; **does NOT touch employment contract data** (EmployeeUpdateData has no `employment`; contract edits go through the Contracts module). Both create and update write the personal columns via `personalAttributes($data, $actor)` only when the actor holds ViewEmployeePersonalData (otherwise the payload is ignored and existing values stay); `national_id` is normalized to `YYMMDD/XXXX` (`^\d{6}/?\d{3,4}$`); `deactivate(TenantMembership): int` — is_active=false + `JobService::unassignFutureForMembership` (returns count of unassigned jobs); `reactivate(TenantMembership): void`; `assignRole(TenantMembership, string $roleName, User $actor): TenantMembership` (guard + syncRoles). Ctor: DatabaseManager, PermissionRegistrar, RoleAssignmentGuard, JobService, ContractService, RegistrationService.
- TenantMembership extended (phase 7): nullable profile columns first_name, last_name, phone, position; LogsActivity; `employmentContracts(): MorphMany<Contract>` = `morphMany(Contract::class,'contractable')->where('category','employment')` (a membership can hold several employment contracts; FE/DTOs use the latest by created_at and read its `employmentContract` child); `scheduledJobs(): HasMany` (assigned_membership_id); `display_name` accessor (first+last, fallback user name/email). **Personal data (2026-10-31):** birth_date, `national_id` (text, `encrypted` cast, `#[Hidden]`, never in logOnly), street, city, postal_code, company_ico; `documents(): MorphMany<Document>`. Detail DTO shows only `national_id_masked`; only Employees/Create + Edit send the full value; Edit logs activity event `national_id_viewed` (no value in properties). No observer exists (app/Observers absent); no EmploymentContract-level hooks.
- App\Policies\TenantMembershipPolicy (rbac-full): viewAny → ViewEmployees; view → ViewEmployees + sameTenant; create → CreateEmployees; update → EditEmployees + sameTenant; delete (gates deactivate) → DeleteEmployees + sameTenant + not self + not tenant owner; reactivate → update; assignRole → AssignEmployees + sameTenant.
- RoleAssignmentGuard::assertAssignable(User $actor, iterable $roles) — validates each role's permission set ⊆ actor's permissions (prevents privilege escalation); throws ValidationException 422 if violated.

**Satellites (BE):**
- DTOs (all #[TypeScript], #[MergeValidationRules]): EmployeeStoreData (#[Required, Email] email, ?first_name/last_name/phone/position, #[Required] role_name validated `Rule::exists('roles','name')` scoped to tenant, ?permissions list<PermissionEnum value>, ?EmploymentContractUpsertData employment — no password, no is_active), EmployeeUpdateData (profile fields + role_name + permissions; no email, no employment), EmployeeListItemData (membership + user fields, roles, is_active, joined_at, employment_type from latest employment Contract's child, upcoming_jobs_count), EmployeeDetailData (+ employment_contract_id, employment_contract: ?EmploymentContractData, can flags), EmployeeRoleData (role_name), EmployeeFormContextData.
- Enums: existing EmploymentContractTypeEnum reused (dpp/dpc/tpp/self_employed).
- Factories: TenantMembershipFactory states withProfile() (sets first/last/phone/position), inactive().
- Seeders: RegistrationService + UserSeeder pass (no new seeder; role templates seeded at tenant bootstrap, EmployeeService handles creation).
- Policies: TenantMembershipPolicy (instance abilities require record-level tenant membership + active status checks).
- Controllers: EmployeeController with #[NavItem(label: 'app.employees', route: 'employees.index', icon: 'IdentificationIcon', order: 20)] · actions index/create/store/show/edit/update/deactivate/reactivate/role (no destroy) · role: `#[Authorize('assignRole','employee')]`.
- Routes (all whereUuid on {employee}): GET /employees, /employees/create, /employees/{employee}, /employees/{employee}/edit; POST /employees (Precognition), PUT|PATCH /employees/{employee} (Precognition), POST /employees/{employee}/deactivate, POST /employees/{employee}/reactivate, POST /employees/{employee}/role (Precognition). **No DELETE route — employees are deactivated, never deleted.** Files: POST /employees/{employee}/documents, GET .../documents/{document}/download, DELETE .../documents/{document} (EmployeeDocumentController, see domain documents; Admin only by default).
- Permissions: ViewEmployees, CreateEmployees, EditEmployees, AssignEmployees, DeleteEmployees (seeded in RoleTemplatesSeeder). Users module coexists (nav in settings group order 15; Employees nav order 20).
- i18n: employee_{added,updated,deactivated,deleted,no_active}, employee_role.*, permission.* (per-permission labels), permission_group.*, roles table (Vlastník, Vedúca, Sekretárka, Účtovníčka, Interná upratovačka, Zákazník + order/phase labels).

**FE Satellites (phase 7-employees-schedule-fe plan):**
- Pages: Employees/{Index,Create,Edit,Show}.vue.
- Components (Components/Employees, verified ls): EmployeeForm, EmployeeProfileFields, EmployeeProfileCard, EmployeeEmploymentSection (reuses Contracts EmploymentContractFields), EmployeeRoleFields, EmployeeRoleCard, EmployeeRoleModal (assign role; escalation prevention), EmployeeMetaCard, EmployeeActionsCard, EmployeePersonalFields + EmployeePersonalCard (hidden when `personal === null`; masked id only on Show), EmployeeDocumentsCard + EmployeeDocumentUploadForm (hidden when `documents === null`; plain multipart file input with `forceFormData`, NOT FileUploadInput; download is a plain `<a>`; delete via ConfirmDeleteModal stating irreversibility), RelatedContractsCard (create link `contractable_type=tenant_membership&category=employment`). Filters are inline via shared TableFilters (no EmployeeFiltersBar).
- AppLayout.vue ICONS += IdentificationIcon. Nav: Employees top-level (phase 7, D1 user decision keeps Users in settings group, Employees in default group order 20).

**Flow:**
- List: filters (search, role, active status), sorts -joined_at. Create: new user (auto-verified, random password, InvitationCreated mail) or link existing (lookup by email, password required if not new). Show: profile + roles + employment contract + actions. Edit: profile + role (with escalation guard showing role options ⊆ actor's), permission overrides (actor can only grant perms they hold), employment contract edit/create. Deactivate: is_active=false (future jobs unassigned via JobService hook); Reactivate flips it back. Removal from a tenant is the Users module (UserService::delete), not Employees.
- POST /employees/{employee}/role — specialized endpoint for Vedúca role-change authority (AssignEmployees permission); returns EmployeeDetailData with updated can flags, displays error 422 if escalation attempted.

**Depends on:** tenancy (TenantMembership tenant_id FK), identity (User model, email unique), roles/permissions (Spatie Permission per-tenant, RoleAssignmentGuard::assertAssignable + assertPermissionsGrantable), contracts (ContractService::create for the initial Employment contract; DocumentTemplateService employment-template lookup; TenantMembership::employmentContracts), schedule (JobService::unassignFutureForMembership), RegistrationService::createInvitation.

**Depended on by:** JobService::unassignFutureForMembership (phase 7 cleaner deactivation hook), EmployeeDetailData.can (FE action availability), Users module coexistence (both write tenant_memberships, Users nav in settings).

**If you change Core, check:**
- EmployeeService methods + controller actions + forms (EmployeeStoreData/EmployeeUpdateData, EmployeeDetailData).
- RoleAssignmentGuard assertions vs policy enforcement (both needed for escalation prevention).
- TenantMembership profile columns + LogsActivity logOnly fields.
- TenantMembership::employmentContracts (MorphMany<Contract> category=employment) vs EmployeeListItemData/EmployeeDetailData (read latest contract's employmentContract child) and Employees eager loads `employmentContracts.employmentContract`.
- POST /employees/{employee}/role error responses (422 validation, 403 authorization).
- AppLayout nav order (Users 15 settings, Employees 20 default).
- Personal-data gate: `ViewEmployeePersonalData` in EmployeeController (Create/Edit/Show props), EmployeeService::personalAttributes, ContractPolicy/ContractService employee-subject gate; DTO `personal` null = fail-closed. Seeded to Admin and Účtovníčka.
- File routes vs DocumentPolicy (ViewEmployeeDocuments/ManageEmployeeDocuments + ViewEmployees, Admin only) and EmployeeDocumentService hard delete.

**Keywords (SK):** zamestnanec, člen tímu, rola, povolenie, pracovná zmluva, zákazka, aktivita, deaktivácia, eskalačná ochrana, priradenie role.

### schedule — Work breakdown + scheduled jobs, calendar view, cleaner assignment (Phase 7, 2026-09-06)

**Core:**
- App\Models\WorkBreakdown (rozpis prác) — UUIDv7; traits BelongsToTenant, HasFactory, HasUuids, LogsActivity (logOnlyDirty), SoftDeletes. Columns: tenant_id FK (restrictOnDelete), contract_id FK unique (restrictOnDelete → contract soft-delete blocks cascade anyway), timestamps, soft_deletes. Relations: contract BelongsTo (HasOne reverse via contract.work_breakdown relation), tasks HasMany WorkBreakdownTask (cascade delete), schedule HasMany ScheduledJob (via generated tasks?).
- App\Models\WorkBreakdownTask — child of WorkBreakdown; UUIDv7; traits HasFactory, HasUuids. Columns: work_breakdown_id FK (restrictOnDelete, cascade delete), name (255), description (text, nullable), frequency (TaskFrequencyEnum backed string 50, default 'one_time'), position (int, sort order), timestamps. NO soft_deletes, NO LogsActivity. Cascade delete on breakdown. Scopes: byFrequency(frequency). Methods: nextOccurrence(date): date helper (one_time → null, weekly_1x → add 7 days, seasonal → add 365 days), recurrenceLabel(): string (for display).
- App\Models\ScheduledJob (Zákazka, table `cleaning_jobs` to avoid framework `jobs` collision) — UUIDv7; traits BelongsToTenant, HasFactory, HasUuids, LogsActivity (logOnlyDirty), SoftDeletes. Columns: tenant_id FK (restrictOnDelete), cleaning_object_id FK (restrictOnDelete), assigned_membership_id FK (nullableOnDelete, nullable), work_breakdown_id FK (nullOnDelete, nullable), work_breakdown_task_id FK (nullOnDelete, nullable), type (JobTypeEnum, no DB default), status (JobStatusEnum, default 'unassigned'), scheduled_date (date), start_time + end_time (time, nullable), note (text, nullable), gps_lat/gps_lng (decimal 10,7 nullable), **started_at (timestamp nullable, phase 10)**, completed_at + cancelled_at (timestamp nullable), timestamps, soft_deletes; also contract_id + invoice_id FKs (nullOnDelete, nullable). Indices: (tenant_id, status), (tenant_id, scheduled_date), cleaning_object_id, (assigned_membership_id, scheduled_date), plus partial UNIQUE `cleaning_jobs_recurrence_unique`. Relations: cleaningObject BelongsTo (eager + loadMissing in list queries), assignedMembership BelongsTo TenantMembership (nullable, active+tenant scoped per ActorScoping), workBreakdown BelongsTo (nullable). Scopes: `scopeVisibleTo(Builder, User $actor)` (fail-closed: full tenant set with ViewAllSchedule, else only jobs on the actor's own active membership, else `1=0`); `isVisibleTo(User $actor): bool` (predicate for single-row checks, replaces ->visibleTo filter). Methods: `canBeStarted()`, `canBeCompleted()`, `canBeUnapproved()` (derived from `JobStatusEnum::canTransitionTo`, phase 10), `isEditable(): bool` (delegates to JobStatusEnum::isEditable — everything except Completed|Cancelled), `canBeAssigned(): bool` / `canBeCancelled(): bool` (both Unassigned|Planned, explicit in_array, NOT matrix-derived).
- App\Enums\TaskFrequencyEnum (8 backed string values: one_time, weekly_1x, weekly_2x, weekly_3x, weekly_4x, weekly_5x, weekly_6x, seasonal; #[TypeScript]). Methods: `monthsInterval(): int` (weekly → 0, seasonal → 12), `nextRunDate(date $from, date $limit): ?date` (calculates next occurrence within limit, applies recurrence math).
- App\Enums\JobStatusEnum (unassigned, planned, in_progress, completed, unapproved, cancelled; #[TypeScript]). Methods: `canTransitionTo(JobStatusEnum): bool` (state matrix: Unassigned→Planned|Cancelled, Planned→InProgress|Cancelled, InProgress→Completed|Unapproved; Completed/Unapproved/Cancelled terminal), `isEditable(): bool` (everything except Completed|Cancelled — InProgress and Unapproved stay editable), `isTerminal(): bool`, label().
- App\Enums\JobTypeEnum (regular, one_off, special; #[TypeScript]). Methods: label().
- App\Services\WorkBreakdownService (final readonly, ctor JobService): `generateFromContract(Contract $contract): WorkBreakdown` — idempotent (exists check via contract_id unique), transaction: create WorkBreakdown + task per quote item (frequency from item.frequency or 'one_time' default), return fresh. Called synchronously by ContractSigned listener (afterCommit wired).
- App\Services\JobService (final readonly, ctor DatabaseManager): `paginate(Request, User $actor): LengthAwarePaginator<JobListItemData>` (QueryBuilder with eager loads object.client + assigned_membership.user, filters search object name/client name, status/type/assigned_member dynamic, scheduled_date range, applies scopeVisibleTo if actor provided); `create(JobStoreData, User $actor): ScheduledJob` — transaction: validation, scopeVisibleTo check, create job, return fresh; `update(ScheduledJob, JobUpdateData, User $actor): ScheduledJob` — guard isEditable() + scopeVisibleTo, update, return; `assign(ScheduledJob, JobAssignData): ScheduledJob` — guard canAssign() + scopeVisibleTo, update assigned_membership_id, auto-transition to Planned if was Unassigned, return; `start(ScheduledJob): ScheduledJob` — guard `status->canTransitionTo(InProgress)` else ValidationException app.job_invalid_transition, set status→InProgress + started_at→now(); `complete(ScheduledJob): ScheduledJob` — same guard shape → Completed + completed_at; `unapprove(ScheduledJob): ScheduledJob` — same guard shape → Unapproved (no timestamp column); `cancel(ScheduledJob): ScheduledJob` — guard canBeCancelled(), set status→Cancelled, return; `unassignFutureForMembership(TenantMembership $membership): int` — called by EmployeeService::deactivate, unassigns all future jobs assigned to membership (WHERE assigned_membership_id = ? AND scheduled_date > today), returns count updated.
- App\Listeners\GenerateWorkBreakdownFromSignedContract — subscribes to ContractSigned event, calls WorkBreakdownService::generateFromContract, dispatches GenerateScheduledJobsJob (afterCommit, phase 7 design: materialization deferred to daily cron, not immediate).
- App\Jobs\GenerateScheduledJobsJob (queued, ShouldBeUnique, retries 3, ShouldDispatchAfterCommit in listener) — binds tenant context + setPermissionsTeamId on worker, iterates active WorkBreakdown rows, calls JobService::generateForBreakdown (rolling 30d horizon, creates ScheduledJob per task recurrence + assigned_date range, idempotent unique partial index on work_breakdown_task_id + scheduled_date per job).
- App\Console\Commands\GenerateScheduledJobsCommand (routes/console.php, daily `app:generate-scheduled-jobs`) — finds WorkBreakdown rows with next_run_at ≤ today (via recurring_invoices logic reuse OR direct job queries), dispatches GenerateScheduledJobsJob per row.
- Config: config/scheduling.php with horizon_days (default 30), excluded_dates (holidays).

**Satellites (BE):**
- DTOs: JobStoreData (#[Required, Uuid] cleaning_object_id, #[Required] scheduled_date: Date, ?string scheduled_time: Time, #[Required] type: JobTypeEnum, #[Nullable] notes, ?string contract_id UUID [Rule::exists...]), ScheduledJobIndexFilterData (object_id?, status?, type?, scheduled_date_from/to?, assigned_member?), JobListItemData (id, type, status, scheduled_date, scheduled_time, **started_at (phase 10)**, object_name, client_name, assigned_member_name, notes), ScheduledJobDetailData extends ListItemData + can (canAssign, canStart, canCancel, canComplete, canUnapprove, phase 10), **started_at**, ScheduledJobCalendarEventData (id, title, date, status, type, resource_id=assigned_membership_id), WorkBreakdownDetailData (id, contract_id/number, tasks: TaskData[]), TaskData (name, description, frequency, position).
- Enums: TaskFrequencyEnum, JobStatusEnum, JobTypeEnum (all #[TypeScript]).
- Factories: ScheduledJobFactory with states unassigned/planned/in_progress/completed, forObject(CleaningObject), forMembership(TenantMembership). WorkBreakdownFactory with forContract(Contract).
- Seeders: ScheduleDemoSeeder creates signed contract → breakdown → 30-day jobs, cleaner@example.com (Interná upratovačka role with 3 assigned jobs via EmployeeService).
- Policies: ScheduledJobPolicy (viewAny/view → ViewSchedule, create → CreateSchedule, update → EditSchedule, assign → AssignCleaners, cancel/start/complete/unapprove → EditSchedule). No `delete` ability — jobs are cancelled, never deleted over HTTP. Every per-row ability also requires `isVisibleTo($user)` plus its own state guard (`isEditable`/`canBeAssigned`/`canBeCancelled`/`canBeStarted`/`canBeCompleted`/`canBeUnapproved`), so a bad-state transition surfaces as 403, not 422.
- Controllers: ScheduledJobController with #[NavItem(label: 'app.schedule', route: 'jobs.index', icon: 'CalendarDaysIcon', order: 32)] · index/create/store/show/edit/update (no destroy) · custom actions assign/start/cancel/complete/unapprove (POST routes, phase 10: start/complete/unapprove added) · show() builds the `can[]` map (update/assign/cancel/start/complete/unapprove) consumed by JobActionsCard · GET /jobs/calendar (json endpoint, returns ScheduledJobCalendarEventData[], filters by date range ?from&to, ≤ 62 days per spec).
- Routes: GET|POST /jobs, GET|PUT|PATCH /jobs/{job} (no DELETE), POST /jobs/{job}/{assign|cancel|start|complete|unapprove}, GET /jobs/calendar. Precognition wraps store/update/assign ONLY — the four bodyless state transitions (cancel/start/complete/unapprove) sit outside that group; phase 10 added start/complete/unapprove.
- Permissions: ViewSchedule, CreateSchedule, EditSchedule, AssignCleaners (seeded in RoleTemplatesSeeder). Interná upratovačka missing ViewAllSchedule → own-only via isVisibleTo.
- i18n: job_status.* (planned/unassigned/...), job_type.* (regular/one_off/special), task_frequency.* (one_time/weekly_1x/seasonal), schedule_* (add/edit/created/assigned/cancelled/), work_breakdown.*.

**FE Satellites (phase 7-employees-schedule-fe plan; phase 10 update):**
- Pages: Schedule/{Index,Create,Edit,Show}.vue.
- Components: Schedule/{JobStatusBadge,JobTypeBadge,JobFiltersBar,JobList,JobCalendar (FullCalendar v6.1.21 month/week read-only, datesSet → useJobCalendar fetch, status colours), JobForm, JobAssignPanel (single select TenantMembership), JobDetailCard (shows started_at when present, phase 10), JobActionsCard (buttons: start/cancel/complete/unapprove/edit per state, phase 10), WorkBreakdownView (read-only Rozpis prác section)}, Objects/{ObjectWorkBreakdownsCard (read-only breakdown on Objects/Show)}, TimeInput.vue (HH:MM input).
- Composables: useJobCalendar (calendar state, fetch via `GET /jobs/calendar?from&to`, updates event list).
- Index: calendar⇄list toggle (default list), filters (object, status, type, date range, assigned member), sorts. Show: detail + assign panel + start/cancel/complete/unapprove actions (phase 10) + read-only WorkBreakdownView (tasks from work_breakdown relation). One `useDeleteConfirm` instance (phase 10) drives all 4 state-transition actions with action-name variant map for ConfirmDeleteModal.
- Objects/Show.vue gained read-only "Rozpis prác" card: WorkBreakdownDetailData if contract.work_breakdown exists.
- AppLayout.vue ICONS += CalendarDaysIcon. Nav: Schedule top-level (phase 7, order 32).

**Flow:**
- **Scheduled job generation:** Contract signed (ServiceAgreement) + attached quote → ContractSigned event → GenerateWorkBreakdownFromSignedContract listener (sync) → WorkBreakdownService::generateFromContract (create breakdown + tasks per item.frequency) → dispatch GenerateScheduledJobsJob (afterCommit) → daily `app:generate-scheduled-jobs` cron fires GenerateScheduledJobsJob (queued, ShouldBeUnique) → rolling 30d horizon materializes ScheduledJob rows per task recurrence (partial unique index prevents duplicates).
- **Cleaner assignment:** Vedúca/Vlastník views Schedule Index, picks job from list/calendar, clicks Assign → select TenantMembership (active only, own-object-scope via scopeVisibleTo) → Planned status, assigned_membership_id set, notification optional phase 7.
- **Own-only scoping (Interná upratovačka):** ScheduledJob::scopeVisibleTo(actor) filters to assigned_membership_id = actor.current_membership_id (no ViewAllSchedule permission). CleaningObject::scopeVisibleTo(actor) filters to objects with ANY job assigned to actor (date/status irrelevant per D3 override). Cleaner sees schedule of their own jobs + objects touched by them.
- **Deactivation hook:** EmployeeService::deactivate calls JobService::unassignFutureForMembership (unassigns all future jobs, no re-assignment).

**Depends on:** tenancy (tenant_id FKs), contracts (ContractSigned event, ServiceAgreement subject), quotes (QuoteItem.frequency enum input), employees (TenantMembership assignment, active scope), objects (cleaning_object_id FK, scopeVisibleTo actor scoping).

**Depended on by:** Invoicing v2 (phase 8+: time-tracking linkage via reserved invoice_id FK), employee deactivation (JobService hook), dashboard widgets (recent/upcoming jobs).

**If you change Core, check:**
- TaskFrequencyEnum recurrence logic (nextRunDate, monthsInterval) vs GenerateScheduledJobsJob task expansion.
- JobStatusEnum state matrix (canTransitionTo rules) vs JobService action guards (assign/start/cancel/complete/unapprove). Phase 10: added start/complete/unapprove; canBe* methods derive from state matrix.
- ScheduledJob::scopeVisibleTo/isVisibleTo actor logic vs EmployeeService cleaner role (missing ViewAllSchedule).
- CleaningObject::scopeVisibleTo reachability rule (ANY assigned job, regardless of status/date — D3 override explicit).
- ScheduledJob.started_at column (timestamp nullable) and casts datetime (phase 10, migration 2026_09_22_000100).
- ScheduledJobDetailData.can map (canStart, canComplete, canUnapprove flags phase 10) vs backend model canBe* methods.
- JobDetailCard started_at display binding (phase 10).
- JobActionsCard action buttons + confirmVariant map vs JobService start/complete/unapprove methods (phase 10).
- WorkBreakdownService::generateFromContract idempotency (contract_id unique constraint enforces single breakdown per contract).
- GenerateScheduledJobsJob unique constraint (ShouldBeUnique via tenantId+work_breakdown_id binding; max_lock_attempts=3).
- config/scheduling.php horizon_days + excluded_dates integration (cron reads these; command queries next_run_at ≤ today).
- ScheduleDemo Seeder flow (contract → breakdown → jobs materialized, cleaner assigned, status badges colored).
- FullCalendar v6.1.21 toolbar + month/week event rendering (status color mapping via token).

**Keywords (SK):** rozvrh, zákazka, rozpis prác, úloha, frekvencia, pracovník, priradenie, plán, preukazovateľnosť, kalendár, bez archiovania.

### notifications — In-app centre, bell, mail preferences, 6 event listeners (Phase 8, 2026-09-06)

**Core:**
- App\Models\Notification extends DatabaseNotification — UUIDv7 id (supplied by framework NotificationSender, not HasUuids); no BelongsToTenant (rows written from queue workers, no bound tenant); no LogsActivity; no SoftDeletes. Columns: id uuid PK, type (64 NotificationTypeEnum value), notifiable_type (string morph), notifiable_id (uuid morph), **tenant_id (uuid NOT NULL FK restrictOnDelete)**, jsonb data (`title`, `body`, `url`, `meta`), read_at (timestamp nullable, NULL = unread), timestamps. Indices: (tenant_id), (notifiable_type, notifiable_id, tenant_id, created_at), partial unique (notifiable_type, notifiable_id, tenant_id) WHERE read_at IS NULL. Relations: tenant() BelongsTo. Scopes: `scopeInTenant(Builder, $tenantId)`, `scopeForRecipient(Builder, User)`. Methods: `isOwnedBy(User): bool`, `typeEnum(): NotificationTypeEnum`.
- App\Enums\NotificationTypeEnum (#[TypeScript], backed string) — 8 cases: `InvitationCreated='invitation.created'` (mail-only), `InvoiceIssued='invoice.issued'` (mail-only), `InvoiceOverdue='invoice.overdue'` (in-app + configurable mail), `ContractExpiring='contract.expiring'` (in-app + configurable mail), `ContractExpired='contract.expired'` (in-app + configurable mail), `QuoteSent='quote.sent'` (in-app + configurable mail), `QuoteExpiring='quote.expiring'` (in-app + configurable mail), `QuoteExpired='quote.expired'` (in-app + configurable mail). Methods: `label(): string` (→ `__('app.notification_type_<key>')`), `defaultMailEnabled(): bool` (InvoiceOverdue=true, others false), `userConfigurable(): bool` (false for InvitationCreated+InvoiceIssued; true for others), `deliversInApp(): bool` (false for InvitationCreated+InvoiceIssued), `static configurable(): array` (returns user-configurable types), `static inAppOptions(): array` (returns displayable for filter/select).
- App\Models\User extended: `implements HasLocalePreference` + `preferredLocale(): string` (returns locale; D5: mail rendered in recipient's locale); `#[Fillable(['notification_preferences'])`, `casts(['notification_preferences' => 'array'])`, docblock `@property array<string, array{mail: bool}> $notification_preferences`; override `notifications(): MorphMany` (→ `$this->morphMany(Notification::class, 'notifiable')->latest()`).
- App\Notifications\BaseTenantNotification (abstract, `extends Notification implements ShouldQueue`, `use Queueable`) — `#[Tries(3)] #[Backoff([10,30,60])] #[Timeout(60)]` class-level. Ctor: `public function __construct(public readonly string $tenantId) { $this->afterCommit(); }`. Abstract methods: `notificationType(): NotificationTypeEnum`, `protected title(object $notifiable): string`, `protected body(object $notifiable): string`, `protected url(object $notifiable): ?string`, `protected meta(): array` (default `{}`). Concrete: `via(object $notifiable): array` (checks mailEnabledFor + adds mail channel), `mailEnabledFor(object $notifiable): bool` (pref ?? defaultMailEnabled), `databaseType(object $notifiable): string` (→ type.value), `toArray(object $notifiable): array` (→ {type, title, body, url, meta}), `toMail(object $notifiable): MailMessage` (subject, line, action, url), `failed(?Throwable $e): void` (logs error).
- App\Notifications\Channels\TenantDatabaseChannel extends DatabaseChannel — `protected function buildPayload($notifiable, Notification $notification): array` (guards `instanceof BaseTenantNotification`, adds tenant_id to payload).
- 6 notification subclasses (InvoiceOverdue, ContractExpired, ContractExpiring, QuoteSent, QuoteExpiring, QuoteExpired), each with ctor payload (tenantId, subjectId, daysLeft optional), url/meta/title/body implementations, `#[Tries(3)] #[Backoff([10,30,60])] #[Timeout(60)]`.
- App\Services\NotificationRecipientResolver (final readonly, ctor PermissionRegistrar) — `usersWithPermission(string $tenantId, PermissionEnum $permission): Collection<User>` (single query: Users where is_active + active membership in tenant + permission in team scope, team scope saved/restored via finally block).
- App\Services\NotificationService (final readonly, ctor DatabaseManager) — `paginate(User, string $tenantId, Request): LengthAwarePaginator<NotificationListItemData>` (QueryBuilder forRecipient+inTenant, AllowedFilter type/read, sort -created_at); `bell(User, $tenantId): NotificationBellData` (unread_count, recent 5); `unreadCount(User, $tenantId): int`; `markRead(Notification): void`; `markAllRead(User, $tenantId): int`; `updatePreferences(User, NotificationPreferencesUpdateData): void`.

**Satellites (BE):**
- DTOs: NotificationListItemData (id, type, title, body, url?, read_at?, created_at), NotificationBellData (unread_count, recent: NotificationListItemData[]), NotificationPreferenceItemData (type, label, mail: bool, configurable: bool), NotificationPreferencesData (items: PreferenceItemData[]), NotificationPreferenceUpdateItemData (type, mail), NotificationPreferencesUpdateData (preferences: UpdateItemData[]).
- Listeners (6, auto-discovered): NotifyInvoiceOverdue (InvoiceMarkedOverdue → InvoiceOverdue), NotifyContractExpired (ContractExpired → ContractExpired), NotifyContractExpiring (ContractExpiring → ContractExpiring), NotifyQuoteSent (QuoteSent → QuoteSent), NotifyQuoteExpiring (QuoteExpiring → QuoteExpiring), NotifyQuoteExpired (QuoteExpired → QuoteExpired). Each: sync, queries usersWithPermission(ViewInvoices|ViewContracts|ViewQuotes), sends notification via Notification::send.
- Event tweaks: InvoiceMarkedOverdue now `implements ShouldDispatchAfterCommit` (parity with siblings).
- Policies: NotificationPolicy (viewAny → ViewNotifications, update → viewAny + owner + tenant + current_tenant_id checks).
- Controllers: NotificationController (#[NavItem(label: 'app.notifications', route: 'notifications.index', icon: 'BellIcon', order: 45)]) · index (Inertia render with paginate/filters/typeOptions/unreadCount), bell (JSON NotificationBellData), markRead/markAllRead. NotificationSettingsController (#[NavItem(label: 'app.notification_settings', route: 'settings.notifications', icon: 'BellAlertIcon', group: 'settings', order: 25)]) · show (Inertia render preferences), update (Precognition). Api/NotificationBellController (invokable, GET /api/notifications/bell, Sanctum + tenant.context + tenant.required, JSON NotificationBellData, mirrors MeController pattern).
- Routes: web GET|POST /notifications, GET /notifications/bell, POST /notifications/read-all, POST /notifications/{notification}/read, GET|PUT /settings/notifications (Precognition on PUT); API GET /api/notifications/bell. All web: auth+tenant.required. API: auth:sanctum+tenant.context+tenant.required.
- Permissions: ViewNotifications (Vlastník/Vedúca/Sekretárka/Účtovníčka seeded), ConfigureNotifications (Vedúca/Sekretárka/Účtovníčka seeded; gates settings page per Q1 default).
- Migrations: 2026_09_12_000100_create_notifications_table.php, 2026_09_12_000200_add_notification_preferences_to_users_table.php.
- Factory: NotificationFactory with states read()/ofType(NotificationType).
- Tests: 5 files (NotificationCenterTest, NotificationBellTest, NotificationPreferencesTest, NotificationDispatchTest, TenantDatabaseChannelTest) + 40 test cases.
- i18n: notification_* keys (8 type labels + 6 title/body pairs + settings labels), total 1054+ keys ×3 languages.

**Flow:**
- **Event dispatch → listener → notification send:** Event fired (InvoiceMarkedOverdue, ContractExpired, etc.) + ShouldDispatchAfterCommit → listener subscribes (auto-discovered via @handle) → NotifyX listener runs sync → resolves users with permission (team-scoped) → `Notification::send($users, new NotificationClass(...))` → framework queues N jobs (one per user, one per channel: DB+mail if enabled) → queue worker processes jobs → `TenantDatabaseChannel::buildPayload()` adds tenant_id → row inserted → or mail sent via queue (per channel).
- **Bell polling (60s interval, web session):** FE `useNotificationBell` composable polls `GET /notifications/bell` (web route, session auth) every 60s, reads `unread_count + recent 5` from `NotificationBellData`, displays bell badge. Stops on 401/403/419 (auth loss). Tab-hidden detection pauses polling (battery optimization).
- **Mark read workflow:** User clicks notification in centre (Notifications/Index or bell popover) → `POST /notifications/{id}/read` → policy + owner check → `markRead()` updates read_at → redirect back + flash. Bulk: `POST /notifications/read-all` → updates all unread for user in active tenant.
- **Preferences workflow:** User visits `/settings/notifications` → page renders 8 items with toggles (mail: on/off, non-configurable types disabled) → saves via `PUT /settings/notifications` with Precognition preview → updates `notification_preferences` jsonb in User.

**Depends on:** tenancy (tenant_id FK, scopeInTenant), identity (User.is_active, PermissionEnum), invoices (InvoiceMarkedOverdue event), contracts (ContractSigned/Expired/Expiring events), quotes (QuoteSent/Expired/Expiring events), queue (notification ShouldQueue + afterCommit), mail (toMail channel + locale preference).

**Depended on by:** events (listeners must fire when event dispatched; zero-listener phase 8 transition), dashboard widgets (recent notifications list reserved), mobile phase 9 (GET /api/notifications/bell endpoint).

**If you change Core, check:**
- NotificationTypeEnum cases + defaultMailEnabled/userConfigurable + deliversInApp flags (seeded in RoleTemplatesSeeder, listened by 6 listeners).
- Listener PermissionEnum permissions (NotifyInvoiceOverdue→ViewInvoices, etc.) + team id restoration in finally.
- BaseTenantNotification subclass signatures match table schema (data JSON keys match title/body/url/meta).
- TenantDatabaseChannel guard instanceof BaseTenantNotification (only these carry tenant_id payload).
- User::notifications() relation (MorphMany latest, used by framework for unreadNotifications()).
- HasLocalePreference implementation (mail rendered in user.locale, side effect on invitation+password-reset mails).
- NotificationRecipientResolver query (active membership + is_active guards, team-scoped permission lookup).
- User.notification_preferences casting (array, allows null keys → defaults to defaultMailEnabled).

**Keywords (SK):** upozornenie, oznamovanie, zvonček, preferenčný, čítané, neprečítané, e-mailový kanál, databázový kanál.

### dashboard — Cross-company overview, permission-gated metrics, cross-tenant alerts (Phase 9, 2026-09-07)

**Core:**
- App\Http\Controllers\DashboardController — #[NavItem(label: 'app.dashboard', route: 'dashboard', icon: 'HomeIcon', order: 10)] invokable → GET / (auth + tenant.required, no #[Authorize]) → DashboardService::overviewFor(User) → Inertia::render('Dashboard', ['overview' => DashboardData]).
- App\Services\DashboardService (final readonly, ctor PermissionRegistrar, DashboardMetricsQuery, DashboardAlertsQuery) — `overviewFor(User $user): DashboardData` (entry point): loads TenantVisibility snapshots (permission state per tenant), queries metrics + alerts keyed by tenant_id, assembles CompanyOverviewData per visibility, returns DashboardData{companies[], alerts[], alert_counts[], generated_at}. `private visibilityFor(User): list<TenantVisibility>` (ADR-D3: setPermissionsTeamId loop with relation-cache reset/restore, PermissionRegistrar team-id switch never touches container current_tenant_id binding).
- App\Services\Dashboard\TenantVisibility (final readonly, no Data) — value object: tenant (with 'interface' loaded), membershipId (actor's membership pivot id in this tenant), 11 permission bools (invoices, schedule, scheduleAll, contracts, quotes, employees, clients, objects, billingSettings, createInvoices).
- App\Services\Dashboard\DashboardMetricsQuery (final readonly) — `collect(list<TenantVisibility>): array{invoices: [...], schedule: [...], ...}` (keyed by tenant_id). Queries: 7 constant (invoice per-currency, schedule today/week/unassigned, contracts, quotes, employees, clients, objects), all via `withoutGlobalScope(TenantScope::class)->whereIn('tenant_id', [...])`, Postgres FILTER syntax (status/date predicates), post-processes to ensure default currency row exists. No N+1 (composite queries per type, pre-computed date bounds from today()).
- App\Services\Dashboard\DashboardAlertsQuery (final readonly) — `collect(list<TenantVisibility>): array{rows: list<DashboardAlertData>, counts: array<string,int>}`. Queries: 4 list (overdue_invoice, contract_expiring, quote_expiring, unassigned_job, supplier_incomplete per-tenant filter) + 4 count queries (pre-cap totals), merged + sorted by urgency (rank then -days for overdue_invoice, +days for others), take(config dashboard.alerts.total_limit). Tenant lookup map from $vis for name/colour.
- App\Data\Dashboard\DashboardData (#[TypeScript]) — companies: CompanyOverviewData[] (by tenant name), alerts: DashboardAlertData[] (merged, capped, sorted), alert_counts: DashboardAlertCountData[] (per enum case, pre-cap), generated_at: ISO8601 string.
- App\Data\Dashboard\CompanyOverviewData (#[TypeScript]) — tenant_id, name, color (TenantColorEnum|null), is_current (tenant_id === current_tenant_id()), supplier_complete, invoices: InvoiceMetricsData|null, schedule: ScheduleMetricsData|null, contracts: ContractsMetricsData|null, people: PeopleMetricsData|null (nulled fail-closed per TenantVisibility permission bools).
- App\Data\Dashboard\InvoiceMetricsData (#[TypeScript]) — default_currency (TenantInterface.default_currency ?? EUR), by_currency: InvoiceCurrencyMetricsData[] (default first, per-currency grouped sums + counts, zeroes if no rows, other currencies only if non-zero).
- App\Data\Dashboard\InvoiceCurrencyMetricsData (#[TypeScript]) — currency, invoiced_month_count/sum (status IN (issued,overdue,paid) + issue_date BETWEEN startOfMonth AND endOfMonth, credit notes excluded), unpaid_count/sum (status=issued), overdue_count/sum (status=overdue). Money as string "1234.56".
- App\Data\Dashboard\ScheduleMetricsData (#[TypeScript]) — today (scheduled_date = today), this_week (scheduled_date BETWEEN monday AND sunday, ISO week), unassigned_next_7_days (status=unassigned, scheduled_date BETWEEN today AND today+6, null for own-only actor), own_only: bool (actor lacks ViewAllSchedule in this tenant).
- App\Data\Dashboard\ContractsMetricsData (#[TypeScript]) — active (status=active count), expiring_30d (status=active + term_type=fixed + end_date BETWEEN today AND today+30), quotes_awaiting (status=sent). Nulls per permission gate (ViewContracts for contracts.*; ViewQuotes for quotes_awaiting).
- App\Data\Dashboard\PeopleMetricsData (#[TypeScript]) — employees (is_active=true membership count), clients (non-soft-deleted count), objects (is_active=true + non-soft-deleted count). Nulls per permissions (employees→ViewEmployees, clients→ViewClients, objects→ViewObjects AND ViewAllObjects).
- App\Data\Dashboard\DashboardAlertData (#[TypeScript]) — type: DashboardAlertTypeEnum, tenant_id/name/color, title (invoice.number ?? customer_name; supplier → tenant.name; unassigned → object.name; contract → contract.title; quote → number ?? subject), subtitle (customer_name or contractableLabel or client.name, null for supplier), amount (decimal string, null for supplier/unassigned), currency (with amount), due_date (Y-m-d: invoice.due_date, contract.end_date, quote.valid_until, job.scheduled_date; null for supplier), days (signed int: overdue invoice → diffInDays (>= 1), expiring/unassigned → days until >= 0, supplier → null), url (absolute route: invoices.show/contracts.show/quotes.show/jobs.show/settings.invoicing/invoices.index).
- App\Data\Dashboard\DashboardAlertCountData (#[TypeScript]) — type: DashboardAlertTypeEnum, count: int (pre-cap total).
- App\Enums\DashboardAlertTypeEnum (#[TypeScript]) backed string — 5 cases: overdue_invoice, supplier_incomplete, unassigned_job, contract_expiring, quote_expiring. Methods: label() → __('app.dashboard_alert_'.$value), rank() → 1..5 (urgency order).
- App\Data\Tenants\TenantSwitchData (#[TypeScript]) — redirect_to: string|null (optional relative path for POST /tenants/{id}/switch redirect; regex validator rejects absolute/protocol-relative URLs).
- config/dashboard.php — alerts[per_type_limit:15, total_limit:50], unassigned_horizon_days:7.

**Satellites (BE):**
- Routes: GET / (auth + tenant.required), POST /tenants/{id}/switch (TenantPolicy::switchTo, payload TenantSwitchData, redirect to $data->redirect_to ?? '/' after session switch).
- Tests: tests/Feature/Dashboard/DashboardOverviewTest.php (13 methods: shape, multi-company ordering, cross-tenant isolation, per-tenant permission gating, fail-closed never-zero, registrar restored, invoice metrics, schedule metrics, cleaner own-only, contracts/quotes, people, supplier incomplete, alert ordering+caps), tests/Unit/Services/DashboardServiceTest.php (2 methods: empty membership user, registrar team_id restored), tests/Feature/Tenancy/TenantControllerTest updated (3 new switch tests: foreign tenant 403, inactive tenant 403, switch + redirect_to).

**Satellites (FE):**
- Pages/Dashboard.vue — prop overview: DashboardData, header with generated_at label + refresh button, grid 1/2/3 cols + alerts sticky aside (xl:22rem width), empty states (no companies, no access, no alerts).
- Components/Dashboard/{CompanyCard.vue (header button when not is_current, supplier badge), CompanyMetricGroup.vue (grid stats + group label + hint), MetricStat.vue (button, label, money/count, caption, secondary rows, tone), AlertsPanel.vue (grouped by type + count badge, list with hidden count), AlertRow.vue (button, dot + tenant name line, title/subtitle, amount/days/date right-aligned, spinner)}, alertTypeStyle.ts (exhaustive Record per type: dot + badge class), dashboardNavigation.ts (InjectionKey for TenantSwitchVisit).
- Composables/{useTenantSwitchVisit.ts (ref switchingTo, navigate method: current → visit directly or reload dashboard; other → POST switch + refresh + visit(url, replace:true)), useCompanyMetricGroups.ts (computed view model: labels, money format, route targets per stat, secondary multi-currency rows, icon + hint per group)}.
- utils/enums.ts — dashboardAlertTypeKey(type): string (returns `dashboard_alert_${type}`).
- Lang keys (15): dashboard_generated_at, dashboard_refresh, dashboard_current_company, dashboard_switch_to, dashboard_supplier_incomplete, dashboard_company_no_access, dashboard_no_companies_{title,description}, dashboard_group_{invoices,schedule,contracts,people}, dashboard_stat_{invoiced_month,unpaid,overdue,today,this_week,unassigned_7d,contracts_active,contracts_expiring,quotes_awaiting,employees,clients,objects}, dashboard_schedule_own_only, dashboard_alerts_{title,empty_title,empty_description,more}, dashboard_days_{overdue,left} (plurals: 1 vs many). Reuse invoice_stat_count plural, dashboard_alert_* keys from BE.

**Flow:**
- **Render dashboard:** GET / (auth + tenant.required) → DashboardController → DashboardService::overviewFor(User) → visibilityFor (company list + per-tenant permission snapshot via Spatie teams=true + PermissionRegistrar::setPermissionsTeamId loop with relation-cache reset/restore finally block) → DashboardMetricsQuery::collect (7 queries, cross-tenant via withoutGlobalScope + whereIn tenant_id filtering) → DashboardAlertsQuery::collect (8 queries per type, merge + sort + cap) → assemble CompanyOverviewData (is_current check, metric groups nulled per visibility) → DashboardData → Inertia render Dashboard {overview}. FE: grid company cards (1/2/3 cols responsive), each shows metric groups (invoices/schedule/contracts/people) with stats as buttons (navigate via useTenantSwitchVisit), aside alerts panel sorted by type (dot + tenant/title/amount/days).
- **Switch + navigate:** Click CompanyCard header (not is_current) or MetricStat button → useTenantSwitchVisit::navigate(tenantId, url) → isCurrent ? router.visit(url) : router.post(`/tenants/${tenantId}/switch`, {}, {onStart, onSuccess→router.visit(url, replace:true)}). POST /tenants/{id}/switch → TenantPolicy::switchTo gates (membership + active) → session bind active_tenant_id → Inertia redirect dashboard → shared props refresh (tenant, can, navigation). If redirect_to provided: switch saves url state, follow-up visit uses it. Spinner on card header / metric stat / alert row when switchingTo === tenantId.

**Depends on:** tenancy (multi-tenant row-level, TenantVisibility permission snapshot per tenant), invoices (metrics query + alert data), schedule (metrics query + alert data), contracts (metrics query + alert data), quotes (metrics query + alert data), employees (people metric count), clients (people metric count), objects (people metric count). AllowedFilter (potential future: metrics filters, not implemented phase 9).

**Depended on by:** GET / route (home page), navigation NavItem order 10 (AppLayout sidebar "Prehľad" order first).

**If you change Core, check:**
- DashboardAlertTypeEnum cases + rank() method: DashboardAlertsQuery sort predicate, AlertsPanel section loop order, alertTypeStyle.ts exhaustiveness, lang keys dashboard_alert_*, tests (alert ordering by rank).
- TenantVisibility permission bool names: DashboardMetricsQuery filtering (vis->invoices, vis->scheduleAll, etc.), if new domain added (e.g., vis->myNewDomain), update DTO + service loop.
- DashboardMetricsQuery date bounds (today(), startOfMonth, startOfWeek, horizon): ScheduleMetricsData window definitions, job unassigned 7d, contracts expiring 30d, quotes expiring 7d — keep in sync with config/dashboard.php unassigned_horizon_days + ConfigureNotifications max(config contracts.expiring_notice_days) + max(config quotes.expiring_notice_days).
- TenantSwitchData redirect_to regex: allowed relative paths (no absolute //, no proto ://, no fragment #). Consumer POST /tenants/{id}/switch.
- Dashboard lang keys parity (sk/en/uk, count ~1094 ×3): verify all 15 dashboard_* keys present in all three files + plurals match en/uk patterns.
- FE routing URLs (deep links from MetricStat + AlertRow): /invoices?filter[status]=overdue, /invoices?filter[status]=issued, /jobs?filter[status]=unassigned, /jobs/calendar, /contracts?filter[status]=active&sort=end_date, /quotes?filter[status]=sent, /employees, /clients, /objects, /settings/invoicing — live routes verify via php artisan route:list.

**Keywords (SK):** prehľad, dashboard, metriky, upozornenia (alerts), firma (company), prepínač (switch), nepridelená práca (unassigned job), splatnosti (due dates), licenci (permissions), čítané (read status).

### Phase 7 additions (12 new gotchas — employees + schedule + cleaner scoping)

46. **Users module coexists with Employees (both write tenant_memberships).** Phase 7 does NOT remove Users CRUD (admin portal platform members vs. operational employees). Users nav in settings group (order 15, D1); Employees nav in default group (order 20, D1). Both write `tenant_memberships` table. Path distinction: Users::create via UserService (no employment contract) → InvitationCreated mail (existing user only); Employees::create via EmployeeService (new or existing) → InvitationCreated mail (new user only), optional employment contract (draft). Authorization: both gated via TenantMembershipPolicy instance checks; no conflict.

47. **Nullable `users.password` + invitation flow for new-password users (D7).** Employees::create with new user → auto-verified User (no password initially) → InvitationCreated mail (new user, skips password send) → InvitationAccept sets password (passwordless login impossible until accepted). Login guard `Auth::attempt(['email' => ..., 'password' => ..., 'is_active' => true])` fails indistinguishably for null hash (no password exists). Users module: create-or-link preserves backward compatibility (existing users must have password or creation fails; DTO Rule::requiredIf new email). Profile password change: guard `Hash::check(current_password)` still works (existing hash required); nullable password allows phase 7 flow only for new employees (not existing Users.create).

48. **TaskFrequencyEnum recurrence semantics (8 values, phase 7 input only).** values: one_time (null → no recurrence), weekly_1x/2x/3x/4x/5x/6x (N per week), seasonal (1 per year). `monthsInterval()`: weekly_* → 0 (daily cron handles), seasonal → 12 (months between occurrences). `nextRunDate(from, limit)`: applies date math modulo recurrence (e.g., weekly_2x → every 14 days, clamped to limit per rolling 30d horizon). Input: QuoteItem.frequency enum (50 chars nullable); output: WorkBreakdownTask.frequency enum; consumption: GenerateScheduledJobsJob task expansion (loops frequency helpers to create ScheduledJob instances on rolling schedule). Unused in phase 5–6 invoicing/contracts (no task decomposition).

49. **Partial unique recurrence index prevents duplicate job generation.** migration: `unique(['work_breakdown_task_id', 'scheduled_date'], where: 'deleted_at IS NULL')` on ScheduledJob. GenerateScheduledJobsJob ShouldBeUnique (via tenantId+breakdown_id binding, max_lock_attempts=3) ensures single job per cron trigger. Result: re-running cron is idempotent; contract re-sign does not re-generate breakdown (contract_id FK unique on WorkBreakdown).

50. **`?array $x = []` in DTO signatures needed to bypass Spatie Data `required` (gotcha).** DTO field `?array $permissions = []` tells Spatie "required: false, default: []" (none of the above alone works); `#[Nullable]` alone still requires key presence, [] default empty array still marks field required (must be omitted in request). Workaround: use union type `#[Nullable] array` OR double-default `?array $x = []` in signature. Applies to: EmployeeStoreData/EmployeeUpdateData.roles, ContractUpsertData.employment (nullable object, resolved to null if absent, no default []).

51. **Larastan `nullsafe.neverNull` house style (gotcha from phase 4, reinforced).** PHPStan reports `$model?->relation->method()` as redundant nullsafe (if relation exists, method exists; null-safe is noop). House style: drop nullsafe if you know property is non-null. Example: `$invoice->client?->name` (client BelongsTo nullable FK) becomes `$invoice->client?->name ?? 'Unknown'` (coalesce name if present, default else). Larastan knows nullsafe narrowing; this is style consistency, not correctness.

52. **Objects reachability via ANY assigned job (D3 override, no date/status filter).** CleaningObject::scopeVisibleTo query includes unassigned, cancelled, completed jobs (WHERE deleted_at IS NULL only). Rationale: cleaner may view object details of past work. Consequence: job cancellation does not hide object. If object has ANY non-trashed job assigned to cleaner (ever), object is reachable. On port: strict access control requires re-review (current is permissive D3 override; future versions may narrow by status).

53. **Scoping must NEVER be applied in cron/queue/listener contexts.** GenerateScheduledJobsJob binds tenant context (setPermissionsTeamId) but never receives actor param (services called without User argument). JobService methods default `?User $actor = null`; job passes null. Consequence: job creates visible-to-all jobs (not restricted by Interná upratovačka scope). Design: system actions (batch generation, event handlers) are unrestricted; user actions (list, create) pass actor for row filtering.

54. **Quote item frequency is phase 7 work-breakdown input only.** QuoteItem.frequency (50 chars nullable) added phase 5, unused until phase 7 WorkBreakdownService::generateFromContract reads it (defaults 'one_time' if missing). Phase 5–6 consumers ignore frequency; phase 7+ consumption via WorkBreakdownTask.frequency assignment.

55. **Contracts gain listener + event dispatch (phase 7 work-breakdown generation).** ContractSigned event listeners: GenerateWorkBreakdownFromSignedContract (phase 7, sync) → WorkBreakdownService::generateFromContract → dispatch GenerateScheduledJobsJob (afterCommit). Event: ContractSigned(tenantId, contractId), ContractExpired/Expiring (zero listeners phase 6, phase 7+ subscribes for notification dispatch). Consequence: signing a ServiceAgreement contract auto-generates Rozpis prác + Zákazka rows (deferred to daily cron MaterializationJob).

56. **QuoteController::convertToContract gates via policy + signature confirmation (phase 7).** POST /quotes/{id}/convert-to-contract requires Policy::convertToContract (CreateContracts permission) + Accepted status + itemized + client + object + category fixed (no standalone, no document kind). Returns Draft service_agreement contract; not auto-signed; redirects to `contracts.edit` (review first). FE: Show page displays contractTemplates prop (document_template options) + convert button (gated allows('create contracts')).

57. **RoleAssignmentGuard 422 escalation prevention (EmployeeService rule).** POST /employees/{id}/role (or store/update with role change) asserts actor's permissions ⊇ target role's permissions (via guard->assertAssignable, throws ValidationException role field). UI: role selector shows only roles ⊆ actor's permissions (e.g., Vedúca can only assign roles with ViewSchedule subset of her perms; cannot assign Vlastník). Guard prevents API escalation.

### Phase 8 additions (4 new gotchas — notifications module)

58. **Listener auto-discovery: explicit Event::listen double-fires listeners.** Laravel 9+ auto-discovers `@handle` methods on listeners (bootstrap/app.php has `withEvents(discover: true)` by default). AppServiceProvider must NOT register discovered listeners explicitly (Event::listen for same event=duplicate delivery). Phase 8: REMOVED duplicate `Event::listen(NotificationSent::class, StampInvoiceSentAt::class)` and `Event::listen(ContractSigned::class, GenerateWorkBreakdownFromSignedContract::class)` (pre-existing bug). Verify with `php artisan event:list --event=ContractSigned` (single `@handle` entry per listener, not two). New listeners (Notify*) are auto-discovered only — never explicit registration.

59. **Notification rows have manual tenant scoping (NOT BelongsToTenant trait).** Unlike domain models, Notification does NOT extend model+BelongsToTenant because rows are written from queue workers with no bound tenant context. `tenant_id` comes from notification payload (set by listener when constructing notification). Scoping is explicit: service calls `Notification::inTenant($tenantId)` + policy checks current_tenant_id. RMB model binding does NOT apply tenant scope (TenantScope global scope absent) → cross-tenant row resolves → policy 403 gates. Same pattern as Activity (login events pre-tenant-bind).

60. **`notification_preferences` is jsonb null-safe with fallback defaults.** User column is `jsonb NOT NULL DEFAULT '{}'` (Core rule). Pref shape: `{ "invoice.overdue": { "mail": true }, ... }`. Absent key → `NotificationTypeEnum::defaultMailEnabled()` fallback. Update flow: Spatie Data builds nested DTO `NotificationPreferencesUpdateData(preferences: [...])`, validated list of `{type, mail}`, service merges into prefs object keeping untouched keys. Side effect: D5 HasLocalePreference on User renders mail toArray()/toMail() in recipient's locale (user.locale), affecting InvitationCreated + password-reset mails for existing users (not just new-employee flow).

61. **API /notifications/bell is stateless session-independent from web /notifications/bell.** Web route (`GET /notifications/bell`) uses session auth (browser cookies); responds to FE composable polling. API route (`GET /api/notifications/bell`) uses Sanctum Bearer + `X-Tenant-Id` header (mobile phase 9). `/api/*` NOT stateful (no `statefulApi()` bootstrap) → browser session cookies do NOT authenticate `/api` requests. Both routes delegate to same service method; context resolved differently: web via TenantContextMiddleware (session binding), API via X-Tenant-Id header → current_tenant_id(). Avoid adding statefulApi() (cross-cutting CSRF impact); keep dual routes.

### Phase 9 additions (6 new gotchas — dashboard module)

66. **Cross-tenant query pattern: `withoutGlobalScope(TenantScope) + whereIn(tenant_id, $ids)` is the only path to read multiple tenants.** Dashboard metrics queries (DashboardMetricsQuery) must view data from all tenants where user has active membership. Never use `withoutGlobalScopes()` (drops SoftDeletes scope too). Always: (1) bind tenant_id list from TenantVisibility[] keyed by permission type (invoices, schedule, etc.); (2) query via `Model::query()->withoutGlobalScope(TenantScope::class)->whereIn('tenant_id', $ids)->groupBy(...)`. Precedent: MarkOverdueInvoices command (pre-phase-9, unbound cron, reads all invoices). Result: dashboard sees only companies where user is a member in an active tenant; row-level isolation maintained at query level (never "see all" without member binding).

67. **DashboardMetricsQuery + DashboardAlertsQuery never receive User param; all filtering by company membership is pre-computed in DashboardService::visibilityFor.** The queries receive TenantVisibility[] (permission snapshots) indexed by tenant_id, never the user. Consequence: queries can be parallelized (each query is stateless, receives filtered id list) and unit-tested without mocking permission registrar. DashboardMetricsQuery{invoices, schedule, ...} methods receive `list<string> $ids` (tenant_ids) extracted from visibilities matching permission type (e.g., scheduleAll visbility goes to schedule metrics). RMB: queries are dumb aggregators; service owns authorization.

68. **DashboardService::visibilityFor(User) final result MUST restore original registrar team_id in a finally block.** The method loops tenants calling `setPermissionsTeamId(tenantId)` + `$user->unsetRelation('roles')->unsetRelation('permissions')` per iteration. **CRITICAL:** finally block restores: `setPermissionsTeamId($previous)` + `$user->unsetRelation('roles')->unsetRelation('permissions')` (Spatie caches role/permission relations on User instance; loading them in tenant A then switching to B without cache-clear means tenant B checks read cached tenant-A permissions). Side effect: HandleInertiaRequests shared props evaluators (can, navigation closures) run AFTER service returns and must read active tenant permissions (app('current_tenant_id') is never touched; binding comes from session via TenantContextMiddleware, always correct).

69. **DashboardAlertCountData pre-cap totals allow FE to show "N more hidden" footer per section.** Counts query returns `count(*)` BEFORE applying per_type_limit (e.g., 47 overdue invoices exist, but dashboard shows 15 + "+32 more"). `alert_counts` array carries pre-cap total; `alerts` array is capped at per_type_limit per type. Merge logic in FE (AlertsPanel.vue computed): `hidden = count - rows.length` for each section. Consequence: user knows there are more alerts without a "load more" button (static cap is acceptable per brief).

70. **Metric group nulls mean "fail-closed, not available"; never render as zero or empty state within a card.** If `invoices === null` (user lacks ViewInvoices in this tenant), CompanyMetricGroup component omitted entirely (useCompanyMetricGroups filters out null groups from array). FE never shows "0 invoices" for missing permission (ambiguous: zero actual invoices vs. permission missing). Consequence: a user with restricted permissions sees fewer group tiles on the same company card (e.g., Vedúca in a 3-company portfolio sees full 4 groups on company A, only schedule on company B, invoices+schedule on company C).

71. **`is_current` on CompanyOverviewData mirrors `tenant_id === current_tenant_id()` at render time, NOT switch time.** After POST /tenants/{id}/switch → redirect dashboard, Inertia re-renders Overview page with fresh TenantContextMiddleware binding. New is_current flags reflect new active_tenant_id. FE switch UX: CompanyCard header button → spinner on card being-switched (switchingTo state) → POST switch → 302 redirect /dashboard → GET /dashboard with new props → card's is_current flips true, card header button becomes static "aktuálna" badge. Old is_current becomes false. Single render per switch (no FE mutation of is_current; BE truth source).

72. **Dashboard is NOT cached; every GET / hits DashboardMetricsQuery (7 queries, ~12 permission lookups N×2).** ADR-D1 rationale: invoice paid/overdue status can flip mid-day; cache would hide stale state right after user actions (mark paid, assign job). 60s per-user cache would be acceptable; rejected in favor of freshness (numbers always correct, no TTL surprise). Cost: ~20 queries per dashboard render (8 metrics + 8 alert lists + N×2 permissions = 24q avg). Optimization gate: measure real p95 on 5-tenant user, decide if caching trade-off acceptable (future phase 10+).

### Document templates + documents additions (2026-10-31, 12 new gotchas)

73. **DocumentTemplate is not BelongsToTenant.** TenantOrGlobalScope returns own + global rows and is a no-op with no bound tenant, so console/queue code sees every firm. Never add BelongsToTenant (creating hook fills tenant_id, TenantScope hides global rows).
74. **`users.is_platform_admin` is a deliberate second authorization axis.** Gates global templates and `POST /tenants` only; not fillable, not shared. A firm Admin who created a firm still gets 403 on global rows. Granting: `app:grant-platform-admin {email}`, `app:create-owner` (only when no platform admin exists), UserSeeder for admin@example.com.
75. **Missing value = literal token.** Never store `<mark>`; draft PDFs mark leftovers at render only (`highlightMissing`). FE derives the missing list from the current body (`usedTokenKeys`), not from the last response.
76. **Document content renders in `config('documents.locale')` (default sk, env DOCUMENTS_LOCALE),** via DocumentLocale::run, restored in `finally`. Any new document-producing code must run inside it or labels follow the UI/queue locale. Invoice PDFs deliberately do not.
77. **Signature tables are styled by `table:has(td[style*='border-top'])`** in document-body.css, because ProseMirror rewrites the table `style` in the editor DOM. Changing block markup in `documents/blocks/signatures.blade.php` breaks both editor and PDF styling.
78. **Block views must pass the sanitizer unchanged** (contract bodies store expanded blocks). Only p, headings, lists, tables and inline style; FE tableStyle extension keeps raw `style` so the first keystroke does not strip it.
79. **Employee files skip `/uploads` staging.** Staged TemporaryUpload files sit on the public MEDIA_DISK up to 24h; ID scans go multipart straight to the private documents disk. Delete is a hard delete (file included). Documents are excluded from the Media viewer (`model_type = Document`), which also hides quote files there.
80. **Rodné číslo handling:** encrypted cast + `#[Hidden]`, never in logOnly or list DTOs; `ViewEmployeePersonalData` gates DTO `personal`, writes, and employee-subject contract text/PDF; Employees/Edit, Contracts/Show, Contracts/Edit send `Inertia::encryptHistory()` and logout `clearHistory()` (history.state would otherwise keep the props).
81. **StoreSignedContractPdf must bind `current_tenant_id` + Spatie team** (Media::creating throws on an unbound worker) and restores both in `finally`; signed PDF is stored once and `ContractPdfService::render` prefers it, so firm edits after signing never change it.
82. **Forward-only data migrations.** 000100/000200 copy contract_templates and contracts.body then drop them; 000500 freezes blocks and has no `down`. Never `migrate:fresh` the dev DB; pg_dump first.
83. **A 422 on `status` (sign, send) is invisible without StatusErrorAlert** — AppLayout toasts `flash` only; Contracts/Show, Contracts/Index and Quotes/Show render `page.props.errors.status` through the component.
84. **S3 is unverified.** `DOCUMENTS_DISK=s3` + `league/flysystem-aws-s3-v3` are wired (`throw => true`), tests run on local/Storage::fake; no real-bucket check done, bucket public-access block is owner infra.

### Shell UX fix (persistent layout + Link nav, 2026-09-06)

62. **Pages must NOT wrap `<AppLayout>`.** The persistent default layout is enforced at `createInertiaApp({ layout: (name) => ... })` in app.ts; every page (except Auth/* and Invitations/*) auto-wraps at the Inertia level. Pages importing `<AppLayout>` and rendering it as a component (old pattern) create double-wrapping → breaks layout persistence → sidebar remounts on navigate → bell refetch loop, drawer-stays-open bug. Grep-check after port: `find resources/js/Pages -name '*.vue' -exec grep -l '<AppLayout' {} \;` must return only Auth/*, Invitations/Accept.vue.

63. **Internal links must use Inertia `<Link>`, never plain `<a>`.** `<Link :href>` from `@inertiajs/vue3` intercepts clicks and dispatches Inertia visits (no full-page reload). Plain `<a href>` bypasses Inertia → sidebar unmounts/remounts → persistent layout invalidated → bell listeners reset, drawer closes, session round-trip. EXCEPT: `/language/{locale}` (full reload by design for locale switch via LocaleMiddleware), `*.pdf` with `target="_blank"`, media `download_url`, `mailto:`, `tel:`, form 409 errors (useInvoiceSettingsDrawer.ts), absolute http. Converted 100+ links to Link in phase 3+ pages and component CTAs (e.g., Quotes/Index create button, Invoices/Index row edit, Cards' action links). ESLint rule (future): warn unused `Link` imports + ban plain `<a>` in page routes.

64. **Mobile drawer closes on navigate.** `AppLayout.vue` listens `const removeNavigate = router.on('navigate', () => { drawerOpen.value = false; })` (registered in `onMounted`, cleaned up in `onUnmounted`). Persistent layout survives navigate → drawer state would stick open → UX regression. Drawer `v-model` + navigation listener solves it (single pattern source: AppLayout footer).

65. **Sidebar footer holds the user card, the full-width NotificationBell row (opens upward), the logout button and the language dropdown; `nav` is the only scroll region (`aside` is `h-screen`).** Logout: Moved from `:233-239` (old user card) to `:278+` (new footer section above language switcher). Footer wrapper carries border + padding; language dropdown sits below logout. On mobile, same footer-in-drawer is the logout surface (no navbar logout duplicate). `router.post('/logout')` unchanged (Inertia handles CSRF via meta, no form needed). Setting page route redirects here post-logout (session cleared).

### Phase 11 additions (mobile admin, tasks, expenses, AI — 2026-11-01)

1. **Pending-action TTL is 10 minutes, MCP token lifetime is 180 days.** Two separate mechanisms: `ai.pending_action_ttl_minutes` (row expiry) and `McpTokenService::LIFETIME_DAYS` = 180 (token). Do not conflate in docs or tests.
2. **`PendingActionPreparedData` is a plain class**, not Spatie Data, so the one-time token never lands in `generated.d.ts`.
3. **`config('ai.providers.anthropic.key')` comes from laravel/ai's merged defaults.** `config/ai.php` in this repo defines no `providers` key; `AiClientOptions::isConfigured()` reads the merged value. Verified 2026-11-01 in the container: `ANTHROPIC_API_KEY` set gives the key and `isConfigured()` true; unset gives null and false.
4. **FirebasePushSender skips quietly** (log `push.skipped`) when `config('firebase.projects.app.credentials')` is empty. Push delivery is untested in any environment without `FIREBASE_CREDENTIALS`.
5. **FcmChannel swallows send exceptions on purpose** (a retry would re-send to tokens that already got the push). Failures are visible only in logs.
6. **Global task visibility is creator-only.** A global task is not visible to other members even with `view all tasks`; `scopeVisibleTo` and `isVisibleTo` both enforce this.
7. **`Task::unscopedForSystem()` is the only scope bypass.** New code that needs cross-tenant task access goes through `TaskService::findForSystem` or the command path, never a raw `withoutGlobalScope`.
8. **Assistant cannot confirm.** `confirm_action` and `delete_global_task` are excluded from `AssistantToolbox`; a prepared irreversible action returns `confirm_in_app_only` when the channel list has no chat.
9. **Assistant partial success returns HTTP 200**, not an error: provider failure after tool effects returns the effects with `app.assistant_partial`. Failure before any effect returns 422 `errors.ai`.
10. **`mobile` token in a company without `access mobile app` → 403 `mobile_forbidden`** from TenantContextMiddleware; at login the same check returns 422 on `email`.
11. **Default tenant for mobile tokens** is the first membership whose mobile access is granted; a user with no mobile-capable company falls back to the first membership and then gets 403 at the middleware.
12. **ToolContext restores the previous request, user, tenant and team id in `finally`.** Nested tool calls rely on this (ActionConfirmationService::execute runs inside ToolContext::run).
13. **Expense `analyze` persists nothing**; a draft is saved only by `store`.
14. **New MCP tools need the right annotation.** A read tool without `IsReadOnly` is refused in a restricted assistant turn; a write tool wrongly marked `IsReadOnly` bypasses the prompt-injection write block.
15. **Claude web connectors and the Gmail flow need OAuth (Passport) and a deployed host.** The MCP `mcp` Bearer token works for local clients only.

### Task attachments additions (2026-11-01, on `feat/task-attachments`)

1. **Personal-task files are tenant-less by design.** The `Media::creating` hook copies the owner's `tenant_id` (NULL for a global task), never the bound company. Migration 2026_11_02_000200 drops NOT NULL and adds CHECK `media_tenant_required_unless_task` (NULL only when `model_type` = `App\Models\Task`). Its `down()` drops the CHECK and restores NOT NULL, and fails loudly if NULL rows exist.
2. **Upload size has three layers.** PHP `docker/php/uploads.ini` (`upload_max_filesize=12M`, `post_max_size=14M`; copied as `zz-uploads.ini` in Dockerfile line 85 and Dockerfile.dev line 39). Laravel `max:10240` kB gives 422 on `file` (messages map `file.max` and `file.uploaded`). A body over 14 MB is refused by ValidatePostSize with 413 before validation. nginx `client_max_body_size 50M` is not the limit. The runtime `ini_get` check is an acceptance criterion and was not run by docs-agent.
3. **Office detection is signature plus client extension.** `finfo` reports `application/zip` for .docx/.xlsx and OLE types for .doc/.xls. `TaskAttachmentFile::resolve` takes the canonical type from the client extension. A renamed zip named `.docx` passes; accepted because storage is private, responses are `nosniff` with `attachment` disposition, and uploaders are internal. Macro-enabled types and any other detected type are refused.
4. **HEIC is accepted, never a 422.** Stored as is, `previewable: false`, always `attachment` disposition. The web input drops HEIC/HEIF from `accept` on touch devices (FE decision; device check open).
5. **Web POST has no success flash.** `TaskAttachmentController::store` returns `back()`; the page reloads only `attachments` (`only: ['attachments']`; `errors` still arrive). The DELETE flash `app.task_attachment_deleted` is the only flash.
6. **Placeholders differ between Laravel and vue-i18n.** `app.task_attachment_too_large` and `app.task_attachment_limit_reached` use `:max`. The FE uses its own keys (`file_too_large`, `task_attachment_limit_hint` with `{max}`), never these BE keys through `t()`.
7. **Soft-deleted task keeps its files.** Files stay on the disk (plan states the `InteractsWithMedia` deleting hook returns early on a soft delete; vendor source not re-read by docs-agent). List and download return 404 because the route binding excludes trashed tasks. No purge job (out of scope).
8. **Media viewer excludes Task media.** `MediaService::index` and `MediaPolicy::view` both exclude `model_type` Task; otherwise the viewer would list attachments without `Task::isVisibleTo`.
9. **Stored name is not the disk name.** `media.name` = `cleanName(original)` and never changes; the disk file is `{uuid}.{ext}`. No Unicode normalization server-side (`intl` absent in the container), so decomposed names are stored as sent; the mobile client compares NFC-normalized names.
10. **Audit name policy.** `attachment_added` and `attachment_deleted` log `name` only on firm tasks. Global-task rows use `keepNullTenant`, so they stay tenant-less and visible to the causer only.

## Verification status

**Last full scan:** 2026-10-31 (partial full-mode pass: Laravel Boost MCP unavailable, `php` not on PATH on host; verified by Read + Grep of models/services/controllers/migrations/routes/web.php. Fully re-verified against code: contracts, employees, quotes (contract conversion + storage), clients, objects (visibility scope), media (collections, moveToModel, media-urls), settings-invoicing, invoices/recurring-invoices Oct 2026 additions (payments, bulk, EFA, Pohoda, qpdf). NOT re-verified in that pass: tenancy, identity, auth, audit-logs, api-me, schedule, notifications, dashboard blocks and the older Gotchas sections - treat as 2026-09 state.)

**Last delta:** 2026-11-01 (task attachments, on `feat/task-attachments`: media, tasks, mcp, mobile-api blocks; Cross-cutting MediaLibrary and rule-6 exceptions; Layer contracts; Gotchas; per-page props). Method: Boost MCP unavailable (`php` not on PATH); routes from routes/api.php and routes/web.php, schema from the new migration, callsites by grep and Read, DTO shapes from the Data classes. Not run by docs-agent: test suite, Docker build, device checks.
**Earlier delta:** 2026-11-01 (AI guardrails on `feat/ai-guardrails`; Phase 11 mobile admin / AI / MCP backend, branch `feat/mobile-admin-api` vs base `cdeed4e`: tasks, expenses, pending actions, push, assistant, MCP tool layer, `/api` + `/mcp` group order, rule-6 exceptions, reconciled tenancy / identity / auth / audit-logs / api-me blocks). Method: Boost MCP unavailable in this run (`php` not on PATH), so routes from `routes/*.php`, schema from migrations, callsites from grep + Read. `vendor/` absent in the worktree. Previous: 2026-09-22 (Phase 10 schedule status flow: started_at column + JobService.start() method + three canBe* helpers + ScheduledJobPolicy/Controller actions for start/complete/unapprove + JobDetailData fields; InvoiceSettingsData.default_header_text/default_footer_text fields added for PDF customization; InvoiceSettingsTextsCard.vue component for settings UI; 1130 tests, 4173 assertions, all verified). Previous: 2026-09-20 Classic invoice PDF fidelity fix.
**Previous delta:** 2026-10-31 (document templates + documents feature, delta over uncommitted work on `main`; verified by Read + Grep of services, policies, models, migrations, routes/web.php, config/documents.php, EmployeeController, listeners; Boost MCP and php unavailable on host, so route list read from routes/web.php, no `route:list` run. Reconciled this pass: tenancy (Tenant.representative_name, TenantPolicy::create, platform admin), identity (users.is_platform_admin, PermissionEnum 56), media (Document collections, viewer exclusion), clients, objects, quotes, employees, contracts (rewritten), PDF cross-cutting, auth layer, shared/page props, gotchas 37/41/44/45/56 + new 73-84; domains document-templates and documents added; contract-templates domain REMOVED. Figures supplied by the caller, not re-run by docs-agent: 1363 PHPUnit tests (5536 assertions), 130 vitest tests, PHPStan, Pint, vue-tsc clean, ESLint 0 errors. Not verified: S3 against a real bucket; `DocumentTemplateSeeder` text not read.)

**Certainty audit:**
- All relationships verified by: live route:list (php artisan route:list), migration files + docker exec postgres psql, grep of every cited callsite + direct reads (git show d7cb13c — BE schedule/employees implementation commit).
- Phase 7 employees verified: EmployeeService methods (paginate/create/update/deactivate), TenantMembership profile + LogsActivity, RoleAssignmentGuard on UserService, TenantMembershipPolicy instance checks, EmployeeController routes, InvitationCreated mail (new user), RoleAssignmentGuard 422 on escalation, EmployeeDetailData.can flags, UsersModule coexistence (nav order 15 settings vs 20 default).
- Phase 7 schedule verified: WorkBreakdown/WorkBreakdownTask/ScheduledJob models exist, enums (TaskFrequencyEnum 8 values + recurrence helpers, JobStatusEnum 6 states + state matrix, JobTypeEnum 3 types), ScheduledJob::scopeVisibleTo + CleaningObject::scopeVisibleTo (any job reachability D3), JobService methods, ScheduledJobPolicy (rbac-full + scopeVisibleTo), ScheduledJobController routes + #[NavItem], GenerateWorkBreakdownFromSignedContract listener (sync), GenerateScheduledJobsJob (queued ShouldBeUnique, rolling 30d), GenerateScheduledJobsCommand (daily cron), config/scheduling.php (horizon_days), ScheduleDemoSeeder (contract → breakdown → jobs + cleaner assignment), FullCalendar v6.1.21 calendar component, useJobCalendar composable, TimeInput component, WorkBreakdownDetailData.tasks array on Objects/Show.
- Phase 11 TODO verify (open):
  1. Real Anthropic call with a key: never run, no key exists yet.
  2. `PushRoute` patterns match the mobile router paths — cross-repo, not checked here.
  3. FCM token registration (`@capacitor-firebase/messaging`) and device deep links — mobile repo, not checked here.
  4. Proxy in front of nginx keeps ≥ 90 s read timeout — `docker/nginx/default.conf` sets 300 s; outer proxy not in repo.
  5. Test figures 1726 / 7370 at 884d0b5 are implementer-supplied, not re-run in this pass.
- Task attachments TODO verify (open, 2026-11-01):
  1. Real iPhone HEIC, a 9 MB photo, a real .docx and a real .xlsx: none in the repo; automated tests use crafted bytes only (tests/Fixtures/task-attachments/). Manual smoke only.
  2. Production `Dockerfile` build: `.dockerignore` excludes `docker/` (line 18), while `Dockerfile` copies `docker/php/php-fpm.conf` (line 84), `docker/nginx/default.conf` (line 110) and `docker/supervisor/supervisord.conf` (line 113) from the context. Read-verified only; the build is not run.
  3. Upload limits in a running container (`ini_get` gives 12M and 14M): acceptance criterion, not run here.
  4. `accept` behaviour on iOS Safari for gallery photos (JPEG expected, HEIC possible): device check open.
- Open TODO verify (earlier phases):
  1. ~~AllowedFilter dead references~~ — PHPStan clear (baseline 190 2026-09-06, −4 stale).
  2. ~~Layouts/DataTable.vue + Composables/useFilters.ts~~ — 0 consumers; kept.
  3. Cross-field #[Validation] rules (quote clientless, contract term/end_date, employee role escalation) under Precognition-Validate-Only partial payloads — behavior verified in phase 7 tests (EmployeeRoleModalTest, EmployeeUpsertTest); known trade-off for partial-payload support (full-payload rules execute on submit, not blur).
