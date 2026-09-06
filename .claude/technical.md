<!-- inogile:context-version=3 -->
# Technical relationship map — cleaning-software @ rebuild (vue-skeleton fork)

## Project type

laravel-be + inertia-fe

## Stack snapshot

**Backend:** Laravel 13.7 (PHP 8.5) + Inertia 3.0.6 (Vue 3 + TS + Tailwind 4 + DaisyUI 5) · Spatie Data 4.22 / Permission 7.4.1 (teams OFF, will enable teams=true, team_foreign_key=tenant_id on port) / Activitylog 5.0 (uuid morphs) / MediaLibrary 11.22 / QueryBuilder 7.2 / TypeScript-Transformer 3 · Sanctum 4.3.2 (Bearer API + uuidMorphs fix applied 2026-09-06) · Scribe 5.9 · Postgres 16 (compose) · PHPUnit 12 on Postgres `cleanmaster_admin_testing` (phpunit.xml force=true, init SQL docker/postgres/init-testing-db.sql) · 181 tests · Docker: `docker compose exec app php artisan …`

**Frontend:** @inertiajs/vue3 ^3.0.3 + Vue ^3.5.33 + TS ^6 strict + Vite 8 + laravel-vite-plugin ^3 · NO Pinia (none today; will add for notifications/capabilities polling on port) · NO Ziggy (0 route() calls; all URLs string literals) · i18n = vue-i18n ^11 (legacy:false), messages bundled from resources/lang/{sk,en}/app.json + locale from Inertia prop (sk default, will add uk on port) · Theme: DaisyUI 5 themes:false + [data-theme='app-theme'] OKLCH token block in resources/css/app.css; Instrument Sans via bunny.net · Icons @heroicons/vue · Dates date-fns (utils/date.ts) · RichTextEditorInput @tiptap · ESLint 10 flat (no ref ban yet), Prettier, Lefthook pre-commit + pre-push vue-tsc. No FE test runner.

## Domains

### identity — users, roles, permissions, self-service profile

**Core:**
- App\Models\User — UUIDv7 via App\Concerns\HasUuids; traits HasRoles, HasApiTokens, LogsActivity (logOnly name,email,locale,is_active), Notifiable, CanResetPassword. Columns: name, email(unique), password, locale(2, default sk), is_active(bool, default true), email_verified_at. No SoftDeletes. Will add on port: tenant_id UUID FK, BelongsToTenant trait, TenantScope global scope.
- App\Models\Role extends Spatie Role — HasUuids + LogsActivity + scopeSearch(Builder, string). App\Models\Permission extends Spatie Permission — HasUuids. Both registered config/permission.php:13-15. On port: migrate to teams=true (tenant_id UUID instead of team_id bigint).
- App\Services\UserService (final readonly): create(CreateUserData): User · update(User, UpdateUserData): User · delete(User): void — each DB::transaction, syncRoles. Will add: setPermissionsTeamId($tenantId) before role lookups.
- App\Services\RoleService: const SYSTEM_ROLES=['admin'] · create(string $name, array $permissions): Role · update(Role, string, array): Role · delete(Role): void · getPermissionsGrouped(): array (groups by 2nd word). Throws InvalidArgumentException; controller catches → flash error.
- App\Services\ProfileService::updateProfile(User, UpdateProfileData): User — also writes session('locale') + app()->setLocale(). Will add locale sync via `app()->setLocale()` per user + session binding.
- Permission catalogue = string constants only, no PermissionEnum today (TODO on port): database/seeders/PermissionSeeder::PERMISSIONS (13 strings: view/create/edit/delete users, view/create/edit/delete roles, view audit logs, edit global settings, view api docs, view media, upload files). Roles seeded: admin (all) + user (none).

