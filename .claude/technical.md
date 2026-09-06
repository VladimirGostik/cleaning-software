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

### PDF generation (spatie/laravel-pdf, chrome driver via Alpine apk) — Phase 4

**Backend:**
- `config/laravel-pdf.php` (published + configured): driver `chrome` (env LARAVEL_PDF_DRIVER), chrome_binary `env('CHROMIUM_PATH', '/usr/bin/chromium-browser')`, no_sandbox true, custom_flags `['--disable-gpu', '--disable-dev-shm-usage']`, timeouts (30s startup, 30000ms, 5000ms operation).
- `spatie/laravel-pdf` with `chrome-php/chrome` (pure-PHP DevTools, no Node at runtime).
- Alpine Dockerfile: `apk add chromium nss freetype harfburst ca-certificates ttf-dejavu font-noto`, smoke test `chromium-browser --headless --version`.
- Contracts: `RendersInvoicePdf { public function render(Invoice $invoice): string; }` (testable via mock).
- Services: `InvoicePdfService` renders `resources/views/pdf/invoices/{classic,modern,minimal}.blade.php` via chrome driver.
- Routes: `GET /invoices/{invoice}/pdf` (Content-Disposition: attachment, filename sanitised via `Invoice::pdfFilenameBase` + `HeaderUtils::makeDisposition`).
- Tests: mocked via `$this->mock(RendersInvoicePdf::class)` (no real Chromium in test DB).

**Frontend:**
- Link: `<a :href="`/invoices/${id}/pdf`" target="_blank">` (native browser download).

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

### invoices — Draft / issue / pay / cancel + credit notes, SK VAT, Pay-by-Square QR (Phase 4, 2026-09-06)

