# Invoicing batch — payments, bulk actions, mail texts, recurring auto-send, Pohoda export, e-invoicing preparation

## Plan ready
- plan_size: file
- mode: spec
- target_agent: [be-agent, fe-agent, devops-agent]
- affected_modules: [invoices, recurring-invoices, settings-invoicing, clients, shell]
- plan_path: .inogile/plans/invoicing-batch-payments-bulk-efa.md
- parts: [payments, bulk-actions, mail-texts, recurring-auto-send, pohoda-export, efa-preparation, bank-import-design]
- dto_shape: see each part's `DTO contracts` block (TS namespaces `App.Data.Invoices.*`, `App.Enums.*` via `typescript:transform`)
- parallel_slices: none — every part edits `app/Services/InvoiceService.php`, `resources/js/Components/Invoices/InvoiceActionsCard.vue` and the three lang bundles `resources/lang/{sk,en,uk}/app.json`; parts run sequentially in the order listed, each its own review unit
- discovery: semble 0 (not whitelisted) | grep 19

### Decisions (agreed with the owner 2026-10-12)
- D1 Partial payments: new `invoice_payments` table, several payments per invoice, status stays Issued/Overdue until the balance is settled, overpayment rejected (owner: A).
- D2 Bulk download = ONE merged PDF, each invoice rendered through the existing `RendersInvoicePdf` and the binaries merged with `qpdf` behind the contract `MergesPdfDocuments` (owner: B, "must work 100%"). `qpdf` chosen over `pdfunite`: page-level copy preserves every per-document resource (fonts, footer template output, page labels, embedded QR raster) byte-exact, deterministic exit codes, ~2 MB Alpine package without the poppler runtime; `pdfunite` is a thinner wrapper that rebuilds the resource dictionary and has known issues with duplicated font subsets across Chromium-produced documents. Zip extension step dropped.
- D3 Bulk actions: merged PDF download, send e-mail, mark paid, issue drafts, plus a duplicate button on the list row (owner: A).
- D4 E-invoicing: preparation only, no live access point; owner's companies are NOT VAT payers → Part 6 runs last, non-payer variant first-class (VAT category `O`, no VAT breakdown); regulatory facts behind `config/efa.php` (owner: A).
- D5 Accounting export = Stormware Pohoda XML (`dataPack` of issued invoices), own part (owner: C). Pohoda specifics not settled by the owner are listed under Assumptions in Part 5, not blocked on.
- D6 (assumed) Merged PDF is synchronous with a cap `config('invoicing.bulk.pdf_max_ids')` default 10 (Chromium ≈ 1–2 s per document → worst case ≈ 20 s + merge, under php-fpm/nginx 60 s). Above the cap → 422 `app.invoice_bulk_pdf_too_many`. Queued generation considered and deferred: it needs a stored export file + download route + notification; add later only if the owner needs > 10 at once.
- D7 (assumed) Partial state is a derived flag (`is_partially_paid`), not a new `InvoiceStatusEnum` case — keeps `canTransitionTo`, stats, `MarkOverdueInvoices`, dashboard untouched.
- D8 (assumed) Mail texts: one subject + one body per tenant (not per locale), empty → existing lang keys; placeholders `{{...}}` via `PlaceholderResolverService`.
- D9 (assumed) Recurring auto-send is a per-template toggle `auto_send`, valid only with `auto_issue`; no global setting.
- D10 (assumed) No new `PermissionEnum` cases: bulk pay/send/issue + payment record/delete + EFA submit = `EditInvoices`; merged PDF, Pohoda XML, EFA XML download = `ViewInvoices`; Peppol ID in settings = `ManageBillingSettings`; Peppol ID on client = `EditClients`.
- D11 Migrations: `Deployed: no` → new `add_*` / `create_*` migrations, existing migrations untouched (`laravel-13-conventions/references/migrations-strategy.md`).
- D12 Bank statement import: schema design only (Part 7), no tables created now; `invoice_payments.source` + `external_reference` reserved so Part 7 needs no payments migration later.
- D13 (assumed) Pohoda XML export is synchronous BY DESIGN — deviation from `laravel-13-conventions/references/exports.md` ("every export queued"), which targets maatwebsite/excel datasets: here the output is one XMLWriter stream over at most 366 days of one tenant's invoices (no Chromium, no spreadsheet engine, no stored file), eager-loaded `items` in one query, hard cap `config('pohoda.max_invoices')` = 2000 rows per request → 422 `app.pohoda_export_too_many` asking to narrow the range. Same precedent as the sync single-invoice PDF download. Queued variant (stored file + notification) only if a tenant ever exceeds the cap.

### Hard gates (summary — details per part)
| Gate | Decision |
|---|---|
| FK ON DELETE | every new FK `restrictOnDelete()` (house rule D8) except `bank_statement_lines.matched_invoice_id` (Part 7, design) `nullOnDelete`; no cascade anywhere |
| Soft delete | `invoice_payments` yes (financial record, audit); `bank_statement_*` (Part 7) no soft delete on lines (operational, re-importable), yes on imports; no UNIQUE columns on soft-delete tables in this plan except Part 7 `(tenant_id, statement_id, entry_reference)` → plain unique (lines are not soft-deleted) |
| JSON vs relation | payments = relation table (queried, summed, per-row audit). Bulk result = flash payload, not persisted. `vat_breakdown` stays as is |
| Async surface | bulk send = existing per-invoice queued `InvoiceIssued` (afterCommit); bulk pay/issue = sync DB writes in one service call, one transaction per invoice (partial success by design); merged PDF = sync capped (D6); Pohoda XML = sync by design (D13, cheap XMLWriter, 366-day + 2000-row cap); recurring auto-send = inside `GenerateRecurringInvoiceJob` after issue (notification already afterCommit); EFA submit = sync call to `SubmitsEInvoices` (null driver) — a real provider later wraps it in a job (`jobs.md`) |
| Seams + file budget | contracts only at variation points: `MergesPdfDocuments` (external binary), `SubmitsEInvoices` (external provider). No interface on `UblInvoiceXmlBuilder`, `PohodaXmlBuilder`, `InvoiceBulkService`. Every new file carries a one-line reason in its part |

### Validation ownership (one owner per rule)
| Rule | Owner |
|---|---|
| ids are uuids, 1..cap, distinct | DTO (`InvoiceBulkActionData`) |
| id belongs to current tenant | service query under `TenantScope` (missing → skipped row, reason `not_found`) |
| per-invoice status guards (Issued, has email, Draft for issue, canTransitionTo Paid) | existing service single-action methods (reused, not re-checked in bulk code) |
| payment amount > 0, ≤ balance_due, paid_on ≤ today | DTO + `InvoiceService::recordPayment` (balance check needs DB, lives in service) |
| `auto_send` only with `auto_issue` | DTO (`prohibited_unless:auto_issue,true`) |
| Pohoda period: from ≤ to, ≤ 366 days | DTO (`PohodaExportData`) |
| EFA readiness (missing seller/buyer fields) | `UblInvoiceXmlBuilder::readiness()` → 422 in service |
| route param uuid | route `whereUuid` |

## Skills (for executor)
- agent-communication:
    - references/format-impl.md
- laravel-boost-discipline:
    - references/pint-workflow.md
    - references/test-policy.md
    - references/approval-gates.md
- laravel-13-conventions:
    - references/data-and-services.md
    - references/artisan-scaffolding.md
    - references/migrations-strategy.md
    - references/models.md
    - references/enums.md
    - references/permissions-policies.md
    - references/routing.md
    - references/jobs.md
    - references/activitylog.md
    - references/spatie-media-inertia.md
    - references/commands-and-config.md
    - references/eloquent-performance.md
    - references/inertia-v3.md
    - references/testing-and-seeders.md
    - references/pint-and-typecheck.md
