<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\DocumentTypeEnum;
use App\Enums\EmploymentContractTypeEnum;
use App\Models\DocumentTemplate;
use Illuminate\Database\Seeder;

final class DocumentTemplateSeeder extends Seeder
{
    private const string LABOUR_CODE = 'zákona č. 311/2001 Z. z. Zákonník práce v znení neskorších predpisov (ďalej len „Zákonník práce“)';

    private const string COMMERCIAL_CODE = 'zákona č. 513/1991 Zb. Obchodný zákonník v znení neskorších predpisov (ďalej len „Obchodný zákonník“)';

    private const string GDPR = 'nariadenia Európskeho parlamentu a Rady (EÚ) 2016/679 (všeobecné nariadenie o ochrane údajov, ďalej len „GDPR“)';

    public function run(): void
    {
        foreach ($this->definitions() as [$name, $type, $employmentType, $body]) {
            DocumentTemplate::query()->withoutGlobalScopes()->firstOrCreate(
                ['tenant_id' => null, 'type' => $type->value, 'employment_type' => $employmentType?->value, 'name' => $name],
                ['body' => $body, 'show_header' => true, 'show_footer' => true, 'is_active' => true],
            );
        }
    }

    /** @return list<array{string, DocumentTypeEnum, EmploymentContractTypeEnum|null, string}> */
    public function definitions(): array
    {
        return [
            ['Zmluva o poskytovaní upratovacích služieb', DocumentTypeEnum::ServiceAgreement, null, $this->serviceAgreement()],
            ['Pracovná zmluva (TPP)', DocumentTypeEnum::Employment, EmploymentContractTypeEnum::Tpp, $this->employmentContract()],
            ['Dohoda o vykonaní práce (DPP)', DocumentTypeEnum::Employment, EmploymentContractTypeEnum::Dpp, $this->workPerformanceAgreement()],
            ['Dohoda o pracovnej činnosti (DPČ)', DocumentTypeEnum::Employment, EmploymentContractTypeEnum::Dpc, $this->workActivityAgreement()],
            ['Zmluva so živnostníkom', DocumentTypeEnum::Employment, EmploymentContractTypeEnum::SelfEmployed, $this->selfEmployedContract()],
            ['Dohoda o mlčanlivosti', DocumentTypeEnum::Nda, null, $this->confidentialityAgreement()],
            ['Súhlas so spracovaním osobných údajov', DocumentTypeEnum::Gdpr, null, $this->dataProcessingConsent()],
            ['Cenová ponuka', DocumentTypeEnum::Quote, null, $this->quote()],
        ];
    }

