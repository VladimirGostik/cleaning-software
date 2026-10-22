<!-- e2e-flow -->
# E2E flow: invoicing batch (payments, bulk actions, mail texts, recurring auto-send, Pohoda export, EFA + Peppol)

- surface: web
- mode: changes (uncommitted working tree on `main`, source plan `.inogile/plans/invoicing-batch-payments-bulk-efa.md`)

## Project profile
- type: skeleton-web (inogile vue-skeleton fork, custom port band)
- base_url: http://localhost:8002 (compose `app` 8002->8000; Vite assets http://localhost:5175)
- start: already running / reuse — `app`, `vite`, `postgres`, `redis`, `mailpit` are up. `queue` service is EXITED (`--max-time=3600`, no restart policy) -> runner MUST `docker compose up -d queue` before Scenario 8/4 and re-check `docker compose ps queue` if a mail does not arrive within 30 s.
- readiness: `curl -sS -o /dev/null -w '%{http_code}' http://localhost:8002/login` = 200
- migrations: all 5 batch migrations (`2026_10_12_000100..000500`) already Ran — no migrate needed; if any shows Pending use `docker compose exec -T app php artisan migrate --force` only.
- qpdf: present in `app` container (`/usr/bin/qpdf` 12.3.2).
- mail: SMTP -> Mailpit; UI http://localhost:8026, API http://localhost:8026/api/v1/search?query=to:<addr> and /api/v1/message/<ID> (fields `Subject`, `Text`, `HTML`).
- db_reset: forbidden — dev DB `cleanmaster_admin` is the developer's working DB. Additive test data only (UI-created, tagged), never `migrate:fresh` / `db:wipe`.
- auth: required — `admin@example.com` / `password` (canonical, never change), UI locale `sk`, single membership: tenant "Demo Cleaning s.r.o." (non-VAT payer, supplier profile complete, ICO 12345678, IBAN set, country SK). One `setup` project -> `storageState`.
- playwright: NOT installed. Host has node 26 + pnpm; project `node_modules` is a docker named volume (host dir empty) -> bootstrap Playwright on the host per `playwright-config.md` (`@playwright/test` devDependency + `npx playwright install chromium`); report the `package.json` / `pnpm-lock.yaml` change. `playwright.config.ts`: `baseURL: http://localhost:8002`, no `webServer` start (compose stack), `.gitignore` gains `playwright/.auth/`, `playwright-report/`, `test-results/`.
- projects: `desktop` only (admin back-office; DataTable selection + drawers are desktop flows). No mobile scenario in scope.

## Test data strategy (applies to all scenarios)
- Run tag `RUN = E2E-<yyyyMMdd-HHmmss>`; every created customer/client name starts with `RUN`; every mail recipient is `e2e+<RUN>-<n>@example.test` (unique per run, Mailpit search by `to:`).
- Invoices are created through the UI (true E2E) by a shared helper in `tests/e2e/support/invoices.ts`:
  - `createDraftInvoice(page, { customer, email?, ico?, country = 'SK', city = 'Bratislava', street = 'Hlavná 1', postal = '81101', price, quantity = 1 })` -> `/invoices/create`, subject type radio "Bez klienta" (label of `invoice_subject_mode_standalone` — confirm live), fill "Názov zákazníka", "E-mail", "IČO", "Krajina", street/city/PSČ, one item ("Názov a popis položky" = `RUN upratovanie`, "Počet", "Jednotková cena"), submit (`invoice_add` label) -> lands on Show/Edit; return invoice id from URL.
  - `issueInvoice(page, id)` -> Show -> button "Vystaviť" -> issue modal submit "Vystaviť" -> status badge "Vystavená"; read the assigned number (FA-YYYY-NNNN) from the page.
- Index isolation: filter the list by the run tag via the search filter (`AllowedFilter::search` on `number`, `customer_name`) so pre-existing invoices (FA-2026-0001/0002) are never selected or mutated.
- Test invoices stay in the dev DB after the run (issued invoices cannot be deleted by design). Leftover Drafts are deleted in `afterAll`. Settings changes (mail texts, tenant Peppol ID) are restored to empty in `afterAll`; created client + recurring template are deleted in `afterAll`.
- Use `test.describe.serial` where scenarios share created invoices (bulk scenarios).