- fe-principles
- vue-general:
    - references/components.md
    - references/composables.md
    - references/typescript.md
    - references/i18n-composables.md
    - references/inertia-admin-forms.md
    - references/anti-patterns-pattern-match.md

Executor notes (all parts):
- `php artisan typescript:transform` after every change in `app/Data/` or `app/Enums/`.
- Every user-facing string → key in `resources/lang/{sk,en,uk}/app.json` (parity enforced by `tests/Feature/Language/TranslationParityTest.php`, currently 1137 keys). Logs stay English.
- Tests: PHPUnit 12, `RefreshDatabase`, `actingAsTenantUser('Admin', $tenant)` / `bindTenant` helpers (see `tests/Feature/Invoices/InvoiceSendTest.php`), PDF rendering mocked via `$this->mock(RendersInvoicePdf::class)`.
- Lint gate per part: `pint --dirty --format agent`, `phpstan analyse --memory-limit=1G` (no new `app/` baseline entries), `pnpm lint:js`, `pnpm lint:prettier`, `pnpm typecheck`.

---

## Part 1 — payments
Depends on: none

### Intent
Replace the one-click `markPaid` (always `now()`, always full amount) with a payment record: the user picks the real payment date and amount, several payments per invoice are allowed, status flips to Paid only when the balance is settled. This also becomes the write path for Part 2's bulk pay and Part 7's bank matching.

### Constraints
- `InvoiceStatusEnum` cases and `canTransitionTo` unchanged (D7).
- `paid_at` keeps its meaning "moment the invoice became Paid" (Activitylog `logOnly` already covers it).
- `cancel()` still requires Issued|Overdue; a partially paid invoice may be cancelled, its payments stay (history). Paid invoices remain non-cancellable (existing guard).
- Dashboard/stats sums switch from `total` to open balance where they mean "unpaid" — numbers must stay correct for invoices with no payments (paid_amount 0).
- Route path `POST /invoices/{invoice}/pay` kept (FE URLs), controller method + policy ability renamed to `recordPayment`.

### Files
New:
- `database/migrations/2026_10_12_000100_create_invoice_payments_table.php` — payments table.
- `database/migrations/2026_10_12_000200_add_paid_amount_to_invoices_table.php` — denormalised `paid_amount`.
- `app/Models/InvoicePayment.php` — row model (BelongsToTenant, HasUuids, SoftDeletes, LogsActivity).
- `app/Enums/PaymentSourceEnum.php` — manual | bank_import (`#[TypeScript]`), reserved consumer Part 7.
- `app/Data/Invoices/InvoicePaymentData.php` — input DTO for one payment.
- `app/Data/Invoices/InvoicePaymentListItemData.php` — payment row for the detail page.
- `database/factories/InvoicePaymentFactory.php` — tests.
- `resources/js/Components/Invoices/InvoicePayModal.vue` — Precognition form modal (date, amount, type, note), precedent `InvoiceIssueModal.vue`.
- `resources/js/Components/Invoices/InvoicePaymentsCard.vue` — payments list on Show with delete per row.
- `tests/Feature/Invoices/InvoicePaymentTest.php` — service + HTTP coverage (there are no `markPaid` tests today).
Changed:
- `app/Models/Invoice.php` — `payments(): HasMany`, `paid_amount` cast + fillable, `balanceDue` = total − deposit − paid_amount, `isPartiallyPaid(): bool`, `isSettled(): bool`.
- `app/Services/InvoiceService.php` — `markPaid` → `recordPayment(Invoice, InvoicePaymentData, ?User $actor): InvoicePayment`, new `deletePayment(Invoice, InvoicePayment): void`, private `recomputePaymentState(Invoice): void`; `stats()` pending/overdue sums use open balance.
- `app/Services/Dashboard/DashboardMetricsQuery.php` — `unpaid_sum` / `overdue_sum` = `SUM(total - deposit - paid_amount)`.
- `app/Http/Controllers/InvoiceController.php` — `pay()` → `recordPayment(InvoicePaymentData, Invoice, Request)`, new `destroyPayment(Invoice, InvoicePayment)`.
- `app/Policies/InvoicePolicy.php` — `markPaid` → `recordPayment`, add `deletePayment` (both `EditInvoices`).
- `routes/web.php` — `POST /invoices/{invoice}/pay` moved into the Precognition group; `DELETE /invoices/{invoice}/payments/{payment}` (`whereUuid` both, `scopeBindings()`).
- `app/Data/Invoices/InvoiceListItemData.php`, `InvoiceDetailData.php` — `paid_amount`, `is_partially_paid`; detail adds `payments: InvoicePaymentListItemData[]`.
- `resources/js/Pages/Invoices/Index.vue`, `Show.vue` — `payConfirm` (ConfirmDeleteModal) replaced by `InvoicePayModal`; Show mounts `InvoicePaymentsCard`.
- `resources/js/Components/Invoices/InvoiceStatusBadge.vue` — optional `partiallyPaid` prop renders the derived state label.
- `resources/lang/{sk,en,uk}/app.json` — new keys (payment dialog, card, errors).
- `.claude/technical.md` invoices block (docs-agent, after review).

### Schema
`invoice_payments`: `id` uuid PK · `tenant_id` FK tenants restrict · `invoice_id` FK invoices restrict · `amount` decimal(12,2) NOT NULL · `paid_on` date NOT NULL · `payment_type` string NOT NULL (PaymentTypeEnum) · `source` string NOT NULL default `manual` (PaymentSourceEnum) · `external_reference` string(128) NULL (reason: bank-statement entry reference, Part 7) · `note` text NULL · `recorded_by_user_id` FK users restrict NULL (reason: unattended bank import has no actor) · timestamps · softDeletes. Indexes: `(tenant_id, invoice_id)`, `(tenant_id, paid_on)`. No UNIQUE → no partial index needed.
`invoices.paid_amount` decimal(12,2) NOT NULL default 0, placed after `deposit`.
Activity log: `InvoicePayment` yes (money) — `logOnly(['amount','paid_on','payment_type','source','external_reference'])`, `logOnlyDirty`.

### DTO contracts
```
InvoicePaymentData { paid_on: string (date, before_or_equal:today), amount: float (numeric, min 0.01, decimal:0,2), payment_type: PaymentTypeEnum, note: ?string (max 500) }
InvoicePaymentListItemData { id, paid_on, amount: string, payment_type: PaymentTypeEnum, source: PaymentSourceEnum, note: ?string, recorded_by_name: ?string, created_at: string }
InvoiceListItemData += { paid_amount: string, is_partially_paid: bool }
InvoiceDetailData  += { paid_amount: string, is_partially_paid: bool, payments: InvoicePaymentListItemData[] }
```

### Service contract
- `recordPayment(Invoice $invoice, InvoicePaymentData $data, ?User $actor): InvoicePayment` — guard `status->canTransitionTo(Paid)` (Issued|Overdue) else 422 `app.invoice_cannot_mark_paid`; guard `amount <= balance_due` else 422 key `amount` `app.invoice_payment_exceeds_balance`; transaction: create payment, `paid_amount += amount`, if `paid_amount >= total - deposit` → status Paid + `paid_at = paid_on` (as datetime start of day). Returns the payment.
- `deletePayment(Invoice, InvoicePayment)` — guard invoice not Cancelled (422 `app.invoice_payment_locked`); transaction: soft-delete payment, recompute `paid_amount` from remaining payments; if below settled and status Paid → status = `due_date < today ? Overdue : Issued`, `paid_at = null`.
- Bulk reuse (Part 2): `recordPayment` called per invoice with `amount = balance_due`.