    private function serviceAgreement(): string
    {
        return $this->title('{{contract.title}}', 'uzatvorená podľa § 269 ods. 2 '.self::COMMERCIAL_CODE)
            .'<p><strong>Poskytovateľ:</strong> {{tenant.name}}, sídlo: {{tenant.address}}, IČO: {{tenant.ico}}, zastúpený: {{tenant.representative}} (ďalej len „poskytovateľ“)</p>'
            .'<p>{{tenant.registration_info}}</p>'
            .'<p>{{if.vat_payer}}IČ DPH poskytovateľa: {{tenant.vat_number}}</p>'
            .'<p>a</p>'
            .'<p><strong>Objednávateľ:</strong> {{client.name}} (ďalej len „objednávateľ“)</p>'
            .'<p>Sídlo alebo adresa: {{client.address}}</p>'
            .'<p>IČO: {{client.ico}}</p>'
            .'<p>DIČ: {{client.dic}}</p>'
            .$this->article(1, 'Predmet zmluvy', [
                'Poskytovateľ sa zaväzuje poskytovať objednávateľovi upratovacie a čistiace služby v tomto rozsahu a v týchto termínoch: {{contract.subject_scope}}.',
                '{{if.has_object}}Služby sa poskytujú v objekte {{object.name}}, {{object.address}}.',
                'Objednávateľ sa zaväzuje za riadne poskytnuté služby zaplatiť cenu podľa článku 4.',
                'Služby sa poskytujú od {{contract.valid_from}}.',
            ])
            .$this->article(2, 'Poskytovanie služieb', [
                'Poskytovateľ poskytuje služby s odbornou starostlivosťou, v dohodnutom rozsahu a v súlade s právnymi predpismi.',
                'Poskytovateľ môže na plnenie použiť svojich zamestnancov alebo iné osoby. Za ich plnenie zodpovedá, akoby plnil sám.',
                'Poskytovateľ zabezpečuje pomôcky a čistiace prostriedky potrebné na poskytovanie služieb, ak sa zmluvné strany písomne nedohodnú inak.',
                'Rozšírenie alebo zmenu rozsahu služieb dohodnú zmluvné strany písomným dodatkom vrátane jej vplyvu na cenu.',
            ])
            .$this->article(3, 'Prístup do priestorov a bezpečnosť', [
                'Objednávateľ umožní poskytovateľovi prístup do priestorov v dohodnutých termínoch a odber vody a elektrickej energie v rozsahu potrebnom na poskytovanie služieb.',
                'Kľúče, prístupové karty a kódy odovzdáva objednávateľ poskytovateľovi na základe písomného záznamu. Poskytovateľ ich používa len na plnenie tejto zmluvy, chráni ich pred stratou a zneužitím, ich stratu oznámi objednávateľovi bez zbytočného odkladu a vráti ich najneskôr pri skončení zmluvy.',
                'Objednávateľ vopred upozorní poskytovateľa na známe nebezpečenstvá v priestoroch a na pravidlá, ktoré sa v nich uplatňujú.',
                'Zabezpečenie a vybavenie pracoviska na bezpečný výkon práce a vzájomné informovanie o možných ohrozeniach sa spravuje § 18 zákona č. 124/2006 Z. z. o bezpečnosti a ochrane zdravia pri práci v znení neskorších predpisov.',
            ])
            .$this->article(4, 'Cena', [
                'Cena za služby a spôsob jej určenia: {{contract.price_terms}}.',
                '{{if.vat_payer}}Poskytovateľ je platiteľom dane z pridanej hodnoty. K cene bez dane sa pripočíta daň v sadzbe podľa právnych predpisov účinných ku dňu dodania služby.',
                'Cenu možno zmeniť len písomným dodatkom. Zmluvná strana môže zmenu ceny navrhnúť písomne, najmä pri zmene rozsahu služieb alebo nákladov na ich poskytovanie. Ak sa zmluvné strany nedohodnú, platí doterajšia cena.',
            ])
            .$this->article(5, 'Fakturácia a platby', [
                'Fakturácia a splatnosť: {{contract.payment_terms}}.',
                'Ak je objednávateľ v omeškaní so zaplatením ceny, má poskytovateľ právo na úroky z omeškania podľa § 369 Obchodného zákonníka.',
            ])
            .$this->article(6, 'Reklamácie a zodpovednosť', [
                'Objednávateľ oznámi nedostatky služieb poskytovateľovi bez zbytočného odkladu po ich zistení tak, aby ich bolo možné overiť. Poskytovateľ opodstatnené nedostatky odstráni na vlastné náklady v primeranej lehote.',
                'Zodpovednosť za škodu sa spravuje § 373 a nasledujúcimi Obchodného zákonníka. Škodu v priestoroch objednávateľa zmluvné strany bez zbytočného odkladu zaznamenajú a vzájomne si oznámia.',
                'Poskytovateľ nezodpovedá za nedostatky a škodu spôsobenú tým, že objednávateľ neumožnil prístup do priestorov alebo poskytol nesprávne informácie o priestoroch.',
            ])
            .$this->article(7, 'Mlčanlivosť a osobné údaje', [
                'Zmluvné strany zachovávajú mlčanlivosť o dôverných skutočnostiach, o ktorých sa dozvedeli pri plnení zmluvy, najmä o bezpečnostných opatreniach a o skutočnostiach zistených v priestoroch objednávateľa. Táto povinnosť sa nevzťahuje na plnenie povinností uložených zákonom alebo orgánom verejnej moci.',
                'Predmetom zmluvy nie je spracúvanie osobných údajov v mene objednávateľa. Poskytovateľ a jeho pracovníci nie sú oprávnení oboznamovať sa s dokumentmi a údajmi objednávateľa nad rámec nevyhnutný na poskytovanie služieb.',
                'Ak má poskytovateľ spracúvať osobné údaje v mene objednávateľa, zmluvné strany pred začatím takého spracúvania uzatvoria zmluvu podľa čl. 28 ods. 3 '.self::GDPR.'.',
            ])
            .$this->article(8, 'Trvanie a skončenie zmluvy', [
                '{{if.fixed_term}}Zmluva sa uzatvára na dobu určitú do {{contract.end_date}}.',
                '{{if.indefinite_term}}Zmluva sa uzatvára na dobu neurčitú.',
                'Zmluvu možno skončiť písomnou dohodou zmluvných strán alebo písomnou výpoveďou aj bez uvedenia dôvodu. Výpovedná lehota: {{contract.notice_period}}.',
                'Zmluvná strana môže od zmluvy odstúpiť pri jej podstatnom porušení druhou zmluvnou stranou podľa § 344 a § 345 Obchodného zákonníka. Odstúpenie musí byť písomné a účinky má dňom doručenia druhej zmluvnej strane.',
                'Pri skončení zmluvy zmluvné strany vyúčtujú poskytnuté služby a poskytovateľ vráti kľúče a prístupové prostriedky.',
            ])
            .$this->article(9, 'Záverečné ustanovenia', [
                'Zmluva sa spravuje Obchodným zákonníkom. Ak záväzkový vzťah nespadá pod § 261 Obchodného zákonníka, zmluvné strany sa podľa § 262 ods. 1 Obchodného zákonníka dohodli, že sa spravuje týmto zákonom.',
                'Zmluvu možno meniť len písomnými dodatkami podpísanými oboma zmluvnými stranami.',
                'Zmluva nadobúda platnosť dňom podpisu oboma zmluvnými stranami. Každá zmluvná strana dostane jedno vyhotovenie.',
            ])
            .$this->signing('Za poskytovateľa: {{tenant.representative}}', 'Za objednávateľa: {{client.name}}');
    }

