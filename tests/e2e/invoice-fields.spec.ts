/**
 * e2e-flow: invoice fields — VAT/discount/deposit, SK fields, issue+QR, recurring, settings
 * surface: web (Inertia admin)
 * branch: feat/invoice-fields-vat-sk-standard
 *
 * Scenarios run sequentially S1→S6 in file order.
 * S1 persists IBAN to DB; S5 needs it for QR generation.
 *
 * App base URL: http://localhost:8080 (APP_PORT=80 in .env, but port 80 is occupied by another
 * project on this dev machine; containers started with APP_PORT=8080).
 */
import { test, expect, Page } from '@playwright/test'

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

async function selectClient(page: Page) {
  await page.getByRole('group', { name: 'Klient' }).getByRole('combobox').click()
  await page.getByRole('group', { name: 'Klient' }).getByRole('option').first().click()
}

// ---------------------------------------------------------------------------
// S1: Invoice settings — IBAN + SWIFT/BIC + defaults prefill verification
// ---------------------------------------------------------------------------

test(
  'S1: invoice settings — save IBAN, SWIFT, default constant_symbol and payment_type; verify prefill on create form',
  async ({ page }) => {
    /**
     * steps:
     *   navigate /settings/invoicing → fill IBAN + SWIFT/BIC + Predvolený KS → select Hotovosť
     *   → save → assert flash → navigate /invoices/create → assert prefill
     */

    // 1. Navigate to invoice settings
    await page.goto('/settings/invoicing')
    await expect(page.getByRole('heading', { name: 'Nastavenia faktúr', level: 1 })).toBeVisible()

    // 2. "Základné" section is the default active tab (heading is visible)
    await expect(page.getByRole('heading', { name: 'Základné', level: 2 })).toBeVisible()

    // 3-4. Fill IBAN and SWIFT
    await page.getByRole('group', { name: 'IBAN' }).getByRole('textbox').fill('SK3112000000198742637541')
    await page.getByRole('group', { name: 'SWIFT / BIC' }).getByRole('textbox').fill('TATRSKBX')

    // 5. Fill default constant_symbol
    await page.getByRole('group', { name: 'Predvolený konštantný symbol' }).getByRole('textbox').fill('0308')

    // 6. Select "Hotovosť" from custom combobox "Predvolená forma úhrady"
    await page.getByRole('group', { name: 'Predvolená forma úhrady' }).getByRole('combobox').click()
    await page.getByRole('option', { name: 'Hotovosť' }).click()

    // 7. Save settings
    await page.getByRole('button', { name: 'Uložiť nastavenia' }).click()

    // 8. Redirected to /invoices — flash visible
    await page.waitForURL(/\/invoices$/)
    await expect(page.getByText('Nastavenia faktúr boli uložené.')).toBeVisible()

    // 9. Navigate to invoice create form
    await page.goto('/invoices/create')

    // 10. Assert prefill
    await expect(
      page.getByRole('group', { name: 'Konštantný symbol' }).getByRole('textbox'),
    ).toHaveValue('0308')

    // Custom combobox shows "Hotovosť" as selected text
    await expect(
      page.getByRole('group', { name: 'Forma úhrady' }).getByRole('combobox'),
    ).toContainText('Hotovosť')
  },
)

// ---------------------------------------------------------------------------
// S2: Regular invoice — mixed VAT rates (23% + 19%) + discount + deposit
// ---------------------------------------------------------------------------

