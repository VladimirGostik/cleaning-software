# Review rules

<!-- Mirror of CLAUDE.md §"Review rules" — keep in sync. Enforced strictly by inogile reviewer-agent; this file feeds GitHub @claude review. -->

Synced with `laravel-13-conventions/references/review-checks.md` §"Distilled house rules" (master copy). See root `REVIEW.md` for the canonical list.

**Laravel 13 distilled house rules:**

1. Validation via Spatie Data attribute DTOs — no FormRequest, no `$request->validate()`, no JsonResource.
2. UUIDv7 PKs (`HasUuids`) — never `$table->id()` / `bigIncrements`.
3. Controllers thin — no business logic, no `DB::transaction` (service owns both). Every action authorizes through the model's Policy (L13 `#[Authorize]`, L10-12 `$this->authorize()`) in every auth profile; `role:` route groups are an extra outer layer, not a replacement.
4. `Inertia::render` props: Data DTO / `::collect(...)` / scalar only — never bare array / Model / paginator without `->through(...)`.
5. Every user-facing message via `__('<ns>.<key>')` from lang files (validation = auto-lookup, override only per-field via `messages()`); never literal strings. Logs stay English.
6. Response DTOs minimal — no speculative fields without a named consumer.
7. Backed enums for fixed value sets — no magic status/permission strings.
8. `env()` only in `config/`; URLs via named route + `route()`.
9. Eager-load accessed relations (`with` / `withCount`); no `Model::all()` in request paths.
10. Events/jobs dispatched inside a transaction: `ShouldDispatchAfterCommit` / `->afterCommit()`.
11. No comments that restate code — no prose docblocks, no `// Arrange` markers, no step narration; PHPDoc only for types PHP can't express (array shapes, generics). 1-line WHY only.
12. Copy a model with `replicate()`; a prefixed snapshot (`customer_*`) is filled by one builder, never repeated field lists. Same-shape child under several parents = one polymorphic table; legal snapshots and diverging children keep a table per parent with shared code.

**Per-project enforcement:**

- **Auth profile:** rbac-full — Policies gate every action. Controllers use `#[Authorize($ability, $modelOrParam)]` attribute. Single authorization axis: RBAC permission only, except the documented `users.is_platform_admin` flag (global document templates, POST /tenants). Never use `role()` checks; always use `Can` component + `useAuthorization().allows(permission)`.
- **Multi-tenancy:** row-level via `tenant_id` FK. Every domain model MUST have `tenant_id` UUID FK + `BelongsToTenant` trait + `TenantScope` global scope (exceptions: global `Task` rows via `TaskScope`, `TaskComment` with nullable `tenant_id` (NULL on personal tasks only) and the fail-closed `TaskCommentScope`, `media.tenant_id` NULL for Task owners only (DB CHECK `media_tenant_required_unless_task`), nullable `notifications.tenant_id`, per-person `DeviceToken`, `MobileSession`, `AssistantConversation` and `AssistantMessage`). **CRITICAL:** `setPermissionsTeamId($tenantId)` must be called before any role/permission lookup outside HTTP requests (jobs, console, seeders, tests).
- **TypeScript code generation:** `#[TypeScript]` attribute on all DTOs + Enums. `php artisan typescript:transform` generates `resources/js/types/generated.d.ts`. FE imports from there.
- **Atomic transactions:** Multi-step operations (create owner, issue invoice, generate recurring invoice) wrapped in `DB::transaction` in Service (NEVER Controller). Events/jobs inside transaction must implement `ShouldDispatchAfterCommit`.
- **Spatie Data DTO boundary:** Controllers accept DTO params with `#[Validation]` attributes. No inline `$request->validate()`. No FormRequest. No JsonResource.
- **i18n via locale keys:** Every user-facing string via `__('<ns>.<key>')` from `lang/{sk,en,uk}/*.php`. Logs stay English.
- **AI tool layer:** tools (`app/Mcp/Tools/*`) hold no business logic; they authorise through `Gate::forUser` inside `ToolContext` and delegate to services. `confirm_action` is MCP-only (never in the assistant toolbox, `AssistantToolbox::EXCLUDED`); `delete_global_task` is excluded there too. Irreversible actions go through `prepare_action` + confirmation, never a direct tool call. The AI task run toolset (`AssistantToolbox::forTaskRun`) also excludes complete, update, assign and company-wide tools; `ReportTaskOutcomeTool` is run-only and never in `CleanMasterServer::TOOLS`.
