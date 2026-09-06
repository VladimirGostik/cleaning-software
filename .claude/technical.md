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
- App\Models\Tenant — UUIDv7; traits HasFactory, HasUuids, LogsActivity (logOnly owner_id,name,ico,dic,vat_number,is_vat_payer,vat_rate,iban,is_active), SoftDeletes. Columns: owner_id FK → users (restrictOnDelete), name, ico (nullable), dic (nullable), vat_number (nullable), is_vat_payer (bool), vat_rate (decimal), iban (nullable), swift_bic (nullable), invoice_number_format, registration_info, address_line, city, postal_code, country, contact_email, contact_phone, is_active, timestamps, soft_deletes. Relations: owner BelongsTo User, interface HasOne TenantInterface, memberships HasMany TenantMembership, members BelongsToMany User (via tenant_memberships, pivot is_active/joined_at).
- App\Models\TenantMembership — UUIDv7 pivot; traits HasFactory, HasUuids, LogsActivity (logOnly is_active,position,first_name,last_name). Columns: user_id FK (restrictOnDelete), tenant_id FK (restrictOnDelete), is_active (bool), joined_at (timestamp), first_name (nullable), last_name (nullable), phone (nullable), position (nullable), timestamps. Unique (user_id, tenant_id); indices (tenant_id, is_active), (user_id). NOT BelongsToTenant (the pivot defines tenancy itself). Relations: user BelongsTo User, tenant BelongsTo Tenant.
- App\Models\TenantInterface — UUIDv7 settings per tenant (D1); traits HasUuids, LogsActivity (logOnly color). Columns: tenant_id FK unique (restrictOnDelete), color (TenantColorEnum nullable, integer in DB), timestamps. Will add phase 4: invoice_template, constant_symbol, payment_type, currency, rounding_mode (on port). Relation: tenant BelongsTo.
- App\Models\TenantInvitation — UUIDv7; traits BelongsToTenant, HasFactory, HasUuids, LogsActivity (logOnly email,role_name,status,expires_at,accepted_at, never token), SoftDeletes. Columns: tenant_id FK (restrictOnDelete), invited_by_user_id FK (nullOnDelete), email, role_name (100), token (64 unique), status (InvitationStatusEnum), expires_at, accepted_at (nullable), timestamps, soft_deletes. Indices (tenant_id, status); partial unique (tenant_id, email) WHERE deleted_at IS NULL AND status='pending'. Relations: invitedBy BelongsTo User, tenant BelongsTo (inherited scope). Methods: isAcceptable() (checks expiry + pending status), markAccepted().
- App\Services\RegistrationService (final readonly) — `createOwner(name, email, password, companyName, ico): User` → DB::transaction, User→Tenant→TenantInterface→TenantMembership→RoleTemplatesSeeder→Admin role assignment. `addTenant(User, AddTenantData): Tenant` → bootstrapTenant + optional invitation create. `private bootstrapTenant(User, name, ico, color?): Tenant` — atomically seeds Tenant + interface + membership + team-scoped role bundle.
- App\Services\InvitationAcceptService — `resolve(token): TenantInvitation` (withoutGlobalScope), `accept(invitation, AcceptInvitationData, skipPasswordCheck?): User` — transaction: abort if non-acceptable (410), hash-check existing user (or create+verify new user), setPermissionsTeamId(invitation.tenant_id), create/reactivate membership, assign role, markAccepted, return user. **Critical:** role lookup happens inside the invitation's tenant context, not session tenant.
- App\Services\RoleAssignmentGuard (final readonly) — `assertAssignable(User $actor, iterable<Role> $roles): void` — validates each role's permission set ⊆ actor's permissions; throws ValidationException if violated (privilege escalation guard for UserService::create/update).
- App\Http\Middleware\TenantContextMiddleware — app middleware (appended after LocaleMiddleware, before HandleInertiaRequests). `handle` resolves tenantId per D5: X-Tenant-Id header (if uuid + active membership, else 403 `tenant_forbidden`) → session active_tenant_id → first active membership. If resolved: `app()->instance('current_tenant_id', $id)`, `setPermissionsTeamId($id)`, session bind. Unauth users pass through (no binding).
- App\Http\Middleware\RequireActiveTenant — route middleware (`tenant.required`). Authenticated + no bound tenant → web: `Auth::logout()`, invalidate session, redirect login with error flash; API: 403 JSON `no_active_tenant`. Logout route still reachable (not gated).
- App\Scopes\TenantScope — global scope on BelongsToTenant models. When `app()->bound('current_tenant_id')`: `where tenant_id = ?`; else unfiltered (D6: console/jobs/seeders run intentionally unbound).
- App\Concerns\BelongsToTenant — trait: `bootBelongsToTenant()` adds TenantScope + creating hook fills tenant_id from container; `tenant(): BelongsTo<Tenant,$this>`.