**Satellites (FE):**
- Pages/Users/Index.vue — props {users: Paginator<UserListItemData>; filterOptions:{roles: RoleListItemData[]}} · Filters search/name/email (~), role, is_active, created_at (date) · DataTable slots roles/is_active/created_at · Edit /users/{id}/edit, DELETE gated can.editUsers/deleteUsers, create gated can.createUsers.
- Pages/Users/Form.vue — props {user?: UserListItemData; roles: RoleListItemData[]} · useForm(put|post, /users[/id], {name,email,password,password_confirmation,is_active,roles: string[]}) · TextInput, PasswordInput (create only), ToggleInput, CheckboxGroup, FormActions.
- Pages/Roles/Index.vue — props {roles: Paginator<RoleListItemData>; filters?} · canDeleteRow=!row.is_system · DELETE gated, canDeleteRow check.
- Pages/Roles/Form.vue — props {role?: RoleDetailData; permissions: {group; permissions:{id;name}[]}[]} (local interface, not generated) · useForm(put|post, /roles[/id], {name,permissions: string[]}) · PermissionManager :model-value/@update · system role: name disabled + alert-warning.
- Pages/Profile/Show.vue — no page props, reads page.props.auth.user.name · two useForm('put') forms (profile, password change) · BUG: references non-existent FormErrorsAlert (TODO fix).
- AppLayout language dropdown → <a href="/language/{locale}"> (full reload) → LocaleMiddleware resolves order: user.locale → session → cookie → Accept-Language → sk default.

**DTOs in:**
- CreateUserData (unique:users,email), UpdateUserData (rules() reads request()->route('user')), CreateRoleData, UpdateRoleData, UpdateProfileData, ChangePasswordData.

**DTOs out:**
- UserListItemData (roles string[]), UserAutocompleteItemData, RoleListItemData (counts + is_system), RoleDetailData (permissions string[]). Dead: UserIndexFilterData, RoleIndexFilterData, LanguageSwitchData.

**Policies:**
- UserPolicy (CRUD via $user->can('… users'); delete blocks self), RolePolicy (delete blocks SYSTEM_ROLES). Auto-discovered.

**Factories & Seeders:**
- UserFactory states unverified(), inactive(). UserSeeder seeds admin@example.com/password + 5 faker users with role user.