### Acceptance criteria
- Recording a partial payment leaves status Issued, sets `paid_amount`, `is_partially_paid = true`; a second payment that settles the balance flips status to Paid with `paid_at` = that payment's date — check: `docker compose exec app php artisan test --filter=InvoicePaymentTest` exits 0.
- Overpayment (amount > balance_due) → 422 on `amount`; future `paid_on` → 422; Draft/Paid/Cancelled → 422 `status`; cross-tenant payment delete → 404.
- Deleting the settling payment reverts Paid → Issued (or Overdue when past due) and clears `paid_at`.
- `InvoiceService::stats()` pending sum and `DashboardMetricsQuery` unpaid/overdue sums reflect open balance (test with one partially paid invoice) — check: `--filter=InvoiceStatsTest`, `--filter=DashboardOverviewTest`.
- Show page lists payments with delete; Index/Show open `InvoicePayModal` prefilled (today, balance due, invoice payment type) — check: `pnpm typecheck` exits 0; Inertia prop assertions in `InvoicePaymentTest`.
- `TranslationParityTest`, pint, phpstan, eslint, prettier exit 0.

### Steps
1. Migrations + `InvoicePayment` model + `PaymentSourceEnum` + factory.
2. `Invoice` model: relation, cast, `balanceDue` includes `paid_amount`, predicates.
3. DTOs (`InvoicePaymentData`, `InvoicePaymentListItemData`, list/detail additions) → `typescript:transform`.
4. `InvoiceService::recordPayment` / `deletePayment` / `recomputePaymentState`; remove `markPaid`; grep callers (`markPaid` only in `InvoiceController::pay` today — verify with Grep).
5. Stats + dashboard sum expressions.
6. Controller, policy (ability rename), routes (Precognition group for `pay`, new DELETE).
7. FE: `InvoicePayModal`, `InvoicePaymentsCard`, badge prop, Index/Show wiring, lang keys ×3.
8. Tests (`InvoicePaymentTest` + stats/dashboard additions), lint gate.

### Test scenarios
- WHO: Admin/Účtovníčka with `edit invoices` can record/delete; `view invoices` only → 403.
- WHEN forbidden: Draft, Paid, Cancelled invoices; amount > balance; `paid_on` in the future; payment of another invoice/tenant in the DELETE route (404 via scoped binding + TenantScope).
- WHAT on failure: 422 with field key (`amount`, `paid_on`, `status`), no row written, `paid_amount` unchanged.
- EDGE: amount exactly equals balance (settles), deposit > 0 (balance = total − deposit − paid), two payments same day, delete the middle of three payments (status stays Paid if remaining still settle), cancel of a partially paid invoice keeps payments and creates the credit note as today.

---

## Part 2 — bulk-actions
Depends on: payments