test(
  'S2: regular invoice — two VAT rates (23%+19%), row discount, deposit; assert subtotal/total/balance_due and VAT recap',
  async ({ page }) => {
    /**
     * steps:
     *   navigate /invoices/create → select first available client → fill item1 (23%, qty 2, price 100)
     *   → add item2 (19%, qty 1, price 50, discount 10) → fill Záloha 20 → submit
     *   → assert on Show page
     *
     * Calc: item1 base=200, item2 base=45 (50*0.9), subtotal=245
     * VAT: 23%→46, 19%→8.55; total=299.55; balance_due=299.55-20=279.55
     *
     * Note: the Show page renders totals in multiple DOM contexts (invoice doc + action form
     * wrappers). Use .first() to target the primary invoice document values unambiguously.
     */

    await page.goto('/invoices/create')

    // Confirm "Jednorazová" is the default type
    await expect(page.getByRole('radio', { name: 'Jednorazová' })).toBeChecked()

    // Select first available client (seed-independent)
    await selectClient(page)

    // Fill item row 1: description, qty=2, price=100, VAT stays at 23%
    await page.getByRole('textbox', { name: 'Popis' }).fill('Upratovanie kancelárie')
    await page.getByRole('spinbutton', { name: 'Množstvo' }).fill('2')
    await page.getByRole('spinbutton', { name: 'Cena/jedn.' }).fill('100')

    // Add row 2
    await page.getByRole('button', { name: 'Pridať položku' }).click()

    // Fill item row 2: description, price=50, discount=10, VAT=19%
    await page
      .getByRole('row', { name: /Odstrániť položku 2/ })
      .getByPlaceholder('Popis položky…')
      .fill('Dezinfekcia')
    await page
      .getByRole('row', { name: /Dezinfekcia/ })
      .getByLabel('Cena/jedn.')
      .fill('50')
    await page
      .getByRole('row', { name: /Dezinfekcia/ })
      .getByLabel('Zľava')
      .fill('10')
    await page
      .getByRole('row', { name: /Dezinfekcia/ })
      .getByLabel('Sadzba DPH')
      .selectOption(['19%'])

    // Záloha = 20
    await page.getByRole('group', { name: 'Záloha' }).getByRole('spinbutton').fill('20')

    // Submit
    await page.getByRole('button', { name: 'Nová faktúra' }).click()
    await page.waitForURL(/\/invoices\/[0-9a-f-]+$/)

    // Assert flash
    await expect(page.getByText('Faktúra bola úspešne vytvorená.')).toBeVisible()

    // Assert subtotal, total, deposit, balance_due.
    // .first() used because the Show page renders amounts in the invoice doc and in action
    // form wrappers (both visible in the DOM at the same time).
    await expect(page.getByText('245.00').first()).toBeVisible()
    await expect(page.getByText('299.55').first()).toBeVisible()
    await expect(page.getByText('20.00').first()).toBeVisible()
    await expect(page.getByText('279.55').first()).toBeVisible()

    // "Zostatok na úhradu" label
    await expect(page.getByText('Zostatok na úhradu').first()).toBeVisible()

    // VAT recap rows (both rates visible in recap table)
    await expect(page.getByText('23%').first()).toBeVisible()
    await expect(page.getByText('19%').first()).toBeVisible()
  },
)

// ---------------------------------------------------------------------------
// S3: Monthly invoice — period fields appear and persist
// ---------------------------------------------------------------------------

test(
  'S3: monthly invoice — "Mesačná" radio shows period inputs; period persists on Show page',
  async ({ page }) => {
    /**
     * steps:
     *   navigate /invoices/create → click Mesačná → assert Obdobie od + Obdobie do visible
     *   → fill dates → select first available client → fill item → submit → assert on Show page
     *
     * Note: "Mesačná" text appears in multiple elements on the Show page (form radio-button
     * style badge + invoice type badge). Use .first() to target the first visible occurrence.
     */

    await page.goto('/invoices/create')

    // Click "Mesačná" radio
    await page.getByRole('radio', { name: 'Mesačná' }).click()
    await expect(page.getByRole('radio', { name: 'Mesačná' })).toBeChecked()

    // Period date inputs should now appear
    await expect(page.getByRole('group', { name: 'Obdobie od' }).getByRole('textbox')).toBeVisible()
    await expect(page.getByRole('group', { name: 'Obdobie do' }).getByRole('textbox')).toBeVisible()

    // Fill period
    await page.getByRole('group', { name: 'Obdobie od' }).getByRole('textbox').fill('2026-06-01')
    await page.getByRole('group', { name: 'Obdobie do' }).getByRole('textbox').fill('2026-06-30')

    // Select first available client (seed-independent)
    await selectClient(page)

    // Fill item
    await page.getByRole('textbox', { name: 'Popis' }).fill('Mesačný paušál')
    await page.getByRole('spinbutton', { name: 'Cena/jedn.' }).fill('150')

    // Submit
    await page.getByRole('button', { name: 'Nová faktúra' }).click()
    await page.waitForURL(/\/invoices\/[0-9a-f-]+$/)

    // Assert flash
    await expect(page.getByText('Faktúra bola úspešne vytvorená.')).toBeVisible()

    // "Mesačná" badge in page header (first occurrence)
    await expect(page.getByText('Mesačná').first()).toBeVisible()

    // Period section label
    await expect(page.getByText('Obdobie').first()).toBeVisible()

    // Period dates formatted sk-SK (e.g. "1. 6. 2026 – 30. 6. 2026")
    await expect(page.getByText(/1\. 6\. 2026/).first()).toBeVisible()
    await expect(page.getByText(/30\. 6\. 2026/).first()).toBeVisible()
  },
)

// ---------------------------------------------------------------------------
// S4: SK-standard fields — constant_symbol, specific_symbol, payment_type, header/footer text
// ---------------------------------------------------------------------------

