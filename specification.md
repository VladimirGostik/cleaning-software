# CleanMaster — Dizajnová špecifikácia webovej aplikácie

## Účel dokumentu

Tento dokument slúži ako kompletný dizajnový brief na vygenerovanie UI návrhov webovej aplikácie CleanMaster. Každá obrazovka je popísaná tak, aby z nej bolo možné vytvoriť funkčný React mockup.

---

## Design System

### Estetika a tón

Profesionálna, čistá, moderná aplikácia pre manažment upratovacej firmy. Cieľová skupina sú majitelia malých firiem na Slovensku — ľudia ktorí nie sú technicky zdatní, potrebujú jednoduchosť a prehľadnosť. Dizajn musí byť:

- **Čistý a prehľadný** — žiadna vizuálna záťaž, veľa bieleho priestoru
- **Profesionálny ale priateľský** — nie korporátny, nie detský
- **Jasná informačná hierarchia** — najdôležitejšie čísla a akcie na prvý pohľad
- **Mobile-ready** — responzívny, funguje na tablete aj desktope

### Farebná paleta

- **Primary**: #2563EB (modrá — dôvera, profesionalita)
- **Primary Dark**: #1D4ED8
- **Primary Light**: #DBEAFE
- **Success**: #16A34A (zelená — aktívne, uhradené, schválené)
- **Warning**: #F59E0B (oranžová — nefakturované, končiace)
- **Danger**: #DC2626 (červená — po splatnosti, problém)
- **Neutral 50**: #F8FAFC (pozadie)
- **Neutral 100**: #F1F5F9 (karty)
- **Neutral 200**: #E2E8F0 (bordery)
- **Neutral 500**: #64748B (sekundárny text)
- **Neutral 800**: #1E293B (primárny text)
- **Neutral 900**: #0F172A (nadpisy)
- **White**: #FFFFFF

### Typografia

- **Nadpisy**: Plus Jakarta Sans (bold, semi-bold)
- **Body**: Plus Jakarta Sans (regular, medium)
- **Monospace (čísla faktúr, kódy)**: JetBrains Mono
- **Veľkosti**: H1: 28px, H2: 22px, H3: 18px, Body: 14px, Small: 12px, Caption: 11px

### Spacing a Grid

- **Sidebar**: 260px šírka, fixná
- **Content area**: fluid, max-width 1400px, padding 24px
- **Card padding**: 20px
- **Gap medzi kartami**: 16px
- **Border radius**: 8px (karty), 6px (buttony, inputy), 12px (modály)

### Komponenty (shared)

- **Badge/Tag**: malý zaoblený label s farbou podľa stavu (success/warning/danger/neutral)
- **Stat Card**: číslo + label + voliteľný trend, hover efekt
- **Data Table**: zebra striping, sortovateľné stĺpce, hover highlight, pagination
- **Empty State**: ilustrácia + text + CTA button
- **Modal/Dialog**: centered, overlay backdrop, max-width 560px
- **Form Field**: label hore, input pod ním, error message červenou, asterisk pre povinné
- **Button Primary**: modrý fill, biele písmo, hover darken
- **Button Secondary**: biely fill, modrý border, modrý text
- **Button Danger**: červený fill pre destructive akcie (len v confirmačných dialógoch)
- **Dropdown**: native select alebo custom s vyhľadávaním pre dlhé zoznamy
- **Toast/Notification**: vpravo hore, auto-dismiss po 5s, ikona + text

---

## Globálny layout aplikácie

### Štruktúra

```
┌─────────────────────────────────────────────────┐
│ TOPBAR (h: 56px)                                │
│ [Logo] [Firma filter ▼] [Jazyk ▼] [Notif 🔔] [Avatar ▼] │
├──────────┬──────────────────────────────────────┤
│ SIDEBAR  │ CONTENT AREA                         │
│ (260px)  │                                      │
│          │ ┌─ Page Header ─────────────────────┐ │
│ [Menu]   │ │ Nadpis stránky    [Akčné buttony] │ │
│          │ └───────────────────────────────────┘ │
│          │                                      │
│          │ ┌─ Content ────────────────────────┐ │
│          │ │                                  │ │
│          │ │  (karty, tabuľky, formuláre)     │ │
│          │ │                                  │ │
│          │ └──────────────────────────────────┘ │
│          │                                      │
└──────────┴──────────────────────────────────────┘
```

### Sidebar — Navigačné menu