    private function employmentContract(): string
    {
        return $this->title('{{contract.title}}', 'uzatvorená podľa § 42 a nasledujúcich '.self::LABOUR_CODE)
            .$this->employmentParties()
            .$this->article(1, 'Základné pracovné podmienky', [
                'Druh práce a jeho stručná charakteristika: {{employment.work_description}}.',
                'Miesto výkonu práce: {{employment.work_place}}.',
                'Deň nástupu do práce: {{contract.valid_from}}.',
                'Mzdové podmienky: {{employment.reward_terms}}.',
            ])
            .'<p>Dohodnuté údaje v prehľade:</p><p>{{block.employment_terms}}</p>'
            .$this->article(2, 'Trvanie pracovného pomeru a skúšobná doba', [
                'Pracovný pomer vzniká dňom nástupu do práce.',
                '{{if.indefinite_term}}Pracovný pomer sa uzatvára na neurčitý čas.',
                '{{if.fixed_term}}Pracovný pomer sa uzatvára na určitú dobu do {{contract.end_date}}.',
                '{{if.probation}}Skúšobná doba sa dohodla do {{employment.probation_end_date}}. Skúšobnú dobu nemožno predlžovať. Ak zamestnanec počas nej neodpracoval pre prekážku v práci na jeho strane celú pracovnú zmenu, skúšobná doba sa predlžuje o jeden deň (§ 45 Zákonníka práce).',
                '{{if.probation}}V skúšobnej dobe môže zamestnávateľ aj zamestnanec skončiť pracovný pomer písomne z akéhokoľvek dôvodu alebo bez uvedenia dôvodu za podmienok podľa § 72 Zákonníka práce.',
            ])
            .$this->article(3, 'Pracovný čas a odpočinok', [
                'Ak nie je v prehľade v článku 1 uvedený iný rozsah, má zamestnanec ustanovený týždenný pracovný čas podľa § 85 ods. 5 Zákonníka práce. Pri kratšom pracovnom čase patrí zamestnancovi mzda zodpovedajúca dohodnutému kratšiemu pracovnému času (§ 49 Zákonníka práce).',
                'Začiatok a koniec pracovného času a rozvrh pracovných zmien určuje zamestnávateľ podľa § 90 Zákonníka práce. Rozvrhnutie pracovného času oznamuje zamestnancovi najmenej týždeň vopred a s platnosťou najmenej na týždeň.',
                'Prestávka v práci sa poskytuje podľa § 91 Zákonníka práce, nepretržitý denný odpočinok podľa § 92 a nepretržitý odpočinok v týždni podľa § 93 Zákonníka práce.',
                'Práca nadčas sa spravuje § 97 Zákonníka práce a mzda za prácu nadčas § 121 Zákonníka práce. Zamestnancovi s kratším pracovným časom nemožno prácu nadčas nariadiť.',
            ])
            .$this->article(4, 'Mzda a jej výplata', [
                'Mzda nesmie byť nižšia ako minimálna mzda ani ako minimálny mzdový nárok podľa § 119 ods. 1 a § 120 Zákonníka práce, ak sa na zamestnávateľa vzťahuje.',
                'Mzda je splatná pozadu za mesačné obdobie, najneskôr do konca nasledujúceho kalendárneho mesiaca (§ 129 ods. 1 Zákonníka práce). Výplatný termín oznámi zamestnávateľ zamestnancovi písomne podľa § 47a ods. 1 písm. d) Zákonníka práce.',
                'Zmluvné strany sa dohodli, že mzda sa vypláca bezhotovostne na účet, ktorý zamestnanec písomne oznámi zamestnávateľovi.',
                'Pri vyúčtovaní mzdy vydá zamestnávateľ zamestnancovi doklad podľa § 130 ods. 5 Zákonníka práce.',
            ])
            .$this->article(5, 'Dovolenka', [
                'Základná výmera dovolenky je najmenej štyri týždne. Zamestnanec, ktorý do konca príslušného kalendárneho roka dovŕši najmenej 33 rokov veku, a zamestnanec, ktorý sa trvale stará o dieťa, má dovolenku najmenej päť týždňov (§ 103 Zákonníka práce).',
                'Nárok na dovolenku a jej čerpanie sa spravuje § 100 a nasledujúcimi Zákonníka práce.',
            ])
            .$this->article(6, 'Skončenie pracovného pomeru', [
                'Pracovný pomer možno skončiť dohodou, výpoveďou, okamžitým skončením alebo skončením v skúšobnej dobe. Pracovný pomer na určitú dobu sa skončí uplynutím dohodnutej doby (§ 59 Zákonníka práce).',
                'Výpovedná doba je najmenej jeden mesiac. Ak pracovný pomer ku dňu doručenia výpovede trval najmenej jeden rok, je najmenej dva mesiace. Pri výpovedi z dôvodov podľa § 63 ods. 1 písm. a) alebo b) Zákonníka práce alebo pre dlhodobú stratu spôsobilosti vykonávať doterajšiu prácu podľa lekárskeho posudku je najmenej tri mesiace, ak pracovný pomer trval najmenej päť rokov (§ 62 Zákonníka práce).',
                'Výpovedná doba začína plynúť od prvého dňa kalendárneho mesiaca nasledujúceho po doručení výpovede a skončí sa uplynutím posledného dňa príslušného kalendárneho mesiaca.',
                'Neplatnosť skončenia pracovného pomeru možno uplatniť na súde najneskôr v lehote dvoch mesiacov odo dňa, keď sa mal pracovný pomer skončiť (§ 77 Zákonníka práce).',
            ])
            .$this->article(7, 'Ďalšie práva a povinnosti', [
                'Zamestnávateľ je povinný prideľovať zamestnancovi prácu podľa tejto zmluvy, platiť mu mzdu a utvárať podmienky na plnenie pracovných úloh. Zamestnanec je povinný podľa pokynov zamestnávateľa vykonávať prácu osobne v určenom pracovnom čase a dodržiavať pracovnú disciplínu (§ 47 ods. 1 Zákonníka práce).',
                'Pri nástupe do zamestnania oboznámi zamestnávateľ zamestnanca s pracovným poriadkom, s kolektívnou zmluvou, ak sa uplatňuje, s právnymi predpismi vzťahujúcimi sa na vykonávanú prácu, s predpismi na zaistenie bezpečnosti a ochrany zdravia pri práci, s ustanoveniami o zásade rovnakého zaobchádzania a s vnútorným predpisom upravujúcim oznamovanie kriminality alebo inej protispoločenskej činnosti (§ 47 ods. 2 Zákonníka práce).',
                'Zamestnanec dodržiava predpisy na zaistenie bezpečnosti a ochrany zdravia pri práci podľa zákona č. 124/2006 Z. z. v znení neskorších predpisov a používa ochranné pracovné prostriedky, ktoré mu zamestnávateľ poskytne.',
                'Zamestnanec zachováva mlčanlivosť o skutočnostiach, o ktorých sa dozvedel pri výkone zamestnania a ktoré v záujme zamestnávateľa nemožno oznamovať iným osobám, najmä o skutočnostiach zistených v objektoch klientov a o kľúčoch a prístupových kódoch. Táto povinnosť sa nevzťahuje na oznámenie kriminality alebo inej protispoločenskej činnosti ani na pracovné a mzdové podmienky zamestnanca (§ 81 písm. f) a § 44 ods. 2 písm. a) Zákonníka práce).',
                'Zamestnanec písomne oznamuje zamestnávateľovi bez zbytočného odkladu zmeny, ktoré sa týkajú pracovného pomeru a súvisia s jeho osobou (§ 81 písm. g) Zákonníka práce).',
                'Zamestnávateľ spracúva osobné údaje zamestnanca na účely pracovnoprávneho vzťahu. Informácie podľa čl. 13 '.self::GDPR.' poskytuje zamestnancovi osobitne.',
            ])
            .$this->article(8, 'Záverečné ustanovenia', [
                'Údaje podľa § 47a ods. 1 Zákonníka práce, ktoré táto zmluva neobsahuje, poskytne zamestnávateľ zamestnancovi písomne v lehotách podľa § 47a ods. 2 Zákonníka práce. O ich zmene informuje zamestnanca podľa § 54c Zákonníka práce.',
                'Dohodnutý obsah tejto zmluvy možno zmeniť len dohodou zmluvných strán. Zmenu vyhotoví zamestnávateľ písomne a jedno vyhotovenie vydá zamestnancovi (§ 54 Zákonníka práce).',
                'Práva a povinnosti neupravené v tejto zmluve sa spravujú Zákonníkom práce a ostatnými pracovnoprávnymi predpismi.',
                'Zmluva nadobúda platnosť a účinnosť dňom podpisu oboma zmluvnými stranami. Jedno vyhotovenie dostane zamestnanec.',
            ])
            .$this->signing('Za zamestnávateľa: {{tenant.representative}}', 'Zamestnanec: {{employee.name}}');
    }