test(
  'S4: SK-standard fields — KS, ŠS, Hotovosť, úvodný/záverečný text persist on Show page',
  async ({ page }) => {
    /**
     * steps:
     *   navigate /invoices/create → select first available client → fill Konštantný symbol + Špecifický symbol
     *   → select Hotovosť → fill Úvodný text + Záverečný text → fill item → submit
     *   → assert KS/ŠS/payment_type/texts on Show page
     *
     * Note: "Hotovosť" appears in multiple elements (combobox span + rounding option span +
     * invoice doc paragraph). Use getByRole('paragraph') to scope to the <p> element on the
     * Show page which renders the payment type.
     */

    await page.goto('/invoices/create')

    // Select first available client (seed-independent)
    await selectClient(page)

    // SK-standard fields (constant_symbol may be prefilled from S1 defaults — overwrite)
    await page.getByRole('group', { name: 'Konštantný symbol' }).getByRole('textbox').fill('0308')
    await page.getByRole('group', { name: 'Špecifický symbol' }).getByRole('textbox').fill('2026001')

    // Payment type: already "Hotovosť" from S1 defaults; ensure it is set
    await page.getByRole('group', { name: 'Forma úhrady' }).getByRole('combobox').click()
    await page.getByRole('option', { name: 'Hotovosť' }).click()

    // Header and footer texts
    await page.getByRole('group', { name: 'Úvodný text' }).getByRole('textbox').fill('Vážený zákazník,')
    await page.getByRole('group', { name: 'Záverečný text' }).locator('textarea').fill('Ďakujeme za spoluprácu.')

    // Item
    await page.getByRole('textbox', { name: 'Popis' }).fill('Položka')
    await page.getByRole('spinbutton', { name: 'Cena/jedn.' }).fill('100')

    // Submit
    await page.getByRole('button', { name: 'Nová faktúra' }).click()
    await page.waitForURL(/\/invoices\/[0-9a-f-]+$/)

    // Assertions on Show page
    await expect(page.getByText('Faktúra bola úspešne vytvorená.')).toBeVisible()

    // constant_symbol rendered under label "KS" (unique text — no .first() needed)
    await expect(page.getByText('0308')).toBeVisible()
    // specific_symbol rendered under label "ŠS" (unique)
    await expect(page.getByText('2026001')).toBeVisible()
    // payment_type: scope to <p> elements (combobox spans + rounding option contain same text)
    await expect(page.getByRole('paragraph').filter({ hasText: 'Hotovosť' })).toBeVisible()
    // header text above items table (unique in page)
    await expect(page.getByText('Vážený zákazník,')).toBeVisible()
    // footer text below totals (unique in page)
    await expect(page.getByText('Ďakujeme za spoluprácu.')).toBeVisible()
  },
)

// ---------------------------------------------------------------------------
// S5: Issue invoice — Draft → Issued + number assignment + QR + PDF link
// ---------------------------------------------------------------------------

test(
  'S5: issue invoice — Draft→Issued transition; number assigned; QR img rendered; PDF link present',
  async ({ page }) => {
    /**
     * steps:
     *   navigate /invoices/create → select first available client → fill item (200) → submit (Draft)
     *   → click "Vystaviť" in Akcie sidebar → issue dialog appears → leave number blank
     *   → click "Vystaviť" inside dialog → assert status, number, QR, PDF link
     *
     * Precondition: S1 already ran, IBAN = SK3112000000198742637541 (needed for QR)
     */

    await page.goto('/invoices/create')

    // Select first available client (seed-independent)
    await selectClient(page)

    // Item: Upratovanie, qty=1, price=200
    await page.getByRole('textbox', { name: 'Popis' }).fill('Upratovanie')
    await page.getByRole('spinbutton', { name: 'Cena/jedn.' }).fill('200')

    // Create Draft
    await page.getByRole('button', { name: 'Nová faktúra' }).click()
    await page.waitForURL(/\/invoices\/[0-9a-f-]+$/)
    await expect(page.getByText('Faktúra bola úspešne vytvorená.')).toBeVisible()

    // Click "Vystaviť" in the Akcie sidebar
    await page.getByRole('button', { name: 'Vystaviť' }).click()

    // Issue dialog appears
    await expect(page.getByRole('dialog')).toBeVisible()
    await expect(page.getByRole('heading', { name: 'Vystaviť' })).toBeVisible()

    // Leave custom number blank (auto-assign) and confirm
    await page.getByRole('dialog').getByRole('button', { name: 'Vystaviť' }).click()

    // Wait for dialog to close (Inertia reloads the page props)
    await expect(page.getByRole('dialog')).not.toBeVisible()

    // Assert: status badge "Vystavená"
    await expect(page.getByText('Vystavená')).toBeVisible()

    // Invoice number assigned (heading is not "Koncept")
    const heading = page.getByRole('heading', { level: 1 })
    await expect(heading).toBeVisible()
    await expect(heading).not.toHaveText('Koncept')

    // QR img with alt "QR platba" (requires supplier_iban from S1 + variable_symbol from issuance)
    await expect(page.getByRole('img', { name: /QR platba/i })).toBeVisible()

    // PDF link present with correct href pattern
    const pdfLink = page.getByRole('link', { name: 'Stiahnuť PDF' })
    await expect(pdfLink).toBeVisible()
    await expect(pdfLink).toHaveAttribute('href', /\/invoices\/[^/]+\/pdf/)
  },
)

