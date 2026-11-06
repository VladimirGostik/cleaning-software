# Mobile invoices: issue, send with editable cover e-mail, richer detail (BE part)

Status: approved by owner instruction ("don't ask anything, plan it and implement it"). Mobile FE works in `cleaning-software-app` against the API contract below; the mobile architect appends `## Mobile section`.

## Intent

The owner can issue a draft invoice and e-mail an issued or overdue invoice from the mobile app. Before sending, the app shows the cover e-mail (subject + text resolved from the firm's existing invoice mail template) and the owner can edit it. The API calls the same services and policies as the web. The only new BE behaviour is a per-send subject/body override, a send draft endpoint, a stats endpoint and sending overdue invoices.

## What already exists (finding for the owner)

- The cover e-mail setting ALREADY EXISTS on the web: Settings -> Fakturácia -> card "E-mail k faktúre" (`resources/js/Components/Invoices/InvoiceSettingsMailCard.vue`, also in the invoice settings drawer). Its columns are `tenant_interfaces.invoice_mail_subject` (255) and `invoice_mail_body` (5000). It takes `{{invoice.*}}` / `{{customer.*}}` / `{{supplier.*}}` tokens, prices included (`{{invoice.total}}`, `{{invoice.balance_due}}`), from `PlaceholderResolverService::invoiceMailVariables` / `invoiceMailCatalog`. `App\Notifications\InvoiceIssued` uses it.
- What is actually missing: (1) `/api` has no issue or send routes; (2) nobody can see the resolved e-mail before it is sent; (3) subject and text cannot be edited for one send (not on the web either); (4) the template has no tokens for the object name or the net / VAT amounts; (5) mobile cannot open the setting. The setting stays web-only: `manage billing_settings` is owner-only, and a long-text template editor is not worth building for a phone. The mobile send sheet shows a hint pointing to the web setting.
- `InvoiceDetailData` from `GET /api/invoices/{id}` already carries everything: supplier, buyer address / IČO / DIČ / IČ DPH, object, period, symbols, items, VAT breakdown, payments, QR, sent_at, customer_email, notes, e-invoice status, credit-note / quote links. The thin mobile detail is a mobile-only problem. BE adds `tenant_id` and two state flags.

## Decisions

1. **API controllers call the web services.** `POST /api/invoices/{invoice}/issue` -> `InvoiceService::issue($invoice, InvoiceIssueData, $actor)`; `POST /api/invoices/{invoice}/send` -> `InvoiceRecipientService::send`. Both use the same `InvoicePolicy` abilities (`issue`, `send`) through `#[Authorize]`. No new permission: `edit invoices` gates both, as on the web. Reason: one owner per rule.
2. **The per-send override travels on the notification, not in the DB.** `InvoiceSendData` gains nullable `subject` / `body`. `InvoiceService::send(Invoice, ?string $subject = null, ?string $body = null)` passes them to `new InvoiceIssued($id, $subject, $body)`. Bulk send, recurring auto-send and the AI `send_invoice` keep calling `send($invoice)`. Reason: the text is a one-off message, not invoice data, so no column and no migration. (assumed) The edited text is neither stored nor written to the activity log; `sent_at` stays the audit fact.
3. **One owner decides what the mail says.** New method `PlaceholderResolverService::invoiceMailContent(Invoice $invoice, ?string $subject = null, ?string $body = null): array{subject: string, body: string}`. Each field picks the first that applies: the override (blank counts as absent), the firm template, the lang default. Tokens are resolved in overrides too. Lang default body = `__('app.invoice_mail_greeting', ['name' => customer_name])."\n\n".__('app.invoice_mail_body')`. `InvoiceIssued::toMail` and the send draft both call this method. The `@else` default-paragraph branch in `resources/views/mail/invoice.blade.php` is removed: `bodyText` is always a string and is rendered with `nl2br(e())` as today. The default mail still shows two paragraphs.
4. **Mail locale is pinned to `config('app.locale')`** (assumed). `InvoiceService::send` notifies with `->locale(config()->string('app.locale'))`, and the send draft resolves inside `App::withLocale(config('app.locale'), ...)`. Reason: the queue worker already renders in the app locale. Pinning makes the draft show exactly what the customer gets, even when the user's UI language (the request locale) is `uk` or `en`. No change in production.
5. **Send draft endpoint** (name and flat shape as the mobile architect asked): `GET /api/invoices/{invoice}/send-draft` -> `InvoiceRecipientService::sendDraft(Invoice, User): InvoiceSendDraftData`. It returns the resolved subject and body, the recipient, the PDF attachment name, plus `email_options` / `save_target` taken from the existing `sendOptions` logic (`[]` / `null` when the invoice already has `customer_email`). It is refused unless `Invoice::canBeSent()`, with 422 `status` (`app.invoice_not_issued_to_send`), the same rule as send. Authorize `send`. Read-only.
6. **Overdue invoices can be sent** (mobile architect's request; assumed as a business rule: a re-send works as a reminder). New `Invoice::canBeSent(): bool` = status Issued or Overdue. It is the single rule, used by `InvoiceService::send`, `InvoiceRecipientService::send|sendOptions|sendDraft` and `InvoiceDetailData::can_be_sent`. Side effects: web bulk send now also sends overdue invoices (others still skip `not_issued`), and the web Show "send" button appears for overdue (`InvoiceActionsCard.vue` line 79 `status === 'issued'` -> `isIssuedOrOverdue`). The mail text does not change.
7. **State flags go on the detail; permissions come from `/api/me`.** `InvoiceDetailData` gains `tenant_id` (the mobile app needs it for `X-Tenant-Id` follow-up calls), `can_be_issued` (Draft and `supplier_missing_fields === []`) and `can_be_sent` (`canBeSent()`). Same pattern as `JobDetailData::can_be_*`. Reason: status rules stay on the server, and the DTO repeats no permissions.
8. **List overview:** `InvoiceListItemData` gains `sent_at: ?string` (ISO 8601). New `GET /api/invoices/stats` -> existing `InvoiceService::stats()` / `InvoiceStatsData` (active company), `#[Authorize('viewAny', Invoice::class)]`.
9. **More template tokens** (assumed): `{{object.name}}`, `{{invoice.subtotal}}` and `{{invoice.vat_amount}}` are added to `invoiceMailVariables` and `invoiceMailCatalog`. Labels: `app.invoice_mail_token_object_name`, `..._invoice_subtotal`, `..._invoice_vat_amount`, in sk/en/uk. Reason: the owner asked for parameters from the invoice "like the price", and these are the missing ones. The web token picker shows them without an FE change.
10. **Throttle** (assumed): named limiter `invoice-send`, 10/min per user (`'user:'.id`, fallback `'ip:'.get_client_ip()`), only on `POST /api/invoices/{invoice}/send`. Reason: mobile retries and double taps send real e-mails.
11. **Out of scope** (assumed): CC/BCC; changing the recipient when `customer_email` is already set (same as the web: the recipient field appears only when it is missing); editing the firm template from mobile; a token picker on mobile; editing subject/body in the web send modal (the contract supports it later).
12. **Responses:** issue -> 200 `InvoiceDetailData`; send -> 202 `InvoiceDetailData` (fresh; `StampInvoiceSentAt` stamps `sent_at` later); send-draft -> 200 `InvoiceSendDraftData`; stats -> 200 `InvoiceStatsData`. Same pattern as `Api\TaskController::complete`. Hard gates: no FK, no soft-delete and no data-shape change. The async surface is unchanged (`InvoiceIssued` stays queued with `afterCommit`). The only new class is one DTO.

## Constraints

- Coordination: do NOT touch `app/Enums/TaskStatusEnum*`, `TaskService`, Task DTOs or DataTable components. Shared files (`routes/api.php`, `resources/lang/{sk,en,uk}/app.json`, `resources/js/types/generated.d.ts`): additive edits only, and re-read each one right before editing.
- No branch switch, no `migrate:fresh`, no commits, one test process at a time. Run PHP through `docker compose exec app ...`. Boost MCP is down; use artisan in the container.
- Web behaviour stays the same except decision 6 (overdue sendable). This covers web send (empty body / recipient modal), recurring auto-send and the AI confirmation send.
- No migration.

## Files

- `app/Models/Invoice.php` — `canBeSent(): bool`.
- `app/Services/PlaceholderResolverService.php` — `invoiceMailContent(...)`; 3 new variables and catalog entries.
- `app/Notifications/InvoiceIssued.php` — ctor `(string $invoiceId, ?string $subject = null, ?string $body = null)`; `toMail` uses `invoiceMailContent`.
- `resources/views/mail/invoice.blade.php` — remove the default-paragraph branch.
- `app/Services/InvoiceService.php` — `send(Invoice $invoice, ?string $subject = null, ?string $body = null): void`; guard via `canBeSent()`; locale pin.
- `app/Data/Invoices/InvoiceSendData.php` — `subject` `#[Nullable, Max(255)]`, `body` `#[Nullable, Max(5000)]`, both default null.
- `app/Services/InvoiceRecipientService.php` — ctor + `PlaceholderResolverService`; `send` forwards subject/body on both branches; guards via `canBeSent()`; new `sendDraft(Invoice, User): InvoiceSendDraftData`.
- `app/Data/Invoices/InvoiceSendDraftData.php` (new, `#[TypeScript]`) — send draft response.
- `app/Data/Invoices/InvoiceDetailData.php` — `tenant_id`, `can_be_issued`, `can_be_sent`.
- `app/Data/Invoices/InvoiceListItemData.php` — `sent_at`.
- `app/Http/Controllers/Api/InvoiceController.php` — `stats`, `issue`, `send`, `sendDraft`, plus a private detail builder shared with `show`; Scribe attributes; group description no longer says "Read-only".
- `routes/api.php` — 4 routes (additive; `/invoices/stats` before `/invoices/{invoice}`, which also has `whereUuid`).
- `app/Providers/AppServiceProvider.php` — `invoice-send` limiter.
- `resources/js/Components/Invoices/InvoiceActionsCard.vue` (+ its `.spec.ts`) — show the send block for overdue too (decision 6); add the new fixture fields if vue-tsc asks.
- `resources/lang/{sk,en,uk}/app.json` — 3 token labels (additive). No new error keys: reuse `invoice_not_draft`, `invoice_supplier_incomplete`, `invoice_number_taken`, `invoice_not_issued_to_send`, `invoice_recipient_required`, `invoice_email_save_not_allowed`, `invoice_no_customer_email`. `validation.json` already has `attributes.subject|body|number|recipient_email|save_for_future`.
- `resources/js/types/generated.d.ts` — regenerated with `typescript:transform`. Then `pnpm api-types:export` ON THE HOST (not in the container) updates the stale mobile `src/types/api.generated.d.ts`.
- Tests: `tests/Feature/Api/InvoiceApiActionsTest.php` (new); extend `tests/Feature/Invoices/InvoiceMailTextTest.php`, `tests/Feature/Api/InvoiceApiTest.php` (flags, `tenant_id`, `sent_at`, stats), `tests/Feature/Invoices/InvoiceSendTest.php` (overdue) and `InvoiceBulkActionsTest` (overdue no longer skipped).

## Steps

1. `Invoice::canBeSent()`. `PlaceholderResolverService`: add the variables `object.name` (`$invoice->object_name`), `invoice.subtotal` and `invoice.vat_amount` (currency-formatted like `invoice.total`); add them to the catalog; add `invoiceMailContent` (decision 3). Lang labels in sk/en/uk.
2. `InvoiceIssued`: new nullable public readonly ctor params (they are part of the queued payload); `toMail` -> `invoiceMailContent($invoice, $this->subject, $this->body)`; the view gets `bodyText` = body. Simplify the blade.
3. `InvoiceService::send` gains the two params and the `canBeSent()` guard; `Notification::route('mail', ...)->notify((new InvoiceIssued($invoice->id, $subject, $body))->locale(config()->string('app.locale')))`.
4. `InvoiceSendData`; `InvoiceRecipientService::send` forwards `$data->subject` and `$data->body` in both branches. Switch the status guards in `send` and `sendOptions` to `canBeSent()`.
5. `InvoiceSendDraftData` + `InvoiceRecipientService::sendDraft`: guard `canBeSent()` (422 `status`); `loadMissing(['client', 'cleaningObject', 'recurringInvoice'])`; content via `App::withLocale(config('app.locale'), fn () => $placeholders->invoiceMailContent($invoice))`; `attachment_name` = `$invoice->pdfFilenameBase().'.pdf'`; when `customer_email` is null, take `email_options` / `save_target` from `sendOptions`, otherwise use `[]` / `null`.
6. `InvoiceDetailData::tenant_id|can_be_issued|can_be_sent`, `InvoiceListItemData::sent_at`.
7. `Api\InvoiceController`:
    - `stats()` `#[Authorize('viewAny', Invoice::class)]`.
    - `issue(InvoiceIssueData $data, Invoice $invoice, Request $request)` `#[Authorize('issue', 'invoice')]`. An empty body must work (`number` nullable, default null in the DTO if needed).
    - `send(InvoiceSendData $data, Invoice $invoice, Request $request, InvoiceRecipientService $recipients)` `#[Authorize('send', 'invoice')]`, returns 202.
    - `sendDraft(Invoice $invoice, Request $request, InvoiceRecipientService $recipients)` `#[Authorize('send', 'invoice')]`.
    - `#[ResponseFromSpatieData]` on each endpoint (status 202 on send).
    - Routes, all `->whereUuid('invoice')` where there is a param: `api.invoices.stats`, `api.invoices.issue`, `api.invoices.send` (`throttle:invoice-send`), `api.invoices.send-draft`.
    - Limiter in `AppServiceProvider`.
8. Web `InvoiceActionsCard.vue`: show the send block for issued or overdue.
9. `php artisan typescript:transform`; fix the web fixtures for vue-tsc; Pint; PHPStan; tests; then `pnpm api-types:export` on the host.

## API contract (mobile codes against this)

All routes sit under `/api`, with a Bearer mobile token and an optional `X-Tenant-Id` (same as `show`). Errors:

- 401: unauthenticated.
- 403 `{message}`: no `edit invoices` / `view invoices`, no mobile access, or a foreign tenant header.
- 404: the invoice is not in the active firm.
- 422 `{message, errors: {<field>: [string]}}`: validation.
- 429: throttled.

```
GET  /api/invoices/stats
  200 App.Data.Invoices.InvoiceStatsData { issued_this_month, overdue, pending, recurring_monthly: InvoiceStatCardData, currency }

POST /api/invoices/{id}/issue           body: {} | { number?: string|null (max 50; null = next in series) }
  200 App.Data.Invoices.InvoiceDetailData (same shape as show incl. qr_data_uri / efa; status "issued", can_be_issued false, can_be_sent true)
  422 errors.status   (not a draft)    errors.supplier (supplier profile incomplete -> finish on web Settings -> Fakturácia)
      errors.number   (number taken / too long)

GET  /api/invoices/{id}/send-draft
  200 App.Data.Invoices.InvoiceSendDraftData {
        recipient_email: string | null,      // invoice customer_email; null -> user must enter one
        email_options: string[],             // suggestions, only when recipient_email is null, else []
        save_target: App.Enums.InvoiceEmailSaveTargetEnum | null,   // "recurring_invoice" | "object"; null when not saveable or recipient set
        subject: string,                     // resolved: firm template or default, tokens filled, plain text
        body: string,                        // resolved plain text, "\n" line breaks
        attachment_name: string              // e.g. "Faktura-2026-0012.pdf"
      }
  422 errors.status (not issued / overdue)

POST /api/invoices/{id}/send             body: {
        recipient_email?: string|null,  // required (422) only when the invoice has no customer_email; ignored otherwise
        save_for_future?: boolean,      // default false; true while save_target is null -> 422 errors.save_for_future
        subject?: string|null,          // max 255; null/blank = firm template/default
        body?: string|null              // max 5000; null/blank = firm template/default; {{tokens}} still resolved
      }
  202 App.Data.Invoices.InvoiceDetailData (customer_email filled; sent_at stays null until the worker sends)
  422 errors.status | errors.recipient_email | errors.save_for_future | errors.subject | errors.body
  429 throttle invoice-send (10/min per user)

GET  /api/invoices/{id}    InvoiceDetailData now also has: tenant_id: string, can_be_issued: boolean, can_be_sent: boolean
GET  /api/invoices         InvoiceListItemData now also has: sent_at: string | null
```

Permissions: issue, send and send-draft need `edit invoices` in the active company (from the `/api/me` permissions); stats, list and detail need `view invoices`. The app shows an action only when `can_be_*` is true and `allows('edit invoices')`. Sending the edited subject/body unchanged is harmless: it renders the same text.

## Acceptance criteria

- `docker compose exec app php artisan test --compact --filter='InvoiceApiActionsTest|InvoiceApiTest|InvoiceMailTextTest|InvoiceSendTest|InvoiceSendRecipientTest|InvoiceBulkActionsTest|GenerateRecurringInvoiceJobTest|ValidationAttributesTest'` exits 0.
- The full suite `docker compose exec app php artisan test --compact` exits 0 (run once, alone).
- `docker compose exec app vendor/bin/phpstan analyse --memory-limit=1G` exits 0, with no new baseline entry under `app/`.
- `docker compose exec app vendor/bin/pint --dirty --format agent` is clean.
- `docker compose exec app php artisan typescript:transform` generates `InvoiceSendDraftData`, `InvoiceSendData.subject|body`, `InvoiceDetailData.tenant_id|can_be_issued|can_be_sent` and `InvoiceListItemData.sent_at`; `docker compose exec app pnpm typecheck` and `docker compose exec app pnpm test` exit 0.
- `docker compose exec app php artisan route:list --path=api/invoices` lists stats, issue, send and send-draft.
- After `pnpm api-types:export` (host), `../cleaning-software-app/src/types/api.generated.d.ts` contains `InvoiceSendDraftData` and `InvoiceSendData`.

## Test scenarios

- Issue:
    - draft -> 200, status issued, number assigned, `issued_by_name` = the membership display name;
    - empty body works; a manual `number` is respected; a taken number -> 422 `number`;
    - non-draft -> 422 `status`; incomplete supplier -> 422 `supplier`;
    - role without `edit invoices` -> 403; another firm's invoice -> 404; MCP token -> 403.
- Send draft:
    - firm template with tokens -> resolved subject/body, including `{{invoice.total}}` and `{{object.name}}`;
    - empty template -> lang default subject, and greeting + default body;
    - user locale `en` -> the draft is still in `app.locale` (sk);
    - invoice with `customer_email` -> `email_options` [] and `save_target` null; without one -> `email_options` from object / client contacts;
    - overdue -> 200; draft or paid -> 422 `status`; without `edit invoices` -> 403.
- Send:
    - with `subject`/`body` -> `InvoiceIssued` goes on demand to `customer_email` carrying them, and the `toMail` subject/body equal the override with tokens resolved;
    - without an override -> the firm template (existing `InvoiceMailTextTest` cases stay green); a blank override -> the template;
    - no `customer_email` and no `recipient_email` -> 422 `recipient_email`;
    - `recipient_email` + `save_for_future` writes the recurring template or the object `billing_email`, as on the web;
    - overdue -> sent; draft -> 422 `status`;
    - the response is 202 with the detail; the 11th call in a minute -> 429.
- Mail rendering: the default mail still contains the greeting with the customer name and `app.invoice_mail_body`; HTML in an override body is escaped.
- Detail / list / stats:
    - `can_be_issued` is true only for a draft with a complete supplier; `can_be_sent` only for issued or overdue;
    - `tenant_id` equals the invoice's firm; `sent_at` is present in list items;
    - stats counts only the active firm.
- Regression: web `POST /invoices/{id}/send` (empty body and recipient modal), bulk send (overdue now sent, draft still skipped `not_issued`), recurring auto-send and AI `send_invoice` confirm still dispatch `InvoiceIssued` with null overrides.

## Mobile section

Repo: `cleaning-software-app` (Quasar 2 / Capacitor 8, app-skeleton fork). Codes against `## API contract` above. Starts after the BE slice has run `pnpm api-types:export` (mobile `src/types/api.generated.d.ts` is stale today: no `InvoiceSendData`, no `InvoiceSendDraftData`). i18n: vue-i18n present, so every string is a key in `src/i18n/{en-US,sk,uk}/documents.ts` (en-US is the type source).

### Intent

The invoice list becomes a real overview: company switcher in the header, one clear row per invoice, overdue rows emphasised and a totals strip. The detail shows everything the BE already returns. The owner can issue a draft and send an issued or overdue invoice, with a cover e-mail prefilled from the firm's template.

### Decisions

1. **Header company switcher.** New `src/components/company/CompanyHeaderSwitcher.vue` (prop `permission: App.Enums.PermissionEnum`) sits in the `BackButtonPage` `#actions` slot of `InvoiceListPage`. It renders a `q-btn flat no-caps` containing the company colour dot (`MobileCompanyData.color`, the same source `CompanyCard` uses), the name (ellipsis, max about 40% of the toolbar width) and `sym_r_expand_more`, plus `aria-haspopup="dialog"` and the `overview.switchCompany` label. It opens the existing `CompanySheet :permission`. It renders nothing when fewer than 2 companies pass `canIn(tenant_id, permission)`. `DocumentListFilters.vue` loses the company chip, its `CompanySheet` and the `companyPermission` prop (InvoiceListPage is the only consumer, grep-verified). Reason: this matches the existing toolbar-action pattern (`ExpenseDetailPage`, `TaskDetailPage`). Overview's `CompanySwitcher` is title-sized, too large for an action slot. QuoteListPage: follow-up only, because it has no refetch on `company.activeId` today.
2. **List row = `src/components/invoices/InvoiceListItem.vue`** (prop `invoice: App.Data.Invoices.InvoiceListItemData`, a `router-link` to `/invoices/:id`, root class `kg-row`).
    - Title: `customer_name`, 2-line clamp.
    - Meta 1: `number`, omitted for drafts, which removes the double "Koncept"; plus `· object_name` when it is set and differs from `customer_name`.
    - Meta 2: "Splatnosť {date}". When overdue it becomes "Po splatnosti od {date}" in `var(--kg-danger)`. The date wording avoids plural forms: the project has no plural rules.
    - Tags: "Dobropis" when `is_credit_note`; "Neodoslaná" when status is issued or overdue and `sent_at === null`.
    - Side: `money(total)`. When `is_partially_paid`, a muted line "zostáva {balance_due}" goes under it, with `DocumentStatusChip` below.
    - The overdue rule is pure: `isInvoiceOverdue(item, todayYmd)` = `status === 'overdue'` OR (`status === 'issued'` AND `due_date < today`), because the cron sets `overdue` only at 12:00.
3. **Totals strip = `src/components/invoices/InvoiceSummaryStrip.vue`** (props `stats: App.Data.Invoices.InvoiceStatsData | null`, `active-status`; emit `filter-status`). It has two tiles: "Čaká na úhradu" (`stats.pending`, i.e. status issued, open balance) and "Po splatnosti" (`stats.overdue`). Each tile shows amount and count, money via `useFormat().money(amount, stats.currency)`. Tapping a tile emits `filter-status` (`issued` / `overdue`), and InvoiceListPage calls `setFilters({ status })`. Data: new `src/composables/useInvoiceStats.ts` (`stats`, `isLoading`, `load()`; `withSessionRecovery(() => InvoicesApi.stats())`). It reloads on `company.activeId` change and is triggered by the page together with the list fetch (pull-to-refresh, keep-alive `refresh`). It covers the whole company, not the filtered set. While loading, the strip shows a skeleton; on error it is hidden, so it never blocks the list.
4. **Detail = composition of section cards** in `src/components/invoices/`, with logic in `src/composables/useInvoiceDetail.ts` (shape like `useTaskDetail`). Cards render only when they have data:
    - `InvoiceHeroCard.vue`: status chip; number or "Koncept"; "Dobropis" tag; balance due (large); the overdue line (same rule); "Odoslaná {date}" or "Neodoslaná" for issued or overdue.
    - `InvoicePartyCard.vue` (props `title: string`, `party: InvoiceParty`), used twice: customer first, supplier second. The customer card adds the object name and address and the representative line.
    - `InvoiceFactsCard.vue`: type (monthly, one_off, special), issue, delivery and due dates, period from–to, issued_at, sent_at, paid_at, cancelled_at, source quote number.
    - `InvoicePaymentInfoCard.vue`: payment type, VS, KS, ŠS, IBAN, SWIFT (from `supplier`).
    - `InvoiceItemsCard.vue`:
        - Each item: description; "{quantity} {unit} × {unit_price}"; discount when > 0; VAT rate when `is_vat_payer`; line total.
        - Totals: subtotal, VAT, rounding when ≠ 0, deposit when > 0, total, paid, balance.
        - VAT recap table from `vat_breakdown` when `is_vat_payer` and the list is non-empty.
    - `InvoicePaymentsCard.vue`: `payments[]` (paid_on, amount, payment type, note).
    - `InvoiceTextsCard.vue`: `header_text`, `note`, `footer_text`, with preserved line breaks (`white-space: pre-line`, no `v-html`).
    - Supplier-incomplete `q-banner` (warning tone) when `status === 'draft'` and `supplier_missing_fields.length > 0`: "Doplňte údaje dodávateľa na webe (Nastavenia → Fakturácia)".
    - Party normalization is pure (`toCustomerParty(detail)`, `toSupplierParty(detail.supplier)` → `InvoiceParty { name, addressLines: string[], ico, dic, vatNumber, email, extra: string[] }`), so cards stay render-only.
5. **Actions in the detail footer** (`kg-footer-bar`):
    - The single orange CTA comes from `invoiceActions(detail, canEdit, online)` → `{ primary: 'issue' | 'send' | 'resend' | null }`:
        - `canEdit` = `company.canIn(detail.tenant_id, 'edit invoices')`.
        - `issue` when `can_be_issued`.
        - `send` when `can_be_sent && sent_at === null`.
        - `resend` when `can_be_sent && sent_at !== null`.
        - `null` when offline or `!canEdit`.
        - No status re-derivation: the BE flags own the status rules.
    - PDF moves to `PdfOpenButton :secondary` when a primary exists, otherwise it stays the CTA.
    - When offline and an action would apply, a muted line "Offline: akcie sú nedostupné" appears.
6. **Issue.**
    - `useConfirm` dialog (title "Vystaviť faktúru?", message "Faktúre sa pridelí ďalšie číslo zo série a už sa nebude dať upraviť.", ok "Vystaviť").
    - Then `InvoicesApi.issue(id, tenant_id)` with body `{}` (mobile never sends a custom number) under `withSessionRecovery`.
    - On 200 the response replaces the local `invoice` and a toast reads "Faktúra {number} bola vystavená".
    - On 422 a toast shows the server message via `describeError(error).message` (keys `status`, `supplier`, `number`).
    - Timeout or network: toast `common.timeoutCheck`, then reload the detail so the real state shows.
    - A busy flag disables the footer during the call.
7. **Send = drill-in page `src/pages/invoices/InvoiceSendPage.vue`**, route `/invoices/:id/send` (name `invoice-send`, `[auth, permissionMiddleware('edit invoices', 'anywhere')]`, `?tenant=` required from the detail link). This is not a bottom sheet: a long editable body above the iOS keyboard (`resize: none`, frame owned by Swift) is proven only with page footers. Swipe-back works on a page, and no dialog-root or overlay-detection rules apply. Logic lives in `src/composables/useInvoiceSend.ts`:
    - Load: `InvoicesApi.sendDraft(id, tenant)` → skeleton `useForm({ fields: { recipient_email, subject, body, save_for_future } })` initialised from the draft. A load 422 `status` or 404/403 shows an error card with retry and back; the page is not cached.
    - Recipient rule (BE decision 11):
        - `draft.recipient_email !== null` → read-only fact row (label "Príjemca", value; a hint that the address comes from the invoice). The field is not sent.
        - `null` → `q-input type="email"` with `FormRules.required` + `FormRules.isEmail`. Below it, `InvoiceEmailSuggestions.vue` (props `options: string[]`; emit `pick`) renders `email_options` as tappable chips (`q-chip clickable`, `aria-label`).
        - A `q-checkbox` "Uložiť e-mail aj pre budúce faktúry" appears only when `save_target !== null`. Its label varies by target (`recurring_invoice` → "…do pravidelnej faktúry", `object` → "…do fakturačných údajov objektu").
    - `subject`: `q-input`, `required` + `maxLen(255)`. `body`: `q-input type="textarea" autogrow`, `required` + `maxLen(5000)`.
    - Muted info: attachment `attachment_name`, and the hint "Predvolený text e-mailu upravíte na webe v Nastavenia → Fakturácia."
    - Submit: footer CTA "Odoslať" (`type="submit" form="invoice-send-form"`), disabled while submitting or offline. It goes through `useGuardedSubmit(form)` (422 binds to fields; unknown keys such as `status` become the banner; timeout becomes `common.timeoutCheck`).
        - The payload always carries `subject`, `body` and `save_for_future`, and carries `recipient_email` only when the draft had none.
        - A 429 inside the handler is rethrown as `new Error(t('documents.invoices.send.throttled'))`.
    - Success (202): toast "Faktúra sa odosiela na {email}" (email = `response.customer_email`), then `router.back()`. The detail is not kept alive, so it remounts and reloads. `sent_at` may still be null until the worker sends; the hero then shows "Neodoslaná", which is accepted: the toast already said "odosiela".
8. **State ownership:** page-local only (composables + `ref`); no new store. API in `InvoicesApiService` only. Timeouts: issue, send-draft, stats `DEFAULT_TIMEOUT_MS`; send uses a new `SEND_TIMEOUT_MS = 30_000` constant, local to the service file.
9. **Mobile hard gates:**
    - Offline: actions are disabled via `useNetwork().isOnline` (skeleton `src/composables/core/useNetwork`). There is no local queue, because e-mail is not idempotent.
    - Native permissions, deep links, push token, biometric, version gating: not touched. The new route is not added to the push allowlist.
    - Safe area: `BackButtonPage safe-bottom` + `kg-footer-bar`, with the existing `kg-keyboard-open` rule.

### Constraints

- Skeleton-owned files untouched (`src/components/layout/*`, `src/composables/core/*`, `src/services/core|container|services|index.ts`, `src/types/*` except generated by the BE export).
- Another session works in tasks. Do NOT edit `src/pages/tasks/**`, `src/components/tasks/**`, `src/components/common/InitialsAvatar.vue`, `src/css/kegos.scss` or `src/i18n/*/tasks.ts`. `src/router/project-routes.ts`: one additive route block after `invoice-detail`; re-read right before editing.
- Styles: component-scoped SCSS with `--kg-*` tokens only; no hex, no new font. 4px multiples for spacing.
- `InvoiceListPage` keeps `defineOptions({ name: 'InvoiceListPage' })` (keep-alive list).
- Every `<q-dialog>` root is a `<div>` / `<q-card>` (`dialogRoot.test.ts`); no new dialogs are planned besides `useConfirm`.
- No branch switch, no commits. pnpm only.
- Out of scope: mobile template editor, token picker, CC/BCC, custom invoice number, payments recording, cancel, QuoteListPage switcher, IBAN copy, QR display.

### Files

New:

- `src/services/invoices/invoiceRules.ts` — pure functions: `isInvoiceOverdue`, `invoiceListMeta` (number / object line), `toCustomerParty`, `toSupplierParty`, `invoiceActions`, `sendPayload(draft, fields)`.
- `src/services/invoices/invoiceRules.test.ts` — vitest for the above.
- `src/components/company/CompanyHeaderSwitcher.vue` — toolbar company switcher (decision 1).
- `src/components/invoices/InvoiceListItem.vue` — list row (decision 2).
- `src/components/invoices/InvoiceSummaryStrip.vue` — stats tiles (decision 3).
- `src/composables/useInvoiceStats.ts` — stats loader (decision 3).
- `src/composables/useInvoiceDetail.ts` — detail load + issue + actions state (decisions 4–6).
- `src/components/invoices/InvoiceHeroCard.vue`, `InvoicePartyCard.vue`, `InvoiceFactsCard.vue`, `InvoicePaymentInfoCard.vue`, `InvoiceItemsCard.vue`, `InvoicePaymentsCard.vue`, `InvoiceTextsCard.vue` — detail sections.
- `src/composables/useInvoiceSend.ts` — send draft load + form + submit (decision 7).
- `src/components/invoices/InvoiceEmailSuggestions.vue` — e-mail suggestion chips.
- `src/pages/invoices/InvoiceSendPage.vue` — compose and send screen.

Changed:

- `src/services/invoices/InvoicesApiService.ts` — `stats()`, `issue(id, tenantId)`, `sendDraft(id, tenantId)`, `send(id, payload, tenantId)`.
- `src/pages/invoices/InvoiceListPage.vue` — header switcher, summary strip, `InvoiceListItem`.
- `src/components/documents/DocumentListFilters.vue` — remove the company chip, sheet and prop.
- `src/pages/invoices/InvoiceDetailPage.vue` — compose the cards, footer actions, use `useInvoiceDetail`.
- `src/router/project-routes.ts` — route `invoice-send` (additive).
- `src/i18n/en-US/documents.ts`, `src/i18n/sk/documents.ts`, `src/i18n/uk/documents.ts` — new keys (see Steps 1).

### Steps

1. **Foundation (wave 1).**
    - `InvoicesApiService` methods, each via `forTenant(tenantId)` except `stats` (active company):
        - `stats(): Promise<App.Data.Invoices.InvoiceStatsData>` → `GET /api/invoices/stats`
        - `issue(id: string, tenantId: string | null): Promise<App.Data.Invoices.InvoiceDetailData>` → `POST /api/invoices/{id}/issue` with body `{}`
        - `sendDraft(id: string, tenantId: string | null): Promise<App.Data.Invoices.InvoiceSendDraftData>` → `GET /api/invoices/{id}/send-draft`
        - `send(id: string, payload: InvoiceSendPayload, tenantId: string | null): Promise<App.Data.Invoices.InvoiceDetailData>` → `POST /api/invoices/{id}/send`, with `type InvoiceSendPayload = { recipient_email?: string; save_for_future: boolean; subject: string; body: string }`
    - `invoiceRules.ts` + tests.
    - All i18n keys in the 3 locales under `documents.invoices`:
        - `creditNote`, `notSent`, `sentOn`, `overdueSince`, `remaining`
        - `summary.{pending,overdue,count}`
        - `sections.{customer,supplier,facts,payment,items,vatRecap,payments,texts}`
        - `fields.{number,type,deliveryDate,period,issuedAt,sentAt,paidAt,cancelledAt,quote,object,ico,dic,vatNumber,email,representative,paymentType,variableSymbol,constantSymbol,specificSymbol,iban,swift,discount,vatRate,rounding,deposit,headerText,footerText,vatBase}`
        - `type.{monthly,one_off,special}`, `paymentType.{transfer,cash,card,cod,other}`
        - `supplierIncomplete`
        - `actions.{issue,send,resend,offline,issueConfirmTitle,issueConfirmMessage,issued}`
        - `send.{title,recipient,recipientLocked,subject,body,attachment,suggestions,saveForFuture.{recurring_invoice,object},templateHint,submit,sending,throttled,loadFailed}`
    - Reuse the existing keys (`dueDate`, `issueDate`, `items`, `subtotal`, `vat`, `total`, `paid`, `balanceDue`, `note`, `draftNumber`, `overview.switchCompany`, `common.*`).
2. **List (wave 2).** Decisions 1–3. The InvoiceListPage `useKeepAlivePage` `refresh` runs list `fetch` and `stats.load()` together; `onRefresh` awaits both.
3. **Detail + issue (wave 2).** Decisions 4–6. `useInvoiceDetail(scope: { id: () => string; tenant: () => string | null })` returns `{ invoice, isLoading, errorKey, isBusy, actions, load, issue }`. The tenant for follow-up calls is `invoice.tenant_id` once loaded, else the route `?tenant=`. The send CTA navigates to `{ name: 'invoice-send', params: { id }, query: { tenant: invoice.tenant_id } }`.
4. **Send (wave 2).** Decision 7 + the route in `project-routes.ts`.
5. **Verify:** `pnpm typecheck`, `pnpm lint`, `pnpm test` (after the BE type export).

### Acceptance criteria

- `pnpm typecheck` exits 0 against the exported types (`InvoiceSendDraftData`, `InvoiceDetailData.tenant_id|can_be_issued|can_be_sent`, `InvoiceListItemData.sent_at` resolve).
- `pnpm lint` exits 0; `pnpm test` exits 0, including the new `invoiceRules.test.ts` and the existing `dialogRoot.test.ts` and `invoiceListQuery.test.ts`.
- List:
    - The company switcher sits on the right of the toolbar when the user has `view invoices` in 2+ companies; the filter row holds only search, status and period.
    - A draft row shows the customer as the title, no "Koncept" title, and one "Koncept" chip.
    - Overdue rows show "Po splatnosti od {date}" in the danger token.
    - The summary strip shows pending and overdue; tapping one filters the list.
- Detail: supplier and customer blocks (address, IČO/DIČ/IČ DPH), number, VS/KS/ŠS, IBAN, issue/delivery/due dates, status, sent state, items with qty/unit/unit price/VAT, VAT recap (VAT payer), payments and texts; empty sections are hidden.
- Draft + `can_be_issued` + `edit invoices` in the invoice's company + online → "Vystaviť" CTA. After confirm, the number shows and the CTA becomes "Odoslať". Without the permission, no action CTA appears.
- Issued or overdue + `can_be_sent` → "Odoslať" ("Odoslať znova" when `sent_at` is set) opens `/invoices/:id/send`:
    - Subject and body are prefilled from the draft.
    - The recipient is read-only when the invoice has a customer e-mail, and otherwise an input with suggestion chips (+ the save checkbox when `save_target` is set).
    - Submit → toast + back to the detail; 422 errors bind to their fields; 429 shows the throttle message.

### Test scenarios (vitest, `invoiceRules.test.ts`)

- `isInvoiceOverdue`: status overdue → true; issued + due yesterday → true; issued + due today → false; paid + past due → false; draft + past due → false.
- `invoiceListMeta`: draft without number → no number part; object equal to customer → omitted; credit note flag passes through.
- `toCustomerParty` / `toSupplierParty`: null fields are dropped; address lines are ordered street, postal code + city, country; an empty party gives empty arrays and no blank strings.
- `invoiceActions`: `can_be_issued` + canEdit + online → issue; `can_be_sent` + unsent → send; sent → resend; offline → null; `!canEdit` → null; both flags false → null.
- `sendPayload`: draft with `recipient_email` → no `recipient_email` key; draft without → trimmed recipient; `save_for_future` false when `save_target` is null.
