# Laravel 13 + Inertia + Vue 3 Skeleton

Opinionated greenfield skeleton pre rýchle, AI-asistované budovanie webových aplikácií.

## Stack

| Layer | Tool |
|-------|------|
| Backend | Laravel 13, PHP 8.5 |
| Frontend | Inertia v3 + Vue 3 + TypeScript |
| DB | PostgreSQL 16 |
| CSS | Tailwind 4 + DaisyUI 5 |
| Validation | Spatie Data 4 (no FormRequest) |
| Auth | Spatie Permission 7 |
| Audit | Spatie Activitylog 5 |
| Media | Spatie MediaLibrary 11 |
| API filter | Spatie QueryBuilder 7 + custom `App\Utils\AllowedFilter` |
| TS gen | Spatie TypeScript Transformer 3 |
| Tests | PHPUnit 12 |
| Lint | Pint, PHPStan (Larastan), ESLint, Prettier, vue-tsc |
| Hooks | Lefthook |
| AI agent | Laravel Boost 2 |

## Bundled modules

- **auth** — login, logout, forgot/reset password
- **profile** — user account + password change
- **permissions** — role/permission CRUD (Spatie Permission)
- **audit-logs** — activity log viewer
- **localisation** — language switcher (SK/EN)

## Postup: od skopírovania po nový modul

### 1. Pull skeleton z gitu

```bash
git clone <skeleton-repo-url>
```

Alebo cez GitHub: klik **"Use this template"** → clone vytvorený repo.

### 2. Init script (Docker bootstrap end-to-end)

```bash
bin/init-app
```

Prompts: slug, display name, DB name, composer vendor + 2 opt-in (host deps pre IDE, git reset).

Script sám: rename placeholders → `.env` → `docker compose up --build` (postgres + redis + app) → composer install + pnpm install vnútri kontajnera → APP_KEY + migrate + seed + typescript:transform → spustí Vite → prune → fresh git. Cca 3-5 min prvýkrát.

**Vyžaduje bežiaci Docker daemon.**

Po skončení: app na `http://localhost:8000`, login `admin@example.com` / `password`.

### 3. Spusti Claude Code

```bash
claude
```

### 4. Scaffold modul

```
/scaffold-module product fields=name:string:required:max:255,price:decimal:required:min:0,sku:string:required:unique:max:64
```

Agent vygeneruje 13 nových súborov + 5 editov, sám spustí `migrate`, `db:seed --class=PermissionSeeder`, `typescript:transform`, lint a typecheck. Detekuje docker/local runner sám.

### 5. Otestuj

Hard refresh browser (`Cmd+Shift+R`). Vidíš nový nav item, klik → CRUD funguje.

### Reset DB neskôr

V Claude Code: `/docker-init` — dropne DB volume, znovu migrate + seed.

---

## Bežné commands

```bash
docker compose exec app php artisan <cmd>      # artisan
docker compose exec app php artisan test       # PHPUnit
docker compose logs -f app                     # tail logy
docker compose down                            # stop
docker compose down -v                         # stop + drop DB
```


## Conventions (zhrnutie — detail v `CLAUDE.md`)

- UUIDv7 PK cez `App\Concerns\HasUuids`
- Spatie Data DTOs (žiadny FormRequest, žiadne `$request->validate()`)
- Inertia + Spatie Data `toArray()` response (žiadny JsonResource)
- `final readonly class` services + `DB::transaction`
- Spatie Permission cez `#[Authorize]` controller attr → Policy
- Spatie Activitylog na biznis modeloch
- `env()` mimo `config/` zakázané
- `php artisan typescript:transform` po každej zmene v `app/Data/`

## Lefthook hooks

Pre-commit:
- `vendor/bin/pint` — PHP format
- `php -d memory_limit=512M vendor/bin/phpstan analyse` — PHP static analysis
- `pnpm exec eslint` — JS/TS lint
- `pnpm exec prettier --check` — formatting
- `php artisan typescript:transform` — regenerate TS types

Pre-push:
- `pnpm exec vue-tsc --noEmit` — TypeScript check


## Project structure (kľúčové)

```
app/
  Concerns/HasUuids.php           — UUIDv7 trait
  Data/                            — Spatie Data DTOs (Create/Update/ListItem/IndexFilter)
  Http/Controllers/                — final classes, #[Authorize] attrs
  Http/Middleware/HandleInertiaRequests.php  — shared 'can' props
  Models/                          — final, HasUuids + LogsActivity
  Policies/                        — 5 methods (viewAny/view/create/update/delete)
  Services/                        — final readonly + DB::transaction
  Utils/AllowedFilter.php          — search/dynamic/relationExact/boolean
resources/js/
  Components/DataTable/            — reusable table (filters + pagination + search)
  Composables/useSpatieTableQuery.ts — query param ↔ Spatie filter syncing
  Layouts/AppLayout.vue            — drawer + nav (gated by 'can')
  Pages/Users/                     — canonical CRUD example
  types/generated.d.ts             — auto-gen z PHP DTOs (NIKDY needituj)
.claude/
  business.md                      — domain rules, use-cases
  technical.md                     — architecture catalog
  agents/scaffold-module.md        — scaffolding agent
  commands/scaffold-module.md      — slash command
  skills/skeleton-module-scaffold/ — 13 sub-rules pre scaffolding
bin/init-app                       — one-shot template bootstrap (self-deletes)
```

## Updating skeleton in a cloned app

Skeleton + derived apps **nie sú syncované**. Po `bin/init-app` je nová app fork. Bugfixy v skeletone si manuálne cherry-pickni. Dôvod: každá app edituje `AppLayout.vue` nav, `PermissionSeeder`, controllers — upstream merge by bol stratený čas.