## Scenario 1: Record partial payment, settle with second payment, delete reverts, overpayment rejected
- file: tests/e2e/invoice-payments.spec.ts
- route: /invoices/{id} (Show)
- project: desktop
- precondition: logged in; helper created + issued invoice `RUN pay` (price 100.00, email set) -> status "Vystavená", balance 100,00 €.
- steps:
    1. On Show click "Zaevidovať úhradu" (actions card).
    2. Modal "Zaevidovať platbu": expect "Dátum platby" prefilled today, "Suma" prefilled 100 (balance due).
    3. Fill "Suma" = 150 -> click "Zaevidovať".
    4. Expect field error "Suma platby presahuje zostatok na úhradu." in the modal; no payment row.
    5. Fill "Suma" = 40, "Poznámka" = `RUN záloha` -> "Zaevidovať".
    6. Expect flash "Platba bola zaevidovaná.", card "Platby" lists 40,00 € with note, "Uhradené" 40,00 €, "Zostáva uhradiť" 60,00 €, status badge "Čiastočne uhradená" (status still not "Uhradená").
    7. Click "Zaevidovať úhradu" again -> modal amount prefilled 60 -> "Zaevidovať".
    8. Expect status badge "Uhradená", "Zostáva uhradiť" 0,00 €, two payment rows, "Zaevidovať úhradu" button gone.
    9. In "Platby" card delete the 60,00 € row (trash button "Odstrániť") -> confirm modal (title "Zmazať platbu") -> "Odstrániť".
    10. Expect flash "Platba bola zmazaná.", one payment row, status back to "Čiastočne uhradená"/"Vystavená" family (not "Uhradená"), "Zostáva uhradiť" 60,00 €.
    11. Go to /invoices, search `RUN pay` -> row status badge shows "Čiastočne uhradená".
- assert:
    - visible: validation text "Suma platby presahuje zostatok na úhradu."
    - visible: badge "Čiastočne uhradená" after partial; "Uhradená" after settling; reverted after delete
    - visible: "Zostáva uhradiť" amounts 60,00 € / 0,00 € / 60,00 €
    - toast: "Platba bola zaevidovaná." / "Platba bola zmazaná."
- test_data: amounts 150 (fail), 40, 60; note `RUN záloha`.

## Scenario 2: Credit-note refund
- file: tests/e2e/invoice-payments.spec.ts
- route: /invoices/{id} (Show of original, then of the credit note)
- project: desktop
- precondition: helper created + issued invoice `RUN dobropis` (price 50.00).
- steps:
    1. Show -> "Stornovať" -> confirm (text "Bude vytvorený dobropis.") -> confirm button.
    2. Expect flash "Faktúra bola stornovaná.", badge "Stornovaná", "Zaevidovať úhradu" absent; links card points to the credit note — follow it (or find it on /invoices by search `RUN dobropis`, the row whose total is -50,00 €).
    3. On the credit note Show click "Zaevidovať vrátenie".
    4. Modal title "Vrátenie peňazí k dobropisu", amount prefilled 50 -> "Zaevidovať".
- assert:
    - visible: modal heading "Vrátenie peňazí k dobropisu"
    - visible: credit note badge "Uhradená" after refund, "Zostáva uhradiť" 0,00 €
    - visible: payment row in "Platby" card
- test_data: price 50.00.

## Scenario 3: Duplicate button on list row
- file: tests/e2e/invoice-bulk.spec.ts (serial describe, first)
- route: /invoices
- project: desktop
- precondition: helper created + issued invoice `RUN A` (email `e2e+<RUN>-a@example.test`, price 120).
- steps:
    1. /invoices, search `RUN` -> row `RUN A` -> click row button "Duplikovať".
- assert:
    - toast: "Faktúra bola duplikovaná."
    - url: lands on the new draft (`/invoices/<uuid>` or `/invoices/<uuid>/edit`), different id from `RUN A`
    - visible: back on /invoices search `RUN A` -> 2 rows, one "Vystavená", one "Návrh"