    private function workPerformanceAgreement(): string
    {
        return $this->title('{{contract.title}}', 'uzatvorená podľa § 223 a § 226 '.self::LABOUR_CODE)
            .$this->employmentParties()
            .$this->article(1, 'Pracovná úloha', [
                'Zamestnanec sa zaväzuje vykonať túto pracovnú úlohu: {{employment.work_description}}.',
                'Rozsah práce: {{employment.work_scope}}. Rozsah práce nesmie presiahnuť 350 hodín v kalendárnom roku. Započítava sa doň aj práca pre zamestnávateľa na základe inej dohody o vykonaní práce (§ 226 ods. 1 Zákonníka práce).',
                'Miesto výkonu práce: {{employment.work_place}}.',
            ])
            .'<p>Dohodnuté údaje v prehľade:</p><p>{{block.employment_terms}}</p>'
            .$this->agreementPeriodArticle(2, 'Doba vykonania pracovnej úlohy', 'Pracovná úloha sa vykoná', 'Dohodu možno uzatvoriť najviac na 12 mesiacov (§ 226 ods. 1 Zákonníka práce).')
            .$this->article(3, 'Odmena', [
                'Dohodnutá odmena za vykonanie pracovnej úlohy: {{employment.reward_terms}}.',
                'Odmena je splatná po dokončení a odovzdaní práce. Ak sa to dohodlo v tejto dohode, časť odmeny je splatná už po vykonaní určitej časti pracovnej úlohy (§ 226 ods. 4 Zákonníka práce).',
                'Zamestnávateľ môže odmenu po prerokovaní so zamestnancom primerane znížiť, ak vykonaná práca nezodpovedá dohodnutým podmienkam.',
                'Za každú hodinu práce vo sviatok patrí zamestnancovi dohodnutá odmena zvýšená najmenej o sumu minimálnej mzdy za hodinu (§ 223 ods. 2 Zákonníka práce).',
            ])
            .$this->agreementWorkingTimeArticle(4, 'viesť evidenciu vykonanej práce tak, aby v jednotlivých dňoch bola zaznamenaná dĺžka časového úseku, v ktorom sa práca vykonávala')
            .$this->agreementDutiesArticle(5)
            .$this->article(6, 'Skončenie dohody', [
                'Dohoda sa skončí vykonaním pracovnej úlohy, uplynutím doby podľa článku 2 alebo písomnou dohodou zmluvných strán.',
                'Ak sa pracovná úloha nevykoná v dohodnutej dobe, môže zamestnávateľ od dohody odstúpiť. Zamestnanec môže od dohody odstúpiť, ak nemôže pracovnú úlohu vykonať preto, že mu zamestnávateľ neutvoril dohodnuté pracovné podmienky. Zamestnávateľ je v takom prípade povinný nahradiť zamestnancovi škodu, ktorá mu tým vznikla (§ 226 ods. 3 Zákonníka práce).',
            ])
            .$this->agreementClosingArticle(7, 'Dohoda sa uzatvára písomne, inak je neplatná (§ 226 ods. 2 Zákonníka práce).')
            .$this->signing('Za zamestnávateľa: {{tenant.representative}}', 'Zamestnanec: {{employee.name}}');
    }

    private function workActivityAgreement(): string
    {
        return $this->title('{{contract.title}}', 'uzatvorená podľa § 223 a § 228a '.self::LABOUR_CODE)
            .$this->employmentParties()
            .$this->article(1, 'Dohodnutá práca', [
                'Zamestnanec sa zaväzuje vykonávať túto prácu: {{employment.work_description}}.',
                'Dohodnutý rozsah pracovného času: {{employment.work_scope}}. Rozsah nesmie presiahnuť 10 hodín týždenne (§ 228a ods. 1 písm. a) Zákonníka práce).',
                'Miesto výkonu práce: {{employment.work_place}}.',
            ])
            .'<p>Dohodnuté údaje v prehľade:</p><p>{{block.employment_terms}}</p>'
            .$this->agreementPeriodArticle(2, 'Doba, na ktorú sa dohoda uzatvára', 'Dohoda sa uzatvára', 'Dohodu možno uzatvoriť najviac na 12 mesiacov (§ 228a ods. 4 Zákonníka práce).')
            .$this->article(3, 'Odmena', [
                'Dohodnutá odmena za vykonanú prácu: {{employment.reward_terms}}.',
                'Odmena je splatná a musí byť vyplatená najneskôr do konca kalendárneho mesiaca, ktorý nasleduje po mesiaci, v ktorom sa práca vykonala (§ 228a ods. 5 Zákonníka práce).',
                'Za každú hodinu práce vo sviatok patrí zamestnancovi dohodnutá odmena zvýšená najmenej o sumu minimálnej mzdy za hodinu (§ 223 ods. 2 Zákonníka práce).',
            ])
            .$this->agreementWorkingTimeArticle(4, 'viesť evidenciu pracovného času tak, aby bol zaznamenaný začiatok a koniec časového úseku, v ktorom zamestnanec vykonával prácu')
            .$this->agreementDutiesArticle(5)
            .$this->article(6, 'Skončenie dohody', [
                'Dohoda sa skončí uplynutím doby podľa článku 2.',
                'Dohodu možno skončiť dohodou zmluvných strán k dohodnutému dňu alebo jednostranne výpoveďou bez uvedenia dôvodu s 15-dennou výpovednou dobou, ktorá sa začína dňom, v ktorom sa písomná výpoveď doručila (§ 228a ods. 4 Zákonníka práce).',
                'Okamžite možno dohodu skončiť len v prípadoch, v ktorých možno okamžite skončiť pracovný pomer.',
            ])
            .$this->agreementClosingArticle(7, 'Dohoda sa uzatvára písomne, inak je neplatná (§ 228a ods. 3 Zákonníka práce).')
            .$this->signing('Za zamestnávateľa: {{tenant.representative}}', 'Zamestnanec: {{employee.name}}');
    }

