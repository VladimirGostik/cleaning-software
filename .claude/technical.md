<!-- inogile:context-version=3 -->
# Technical relationship map — cleaning-software @ rebuild (vue-skeleton fork)

## Project type

laravel-be + inertia-fe (phase 2 tenancy complete: multi-tenant row-level scoping, tenant switcher, invitations)

## Stack snapshot

**Backend:** Laravel 13.7 (PHP 8.5) + Inertia 3.0.6 (Vue 3 + TS + Tailwind 4 + DaisyUI 5) · Spatie Data 4.22 / Permission 7.4.1 (teams OFF, will enable teams=true, team_foreign_key=tenant_id on port) / Activitylog 5.0 (uuid morphs) / MediaLibrary 11.22 / QueryBuilder 7.2 / TypeScript-Transformer 3 · Sanctum 4.3.2 (Bearer API + uuidMorphs fix applied 2026-09-06) · Scribe 5.9 · Postgres 16 (compose) · PHPUnit 12 on Postgres `cleanmaster_admin_testing` (phpunit.xml force=true, init SQL docker/postgres/init-testing-db.sql) · 181 tests · Docker: `docker compose exec app php artisan …`

**Frontend:** @inertiajs/vue3 ^3.0.3 + Vue ^3.5.33 + TS ^6 strict + Vite 8 + laravel-vite-plugin ^3 · NO Pinia (none today; will add for notifications/capabilities polling on port) · NO Ziggy (0 route() calls; all URLs string literals) · i18n = vue-i18n ^11 (legacy:false), messages bundled from resources/lang/{sk,en}/app.json + locale from Inertia prop (sk default, will add uk on port) · Theme: DaisyUI 5 themes:false + [data-theme='app-theme'] OKLCH token block in resources/css/app.css; Instrument Sans via bunny.net · Icons @heroicons/vue · Dates date-fns (utils/date.ts) · RichTextEditorInput @tiptap · ESLint 10 flat (no ref ban yet), Prettier, Lefthook pre-commit + pre-push vue-tsc. No FE test runner.

## Domains

### tenancy — multi-tenant isolation, invitations, team management (Phase 2, 2026-09-06)

**Core:**
- App\Models\Tenant — UUIDv7; traits HasFactory, HasUuids, LogsActivity (logOnly owner_id,name,ico,dic,vat_number,is_vat_payer,vat_rate,iban,is_active), SoftDeletes. Columns: owner_id FK → users (restrictOnDelete), name, ico (nullable), dic (nullable), vat_number (nullable), is_vat_payer (bool), vat_rate (decimal), iban (nullable), swift_bic (nullable), invoice_number_format, registration_info, address_line, city, postal_code, country, contact_email, contact_phone, is_active, timestamps, soft_deletes. Relations: owner BelongsTo User, interface HasOne TenantInterface, memberships HasMany TenantMembership, members BelongsToMany User (via tenant_memberships, pivot is_active/joined_at). Methods: `missingSupplierFields(): array` (list required fields still empty: name/address_line/city/postal_code/ico + dic/vat_number when is_vat_payer; returns [] when complete; FE contract for banner label keys), `hasCompleteSupplierProfile(): bool` (shortcut === [] guard).
- App\Models\TenantMembership — UUIDv7 pivot; traits HasFactory, HasUuids, LogsActivity (logOnly is_active,position,first_name,last_name). Columns: user_id FK (restrictOnDelete), tenant_id FK (restrictOnDelete), is_active (bool), joined_at (timestamp), first_name (nullable), last_name (nullable), phone (nullable), position (nullable), timestamps. Unique (user_id, tenant_id); indices (tenant_id, is_active), (user_id). NOT BelongsToTenant (the pivot defines tenancy itself). Relations: user BelongsTo User, tenant BelongsTo Tenant.
- App\Models\TenantInterface — UUIDv7 settings per tenant (D1); traits HasUuids, LogsActivity (logOnly color). Columns: tenant_id FK unique (restrictOnDelete), color (TenantColorEnum nullable, integer in DB), timestamps. Will add phase 4: invoice_template, constant_symbol, payment_type, currency, rounding_mode (on port). Relation: tenant BelongsTo.
- App\Models\TenantInvitation — UUIDv7; traits BelongsToTenant, HasFactory, HasUuids, LogsActivity (logOnly email,role_name,status,expires_at,accepted_at, never token), SoftDeletes. Columns: tenant_id FK (restrictOnDelete), invited_by_user_id FK (nullOnDelete), email, role_name (100), token (64 unique), status (InvitationStatusEnum), expires_at, accepted_at (nullable), timestamps, soft_deletes. Indices (tenant_id, status); partial unique (tenant_id, email) WHERE deleted_at IS NULL AND status='pending'. Relations: invitedBy BelongsTo User, tenant BelongsTo (inherited scope). Methods: isAcceptable() (checks expiry + pending status), markAccepted().
- App\Services\RegistrationService (final readonly) — `createOwner(name, email, password, companyName, ico, ?TenantSupplierProfileData $supplier = null): User` → DB::transaction, User→Tenant (with supplier data if provided)→TenantInterface→TenantMembership→RoleTemplatesSeeder→Admin role assignment. `addTenant(User, AddTenantData): Tenant` → bootstrapTenant with null supplier (D1: redirect to settings.invoicing instead). `private bootstrapTenant(User, name, ico, color?, ?TenantSupplierProfileData $supplier = null): Tenant` — atomically seeds Tenant (supplier columns from DTO or defaults) + interface + membership + team-scoped role bundle.
- App\Services\InvitationAcceptService — `resolve(token): TenantInvitation` (withoutGlobalScope), `accept(invitation, AcceptInvitationData, skipPasswordCheck?): User` — transaction: abort if non-acceptable (410), hash-check existing user (or create+verify new user), setPermissionsTeamId(invitation.tenant_id), create/reactivate membership, assign role, markAccepted, return user. **Critical:** role lookup happens inside the invitation's tenant context, not session tenant.
- App\Services\RoleAssignmentGuard (final readonly) — `assertAssignable(User $actor, iterable<Role> $roles): void` — validates each role's permission set ⊆ actor's permissions; throws ValidationException if violated (privilege escalation guard for UserService::create/update).
- App\Http\Middleware\TenantContextMiddleware — app middleware (appended after LocaleMiddleware, before HandleInertiaRequests). `handle` resolves tenantId per D5: X-Tenant-Id header (if uuid + active membership, else 403 `tenant_forbidden`) → session active_tenant_id → first active membership. If resolved: `app()->instance('current_tenant_id', $id)`, `setPermissionsTeamId($id)`, session bind. Unauth users pass through (no binding).
- App\Http\Middleware\RequireActiveTenant — route middleware (`tenant.required`). Authenticated + no bound tenant → web: `Auth::logout()`, invalidate session, redirect login with error flash; API: 403 JSON `no_active_tenant`. Logout route still reachable (not gated).
- App\Scopes\TenantScope — global scope on BelongsToTenant models. When `app()->bound('current_tenant_id')`: `where tenant_id = ?`; else unfiltered (D6: console/jobs/seeders run intentionally unbound).
- App\Concerns\BelongsToTenant — trait: `bootBelongsToTenant()` adds TenantScope + creating hook fills tenant_id from container; `tenant(): BelongsTo<Tenant,$this>`.