- test_data: the duplicate (Draft, has email) is reused as a draft in Scenario 4.

## Scenario 4: Bulk issue drafts with a skipped row + result modal
- file: tests/e2e/invoice-bulk.spec.ts
- route: /invoices
- project: desktop
- precondition: from Scenario 3 — `RUN A` (issued) + its duplicate (draft); helper creates one more draft `RUN B` (no email, price 80).
- steps:
    1. /invoices, search `RUN` -> tick row checkboxes ("Vybrať faktúru …") for the two drafts + issued `RUN A`.
    2. Bulk bar shows "3 vybraných" -> click "Vystaviť koncepty" -> confirm modal "Vystaviť koncepty" (text "Vystaviť 3 vybraných faktúr?") -> confirm "Vystaviť koncepty".
- assert:
    - visible: modal "Výsledok hromadnej akcie" with "Spracované: 2" and "Preskočené: 1"
    - visible: skipped table row = number of `RUN A` + reason "Faktúra nie je koncept"
    - toast: "Spracované 2 z 3 faktúr."
    - visible: after "Zavrieť", both former drafts show badge "Vystavená" with FA numbers; selection bar gone
- test_data: none extra.

## Scenario 5: Bulk send e-mail with skipped row -> Mailpit
- file: tests/e2e/invoice-bulk.spec.ts
- route: /invoices + Mailpit API
- project: desktop
- precondition: Scenario 4 done (`RUN A` issued with email, duplicate of A issued with same email, `RUN B` issued without email); `queue` service running.
- steps:
    1. /invoices, search `RUN` -> select `RUN A`, `RUN B` -> "Odoslať e-mailom" -> confirm "Odoslať faktúry e-mailom" -> confirm.
    2. Result modal -> "Zavrieť".
    3. Poll Mailpit `GET http://localhost:8026/api/v1/search?query=to:"e2e+<RUN>-a@example.test"` (≤ 30 s, `expect.poll`).
- assert:
    - visible: "Spracované: 1", "Preskočené: 1", reason "Odberateľ nemá e-mail" for `RUN B`
    - mailpit: ≥1 message to `e2e+<RUN>-a@example.test`, subject contains `RUN A`'s invoice number, has a PDF attachment (`Attachments` length ≥ 1)
- test_data: none extra.

## Scenario 6: Bulk mark paid with skipped row
- file: tests/e2e/invoice-bulk.spec.ts
- route: /invoices
- project: desktop
- precondition: Scenario 5 done; helper creates + issues `RUN C` (price 30) and records a full payment on it via Show (status "Uhradená").
- steps:
    1. /invoices, search `RUN` -> select `RUN A`, `RUN B`, `RUN C` -> "Označiť ako uhradené".
    2. Modal "Označiť faktúry ako uhradené" ("Každá faktúra sa uhradí v plnej výške zostatku."), "Dátum úhrady" defaults today -> submit "Označiť ako uhradené".
- assert:
    - visible: "Spracované: 2", "Preskočené: 1", `RUN C` reason "Faktúru nie je možné uhradiť"
    - visible: `RUN A` and `RUN B` rows badge "Uhradená"
- test_data: none extra.

## Scenario 7: Bulk merged PDF download
- file: tests/e2e/invoice-bulk.spec.ts
- route: /invoices -> GET /invoices/bulk/pdf?ids[]=…
- project: desktop
- precondition: ≥3 `RUN` invoices exist (from Scenarios 3–6).
- steps:
    1. /invoices, search `RUN` -> select 3 rows -> bulk bar link "Stiahnuť PDF" (opens new tab / download).
    2. Capture the response (popup `waitForEvent('popup')` response or read the link `href` and fetch it with `page.request.get(href)` — same session).
- assert:
    - response 200, `content-type` application/pdf, `content-disposition` attachment `faktury-<today>.pdf`
    - body starts with `%PDF`; page count ≥ 3 (count via `pdf-lib` devDependency or `qpdf --show-npages` in the `app` container — runner picks; regex on raw bytes is NOT reliable with object streams)
- test_data: none.