    private function selfEmployedContract(): string
    {
        return $this->title('{{contract.title}}', 'uzatvorená podľa § 269 ods. 2 '.self::COMMERCIAL_CODE)
            .'<p><strong>Objednávateľ:</strong> {{tenant.name}}, sídlo: {{tenant.address}}, IČO: {{tenant.ico}}, zastúpený: {{tenant.representative}} (ďalej len „objednávateľ“)</p>'
            .'<p>{{tenant.registration_info}}</p>'
            .'<p>a</p>'
            .'<p><strong>Poskytovateľ:</strong> {{employee.name}}, miesto podnikania: {{employee.address}} (ďalej len „poskytovateľ“)</p>'
            .'<p>IČO: {{employee.ico}}</p>'
            .$this->article(1, 'Predmet zmluvy', [
                'Poskytovateľ sa zaväzuje vykonať pre objednávateľa tieto služby: {{contract.subject_scope}}. Objednávateľ sa zaväzuje zaplatiť za ich riadne vykonanie cenu podľa článku 3.',
                'Miesto plnenia: {{employment.work_place}}.',
                'Poskytovateľ vyhlasuje, že je podnikateľom a má živnostenské oprávnenie zodpovedajúce predmetu tejto zmluvy.',
                'Plnenie sa začína {{contract.valid_from}}.',
            ])
            .$this->article(2, 'Samostatnosť poskytovateľa', [
                'Poskytovateľ plní zmluvu samostatne, vo vlastnom mene, na vlastnú zodpovednosť a na vlastné náklady.',
                'Objednávateľ určuje požadovaný výsledok a podmienky vstupu do objektu. Spôsob, postup a organizáciu plnenia určuje poskytovateľ a pri ich určení nie je viazaný pokynmi objednávateľa.',
                'Termíny plnenia dohodnú zmluvné strany podľa povahy služby. Zmluva neurčuje poskytovateľovi pracovný čas ani pracovné zmeny.',
                'Poskytovateľ nie je povinný plniť osobne a môže plnením poveriť inú osobu. Za jej plnenie zodpovedá, akoby plnil sám. Túto osobu oznámi objednávateľovi vopred, ak to vyžadujú podmienky vstupu do objektu.',
                'Poskytovateľ používa vlastné pomôcky a čistiace prostriedky, ak sa zmluvné strany písomne nedohodnú inak.',
                'Poskytovateľ môže poskytovať služby aj iným osobám. Zmluva nezakladá výlučnosť v prospech žiadnej zmluvnej strany.',
                'Zmluva nezakladá pracovnoprávny vzťah. Závislú prácu nemožno vykonávať v obchodnoprávnom vzťahu (§ 1 ods. 3 Zákonníka práce). Zmluvné strany budú postupovať tak, aby skutočné plnenie zodpovedalo tejto zmluve.',
                'Daňové povinnosti a povinnosti k sociálnemu a zdravotnému poisteniu z tohto príjmu si plní poskytovateľ sám.',
            ])
            .$this->article(3, 'Cena a platobné podmienky', [
                'Cena za služby a spôsob jej určenia: {{contract.price_terms}}.',
                'Fakturácia a splatnosť: {{contract.payment_terms}}.',
                'Ak je poskytovateľ platiteľom dane z pridanej hodnoty, pripočíta k cene daň v sadzbe podľa právnych predpisov účinných ku dňu dodania služby.',
                'Cenu možno zmeniť len písomným dodatkom.',
                'Pri omeškaní so zaplatením ceny má poskytovateľ právo na úroky z omeškania podľa § 369 Obchodného zákonníka.',
            ])
            .$this->article(4, 'Kvalita plnenia a zodpovednosť', [
                'Poskytovateľ vykonáva služby s odbornou starostlivosťou a v dohodnutom rozsahu.',
                'Objednávateľ oznámi nedostatky bez zbytočného odkladu po ich zistení. Poskytovateľ opodstatnené nedostatky odstráni na vlastné náklady v primeranej lehote.',
                'Zodpovednosť za škodu sa spravuje § 373 a nasledujúcimi Obchodného zákonníka.',
            ])
            .$this->article(5, 'Prístup do objektov, bezpečnosť a mlčanlivosť', [
                'Kľúče a prístupové prostriedky prevezme poskytovateľ na základe písomného záznamu, používa ich len na plnenie tejto zmluvy a vráti ich najneskôr pri skončení zmluvy.',
                'Poskytovateľ zodpovedá za bezpečnosť a ochranu zdravia pri svojej činnosti. Zabezpečenie a vybavenie pracoviska a vzájomné informovanie o možných ohrozeniach sa spravuje § 18 zákona č. 124/2006 Z. z. v znení neskorších predpisov.',
                'Poskytovateľ zachováva mlčanlivosť o dôverných skutočnostiach, o ktorých sa dozvedel pri plnení zmluvy. Táto povinnosť sa nevzťahuje na plnenie povinností uložených zákonom a na oznámenie kriminality alebo inej protispoločenskej činnosti.',
                'Ak má poskytovateľ spracúvať osobné údaje v mene objednávateľa, zmluvné strany pred začatím takého spracúvania uzatvoria zmluvu podľa čl. 28 ods. 3 '.self::GDPR.'.',
            ])
            .$this->article(6, 'Trvanie a skončenie zmluvy', [
                '{{if.fixed_term}}Zmluva sa uzatvára na dobu určitú do {{contract.end_date}}.',
                '{{if.indefinite_term}}Zmluva sa uzatvára na dobu neurčitú.',
                'Zmluvu možno skončiť písomnou dohodou alebo písomnou výpoveďou aj bez uvedenia dôvodu. Výpovedná lehota: {{contract.notice_period}}.',
                'Zmluvná strana môže od zmluvy odstúpiť pri jej podstatnom porušení druhou zmluvnou stranou podľa § 344 a § 345 Obchodného zákonníka.',
                'Pri skončení zmluvy zmluvné strany vyúčtujú vykonané služby.',
            ])
            .$this->article(7, 'Záverečné ustanovenia', [
                'Zmluva sa spravuje Obchodným zákonníkom.',
                'Zmluvu možno meniť len písomnými dodatkami podpísanými oboma zmluvnými stranami.',
                'Zmluva nadobúda platnosť a účinnosť dňom podpisu oboma zmluvnými stranami. Každá zmluvná strana dostane jedno vyhotovenie.',
            ])
            .$this->signing('Za objednávateľa: {{tenant.representative}}', 'Poskytovateľ: {{employee.name}}');
    }