**Satellites (BE):**
- DTOs: Tenants/{AddTenantData, TenantListItemData, TenantSupplierProfileData}, Invitations/{AcceptInvitationData, InvitationAcceptPageData}, Auth/MeData, PermissionGroupData (grouped permission list for forms). TenantSupplierProfileData (#[TypeScript]): full supplier identity input (name handled by Tenant directly; address_line, city, postal_code, country, dic, vat_number, is_vat_payer, contact_email, contact_phone, iban, swift_bic); `toTenantAttributes()` returns dict for Tenant::create; used by RegistrationService::createOwner + CreateOwner command input.
- Enums: PermissionEnum (53 backed string cases, #[TypeScript]), TenantColorEnum (8 hex, #[TypeScript]), InvitationStatusEnum (pending/accepted/revoked/expired), InvitationAcceptStateEnum (4 states for FE: expired/wrong_user/existing_user/new_user), SupportedLanguage (now sk/en/uk with `getDefault()`, `getDisplayName()`, `isSupported()`).
- Factories: TenantFactory (forOwner), TenantMembershipFactory (withProfile, inactive states), TenantInvitationFactory (accepted, expired, revoked states), TenantInterfaceFactory.
- Seeders: PermissionSeeder (global catalogue from PermissionEnum), RoleTemplatesSeeder (per-tenant role bundles: Admin=all, Vedúca, Sekretárka, Účtovníčka, Interná upratovačka, Zákazník; `seedForTenant(Tenant)` idempotent, called post-bootstrap).
- Policies: TenantPolicy::switchTo(User, Tenant) = active membership + active tenant. Deliberately no `create` (D4a exception: new tenant has no roles yet → circular RBAC).
- Notifications: InvitationCreated (ShouldQueue, afterCommit, #[Tries(3)], mail-only, token + tenant_name + role_name in body).
- Commands: CreateOwner (interactive prompts or flags: --name, --email, --password, --company, --ico + 11 optional supplier flags --address-line, --city, --postal-code, --country (default SK), --dic, --vat-number, --vat-payer (value-less), --contact-email, --contact-phone, --iban, --swift; validates via TenantSupplierProfileData::validateAndCreate before any DB write; calls RegistrationService::createOwner with built DTO).
- Routes: POST /tenants (auth-only, no policy — D4a exception, documented), POST /tenants/{tenant}/switch (TenantPolicy), GET|POST /invitations/{token} (guest, throttle:invitation-accept on POST), GET /api/me (auth:sanctum, returns MeData with active tenant + permissions per team scope).
- Middleware: TenantContextMiddleware (app-level, D5 resolution), RequireActiveTenant (route `tenant.required`), plus bootstrap/app.php registers aliases.

**FE Satellites (apply via FE agent plan phase-2-tenancy-fe.md):**
- Composables: usePageProps (ComputedRef<SharedProps>), useAuthorization (allows(PermissionEnum)), useTenantTheme (themeStyle for --color-primary override).
- Components: TenantColorDot, TenantSwitcher (dropdown), AddTenantModal, ColorSwatchPicker, Can (permission guard).
- Pages: Invitations/Accept (4 states: expired/wrong_user/existing_user/new_user), Users/Index/Form (tenant members only, updated filters/forms).
- Shared props: tenant{active,available}, tenantColors, can (camelCase keys per PermissionEnum::sharedKey).

**Flow:**
- **Create owner (bootstrap):** `php artisan app:create-owner` → prompts name/email/password/company/ico → RegistrationService::createOwner → User + Tenant + TenantInterface + TenantMembership + seeded roles + Admin role assigned → logs "created successfully". Bootstrap flow only.
- **Add tenant:** Authenticated user (any) POST /tenants (AddTenantData: name, ico, color optional, copy_settings bool, leader_email optional) → RegistrationService::addTenant → new Tenant (owned by actor, supplier fields empty) → TenantInterface → active membership (actor + Admin role) → optional InvitationCreated mail to leader_email → session switch to new tenant → redirect to route('settings.invoicing') with flash tenant_created_complete_supplier (D1: actor is Admin, always authorized to complete supplier profile). No policy gate (D4a).
- **Switch tenant:** User POST /tenants/{tenant}/switch → TenantPolicy::switchTo gates (membership + active) → session bind active_tenant_id → Inertia redirect dashboard → shared props refresh (tenant, can, navigation).
- **Accept invitation:** Guest or logged-in GET /invitations/{token} → resolves InvitationAcceptPageData (4 states) → InvitationAcceptForm if form-state; POST /invitations/{token} (AcceptInvitationData: password, name optional, throttle 5/min) → InvitationAcceptService::accept (existing: Hash::check; new: create+verify) → Auth::login → session bind inviting tenant → redirect dashboard + flash. Expired/accepted/revoked → 410. Unknown token → 404.
- **API /api/me:** GET (auth:sanctum + tenant.context) → MeData{userId, activeTenantId, permissions[]} (per resolved tenant team scope via PermissionRegistrar::setPermissionsTeamId).

**Depends on:** Spatie Permission (teams=true, team_foreign_key=tenant_id), Spatie Activitylog, mailer (InvitationCreated notification).

**Depended on by:** Every tenant-scoped model (identity, auth, audit-logs, media, localisation) inherits BelongsToTenant + TenantScope; all services call setPermissionsTeamId; controllers gate via TenantPolicy / model policies + app()-bound current_tenant_id.

**If you change Core, check:**
- PermissionEnum consumers: PermissionSeeder, RoleTemplatesSeeder, PermissionGroupData, PermissionManager (FE), Inertia shared can keys, #[Authorize] routes, NavItem permissions.
- TenantColorEnum consumers: TenantInterface casting, AddTenantModal (FE), Inertia tenantColors prop, CSS --color-primary override (FE).
- InvitationStatusEnum/InvitationAcceptStateEnum: InvitationAcceptPageData (FE mapping), InvitationController template, tests/Feature/Tenancy/InvitationAcceptTest.php.
- Invitation token route: regex constraint, throttle middleware, controller 410/404 guards.
- setPermissionsTeamId call sites: TenantContextMiddleware, RegistrationService, InvitationAcceptService, UserService, tests/TestCase::actingAsTenantUser, every job/command (D6 discipline).
- TenantScope unfiltered behaviour (D6): tests/Feature/Tenancy/TenantScopeIsolationTest.php assert; console commands + seeders bind explicit tenant context.

**Keywords (SK):** firma, tenancy, member, pozvánka, invitation, tenant switcher, multi-tenant, tenant isolation, shared-props, team scope, setPermissionsTeamId, tenant_id FK, BelongsToTenant, TenantScope, active membership, is_active.

### identity — users, roles, permissions, self-service profile

**Core:**
- App\Models\User — UUIDv7 via App\Concerns\HasUuids; traits HasRoles, HasApiTokens, LogsActivity (logOnly name,email,locale,is_active), Notifiable, CanResetPassword. Columns: name, email(unique), password, locale(2, default sk), is_active(bool, default true), email_verified_at, timestamps. No SoftDeletes; global identity (no tenant_id). Relations: memberships HasMany TenantMembership, tenants BelongsToMany Tenant (via tenant_memberships, pivot is_active/joined_at), ownedTenants HasMany Tenant (owner_id). Methods: hasActiveMembership(?tenantId): bool (exists-query for "has at least one active membership").
- App\Models\Role extends Spatie Role — UUIDv7 via HasUuids + LogsActivity (logOnly name,guard_name,tenant_id) + scopeInTenant(Builder, string) (no global scope; app queries use inTenant() explicitly). Methods: isSystem(): bool (= name === RoleTemplatesSeeder::ADMIN_ROLE). Per-tenant.
- App\Models\Permission extends Spatie Permission — UUIDv7 via HasUuids. Global catalogue (not per-tenant); only roles are per-tenant.
- Both registered config/permission.php (teams=true, team_foreign_key=tenant_id, uuid team columns).
- App\Services\UserService (final readonly, ctor DatabaseManager, PermissionRegistrar, RoleAssignmentGuard): `create(CreateUserData, User $actor): User` — transaction, setPermissionsTeamId(current_tenant_id), look up or create User by email, membership create/reactivate (is_active from DTO), `$guard->assertAssignable($actor, roles)`, syncRoles in tenant scope, return fresh User. `update(User, UpdateUserData, User $actor): User` — name/email/locale on User, is_active on membership, roles guard + syncRoles in tenant scope. `delete(User): void` — syncRoles([]), syncPermissions([]), delete tenant membership (User row never hard-deleted in phase 2).
- App\Services\RoleService: `const SYSTEM_ROLES = [RoleTemplatesSeeder::ADMIN_ROLE]` · `create(name, permissions): Role` (check uniqueness inTenant + not in SYSTEM_ROLES) · `update(Role, name, permissions)` · `delete(Role)` (block SYSTEM_ROLES + Spatie guard) · `getPermissionsGrouped(): list<PermissionGroupData>` (from PermissionEnum cases grouped by group(), each item has id/name/label per PermissionItemData).
- App\Services\ProfileService::updateProfile(User, UpdateProfileData): User — name/email/locale on User, session('locale') + app()->setLocale(). Locale sync via app()->setLocale() (user.locale consumer on login via LocaleMiddleware).
- App\Enums\PermissionEnum (#[TypeScript], backed string) — 53 cases, grouped into 15 groups (employees, roles, audit_logs, api_docs, media, billing; phases 3+ add clients, objects, quotes, contracts, schedule, invoices, recurring_invoices, notifications, contract_templates). Methods: label(), group(), groupLabel(), sharedKey() (Str::camel($value)), values(). Key list in Phase 2 plan §3.1.

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
- API mirror (Sanctum): GET /api/users → same member-only query; GET|PUT|DELETE /api/users/{user}, GET|PUT /api/profile, PUT /api/profile/password → same services, JSON UserListItemData (paginated).

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
- App\Models\Activity extends Spatie\Activitylog\Models\Activity (D2) — `final class`, booted() creating hook sets `tenant_id ??= app('current_tenant_id')` (nullable for login/logout before tenant bound). Columns: morph subject/causer (uuid), batch_uuid (nullable), **tenant_id (uuid nullable, new)**, created_at, etc. Methods: `scopeVisibleInTenant(Builder, $tenantId): Builder` (tenant_id = ? OR tenant_id IS NULL AND causer is member of tenant); `isVisibleInTenant($tenantId): bool` (same predicate, single row). Config activitylog.php activity_model → `App\Models\Activity`, clean_after_days 365.
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
- App\Models\Media extends Spatie\MediaLibrary\MediaCollections\Models\Media (D1-new) — `final class`, booted() creating hook sets `tenant_id ??= app('current_tenant_id')` (throws RuntimeException('media.tenant_context_missing') when unbound). Columns: morph model_id (uuid), **tenant_id (uuid NOT NULL, new)** + index, disk public per config/media-library.php, 10 MB max. Methods: `scopeInTenant(Builder, $tenantId): Builder` (tenant_id = ?). Config media-library.php media_model → `App\Models\Media`.
- App\Models\TemporaryUpload — UUID PK, #[Fillable(['session_id','user_id','tenant_id'])], implements HasMedia, collection default; traits HasFactory, BelongsToTenant. FKs: user_id → users (nullOnDelete), **tenant_id → tenants (restrictOnDelete, new)**. TenantScope applied. user(): BelongsTo.
- App\Services\TemporaryUploadService (final readonly, DB::transaction): `store(UploadedFile, ?User, string $sessionId): Media` (posts to /uploads, current_tenant_id bound required) · `moveToModel(HasMedia $model, string $collection, string $uuid): Media` (tenant + ownership checks via OwnedTemporaryMedia rule) · `delete(string $uuid, ?User, string $sessionId): void` · `purgeOlderThan(int $hours=24): int` (runs unbound in console, sees all tenants intentionally).
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

**Depends on:** tenancy (Media.tenant_id, TemporaryUpload tenant FK + TenantScope, inTenant scope), identity (user_id FKs), auth, AllowedFilter, config/media-urls.php.

**Depended on by:** future media-owning models (Quote document, Invoice PDF) via moveToModel + OwnedTemporaryMedia, FileUploadInput (FE form component).

**If you change Core, check:**
- config/media-urls.php (references models; no current consumers) + media-urls config loading.
- TemporaryUploadService: store (app('current_tenant_id') required), moveToModel (OwnedTemporaryMedia validation), purgeOlderThan (unbound console, all tenants).
- OwnedTemporaryMedia rule: validates uuid + user_id + tenant_id match current context.
- MediaPolicy: tenant_id === current_tenant_id guard on view.
- FileUploadInput.vue (FE): posts to /uploads (hard-coded, inside tenant.required group), deletes to /uploads/{uuid}.

**Keywords:** media, upload, temporary upload, HasMedia (Spatie), collection, moveToModel, OwnedTemporaryMedia, purge, tenant_id scope, media-urls, FileUploadInput.

### api-me — Sanctum API self-capability endpoint

**Core:**
- App\Http\Controllers\Api\MeController (invokable, `GET /api/me`, Sanctum auth) — #[Group('Auth')], #[Authenticated], #[Endpoint('Me', ...)], #[ResponseFromSpatieData(MeData::class, User::class)]. Returns current user's active tenant + permissions.
- App\Data\Auth\MeData (#[TypeScript]) — userId: string, activeTenantId: string (non-null: route behind tenant.required), permissions: PermissionEnum[] (derived from $user->getAllPermissions() filtered to active tenant team scope via PermissionRegistrar::setPermissionsTeamId before query; FE converts to union for type-safe checks).
- Middleware: appended to api group as `['auth:sanctum', 'tenant.context', 'tenant.required']` (D5 resolution, post-Sanctum).
- No policy gate (returns caller's own capabilities, documented in controller).

**Flow:**
- GET /api/me (Sanctum Bearer) — resolves user via Sanctum, TenantContextMiddleware (D5: X-Tenant-Id header precedence → session → first active membership), setPermissionsTeamId(resolved), getAllPermissions()->pluck('name') → map to PermissionEnum cases, return MeData. On foreign tenant header → 403 `tenant_forbidden`; on zero memberships → 403 `no_active_tenant`.

**Depends on:** tenancy (active tenant resolution), identity (user permissions per team).

**Depended on by:** mobile app phase 2 (capabilities polling every 60s, not yet consumer in phase 2 web).

**Keywords:** api, sanctum, bearer, me, capabilities, tenant context, permissions per team.

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
- HandleInertiaRequests::share builds can: `collect(PermissionEnum::cases())->mapWithKeys(fn ($p) => [$p->sharedKey() => $user->can($p->value)])` (derived from 53-case enum, not hand-maintained list).
- FE: useAuthorization().allows(PermissionEnum) → reads page.props.can[permissionKey(value)] === true (permissionKey mirrors Str::camel).
- <Can permission="create clients"> guard with optional #fallback slot.
- Per-tenant team scope: Spatie Permission teams=true, team_foreign_key=tenant_id. Every role/permission check calls setPermissionsTeamId(current_tenant_id) first (TenantContextMiddleware, services, tests).

### Activitylog reach
User, Role (logOnlyDirty, dontLogEmptyChanges), LogAuthenticationActivity (3 events). Morph columns uuid. Phase 3+: enabled on business models (Client, Object, Quote, Invoice, RecurringInvoice, ContractTemplate, Contract, EmploymentContract, ScheduledJob, TenantMembership, WorkBreakdown — all SoftDeletes domain models except WorkBreakdownTask which cascades).

### MediaLibrary
Two collections: TemporaryUpload::default (public disk, 24h purge), Quote::document (private disk DOCUMENTS_DISK, singleFile, never replaced via form edit). **Phase 5:** Quote document-kind quotes upload PDF/image via temporary upload contract (FE posts to /uploads, gets uuid, form sends document_uuid, DTO validates OwnedTemporaryMedia + TemporaryMediaConstraints, service moves via moveToModel + re-validates ownership/tenant; Media::move honours collection useDisk so file physically moves from public temp to private DOCUMENTS_DISK). On port: add Contract/Invoice PDF attachments (phase 6+) — use same private-disk contract.

### AllowedFilter + DataTable query contract
App\Utils\AllowedFilter extends Spatie AllowedFilter: search([...cols]), contains, dynamic (→ SymbolOperatorFilter, operators != <= >= ~ < > = between:), relationExact(name, relation, column), callbackClean, callbackWithOperator. Validators uuid()/date()/isoDateTime()/integer()/numeric()/boolean() log-and-skip. ILIKE on pgsql. FE counterpart Composables/useSpatieTableQuery.ts + Components/DataTable/* (filter[x]=<opPrefix><value> URL contract). Operator prefixes (filterOperators.ts ↔ SymbolOperators::parse, keep in sync): ~: contains, !=:, <:, <=:, >:, >=:, between: (from,to), none = '='. Operator sets by FilterType: string/text ~ = !=; number = != < <= > >= between; boolean = (tri-state); date/datetime = < <= > >= between; enum/select/autocomplete = != (+multiple → comma). Sub-components TableFilters, TableFilter, TableSearch (400 ms debounce → filter[search]), TablePagination (emits page, perPage; 10/25/50/100).

### Precognition + Forms
HandlePrecognitiveRequests around every POST|PUT mutation (web + api). AppServiceProvider::register (:25-27) binds PrecognitiveDataValidatorResolver (honours Precognition-Validate-Only), short-circuits DTO resolution during precognitive requests. FE: useForm(method, url, data) (Inertia 3 method-bound, Precognition built-in) inside FormProvider → form.submit() → BE store|update(DTO) → 422 → form.errors Record<field,string> | 204 + redirect with flash → toast.

### Spatie Data DTO boundary
DTOs injected as controller params, #[TypeScript] on all → resources/js/types/generated.d.ts (App.Data.*, App.Enums.*) via TypeScriptTransformerServiceProvider (app/Data + app/Enums, GlobalNamespaceWriter). DataValidatorResolver singleton swapped — custom resolvers must extend PrecognitiveDataValidatorResolver.

### Navigation (BE-driven discovery)
App\Navigation\NavItem attribute (repeatable, method-level: label, route, icon, permission, policyModel, group, order) discovered by NavigationRegistry via router reflection; visibility Gate::forUser()->allows(permission, policyModel) or $user->can (:79-89); groups NavigationRegistry::GROUPS (settings). Shared as navigation: NavigationItemData[], rendered Layouts/AppLayout.vue:215. FE: hardcoded ICONS map (HomeIcon, UsersIcon, ShieldCheckIcon, ClipboardDocumentListIcon, PhotoIcon, EnvelopeIcon, UserCircleIcon, Cog6ToothIcon; unknown → HomeIcon) — every new NavItem icon must be added. translateLabel strips app. prefix then t(). Active = page.url.startsWith(href). Nav links plain <a> (full page load). On port: add NavItems for clients, objects, quotes, contracts, invoices, recurring invoices, schedule, employees, notifications + extend ICONS map.

### PDF generation (spatie/laravel-pdf, chrome driver via Alpine apk) — Phase 4+

**Backend:**
- `config/laravel-pdf.php` (published + configured): driver `chrome` (env LARAVEL_PDF_DRIVER), chrome_binary `env('CHROMIUM_PATH', '/usr/bin/chromium-browser')`, no_sandbox true, custom_flags `['--disable-gpu', '--disable-dev-shm-usage']`, timeouts (30s startup, 30000ms, 5000ms operation).
- `spatie/laravel-pdf` with `chrome-php/chrome` (pure-PHP DevTools, no Node at runtime).
- Alpine Dockerfile: `apk add chromium nss freetype harfburst ca-certificates ttf-dejavu font-noto`, smoke test `chromium-browser --headless --version`.
- Contracts: `RendersInvoicePdf { public function render(Invoice $invoice): string; }` + `RendersQuotePdf { public function render(Quote $quote): string; }` + `RendersContractPdf { public function render(Contract $contract): string; }` (testable via mock).
- Services: `InvoicePdfService` renders `resources/views/pdf/invoices/{classic,modern,minimal}.blade.php` · `QuotePdfService` renders `resources/views/pdf/quotes/default.blade.php` · `ContractPdfService` renders `resources/views/pdf/contracts/default.blade.php` — all via chrome driver.
- Routes: `GET /invoices/{invoice}/pdf` · `GET /quotes/{quote}/pdf` · `GET /contracts/{contract}/pdf` (all Content-Disposition: attachment, filename sanitised via `HasPdfFilename::pdfFilenameBase()` + `HeaderUtils::makeDisposition`).
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
- `.env`: `MAIL_MAILER=smtp`, `MAIL_HOST=mailpit`, `MAIL_PORT=1025` (dev Mailpit service).

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
- `.env.example` + `.env`: `MAIL_MAILER=smtp`, `MAIL_HOST=mailpit`, `MAIL_PORT=1025`, `MAIL_SCHEME=null` (no TLS for Mailpit).
- Notifications: `InvoiceIssued` queued, via('mail') → customer_email, attach PDF.
- Dev: browse Mailpit UI at `http://localhost:8025` to view sent mails + attachments.
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

**TODO (out of scope Phase 2):**
- Notification bell (polling /api/notifications/bell every 60s, display unread count + 5 recent).
- Dashboard content (widgets, recent activity).

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
- can: Record<string, boolean> (derived from PermissionEnum::cases, keyed by sharedKey = Str::camel, 53 keys phase 2; e.g., 'viewEmployees', 'createClients')
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

### clients — Cleaning company customers (corporate + private) with contacts and address

**Core:**
- `App\Models\Client` — UUIDv7; traits BelongsToTenant, HasFactory, HasUuids, LogsActivity, SoftDeletes. Columns: tenant_id FK, `type` (ClientTypeEnum: corporate/private), name (255), nullable ico (32) + dic (32) + vat_number (32), bool is_vat_payer, address (street/city/postal_code/country), nullable note. Relations: `contacts(): HasMany<ClientContact>`, `primaryContact(): HasOne<ClientContact>` (where is_primary=true), `objects(): HasMany<CleaningObject>`. Activity log on changes (logOnly same list as columns; logOnlyDirty). Partial unique index `(tenant_id, ico)` WHERE deleted_at IS NULL AND ico IS NOT NULL.
- `App\Models\ClientContact` — UUIDv7 pivot; traits BelongsToTenant, HasFactory, HasUuids, LogsActivity, SoftDeletes. Columns: tenant_id FK, client_id FK, name (255), nullable position (255) + email (255, unique per client's contact set) + phone (64), bool is_primary. Relations: client BelongsTo, tenant BelongsTo. Index (client_id, is_primary).
- `App\Services\ClientService` (final readonly, ctor DatabaseManager) — `paginate(Request): LengthAwarePaginator` (QueryBuilder::for(Client) + AllowedFilter search/name/type/city/ico/created_at + defaultSort name + eager load contacts + primaryContact + counts → through ClientListItemData); `create(ClientUpsertData): Client` (transaction: Client::create + syncContacts); `update(Client, ClientUpsertData): Client`; `delete(Client): void` — **D1 override user decision:** transaction: soft-delete all client's objects via `$client->objects->each->delete()` (SoftDeletes global scope excludes trashed from queries; per-model logging), soft-delete contacts, soft-delete client. CleaningObject keeps both SoftDeletes (for this cascade) and is_active (for direct deactivation via ObjectService::deactivate). **D8 note:** IČO uniqueness enforced by DTO rule (Precognition-visible) + DB partial index (race constraint accepted for internal tool). `private syncContacts(Client, DataCollection): void` — existing-id validation, soft-delete outgoing, primary auto-promotion (none → first becomes primary).
- `App\Enums\ClientTypeEnum` (#[TypeScript], backed string) — `Corporate='corporate'`, `Private='private'`. Method: `label(): string` → `__('app.client_type_'.$this->value)`.

**Satellites (BE):**
- DTOs: `ClientContactData` (id, name, ?position, ?email, ?phone, is_primary); `ClientUpsertData` (type enum required, name required, ?ico requiredIf type=corporate, ?dic, ?vat_number, is_vat_payer, address fields, ?note, contacts: DataCollection<ClientContactData>). Rules: ico unique per tenant (D8), at most one primary contact. `ClientListItemData` (id, type, name, ?ico, ?city, contacts_count, objects_count, ?primary_contact_email, ?primary_contact_phone, created_at). `ClientDetailData` (extends ListItemData + dic, vat_number, street, postal_code, country, note, contacts: DataCollection<ClientContactData>). `ClientOptionData` (id, name) — for pickers.
- Enums: ClientTypeEnum (see Core above).
- Factories: ClientFactory (with `private()`, `withContacts(int $count)` states).
- Seeders: ClientSeeder (demo tenant 12345678 with 6 corporate + 3 private clients, 2–3 contacts each).
- Policies: `ClientPolicy` — viewAny/view → ViewClients; create → CreateClients; update → EditClients; delete → DeleteClients. Tenant isolation via TenantScope (no extra record check).
- Controllers: `ClientController` (ctor ClientService $clients), actions: `#[Authorize('viewAny')] index(Request)` → Clients/Index `[clients, filters]`; `#[Authorize('view')] show(Client)` → Clients/Show `[client: ClientDetailData, objects: ObjectListItemData[]]` (load contacts, eager-load client on objects); `#[Authorize('create')] store(ClientUpsertData)` → index + flash; `#[Authorize('update')] update(ClientUpsertData, Client)` → show + flash; `#[Authorize('delete')] destroy(Client)` → index + flash. Routes: GET /clients, GET /clients/{client}, POST /clients (Precognition), PUT /clients/{client} (Precognition), DELETE /clients/{client} (all `whereUuid`).
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

**Depended on by:** objects (client_id FK, ObjectUpsertData rule), quotes phase 4 (client subject), invoices phase 4 (client subject), contracts phase 4 (service agreement subject).

**If you change Core, check:**
- ClientService::delete cascade: objects soft-delete logic (loop with `->delete()` per-model for activity logging), contact soft-delete, activity logging per model (3 types: Client, ClientContact, CleaningObject).
- IČO uniqueness: DTO rule builder (Rule::unique 'clients' with tenant_id + whereNull deleted_at), partial DB index, test reuse-after-delete scenario.
- Contact primary: syncContacts logic (reset all → promote first if none), validation closure (at most one), test auto-promote.
- ClientTypeEnum.label() consumers: Badge (label t(clientTypeKey(type))), Form (type selector options), tests.
- ClientPolicy used in: ObjectController (clients list for picker), ObjectService (no direct policy call but objects depend on clients).

**Keywords (SK):** klient, podnikateľ, fyzická osoba, kontakt, IČO, DIČ, DPH, adresa, kontaktná osoba, primárny kontakt.

### objects — Cleaning locations per client with access info and deactivation

**Core:**
- `App\Models\CleaningObject` — table `objects`; UUIDv7; traits BelongsToTenant, HasFactory, HasUuids, LogsActivity, **SoftDeletes** (D1 override user decision: hybrid lifecycle — is_active for direct deactivation, deleted_at for client cascade). Columns: tenant_id FK, client_id FK, type (ObjectTypeEnum: office/apartment/house/common_areas), name (255), address (street/city/postal_code/country), nullable access_code (64) + key_box_code (64) + key_count (int) + special_instructions (text) + area_sqm (decimal:2) + floor (int) + gps_lat (10,7) + gps_lng (10,7) (GPS reserved, no UI), bool is_active (direct deactivation), timestamps + soft_deletes. Relations: `client(): BelongsTo` → `$this->belongsTo(Client::class)->withTrashed()` (keeps relation resolvable even if client is soft-deleted). Scopes: `scopeVisibleTo(Builder, User): Builder` — **D2 fail-closed:** if actor can ViewAllObjects return unfiltered, else `whereRaw('1 = 0')` (D2 phase-7 TODO: replace with job assignment scope). `isVisibleTo(User): bool` — calls `scopeVisibleTo` as exists check. Activity log logOnly (no note column logged).
- `App\Services\ObjectService` (final readonly, ctor DatabaseManager) — `paginate(Request, User): LengthAwarePaginator` (QueryBuilder::for(CleaningObject::visibleTo($actor)) + AllowedFilter search/name/type/client_id/is_active/city/created_at + sorts + eager load client + through ObjectListItemData); `create(ObjectUpsertData): CleaningObject`, `update(CleaningObject, ObjectUpsertData): CleaningObject` (both transaction); `deactivate(CleaningObject): void` (transaction: set is_active=false, log activity).
- `App\Enums\ObjectTypeEnum` (#[TypeScript], backed string) — `Office='office'`, `Apartment='apartment'`, `House='house'`, `CommonAreas='common_areas'`. Method: `label(): string` → `__('app.object_type_'.$this->value)`.

**Satellites (BE):**
- DTOs: `ObjectUpsertData` (client_id required UUID, type enum, name required, address/access fields, is_active default true, key_count/area_sqm/floor nullable). Rules: client_id Rule::exists scoped to current_tenant_id() + whereNull deleted_at (live clients only). `ObjectListItemData` (id, type, name, ?city, is_active, client_id, ?client_name, ?area_sqm, created_at). `ObjectDetailData` (extends ListItemData + street, postal_code, country, access_code, key_box_code, key_count, special_instructions, floor, gps columns omitted from response).
- Enums: ObjectTypeEnum (see Core).
- Factories: CleaningObjectFactory (default client_id via Client::factory(), `inactive()` state).
- Seeders: ObjectSeeder (demo tenant, 1–3 per client, last client gets one inactive).
- Policies: `ObjectPolicy` — viewAny → ViewObjects; view → ViewObjects && isVisibleTo($user); create → CreateObjects; update → EditObjects && isVisibleTo; delete (gates deactivate) → DeleteObjects && isVisibleTo. Tenant isolation + **own-only scoping** (D2) via isVisibleTo.
- Controllers: `ObjectController` (ctor ObjectService), actions: `#[Authorize('viewAny')] index(Request)` → Objects/Index `[objects, filters, filterOptions: {clients}]` (clients empty for own-only actors → filter hidden, button disabled); `#[Authorize('view')] show(CleaningObject)` → Objects/Show `[object: ObjectDetailData, clients: ClientOptionData[]]` (clients empty if !can('update') → edit hidden); `#[Authorize('create')] store(ObjectUpsertData)` → show + flash; `#[Authorize('update')] update(ObjectUpsertData, CleaningObject)` → show + flash; `#[Authorize('delete')] deactivate(CleaningObject)` (POST to deactivate endpoint) → show + flash; `#[Authorize('update')] reactivate(CleaningObject)` (POST to reactivate endpoint) → show + flash. Routes: GET /objects, GET /objects/{object}, POST /objects (Precognition), PUT /objects/{object} (Precognition), POST /objects/{object}/deactivate, POST /objects/{object}/reactivate (all `whereUuid`). `private clientOptions(User $actor): array` — ViewAllObjects actors get all clients; own-only get empty (D2).
- Navigation: #[NavItem] on index → `objects` nav (icon BuildingOffice2Icon, permission ViewObjects, order 31).

**FE Satellites:**
- Composables: none.
- Components: `ObjectTypeBadge` (badge + icon per type: office/apartment/house/common_areas mapped to different colours/icons), `ObjectStatusBadge` (active green / inactive ghost), `ObjectDetailCard` (type/status badges, ?client link, area/floor, address, created_at), `ObjectAccessCard` (warning-bordered card: access_code/key_box_code/key_count with sensitive warning + caption), `ObjectForm` (form: client SelectInput required, type/name, address fields, access section, key_count/special_instructions, is_active toggle on edit), `ObjectFormDrawer`, `objectPayload.ts` helper (ObjectFormData interface, function to map DetailData → payload incl. string→null conversion).
- Pages: `Objects/Index` (DataTable: columns name/type/client_name→link/city/area_sqm/is_active; filters search/name/type/client_id/is_active/city/created_at; sort name/type/city/is_active/created_at; info hint when !ViewAllObjects; empty state; buttons view/deactivate when active; create drawer pre-scoped if single client; create hidden when no client list). `Objects/Show` (header + edit/deactivate buttons; inactive banner with reactivate button when is_active=false; grid: left ObjectAccessCard+instructions, right ObjectDetailCard; deactivate/reactivate modals; edit drawer).
- i18n: `object_add`, `object_edit`, `object_deactivate`, `object_reactivate`, `object_deactivate_confirm`, `object_deactivate_hint`, `object_inactive_banner`, `object_type`, `object_name`, `object_area_sqm`, `object_floor`, `object_access`, `object_access_code`, `object_key_box_code`, `object_key_count`, `object_access_sensitive_hint`, `object_special_instructions`, `object_no_instructions`, `object_is_active`, `object_created`, `objects_empty`, `objects_empty_hint`, `objects_own_only_hint` (per phase-3 FE plan).

**Flow:**
- **List:** GET /objects (actor) → ObjectController@index + visibleTo($actor) + AllowedFilter → Objects/Index {objects, filters, filterOptions: {clients}}. Own-only actors: clients = [], info hint shown, button hidden. Table rows: view → Show; deactivate button (active only) → deactivate confirm modal.
- **Show:** GET /objects/{object} → RMB TenantScope + Policy::view isVisibleTo gate → Objects/Show {object, clients: empty if !can('update')}. Edit drawer (clients list provided). Reactivate button (is_active=false) → PUT /objects/{id} with full payload + is_active=true (trade-off: replays full record; dedicated endpoint optional future improvement).
- **Create:** POST /objects (Precognition) → ObjectService::create → 302 index + flash.
- **Update:** PUT /objects/{object} (Precognition) → same service, 302 show + flash.
- **Deactivate:** POST /objects/{object}/deactivate → ObjectPolicy::delete gate + isVisibleTo → ObjectService::deactivate (is_active=false, activity logged).

**Depends on:** tenancy (tenant_id FK, BelongsToTenant, TenantScope), clients (client_id FK + Rule::exists to live clients, client() withTrashed relation, ClientService::delete soft-deletes objects as cascade).

**Depended on by:** quotes phase 4 (object subject), invoices phase 4 (object subject), contracts phase 4 (service agreement subject), schedule phase 7 (ScheduledJob.object_id FK).

**If you change Core, check:**
- ObjectService::deactivate: is_active = false + activity logged (not soft-delete trigger).
- scopeVisibleTo logic: D2 fail-closed check (ViewAllObjects cap) vs phase-7 job-based scoping. TODO phase 7 marker in code.
- client() withTrashed relation: orphaned inactive objects of deleted clients still resolve client_name (test scenario).
- ObjectPolicy isVisibleTo gates: view/update/delete all check it (D2 defence-in-depth).
- Precognition cross-field rules (client_id exists scope) behaviour with partial payloads — deferred full testing to phase 4+.

**Keywords (SK):** objekt, lokalita, kancelária, byt, dom, spoločné priestory, prístupový kód, kľúč, deaktivácia, citlivé údaje.

### quotes — Itemized + document quotes, clientless snapshot, status lifecycle, convertToInvoice (Phase 5, 2026-09-06)

**Core:**
- `App\Models\Quote` — UUIDv7; traits BelongsToTenant, HasFactory, HasPdfFilename, HasUuids, InteractsWithMedia, LogsActivity (logOnly status/kind/number/total/client_id/cleaning_object_id), SoftDeletes. Columns: tenant_id FK, nullable client_id FK → clients (withTrashed), nullable cleaning_object_id FK → objects (withTrashed), status (QuoteStatusEnum: draft/sent/accepted/rejected/expired), kind (QuoteKindEnum: itemized/document, immutable after create), nullable number (50 chars, partial unique index tenant_id + number WHERE deleted_at IS NULL AND number IS NOT NULL), nullable subject (255), issue_date (date), valid_until (date), nullable sent_at/accepted_at/rejected_at (timestamp), bool is_vat_payer, nullable vat_rate (decimal:5,2), currency (CurrencyEnum, default EUR), money columns (subtotal/vat_amount/total default 0, all decimal:12,2), json vat_breakdown nullable, **clientless snapshot** (customer_name nullable, customer_email/customer_street/customer_city/customer_postal_code nullable, mutually exclusive with client_id via DTO `prohibits`), nullable note (text), timestamps, soft_deletes. Indexes (tenant_id,status), (tenant_id,kind), (tenant_id,client_id), (tenant_id,valid_until). Relations: `client()->withTrashed()`, `cleaningObject()->withTrashed()`, `items(): HasMany<QuoteItem>` ordered position, `invoices(): HasMany<Invoice>` (quote_id FK, re-conversion allowed → many invoices per quote). Methods: `isEditable(): bool` (Draft), `canBeConverted(): bool` (Accepted), `isDocument(): bool` (kind === document). MediaLibrary `registerMediaCollections()`: collection `document` singleFile useDisk config('quotes.document.disk', 'local') — private disk, never public.
- `App\Models\QuoteItem` — UUIDv7; traits BelongsToTenant, HasFactory, HasUuids. Columns: tenant_id FK, quote_id FK (restrictOnDelete), description (255), frequency nullable (50, quote-only for phase 7 work-breakdown generation), quantity/unit_price (decimal:10,2), unit nullable (50), discount_percent/vat_rate (decimal:5,2), computed line_base/line_vat/line_total (decimal:12,2), position (smallInt). Index quote_id. Relation: `quote(): BelongsTo`.
- `App\Enums\QuoteStatusEnum` (#[TypeScript], backed string) — Draft|Sent|Accepted|Rejected|Expired; `canTransitionTo(self): bool` matrix; `label(): string`.
- `App\Enums\QuoteKindEnum` (#[TypeScript], backed string) — Itemized|Document; `label(): string`.
- `App\Services\QuoteService` (final readonly, ctor InvoiceService, TemporaryUploadService, DocumentTotalsCalculator, DatabaseManager) — `paginate(Request): LengthAwarePaginator<QuoteListItemData>` (QueryBuilder AllowedFilter search across number/subject/customer_name/client.name + dynamic filters status/kind/client_id/issue_date/valid_until/total/created_at, default -created_at, eager load client/object/media), `create(QuoteUpsertData, ?User, string $sessionId): Quote` (transaction: itemized → syncItems + computeTotals; document + document_uuid → moveToModel), `update(Quote, QuoteUpsertData, ?User, string $sessionId): Quote` (guard isEditable), `send(Quote): Quote` (guard status transition + document kind; dispatch QuoteSent event), `accept/reject(Quote): Quote` (status guard + document guard; stamp accepted_at/rejected_at), `attachClient(Quote, string $clientId, ?string $objectId): Quote` (guard client_id IS NULL; nulls 5 snapshot columns; ObjectBelongsToClient rule), `duplicate(Quote): Quote` (transaction: Draft copy, number=null, issue_date=today, valid_until=today+config('quotes.default_validity_days'), copy items; document → copy media), `delete(Quote): void` (guard isEditable; soft-delete), `convertToInvoice(Quote): Invoice` (guard document kind + Accepted status; build InvoiceUpsertData mapping items with frequency suffix, snapshot clientless data if client_id IS NULL; transaction: create invoice via InvoiceService, update invoice.quote_id).
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
- **Create document:** POST /quotes (kind=document, document_uuid staged) → moveToModel → private disk → 302 show.
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

### invoices — Draft / issue / pay / cancel + credit notes, SK VAT, Pay-by-Square QR (Phase 4, 2026-09-06)

**Core:**
- `App\Models\Invoice` — UUIDv7; traits BelongsToTenant, HasFactory, HasPdfFilename, HasUuids, LogsActivity (logOnlyDirty status/number/total/client_id/issued_at/paid_at/cancelled_at/sent_at), SoftDeletes. Columns: tenant_id FK, nullable client_id FK → clients (withTrashed), nullable cleaning_object_id FK → objects (withTrashed), nullable recurring_invoice_id FK → recurring_invoices (restrictOnDelete), nullable credited_invoice_id self-FK (restrictOnDelete), **nullable quote_id FK → quotes (nullOnDelete)** (audit backlink when generated from quote.convertToInvoice; invoice keeps snapshot so delete doesn't cascade; index quote_id), type (InvoiceTypeEnum: monthly/one_off/special), status (InvoiceStatusEnum: draft/issued/paid/overdue/cancelled), template (InvoiceTemplateEnum: classic/modern/minimal), nullable number (50 chars, partial unique index tenant_id + number WHERE deleted_at IS NULL AND number IS NOT NULL), variable_symbol (10 chars, derived from number digits only), dates (period_from/period_to/issue_date/delivery_date/due_date nullable except issue_date), timestamps issued_at/sent_at/paid_at/cancelled_at nullable, **customer snapshot** (customer_name required, representative/ico/dic/vat_number/street/city/postal_code/country/email nullable), **object snapshot** (object_name/street/city/postal_code nullable), **supplier snapshot** (supplier_name required, ico/dic/vat_number/iban/swift/address_line/city/postal_code/country/contact_email/contact_phone/registration_info nullable, snapshot at issue from Tenant), bool is_vat_payer, nullable vat_rate (decimal), money columns (subtotal/vat_amount/total/deposit/rounding_amount default 0, all decimal:12,2), json vat_breakdown (nullable, frozen at issue), **SK fields** (constant_symbol/specific_symbol/payment_type/currency/rounding_mode/header_text/footer_text all nullable except currency EUR default), nullable note, reserved efa_status/efa_id (spec: IS EFA phase 8+), timestamps, soft_deletes. Indexes (tenant_id,status), (tenant_id,due_date), (tenant_id,issue_date), (tenant_id,client_id), recurring_invoice_id, credited_invoice_id, quote_id. Relations: `client()->withTrashed()`, `cleaningObject()->withTrashed()`, `items(): HasMany<InvoiceItem>` ordered position, `quote(): BelongsTo<Quote>` (nullable, audit trail), `creditedInvoice(): BelongsTo`, `creditNote(): HasOne credited_invoice_id`, `recurringInvoice(): BelongsTo`. Methods: `balanceDue(): Attribute` (total - deposit float), `isEditable(): bool` (Draft), `canBeCancelled(): bool` (Issued | Overdue).
- `App\Models\InvoiceItem` — UUIDv7; traits BelongsToTenant, HasFactory, HasUuids. Columns: tenant_id FK, invoice_id FK (restrictOnDelete), description, qty/unit/unit_price (decimal:10,2), discount_percent (decimal:5,2), vat_rate (decimal:5,2), computed line_base/line_vat/line_total (decimal:12,2), position (smallInt). Index invoice_id. Relation: `invoice(): BelongsTo`.
- `App\Models\InvoiceNumberSequence` — UUIDv7; traits BelongsToTenant, HasUuids. Columns: tenant_id FK, year (smallInt), last_number (int), timestamps. Unique (tenant_id, year). No activity logging (operational).
- `App\Services\InvoiceNumberService` — `next(Tenant, DateTimeInterface): string` (firstOrCreate + lockForUpdate + collision loop honouring `{YYYY}/{YY}/{MM}/{X+}` format placeholders), `variableSymbol(string): ?string` (digits max 10 chars).
- `App\Services\InvoiceService` (final readonly, ctor DocumentTotalsCalculator, InvoiceNumberService, DatabaseManager) — `paginate(Request): LengthAwarePaginator<InvoiceListItemData>` (QueryBuilder AllowedFilter search/number/status/type/client_id/customer_name/issue_date/due_date/total/created_at, sorts default -created_at, eager load client), `stats(): InvoiceStatsData` (issued this month / overdue / pending / recurring monthly), `create(InvoiceUpsertData): Invoice` (transaction: snapshot customer/object/supplier + syncItems via DocumentTotalsCalculator → computeTotals with VAT breakdown + rounding), `update(Invoice, InvoiceUpsertData): Invoice` (guard isEditable), `issue(Invoice, InvoiceIssueData): Invoice` (guard Draft → guard supplier completeness: `$tenant->missingSupplierFields() !== []` → ValidationException key `supplier` + __('app.invoice_supplier_incomplete') before transaction, no sequence consumed; then tenant-scoped number exists check, set number/variable_symbol/status/issued_at), `markPaid(Invoice): Invoice` (guard canTransitionTo(Paid)), `cancel(Invoice): Invoice` (transaction: mark Cancelled + create credit note with negated items/SK fields), `duplicate(Invoice): Invoice` (Draft copy, no number), `delete(Invoice): void` (guard isEditable), `send(Invoice): void` (guard Issued + customer_email, dispatch InvoiceIssued queued notification).
- `App\Services\InvoiceSettingsService` — `update(Tenant, InvoiceSettingsData): void` (transaction: write tenant supplier + invoicing fields, write interface invoice defaults).
- Contracts: `RendersInvoicePdf { public function render(Invoice $invoice): string; }`, `GeneratesPaymentQr { public function dataUri(Invoice $invoice): ?string; }`.
- `App\Services\Pdf\InvoicePdfService implements RendersInvoicePdf` — chrome driver, Blade-based 3 templates (Classic/Modern/Minimal).
- `App\Services\Pdf\PayBySquareService implements GeneratesPaymentQr` — null unless Issued|Overdue + IBAN + EUR + balance_due > 0.
- `App\Notifications\InvoiceIssued` — queued (ShouldQueue, #[Tries(3)] #[Backoff] #[Timeout(120)]), afterCommit, mail-only, PDF attachment via RendersInvoicePdf, sent_at stamped by StampInvoiceSentAt listener on NotificationSent event.
- `App\Events\InvoiceMarkedOverdue` — domain event dispatched by MarkOverdueInvoices command (no phase-4 listeners; phase-5 notifications subscribes).
- `App\Console\Commands\MarkOverdueInvoices` — daily cron, flips Issued past due_date → Overdue, dispatches InvoiceMarkedOverdue.

**Satellites (BE):**
- Enums: InvoiceStatusEnum (draft/issued/paid/overdue/cancelled, `canTransitionTo(self $to): bool`), InvoiceTypeEnum (monthly/one_off/special), InvoiceTemplateEnum (classic/modern/minimal, `view(): string`), PaymentTypeEnum (transfer/cash/card/cod/other), CurrencyEnum (EUR/CZK/USD uppercase), RoundingModeEnum (none/document/cash_005, `round(float): float`).
- DTOs: `InvoiceUpsertData` (client_id nullable, object_id nullable, type/template/dates, customer_* snapshot fields, items, SK fields, payment fields, deposit), `InvoiceItemData` (description/qty/unit/unit_price/discount/vat_rate + line_* nullable), `InvoiceIssueData { ?string $number }`, `InvoiceListItemData` (id/number/status/type/customer_name/client_id/client_name/object_name/currency/total/balance_due/issue_date/due_date), `InvoiceDetailData` (full DTO + vat_breakdown array + qr_data_uri + `quote_id: string|null` + `quote_number: string|null` (backlink to source quote if generated via convertToInvoice) + `supplier_missing_fields: string[]` empty when Issued or status terminal, non-empty on Draft; vocabulary keys: name/address_line/city/postal_code/ico/dic/vat_number), `InvoiceSupplierData`, `VatBreakdownLineData`, `InvoiceStatCardData`, `InvoiceStatsData`, `InvoiceFormContextData` (clients/objects/is_vat_payer/defaults/recurring_default_state + `supplier_missing_fields: string[]` from tenant; mounted in Create/Edit/Show forms for banner), `InvoiceSettingsData` (supplier name/ico/dic/vat_number/is_vat_payer/address/contact + invoice defaults template/number_format + recurring defaults), `ObjectOptionData` (for pickers).
- Policies: InvoicePolicy (viewAny/view/create/update/issue/markPaid/send/cancel/delete/downloadPdf per PermissionEnum).
- Controllers: InvoiceController (#[NavItem] invoices), InvoiceSettingsController (#[NavItem] invoicing_settings, ManageBillingSettings gate).
- Routes: GET|POST /invoices; GET|PUT|DELETE /invoices/{invoice}; POST /invoices/{invoice}/issue/pay/cancel/duplicate/send; GET /invoices/{invoice}/pdf; GET|PUT /settings/invoicing; GET /settings/invoicing/preview/{template}.
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
- **Pay:** Issued/Overdue → POST /invoices/{id}/pay → InvoiceService::markPaid (paid_at set) → 302 show + flash. List row action or card button.
- **Cancel:** Issued/Overdue → POST /invoices/{id}/cancel + confirm → InvoiceService::cancel (transaction: Cancelled, cancelled_at, credit note created) → 302 show + flash. Both invoices linked (original.credited_invoice_id ← note.id, note.credited_invoice_id ← original.id).
- **Send:** Issued + email → POST /invoices/{id}/send → guard + dispatch InvoiceIssued job → sent_at stamped by listener → 302 show + flash. PDF attachment via mocked (test) or real Chromium.
- **PDF:** GET /invoices/{id}/pdf (Content-Disposition: attachment) → RendersInvoicePdf renders via chrome driver, filename from invoice.pdfFilenameBase + `HeaderUtils::makeDisposition`.
- **Duplicate:** Draft only → POST /invoices/{id}/duplicate → InvoiceService::duplicate (copy without number/VS) → 302 edit + flash.

**Depends on:** tenancy (tenant_id FK, BelongsToTenant, TenantScope), clients (client_id FK + rule, withTrashed relation), objects (cleaning_object_id FK + rule, withTrashed relation), recurring-invoices (recurring_invoice_id FK), quotes (quote_id FK nullOnDelete, audit backlink, InvoiceService.create receives built InvoiceUpsertData from QuoteService.convertToInvoice), media (temporary upload → moveToModel contract for future PDF attachments phase 6+).

**Depended on by:** recurring-invoices (InvoiceService consumed by RecurringInvoiceService::generateInvoiceFromTemplate), quotes (quote_id backlink for source tracking), Inertia show page (quote_id/quote_number link to Quotes/Show).

**If you change Core, check:**
- InvoiceStatusEnum canTransitionTo matrix (Draft→Issued; Issued→Paid|Overdue|Cancelled; Overdue→Paid|Cancelled; Paid/Cancelled terminal).
- InvoiceService::issue lockForUpdate loop + collision detection vs manual number override (InvoiceIssueData).
- InvoiceService::cancel credit-note generation (negated items + all SK fields copied, own number, two-way linked).
- InvoicePdfService chrome driver setup (config/laravel-pdf.php CHROMIUM_PATH, env LARAVEL_PDF_DRIVER, docker apk packages).
- PayBySquareService null conditions (Issued|Overdue, IBAN, EUR, balance_due > 0).
- InvoiceIssued notification queued discipline (afterCommit, retries, timeout), attachment via contract interface (testable via mock).
- computeTotals money math (line_base formula, vat_breakdown grouping, rounding modes, balanceDue logic).
- Snapshot timing (create/update write, issue freezes, non-editable invoice pay/cancel unaffected).
- MarkOverdueInvoices cron (withoutGlobalScope TenantScope, per-invoice event dispatch) + test idempotency.

**Keywords (SK):** faktúra, návrh, vydaná, splatnosť, overdue, zaplatená, stornovaná, dobropis, číslovanie, DPH, jednotková sadzba, záloha, Pay-by-Square QR, zokrúhlenie.

### recurring-invoices — Template-driven auto-generation on schedule (Phase 4, 2026-09-06)

**Core:**
- `App\Models\RecurringInvoice` — UUIDv7; traits BelongsToTenant, HasFactory, HasUuids, LogsActivity (logOnlyDirty status/frequency/next_run_at/start_date/occurrences_generated), SoftDeletes. Columns: tenant_id FK, nullable client_id FK (withTrashed), nullable cleaning_object_id FK → objects (withTrashed), name (255), type (InvoiceTypeEnum, immutable after create), template nullable (InvoiceTemplateEnum), frequency (RecurringFrequencyEnum: monthly/every_2_months/quarterly/semi_annually/annually), day_of_month (1–28), status (RecurringInvoiceStatusEnum: active/paused/completed/cancelled), bool auto_issue, dates (start_date, end_date nullable, period_from/period_to nullable), occurrences_limit nullable, occurrences_generated default 0, next_run_at/last_generated_at nullable, **customer snapshot** (customer_name/representative/ico/dic/vat_number/street/city/postal_code/country/email nullable, same as Invoice), **SK fields** (constant_symbol/payment_type/currency/rounding_mode/header_text/footer_text/deposit), due_days (14 default), nullable note, timestamps, soft_deletes. Relations: `client()->withTrashed()`, `cleaningObject()->withTrashed()`, `items(): HasMany<RecurringInvoiceItem>`, `generatedInvoices(): HasMany<Invoice>` (`recurring_invoice_id` FK). Methods: `isRunnable(): bool` (active + today ≤ next_run_at), `hasReachedLimit(): bool`, `hasReachedEndDate(Carbon): bool`.
- `App\Models\RecurringInvoiceItem` — UUIDv7; traits BelongsToTenant, HasFactory, HasUuids. Columns: tenant_id FK, recurring_invoice_id FK (restrictOnDelete), description, qty/unit/unit_price (decimal:10,2), discount_percent (decimal:5,2), vat_rate (decimal:5,2), position. Index recurring_invoice_id.
- `App\Services\RecurringInvoiceService` (final readonly, ctor InvoiceService, DatabaseManager) — `paginate(Request): LengthAwarePaginator<RecurringInvoiceListItemData>` (QueryBuilder AllowedFilter search/name/customer_name/status/frequency/client_id/next_run_at/created_at, default -created_at), `create/update/delete`, `pause/resume/cancel` (state transitions with 422 guards), `generateInvoiceFromTemplate(RecurringInvoice): Invoice` (build InvoiceUpsertData with per-item vat_rate/discount + SK fields + deposit, call InvoiceService::create, link via recurring_invoice_id, optionally auto-issue per flag or tenant default), `resolveTenantDefaultState(tenantId): RecurringDefaultStateEnum`.
- `App\Jobs\GenerateRecurringInvoiceJob` (ShouldQueue, ShouldBeUnique) — queued from GenerateRecurringInvoices command, `uniqueId() = recurring_id`, `uniqueFor() = 3600`, idempotency guard (isRunnable && next_run_at ≤ today), binds `app()->instance('current_tenant_id')`, transaction: generate via service, (D2b) check `$tenant->missingSupplierFields()` before auto-issue — if supplier incomplete + auto_issue=true, skip issue (leave Draft + Log::warning) and advance schedule anyway; else if supplier complete, issue normally, advance next_run_at/occurrences_generated/last_generated_at, mark Completed on limit/end-date.
- `App\Console\Commands\GenerateRecurringInvoices` — daily cron, finds Active rows with next_run_at ≤ today, dispatches job per row.

**Satellites (BE):**
- Enums: RecurringFrequencyEnum (monthly/every_2_months/quarterly/semi_annually/annually, `monthsInterval(): int`, `nextRunDate(Carbon $from, int $dayOfMonth): Carbon` — clamps to daysInMonth, startOfDay), RecurringInvoiceStatusEnum (active/paused/completed/cancelled, `isRunnable(): bool`), RecurringDefaultStateEnum (draft/issued).
- DTOs: `RecurringInvoiceUpsertData` (name/type/frequency/day_of_month/auto_issue/start_date/end_date/occurrences_limit, customer_* snapshot, SK fields, items, due_days), `RecurringInvoiceItemData` (description/qty/unit/unit_price/discount/vat_rate), `RecurringInvoiceListItemData`, `RecurringInvoiceDetailData` (full + lastGenerated invoice timestamp + generated invoices list).
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
- RecurringInvoiceService generateInvoiceFromTemplate: per-item vat_rate/discount + SK fields snapshot + auto-issue logic (flag vs tenant default).
- Termination logic: end_date vs occurrences_limit (both provided = 422), Completed state, can't pause completed/cancelled.

**Keywords (SK):** opakovaná faktúra, harmonogram, frekvencia, výtvorenosť, podmienky ukončenia, automatické vydanie.

### settings-invoicing — Tenant supplier data + invoice defaults (Phase 4, 2026-09-06)

**Core:**
- `App\Models\TenantInterface` (existing, extended in Phase 4) — adds columns to phase 2 baseline: `invoice_template` (InvoiceTemplateEnum, default 'classic'), `recurring_default_state` (RecurringDefaultStateEnum, default 'draft'), `default_constant_symbol` (10 chars nullable), `default_payment_type` (PaymentTypeEnum, default 'transfer'), `default_currency` (CurrencyEnum, default 'EUR'), `default_rounding_mode` (RoundingModeEnum, default 'none'). Extended logOnly with these six fields.
- `App\Models\Tenant` (extended) — adds supplier columns: `dic` (32 nullable), `vat_number` (32 nullable), `address_line` (255 nullable), `city` (255 nullable), `postal_code` (16 nullable), `country` (2 nullable), `contact_email` (255 nullable), `contact_phone` (30 nullable), `swift_bic` (11 nullable, SWIFT-BIC format). Extended logOnly + @property docblocks. Existing: `name` (255), `ico` (20 nullable), `is_vat_payer` (bool), `iban` (34 nullable), `invoice_number_format` (255, default 'FA-{YYYY}-{XXXX}'), `registration_info` (text nullable), `vat_rate` (decimal:5,2).
- `App\Services\InvoiceSettingsService` — `update(Tenant, InvoiceSettingsData): void` (transaction: write Tenant supplier + invoicing fields, write TenantInterface invoice defaults + recurring defaults).

**Satellites (BE):**
- DTOs: `InvoiceSettingsData` (supplier: name/ico/dic/vat_number/is_vat_payer/address_line/city/postal_code/country/contact_email/contact_phone; invoicing: invoice_template/invoice_number_format/iban/swift_bic/vat_rate/registration_info; recurring: recurring_default_state; defaults: default_constant_symbol/default_payment_type/default_currency/default_rounding_mode). Rules: country 2 chars, IBAN format, SWIFT-BIC format, number format with {X+} placeholder validation, constant symbol digits only, vat_rate 0–100.
- Policies: no policy (owner-only gate via ManageBillingSettings permission).
- Controllers: `InvoiceSettingsController` (final readonly, ctor InvoiceSettingsService) — `#[Authorize(ManageBillingSettings)]` on show/update, `#[NavItem(invoicing_settings, ManageBillingSettings, settings group)]`. Routes: GET /settings/invoicing → InvoiceSettingsData::fromTenant(current_tenant); PUT /settings/invoicing (Precognition) → InvoiceSettingsService::update.

**FE Satellites:**
- Pages: `Settings/Invoicing` (supplier section, bank section, number format preset/custom, template picker, defaults, recurring state).
- Components: `InvoiceSettingsSupplierCard`, `InvoiceSettingsBankCard`, `InvoiceNumberFormatField` (presets + custom with live example), `InvoiceTemplatePicker` + `InvoiceTemplateThumbnail` (with preview iframe), `InvoiceSettingsDefaultsCard`.
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
- Tenant logOnly: 11 new supplier fields + existing (name, ico, is_vat_payer, iban, invoice_number_format, registration_info, vat_rate).
- InvoiceSettingsService::update: transaction scope, Tenant + TenantInterface both updated.
- Number format validation regex (must include {X+}).
- Defaults propagation in InvoiceFormContextData::fromTenant (stale defaults if not re-fetched on page load).

**Keywords (SK):** nastavenia, dodávateľ, číslovanie, šablóna, SK polia, záloha.

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
- PermissionEnum (53 backed string cases, grouped by resource: employees/roles/audit_logs/api_docs/media/billing + 9 groups phase 3+) — methods: label(), group(), groupLabel(), sharedKey() (Str::camel), values(). Labels NOT shipped in generated.d.ts (FE uses t() for localized labels from app.json; BE Spatie Permission stores value).
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

41. **Contract body resolution happens on every Draft save, not just template assignment.** PlaceholderResolverService::resolve called in `ContractService::create` and `::update` while status=Draft (idempotent — resolved text contains no `{{…}}` tokens; unknown tokens stay verbatim for manual completion). Body is frozen post-sign (`update` guarded by `isEditable()`). Consequence: a user who edits a Draft contract's body, typo-fixing a token `{{client.nam}}` → `{{client.name}}`, saves twice → first save resolves the token (if `{{client.name}}` exists); second save is idempotent. On sign, body is snapshot-frozen; template edits never propagate to existing contracts (snapshot by design).

42. **EmploymentContract has strict (restrict, not cascade) FK on contract_id.** Parent Contract soft-delete does NOT cascade-delete EmploymentContract (soft-delete parent prevents cascade anyway; no `forceDelete` path exists in phase 6). Result: deleting a Draft employment contract requires deleting its employment child first OR allowing the service to handle it via `updateOrCreate(['contract_id'], […])` when employment data absent. Current code: `employment()->delete()` called in `ContractService::update` when category changes from employment→other; delete action checks `isEditable()` before soft-delete (service guards authorization).

43. **Contract number is free text, not unique.** Unlike invoices (auto-numbered per tenant per year, unique), contracts use optional `number` field (max 50 chars, no uniqueness, no auto-assignment). Reason: quotes converted to contracts copy the quote's number; re-conversion of the same quote must not collide on uniqueness. Contracts can have duplicate numbers (reference_number use case: customer PO numbers, multi-contract grouping). Show page uses `{{ number }}` when set; list displays both title + number in separate columns.

44. **ContractableTypeEnum morph aliases must stay in sync with AppServiceProvider::morphMap.** `ContractableTypeEnum.modelClass() → App\Models\CleaningObject | App\Models\TenantMembership` (backend logic). Relation::morphMap(['cleaning_object' => CleaningObject::class, 'tenant_membership' => TenantMembership::class]) must match enum values exactly. FE mirror: CONTRACT_CATEGORY_CONTRACTABLE map (employment→tenant_membership, service_agreement→cleaning_object, nda/gdpr/other→null) enforces UX rules; service `assertCategoryMatchesContractable()` is the authority (422 on mismatch).

45. **Sekretárka can edit/sign/terminate contracts (D2 user decision).** Role seeder (RoleTemplatesSeeder) grants Sekretárka EditContracts + TerminateContracts permissions. Test `test_secretary_can_edit_sign_and_terminate` in tests/Feature/Contracts confirms workflow: Sekretárka creates Draft → edits → signs → terminates. Permits full contract lifecycle management (view/create/edit/terminate/delete + templates full CRUD) per user decision D2 (EditContracts permission gates both edit action + sign via `ContractPolicy::sign`).

37. **Quote document upload via temporary-file contract.** FE posts file to `POST /uploads` (separate endpoint, TemporaryUploadPolicy + upload files permission), gets uuid + metadata back. Form sends `document_uuid` in QuoteUpsertData. DTO validates OwnedTemporaryMedia (user/session/tenant ownership) + TemporaryMediaConstraints (mime whitelist from config('quotes.document.allowed_mimes'), size ≤ config('quotes.document.max_size_kb')). Service calls `TemporaryUploadService::moveToModel($quote, 'document', $uuid, $user, $sessionId)` inside transaction (tenant+ownership re-checked). `Media::move()` physically relocates file from public temp disk to private DOCUMENTS_DISK (no URL exposed, route-gated access only). On re-upload, `singleFile()` replaces (media count stays 1). Staged uploads live ≤24h before cron purge (skeleton-wide, not quote-specific; known gap).

38. **DocumentTotalsCalculator extracted for quotes + invoices.** Both models need identical VAT line math + breakdown grouping. Extracted to final readonly service (line + totals methods). InvoiceService::syncItems + computeTotals delegate to it. QuoteService::syncItems + computeTotals delegate to it. Parity test proves identical output for same inputs (QuoteTotalsParityTest). RoundingModeEnum::round applies to invoices only (None enum case when rounding not applicable); quotes ignore rounding (treated as None).

39. **convertToContract deferred to Phase 6.** Quote::contracts() HasMany relation NOT added; Quote.convertToContract() method absent; POST /quotes/{id}/convert-to-contract route missing; QuotePolicy::convertToContract() missing; ContractService::createFromQuote() not implemented. Phase 6 adds polymorphic subject + backlink. Phase 5 job: add contract lifecycle starting from accepted quotes (scope Phase 6+ plan).

40. **Quote item frequency is phase 7 work-breakdown input.** QuoteItem.frequency column (nullable 50 chars, e.g., "weekly_1x", "seasonal") stays unused in phase 5 (no consumer). Phase 7 work breakdown generation reads it when accepted quote is converted to ServiceAgreement contract, decomposing items into WorkBreakdownTask frequency recurrence. If missing (null), defaults to "one_time" task.

### contract-templates — Reusable contract body templates with placeholders (Phase 6, 2026-09-06)

**Core:**
- App\Models\ContractTemplate — UUIDv7; traits BelongsToTenant, HasFactory, HasUuids, LogsActivity (logOnly name,category,is_active), SoftDeletes. Columns: tenant_id FK (restrictOnDelete), name (255), category (ContractCategoryEnum backed string 32), body (text, plain text with `{{placeholder}}` tokens), is_active (bool, default true), timestamps, soft_deletes. Indices: (tenant_id, category), (tenant_id, is_active). Relations: contracts HasMany. Scopes: active() excludes inactive. Methods: none (read-only scope, mutations via service).
- App\Services\ContractTemplateService (final readonly, DatabaseManager injected): `paginate(Request): LengthAwarePaginator<ContractTemplateListItemData>` (QueryBuilder with AllowedFilter search/category dynamic/is_active boolean, sorts name/category/is_active/updated_at, default name); `create(ContractTemplateUpsertData): ContractTemplate` (transaction); `update(ContractTemplate, ContractTemplateUpsertData): ContractTemplate` (transaction); `delete(ContractTemplate): void` (soft-delete).
- App\Contracts\RendersContractPdf interface (parallel to RendersQuotePdf, RendersInvoicePdf); bound in AppServiceProvider::register.
- App\Services\Pdf\ContractPdfService implements RendersContractPdf — `render(Contract $contract): string` via Pdf::view('pdf.contracts.default', compact('contract','tenant'))->generatePdfContent(), chrome driver (body via `nl2br(e())`, employment section conditional, signature + termination blocks, supplier + party details).

**Satellites (BE):**
- DTOs: ContractTemplateUpsertData (#[Required, Max(255)] name, category enum, #[Required, Max(50000)] body, is_active=true); ContractTemplateListItemData (id, name, category, is_active, updated_at); ContractTemplateDetailData (id, name, category, body, is_active); ContractTemplateOptionData (id, name, category, body — body included for FE prefill without round-trip).
- Enums: ContractCategoryEnum (service_agreement, employment, nda, gdpr, other; #[TypeScript], backed string; expectedContractableType() method: employment→tenant_membership, service_agreement→cleaning_object, rest→null).
- Policies: ContractTemplatePolicy (viewAny/view/create/update/delete gated via PermissionEnum::ViewContractTemplates/CreateContractTemplates/etc.).
- Controllers: ContractTemplateController with #[NavItem(label: 'app.contract_templates', route: 'contract-templates.index', icon: 'RectangleStackIcon', group: 'settings', order: 30)] on index (D1: templates in settings group, not top-level). Routes: GET|POST /contract-templates, GET|PUT|DELETE /contract-templates/{contractTemplate} (Precognition on store/update).
- Factories: ContractTemplateFactory with states inactive(), employment() (body differs per category).
- i18n: contract_template_{add,edit,created,updated,deleted}, contract_template_name/category/is_active/inactive_hint/body/body_placeholder, contract_select_category.

**Flow:**
- GET /contract-templates → list with filters (search, category, is_active) · create link gated `view contract_templates` → form with body editor + token list → POST /contract-templates (PreCognition) → redirect to show → flash.
- Show template: read-only body preview with token highlighting; edit/delete gated.

**Depends on:** tenancy (tenant_id FK), plain-text body (no HTML), placeholder resolution (phase 7 WorkBreakdownService subscribes to ContractSigned).

**Depended on by:** ContractService::create (optional contract_template_id), QuoteService::convertToContract (template-driven body resolution).

**If you change Core, check:**
- ContractTemplateService methods and filters (AllowedFilter names in controller match service paginate definitions).
- ContractTemplate.php body column max length in migration vs #[Max(50000)] in DTO.
- Template soft-delete + withTrashed() on Contract.contractTemplate() (frozen body carries template_name, template still resolves if trashed).

**Keywords (SK):** šablóna zmluvy, kategória zmluvy, placeholder, token, premenná.

### contracts — Service agreements, employment contracts, lifecycle (Phase 6, 2026-09-06)

**Core:**
- App\Models\Contract — UUIDv7; traits BelongsToTenant, HasFactory, HasPdfFilename, HasUuids, LogsActivity (logOnly status,signed_at,terminated_at,number,title,contractable_id), SoftDeletes. Columns: tenant_id FK (restrictOnDelete), contract_template_id FK (nullOnDelete, nullable, withTrashed() on reads), quote_id FK (nullOnDelete, nullable, foreign quotes), uuidMorphs('contractable') (required, type/id composite unique), category (ContractCategoryEnum 32), status (ContractStatusEnum 32, default draft), term_type (ContractTermTypeEnum 32), title (255), number (50, nullable, free text, no uniqueness), body (text, plain text resolved server-side on save while Draft), valid_from (date), end_date (date, nullable, required when term_type=fixed), signed_at (datetime, nullable), terminated_at (datetime, nullable), termination_reason (text, nullable), notes (text, nullable), timestamps, soft_deletes. Indices: (tenant_id, status), (tenant_id, category), quote_id, contract_template_id, expiry check (end_date WHERE status=active AND term_type=fixed). Relations: contractTemplate BelongsTo (withTrashed), quote BelongsTo (withTrashed), contractable MorphTo (cleaning_object → CleaningObject, tenant_membership → TenantMembership per morph map), employmentContract HasOne (unique constraint on contract_id). Scopes/methods: isEditable() (status === Draft), canBeSigned() (status === Draft), canBeTerminated() (status === Active), contractableLabel() (object name or membership user name+email).
- App\Models\EmploymentContract — UUIDv7; traits BelongsToTenant, HasFactory, HasUuids; fillable tenant_id/contract_id/employment_type/position/hourly_rate/monthly_salary/weekly_hours/probation_end_date; casts employment_type enum, decimal:2 ×3, probation_end_date date; contract BelongsTo. FK contract_id restrictOnDelete + unique (1:1 sub-part, lifecycle tied to parent). Tenant_id FK restrictOnDelete (orphaned employment records on membership delete are OK per D9).
- App\Services\ContractService (final readonly, DatabaseManager + PlaceholderResolverService injected): `paginate(Request): LengthAwarePaginator<ContractListItemData>` (QueryBuilder with AllowedFilter search title/number, dynamic filters status/category/term_type/contractable_type/valid_from date/end_date date, sorts title/number/status/category/valid_from/end_date/created_at, default -created_at, eager loads contractable + membership user); `create(ContractUpsertData, ?Quote $sourceQuote): Contract` — transaction: resolve contractable, assert category↔type, resolve body tokens, create Contract + child EmploymentContract when employment data present, return fresh with relations; `update(Contract, ContractUpsertData): Contract` — guard isEditable(), category↔type assert, re-resolve tokens, updateOrCreate employment child, return fresh; `sign(Contract): Contract` — guard canBeSigned(), transaction: update status→Active + signed_at→now(), dispatch ContractSigned event afterCommit; `terminate(Contract, ContractTerminateData): Contract` — guard canBeTerminated(), transaction: update status→Terminated + terminated_at + reason; `delete(Contract): void` — guard isEditable(), soft-delete; `private assertCategoryMatchesContractable(ContractCategoryEnum, ContractableTypeEnum): void`.
- App\Services\PlaceholderResolverService (final readonly): `resolve(string $body, array $variables): string` — str_replace `{{key}}` with value per variables (unknown tokens untouched); `variablesFor(contractable, tenant, ContractUpsertData, ?Quote): array` — union of tenant (tenant.name/ico/dic/address/iban), contract (contract.valid_from/end_date/title), object branch (client.name/ico/dic, object.name/address, loadMissing('client')), membership branch (employee.name — first/last fallback to user.name, employee.email/position, loadMissing('user')), quote branch (quote.number/total/items — only when Quote provided, via quoteItemsText() helper); `catalog(): PlaceholderCatalogData` — PlaceholderTokenData[] per ContractableTypeEnum (object + quote tokens in union for conversion use).
- Events: ContractSigned(tenantId, contractId), ContractExpired(tenantId, contractId), ContractExpiring(tenantId, contractId, daysLeft) — all ShouldDispatchAfterCommit, no listeners phase 6 (phase 7 subscribes for work-breakdown generation + notifications).

**Satellites (BE):**
- DTOs: ContractUpsertData (#[Required, Max(255)] title, #[Nullable, Max(50)] number, category enum required, term_type enum required, contractable_type enum required, #[Required, Uuid] contractable_id, #[Nullable, Uuid] contract_template_id, #[Required, Max(50000)] body, #[Required, Date] valid_from, #[Nullable, Date] end_date, #[Nullable, Max(5000)] notes, ?EmploymentContractUpsertData employment); rules() validates contractable_id exists in tenant (via closure, respects soft-delete for objects), template exists in tenant active + not trashed, end_date required_if fixed + after_or_equal valid_from, employment required_if employment category; messages() overrides end_date.required_if and employment.required_if. EmploymentContractUpsertData (#[Required] employment_type enum, #[Nullable, Max(255)] position, #[Nullable, Min(0)] hourly_rate/monthly_salary, #[Nullable, Min(0), Max(168)] weekly_hours, #[Nullable, Date] probation_end_date). ContractTerminateData (#[Required, Date] terminated_at, #[Nullable, Max(1000)] termination_reason). ContractListItemData (id, title, number, category, status, term_type, valid_from, end_date, contractable_type, contractable_label, signed_at) fromModel; is_editable/can_be_signed/can_be_terminated flags (BE R3, optional). ContractDetailData extends ListItemData + body, signed_at, terminated_at, termination_reason, notes, contractable_id, contract_template_id/name, quote_id/number, employment: ?EmploymentContractData, is_editable/can_be_signed/can_be_terminated flags, contractableLabel. EmploymentContractData (employment_type, position, hourly_rate/monthly_salary/weekly_hours/probation_end_date as strings), fromModel. MembershipOptionData (id, label, is_active), fromModel(TenantMembership, with user name/email fallback). PlaceholderTokenData (token, label); PlaceholderCatalogData (cleaning_object: PlaceholderTokenData[], tenant_membership: PlaceholderTokenData[]). ContractFormContextData (objects: ObjectOptionData[], memberships: MembershipOptionData[], templates: ContractTemplateOptionData[], tokens: PlaceholderCatalogData).
- Enums: ContractStatusEnum (draft, active, expired, terminated; methods canTransitionTo(target): bool, isEditable(), canBeSigned(), canBeTerminated(), label()); ContractTermTypeEnum (fixed, indefinite, label()); EmploymentContractTypeEnum (dpp, dpc, tpp, self_employed, label()); ContractableTypeEnum (cleaning_object, tenant_membership; modelClass(): string, table(): string, label()).
- Policies: ContractPolicy (viewAny/view → ViewContracts, create → CreateContracts, update → EditContracts + isEditable(), sign → EditContracts + canBeSigned(), terminate → TerminateContracts + canBeTerminated(), delete → DeleteContracts + isEditable(), downloadPdf → ViewContracts); ContractTemplatePolicy (viewAny/view → ViewContractTemplates, create → CreateContractTemplates, update → EditContractTemplates, delete → DeleteContractTemplates).
- Controllers: ContractController (ContractService + PlaceholderResolverService injected): index #[NavItem(label: 'app.contracts', route: 'contracts.index', icon: 'DocumentCheckIcon', order: 36)] · create/store/show/edit/update/destroy · sign/terminate (custom actions, Precognition on terminate) · pdf (GET /contracts/{id}/pdf, Content-Disposition attachment, filename via pdfFilenameBase + HeaderUtils::makeDisposition). ContractTemplateController (index #[NavItem] as above) · create/store/show/edit/update/destroy.
- Routes: GET|POST /contract-templates, GET|PUT|DELETE /contract-templates/{contractTemplate} · GET|POST /contracts, GET|PUT|DELETE /contracts/{contract}, POST /contracts/{contract}/sign, POST /contracts/{contract}/terminate, GET /contracts/{contract}/pdf (all within auth+tenant.required, Precognition on store/update/terminate).
- Commands: CheckContractExpiry (daily, `app:check-contract-expiry`): phase 1 — Active fixed-term contracts past end_date → Expired + log + dispatch ContractExpired event; phase 2 — for configured notice days [30,14,7]: contracts approaching end_date → log + dispatch ContractExpiring event.
- Config: config/contracts.php with expiring_notice_days array [30, 14, 7].
- Factories: ContractFactory with states draft/active/expired/terminated/indefinite, forObject(CleaningObject), forMembership(TenantMembership), fromQuote(Quote). EmploymentContractFactory with forContract(Contract).
- Blade PDF: resources/views/pdf/contracts/default.blade.php (body `nl2br(e())`, employment section conditional, terminated note, supplier+party blocks, signature lines, footer with status).
- i18n: contract_{created,updated,deleted,signed,terminated,not_editable,cannot_sign,cannot_terminate}, contract_invalid_contractable/end_date_required_for_fixed/employment_required/category_contractable_mismatch, contract_token_*, contract_pdf_*.

**FE Satellites (via phase-6-contracts-fe plan):**
- Pages: Contracts/{Index,Create,Edit,Show}, ContractTemplates/{Index,Create,Edit,Show}.
- Components: ContractStatusBadge, ContractCategoryBadge, ContractTermBadge, PlaceholderTokenList (grouped by prefix, keyboard-first), ContractBodyEditor (TextareaInput + insertAtCursor expose), ContractBodyPreview (token highlighting), ContractSubjectFields (category→contractable UX), EmploymentContractFields, ContractForm, ContractTerminateModal, ContractPartiesCard, ContractTermCard, ContractEmploymentCard, ContractActionsCard, ContractLinksCard, ContractTemplateForm.
- Utils: CONTRACT_STATUSES/CATEGORIES/TERM_TYPES/EMPLOYMENT_TYPES/CONTRACTABLE_TYPES arrays + key fns (contractStatusKey, etc.), CONTRACT_CATEGORY_CONTRACTABLE map (employment→membership, service_agreement→object, nda/gdpr/other→null — must mirror BE expectedContractableType).
- TextareaInput.vue extended: textareaRef + insertAtCursor(text) expose (the only legitimate ref() use — imperative DOM for caret manipulation).
- AppLayout.vue ICONS += DocumentCheckIcon, RectangleStackIcon.
- i18n FE-only keys: contract_template_add/edit/created/updated/deleted/name/category/is_active/inactive_hint/body/body_placeholder, contract_add/edit/created/updated/deleted/title/number/number_hint/term_type/valid_from/end_date/end_date_indefinite/contractable/contractable_type/contractable_fixed_object/contractable_fixed_membership/select_object/select_membership/select_term_type/select_contractable_type/template/no_template/body/body_placeholder/body_resolve_hint/notes/section_basics/section_subject/section_body/section_employment/section_notes/section_parties/section_term/section_actions/section_links/employment_type/position/hourly_rate/monthly_salary/weekly_hours/probation_end_date/party_supplier/party_object/party_employee/signed_at/not_signed/terminated_at/termination_reason/terminated_title/expiring_warning/action_sign/sign_confirm/action_terminate/terminate_confirm/terminate_date/terminate_reason/terminate_reason_placeholder/action_download_pdf/link_quote/link_object/link_template, contract_token_*.

**Flow:**
- Create: pick category → contractable type fixed or radio-switchable (D3) → pick party from options (objects active, memberships active only) → optional template pick (filtered by category) → body prefilled or kept + hint → body editor with token list for insertion → submit → Draft contract created, body tokens resolved (unknown tokens stay).
- Show Draft: title + status + category badges; parties card (supplier + party); term card (type, dates, signed/terminated); body preview (tokens highlighted); employment card conditional; lifecycle actions (sign/edit/delete); links card (quote/object/template backlinks). Sign → confirm → Active status, signed_at set, edit/delete disappear. Terminate → modal (date + reason) → Terminated status.
- List: filters (search, status, category, term_type, contractable_type, valid_from/end_date date ranges); sorts (title/number/status/category/valid_from/end_date/created_at, default -created_at); actions (sign Draft, terminate Active).
- PDF: GET /contracts/{id}/pdf (attachment, filename from number or 'draft.pdf', sanitized).

**Depends on:** tenancy (tenant_id FK), PlaceholderResolverService (body resolution), ContractTemplate (optional template_id), Quote (optional quote_id backlink), TenantMembership (for employment contractable), CleaningObject (for service-agreement contractable), PDF generation (RendersContractPdf contract), events (ContractSigned/Expired/Expiring, phase 7 listeners).

**Depended on by:** Quote::contracts() HasMany (reverse backlink), QuoteService::convertToContract (creates Draft service_agreement contract), phase 7 WorkBreakdownService (subscribes to ContractSigned for work breakdown generation).

**If you change Core, check:**
- ContractStatusEnum state matrix (canTransitionTo rules) vs ContractService::sign/terminate/delete/update guards.
- ContractCategoryEnum expectedContractableType() vs FE CONTRACT_CATEGORY_CONTRACTABLE map (must stay in sync).
- PlaceholderResolverService::variablesFor() token list vs PlaceholderCatalogData returned by catalog() (must list exact same tokens per contractable type).
- ContractTemplate.withTrashed() on Contract.contractTemplate() relation (frozen template name resolves even if template soft-deleted).
- Quote.withTrashed() on Contract.quote() relation (audit trail survives quote deletion).
- EmploymentContract restrict FK on contract_id (orphaned employment rows prevented; parent deletion blocked if strict fk enabled — never cascade).
- CheckContractExpiry command active filter (status = active AND term_type = fixed) matches service guards.
- ContractListItemData is_editable/can_be_signed/can_be_terminated flags (if added per R3) must match service guard conditions + backend model methods.

**Keywords (SK):** zmluva, zmluvná strana, predmet zmluvy, služobná zmluva, pracovná zmluva, čas platnosti, podpis, ukončenie, premenné, zástupný symbol, placeholder, token.

### quotes — Updated with contract conversion (Phase 6, 2026-09-06)

Updated from phase 5:
- Quote::contracts() HasMany → [Contract] (new FK quote_id on contracts table, nullOnDelete). Loaded in QuoteDetailData via eager load.
- QuoteDetailData.contracts: QuoteContractLinkData[] (id, title, number, status: ContractStatusEnum).
- QuoteService::convertToContract(Quote $quote, QuoteConvertToContractData $data): Contract — requires Accepted status + itemized kind + client_id + cleaning_object_id not null; optional contract_template_id; builds ContractUpsertData with body from template body (resolved) or items list (plain text); delegates to ContractService::create($upsert, $quote); returns Draft service_agreement contract with quote_id set. Re-conversion allowed.
- QuoteController::show prop contractTemplates: ContractTemplateOptionData[] (R1 — active, category=service_agreement, ordered by name; empty unless quote is Accepted + itemized + user can create contracts).
- QuotePolicy::convertToContract(User, Quote): bool → CreateContracts permission.
- Routes: POST /quotes/{quote}/convert-to-contract (Precognition) → to_route('contracts.show', $contract).
- Errors when converting: quote_not_acceptable_for_conversion (not Accepted), quote_document_not_convertible (document kind), quote_client_required_for_contract (no client_id), quote_object_required_for_contract (no object_id).
- **Phase 7 update:** QuoteItem.frequency enum (TaskFrequencyEnum) — used for work-breakdown generation when Accepted quote converted to ServiceAgreement contract.

### employees — Employee management over TenantMembership (Phase 7, 2026-09-06)

**Core:**
- App\Services\EmployeeService (final readonly, ctor DatabaseManager, PermissionRegistrar, RoleAssignmentGuard): `paginate(Request): LengthAwarePaginator<EmployeeListItemData>` (QueryBuilder TenantMembership with eager load user, filters search name/email/first_name/last_name/role, is_active boolean, sorts -joined_at, default); `create(EmployeeStoreData/EmployeeUpdateData, User $actor): TenantMembership` — transaction: lookup or create User (auto-verified if new), setPermissionsTeamId, create/reactivate membership (is_active from DTO), `$guard->assertAssignable($actor, roles)` (privilege escalation guard), syncRoles in tenant scope, dispatch InvitationCreated mail (new user only, afterCommit), return fresh TenantMembership; `update(TenantMembership, EmployeeStoreData/EmployeeUpdateData, User $actor): TenantMembership` — profile fields + role assignment (guard), syncPermissions (direct overrides, restricted to actor's own permissions), updateOrCreate EmploymentContract, return fresh; `deactivate(TenantMembership): TenantMembership` — is_active=false, dispatch unassignFutureForMembership job (via JobService, phase 7: cleaner own-only); `reactivate(TenantMembership): TenantMembership` — is_active=true.
- TenantMembership extended (phase 7): nullable profile columns first_name, last_name, phone, position; LogsActivity trait (logOnlyDirty logOnly is_active, first_name, last_name, phone, position); employmentContract MorphMany (type 'contract', EmploymentContractObserver listens for deletion via "deleting" event if employment remains orphaned post-contract-delete).
- App\Policies\TenantMembershipPolicy (rbac-full): viewAny/view → ViewEmployees permission + record-level check (member of active tenant), create → CreateEmployees, update → EditEmployees + record-level, delete → DeleteEmployees + record-level + not self + not tenant owner.
- RoleAssignmentGuard::assertAssignable(User $actor, iterable $roles) — validates each role's permission set ⊆ actor's permissions (prevents privilege escalation); throws ValidationException 422 if violated.

**Satellites (BE):**
- DTOs: EmployeeStoreData/EmployeeUpdateData (#[Required] name, email [unique except self], ?string password [Rule::requiredIf new email], is_active=true, roles: string[], ?EmploymentContractUpsertData employment, ?string first_name, ?string last_name, ?string phone, ?string position), EmployeeIndexFilterData (search?, role?, is_active?), EmployeeListItemData (id, name, email, first_name, last_name, phone, position, roles: string[], is_active, joined_at), EmployeeDetailData extends EmployeeListItemData + can: {edit_profile, assign_role, delete}), EmployeeRoleData (id, name).
- Enums: existing EmploymentContractTypeEnum reused.
- Factories: TenantMembershipFactory states withProfile() (sets first/last/phone/position), inactive().
- Seeders: RegistrationService + UserSeeder pass (no new seeder; role templates seeded at tenant bootstrap, EmployeeService handles creation).
- Policies: TenantMembershipPolicy (instance abilities require record-level tenant membership + active status checks).
- Controllers: EmployeeController with #[NavItem(label: 'app.employees', route: 'employees.index', icon: 'IdentificationIcon', group: 'default', order: 20)] · routes index/create/store/show/edit/update/destroy/deactivate (POST /employees/{employee}/deactivate) · role assignment POST /employees/{employee}/role (#[Authorize('assign employees')], returns EmployeeDetailData with can flags).
- Routes: GET|POST /employees, GET|PUT|DELETE /employees/{employee}, POST /employees/{employee}/deactivate, POST /employees/{employee}/role (Precognition on store/update/role).
- Permissions: ViewEmployees, CreateEmployees, EditEmployees, AssignEmployees, DeleteEmployees (seeded in RoleTemplatesSeeder). Users module coexists (nav in settings group order 15; Employees nav order 20).
- i18n: employee_{added,updated,deactivated,deleted,no_active}, employee_role.*, permission.* (per-permission labels), permission_group.*, roles table (Vlastník, Vedúca, Sekretárka, Účtovníčka, Interná upratovačka, Zákazník + order/phase labels).

**FE Satellites (phase 7-employees-schedule-fe plan):**
- Pages: Employees/{Index,Create,Edit,Show}.vue.
- Components: EmployeeForm (reuses EmploymentContractFields), EmployeeFiltersBar (search/role/is_active), PermissionCheckboxGroups, EmployeeStatusBadge, EmployeeRoleModal (assign role, displays can role-change + permission gating + escalation prevention), EmployeeDetailCard, EmployeeActionsCard.
- AppLayout.vue ICONS += IdentificationIcon. Nav: Employees top-level (phase 7, D1 user decision keeps Users in settings group, Employees in default group order 20).

**Flow:**
- List: filters (search, role, active status), sorts -joined_at. Create: new user (auto-verified, random password, InvitationCreated mail) or link existing (lookup by email, password required if not new). Show: profile + roles + employment contract + actions. Edit: profile + role (with escalation guard showing role options ⊆ actor's), permission overrides (actor can only grant perms they hold), employment contract edit/create. Deactivate: is_active=false (future jobs unassigned via JobService hook). Delete: hard-delete membership, keep User global identity.
- POST /employees/{employee}/role — specialized endpoint for Vedúca role-change authority (AssignEmployees permission); returns EmployeeDetailData with updated can flags, displays error 422 if escalation attempted.

**Depends on:** tenancy (TenantMembership tenant_id FK), identity (User model, email unique), roles/permissions (Spatie Permission per-tenant, RoleAssignmentGuard), contracts (EmploymentContract one-to-many morphMany relation on membership).

**Depended on by:** JobService::unassignFutureForMembership (phase 7 cleaner deactivation hook), EmployeeDetailData.can (FE action availability), Users module coexistence (both write tenant_memberships, Users nav in settings).

**If you change Core, check:**
- EmployeeService methods + controller actions + forms (EmployeeStoreData/EmployeeUpdateData, EmployeeDetailData).
- RoleAssignmentGuard assertions vs policy enforcement (both needed for escalation prevention).
- TenantMembership profile columns + LogsActivity logOnly fields.
- EmploymentContract morphMany relation + lifecycle hooks (deleting listener clears FK if orphaned).
- Post /employees/{employee}/role error responses (422 validation, 403 authorization).
- AppLayout nav order (Users 15 settings, Employees 20 default).

**Keywords (SK):** zamestnanec, člen tímu, rola, povolenie, pracovná zmluva, zákazka, aktivita, deaktivácia, eskalačná ochrana, priradenie role.

### schedule — Work breakdown + scheduled jobs, calendar view, cleaner assignment (Phase 7, 2026-09-06)

**Core:**
- App\Models\WorkBreakdown (rozpis prác) — UUIDv7; traits BelongsToTenant, HasFactory, HasUuids, LogsActivity (logOnlyDirty), SoftDeletes. Columns: tenant_id FK (restrictOnDelete), contract_id FK unique (restrictOnDelete → contract soft-delete blocks cascade anyway), timestamps, soft_deletes. Relations: contract BelongsTo (HasOne reverse via contract.work_breakdown relation), tasks HasMany WorkBreakdownTask (cascade delete), schedule HasMany ScheduledJob (via generated tasks?).
- App\Models\WorkBreakdownTask — child of WorkBreakdown; UUIDv7; traits HasFactory, HasUuids. Columns: work_breakdown_id FK (restrictOnDelete, cascade delete), name (255), description (text, nullable), frequency (TaskFrequencyEnum backed string 50, default 'one_time'), position (int, sort order), timestamps. NO soft_deletes, NO LogsActivity. Cascade delete on breakdown. Scopes: byFrequency(frequency). Methods: nextOccurrence(date): date helper (one_time → null, weekly_1x → add 7 days, seasonal → add 365 days), recurrenceLabel(): string (for display).
- App\Models\ScheduledJob (Zákazka, table `cleaning_jobs` to avoid framework `jobs` collision) — UUIDv7; traits BelongsToTenant, HasFactory, HasUuids, LogsActivity (logOnlyDirty), SoftDeletes. Columns: tenant_id FK (restrictOnDelete), cleaning_object_id FK (restrictOnDelete), assigned_membership_id FK (nullableOnDelete, nullable), work_breakdown_id FK (nullOnDelete, nullable), work_breakdown_task_id FK (nullOnDelete, nullable), type (JobTypeEnum, default 'regular'), status (JobStatusEnum, default 'unassigned'), scheduled_date (date), scheduled_time (time, nullable), notes (text, nullable), timestamps, soft_deletes. Indices: (tenant_id, status), (tenant_id, scheduled_date), (assigned_membership_id, status), (work_breakdown_id). Relations: cleaningObject BelongsTo (eager + loadMissing in list queries), assignedMembership BelongsTo TenantMembership (nullable, active+tenant scoped per ActorScoping), workBreakdown BelongsTo (nullable). Scopes: `scopeVisibleTo(Builder, User|TenantMembership $actor)` (own jobs if membership assigned OR cleaner permission missing); `isVisibleTo(User|TenantMembership $actor): bool` (predicate for single-row checks, replaces ->visibleTo filter). Methods: `canTransitionTo(JobStatusEnum $target): bool` (status matrix), `isEditable(): bool` (Unassigned|Planned only), `canAssign(): bool` (not Cancelled).
- App\Enums\TaskFrequencyEnum (8 backed string values: one_time, weekly_1x, weekly_2x, weekly_3x, weekly_4x, weekly_5x, weekly_6x, seasonal; #[TypeScript]). Methods: `monthsInterval(): int` (weekly → 0, seasonal → 12), `nextRunDate(date $from, date $limit): ?date` (calculates next occurrence within limit, applies recurrence math).
- App\Enums\JobStatusEnum (planned, unassigned, in_progress, completed, unapproved, cancelled; #[TypeScript]). Methods: `canTransitionTo(JobStatusEnum): bool` (state matrix), `isEditable(): bool` (Unassigned|Planned), label().
- App\Enums\JobTypeEnum (regular, one_off, special; #[TypeScript]). Methods: label().
- App\Services\WorkBreakdownService (final readonly, ctor JobService): `generateFromContract(Contract $contract): WorkBreakdown` — idempotent (exists check via contract_id unique), transaction: create WorkBreakdown + task per quote item (frequency from item.frequency or 'one_time' default), return fresh. Called synchronously by ContractSigned listener (afterCommit wired).
- App\Services\JobService (final readonly, ctor DatabaseManager): `paginate(Request, User $actor): LengthAwarePaginator<JobListItemData>` (QueryBuilder with eager loads object.client + assigned_membership.user, filters search object name/client name, status/type/assigned_member dynamic, scheduled_date range, applies scopeVisibleTo if actor provided); `create(JobStoreData, User $actor): ScheduledJob` — transaction: validation, scopeVisibleTo check, create job, return fresh; `update(ScheduledJob, JobUpdateData, User $actor): ScheduledJob` — guard isEditable() + scopeVisibleTo, update, return; `assign(ScheduledJob, ?string $membership_id): ScheduledJob` — guard canAssign() + scopeVisibleTo, update assigned_membership_id, auto-transition to Planned if was Unassigned, return; `cancel(ScheduledJob): ScheduledJob` — guard not Cancelled, set status→Cancelled, return; `complete(ScheduledJob): ScheduledJob` — guard in_progress, set status→Completed, return; `unapprove(ScheduledJob): ScheduledJob` — guard Completed, set status→Unapproved, return; `unassignFutureForMembership(TenantMembership $membership): int` — called by EmployeeService::deactivate, unassigns all future jobs assigned to membership (WHERE assigned_membership_id = ? AND scheduled_date > today), returns count updated.
- App\Listeners\GenerateWorkBreakdownFromSignedContract — subscribes to ContractSigned event, calls WorkBreakdownService::generateFromContract, dispatches GenerateScheduledJobsJob (afterCommit, phase 7 design: materialization deferred to daily cron, not immediate).
- App\Jobs\GenerateScheduledJobsJob (queued, ShouldBeUnique, retries 3, ShouldDispatchAfterCommit in listener) — binds tenant context + setPermissionsTeamId on worker, iterates active WorkBreakdown rows, calls JobService::generateScheduledJobsForBreakdown (rolling 30d horizon, creates ScheduledJob per task recurrence + assigned_date range, idempotent unique partial index on work_breakdown_task_id + scheduled_date per job).
- App\Console\Commands\GenerateScheduledJobsCommand (routes/console.php, daily `app:generate-scheduled-jobs`) — finds WorkBreakdown rows with next_run_at ≤ today (via recurring_invoices logic reuse OR direct job queries), dispatches GenerateScheduledJobsJob per row.
- Config: config/scheduling.php with horizon_days (default 30), excluded_dates (holidays).

**Satellites (BE):**
- DTOs: JobStoreData (#[Required, Uuid] cleaning_object_id, #[Required] scheduled_date: Date, ?string scheduled_time: Time, #[Required] type: JobTypeEnum, #[Nullable] notes, ?string contract_id UUID [Rule::exists...]), ScheduledJobIndexFilterData (object_id?, status?, type?, scheduled_date_from/to?, assigned_member?), JobListItemData (id, type, status, scheduled_date, scheduled_time, object_name, client_name, assigned_member_name, notes), ScheduledJobDetailData extends ListItemData + can (canAssign, canCancel, canComplete), ScheduledJobCalendarEventData (id, title, date, status, type, resource_id=assigned_membership_id), WorkBreakdownDetailData (id, contract_id/number, tasks: TaskData[]), TaskData (name, description, frequency, position).
- Enums: TaskFrequencyEnum, JobStatusEnum, JobTypeEnum (all #[TypeScript]).
- Factories: ScheduledJobFactory with states unassigned/planned/in_progress/completed, forObject(CleaningObject), forMembership(TenantMembership). WorkBreakdownFactory with forContract(Contract).
- Seeders: ScheduleDemoSeeder creates signed contract → breakdown → 30-day jobs, cleaner@example.com (Interná upratovačka role with 3 assigned jobs via EmployeeService).
- Policies: ScheduledJobPolicy (viewAny/view → ViewSchedule, create → CreateSchedule, update → EditSchedule, assign → AssignCleaners, delete/cancel → EditSchedule, all with scopeVisibleTo + isVisibleTo checks).
- Controllers: ScheduledJobController with #[NavItem(label: 'app.schedule', route: 'jobs.index', icon: 'CalendarDaysIcon', order: 32)] · index/create/store/show/edit/update/destroy · custom actions assign/cancel/complete/unapprove (POST routes) · GET /jobs/calendar (json endpoint, returns ScheduledJobCalendarEventData[], filters by date range ?from&to, ≤ 62 days per spec).
- Routes: GET|POST /jobs, GET|PUT|DELETE /jobs/{job}, POST /jobs/{job}/assign, POST /jobs/{job}/cancel, POST /jobs/{job}/complete, POST /jobs/{job}/unapprove, GET /jobs/calendar (Precognition on store/update/assign actions).
- Permissions: ViewSchedule, CreateSchedule, EditSchedule, AssignCleaners (seeded in RoleTemplatesSeeder). Interná upratovačka missing ViewAllSchedule → own-only via isVisibleTo.
- i18n: job_status.* (planned/unassigned/...), job_type.* (regular/one_off/special), task_frequency.* (one_time/weekly_1x/seasonal), schedule_* (add/edit/created/assigned/cancelled/), work_breakdown.*.

**FE Satellites (phase 7-employees-schedule-fe plan):**
- Pages: Schedule/{Index,Create,Edit,Show}.vue.
- Components: Schedule/{JobStatusBadge,JobTypeBadge,JobFiltersBar,JobList,JobCalendar (FullCalendar v6.1.21 month/week read-only, datesSet → useJobCalendar fetch, status colours), JobForm, JobAssignPanel (single select TenantMembership), WorkBreakdownView (read-only Rozpis prác section)}, Objects/{ObjectWorkBreakdownsCard (read-only breakdown on Objects/Show)}, TimeInput.vue (HH:MM input).
- Composables: useJobCalendar (calendar state, fetch via `GET /jobs/calendar?from&to`, updates event list).
- Index: calendar⇄list toggle (default list), filters (object, status, type, date range, assigned member), sorts. Show: detail + assign panel + cancel/complete/unapprove actions + read-only WorkBreakdownView (tasks from work_breakdown relation).
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
- JobStatusEnum state matrix (canTransitionTo rules) vs JobService action guards (assign/cancel/complete/unapprove).
- ScheduledJob::scopeVisibleTo/isVisibleTo actor logic vs EmployeeService cleaner role (missing ViewAllSchedule).
- CleaningObject::scopeVisibleTo reachability rule (ANY assigned job, regardless of status/date — D3 override explicit).
- WorkBreakdownService::generateFromContract idempotency (contract_id unique constraint enforces single breakdown per contract).
- GenerateScheduledJobsJob unique constraint (ShouldBeUnique via tenantId+work_breakdown_id binding; max_lock_attempts=3).
- config/scheduling.php horizon_days + excluded_dates integration (cron reads these; command queries next_run_at ≤ today).
- ScheduleDemo Seeder flow (contract → breakdown → jobs materialized, cleaner assigned, status badges colored).
- FullCalendar v6.1.21 toolbar + month/week event rendering (status color mapping via token).

**Keywords (SK):** rozvrh, zákazka, rozpis prác, úloha, frekvencia, pracovník, priradenie, plán, preukazovateľnosť, kalendár, bez archiovania.

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

56. **QuoteController::convertToContract gates via policy + signature confirmation (phase 7).** POST /quotes/{id}/convert-to-contract requires Policy::convertToContract (CreateContracts permission) + Accepted status + itemized + client + object + category fixed (no standalone, no document kind). Returns Draft service_agreement contract; not auto-signed. FE: Show page displays contractTemplates prop (R1 filters) + convert button (gated allows('create contracts')).

57. **RoleAssignmentGuard 422 escalation prevention (EmployeeService rule).** POST /employees/{id}/role (or store/update with role change) asserts actor's permissions ⊇ target role's permissions (via guard->assertAssignable, throws ValidationException role field). UI: role selector shows only roles ⊆ actor's permissions (e.g., Vedúca can only assign roles with ViewSchedule subset of her perms; cannot assign Vlastník). Guard prevents API escalation.

## Verification status

**Last full scan:** 2026-09-06 (Phase 7; degraded — Laravel Boost MCP unavailable; used docker compose exec + direct psql / grep).

**Last delta:** 2026-09-06 (Phase 7 complete: Employees + Schedule + Cleaner scoping. 912 tests, PHPStan [OK] (baseline −4 stale, +1 existing pattern), Pint clean. BE: EmployeeService (paginate/create/update/deactivate/reactivate over TenantMembership); TenantMembership extended (profile columns, LogsActivity); RoleAssignmentGuard (escalation prevention, 422). WorkBreakdown/WorkBreakdownTask/ScheduledJob models (table `cleaning_jobs`, BelongsToTenant, LogsActivity, SoftDeletes); TaskFrequencyEnum (8 values, phase 7 input), JobStatusEnum (6 states, canTransitionTo), JobTypeEnum (3 types); WorkBreakdownService::generateFromContract (idempotent via contract_id FK unique); JobService (paginate/create/update/assign/cancel/complete/unapprove/unassignFutureForMembership with actor-scoping). ScheduledJob::scopeVisibleTo/isVisibleTo (cleaner own-only, Interná upratovačka actor-scoped), CleaningObject::scopeVisibleTo/isVisibleTo (any assigned job reachability D3 override). Listener: GenerateWorkBreakdownFromSignedContract (ContractSigned → WorkBreakdownService::generate → GenerateScheduledJobsJob afterCommit). Job: GenerateScheduledJobsJob (queued ShouldBeUnique, rolling 30d horizon, partial unique index prevents duplicates). Command: GenerateScheduledJobsCommand (daily cron). Policies: TenantMembershipPolicy (rbac-full instance checks), ScheduledJobPolicy (rbac-full + scopeVisibleTo). Controllers: EmployeeController (#[NavItem] IdentificationIcon order 20), ScheduledJobController (#[NavItem] CalendarDaysIcon order 32), both with #[Authorize] + Precognition. Routes: /employees/{id}/role, GET /jobs/calendar. Config: config/scheduling.php. Seeders: ScheduleDemoSeeder. DTOs: Employee* (5), ScheduledJob* (4), WorkBreakdown*; enums (3 new); factories (3). Updates: Quote::contracts() HasMany (phase 6), convertToContract (Accepted itemized), QuoteItem.frequency (TaskFrequencyEnum, phase 5 unused until 7); TenantMembership: profile fields + LogsActivity; User.password nullable (invitation flow); UserService: RoleAssignmentGuard on create/update. FE: Pages Employees/{Index,Create,Edit,Show}, Schedule/{Index,Create,Edit,Show}; components (JobStatusBadge, JobTypeBadge, JobFiltersBar, JobList, JobCalendar FullCalendar v6.1.21, JobForm, JobAssignPanel, WorkBreakdownView, TimeInput, ObjectWorkBreakdownsCard, EmployeeForm, EmployeeFiltersBar, EmployeeStatusBadge, EmployeeRoleModal); composables useJobCalendar; AppLayout ICONS += IdentificationIcon, CalendarDaysIcon; enums utils (6 new fn); i18n +80 keys sk/en/uk (employee_*, job_*, task_frequency_*, schedule_*, work_breakdown_*, permission_*, employee_role_*). Tests: 154 new (EmployeeService/Policy/Controller, JobService/Policy/Controller, GenerateScheduledJobs{Job,Command}, WorkBreakdownService, Quote/Contract extensions) → 912 total across 7 new Feature/* files + 7 phase 7 domain modules.)

**Certainty audit:**
- All relationships verified by: live route:list (php artisan route:list), migration files + docker exec postgres psql, grep of every cited callsite + direct reads (git show d7cb13c — BE schedule/employees implementation commit).
- Phase 7 employees verified: EmployeeService methods (paginate/create/update/deactivate), TenantMembership profile + LogsActivity, RoleAssignmentGuard on UserService, TenantMembershipPolicy instance checks, EmployeeController routes, InvitationCreated mail (new user), RoleAssignmentGuard 422 on escalation, EmployeeDetailData.can flags, UsersModule coexistence (nav order 15 settings vs 20 default).
- Phase 7 schedule verified: WorkBreakdown/WorkBreakdownTask/ScheduledJob models exist, enums (TaskFrequencyEnum 8 values + recurrence helpers, JobStatusEnum 6 states + state matrix, JobTypeEnum 3 types), ScheduledJob::scopeVisibleTo + CleaningObject::scopeVisibleTo (any job reachability D3), JobService methods, ScheduledJobPolicy (rbac-full + scopeVisibleTo), ScheduledJobController routes + #[NavItem], GenerateWorkBreakdownFromSignedContract listener (sync), GenerateScheduledJobsJob (queued ShouldBeUnique, rolling 30d), GenerateScheduledJobsCommand (daily cron), config/scheduling.php (horizon_days), ScheduleDemoSeeder (contract → breakdown → jobs + cleaner assignment), FullCalendar v6.1.21 calendar component, useJobCalendar composable, TimeInput component, WorkBreakdownDetailData.tasks array on Objects/Show.
- Open TODO verify:
  1. ~~AllowedFilter dead references~~ — PHPStan clear (baseline 190 2026-09-06, −4 stale).
  2. ~~Layouts/DataTable.vue + Composables/useFilters.ts~~ — 0 consumers; kept.
  3. Cross-field #[Validation] rules (quote clientless, contract term/end_date, employee role escalation) under Precognition-Validate-Only partial payloads — behavior verified in phase 7 tests (EmployeeRoleModalTest, EmployeeUpsertTest); known trade-off for partial-payload support (full-payload rules execute on submit, not blur).