**Satellites (BE):**
- DTOs: Tenants/{AddTenantData, TenantListItemData}, Invitations/{AcceptInvitationData, InvitationAcceptPageData}, Auth/MeData, PermissionGroupData (grouped permission list for forms).
- Enums: PermissionEnum (53 backed string cases, #[TypeScript]), TenantColorEnum (8 hex, #[TypeScript]), InvitationStatusEnum (pending/accepted/revoked/expired), InvitationAcceptStateEnum (4 states for FE: expired/wrong_user/existing_user/new_user), SupportedLanguage (now sk/en/uk with `getDefault()`, `getDisplayName()`, `isSupported()`).
- Factories: TenantFactory (forOwner), TenantMembershipFactory (withProfile, inactive states), TenantInvitationFactory (accepted, expired, revoked states), TenantInterfaceFactory.
- Seeders: PermissionSeeder (global catalogue from PermissionEnum), RoleTemplatesSeeder (per-tenant role bundles: Admin=all, Vedúca, Sekretárka, Účtovníčka, Interná upratovačka, Zákazník; `seedForTenant(Tenant)` idempotent, called post-bootstrap).
- Policies: TenantPolicy::switchTo(User, Tenant) = active membership + active tenant. Deliberately no `create` (D4a exception: new tenant has no roles yet → circular RBAC).
- Notifications: InvitationCreated (ShouldQueue, afterCommit, #[Tries(3)], mail-only, token + tenant_name + role_name in body).
- Commands: CreateOwner (interactive or flag-based, validates, calls RegistrationService::createOwner).
- Routes: POST /tenants (auth-only, no policy — D4a exception, documented), POST /tenants/{tenant}/switch (TenantPolicy), GET|POST /invitations/{token} (guest, throttle:invitation-accept on POST), GET /api/me (auth:sanctum, returns MeData with active tenant + permissions per team scope).
- Middleware: TenantContextMiddleware (app-level, D5 resolution), RequireActiveTenant (route `tenant.required`), plus bootstrap/app.php registers aliases.

**FE Satellites (apply via FE agent plan phase-2-tenancy-fe.md):**
- Composables: usePageProps (ComputedRef<SharedProps>), useAuthorization (allows(PermissionEnum)), useTenantTheme (themeStyle for --color-primary override).
- Components: TenantColorDot, TenantSwitcher (dropdown), AddTenantModal, ColorSwatchPicker, Can (permission guard).
- Pages: Invitations/Accept (4 states: expired/wrong_user/existing_user/new_user), Users/Index/Form (tenant members only, updated filters/forms).
- Shared props: tenant{active,available}, tenantColors, can (camelCase keys per PermissionEnum::sharedKey).

**Flow:**
- **Create owner (bootstrap):** `php artisan app:create-owner` → prompts name/email/password/company/ico → RegistrationService::createOwner → User + Tenant + TenantInterface + TenantMembership + seeded roles + Admin role assigned → logs "created successfully". Bootstrap flow only.
- **Add tenant:** Authenticated user (any) POST /tenants (AddTenantData: name, ico, color optional, copy_settings bool, leader_email optional) → RegistrationService::addTenant → new Tenant (owned by actor) → TenantInterface → active membership (actor + Admin role) → optional InvitationCreated mail to leader_email → session switch to new tenant → redirect dashboard. No policy gate (D4a).
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
User, Role (logOnlyDirty, dontLogEmptyChanges), LogAuthenticationActivity (3 events). Morph columns uuid. On port: enable on business models (Client, Object, Quote, Invoice, RecurringInvoice, Contract, EmploymentContract, ScheduledJob, TenantMembership).

### MediaLibrary
One collection (TemporaryUpload::default). Disk public by default (MEDIA_DISK). media.model_id uuid. On port: add Quote.document media (private disk).

### AllowedFilter + DataTable query contract
App\Utils\AllowedFilter extends Spatie AllowedFilter: search([...cols]), contains, dynamic (→ SymbolOperatorFilter, operators != <= >= ~ < > = between:), relationExact(name, relation, column), callbackClean, callbackWithOperator. Validators uuid()/date()/isoDateTime()/integer()/numeric()/boolean() log-and-skip. ILIKE on pgsql. FE counterpart Composables/useSpatieTableQuery.ts + Components/DataTable/* (filter[x]=<opPrefix><value> URL contract). Operator prefixes (filterOperators.ts ↔ SymbolOperators::parse, keep in sync): ~: contains, !=:, <:, <=:, >:, >=:, between: (from,to), none = '='. Operator sets by FilterType: string/text ~ = !=; number = != < <= > >= between; boolean = (tri-state); date/datetime = < <= > >= between; enum/select/autocomplete = != (+multiple → comma). Sub-components TableFilters, TableFilter, TableSearch (400 ms debounce → filter[search]), TablePagination (emits page, perPage; 10/25/50/100).

### Precognition + Forms
HandlePrecognitiveRequests around every POST|PUT mutation (web + api). AppServiceProvider::register (:25-27) binds PrecognitiveDataValidatorResolver (honours Precognition-Validate-Only), short-circuits DTO resolution during precognitive requests. FE: useForm(method, url, data) (Inertia 3 method-bound, Precognition built-in) inside FormProvider → form.submit() → BE store|update(DTO) → 422 → form.errors Record<field,string> | 204 + redirect with flash → toast.

### Spatie Data DTO boundary
DTOs injected as controller params, #[TypeScript] on all → resources/js/types/generated.d.ts (App.Data.*, App.Enums.*) via TypeScriptTransformerServiceProvider (app/Data + app/Enums, GlobalNamespaceWriter). DataValidatorResolver singleton swapped — custom resolvers must extend PrecognitiveDataValidatorResolver.

### Navigation (BE-driven discovery)
App\Navigation\NavItem attribute (repeatable, method-level: label, route, icon, permission, policyModel, group, order) discovered by NavigationRegistry via router reflection; visibility Gate::forUser()->allows(permission, policyModel) or $user->can (:79-89); groups NavigationRegistry::GROUPS (settings). Shared as navigation: NavigationItemData[], rendered Layouts/AppLayout.vue:215. FE: hardcoded ICONS map (HomeIcon, UsersIcon, ShieldCheckIcon, ClipboardDocumentListIcon, PhotoIcon, EnvelopeIcon, UserCircleIcon, Cog6ToothIcon; unknown → HomeIcon) — every new NavItem icon must be added. translateLabel strips app. prefix then t(). Active = page.url.startsWith(href). Nav links plain <a> (full page load). On port: add NavItems for clients, objects, quotes, contracts, invoices, recurring invoices, schedule, employees, notifications + extend ICONS map.

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

**Phase 3+ (clients, objects, quotes, invoices, etc):**
- (placeholder; tenant-scoped CRUD pages per domain modules).

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

## Verification status

**Last full scan:** 2026-09-06 (Phase 1 initial; degraded — Laravel Boost MCP unavailable; used docker compose exec + direct psql / grep instead).

**Last delta:** 2026-09-06 (Phase 2 tenancy complete: 128 files changed, 275 tests, PHPStan baseline 188 entries [OK], Pint clean, `pnpm lint:js`/prettier/vue-tsc/build green; browser: tenant switcher, add-tenant modal, switch + colour override, users isolation, uk locale verified; FE gotchas 1/2/5/6/7/8/9/10 resolved, new 10/11 noted).

**Certainty audit:**
- All relationships verified by: live route:list output (php artisan route:list), migration files + docker exec postgres psql schema queries, grep of every cited callsite + direct reads.
- Phase 2 tenancy verified by: Tenant/TenantMembership/TenantInterface/TenantInvitation models exist (app/Models/), TenantScope + BelongsToTenant traits, PermissionEnum with 53 cases (app/Enums/PermissionEnum.php), RoleTemplatesSeeder seeding 6 roles per tenant, Activity.php custom tenant_id hook, Media.php tenant_id NOT NULL + scopeInTenant, TenantContextMiddleware D5 resolution, RequireActiveTenant login guards (D4), Inertia shared props collapse (types/index.d.ts single SharedProps), usePageProps/useAuthorization/Can components (resources/js/Composables + Components), FE AddTenantModal/TenantSwitcher/TenantColorDot, 275 tests (Feature/Tenancy/{TenantScope,PermissionTeam,TenantContext,RequireActiveTenant,TenantController,InvitationAccept,RoleTemplatesSeeder} + updated {Auth,Users,Roles,AuditLogs,Media,Language} tests).
- Open TODO verify (from skeleton):
  1. ~~AllowedFilter dead references~~ — PhPStan clear (2026-09-06, baseline 188).
  2. ~~Layouts/DataTable.vue + Composables/useFilters.ts~~ — 0 consumers; skeleton code kept (unused, no plan to use).
  3. Cross-field #[Validation] rules (quote clientless prohibits, contract term/end_date conditionals) under Precognition-Validate-Only partial payloads — deferred Phase 3+ when domain models added; rule behavior TBD.