**Flow:**
- GET /users → UserController@index (#[Authorize('viewAny', User::class)]) → QueryBuilder::for(User::with('roles')) + AllowedFilter filters → ->through(UserListItemData::fromModel) → Inertia render Users/Index {users: Paginator<UserListItemData>, filters, filterOptions.roles: RoleListItemData[]}.
- POST /users (+Precognition) → @store(CreateUserData) → UserService::create → redirect users.index + flash success. PUT /users/{user} → @update(UpdateUserData, User) → UserService::update. DELETE /users/{user} → UserService::delete. GET /users/autocomplete?q= → JSON UserAutocompleteItemData[] (private const AUTOCOMPLETE_MIN_CHARS=2; empty q→active users first 20 asc name; 1-char q→[]; ≥2 chars→ilike search).
- GET|POST /roles, GET /roles/create, GET /roles/{role}/edit, PUT|DELETE /roles/{role} → RoleController → RoleService → Inertia render Roles/{Index,Form} {roles|role: RoleDetailData, permissions: grouped array}.
- GET /profile → Inertia render Profile/Show (no props; reads shared auth.user). PUT /profile → ProfileService::updateProfile. PUT /profile/password → Hash::check + update inline in controller (web + API).
- API mirror (Sanctum): GET /api/users, GET|PUT|DELETE /api/users/{user}, GET|PUT /api/profile, PUT /api/profile/password → same services, JSON UserListItemData (paginated).

**Depends on:** auth layer (#[Authorize], Spatie can()), App\Utils\AllowedFilter, Activitylog.

**Depended on by:** auth, audit-logs (causer/subject), media (temporary_uploads.user_id), navigation (NavigationRegistry::canAccess), Inertia shared can.

**If you change Core, check:**
- SYSTEM_ROLES consumers: app/Policies/RolePolicy.php:35, app/Http/Controllers/RoleController.php:46, app/Http/Controllers/UserController.php:143, app/Data/RoleListItemData.php:30, app/Data/RoleDetailData.php:31.
- Permission strings: database/seeders/PermissionSeeder.php:14, app/Http/Middleware/HandleInertiaRequests.php:43-52 (shared can keys), resources/js/types/index.d.ts:71 (SharedCan), #[NavItem(permission:)] in User/Role/Media/AuditLog controllers, routes/web.php:37 (permission:view api docs), tests/Support/CreatesUsers.php (userWithPermission()).
- User columns: app/Models/User.php:20 (#[Fillable]), :39 (logOnly), app/Data/UserListItemData.php, database/factories/UserFactory.php, app/Http/Controllers/UserController.php:41-48.
- Role model FQCN: config/permission.php:15.

**Keywords:** user, role, permission, admin, is_active, locale, syncRoles, syncPermissions, autocomplete, profile, change password, HasRoles, PermissionManager.

### auth — session login, password reset, Sanctum tokens, auth audit

**Core:**
- No model/service; controllers call Auth::attempt directly (AuthController.php:27, Api/AuthController.php:31). Web: AuthController::showLogin/login(LoginData)/logout. Password reset: PasswordResetLinkController::create|store(PasswordResetLinkData), NewPasswordController::create|store(NewPasswordData). API: Api\AuthController::login(LoginData) → AuthTokenData::make($user, createToken('api')->plainTextToken) · logout → currentAccessToken()->delete(). Middleware guest/auth/auth:sanctum.
- App\Listeners\LogAuthenticationActivity — handleLogin|handleLogout|handleFailed → activity()->log('login'|'logout'|'login_failed') with ip, user_agent, email; wired AppServiceProvider.php:52-54 (3 events only). Will add on port: all 7 auth events + requestable IP.
- is_active NOT enforced at login (AuthController.php:27, Api/AuthController.php:31) — TODO on port guard.

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

**Depends on:** identity, audit-logs (listener), localisation (LocaleMiddleware reads user.locale).

**Depended on by:** every authenticated route; HandleInertiaRequests.

**If you change Core, check:**
- app/Data/AuthTokenData.php, tests/Feature/Api/AuthApiControllerTest.php:17-103, AppServiceProvider.php:52-54, app/Listeners/LogAuthenticationActivity.php.

**Keywords:** login, logout, sanctum, bearer token, password reset, forgot password, remember, guest, session regenerate, login_failed.

### audit-logs — Activitylog viewer

**Core:**
- Vendor Spatie\Activitylog\Models\Activity (bigint id; subject_id/causer_id uuid on live DB; batch_uuid nullable as of 2026_04_30_200000 migration). No service — QueryBuilder inline in AuditLogController@index: filters search (description/log_name/causer name+email), subject_type, created_at (date); sorts created_at, description, causer_name (leftJoin users as causer_user); default -created_at; per_page.
- ActivityPolicy (viewAny|view → view audit logs) registered manually in app/Providers/AuthServiceProvider.php. Writers: User/Role LogsActivity (User.php:39, Role.php:20), LogAuthenticationActivity.
- Config activitylog clean_after_days 365. Morph columns uuid (nullableUuidMorphs subject/causer).

**Satellites (FE):**
- Pages/AuditLogs/Index.vue — props {activities: Paginator<ActivityLogListItemData>; query?} (BE sends `filters`, page declares `query` — unused) · Filters subject_type (~), created_at (date) · DataTable · #buttons → /audit-logs/{id}.
- Pages/AuditLogs/Show.vue — {activity: ActivityLogDetailData} · renders attribute_changes/properties as <pre>.

**DTOs:**
- ActivityLogListItemData::fromModel (subject_type via class_basename), ActivityLogDetailData (+ properties, attribute_changes = $activity->changes). Dead: ActivityLogIndexFilterData.

**Flow:**
- GET /audit-logs → Inertia AuditLogs/Index {activities: Paginator<ActivityLogListItemData>, filters}. GET /audit-logs/{activity} → AuditLogs/Show {activity: ActivityLogDetailData}.

**Depends on:** identity, auth.

**Depended on by:** nothing (sink). Shared can.viewAuditLogs (HandleInertiaRequests.php:51).

**If you change Core, check:**
- Any new LogsActivity model must have uuid PK — database/migrations/2026_04_30_123027_create_activity_log_table.php (nullableUuidMorphs); app/Providers/AuthServiceProvider.php; AuditLogController.php:22 NavItem.

**Keywords:** activity log, audit, causer, subject, LogsActivity, batch_uuid, login_failed, attribute_changes.

### media — MediaLibrary viewer + temporary uploads

**Core:**
- Vendor Media (bigint id, uuid unique, model_id uuid via uuidMorphs, disk public per config/media-library.php:37, 10 MB max). App\Models\TemporaryUpload — UUID PK, #[Fillable(['session_id','user_id'])], implements HasMedia, collection default; user_id → users nullOnDelete (2026_05_14_111222); user(): BelongsTo. On port: add tenant_id FK, BelongsToTenant, TenantScope.
- App\Services\TemporaryUploadService (DB::transaction): store(UploadedFile, ?User, string $sessionId): Media · moveToModel(HasMedia $model, string $collection, string $uuid): Media (Media::move + deletes empty TemporaryUpload) · delete(string $uuid, ?User, string $sessionId): void · purgeOlderThan(int $hours=24): int.
- App\Services\MediaService::index(MediaIndexFilterData): LengthAwarePaginator<Media> · show(Media): MediaDetailData.
- App\Services\MediaUrlResolver::resolve(?string $modelType, string|int|null $modelId): array{label,url} — reads config/media-urls.php.

**Satellites (FE):**
- Pages/Media/Index.vue — {media: Paginator<MediaListItemData>; filters?} · DataTable · formatBytes.
- Pages/Media/Show.vue — {media: MediaDetailData} · image preview.
- FileUploadInput.vue (form component) — posts to /uploads (hard-coded :41) → 201 {uuid,name,file_name,mime_type,size,url}; DELETE /uploads/{uuid} → 204; v-model uuid|uuid[]. RichTextEditorInput reuses endpoint for inline images.

**DTOs:**
- MediaListItemData::fromModel (app(MediaUrlResolver::class)), MediaDetailData, MediaIndexFilterData (#[MapInputName('filter.*')]), StoreTemporaryUploadData (file|max:10240).

**Policies:**
- MediaPolicy (viewAny|view → view media, AuthServiceProvider), TemporaryUploadPolicy (create|delete → upload files, class-level; ownership in service). App\Rules\OwnedTemporaryMedia (no callers yet, by design).

**Seeder:**
- App\Console\Commands\PurgeTemporaryUploadsCommand (app:purge-temporary-uploads {--hours=24}) daily in routes/console.php.

**Flow:**
- POST /uploads (multipart file) → #[Authorize('create', TemporaryUpload::class)] → TemporaryUploadService::store → 201 JSON {uuid,name,file_name,mime_type,size,url} (bare array). DELETE /uploads/{uuid} → 204. Consumer contract: form DTO holds uuid validated by OwnedTemporaryMedia → service calls moveToModel($owner,'collection',$uuid).
- GET /media → MediaController@index(MediaIndexFilterData, Request) → Media/Index {media: Paginator<MediaListItemData>, filters}. GET /media/{media} (whereNumber constraint → 404 for non-numeric id) → Media/Show {media: MediaDetailData}.

**Depends on:** identity, auth, AllowedFilter, config/media-urls.php.

**Depended on by:** future media-owning models (Quote document) via moveToModel + OwnedTemporaryMedia.

**If you change Core, check:**
- config/media-urls.php:16 (references non-existent App\Models\EmailTemplate); app/Services/TemporaryUploadService.php:46,71 + app/Rules/OwnedTemporaryMedia.php ((new TemporaryUpload)->getMorphClass()); resources/js/Components/Forms/FileUploadInput.vue:41.

**Keywords:** media, upload, temporary upload, HasMedia, collection, moveToModel, OwnedTemporaryMedia, purge, mime_type, media-urls.

## Cross-cutting

### auth layer (3 tiers)
Route middleware auth / auth:sanctum / guest; per-action #[Authorize($ability, $modelOrParam)] (Illuminate\Routing\Attributes\Controllers\Authorize); App\Http\Middleware\PermissionMiddleware aliased permission (bootstrap/app.php:21) used ONLY for /docs* (routes/web.php:37, config/scribe.php:91). Policies check flat strings via $user->can. Shared Inertia can map hand-maintained HandleInertiaRequests.php:36-54 (10 keys; FE consumes 6 hard-coded keys in types/index.d.ts). On port: extend to include tenancy scope + new resource permissions (clients, objects, quotes, contracts, invoices, schedule, employees, notifications) + switch to PermissionEnum.

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

### Localisation (BE + FE sync)
App\Enums\SupportedLanguage {sk, en} (#[TypeScript]). LocaleMiddleware (web) resolves user.locale → session → cookie → Accept-Language → sk. GET /language/{locale} (unauthenticated, no DTO). Translations = JSON resources/lang/{sk,en}/app.json (+ sk/validation.json) loaded by AppServiceProvider::loadJsonTranslations as app.<key> (156 sk keys; +11 phase-1 keys: app_name, auth_hero_title_1/2, auth_hero_subtitle, auth_hero_feature_clients/invoices/schedule, auth_welcome_back, footer_copy, auth_show_password, auth_hide_password). FE: vue-i18n ^11 (legacy:false), messages bundled at build from resources/lang/{locale}/app.json, locale from Inertia `locale` prop + switched on router.on('navigate'). No lang/ dir, no PHP lang files. FE router locale change calls GET /language/{locale} (full reload). On port: add uk to SupportedLanguage + resources/lang/uk/app.json + FE app.ts messages import.

### Scribe API docs
add_routes=false; routes in routes/web.php:37-49 under auth + permission:view api docs. Strategies App\Scribe\Strategies\BodyParameters\GetBodyParamsFromSpatieData, Responses\GetResponseFromSpatieData (#[ResponseFromSpatieData(dataClass, model, states, with, paginated)]), wired config/scribe.php:223+. Only api/* documented. On port: extend docs for business API endpoints (clients, objects, quotes, invoices, contracts).

### App shell (FE)
Layouts/AppLayout.vue — DaisyUI drawer lg:drawer-open, data-theme="app-theme" on root · dark sidebar (--color-neutral background) with amber-gradient BrandMark logo, white text nav, user card (amber-gradient initial avatar) · nav source = BE page.props.navigation (NavigationItemData[] {key,label,href,icon,order,children[]}) · resolves icon via ICONS map · flash → toast (watch page.props.flash, 4 s) + window app-toast CustomEvent. Layouts/Header.vue — props {title; breadcrumbs?} + #actions slot. ConfirmDeleteModal + useDeleteConfirm composable. Components: FormProvider, TextInput, PasswordInput, SelectInput, CheckboxInput, CheckboxGroup, RadioGroup, ToggleInput, DateInput, NumberInput, TextareaInput, AutocompleteInput, FileUploadInput, RichTextEditorInput, PermissionManager. Components/BrandMark.vue — sparkle-broom SVG icon (size via class). DaisyUI themes:false + [data-theme='app-theme'] OKLCH cleanmaster token block (Plus Jakarta Sans / JetBrains Mono via Google Fonts in app.blade.php) + data-theme="app-theme" hardcoded in app.blade.php + :root {--auth-*} login palette + app.ts Inertia progress colour amber. On port: add tenant switcher dropdown + "Add new company" + notification bell + extend language list to include uk.

## Layer contracts

### HTTP ↔ Service
Controller receives DTO + route-bound model, calls final readonly service, returns Inertia::render / redirect-with-flash (success|error|info|status) / response()->json(Data). Exceptions: RoleService signals via InvalidArgumentException; profile password change and auth attempts inline in controllers; list queries live in controllers (User, Role, AuditLog) except MediaService::index. On port: all business model services follow same pattern (create/update/delete in service, list in controller).

### Service ↔ Model
Services own DB::transaction; models hold casts, LogsActivity options, scopes. No observers/events besides framework auth. On port: add business model observers + event dispatchers (QuoteSent, InvoiceIssued, InvoiceOverdue, ContractExpired, etc.).

### Service ↔ Job / Queue
No queued jobs today (QUEUE_CONNECTION sync in tests). Only schedule PurgeTemporaryUploadsCommand daily. On port: add queued jobs (InvoiceIssued mail, GenerateScheduledJobs, GenerateRecurringInvoiceJob) with ShouldDispatchAfterCommit.

### BE ↔ FE shared props (HandleInertiaRequests::share)
flash{success,error,info,status}, auth{user{id,name,email}|null}, can{viewUsers,createUsers,editUsers,deleteUsers,viewRoles,createRoles,editRoles,deleteRoles,viewAuditLogs,viewMedia}, locale, languages[{value,label,flag}], navigation. Typed by TWO declare module '@inertiajs/core' blocks (types/index.d.ts — SharedFlash/SharedUser/SharedCan, navigation missing; types/inertia.d.ts — inline literals + navigation). On port: add tenant{active,available}, tenantColors, per-tenant permissions (clients, objects, quotes, invoices, contracts, schedule, employees, notifications), canResetPassword (carry from main).

### Per-page props (Inertia render)
Users/Index {users: Paginator<UserListItemData>, filters, filterOptions{roles}} · Users/Form {user?, roles} · Roles/Index {roles: Paginator<RoleListItemData>, filters} · Roles/Form {role?: RoleDetailData, permissions: [{group, permissions}]} (bare array) · AuditLogs/Index {activities, filters} · AuditLogs/Show {activity} · Media/Index {media, filters} · Media/Show {media} · Profile/Show {} · Auth/Login {canResetPassword} · Auth/ForgotPassword {status} · Auth/ResetPassword {email, token} · Dashboard {}. On port: add Clients/Index/Form, Objects/Index/Form/Show, Quotes/Index/Create/Edit/Show, Invoices/Index/Create/Edit/Show, RecurringInvoices/Index/Create/Edit/Show, Contracts/Index/Create/Edit/Show, Schedule/Index/Create/Edit/Show, Employees/Index/Create/Edit/Show, ContractTemplates/Index/Create/Edit/Show, Notifications/Index, NotificationSettings/Show.

### BE ↔ FE API (Sanctum, routes/api.php)
POST /api/auth/login → AuthTokenData · POST /api/auth/logout → 204 · GET|PUT /api/profile → UserListItemData · PUT /api/profile/password → 204 · GET /api/users → Paginator<UserListItemData> · GET|PUT|DELETE /api/users/{user}. Precognition on mutations. On port: add /api/notifications/bell for 60s polling.

### BE ↔ FE Enums
#[TypeScript] backed enum → App.Enums.X union in generated.d.ts; labels NOT shipped (FE t() via flat key, BE label fields if needed). Today: SupportedLanguage only. On port: JobStatusEnum, JobTypeEnum, TaskFrequencyEnum, ObjectTypeEnum, ClientTypeEnum, QuoteKindEnum, ContractCategoryEnum, ContractTermTypeEnum, PaymentTypeEnum, RoundingModeEnum, EmploymentContractTypeEnum, InvoiceTemplateEnum, InvoiceStatusEnum, TenantColorEnum, RecurringInvoiceFrequencyEnum.

### FE Authorization
can.<camelVerbResource> booleans (manually enumerated in HandleInertiaRequests.php and types/index.d.ts:71). BE-filtered navigation (NavItem permission checks). No <Can> component (FE skeleton lacks it; main has it; CLAUDE.md mandates it — will be added on port). No useAuthorization().allows (will be added on port).

## Gotchas

### Backend (all 16 from skeleton map)

1. Spatie Permission teams OFF. config/permission.php:48 teams=false, team_foreign_key team_id (:39). roles/model_has_roles/model_has_permissions have no team column (psql-verified). Port needs teams=true, team_foreign_key=tenant_id, and migration 2026_04_30_123027_create_permission_tables.php types team column unsignedBigInteger (:44,:66,:86) → must become uuid('tenant_id') before migrate:fresh. Every seeder/test/job must call setPermissionsTeamId() (none do today: tests/Support/CreatesUsers.php, PermissionSeeder, UserSeeder).

2. ~~Sanctum personal_access_tokens.tokenable_id is bigint (morphs) vs users.id uuid~~ — FIXED 2026-09-06: uuidMorphs('tokenable') applied. Tests now run on Postgres.

3. activity_log morph columns uuid (nullableUuidMorphs subject/causer). Non-UUID-PK LogsActivity subjects (TenantInterface bigint, vendor Media) fail on Postgres. Decide at port: keep uuid morphs and forbid logging bigint models, or switch to string morphs as the old codebase did.

4. No PermissionEnum — raw strings in PermissionSeeder::PERMISSIONS, policies, HandleInertiaRequests, #[NavItem(permission:)], routes/web.php:37, tests/Support/CreatesUsers.php. Port introduces the enum; migrate all sites together.

5. PermissionMiddleware vs #[Authorize]: keep permission: middleware limited to non-model routes (docs).

6. is_active not enforced at login (AuthController.php:27, Api/AuthController.php:31).

7. config/media-urls.php:5,16 references non-existent App\Models\EmailTemplate.

8. Auth-event audit covers 3 of 7 events; request()->ip() used directly.

9. No rate limiters on login / forgot / reset / api login.

10. UserSeeder seeds admin@example.com/password + 5 faker users; app:demo runs migrate:fresh --force. CLAUDE.md on main said bootstrap via app:create-owner (not in skeleton).

11. Dead code: DTOs UserIndexFilterData, RoleIndexFilterData, ActivityLogIndexFilterData, LanguageSwitchData; OwnedTemporaryMedia no callers (by design). AllowedFilter issues (callbackWithOperator, FilterPrice, relationExact contains operator) fixed 2026-09-06.

12. Roles/Form permissions prop is a bare grouped array (TS any).

13. Temporary upload response is a bare array; FileUploadInput.vue:41 hard-codes /uploads.

14. SupportedLanguage only sk/en — CleanMaster needs uk. Translations JSON under resources/lang/*/app.json in app. group; no lang/ dir, no PHP lang files.

15. db:table artisan fails in container (intl missing) — use `docker compose exec postgres psql`.

16. No morph map, no preventLazyLoading, no SoftDeletes on any model — business models from the port add SoftDeletes + BelongsToTenant + TenantScope (none exist yet).

17. PHPStan level max runs against `phpstan-baseline.neon` (136 entries, regenerated 2026-09-06 after fixes). Regenerate baseline only when reducing errors; run with `--memory-limit=1G` to avoid OOM with parallel workers. Excluded paths: vendor-published migrations (spatie/permission). Burn-down follow-up targets: test closures `AssertableInertia $page` typing (~60), AllowedFilter generics, QueryBuilder mixed chains, ActivityLog DTO causer access, Scribe strategies.

18. `compose.yml` injects `DB_CONNECTION=pgsql`, `DB_DATABASE=cleanmaster_admin` into container process env (in `$_SERVER`). PHPUnit `<env>` blocks without `force="true"` are overridden; apply `force="true"` to both `<env>` and `<server>` (phpdotenv reads both) to isolate test DB. Testing DB `cleanmaster_admin_testing` created by `docker/postgres/init-testing-db.sql` on first volume init; on existing volumes, create manually per CLAUDE.md runbook.

### Frontend (all 10 from port map, verbose)

1. Three-way i18n mismatch. main: shared prop translations = Arr::dot(trans('app')) + useTranslate()/usePageProps(), PHP files lang/{sk,en,uk}/{app,auth,passwords,validation}.php. Skeleton: build-time vue-i18n from resources/lang/{locale}/app.json + Lang::addLines. Port = convert main's lang/*/app.php to flat JSON under resources/lang/<locale>/app.json (sub-groups job_status.*, permission.* → dotted keys in app.json or separate <group>.json files which loadJsonTranslations supports; FE imports them in app.ts messages), add uk to SupportedLanguage + app.ts, replace every useTranslate()/usePageProps().translations with useI18n().t. Do not reintroduce translations shared prop.

2. URLs stay string literals — Ziggy absent on both sides; pages port 1:1. Skeleton DataTable pages pass route-name="users.index" which is NOT a prop (falls through as DOM attr) — don't copy; use route-url or omit.

3. usePageProps() + useAuthorization()/<Can> + Pinia capabilities store do not exist. CLAUDE.md mandates Can + useAuthorization().allows — re-create during port. Pinia not installed (main needed it for stores/capabilities.ts and stores/notifications.ts 60 s bell poll) — dependency change needs approval. Shared can here is hand-maintained camelCase; main derived from PermissionEnum. main's shared props also had app, tenant{active,available}, tenantColors, canResetPassword, richer auth.user — extend HandleInertiaRequests and BOTH type blocks (or collapse them first).

4. Navigation is BE-driven. main's AppLayout hardcoded navItems[] with can keys + tenant switcher dropdown. Here: add #[NavItem(label:'app.clients', route:'clients.index', icon:'BuildingOffice2Icon', permission:'view clients', order:…)] per controller + extend ICONS map. Tenant switcher / "Pridať novú firmu" and notification bell have no slot in skeleton AppLayout — explicit layout edits (sidebar user card / mobile navbar).

5. ~~Theme declaration differs~~ RESOLVED 2026-09-06: kept `app-theme` selector in place, overwrote OKLCH token values + added color-scheme:light in resources/css/app.css; Plus Jakarta Sans/JetBrains Mono via Google Fonts <link> in app.blade.php; :root {--auth-*} login palette + .auth-* utilities ported to app.css; Inertia progress colour set to amber in app.ts. Auth pages reskinned with new Auth* components (AuthShell + hero + fields + language switcher). Phase-2 tenant colour override will target --color-primary inline on <html> or AppLayout root.

6. Pagination. main's Components/Pagination.vue expects {meta, links} (Spatie PaginatedDataCollection shape) + useXFilters composables + router.get. Skeleton DataTable expects raw LengthAwarePaginator shape from ->paginate()->through(). Port lists onto DataTable (FilterConfig[] + slots), drop Pagination.vue + per-domain use*Filters; drawer-based Clients/Objects lists may keep custom layouts but feed TablePagination the paginator shape.

7. Form inputs. main had FormProvider/useFormContext/useFieldError — same names, field= usage ports. Skeleton DateInput/NumberInput/TextareaInput/AutocompleteInput/FileUploadInput/RichTextEditorInput are prop-mode only — wire v-model + :error, or extend with field support (pattern TextInput.vue). main-only ColorSwatchPicker, FileDropInput, Forms/index.ts barrel, ConfirmDialog, EmptyState, PageHeader: map PageHeader → Layouts/Header.vue, ConfirmDialog → ConfirmDeleteModal + useDeleteConfirm, FileDropInput → FileUploadInput (temporary-upload uuid flow + OwnedTemporaryMedia instead of direct multipart), EmptyState → port as new shared component.

8. ESLint ref ban gone; skeleton uses ref() freely. Recommend NOT reinstating; follow skeleton style.

9. Precognition is the default. Every main form: useForm({...}) + router.post → useForm(method,url,data) + form.submit(); every store/update route carries HandlePrecognitiveRequests. DTOs with cross-field rules (prohibits, quote clientless, contract term/end_date) run under Validate-Only subset — verify each behaves with partial payloads.

10. Minor defects: ~~Profile/Show.vue FormErrorsAlert missing~~ FIXED 2026-09-06 (removed dead reference); ~~FormActions literal fallbacks~~ FIXED 2026-09-06 (now t('cancel')/t('save') defaults via useI18n). Open: PermissionManager untranslated (bring main's permission.*/permission_group.* keys); duplicated shared-props declarations in types/index.d.ts + types/inertia.d.ts; Users/Index query vs BE filters prop mismatch.

## Verification status

**Last full scan:** 2026-09-06 (degraded — Laravel Boost MCP unavailable; used docker compose exec + direct psql / grep instead).

**Last delta:** 2026-09-06 (phase 1 visual + login: Auth pages reskinned, new Auth* components, BrandMark SVG, AppLayout branding, theme tokens ported, i18n +11 keys, FE gotchas 5+10 resolved/updated).

**Certainty audit:**
- All relationships verified by: live route:list output, migration file + docker exec postgres psql information_schema queries, grep of every cited callsite + direct reads.
- Open TODO verify:
  1. AllowedFilter dead references (callbackWithOperator + FilterPrice/FiltersCallbackWithOperator) — confirm with phpstan on app/Utils/AllowedFilter.php.
  2. Layouts/DataTable.vue + Composables/useFilters.ts — 0 consumers; retained or dead in skeleton? (ask upstream; recommend: do not use for the port).
  3. Cross-field #[Validation] rules from main (quote clientless prohibits, contract term/end_date conditionals) under Precognition-Validate-Only partial payloads — behavior TBD.