### Intent
Row selection on the invoices list with a bulk bar: download one merged PDF, send by e-mail, mark paid (one payment date, each invoice's full balance), issue drafts; plus a duplicate button on the row. Partial success is normal and the result says which rows were skipped and why.

### Constraints
- `DataTable` is the shared skeleton component — extend with an opt-in `selectable` prop, no fork, default behaviour unchanged for every other page.
- Bulk code never re-implements a guard: it calls `recordPayment` / `send` / `issue` per invoice and catches `ValidationException`.
- Each invoice is its own transaction (the single-action methods already own theirs) — one failure never rolls back the others.
- Merged PDF: every page comes from the existing `RendersInvoicePdf::render()` output, merged binary-level by `qpdf`; no combined HTML.
- Cap: `config('invoicing.bulk.max_ids')` = 100 for pay/send/issue, `config('invoicing.bulk.pdf_max_ids')` = 10 for the merged PDF (D6).
- Ids from another tenant fall out of the `TenantScope`d query and are reported as skipped `not_found`, never 403 (no information leak across tenants).

### Files
New:
- `app/Contracts/MergesPdfDocuments.php` — `merge(array $pdfBinaries): string` (variation point: external binary).
- `app/Services/Pdf/QpdfMerger.php` — writes inputs to a temp dir under `storage/app/tmp/pdf-merge/<uuid>/`, runs `qpdf --empty --pages <in...> -- <out>` via `Illuminate\Support\Facades\Process::timeout(config('invoicing.bulk.merge_timeout_seconds', 60))`, throws `PdfMergeFailedException` on non-zero exit, always cleans the temp dir.
- `app/Exceptions/PdfMergeFailedException.php` — `final class … extends RuntimeException`, carries exit code + stderr; `InvoiceBulkController::pdf` catches it, logs `invoice.bulk_pdf.merge_failed` (English, with stderr) and redirects back with flash `error` `app.invoice_bulk_pdf_failed`. `app/Exceptions/` does not exist yet — it is the framework-standard directory that `php artisan make:exception PdfMergeFailedException --no-interaction` scaffolds (not a new top-level dir per `laravel-boost-discipline/references/approval-gates.md`); created deliberately because tests assert the type and the controller must not catch a bare `RuntimeException`. This is the only custom exception in the plan.
- `app/Services/InvoiceBulkService.php` — `pay(array $ids, string $paidOn, ?User): BulkActionResultData`, `send(array $ids): BulkActionResultData`, `issue(array $ids, ?User): BulkActionResultData`, `mergedPdf(array $ids): string`; reason: bulk orchestration + skipped-row reporting is one responsibility, keeps `InvoiceService` focused on single-invoice actions.
- `app/Data/Invoices/InvoiceBulkActionData.php` — `ids`, optional `paid_on`.
- `app/Data/Invoices/BulkActionResultData.php` + `BulkSkippedItemData.php` — flash payload.
- `app/Enums/BulkSkipReasonEnum.php` — not_found | not_issued | no_email | not_draft | not_payable | supplier_incomplete | number_taken (`#[TypeScript]`, label per key).
- `app/Http/Controllers/InvoiceBulkController.php` — four actions, thin.
- `resources/js/Components/Invoices/InvoiceBulkActionsBar.vue` — sticky bar above the table: count, buttons gated by `allows(...)`, opens pay modal / confirm modals / download link.
- `resources/js/Components/Invoices/InvoiceBulkResultModal.vue` — processed count + skipped rows table with translated reasons.
- `resources/js/Components/Invoices/InvoiceBulkPayModal.vue` — date only (reuses `DateInput`), submits `ids + paid_on`.
- `tests/Feature/Invoices/InvoiceBulkActionsTest.php`, `tests/Feature/Invoices/InvoiceBulkPdfTest.php`, `tests/Unit/Services/QpdfMergerTest.php` (skipped unless `qpdf` binary present via `shell_exec('command -v qpdf')`; CI container has it).
Changed:
- `Dockerfile` and `Dockerfile.dev` — `apk add --no-cache qpdf` (devops-agent; owner approved).
- `config/invoicing.php` — `bulk` block (`max_ids`, `pdf_max_ids`, `merge_timeout_seconds`, `qpdf_binary` default `qpdf`).
- `app/Providers/AppServiceProvider.php` — bind `MergesPdfDocuments` → `QpdfMerger`.
- `routes/web.php` — `POST /invoices/bulk/pay|send|issue` (Precognition group), `GET /invoices/bulk/pdf` (ids as query `ids[]`); declared BEFORE `/invoices/{invoice}` routes (literal segment `bulk` is not a uuid, `whereUuid` already prevents the clash — keep order anyway).
- `app/Policies/InvoicePolicy.php` — `bulkPay`, `bulkSend`, `bulkIssue` (EditInvoices), `bulkPdf` (ViewInvoices) class-level abilities.
- `app/Http/Middleware/HandleInertiaRequests.php` — flash gains `bulk_result` (array|null) from session key `bulk_result`.
- `resources/js/types/index.d.ts` — `SharedProps.flash.bulk_result?: App.Data.Invoices.BulkActionResultData | null`.
- `resources/js/Components/DataTable/DataTable.vue` — `selectable?: boolean`, `selected` v-model (`string[]`), header checkbox (checked / indeterminate for current page), row checkbox cell (first column, `aria-label` with row identifier), selection reset on `rows` change; `rowKey` required when `selectable` (dev-time warning via `console.warn` guarded by `import.meta.env.DEV`). `isInteractiveTarget` already excludes `input` so row-click navigation is unaffected.
- `resources/js/types/table.ts` — no change needed (selection is a prop, not a column).
- `resources/js/Pages/Invoices/Index.vue` — `selectable`, `v-model:selected`, bar + modals, duplicate row button (`router.post('/invoices/{id}/duplicate')`, `allows('create invoices')`), watches `flash.bulk_result` to open the result modal.
- `resources/lang/{sk,en,uk}/app.json`.

### DTO contracts
```
InvoiceBulkActionData { ids: string[] (required, array, min 1, max config max_ids, each uuid, distinct), paid_on: ?string (date, before_or_equal:today; required only for pay — enforced in controller DTO variant InvoiceBulkPayData extends with Required) }
BulkActionResultData { action: 'pay'|'send'|'issue', processed: int, skipped: BulkSkippedItemData[] }
BulkSkippedItemData { id: string, number: ?string, reason: BulkSkipReasonEnum }
```
Merged PDF request: `GET /invoices/bulk/pdf?ids[]=…` validated by `InvoiceBulkPdfData { ids: string[] (min 1, max pdf_max_ids, uuid, distinct) }` resolved from query; response `application/pdf`, filename `faktury-<YYYY-MM-DD>.pdf`, `Content-Disposition: attachment` via `HeaderUtils::makeDisposition` (precedent `InvoiceController::pdf`). Order of pages = order of `ids` as sent (FE sends in table order).

### Behaviour
- `InvoiceBulkService` loads `Invoice::query()->whereIn('id', $ids)->get()->keyBy('id')`; iterates `$ids` in input order; missing → `not_found`; calls the single action inside `try { … } catch (ValidationException $e)` mapping the error key to a reason (`status`→ per action, `customer_email`→`no_email`, `supplier`→`supplier_incomplete`, `number`→`number_taken`); never catches other throwables.
- Controller flashes `bulk_result` + `success` (`app.invoice_bulk_done` with `:processed/:total`) and redirects back (`preserveScroll` on FE).
- Merged PDF: service renders each via `RendersInvoicePdf`, calls `MergesPdfDocuments::merge`; one id merges without qpdf (returns the single binary — keeps the contract call count honest in tests).
- FE bar actions: pay → `InvoiceBulkPayModal` (date default today); send/issue → `ConfirmDeleteModal` (variant primary/success) with count; PDF → `<a :href>` with `ids[]` query, `target="_blank"`, disabled above `pdf_max_ids` with tooltip; selection clears after any successful bulk action (page reload resets `rows`).

### Acceptance criteria
- Mixed selection (draft, issued with email, issued without email, overdue, paid, cancelled, other-tenant id): bulk send processes exactly the issued-with-email rows and reports every other row with the right reason; bulk pay processes issued+overdue with `amount = balance_due` and the chosen `paid_on`; bulk issue processes drafts only (supplier incomplete → all drafts skipped `supplier_incomplete`, no sequence consumed) — check: `php artisan test --filter=InvoiceBulkActionsTest` exits 0.
- More than cap ids → 422 on `ids`; empty → 422; non-uuid → 422.
- `GET /invoices/bulk/pdf` with 3 ids calls `RendersInvoicePdf::render` 3 times and `MergesPdfDocuments::merge` once with 3 binaries in the requested order; 1 id → no merge call; > `pdf_max_ids` → 422; draft ids allowed (draft PDF exists today) — check: `--filter=InvoiceBulkPdfTest`.
- `QpdfMergerTest` merges two minimal PDFs produced by the chrome driver fixture (or two static fixture PDFs under `tests/Fixtures/pdf/`) and asserts page count 2 via `qpdf --show-npages`; non-zero exit → `PdfMergeFailedException`; temp dir removed.
- `DataTable` without `selectable` renders exactly as before (snapshot-free: existing Users/Clients Inertia tests still pass).
- `docker compose exec app qpdf --version` exits 0 after rebuild; `vue-tsc`, eslint, prettier, pint, phpstan, parity exit 0.

### Steps
1. Devops: `qpdf` in both Dockerfiles (`apk add --no-cache qpdf`), `config/invoicing.php` bulk block, smoke line in the Dockerfile (`qpdf --version`).
2. `MergesPdfDocuments` + `QpdfMerger` + exception + binding + unit test.
3. Enums/DTOs (`BulkSkipReasonEnum`, `InvoiceBulkActionData`, `InvoiceBulkPdfData`, result DTOs) → `typescript:transform`.
4. `InvoiceBulkService` + policy abilities + `InvoiceBulkController` + routes + `HandleInertiaRequests` flash key.
5. Feature tests for pay/send/issue/pdf.
6. FE: `DataTable` selection, `InvoiceBulkActionsBar`, `InvoiceBulkPayModal`, `InvoiceBulkResultModal`, Index wiring, duplicate row button, `SharedProps` type, lang keys ×3.
7. Lint gate.

### Test scenarios
- WHO: `edit invoices` for pay/send/issue, `view invoices` for PDF; a user without `edit invoices` → 403 on POST bulk routes even with valid ids.
- WHEN forbidden: cap exceeded; ids of another tenant (skipped, not 403); supplier incomplete during bulk issue (all drafts skipped, nothing issued, no number consumed).
- WHAT on failure: per-row skip with reason, `processed` count correct, HTTP 302 back with `bulk_result`; qpdf failure → 500 page with logged stderr, no partial file served.
- EDGE: duplicate ids rejected by `distinct`; same invoice in pay twice in one request impossible (distinct); bulk send stamps `sent_at` per invoice via the existing listener; order of PDF pages equals ids order; the row's "duplicate" button creates a Draft and redirects to edit (existing endpoint).

---

## Part 3 — mail-texts
Depends on: none (touches `InvoiceIssued`, which Part 2 and Part 4 only call)

### Intent
Tenant-editable subject and body for the invoice e-mail in Settings → Invoicing with `{{placeholders}}`; empty fields keep today's fixed translations.

### Constraints
- One subject + one body per tenant (D8). Body is plain text; rendered escaped with line breaks (`nl2br(e(...))`) inside the existing `mail/invoice.blade.php` shell — no HTML editor.
- Placeholder syntax and resolution reuse `PlaceholderResolverService::resolve()`; unknown tokens stay verbatim (same rule as contracts).
- `InvoiceIssued::toMail` runs in the queue without tenant context — load `TenantInterface` by `$invoice->tenant_id` explicitly (never `current_tenant_id()`), precedent `SupplierSignatureResolver`.

### Files
New:
- `database/migrations/2026_10_12_000300_add_invoice_mail_texts_to_tenant_interfaces_table.php` — `invoice_mail_subject` string(255) NULL, `invoice_mail_body` text NULL.
- `app/Data/Invoices/InvoiceMailPlaceholderCatalogData.php` — tokens for the settings card (`PlaceholderTokenData[]`, reuse `App\Data\Contracts\PlaceholderTokenData`).
- `resources/js/Components/Invoices/InvoiceSettingsMailCard.vue` — subject `TextInput`, body `TextareaInput` (6 rows), token list via `Components/Contracts/PlaceholderTokenList.vue` inserting at cursor (`TextareaInput.insertAtCursor`, precedent `ContractBodyEditor`).
- `tests/Feature/Invoices/InvoiceMailTextTest.php`, `tests/Feature/Settings/InvoiceMailSettingsTest.php`.
Changed:
- `app/Models/TenantInterface.php` — fillable + `logOnly` + docblock for the two columns.
- `app/Data/Invoices/InvoiceSettingsData.php` — `invoice_mail_subject: ?string (max 255)`, `invoice_mail_body: ?string (max 5000)`; `fromTenant` reads them.
- `app/Services/InvoiceSettingsService.php` — writes the two interface fields.
- `app/Services/PlaceholderResolverService.php` — `invoiceMailVariables(Invoice): array<string, string|null>` + `invoiceMailCatalog(): InvoiceMailPlaceholderCatalogData`. Tokens: `invoice.number`, `invoice.variable_symbol`, `invoice.total` (via `CurrencyEnum::format`), `invoice.balance_due`, `invoice.issue_date`, `invoice.due_date` (d.m.Y), `invoice.period` (`period_from – period_to` or empty), `customer.name`, `supplier.name`, `supplier.iban`, `supplier.contact_email`, `supplier.contact_phone`.
- `app/Notifications/InvoiceIssued.php` — resolves subject/body: interface text when non-empty else `__('app.invoice_mail_subject')` / `__('app.invoice_mail_body')`; passes `bodyText` to the view.
- `resources/views/mail/invoice.blade.php` — renders `$bodyText` (escaped + `nl2br`) in place of the fixed greeting/body paragraphs when custom text present; detail table (number, due date, IBAN, VS) and attachment note stay.
- `app/Http/Controllers/InvoiceSettingsController.php` — show props gain `mailPlaceholders` (catalog DTO).
- `resources/js/Components/Invoices/InvoiceSettingsForm.vue` — form fields + card; `resources/js/Pages/Settings/Invoicing.vue` and `InvoiceSettingsDrawer.vue` / `useInvoiceSettingsDrawer.ts` pass `mailPlaceholders`.
- `resources/lang/{sk,en,uk}/app.json` — card labels, hint, token labels `invoice_mail_token_*`.

### DTO contracts
```
InvoiceSettingsData += { invoice_mail_subject: ?string, invoice_mail_body: ?string }
InvoiceMailPlaceholderCatalogData { tokens: PlaceholderTokenData[] }   // token already brace-wrapped, label translated
```

### Acceptance criteria
- Settings PUT persists both fields; GET returns them; `max` limits enforced — check: `php artisan test --filter=InvoiceMailSettingsTest` exits 0.
- With custom subject `Faktúra {{invoice.number}} – {{customer.name}}` the queued mail subject equals the resolved string; with empty fields the subject/body equal today's lang keys; unknown token stays verbatim; body line breaks become `<br>` and HTML in the body is escaped — check: `--filter=InvoiceMailTextTest` (uses `Notification::fake` + direct `toMail()` render with `RendersInvoicePdf` mocked).
- Mail resolution works without tenant context (test calls `toMail` from a bound-less context).
- Settings page shows the card with insertable tokens; `vue-tsc` exits 0.

### Steps
1. Migration + `TenantInterface` + DTO + service write path.
2. `PlaceholderResolverService::invoiceMailVariables/Catalog` + catalog DTO.
3. `InvoiceIssued` + blade.
4. Controller props + FE card + form wiring + lang keys ×3.
5. Tests, lint gate.

### Test scenarios
- WHO: `manage billing settings` edits texts; others 403 (existing gate).
- WHEN forbidden: body > 5000 chars, subject > 255 → 422.
- WHAT on failure: nothing written (transaction in `InvoiceSettingsService`).
- EDGE: tenant with null interface fields → fallback; invoice without number (draft send is blocked anyway) → `{{invoice.number}}` resolves to empty string; `{{invoice.period}}` empty when no period; currency formatting uses `CurrencyEnum::format` (NBSP).

---

## Part 4 — recurring-auto-send
Depends on: mail-texts (uses the final `InvoiceIssued` contract), payments (none functionally)

### Intent
A recurring template can send the generated invoice by e-mail right after auto-issue.

### Constraints
- `auto_send` meaningful only with `auto_issue` (DTO rule); the job never sends a Draft.
- Supplier-incomplete path (D2b) already skips issue → also skips send, one warning log line.
- No customer e-mail → skip send, `Log::warning('recurring_invoice.auto_send.skipped_no_email', [...])`, schedule still advances.

### Files
New:
- `database/migrations/2026_10_12_000400_add_auto_send_to_recurring_invoices_table.php` — `auto_send` boolean NOT NULL default false after `auto_issue`.
Changed:
- `app/Models/RecurringInvoice.php` — fillable, cast, `logOnly` + docblock.
- `app/Data/RecurringInvoices/RecurringInvoiceUpsertData.php` — `auto_send: bool = false`, rule `prohibited_unless:auto_issue,true` via `rules()` (accepted values: false allowed always; true only with auto_issue true).
- `app/Data/RecurringInvoices/RecurringInvoiceDetailData.php`, `RecurringInvoiceListItemData.php` (detail only unless list shows it — detail only).
- `app/Services/RecurringInvoiceService.php` — create/update write `auto_send`.
- `app/Jobs/GenerateRecurringInvoiceJob.php` — after a successful `issue(...)`: `if ($ri->auto_send) { $invoice->customer_email !== null ? $invoiceService->send($invoice) : Log::warning(...) }` inside the same transaction (notification is `afterCommit`).
- `resources/js/Components/RecurringInvoices/RecurringInvoiceForm.vue` — `ToggleInput field="auto_send"` shown/enabled only when `auto_issue` is on (set false when auto_issue turns off), hint key.
- `resources/js/Components/RecurringInvoices/RecurringScheduleCard.vue` — shows auto-send state.
- `tests/Feature/RecurringInvoices/GenerateRecurringInvoiceJobTest.php` (+ cases), `RecurringInvoiceControllerTest.php` (+ validation case).
- `resources/lang/{sk,en,uk}/app.json`.

### Acceptance criteria
- Template `auto_issue=true, auto_send=true` with customer e-mail: job issues and queues exactly one `InvoiceIssued` to that e-mail (`Notification::assertSentOnDemand`); without e-mail: no notification, warning logged, `next_run_at` advanced; `auto_send=true` with supplier incomplete: no notification; `auto_send=false`: no notification — check: `php artisan test --filter=GenerateRecurringInvoiceJobTest` exits 0.
- POST/PUT with `auto_send=true, auto_issue=false` → 422 on `auto_send` — check: `--filter=RecurringInvoiceControllerTest`.
- Form toggle disabled while `auto_issue` is off; `vue-tsc` exits 0.

### Steps
1. Migration + model + DTOs + service.
2. Job branch.
3. FE form + card + lang keys ×3.
4. Tests, lint gate.

### Test scenarios
- WHO: unchanged (`edit recurring_invoices`).
- WHEN forbidden: `auto_send` without `auto_issue`.
- WHAT on failure: 422, template unchanged.
- EDGE: tenant default state `Issued` + template `auto_issue=false` + `auto_send=true` → DTO rejects (auto_send needs the template flag, not the tenant default) — documented in the hint key; send guard `status !== Issued` cannot trigger (issue just happened) but stays in `send()`.

---

## Part 5 — pohoda-export
Depends on: payments (schema), none functionally beyond invoices

### Intent
Download a Stormware Pohoda XML `dataPack` of issued invoices (and credit notes) for a date range so the accountant imports them into Pohoda.

### Constraints
- Synchronous XML download by design (D13 — deviation from `exports.md` "every export queued"): XMLWriter stream, no Chromium, `items` eager-loaded in one query, period ≤ 366 days, ≤ `config('pohoda.max_invoices')` (2000) rows else 422 `app.pohoda_export_too_many`.
- Statuses exported: Issued, Overdue, Paid, Cancelled (a cancelled invoice is still a tax document; its credit note is exported as its own item). Drafts never.
- Non-VAT payer tenant: every item `rateVAT = none`, summary only `priceNone`; VAT payer: rates mapped `23 → high`, `19 → low`, `5 → third`, `0 → none` (rate→band map in `config/pohoda.php`, owner can adjust when legislation changes).
- `dataPack` `ico` = tenant ICO digits (empty when tenant has none → 422 `app.pohoda_export_requires_ico`).
- Encoding UTF-8 with explicit XML declaration (assumption A5-1 below).

### Assumptions (Pohoda specifics — owner may veto)
- A5-1 UTF-8 output; `config('pohoda.encoding')` allows `windows-1250` (converted via `mb_convert_encoding`) if the accountant's Pohoda rejects UTF-8.
- A5-2 Payments are NOT included in the export (`inv:liquidation` omitted) — payments are booked in Pohoda from the bank statement; pre-liquidating would double-book. Toggle later if the accountant asks.
- A5-3 Credit notes (`credited_invoice_id` set) export as `invoiceType = issuedCreditNotice` with positive amounts and `inv:invoiceHeader/inv:text` referencing the original number; the cancelled original stays `issuedInvoice`.
- A5-4 Our number is forced via `inv:number/typ:numberRequested`; `symVar` = `variable_symbol`, `symConst` = `constant_symbol`.
- A5-5 Document dates: `date` = `issue_date`, `dateTax` = `delivery_date`, `dateAccounting` = `issue_date`, `dateDue` = `due_date`.
- A5-6 Partner: `typ:address` from customer snapshot (`company` = `customer_name`, `ico`, `dic`, `icDph` = `customer_vat_number`, `street`, `city`, `zip`, `country` ISO2 when 2 chars).
- A5-7 Item: `text` = description, `quantity`, `unit`, `homeCurrency/unitPrice` = `unit_price`, discount applied via `discountPercentage`, `payVAT = false` (prices entered without VAT), `rateVAT` per map; summary `roundingDocument` = `none|math2one` per `rounding_mode` (document → `math2one`, cash_005 → `none` + explicit `rounding_amount` as a last item "Zaokrúhlenie" when non-zero).
- A5-8 Currency: only EUR exported as `homeCurrency`; non-EUR invoices → skipped and listed in the response header `X-Pohoda-Skipped` count + flash? No — sync download cannot flash; they are listed inside the XML as `<dat:note>` and in the modal preview count (FE calls `GET /invoices/export/pohoda/preview?from&to` → JSON `PohodaExportPreviewData { count, skipped_non_eur }`).
- A5-9 `dataPackItem id` = invoice number; `dataPack id` = `cleanmaster-<tenant-ico>-<from>-<to>`; `application = "CleanMaster"`, `version = "2.0"`, namespaces `dat`, `inv`, `typ` version_2.

### Files
New:
- `config/pohoda.php` — namespaces, encoding, `vat_band_map`, `application` name, `max_invoices` (2000).
- `app/Data/Invoices/PohodaExportData.php` — `from`, `to` (dates, `to` ≥ `from`, span ≤ 366 days).
- `app/Data/Invoices/PohodaExportPreviewData.php` — counts for the modal.
- `app/Services/Export/PohodaXmlBuilder.php` — `build(Tenant, Collection<Invoice>): string` (XMLWriter), `preview(Tenant, PohodaExportData): PohodaExportPreviewData`, private per-document/item/summary writers; reason: single responsibility = Pohoda document shape.
- `app/Http/Controllers/InvoiceExportController.php` — `pohoda(PohodaExportData)` download, `pohodaPreview(PohodaExportData)` JSON (returns the DTO).
- `resources/js/Components/Invoices/InvoicePohodaExportModal.vue` — two `DateInput`s (default previous calendar month), preview count via Inertia `useHttp`-free plain `fetch`? No — use the project's `axios`/Inertia-free request helper if present, else `router.get` with `only` is wrong for JSON; use `window.fetch` wrapped in a small `usePohodaPreview` composable (`resources/js/Composables/usePohodaPreview.ts`) with loading/error branches; download via `window.open(url)`.
- `tests/Feature/Invoices/PohodaExportTest.php`, `tests/Unit/Services/PohodaXmlBuilderTest.php`.
Changed:
- `routes/web.php` — `GET /invoices/export/pohoda`, `GET /invoices/export/pohoda/preview` (before `/invoices/{invoice}`).
- `app/Policies/InvoicePolicy.php` — `export` (ViewInvoices).
- `resources/js/Pages/Invoices/Index.vue` — header button "Export do Pohody" (`allows('view invoices')`) opening the modal.
- `resources/lang/{sk,en,uk}/app.json`.

### Acceptance criteria
- XML is well-formed, root `dat:dataPack` with `ico` = tenant ICO, one `dat:dataPackItem` per exported invoice in `issue_date` order; XPath assertions for header fields, partner, items, summary; non-VAT tenant → every `inv:rateVAT = none` and no `priceLow/High` elements; VAT tenant → bands mapped; credit note → `issuedCreditNotice` with positive amounts — check: `php artisan test --filter=PohodaXmlBuilderTest` exits 0.
- Period filter: drafts, other tenants, invoices outside the range excluded; `to < from` → 422; span > 366 d → 422; more than `max_invoices` rows → 422 `app.pohoda_export_too_many` (test with config override to 2); tenant without ICO → 422; response headers `Content-Type: application/xml; charset=UTF-8`, attachment filename `pohoda-<from>-<to>.xml` — check: `--filter=PohodaExportTest`.
- Preview endpoint returns counts for the same filter.
- Modal: loading + error branches on preview, download button disabled when count = 0; `vue-tsc` exits 0.

### Steps
1. `config/pohoda.php`, DTOs, policy ability, routes.
2. `PohodaXmlBuilder` + unit tests (fixture tenants: VAT payer, non-VAT payer; invoice with discount, deposit, rounding, credit note).
3. Controller + feature tests.
4. FE modal + composable + Index button + lang keys ×3.
5. Lint gate.

### Test scenarios
- WHO: `view invoices` downloads; without it 403.
- WHEN forbidden: invalid range, missing tenant ICO.
- WHAT on failure: 422 (no file), 403.
- EDGE: invoice with deposit (summary reflects `total`, deposit noted in `inv:note` — assumption), non-EUR invoice skipped and counted, invoice cancelled + its credit note both present, `rounding_amount` ≠ 0 emitted as last item, description with `<`/`&` escaped by XMLWriter, 0 invoices → XML with empty dataPack still valid.

---

## Part 6 — efa-preparation
Depends on: payments (DTO shape), bulk-actions (actions card layout), mail-texts (none), pohoda-export (none) — runs last per D4

### Intent
Make the data model and document generation ready for Slovak e-invoicing (IS EFA / Peppol BIS Billing 3.0): identifiers on seller and buyer, UBL 2.1 XML generation + download, status tracking, and a provider seam with a null implementation. No live submission.

### Regulatory facts — behind config, flagged uncertain
- `config/efa.php`: `driver` (`null`), `customization_id` (`urn:cen.eu:en16931:2017#compliant#urn:fdc:peppol.eu:2017:poacc:billing:3.0`), `profile_id` (`urn:fdc:peppol.eu:2017:poacc:billing:01:1.0`), `seller_scheme_id` for IČO (default `0231`? UNCERTAIN — verify ICD code for Slovak IČO; config so the owner's accountant can fix), `vat_scheme_id` (`VAT`), `unit_code_map` (`ks→C62, hod→HUR, m2→MTK, m→MTR, mesiac→MON, deň→DAY, bal→PK, l→LTR`, default `C62`), `payment_means_map` (transfer→`30`, cash→`10`, card→`48`, cod→`10`, other→`ZZZ`), `mandatory_from` (`2027-01-01`, informational, UNCERTAIN), `applies_to_non_vat_payers` (false, UNCERTAIN).
- Facts the plan does not depend on but the owner must confirm with the accountant before any live driver: start date and scope (VAT payers, domestic B2B), decentralised Peppol model vs direct IS EFA submission, Slovak CIUS extras, Peppol scheme for SK entities, non-payer obligations.

### Constraints
- Owner's tenants are non-VAT payers: XML for `is_vat_payer=false` uses `TaxCategory/ID = O`, `TaxExemptionReasonCode = VATEX-EU-O`, no VAT breakdown lines, `TaxTotal/TaxAmount = 0`; VAT payer path emits `S` per rate + `Z` for rate 0 — both paths tested.
- XML generated from the invoice SNAPSHOT columns only (supplier_*/customer_*), never live tenant/client data — same rule as the PDF.
- Download allowed for any non-Draft invoice with a number; submit only via the contract; null driver → 422 `app.efa_not_configured` (thrown as `ValidationException` key `efa` by `NullEInvoiceAccessPoint`; `EInvoiceService::submit` does not catch it, so status stays unchanged).
- `efa_status` column (exists, string nullable) gets a backed enum; `efa_id` kept; new columns additive.
- Invoice becomes `HasMedia` with collection `efa_xml` on `DOCUMENTS_DISK` (private), NOT `singleFile` (retain every submitted version); `Media.tenant_id` hook already requires a bound tenant → the submit path runs in HTTP context (tenant bound); document this in the service.

### Files
New:
- `database/migrations/2026_10_12_000500_add_peppol_fields_for_efa.php` — `tenants.peppol_participant_id` string(64) NULL, `clients.peppol_participant_id` string(64) NULL, `invoices.customer_peppol_participant_id` string(64) NULL + `supplier_peppol_participant_id` string(64) NULL (snapshots, stamped at create/update like other snapshot columns), `invoices.efa_submitted_at` timestamp NULL, `invoices.efa_last_error` text NULL; `efa_status` default `none` via backfill `UPDATE … WHERE efa_status IS NULL`.
- `app/Enums/EfaStatusEnum.php` — none | ready | generated | submitted | accepted | rejected | failed (`#[TypeScript]`, `label()`), `canSubmit()` (none|ready|generated|rejected|failed).
- `app/Contracts/SubmitsEInvoices.php` — `submit(Invoice $invoice, string $xml): EInvoiceSubmissionResult`.
- `app/Services/Efa/EInvoiceSubmissionResult.php` — final readonly value object `{ accepted: bool, providerId: ?string, status: EfaStatusEnum, message: ?string }`.
- `app/Services/Efa/NullEInvoiceAccessPoint.php` — throws `ValidationException::withMessages(['efa' => [__('app.efa_not_configured')]])` (house pattern for 422 from services, same key the readiness check uses); reason: keeps the seam honest without a fake "success" and needs no custom exception class.
- `app/Services/Efa/UblInvoiceXmlBuilder.php` — `build(Invoice): string`, `readiness(Invoice): string[]` (missing: supplier_ico, supplier_address_line/city/postal_code/country, supplier_vat_number when VAT payer, customer_name, customer_country, customer_ico or peppol id, number, due_date), private writers for party, lines, tax totals, monetary totals, payment means (IBAN → `PayeeFinancialAccount`), `BillingReference` for credit notes (`CreditNote` root, positive amounts).
- `app/Services/Efa/EInvoiceService.php` — `xml(Invoice): string` (guard non-Draft + readiness → 422 key `efa` with the missing list), `submit(Invoice, ?User): Invoice` (transaction: build, store media `efa_xml`, call contract, persist `efa_status/efa_id/efa_submitted_at/efa_last_error`, activity log), `markReady(Invoice)` used by `issue()` hook? No hook — `efa_status` moves `none → ready` lazily in `xml()` when readiness passes (no coupling into `InvoiceService::issue`).
- `app/Data/Invoices/EInvoiceStatusData.php` — `{ status: EfaStatusEnum, efa_id: ?string, submitted_at: ?string, last_error: ?string, missing_fields: string[], xml_available: bool }` embedded in `InvoiceDetailData.efa`.
- `app/Http/Controllers/EInvoiceController.php` — `xml(Invoice)` download, `submit(Invoice, Request)`.
- `resources/js/Components/Invoices/EfaStatusBadge.vue`, `resources/js/Components/Invoices/InvoiceEfaCard.vue` (status, missing fields list, download XML link, submit button gated by `canSubmit` + `edit invoices`, confirm modal).
- `tests/Feature/Invoices/EInvoiceXmlTest.php`, `tests/Feature/Invoices/EInvoiceSubmitTest.php`, `tests/Unit/Services/UblInvoiceXmlBuilderTest.php`, `tests/Feature/Settings/PeppolIdTest.php`, `tests/Feature/Clients/ClientPeppolIdTest.php`.
Changed:
- `app/Models/Invoice.php` — `implements HasMedia`, `InteractsWithMedia`, `registerMediaCollections()` (`efa_xml`, disk `config('invoicing.signature.disk')` reuse of `DOCUMENTS_DISK`), casts `efa_status`, fillable for new columns, docblock.
- `app/Models/Tenant.php`, `app/Models/Client.php` — fillable + `logOnly` for `peppol_participant_id`.
- `app/Services/InvoiceService::buildAttributes` — snapshot both Peppol ids.
- `app/Data/Invoices/InvoiceSettingsData.php` (+ `fromTenant`), `app/Data/Clients/ClientUpsertData.php`, `ClientDetailData` — `peppol_participant_id: ?string (max 64, regex ^\d{4}:[A-Za-z0-9\-\.]+$)`.
- `app/Services/InvoiceSettingsService.php`, `app/Services/ClientService.php` — write the field.
- `app/Data/Invoices/InvoiceDetailData.php` — `efa: EInvoiceStatusData`.
- `app/Policies/InvoicePolicy.php` — `downloadEfaXml` (ViewInvoices), `submitEfa` (EditInvoices).
- `app/Providers/AppServiceProvider.php` — bind `SubmitsEInvoices` by `config('efa.driver')` (`null` → `NullEInvoiceAccessPoint`).
- `routes/web.php` — `GET /invoices/{invoice}/efa-xml`, `POST /invoices/{invoice}/efa-submit`.
- `resources/js/Components/Invoices/InvoiceSettingsSupplierCard.vue`, `resources/js/Components/Clients/ClientForm.vue`, `ClientDetailCard.vue` — Peppol ID field/display.
- `resources/js/Pages/Invoices/Show.vue` — mounts `InvoiceEfaCard` under the actions card.
- `resources/lang/{sk,en,uk}/app.json`.
- `config/efa.php` (new, listed above).

### DTO contracts
```
EInvoiceStatusData { status: EfaStatusEnum, efa_id: ?string, submitted_at: ?string, last_error: ?string, missing_fields: string[], xml_available: bool }
InvoiceDetailData += { efa: EInvoiceStatusData }
InvoiceSettingsData += { peppol_participant_id: ?string }
ClientUpsertData += { peppol_participant_id: ?string }
```

### Acceptance criteria
- Non-VAT-payer issued invoice → XML with `cbc:CustomizationID`/`ProfileID` from config, `cac:TaxCategory/cbc:ID = O` + `VATEX-EU-O`, `TaxAmount 0`, every line with `unitCode` from the map (unknown unit → `C62`), `PaymentMeansCode` mapped, `PayeeFinancialAccount/ID` = IBAN, `LegalMonetaryTotal` consistent with `subtotal/total/deposit` (`PrepaidAmount`), VAT payer invoice → `S` categories grouped per rate equal to `vat_breakdown`; credit note → `CreditNote` root with `BillingReference` — check: `php artisan test --filter=UblInvoiceXmlBuilderTest` exits 0 (XPath assertions, well-formedness via `DOMDocument::loadXML`).
- `GET /invoices/{id}/efa-xml`: Draft → 422 `status`; missing seller IČO → 422 `efa` listing `supplier_ico`; happy → `application/xml` attachment `<number>.xml`, `efa_status` becomes `ready` — check: `--filter=EInvoiceXmlTest`.
- `POST /invoices/{id}/efa-submit` with null driver → 422 `app.efa_not_configured`, no media stored, status unchanged; with a test double bound to `SubmitsEInvoices` returning accepted → media `efa_xml` count 1, `efa_status = submitted|accepted`, `efa_id`, `efa_submitted_at` set, activity logged; double throwing a non-validation `Throwable` → caught in `EInvoiceService::submit`, `efa_status = failed`, `efa_last_error` = message, no media (media is written only after provider success, in the same transaction) — check: `--filter=EInvoiceSubmitTest`.
- Peppol ID validated on settings + client forms (regex), persisted, snapshotted onto new invoices — check: `--filter=PeppolIdTest`, `--filter=ClientPeppolIdTest`.
- Show page card renders status + missing fields; `vue-tsc` exits 0.

### Steps
1. `config/efa.php`, migration, enum, model changes (`HasMedia`), DTO fields, settings/client write paths → `typescript:transform`.
2. `UblInvoiceXmlBuilder` + unit tests (fixtures: non-VAT, VAT, credit note, deposit, discount).
3. Contract + null driver + exception + binding + `EInvoiceService` + value object.
4. Controller, policy, routes, `InvoiceDetailData.efa`, feature tests.
5. FE: badge, card, settings + client fields, Show wiring, lang keys ×3.
6. Lint gate; docs-agent adds the uncertain-facts list to `.claude/business.md` roadmap.

### Test scenarios
- WHO: `view invoices` downloads XML; `edit invoices` submits; `manage billing settings` edits seller Peppol ID; `edit clients` edits buyer Peppol ID.
- WHEN forbidden: Draft download; readiness failures; submit when `efa_status` is submitted|accepted (422 `app.efa_already_submitted`); null driver.
- WHAT on failure: 422 with field keys (`ValidationException` passes through untouched); provider `Throwable` → `failed` + message stored, no media.
- EDGE: unit with trailing spaces/case (`Hod`) maps case-insensitively; invoice with rounding_amount ≠ 0 → `PayableRoundingAmount`; deposit → `PrepaidAmount` and `PayableAmount = balance`; customer without IČO but with Peppol id passes readiness; both missing → fails; re-submission after `rejected` allowed and stores a second media item.

---

## Part 7 — bank-import-design (design only, no steps executed in this plan)
Depends on: payments

### Intent
Reserve the data model and matching rules for importing bank statements (camt.053 XML / SK bank CSV) and matching entries to invoices by variable symbol + amount, so Part 1's payments need no later migration.

### Schema (to be created in the follow-up plan)
- `bank_statement_imports`: `id` uuid · `tenant_id` FK restrict · `format` string (BankStatementFormatEnum camt053|csv_generic) · `statement_id` string(64) (from the file, e.g. `BkToCstmrStmt/Stmt/Id`) · `account_iban` string(34) · `period_from`/`period_to` date · `imported_by_user_id` FK users restrict · `lines_total`/`lines_matched` int · media collection `statement` (HasMedia, private disk) · timestamps · softDeletes · unique `(tenant_id, statement_id)` partial `WHERE deleted_at IS NULL`.
- `bank_statement_lines`: `id` uuid · `tenant_id` FK restrict · `import_id` FK restrict · `entry_reference` string(64) · `booking_date` date · `amount` decimal(12,2) (credit positive) · `currency` string(3) · `variable_symbol` string(10) NULL · `counterparty_iban` string(34) NULL · `counterparty_name` string(255) NULL · `end_to_end_id` string(64) NULL · `remittance` text NULL · `match_status` string (unmatched|auto|manual|ignored) · `matched_invoice_id` FK invoices `nullOnDelete` (reason: an invoice soft-deleted later must not block the line; soft delete never fires it, kept for forceDelete safety) · `invoice_payment_id` FK invoice_payments restrict NULL · timestamps · no softDeletes (operational, re-importable) · unique `(tenant_id, import_id, entry_reference)`.
- `invoice_payments.source = bank_import`, `external_reference = entry_reference` (reserved in Part 1).

### Matching rules (for the follow-up)
- auto: `variable_symbol` equals an open invoice's VS in the tenant AND amount equals `balance_due` AND currency equals → `recordPayment(source bank_import)` in the same transaction, line `auto`.
- suggestion: VS matches but amount differs (partial / overpayment) → `unmatched` with `suggested_invoice_id` shown in UI; user confirms → `manual` + partial payment (overpayment rejected by Part 1 guard, user must ignore the remainder).
- idempotency: unique `(tenant_id, import_id, entry_reference)` → re-importing the same statement is a no-op per line.
- parsing: camt.053 via XMLReader (`Ntry` → `NtryRef`, `Amt`, `CdtDbtInd`, `BookgDt`, `NtryDtls/TxDtls/Refs/EndToEndId`, `RmtInf/Strd/CdtrRefInf/Ref` → VS), CSV mapping per bank in config.
- Out of this plan: no files, no steps, no tests.

---

## Risks
- `qpdf` must exist in the CI/test image for `QpdfMergerTest`; the test self-skips when the binary is absent (documented), feature tests mock `MergesPdfDocuments`.
- Merged PDF sync cap 10: owner may hit the cap on month-end runs → queued variant (stored export + notification) is the follow-up.
- `clients.country` is `varchar(255)` free text; EFA/Pohoda emit it only when it is exactly 2 chars (snapshot `customer_country` is `varchar(2)` already).
- EFA regulatory facts (scheme ids, CIUS, dates) are config values flagged UNCERTAIN — must be confirmed with the accountant before any live driver.
- Pohoda import acceptance (encoding, `issuedCreditNotice` for credit notes, deposit handling) can only be verified by importing into the accountant's Pohoda — first export is a trial run.
- Coverage gap closed: `markPaid` had no tests; Part 1 adds them before changing behaviour.