    private function confidentialityAgreement(): string
    {
        return $this->heading('{{contract.title}}')
            .'<p style="text-align: center;">{{contract.number}}</p>'
            .'<p>{{block.parties}}</p>'
            .$this->article(1, 'Účel dohody', [
                'Zmluvné strany si pri spolupráci môžu sprístupniť dôverné informácie. Účel a rámec spolupráce: {{contract.subject_scope}}.',
                'Dohoda chráni dôverné informácie každej zmluvnej strany.',
            ])
            .$this->article(2, 'Dôverné informácie', [
                'Dôvernými informáciami sú skutočnosti, ktoré sa zmluvná strana dozvie v súvislosti so spoluprácou a ktoré druhá zmluvná strana označila za dôverné alebo ktorých dôverná povaha vyplýva z okolností, najmä ceny a obchodné podmienky, údaje o klientoch a objektoch, kľúče, prístupové kódy a bezpečnostné opatrenia a skutočnosti zistené v priestoroch klientov.',
                'Dôvernými informáciami je aj obchodné tajomstvo podľa § 17 zákona č. 513/1991 Zb. Obchodný zákonník v znení neskorších predpisov.',
            ])
            .$this->article(3, 'Povinnosti zmluvných strán', [
                'Zmluvná strana používa dôverné informácie len na účel podľa článku 1 a chráni ich primeranými opatreniami pred neoprávneným prístupom.',
                'Zmluvná strana neposkytne dôverné informácie tretej osobe bez písomného súhlasu druhej zmluvnej strany. Sprístupniť ich môže len osobám, ktoré ich potrebujú na účel podľa článku 1 a sú viazané povinnosťou mlčanlivosti.',
                'Zmluvná strana oznámi druhej zmluvnej strane bez zbytočného odkladu neoprávnené sprístupnenie dôverných informácií, o ktorom sa dozvie.',
            ])
            .$this->article(4, 'Výnimky', [
                'Povinnosť mlčanlivosti sa nevzťahuje na informácie, ktoré sú verejne známe bez porušenia tejto dohody alebo ktoré zmluvná strana získala oprávnene z iného zdroja.',
                'Povinnosť mlčanlivosti sa nevzťahuje na plnenie povinnosti uloženej zákonom alebo orgánom verejnej moci a na oznámenie kriminality alebo inej protispoločenskej činnosti podľa zákona č. 54/2019 Z. z. v znení neskorších predpisov.',
                'Ak je zmluvnou stranou zamestnanec, dohoda sa nevzťahuje na jeho pracovné podmienky vrátane mzdových podmienok a podmienky zamestnávania (§ 44 ods. 2 písm. a) zákona č. 311/2001 Z. z. Zákonník práce v znení neskorších predpisov).',
            ])
            .$this->article(5, 'Osobné údaje', [
                'Ak dôverné informácie obsahujú osobné údaje, zmluvná strana s nimi nakladá v súlade s nariadením Európskeho parlamentu a Rady (EÚ) 2016/679 (všeobecné nariadenie o ochrane údajov) a zákonom č. 18/2018 Z. z. o ochrane osobných údajov v znení neskorších predpisov.',
                'Táto dohoda nie je zmluvou so sprostredkovateľom podľa čl. 28 uvedeného nariadenia.',
            ])
            .$this->article(6, 'Trvanie', [
                'Dohoda je účinná od {{contract.valid_from}}.',
                '{{if.fixed_term}}Dohoda sa uzatvára na dobu určitú do {{contract.end_date}}.',
                '{{if.indefinite_term}}Dohoda sa uzatvára na dobu neurčitú.',
                'Povinnosť mlčanlivosti trvá aj po skončení dohody, kým informácie nespĺňajú podmienky výnimky podľa článku 4.',
            ])
            .$this->article(7, 'Vrátenie informácií a porušenie dohody', [
                'Na žiadosť druhej zmluvnej strany alebo pri skončení spolupráce zmluvná strana vráti alebo zničí dôverné informácie a ich kópie, ak ich nie je povinná uchovávať podľa právneho predpisu.',
                'Zmluvná strana, ktorá poruší túto dohodu, nahradí druhej zmluvnej strane škodu podľa právneho predpisu, ktorým sa spravuje vzťah zmluvných strán.',
            ])
            .$this->article(8, 'Záverečné ustanovenia', [
                'Dohodu možno meniť len písomne.',
                'Dohoda nadobúda platnosť dňom podpisu oboma zmluvnými stranami. Každá zmluvná strana dostane jedno vyhotovenie.',
            ])
            .$this->placeAndDate()
            .'<p>{{block.signatures}}</p>';
    }