## Scenario 8: Settings mail text card + custom subject/body in the sent mail
- file: tests/e2e/invoice-mail-texts.spec.ts
- route: /settings/invoicing, /invoices/{id}, Mailpit API
- project: desktop
- precondition: `queue` running; helper created + issued invoice `RUN mail` with email `e2e+<RUN>-mail@example.test`.
- steps:
    1. /settings/invoicing -> card "E-mail s faktúrou".
    2. Fill "Predmet e-mailu" = `RUN Faktúra ` then click token "Číslo faktúry" in the token list (inserts `{{invoice.number}}` at cursor of the last focused field).
    3. Focus "Text e-mailu", type `Dobrý deň, posielame faktúru na sumu ` then click token "Celková suma"; add a new line `RUN pätička`.
    4. Click "Uložiť" -> expect "Nastavenia fakturácie boli uložené."; reload -> both fields keep the values incl. `{{invoice.number}}` / `{{invoice.total}}`.
    5. Open Show of `RUN mail` -> "Odoslať e-mailom" -> confirm -> expect "Faktúra bude odoslaná e-mailom.".
    6. Poll Mailpit for `to:"e2e+<RUN>-mail@example.test"`, open message.
    7. afterAll: clear both fields in settings, save (restore default texts).
- assert:
    - visible: inserted tokens in the inputs (value contains `{{invoice.number}}`, `{{invoice.total}}`)
    - toast: "Nastavenia fakturácie boli uložené."
    - mailpit: `Subject` = `RUN Faktúra <invoice number>` (token resolved, no `{{`)
    - mailpit: body (`Text` or `HTML`) contains `posielame faktúru na sumu` + the formatted total and `RUN pätička`, contains no `{{`
- test_data: subject/body above.

## Scenario 9: Recurring invoice auto-send toggle
- file: tests/e2e/recurring-auto-send.spec.ts
- route: /recurring-invoices/create -> /recurring-invoices/{id}
- project: desktop
- precondition: logged in.
- steps:
    1. /recurring-invoices/create -> fill required fields (customer standalone `RUN opakovaná`, email `e2e+<RUN>-rec@example.test`, one item, frequency, first run date far in the future e.g. +1 year so the daily cron never fires during the run — confirm field names live).
    2. With "Automaticky vystaviť" OFF: expect toggle "Automaticky odoslať e-mailom" disabled.
    3. Turn "Automaticky vystaviť" ON -> "Automaticky odoslať e-mailom" enabled -> turn it ON.
    4. Turn "Automaticky vystaviť" OFF -> expect "Automaticky odoslať e-mailom" unchecked + disabled; turn auto-issue ON again and auto-send ON again.
    5. Submit -> expect "Opakovaná faktúra bola úspešne vytvorená." and Show page.
    6. afterAll: delete the template (Show delete action / DELETE route via UI).
- assert:
    - visible: auto-send toggle disabled while auto-issue off; reset to off when auto-issue switched off
    - visible: Show schedule card label "Automaticky odoslať e-mailom" with a check icon (not "—")
    - visible: Edit page reload keeps both toggles ON
- test_data: as above.

## Scenario 10: Pohoda export modal
- file: tests/e2e/pohoda-export.spec.ts
- route: /invoices -> GET /invoices/export/pohoda/preview + /invoices/export/pohoda
- project: desktop
- precondition: at least one `RUN` invoice issued today (create + issue via helper if the file runs standalone); note its number.
- steps:
    1. /invoices -> header button "Export do Pohody" -> modal "Export do Pohody (XML)".
    2. Set "Od" = today, "Do" = today.
    3. Expect preview text "Dokladov na export: N" with N ≥ 1 (N = count of non-draft invoices issued today in the tenant; assert ≥ number of `RUN` invoices issued today).
    4. Set "Do" earlier than "Od" -> expect an error under the date field and "Stiahnuť XML" disabled; restore "Do" = today.
    5. Click "Stiahnuť XML" (`window.open`) -> capture popup/download or fetch `/invoices/export/pohoda?from=<today>&to=<today>` with `page.request`.