**Core:**
- `App\Models\Invoice` — UUIDv7; traits BelongsToTenant, HasFactory, HasUuids, LogsActivity (logOnlyDirty status/number/total/client_id/issued_at/paid_at/cancelled_at/sent_at), SoftDeletes. Columns: tenant_id FK, nullable client_id FK → clients (withTrashed), nullable cleaning_object_id FK → objects (withTrashed), nullable recurring_invoice_id FK → recurring_invoices (restrictOnDelete), nullable credited_invoice_id self-FK (restrictOnDelete), type (InvoiceTypeEnum: monthly/one_off/special), status (InvoiceStatusEnum: draft/issued/paid/overdue/cancelled), template (InvoiceTemplateEnum: classic/modern/minimal), nullable number (50 chars, partial unique index tenant_id + number WHERE deleted_at IS NULL AND number IS NOT NULL), variable_symbol (10 chars, derived from number digits only), dates (period_from/period_to/issue_date/delivery_date/due_date nullable except issue_date), timestamps issued_at/sent_at/paid_at/cancelled_at nullable, **customer snapshot** (customer_name required, representative/ico/dic/vat_number/street/city/postal_code/country/email nullable), **object snapshot** (object_name/street/city/postal_code nullable), **supplier snapshot** (supplier_name required, ico/dic/vat_number/iban/swift/address_line/city/postal_code/country/contact_email/contact_phone/registration_info nullable, snapshot at issue from Tenant), bool is_vat_payer, nullable vat_rate (decimal), money columns (subtotal/vat_amount/total/deposit/rounding_amount default 0, all decimal:12,2), json vat_breakdown (nullable, frozen at issue), **SK fields** (constant_symbol/specific_symbol/payment_type/currency/rounding_mode/header_text/footer_text all nullable except currency EUR default), nullable note, reserved efa_status/efa_id (spec: IS EFA phase 8+), timestamps, soft_deletes. Indexes (tenant_id,status), (tenant_id,due_date), (tenant_id,issue_date), (tenant_id,client_id), recurring_invoice_id, credited_invoice_id. Relations: `client()->withTrashed()`, `cleaningObject()->withTrashed()`, `items(): HasMany<InvoiceItem>` ordered position, `creditedInvoice(): BelongsTo`, `creditNote(): HasOne credited_invoice_id`, `recurringInvoice(): BelongsTo`. Methods: `balanceDue(): Attribute` (total - deposit float), `isEditable(): bool` (Draft), `canBeCancelled(): bool` (Issued | Overdue).
- `App\Models\InvoiceItem` — UUIDv7; traits BelongsToTenant, HasFactory, HasUuids. Columns: tenant_id FK, invoice_id FK (restrictOnDelete), description, qty/unit/unit_price (decimal:10,2), discount_percent (decimal:5,2), vat_rate (decimal:5,2), computed line_base/line_vat/line_total (decimal:12,2), position (smallInt). Index invoice_id. Relation: `invoice(): BelongsTo`.
- `App\Models\InvoiceNumberSequence` — UUIDv7; traits BelongsToTenant, HasUuids. Columns: tenant_id FK, year (smallInt), last_number (int), timestamps. Unique (tenant_id, year). No activity logging (operational).
- `App\Services\InvoiceNumberService` — `next(Tenant, DateTimeInterface): string` (firstOrCreate + lockForUpdate + collision loop honouring `{YYYY}/{YY}/{MM}/{X+}` format placeholders), `variableSymbol(string): ?string` (digits max 10 chars).
- `App\Services\InvoiceService` (final readonly, ctor InvoiceNumberService, DatabaseManager) — `paginate(Request): LengthAwarePaginator<InvoiceListItemData>` (QueryBuilder AllowedFilter search/number/status/type/client_id/customer_name/issue_date/due_date/total/created_at, sorts default -created_at, eager load client), `stats(): InvoiceStatsData` (issued this month / overdue / pending / recurring monthly), `create(InvoiceUpsertData): Invoice` (transaction: snapshot customer/object/supplier + items + computeTotals with VAT breakdown + rounding), `update(Invoice, InvoiceUpsertData): Invoice` (guard isEditable), `issue(Invoice, InvoiceIssueData): Invoice` (guard Draft, tenant-scoped number exists check, set number/variable_symbol/status/issued_at), `markPaid(Invoice): Invoice` (guard canTransitionTo(Paid)), `cancel(Invoice): Invoice` (transaction: mark Cancelled + create credit note with negated items/SK fields), `duplicate(Invoice): Invoice` (Draft copy, no number), `delete(Invoice): void` (guard isEditable), `send(Invoice): void` (guard Issued + customer_email, dispatch InvoiceIssued queued notification).
- `App\Services\InvoiceSettingsService` — `update(Tenant, InvoiceSettingsData): void` (transaction: write tenant supplier + invoicing fields, write interface invoice defaults).
- Contracts: `RendersInvoicePdf { public function render(Invoice $invoice): string; }`, `GeneratesPaymentQr { public function dataUri(Invoice $invoice): ?string; }`.
- `App\Services\Pdf\InvoicePdfService implements RendersInvoicePdf` — chrome driver, Blade-based 3 templates (Classic/Modern/Minimal).
- `App\Services\Pdf\PayBySquareService implements GeneratesPaymentQr` — null unless Issued|Overdue + IBAN + EUR + balance_due > 0.
- `App\Notifications\InvoiceIssued` — queued (ShouldQueue, #[Tries(3)] #[Backoff] #[Timeout(120)]), afterCommit, mail-only, PDF attachment via RendersInvoicePdf, sent_at stamped by StampInvoiceSentAt listener on NotificationSent event.
- `App\Events\InvoiceMarkedOverdue` — domain event dispatched by MarkOverdueInvoices command (no phase-4 listeners; phase-5 notifications subscribes).
- `App\Console\Commands\MarkOverdueInvoices` — daily cron, flips Issued past due_date → Overdue, dispatches InvoiceMarkedOverdue.

**Satellites (BE):**
- Enums: InvoiceStatusEnum (draft/issued/paid/overdue/cancelled, `canTransitionTo(self $to): bool`), InvoiceTypeEnum (monthly/one_off/special), InvoiceTemplateEnum (classic/modern/minimal, `view(): string`), PaymentTypeEnum (transfer/cash/card/cod/other), CurrencyEnum (EUR/CZK/USD uppercase), RoundingModeEnum (none/document/cash_005, `round(float): float`).
- DTOs: `InvoiceUpsertData` (client_id nullable, object_id nullable, type/template/dates, customer_* snapshot fields, items, SK fields, payment fields, deposit), `InvoiceItemData` (description/qty/unit/unit_price/discount/vat_rate + line_* nullable), `InvoiceIssueData { ?string $number }`, `InvoiceListItemData` (id/number/status/type/customer_name/client_id/client_name/object_name/currency/total/balance_due/issue_date/due_date), `InvoiceDetailData` (full DTO + vat_breakdown array + qr_data_uri), `InvoiceSupplierData`, `VatBreakdownLineData`, `InvoiceStatCardData`, `InvoiceStatsData`, `InvoiceFormContextData` (clients/objects/is_vat_payer/defaults/recurring_default_state), `InvoiceSettingsData` (supplier name/ico/dic/vat_number/is_vat_payer/address/contact + invoice defaults template/number_format + recurring defaults), `ObjectOptionData` (for pickers).
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

**Depends on:** tenancy (tenant_id FK, BelongsToTenant, TenantScope), clients (client_id FK + rule, withTrashed relation), objects (cleaning_object_id FK + rule, withTrashed relation), recurring-invoices (recurring_invoice_id FK).

**Depended on by:** recurring-invoices (InvoiceService consumed by RecurringInvoiceService::generateInvoiceFromTemplate), contracts phase 4 (convertToInvoice backlink quote_id deferred phase 5).

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
- `App\Jobs\GenerateRecurringInvoiceJob` (ShouldQueue, ShouldBeUnique) — queued from GenerateRecurringInvoices command, `uniqueId() = recurring_id`, `uniqueFor() = 3600`, idempotency guard (isRunnable && next_run_at ≤ today), binds `app()->instance('current_tenant_id')`, transaction: generate via service, auto-issue if flag or tenant default, advance next_run_at/occurrences_generated/last_generated_at, mark Completed on limit/end-date.
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

## Verification status

**Last full scan:** 2026-09-06 (Phase 1 initial; degraded — Laravel Boost MCP unavailable; used docker compose exec + direct psql / grep instead).

**Last delta:** 2026-09-06 (Phase 4 invoicing complete: 544 tests, PHPStan [OK] +2 baseline entries for existing PendingCommand pattern, Pint clean, FE lint/typecheck/build green, real Chromium PDF verified 37 KB, browser-verified create → issue auto-number FA-2026-0001 + PDF download, queue service running. BE: invoices CRUD with snapshot customer/object/supplier data, SK fields (payment_type/currency/rounding_mode/constant_symbol), per-item VAT rate + discount + line breakdown, deposit + balance_due, credit notes (negated items + own number, two-way linked), per-tenant per-year numbering with lockForUpdate + manual override, status lifecycle (draft/issued/paid/overdue/cancelled) with canTransitionTo matrix, softDeletes, full Spatie permission gating (view/create/edit/cancel invoices, manage billing settings). RecurringInvoice template-driven auto-generation on rolling 30-day horizon via daily `GenerateRecurringInvoices` command → `GenerateRecurringInvoiceJob` (ShouldBeUnique, idempotent), 3-way termination (forever/until_date/count), auto-issue flag or tenant default state (draft/issued). InvoiceSettings supplier identity (name/ico/dic/vat_number/address/contact) + invoice defaults (template/number_format/iban/swift_bic/vat_rate/registration_info) + recurring defaults, per-tenant defaults propagated to new invoice forms. Money math mirrors InvoiceService::computeTotals exactly (line_base formula, vat_breakdown grouping, rounding modes: none/document/cash_005). PDF generation via spatie/laravel-pdf chrome driver (Chromium from Alpine apk /usr/bin/chromium-browser, 3 Blade templates classic/modern/minimal), mocked in tests. Mail via queued InvoiceIssued notification (3 retries, 120s timeout, PDF attachment, sent_at stamped by listener). Pay-by-Square QR via engazan/pay-by-square + bacon/bacon-qr-code (EUR-only, balance_due). FE: Items editor as card-per-row custom field (FormProvider-aware, Precognition on change), totals live in one place (useInvoiceTotals composable + InvoiceTotalsPanel component, stacked on mobile), subject modes via RadioGroup (client/object/standalone), confirm modals via useDeleteConfirm + ConfirmDeleteModal (`confirmVariant` prop for success/warning), InvoiceIssueModal (Precognition form), InvoiceStatsCards (4 cards), InvoiceTemplatePicker with preview iframe + thumbnail SVG. Pages: Invoices/{Index,Create,Edit,Show}, RecurringInvoices/{Index,Create,Edit,Show}, Settings/Invoicing. Queue service runs `queue:work`, supervisor scheduler (prod) runs `schedule:work`. Mailpit service available at http://localhost:8025 (mail infrastructure ready). Permissions: ViewInvoices/CreateInvoices/EditInvoices/CancelInvoices + ViewRecurringInvoices/CreateRecurringInvoices/EditRecurringInvoices/DeleteRecurringInvoices + ManageBillingSettings (owner only). i18n: 80+ keys (enum labels, nav, flash, errors, PDF, mail, FE component labels). Tests: 7 files Invoices/*, 4 files RecurringInvoices/*, 1 file Settings/; Invoices suites cover service (CRUD/snapshot/statuses/math/rounding), issue/cancel/duplicate/pay/send/PDF/QR, stats/filters, form context, overdue cron, Precognition cross-field rules; RecurringInvoices suites cover service lifecycle, frequency logic, job idempotency, command scheduling, customer display (N+1 safe); Settings tests cover read/write, defaults propagation, validation (format, IBAN, SWIFT, enum values), number format preview).)

**Certainty audit:**
- All relationships verified by: live route:list output (php artisan route:list), migration files + docker exec postgres psql schema queries, grep of every cited callsite + direct reads.
- Phase 2 tenancy verified by: Tenant/TenantMembership/TenantInterface/TenantInvitation models exist (app/Models/), TenantScope + BelongsToTenant traits, PermissionEnum with 53 cases (app/Enums/PermissionEnum.php), RoleTemplatesSeeder seeding 6 roles per tenant, Activity.php custom tenant_id hook, Media.php tenant_id NOT NULL + scopeInTenant, TenantContextMiddleware D5 resolution, RequireActiveTenant login guards (D4), Inertia shared props collapse (types/index.d.ts single SharedProps), usePageProps/useAuthorization/Can components (resources/js/Composables + Components), FE AddTenantModal/TenantSwitcher/TenantColorDot, 275 tests (Feature/Tenancy/{TenantScope,PermissionTeam,TenantContext,RequireActiveTenant,TenantController,InvitationAccept,RoleTemplatesSeeder} + updated {Auth,Users,Roles,AuditLogs,Media,Language} tests).
- Open TODO verify (from skeleton):
  1. ~~AllowedFilter dead references~~ — PhPStan clear (2026-09-06, baseline 188).
  2. ~~Layouts/DataTable.vue + Composables/useFilters.ts~~ — 0 consumers; skeleton code kept (unused, no plan to use).
  3. Cross-field #[Validation] rules (quote clientless prohibits, contract term/end_date conditionals) under Precognition-Validate-Only partial payloads — deferred Phase 3+ when domain models added; rule behavior TBD.