    private function dataProcessingConsent(): string
    {
        return $this->heading('{{contract.title}}')
            .'<p style="text-align: center;">podľa čl. 6 ods. 1 písm. a) a čl. 7 '.self::GDPR.'</p>'
            .'<p>{{block.parties}}</p>'
            .$this->article(1, 'Prevádzkovateľ a dotknutá osoba', [
                'Dodávateľ uvedený vyššie je prevádzkovateľom osobných údajov. Zmluvná strana uvedená vyššie je dotknutou osobou.',
                'Kontaktné údaje prevádzkovateľa: {{tenant.address}} {{tenant.contact_phone}} {{tenant.contact_email}}',
            ])
            .$this->article(2, 'Predmet súhlasu', [
                'Dotknutá osoba udeľuje súhlas so spracúvaním svojich osobných údajov na tento účel a v tomto rozsahu údajov: {{contract.subject_scope}}.',
                'Súhlas sa nevzťahuje na spracúvanie, ktoré je nevyhnutné na plnenie zmluvy alebo zákonnej povinnosti, najmä na vedenie pracovnoprávnej a mzdovej agendy. O takom spracúvaní informuje prevádzkovateľ dotknutú osobu osobitne.',
                'Súhlas sa nevzťahuje na rodné číslo ani na osobitné kategórie osobných údajov podľa čl. 9 GDPR.',
            ])
            .$this->article(3, 'Dobrovoľnosť a odvolanie súhlasu', [
                'Udelenie súhlasu je dobrovoľné. Jeho neudelenie alebo odvolanie nemá pre dotknutú osobu nepriaznivé následky a nie je podmienkou uzatvorenia ani trvania zmluvy.',
                'Dotknutá osoba môže súhlas kedykoľvek odvolať rovnako jednoducho, ako ho udelila, oznámením na kontaktné údaje prevádzkovateľa. Odvolanie nemá vplyv na zákonnosť spracúvania pred jeho odvolaním (čl. 7 ods. 3 GDPR).',
            ])
            .$this->article(4, 'Doba spracúvania', [
                '{{if.fixed_term}}Súhlas sa udeľuje do {{contract.end_date}}, ak ho dotknutá osoba skôr neodvolá.',
                '{{if.indefinite_term}}Súhlas sa udeľuje do jeho odvolania alebo do splnenia účelu podľa článku 2, podľa toho, čo nastane skôr.',
                'Po odvolaní súhlasu alebo uplynutí tejto doby prevádzkovateľ údaje bez zbytočného odkladu vymaže, ak nemá na ich ďalšie spracúvanie iný právny základ.',
            ])
            .$this->article(5, 'Informácie pre dotknutú osobu', [
                'Príjemcov osobných údajov a prípadný prenos do tretej krajiny uvádza prevádzkovateľ v rozsahu podľa článku 2, ak k nim dochádza. Ak prevádzkovateľ určil zodpovednú osobu, oznámi dotknutej osobe jej kontaktné údaje.',
                'Dotknutá osoba má právo na prístup k osobným údajom, na ich opravu a vymazanie, na obmedzenie spracúvania a na prenosnosť údajov podľa čl. 15 až 20 GDPR.',
                'Dotknutá osoba má právo podať sťažnosť Úradu na ochranu osobných údajov Slovenskej republiky.',
                'Poskytnutie údajov na účel podľa článku 2 nie je zákonnou ani zmluvnou požiadavkou. Prevádzkovateľ na základe tohto súhlasu nevykonáva automatizované individuálne rozhodovanie.',
            ])
            .$this->placeAndDate()
            .'<p>{{block.signatures}}</p>';
    }

    private function quote(): string
    {
        return $this->heading('Cenová ponuka {{quote.number}}')
            .'<p><strong>Pre:</strong> {{customer.name}}</p>'
            .'<p>{{customer.address}}</p>'
            .'<p>{{customer.email}}</p>'
            .'<p>Dátum vystavenia: {{quote.issue_date}}</p>'
            .'<p>{{quote.subject}}</p>'
            .'<p>{{if.has_object}}Objekt: {{object.name}}, {{object.address}}</p>'
            .'<h2>Ponúkané služby</h2>'
            .'<p>{{block.quote_items}}</p>'
            .'<p>{{block.quote_totals}}</p>'
            .'<h2>Podmienky ponuky</h2>'
            .$this->list([
                'Ponuka platí do {{quote.valid_until}}.',
                'Ponuka má informatívny charakter a nie je návrhom na uzavretie zmluvy podľa § 43a ods. 1 zákona č. 40/1964 Zb. Občiansky zákonník v znení neskorších predpisov. Záväzok poskytnúť služby vznikne až uzavretím písomnej zmluvy.',
                'Ceny vychádzajú z údajov známych v deň vystavenia ponuky. Pri zmene rozsahu alebo podmienok služieb možno ponuku upraviť.',
                '{{if.vat_payer}}K cene bez dane sa pripočítava daň z pridanej hodnoty v sadzbe podľa právnych predpisov účinných ku dňu dodania služby.',
                'Podpis na tejto ponuke potvrdzuje záujem o ponúkané služby a nie je uzavretím zmluvy.',
            ])
            .'<p>{{quote.note}}</p>'
            .'<p>{{block.signatures}}</p>';
    }

    private function employmentParties(): string
    {
        return '<p><strong>Zamestnávateľ:</strong> {{tenant.name}}, sídlo: {{tenant.address}}, IČO: {{tenant.ico}}, zastúpený: {{tenant.representative}} (ďalej len „zamestnávateľ“)</p>'
            .'<p>{{tenant.registration_info}}</p>'
            .'<p>a</p>'
            .'<p><strong>Zamestnanec:</strong> {{employee.name}}, dátum narodenia: {{employee.birth_date}}, adresa: {{employee.address}} (ďalej len „zamestnanec“)</p>';
    }