Sidebar je fixný na ľavej strane, tmavé pozadie (#0F172A), svetlý text. Aktívna položka má primary modrý highlight.

Menu položky (zobrazené podľa oprávnení používateľa):

1. **Dashboard** (ikona: LayoutDashboard)
2. **Klienti** (ikona: Users)
3. **Objekty** (ikona: Building2)
4. **Cenové ponuky** (ikona: FileText)
5. **Zmluvy** (ikona: FileSignature)
6. **Rozvrh** (ikona: Calendar)
7. **Zamestnanci** (ikona: UserCog)
8. **Faktúry** (ikona: Receipt)
9. **Šablóny** (ikona: FolderTemplate)
10. **Notifikácie** (ikona: Bell) — s červeným badge ak sú neprečítané

---

Oddelovač (separator line) ---

11. **Správa oprávnení** (ikona: Shield)
12. **Nastavenia** (ikona: Settings)

### Topbar

- Logo (ľavý okraj, vedľa sidebar toggle pre mobile)
- **Firma filter**: dropdown "Všetky firmy" / konkrétna firma — mení filter dát v celej aplikácii
- **Jazyk**: SK / EN / UA toggle
- **Notifikácie**: bell ikona s počtom neprečítaných
- **Používateľský avatar**: dropdown s "Profil", "Odhlásiť sa"

---

## Obrazovky — Fáza 1 (Admin portál)

---

### SCREEN: Prihlásenie

**URL**: `/login`
**Layout**: Bez sidebaru a topbaru. Centrovený formulár na stránke. Split layout — ľavá strana vizuál/branding (modrý gradient s ilustráciou upratovania), pravá strana formulár.

**Ľavá strana (60%)**:
- Veľký nadpis: "Celá vaša firma pod kontrolou" (biely text na modrom gradient pozadí)
- Podnadpis: "Správa klientov, zmlúv, zamestnancov a faktúr na jednom mieste."
- Abstraktná ilustrácia alebo dashboard screenshot

**Pravá strana (40%) — formulár**:
- Logo CleanMaster (hore)
- H2: "Prihláste sa"
- Input: E-mail (ikona mail)
- Input: Heslo (ikona lock, toggle viditeľnosti)
- Checkbox: "Zapamätať si ma"
- Button Primary: "Prihlásiť sa" (plná šírka)
- Link: "Zabudli ste heslo?"
- Oddelovač
- Jazyk selector: SK | EN | UA (malé textové linky)

**Chybové stavy**:
- Nesprávne údaje: červený toast "Nesprávny e-mail alebo heslo"
- Po 5 neúspechoch: "Účet dočasne zablokovaný. Skúste o 15 minút."

---

### SCREEN: Dashboard

**URL**: `/dashboard`
**Layout**: Štandardný so sidebar + topbar.

**Page Header**:
- H1: "Dashboard"
- Firma filter zobrazuje aktuálne vybranú firmu v topbare

**Content — Stat Cards (1. riadok, 4 karty v rade)**:

| Karta | Hodnota | Farba | Ikona |
|---|---|---|---|
| Dnešné zákazky | číslo (napr. "8") | Primary blue | Calendar |
| Zákazky bez priradenia | číslo | Danger red ak > 0, neutral ak 0 | UserX |
| Nefakturované zákazky | číslo | Warning orange ak > 0 | FileWarning |
| Končiace zmluvy (30d) | číslo | Warning orange ak > 0 | Clock |

**Content — Dnešné zákazky (tabuľka, 2. sekcia)**:
- H3: "Dnešné zákazky"
- Tabuľka: Čas | Objekt | Klient | Upratovačka | Stav (badge) | Firma (farebný tag)
- Kliknuteľné riadky → presmerovanie na detail zákazky

**Content — Nahlásené neprítomnosti (3. sekcia, vedľa alebo pod)**:
- H3: "Nahlásené neprítomnosti"
- Malé karty: Meno upratovačky | Dátum od-do | Počet ovplyvnených zákaziek (červený badge)
- Empty state ak žiadne: "Žiadne nahlásené neprítomnosti" s ikonou ✓

**Content — Nefakturované zákazky (4. sekcia)**:
- H3: "Nefakturované zákazky"
- Link: "Zobraziť všetky" → presmeruje na Faktúry s filtrom
- Zoznam: Dátum | Objekt | Klient | Suma | Button "Fakturovať"

---

### SCREEN: Klienti / Zoznam

**URL**: `/clients`
**Layout**: Štandardný.

**Page Header**:
- H1: "Klienti"
- Button Primary: "+ Pridať klienta"

**Filtre (pod headerom)**:
- Search input: "Hľadať podľa názvu, IČO, e-mailu..." (ikona Search)
- Dropdown: Typ klienta — Všetci / Firemní / Súkromní

**Tabuľka**:
- Stĺpce: Názov/Meno | Typ (badge: "Firemný" modrý, "Súkromný" zelený) | Objekty (počet) | Aktívne zmluvy (počet) | E-mail | Akcie (→ detail)
- Kliknuteľné riadky → detail klienta
- Pagination dole (10/25/50 per page)

**Empty state**: Ilustrácia + "Zatiaľ nemáte žiadnych klientov" + Button "Pridať prvého klienta"

---

### SCREEN: Klienti / Pridať klienta

**URL**: `/clients/create`
**Layout**: Štandardný. Centrovený formulár (max-width 640px).

**Page Header**:
- H1: "Nový klient"
- Button Secondary: "Zrušiť" (návrat na zoznam)

**Formulár**:
- **Sekcia 1**: Typ klienta
  - Radio buttons alebo segmented control: Firemný | Súkromný
  - (animovaná zmena formulárových polí podľa výberu)

- **Sekcia 2**: Základné údaje (karta s bielym pozadím)
  - Ak Firemný: Názov firmy* | IČO* | DIČ | IČ DPH
  - Ak Súkromný: Meno* | Priezvisko*
  - Fakturačná adresa* (ulica, mesto, PSČ — 3 stĺpce)

- **Sekcia 3**: Kontaktné údaje (karta)
  - Kontaktná osoba (ak firemný)
  - E-mail*
  - Telefón

- **Sekcia 4**: Poznámka (karta)
  - Textarea: "Interná poznámka ku klientovi"

**Footer formulára**:
- Button Primary: "Uložiť klienta"
- Button Secondary: "Zrušiť"

---

### SCREEN: Klienti / Detail klienta

**URL**: `/clients/:id`
**Layout**: Štandardný.

**Page Header**:
- H1: Názov klienta / Meno
- Badge: typ (Firemný/Súkromný)
- Button Group: "Upraviť" (secondary) | "Pridať objekt" (primary) | "..." menu (Vytvoriť ponuku, Vytvoriť faktúru, Zmazať)

**Content — 2 stĺpcový layout**:

**Ľavý stĺpec (65%)**:

- **Karta: Objekty** (H3 + tabuľka)
  - Stĺpce: Názov objektu | Adresa | Aktívna zmluva (áno/nie badge) | Akcie (→ detail)
  - Button: "+ Pridať objekt"

- **Karta: Zmluvy** (H3 + tabuľka)
  - Stĺpce: Číslo | Objekt | Platnosť | Stav (badge: Draft/Aktívna/Ukončená) | Akcie
  - Prázdny stav ak žiadne

- **Karta: Cenové ponuky** (H3 + tabuľka)
  - Stĺpce: Číslo | Objekt | Dátum | Stav (badge) | Suma | Akcie

- **Karta: Faktúry** (H3 + tabuľka)
  - Stĺpce: Číslo | Dátum | Suma | Stav (badge: Draft/Vystavená/Uhradená/Po splatnosti) | Akcie

**Pravý stĺpec (35%)**:

- **Karta: Kontaktné údaje**
  - Riadky: E-mail (kliknuteľný mailto), Telefón (kliknuteľný tel), Adresa, IČO, DIČ, IČ DPH
  - Kompaktný layout, ikony pred každým riadkom

- **Karta: Poznámka**
  - Text poznámky (ak existuje)

---

### SCREEN: Objekty / Pridať objekt

**URL**: `/objects/create` alebo `/clients/:id/objects/create`
**Layout**: Štandardný. Centrovený formulár (max-width 640px).

**Page Header**:
- H1: "Nový objekt"

**Formulár**:
- **Sekcia 1**: Priradenie (karta)
  - Klient*: Dropdown s vyhľadávaním. Ak sa prišlo z detailu klienta, je predvyplnený a locked (disabled s info tooltipom).

- **Sekcia 2**: Základné údaje (karta)
  - Názov objektu* (napr. "Kancelária Hlavná 5")
  - Typ objektu*: Dropdown (Kancelária / Byt / Dom / Spoločné priestory / Iné)
  - Adresa*: ulica, mesto, PSČ
  - Rozloha (m²): number input
  - Počet miestností: number input

- **Sekcia 3**: Kontakt na mieste (karta)
  - Kontaktná osoba
  - Telefón

- **Sekcia 4**: Prístupové informácie (karta, žlté/oranžové pozadie — dôležité info)
  - Textarea: "Kódy k alarmu, kde sú kľúče, špeciálne pokyny..."
  - Info text: "Tieto informácie uvidí upratovačka pri zákazke."

- **Sekcia 5**: Poznámky (karta)
  - Textarea: "Citlivé povrchy, špeciálne požiadavky..."

**Footer**: Button Primary "Uložiť" | Button Secondary "Zrušiť"

---

### SCREEN: Objekty / Detail objektu

**URL**: `/objects/:id`
**Layout**: Štandardný.

**Page Header**:
- H1: Názov objektu
- Pod-nadpis: Adresa (šedý text)
- Badge: Typ objektu
- Button Group: "Upraviť" | "Vytvoriť ponuku" | "Vytvoriť faktúru"

**Content — Tabs layout** (prepínanie medzi sekciami):

**Tab: Prehľad**
- Stat karty v riadku: Rozloha (m²) | Počet miestností | Aktívna zmluva (áno/nie) | Priradené upratovačky (počet)
- Karta "Klient": meno/názov, odkaz na detail klienta
- Karta "Prístupové informácie": žlté pozadie, dôležité pre upratovačky
- Karta "Rozpis prác": tabuľka úloh z aktívnej zmluvy (služba | popis | frekvencia)

**Tab: Cenové ponuky**
- Tabuľka cenových ponúk viazaných na tento objekt

**Tab: Zmluvy**
- Tabuľka zmlúv (vrátane historických) viazaných na tento objekt

**Tab: Faktúry**
- Tabuľka faktúr viazaných na tento objekt

**Tab: Rozvrh**
- Kalendárový pohľad zákaziek na tomto objekte

---

### SCREEN: Cenové ponuky / Zoznam

**URL**: `/quotes`
**Layout**: Štandardný.

**Page Header**:
- H1: "Cenové ponuky"
- Button Primary: "+ Nová cenová ponuka"

**Filtre**:
- Search: "Hľadať podľa čísla, klienta..."
- Dropdown: Stav — Všetky / Draft / Odoslaná / Schválená / Zamietnutá / Expirovaná
- Dropdown: Klient

**Tabuľka**:
- Stĺpce: Číslo | Klient | Objekt | Dátum | Platná do | Suma | Stav (badge) | Akcie

Stav badges:
- Draft: neutral/šedý
- Odoslaná: primary/modrý
- Schválená: success/zelený
- Zamietnutá: danger/červený
- Expirovaná: warning/oranžový

---

### SCREEN: Cenové ponuky / Vytvoriť

**URL**: `/quotes/create`
**Layout**: Štandardný. Širší formulár (max-width 900px) kvôli tabuľke položiek.

**Page Header**:
- H1: "Nová cenová ponuka"

**Formulár**:

- **Sekcia 1**: Základné údaje (karta)
  - Klient*: Dropdown s vyhľadávaním
  - Objekt*: Dropdown, filtrovaný podľa vybraného klienta (animovaná zmena po výbere klienta)
  - Platná do*: Date picker

- **Sekcia 2**: Položky ponuky (karta, hlavná sekcia)
  - H3: "Položky cenovej ponuky"
  - **Editovateľná tabuľka** — každý riadok je formulár:

  | Služba* | Popis prác* | Frekvencia* | Jednotka* | Množstvo* | Cena/j.* | Suma |
  |---|---|---|---|---|---|---|
  | Input | Textarea (expandovateľný) | Dropdown | Dropdown | Number | Number | Computed |
  | "Upratovanie kancelárií" | "Vysávanie, umývanie podláh..." | 3×/týždeň | Paušál | 1 | 450 | 450 € |

  - Každý riadok má ikonu ✕ na odstránenie (disabled ak je len 1 riadok)
  - Button dole: "+ Pridať položku" (link style)

  - **Sumár** (pravá strana, pod tabuľkou):
    - Suma bez DPH: **450,00 €** (ak firma je platca DPH)
    - DPH 23%: 103,50 € (ak firma je platca DPH)
    - **Suma s DPH: 553,50 €** (tučné, väčšie písmo)
    - Ak firma NIE JE platca DPH: len **Celková suma: 450,00 €**

- **Sekcia 3**: Poznámka (karta)
  - Textarea: "Poznámka pre klienta na ponuke"

**Footer**: Button Primary "Uložiť ako draft" | Button Secondary "Zrušiť"

---

### SCREEN: Cenové ponuky / Detail

**URL**: `/quotes/:id`
**Layout**: Štandardný.

**Page Header**:
- H1: "Cenová ponuka CP-2026-0001"
- Badge: Stav (Draft / Odoslaná / Schválená / ...)
- Button Group (dynamicky podľa stavu):
  - Draft: "Upraviť" | "Odoslať klientovi" (primary) | "..." (Duplikovať, Zamietnuť)
  - Odoslaná: "Schváliť → Vytvoriť zmluvu" (primary, zelený) | "..." (Duplikovať, Zamietnuť)
  - Schválená: "Stiahnuť PDF" | odkaz na vytvorenú zmluvu

**Content — 2 stĺpce**:

**Ľavý stĺpec (65%)**:

- **Karta: Položky ponuky** (read-only tabuľka)
  - Služba | Popis prác | Frekvencia | Jednotka | Množstvo | Cena | Suma
  - Sumár na konci

- **Karta: Rozpis prác (náhľad)**
  - H3: "Vygenerovaný rozpis prác"
  - Tabuľka: Služba | Popis (čo sa robí) | Frekvencia
  - Info text: "Tento rozpis prác sa priradí k objektu po vytvorení zmluvy."

**Pravý stĺpec (35%)**:

- **Karta: Informácie**
  - Klient: link
  - Objekt: link
  - Dátum vytvorenia
  - Platná do (červenou ak po dátume)
  - Poznámka

- **Karta: Akcie**
  - Button: "Stiahnuť PDF" (ikona Download)
  - Button: "Odoslať e-mailom" (ikona Mail)

---

### SCREEN: Zmluvy / Zoznam

**URL**: `/contracts`
**Layout**: Štandardný.

**Page Header**:
- H1: "Zmluvy"
- Info text: "Zmluvy sa vytvárajú zo schválených cenových ponúk."

**Filtre**:
- Search: "Hľadať podľa čísla, klienta..."
- Dropdown: Stav — Všetky / Draft / Aktívna / Pozastavená / Ukončená / Expirovaná
- Dropdown: Typ — Na dobu určitú / Na dobu neurčitú
- Dropdown: Klient
- Toggle: "Končiace do 30 dní" (zvýraznený ak aktívny)

**Tabuľka**:
- Stĺpce: Číslo | Klient | Objekt | Typ | Platnosť od-do | Mesačná suma | Stav (badge) | Akcie

Stav badges:
- Draft: neutral/šedý
- Aktívna: success/zelený
- Pozastavená: warning/oranžový
- Ukončená: neutral/šedý s prečiarknutím
- Expirovaná: danger/červený

---

### SCREEN: Zmluvy / Detail

**URL**: `/contracts/:id`
**Layout**: Štandardný.

**Page Header**:
- H1: "Zmluva ZML-2026-0001"
- Badge: Stav
- Badge: Typ (Na dobu určitú / Na dobu neurčitú)
- Button Group:
  - Aktívna: "Upraviť" | "Predĺžiť" | "Vytvoriť faktúru" (primary) | "..." (Vytvoriť novú verziu, Ukončiť, Stiahnuť PDF)
  - Draft: "Upraviť" | "Aktivovať" (primary zelený) | "Stiahnuť PDF"

**Content — Tabs**:

**Tab: Prehľad**
- **Karta: Informácie** (2 stĺpce)
  - Ľavý: Klient (link), Objekt (link), Zdrojová cenová ponuka (link)
  - Pravý: Typ zmluvy, Platnosť od-do, Výpovedná lehota, Mesačná suma
- **Karta: Rozpis prác** (tabuľka)
  - Služba | Popis | Frekvencia
- **Karta: Priradené upratovačky** (tabuľka)
  - Meno | Deň/čas | Stav
- **Karta: Príloha** — nahraný scan zmluvy (PDF viewer alebo download link)

**Tab: Faktúry**
- Tabuľka faktúr generovaných z tejto zmluvy

**Tab: Zákazky**
- Posledných 20 zákaziek pod touto zmluvou

**Tab: História zmien (Log)**
- Timeline layout (vertikálna čiara s bodkami)
- Každý záznam: Dátum + čas | Kto zmenil | Čo sa zmenilo (pole: stará hodnota → nová hodnota)
- Typy: Vytvorenie (zelená bodka) | Úprava (modrá) | Predĺženie (oranžová) | Ukončenie (červená)

---

### SCREEN: Zmluvy / Vytvoriť (z cenovej ponuky)

**URL**: `/contracts/create?quote_id=...`
**Layout**: Štandardný. Formulár (max-width 720px).

**Page Header**:
- H1: "Nová zmluva"
- Info banner (modrý): "Vytvárané z cenovej ponuky CP-2026-0001"

**Formulár**:
- **Sekcia 1**: Základné údaje (karta, read-only sivé pozadie)
  - Číslo zmluvy: predvyplnené, editovateľné (monospace font)
  - Klient: read-only, link
  - Objekt: read-only, link

- **Sekcia 2**: Platnosť (karta)
  - Typ zmluvy*: Segmented control (Na dobu určitú | Na dobu neurčitú)
  - Platnosť od*: Date picker
  - Platnosť do: Date picker (viditeľné len ak doba určitá)
  - Výpovedná lehota (dni): Number (viditeľné len ak doba neurčitá)

- **Sekcia 3**: Rozpis prác (karta, read-only)
  - Prevzatý z cenovej ponuky
  - Tabuľka: Služba | Popis | Frekvencia
  - Mesačná suma: computed

- **Sekcia 4**: Príloha (karta)
  - File upload: "Nahrať scan podpísanej zmluvy (PDF)"
  - Drag & drop zóna

- **Sekcia 5**: Poznámky (karta)
  - Textarea

**Footer**: Button "Uložiť ako draft" | Button Primary "Aktivovať zmluvu" (zelený) | "Zrušiť"

---

### SCREEN: Zamestnanci / Zoznam

**URL**: `/employees`
**Layout**: Štandardný.

**Page Header**:
- H1: "Zamestnanci"
- Button Primary: "+ Pridať zamestnanca"

**Filtre**:
- Search: meno
- Dropdown: Rola — Všetky / Upratovačka / Vedúca / Sekretárka / Účtovníčka / Vlastné
- Dropdown: Stav — Aktívni / Neaktívni

**Tabuľka**:
- Stĺpce: Meno | Rola (badge) | Priradené objekty (počet) | Typ zmluvy | Stav (zelený/šedý dot) | Firma (farebný tag, ak multi-firma) | Akcie

---

### SCREEN: Zamestnanci / Pridať

**URL**: `/employees/create`
**Layout**: Formulár (max-width 720px).

**Page Header**:
- H1: "Nový zamestnanec"

**Formulár**:

- **Sekcia 1**: Identifikácia (karta)
  - E-mail*: Input. Info text: "Ak používateľ s týmto e-mailom už existuje, bude pridaný do tejto firmy."
  - Meno*, Priezvisko*, Telefón*

- **Sekcia 2**: Rola a oprávnenia (karta)
  - Rola v tejto firme*: Dropdown (Upratovačka / Vedúca / Sekretárka / Účtovníčka / Vlastná)
  - Po výbere: expandovateľná sekcia "Oprávnenia" s checkboxmi
  - Checkboxy zoskupené podľa modulov:
    ```
    ☐ Klienti          [zobraziť] [vytvoriť] [upraviť] [zmazať]
    ☐ Objekty          [zobraziť] [vytvoriť] [upraviť] [zmazať]
    ☐ Cenové ponuky    [zobraziť] [vytvoriť] [upraviť] [odoslať]
    ☐ Zmluvy           [zobraziť] [vytvoriť] [upraviť] [ukončiť]
    ☐ Zamestnanci      [zobraziť] [vytvoriť] [upraviť] [priradiť]
    ...
    ```
  - Predvyplnené podľa zvolenej role. Editovateľné.

- **Sekcia 3**: Zamestnanecká zmluva (karta, voliteľná)
  - Segmented: "Vytvoriť novú" | "Vybrať existujúcu" | "Žiadna"
  - Ak nová: Typ (DPP/DPČ/TPP/Živnosť), Platnosť od-do, Sadzba, Úväzok, File upload
  - Ak existujúca: Dropdown so zmluvami daného User-a

**Footer**: "Uložiť a pozvať" (primary) | "Zrušiť"

---

### SCREEN: Zamestnanci / Detail

**URL**: `/employees/:id`
**Layout**: Štandardný.

**Page Header**:
- H1: Meno zamestnanca
- Badge: Rola v tejto firme
- Badge: Stav (Aktívny / Neaktívny)
- Button Group: "Upraviť" | "Upraviť oprávnenia" | "..." (Deaktivovať)

**Content — 2 stĺpce**:

**Ľavý (65%)**:
- **Karta: Priradené objekty** (tabuľka)
  - Objekt | Adresa | Deň/čas | Klient
- **Karta: Posledné zákazky** (tabuľka, posledných 10)
  - Dátum | Objekt | Stav | Check-in | Check-out
- **Karta: Neprítomnosti** (tabuľka)
  - Dátum od-do | Dôvod | Ovplyvnené zákazky (počet)

**Pravý (35%)**:
- **Karta: Kontaktné údaje** — e-mail, telefón
- **Karta: Zamestnanecká zmluva** — typ, platnosť, sadzba, link na dokument
- **Karta: Oprávnenia** — zoznam aktívnych oprávnení (kompaktné badges)
- **Karta: Ďalšie firmy** — "Aktívny aj v 2 ďalších firmách" (ak vlastník nemá prístup k tým firmám, zobrazí len počet)

---

### SCREEN: Rozvrh / Kalendár

**URL**: `/schedule`
**Layout**: Štandardný. Content area na plnú šírku (bez content max-width).

**Page Header**:
- H1: "Rozvrh"
- Button Group pohľad: "Týždeň" | "Mesiac" (segmented control)
- Button Primary: "+ Nová zákazka"

**Filtre (kompaktný riadok)**:
- Dropdown: Objekt — Všetky / konkrétny
- Dropdown: Upratovačka — Všetky / konkrétna
- Dropdown: Stav — Všetky / Plánovaná / Dokončená / Zrušená

**Kalendár**:
- **Týždenný pohľad**: 7 stĺpcov (Po-Ne), riadky sú hodiny (6:00-20:00). Zákazky ako farebné bloky:
  - Plánovaná: primary modrý
  - Dokončená: success zelený
  - Nepriradená: danger červený border, prúžkované pozadie
  - Zrušená: neutral šedý, prečiarknutý
- Každý blok zobrazuje: Objekt (skrátený názov), Upratovačka (iniciály), Čas
- Klik na blok → AP / Rozvrh / Detail zákazky
- Drag & drop na presunutie (len jednorazové zákazky)
- **Overlay neprítomností**: Dni neprítomnosti upratovačiek zvýraznené červeným prúžkom na vrchu dňa

- **Mesačný pohľad**: Grid 7×5, dni s bodkami/ikonami podľa počtu zákaziek

---

### SCREEN: Rozvrh / Pridať zákazku

**URL**: `/schedule/create`
**Layout**: Modal (560px) alebo side drawer.

**Formulár**:
- Objekt*: Dropdown
- Typ*: Segmented (Pravidelná | Jednorazová | Špeciálna)
- Dátum*: Date picker
- Čas od / Čas do: Time picker (vedľa seba)
- Upratovačka: Dropdown (filtrovanie na priradené k objektu, "Nepriradená" možnosť)
- Opakovanie: (len ak Pravidelná) Dropdown — Týždenne / Každé 2 týždne / Mesačne
- Poznámka: Textarea

**Footer**: "Uložiť" (primary) | "Zrušiť"

---

### SCREEN: Rozvrh / Detail zákazky

**URL**: `/schedule/:id`
**Layout**: Modal (640px) alebo samostatná stránka.

**Header**:
- H2: "Zákazka — [Objekt]"
- Badge: Stav
- Badge: Typ (Pravidelná/Jednorazová/Špeciálna)
- Badge (červený): "Nefakturovaná" (ak dokončená a bez faktúry)

**Content**:
- **Info grid** (2 stĺpce):
  - Objekt (link) | Klient (link)
  - Dátum | Čas
  - Upratovačka | Zmluva (link, ak existuje)
  - Check-in čas (Fáza 2) | Check-out čas (Fáza 2)
  - Faktúra (link, ak existuje)

- **Rozpis prác**: Checklist (read-only v AP, interaktívny v MAPP)

- **Fotodokumentácia** (Fáza 2): Grid obrázkov

**Akcie** (button group na footer):
- "Upraviť" (ak nie Dokončená)
- "Priradiť upratovačku" (dropdown)
- "Fakturovať" (ak Dokončená a nefakturovaná, primary)
- "Zrušiť zákazku" (danger, confirmácia)

---

### SCREEN: Faktúry / Zoznam

**URL**: `/invoices`
**Layout**: Štandardný.

**Page Header**:
- H1: "Faktúry"
- Button Primary: "+ Vytvoriť faktúru"

**Filtre**:
- Search: číslo, klient
- Dropdown: Stav — Všetky / Draft / Vystavená / Uhradená / Po splatnosti / Stornovaná
- Dropdown: Typ — Mesačná / Jednorazová / Špeciálna
- Dropdown: Klient
- Date range: Dátum od — do

**Tabuľka**:
- Stĺpce: Číslo (monospace) | Klient | Objekt | Typ (badge) | Dátum | Splatnosť | Suma (tučné) | Stav (badge) | Akcie (→ detail, PDF)

**Sumár riadok** (nad tabuľkou):
- Stat karty: Celkom vystavené | Uhradené | Neuhradené | Po splatnosti (červené)

---

### SCREEN: Faktúry / Vytvoriť

**URL**: `/invoices/create`
**Layout**: Štandardný. Formulár (max-width 900px).

**Page Header**:
- H1: "Nová faktúra"

**Formulár**:

- **Sekcia 1**: Základné údaje (karta)
  - Číslo faktúry: predvyplnené (monospace), editovateľné
  - Klient*: Dropdown
  - Objekt: Dropdown (filtrovaný podľa klienta)
  - Typ*: Segmented (Mesačná | Jednorazová | Špeciálna)
  - Obdobie od-do: Date range (viditeľné len ak Mesačná/Špeciálna)
  - Dátum vystavenia*: Date (default: dnes)
  - Dátum splatnosti*: Date (default: +14 dní)

- **Sekcia 2**: Nefakturované zákazky (karta, žlté pozadie)
  - H3: "Nefakturované zákazky pre tohto klienta"
  - Tabuľka s checkboxmi: ☐ Dátum | Objekt | Typ | Suma
  - "Zaškrtnite zákazky ktoré chcete zahrnúť do faktúry."
  - Info: ak žiadne nefakturované, sekcia sa nezobrazí

- **Sekcia 3**: Položky faktúry (karta, editovateľná tabuľka)
  - Popis | Množstvo | Jednotka | Cena/j. | Suma
  - Predvyplnené z rozpisu prác zmluvy (ak mesačná) alebo zo zákaziek
  - "+ Pridať položku" button

  - **Sumár**:
    - Suma bez DPH (ak platca DPH)
    - DPH (ak platca DPH)
    - **Celková suma** (veľké tučné číslo)

- **Sekcia 4**: Poznámka (karta)
  - Textarea

**Footer**: "Uložiť ako draft" | "Vystaviť" (primary) | "Zrušiť"

---

### SCREEN: Faktúry / Detail

**URL**: `/invoices/:id`
**Layout**: Štandardný.

**Page Header**:
- H1: "Faktúra FA-2026-0001" (monospace)
- Badge: Stav
- Badge: Typ
- Button Group:
  - Draft: "Upraviť" | "Vystaviť" (primary)
  - Vystavená: "Stiahnuť PDF" | "Odoslať e-mailom" (primary) | "..." (Storno, Duplikovať)

**Content — 2 stĺpce**:

**Ľavý (65%)**:
- **Karta: Náhľad faktúry** (vizualizácia ako faktúra vyzerá)
  - Hlavička: Logo firmy | Údaje firmy | Údaje klienta
  - Tabuľka položiek
  - Sumár: bez DPH, DPH, s DPH
  - QR kód na platbu (Pay by Square)
  - IBAN, VS, KS

**Pravý (35%)**:
- **Karta: Informácie**
  - Klient (link), Objekt (link), Zmluva (link)
  - Dátum vystavenia, Splatnosť
  - Suma
- **Karta: Prepojené zákazky** (zoznam)
- **Karta: Akcie**
  - Tlačidlá na stiahnutie, odoslanie, storno

---

### SCREEN: Šablóny dokumentov

**URL**: `/templates`
**Layout**: Štandardný.

**Page Header**:
- H1: "Šablóny dokumentov"
- Button Primary: "+ Nahrať šablónu"

**Content — Grid kariet** (nie tabuľka):
- Pre každú šablónu karta s:
  - Ikona podľa typu (zmluva, cenová ponuka, DPP...)
  - Názov šablóny
  - Typ (badge)
  - Veľkosť súboru
  - Dátum nahratia
  - Akcie: "Stiahnuť" (primary) | "Zmazať" (danger, confirmácia)

- Prázdny stav: "Zatiaľ nemáte žiadne šablóny. Nahrajte svoju prvú šablónu." + CTA

---

### SCREEN: Notifikácie / Nastavenia

**URL**: `/notifications/settings`
**Layout**: Štandardný. Formulár.

**Page Header**:
- H1: "Nastavenia notifikácií"

**Content — tabuľka s togglemi**:

| Typ notifikácie | DB (in-app) | E-mail | Push |
|---|---|---|---|
| Končiaca zmluva (30/14/7 dní) | Toggle | Toggle | — |
| Nová reklamácia | Toggle | Toggle | Toggle |
| Nahlásená neprítomnosť | Toggle | Toggle | Toggle |
| Nefakturovaná zákazka (>7 dní) | Toggle | — | — |
| Faktúra po splatnosti | Toggle | Toggle | — |
| Check-out neschválený | Toggle | — | Toggle |
| Cenová ponuka expiruje | Toggle | Toggle | — |

**Footer**: "Uložiť" (primary)

---

### SCREEN: Správa oprávnení

**URL**: `/permissions`
**Layout**: Štandardný.

**Page Header**:
- H1: "Správa oprávnení"

**Content — 2 panely**:

**Ľavý panel**: Zoznam rolí (karty)
- Defaultné role (zamknutá ikona): Vlastník, Vedúca, Upratovačka, Sekretárka, Účtovníčka
- Vlastné role: Libovoľné, s tlačidlom "+ Nová rola"
- Klik na rolu → zobrazí oprávnenia v pravom paneli

**Pravý panel**: Oprávnenia pre vybranú rolu
- H2: Názov role (editovateľný pre vlastné role)
- Checkbox skupiny podľa modulov (rovnaký layout ako pri vytváraní zamestnanca)
- "Uložiť zmeny" | "Obnoviť default" (len pre defaultné role)

---

### SCREEN: Nastavenia / Profil firmy a DPH

**URL**: `/settings/company`
**Layout**: Štandardný. Formulár (max-width 640px).

**Page Header**:
- H1: "Nastavenia firmy"

**Formulár**:

- **Sekcia 1**: Základné údaje (karta)
  - Názov firmy, IČO, DIČ, Adresa, Logo (file upload s preview)

- **Sekcia 2**: DPH nastavenia (karta, zvýraznená rámčekom)
  - **Platca DPH**: Veľký toggle switch s labelom
  - Ak áno: IČ DPH (input), Sadzba DPH % (number, default 23)
  - Info box: "Ak nie ste platca DPH, systém nebude zobrazovať DPH na cenových ponukách, zmluvách ani faktúrach."

- **Sekcia 3**: Fakturácia (karta)
  - Formát čísla faktúry: Input (s placeholder "FA-{YYYY}-{XXXX}")
  - IBAN: Input
  - Kontaktný e-mail, telefón

**Footer**: "Uložiť" (primary)

---

### SCREEN: Nastavenia / Predplatné

**URL**: `/settings/subscription`
**Layout**: Štandardný.

**Page Header**:
- H1: "Predplatné"

**Content**:
- **Aktuálny plán** (zvýraznená karta):
  - Názov plánu: "Štart"
  - Cena: 19 €/mes
  - Progress bary: Firmy 1/1 | Používatelia 2/3 | Klienti 12/∞

- **Porovnanie plánov** (4 karty vedľa seba):

| | Free | Štart | Business | Premium |
|---|---|---|---|---|
| Cena | 0 € | 19 € | 39 € | 69 € |
| Firmy | 1 | 1 | 3 | ∞ |
| Používatelia | 1 | 3 | 10 | ∞ |
| Klienti | 5 | ∞ | ∞ | ∞ |

  - Aktívny plán zvýraznený primary farbou
  - "Upgradovať" button na vyšších plánoch

---

## Obrazovky — Landing page

---

### SCREEN: Landing page

**URL**: `/` (hlavná doména)
**Layout**: Bez sidebaru. Full-width. Navigácia hore (sticky).

**Navigácia** (topbar):
- Logo | Funkcie | Cenník | Kontakt | Jazyk (SK/EN/UA) | Button "Vyskúšať zadarmo"

**Hero sekcia** (full-width, veľký padding):
- Split layout alebo centrovený
- H1 (veľký, 48px): "Celá vaša upratovacia firma pod kontrolou"
- Podnadpis (20px, šedý): "Klienti, zmluvy, rozvrh a faktúry na jednom mieste. Vytvorené pre slovenské upratovacie firmy."
- Button Primary (veľký): "Vyskúšať zadarmo"
- Button Secondary: "Pozrieť demo"
- Hero obrázok: Dashboard screenshot v mockup ráme (laptop/tablet)

**Funkcie sekcia** (3 stĺpce karty):
1. Ikona + "Papierovačky" — "Cenové ponuky, zmluvy, šablóny dokumentov. Všetko na jednom mieste."
2. Ikona + "Zamestnanci" — "Rozvrh, priradenie, mobilná app pre upratovačky."
3. Ikona + "Fakturácia" — "Mesačné a jednorazové faktúry. Slovenská legislatíva, QR platby."

**Ďalšie funkcie** (alternujúce sekcie obrázok+text):
- Multi-firma správa
- Mobilná aplikácia
- Oprávnenia a role
- Zákaznícky portál

**Cenník sekcia**:
- 4 pricing karty (Free/Štart/Business/Premium)
- "Štart" zvýraznený ako "Najobľúbenejší"
- FAQ accordion pod tým

**CTA sekcia** (pred footer):
- H2: "Začnite ešte dnes zadarmo"
- Registračný formulár inline: E-mail + Button "Začať"

**Footer**:
- Logo | Kontakt | Právne informácie | Sociálne siete | Jazyk

---

## Obrazovky — Fáza 2 (mobilná app)

Mobilné obrazovky sú optimalizované pre 375px viewport (iPhone). Navigácia cez bottom tab bar.

---

### SCREEN: MAPP / Dashboard

**Layout**: Mobilný. Bottom tab bar: Domov | Rozvrh | Fotky | Profil

**Content**:
- Greeting: "Ahoj, [Meno]" (veľký text)
- **Dnešné zákazky** (zoznam kariet):
  - Každá karta:
    - Farebný prúžok na ľavom okraji (farba firmy)
    - Čas (veľký, tučný)
    - Objekt (názov)
    - Adresa (malý šedý text)
    - Stav badge
  - Klik → MAPP / Detail zákazky
- **Banner neprítomnosť** (ak aktívna): žlté pozadie, "Neprítomná do [dátum]"
- **Sekcia "Zajtra"** (collapsed, klik na rozbalenie)

---

### SCREEN: MAPP / Detail zákazky

**Layout**: Mobilný. Full screen detail.

**Header**: Back arrow | "Zákazka" | Stav badge

**Content (scrollovateľný)**:
- **Info karta** (biele pozadie):
  - Objekt (veľký text) | Adresa (odkaz na mapu)
  - Čas od-do
  - Firma (farebný tag)
- **Prístupové info** (žltá karta):
  - Kódy, pokyny, kľúče
- **Rozpis prác** (checklist):
  - Checkboxy s úlohami, odškrtávateľné
- **Kontakt** (ikony):
  - Telefón (tap = volanie) | E-mail

**Sticky footer buttons**:
- Ak Plánovaná: Veľký zelený button "CHECK-IN"
- Ak Prebiehajúca: Button "Nahrať fotky" + Veľký button "CHECK-OUT"

---

### SCREEN: MAPP / Nahlásenie neprítomnosti

**Layout**: Mobilný. Modal/sheet.

**Formulár**:
- Dátum od: Date picker
- Dátum do: Date picker
- Dôvod: Výber (Choroba/Dovolenka/Osobné/Iné)
- Poznámka: Textarea (voliteľná)
- Button: "Odoslať" (full width, primary)

---

### SCREEN: MAPP / Reklamácia prijatá

**Layout**: Mobilný. Push notifikácia → otvorí túto obrazovku.

**Content**:
- **Header**: Červený banner "Reklamácia"
- **Info**: Objekt, dátum zákazky
- **Popis problému**: Text od zákazníka
- **Fotky**: Horizontálny scrollovateľný galéria zákazníkových fotiek

**Akcie** (2 veľké buttony):
- "Neopodstatnená — nahlásiť vedúcej" (secondary)
- "Navrhnúť náhradný termín" (primary)

---

## Obrazovky — Fáza 2 (zákaznícky portál)

---

### SCREEN: ZP / Dashboard

**URL**: `/portal/dashboard`
**Layout**: Jednoduchší layout — bez sidebaru, top navigácia s tabmi.

**Top navigácia**: Dashboard | Rozvrh | Faktúry | Reklamácie | Nastavenia

**Content**:
- **Karta: Najbližšie upratovanie**
  - Veľký text: "Zajtra, 14:00" | Objekt | Adresa
- **Karta: Posledné upratovanie**
  - Dátum | Mini galéria fotiek po upratovaní
- **Karta: Neuhradené faktúry**
  - Počet | Celková suma | Button "Zobraziť"
- **Karta: Otvorené reklamácie** (ak existujú)

---

### SCREEN: ZP / Reklamácie / Vytvoriť

**URL**: `/portal/complaints/create`
**Layout**: Formulár.

**Formulár**:
- Objekt*: Dropdown (ak má viacero)
- Zákazka*: Dropdown (posledné zákazky na objekte)
- Popis problému*: Textarea
- Fotky: File upload (drag & drop, max 10, max 5MB/fotka)
  - Preview grid nahratých fotiek s X na odstránenie

**Footer**: "Odoslať reklamáciu" (primary) | "Zrušiť"

---

## Responzívne správanie

### Desktop (>1200px)
- Sidebar vždy viditeľný
- 2-stĺpcové layouty na detailoch
- Tabuľky na plnú šírku

### Tablet (768-1200px)
- Sidebar collapsible (hamburger menu)
- 2-stĺpcové layouty sa skladajú do 1 stĺpca
- Tabuľky so scroll horizontálne

### Mobile (<768px)
- Sidebar ako overlay drawer
- Single column layout
- Tabuľky sa menia na karty (stacked layout)
- Bottom sheet modály namiesto centrovených dialógov

---

*Dokument vytvorený: 3. mája 2026*
*Verzia: 1.0*
*Zdrojový dokument: cleanmaster-technicka-specifikacia-v1.md*