- assert:
    - visible: "Dokladov na export: N"
    - visible: date error + disabled "Stiahnuť XML" for inverted range
    - response 200, `content-type` application/xml; charset=UTF-8, attachment `pohoda-<today>-<today>.xml`
    - XML well-formed (parse with DOMParser in `page.evaluate` or a node XML parser — no `parsererror`), root `dat:dataPack` with `ico="12345678"`, contains the noted `RUN` invoice number(s), number of `dat:dataPackItem` = N
- test_data: today's date.

## Scenario 11: EFA card on invoice detail (null driver)
- file: tests/e2e/efa-peppol.spec.ts
- route: /invoices/{id} -> GET /invoices/{id}/efa-xml
- project: desktop
- precondition: helper creates
  - `RUN efa-draft` draft (no ICO);
  - `RUN efa-noico` issued, customer without IČO, country SK;
  - `RUN efa-ok` issued, customer IČO `12345678`, country SK, street/city/PSČ filled.
- steps:
    1. Show of `RUN efa-draft` -> card "Elektronická faktúra".
    2. Show of `RUN efa-noico` -> card.
    3. Show of `RUN efa-ok` -> card -> fetch the "Stiahnuť XML" link href with `page.request.get` -> reload Show.
- assert:
    - draft: "Pre e-faktúru chýba" lists "číslo faktúry"; no "Stiahnuť XML" link in the EFA card
    - noico: status badge "Neodoslaná"; missing list contains "IČO alebo Peppol ID odberateľa"; no "Stiahnuť XML" in the card
    - ok: no missing list; link "Stiahnuť XML" (EFA card, distinct from PDF button) present; response 200 `application/xml`, attachment `<number>.xml`, well-formed, root `Invoice` (UBL), contains `<cbc:ID>` = invoice number and `TaxCategory` ID `O` (non-VAT tenant)
    - ok after reload: badge "Pripravená"
    - all three: NO button "Odoslať e-faktúru" (driver `null` -> `can_submit` false)
- test_data: IČO 12345678.

## Scenario 12: Peppol ID in Settings supplier card and client form
- file: tests/e2e/efa-peppol.spec.ts
- route: /settings/invoicing, /clients, /clients/{id}
- project: desktop
- precondition: logged in.
- steps:
    1. /settings/invoicing -> supplier card field "Peppol ID" -> fill `abc` -> "Uložiť".
    2. Expect error "Peppol ID musí mať tvar schéma:identifikátor, napr. 0245:12345678."
    3. Fill `1234:12345678` -> "Uložiť" -> expect error "Schéma 1234 nie je v zozname podporovaných Peppol schém."
    4. Fill `0245:12345678` -> "Uložiť" -> "Nastavenia fakturácie boli uložené."; reload -> value persists.
    5. /clients -> create client via drawer: name `RUN klient`, required fields, "Peppol ID" = `0245:87654321` -> save -> open client Show.
    6. Edit the client (drawer) -> "Peppol ID" = `abc` -> save -> expect invalid-format error; cancel.
    7. afterAll: settings "Peppol ID" cleared + saved; delete `RUN klient`.
- assert:
    - visible: both validation messages (settings) + format error (client)
    - toast: "Nastavenia fakturácie boli uložené."
    - visible: persisted `0245:12345678` after reload
    - visible: client detail card shows "Peppol ID" `0245:87654321`
- test_data: `abc`, `1234:12345678`, `0245:12345678`, `0245:87654321`.

## Assumptions (orchestrator, owner delegated "urob ako najlepšie vieš")
- Business rules taken from the approved source plan (D1–D13) + code, no extra owner questions: partial state = derived "Čiastočne uhradená" badge; credit-note refund = "Zaevidovať vrátenie" on the credit note settles it; bulk skips reported with reasons, never 403; merged PDF cap 10 (cap UI not E2E-tested — covered by `InvoiceBulkPdfTest`); EFA submit hidden with null driver.
- Data stays in the dev DB (tagged `E2E-<timestamp>`), consumes FA-2026-NNNN numbers of "Demo Cleaning s.r.o." — acceptable for an undeployed greenfield project; nothing pre-existing is mutated except Settings fields that are restored afterwards.
- Exact field labels (subject-mode radio, recurring form fields, login form) confirmed live by the runner; Slovak strings above are from `resources/lang/sk/app.json`.