    // § 226 and § 228a allow no open-ended agreement: the indefinite branch stays unresolved until an end date is written in.
    private function agreementPeriodArticle(int $number, string $title, string $subject, string $limit): string
    {
        return $this->article($number, $title, [
            '{{if.fixed_term}}'.$subject.' v dobe od {{contract.valid_from}} do {{contract.end_date}}.',
            '{{if.indefinite_term}}'.$subject.' v dobe od {{contract.valid_from}} do {{employment.agreement_end_date}}.',
            $limit,
        ]);
    }

    private function agreementWorkingTimeArticle(int $number, string $recordDuty): string
    {
        return $this->article($number, 'Pracovný čas a predvídateľnosť práce', [
            'Pracovný čas v priebehu 24 hodín nesmie presiahnuť 12 hodín, u mladistvého zamestnanca 8 hodín. Prestávky a odpočinok sa spravujú § 91 až § 93 Zákonníka práce. Prácu nadčas ani pracovnú pohotovosť nemožno nariadiť ani dohodnúť (§ 223 ods. 2 Zákonníka práce).',
            'Dni a časové úseky, v ktorých môže zamestnávateľ vyžadovať vykonávanie práce: {{employment.work_schedule}}.',
            'Zamestnávateľ informuje zamestnanca o výkone práce najmenej 24 hodín pred jej začiatkom (§ 223a ods. 1 Zákonníka práce). Zamestnanec nie je povinný vykonať prácu požadovanú v rozpore s týmito údajmi. Pri zrušení výkonu práce v kratšej lehote patrí zamestnancovi náhrada podľa § 223a ods. 4 Zákonníka práce.',
            'Zamestnávateľ je povinný '.$recordDuty.' (§ 224 ods. 2 písm. e) Zákonníka práce).',
        ]);
    }

    private function agreementDutiesArticle(int $number): string
    {
        return $this->article($number, 'Práva a povinnosti', [
            'Zamestnanec vykonáva prácu osobne, zodpovedne a riadne, dodržiava dohodnuté podmienky a právne predpisy vzťahujúce sa na vykonávanú prácu, najmä predpisy na zaistenie bezpečnosti a ochrany zdravia pri práci, s ktorými bol oboznámený.',
            'Zamestnanec riadne hospodári so zverenými prostriedkami a chráni majetok zamestnávateľa pred poškodením, stratou, zničením a zneužitím. Bez zbytočného odkladu písomne oznamuje zamestnávateľovi zmeny, ktoré sa týkajú pracovnoprávneho vzťahu a súvisia s jeho osobou.',
            'Zamestnávateľ utvára primerané pracovné podmienky zabezpečujúce riadny a bezpečný výkon práce, poskytuje potrebné základné prostriedky, materiál, náradie a osobné ochranné pracovné prostriedky a oboznámi zamestnanca s predpismi vzťahujúcimi sa na vykonávanú prácu (§ 224 Zákonníka práce).',
            'Zamestnanec zachováva mlčanlivosť o skutočnostiach zistených v objektoch klientov a o kľúčoch a prístupových kódoch. Táto povinnosť sa nevzťahuje na oznámenie kriminality alebo inej protispoločenskej činnosti ani na pracovné a mzdové podmienky zamestnanca (§ 44 ods. 2 písm. a) Zákonníka práce).',
            'Zodpovednosť za škodu sa spravuje § 225 Zákonníka práce.',
            'Zamestnávateľ spracúva osobné údaje zamestnanca na účely tohto pracovnoprávneho vzťahu. Informácie podľa čl. 13 '.self::GDPR.' poskytuje zamestnancovi osobitne.',
        ]);
    }

    private function agreementClosingArticle(int $number, string $form): string
    {
        return $this->article($number, 'Záverečné ustanovenia', [
            $form,
            'Ak priemerný týždenný pracovný čas presiahne tri hodiny v období štyroch po sebe nasledujúcich týždňov, poskytne zamestnávateľ zamestnancovi aj písomnú informáciu podľa § 47a ods. 1 písm. a) a d) Zákonníka práce vrátane výplatných termínov (§ 223 ods. 3 Zákonníka práce).',
            'Spory z tejto dohody sa prejednávajú rovnako ako spory z pracovného pomeru. Pri skončení dohody vydá zamestnávateľ zamestnancovi potvrdenie podľa § 223 ods. 7 Zákonníka práce.',
            'Práva a povinnosti neupravené v tejto dohode sa spravujú Zákonníkom práce.',
            'Dohoda nadobúda platnosť dňom podpisu oboma zmluvnými stranami. Jedno vyhotovenie dostane zamestnanec.',
        ]);
    }

    private function heading(string $text): string
    {
        return '<h1 style="text-align: center;">'.$text.'</h1>';
    }

    private function title(string $text, string $basis): string
    {
        return $this->heading($text)
            .'<p style="text-align: center;">'.$basis.'</p>'
            .'<p style="text-align: center;">{{contract.number}}</p>';
    }

    /** @param list<string> $paragraphs */
    private function article(int $number, string $title, array $paragraphs): string
    {
        return '<h2 style="text-align: center;">Článok '.$number.' – '.$title.'</h2>'.$this->list($paragraphs);
    }

    /** @param list<string> $items */
    private function list(array $items): string
    {
        return '<ol>'.implode('', array_map(static fn (string $item): string => '<li>'.$item.'</li>', $items)).'</ol>';
    }

    private function placeAndDate(): string
    {
        return '<p>V {{tenant.city}} dňa ____________________</p>';
    }

    // Same markup as documents.blocks.signatures, whose fixed labels name a supplier, which is wrong for these parties.
    private function signing(string $left, string $right): string
    {
        return $this->placeAndDate()
            .'<table style="width: 100%; border-collapse: collapse; margin-top: 60px;"><tbody><tr>'
            .'<td style="width: 45%; border-top: 1px solid #333333; padding: 4px 0 0 0;"><p>'.$left.'</p></td>'
            .'<td style="width: 10%;"></td>'
            .'<td style="width: 45%; border-top: 1px solid #333333; padding: 4px 0 0 0;"><p>'.$right.'</p></td>'
            .'</tr></tbody></table>';
    }
}