// ---------------------------------------------------------------------------
// S6: Recurring invoice template — create + assert saved + status Active
// ---------------------------------------------------------------------------

test(
  'S6: recurring invoice — create template; assert Aktívna status and Mesačne frequency on Show page',
  async ({ page }) => {
    /**
     * steps:
     *   navigate /recurring-invoices/create → fill Názov šablóny + Frekvencia (Mesačne already)
     *   → fill Deň v mesiaci=1, Dátum začiatku, Splatnosť (already default 14)
     *   → select Bankový prevod → select first available client → fill item (Mesačný paušál, 300, 23%)
     *   → fill Obdobie od/do (required for Mesačná type) → submit
     *   → assert Aktívna + Mesačne + template name heading on Show page
     *
     * Note: "Mesačne" appears in both the status badge area and the Rozvrh display section.
     * getByText('Mesačne') also matches ancestor containers (substring match).
     * Use .first() to target the topmost badge — same pattern as S3's Mesačná assertion.
     */

    await page.goto('/recurring-invoices/create')

    // Template name
    await page.getByRole('group', { name: 'Názov šablóny' }).getByRole('textbox').fill('Mesačné upratovanie')

    // Frekvencia is already "Mesačne" — confirm and leave
    await expect(
      page.getByRole('group', { name: 'Frekvencia' }).getByRole('combobox'),
    ).toContainText('Mesačne')

    // Deň v mesiaci is already 1 — confirm and leave
    await expect(
      page.getByRole('group', { name: 'Deň v mesiaci' }).getByRole('spinbutton'),
    ).toHaveValue('1')

    // Dátum začiatku
    await page.getByRole('group', { name: 'Dátum začiatku' }).getByRole('textbox').fill('2026-06-19')

    // Splatnosť (dni) = 14 (already default)
    await expect(
      page.getByRole('group', { name: 'Splatnosť (dni)' }).getByRole('spinbutton'),
    ).toHaveValue('14')

    // Termination: "Navždy" is already checked
    await expect(page.getByRole('radio', { name: 'Navždy' })).toBeChecked()

    // Payment type: Bankový prevod (custom combobox inside "Forma úhrady" group)
    await page.getByRole('group', { name: 'Forma úhrady' }).getByRole('combobox').click()
    await page.getByRole('option', { name: 'Bankový prevod' }).click()

    // Select first available client (seed-independent)
    await selectClient(page)

    // Item: Mesačný paušál, qty=1, price=300, VAT=23%
    await page.getByRole('textbox', { name: 'Popis' }).fill('Mesačný paušál')
    await page.getByRole('spinbutton', { name: 'Cena/jedn.' }).fill('300')
    await page.getByLabel('Sadzba DPH').selectOption(['23%'])

    // Obdobie od/do required when Typ faktúry = Mesačná
    await page.getByRole('group', { name: 'Obdobie od' }).getByRole('textbox').fill('2026-06-01')
    await page.getByRole('group', { name: 'Obdobie do' }).getByRole('textbox').fill('2026-06-30')

    // Submit
    await page.getByRole('button', { name: 'Nová opakovaná faktúra' }).click()
    await page.waitForURL(/\/recurring-invoices\/[0-9a-f-]+$/)

    await expect(page.getByText('Opakovaná faktúra bola úspešne vytvorená.')).toBeVisible()
    // Scope to heading to avoid strict-mode collision with breadcrumb <li>
    await expect(page.getByRole('heading', { name: 'Mesačné upratovanie' })).toBeVisible()
    await expect(page.getByText('Aktívna').first()).toBeVisible()
    // .first() — avoids strict-mode collision: "Mesačne" appears in status badge AND Rozvrh section
    await expect(page.getByText('Mesačne').first()).toBeVisible()
  },
)
