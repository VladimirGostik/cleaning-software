<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Validation Language Lines
    |--------------------------------------------------------------------------
    |
    | The following language lines contain the default error messages used by
    | the validator class. Some of these rules have multiple versions such
    | as the size rules. Feel free to tweak each of these messages here.
    |
    */

    'accepted' => 'Pole :attribute musí byť akceptované.',
    'accepted_if' => 'Pole :attribute musí byť akceptované, keď :other je :value.',
    'active_url' => 'Pole :attribute musí byť platná URL adresa.',
    'after' => 'Pole :attribute musí byť dátum po :date.',
    'after_or_equal' => 'Pole :attribute musí byť dátum po alebo rovný :date.',
    'alpha' => 'Pole :attribute môže obsahovať iba písmená.',
    'alpha_dash' => 'Pole :attribute môže obsahovať iba písmená, čísla, pomlčky a podčiarkovníky.',
    'alpha_num' => 'Pole :attribute môže obsahovať iba písmená a čísla.',
    'any_of' => 'Pole :attribute je neplatné.',
    'array' => 'Pole :attribute musí byť pole.',
    'ascii' => 'Pole :attribute môže obsahovať iba jednobytové alfanumerické znaky a symboly.',
    'before' => 'Pole :attribute musí byť dátum pred :date.',
    'before_or_equal' => 'Pole :attribute musí byť dátum pred alebo rovný :date.',
    'between' => [
        'array' => 'Pole :attribute musí mať medzi :min a :max položkami.',
        'file' => 'Pole :attribute musí mať veľkosť medzi :min a :max kilobajtov.',
        'numeric' => 'Pole :attribute musí byť medzi :min a :max.',
        'string' => 'Pole :attribute musí mať medzi :min a :max znakmi.',
    ],
    'boolean' => 'Pole :attribute musí mať hodnotu true alebo false.',
    'can' => 'Pole :attribute obsahuje nepovolenú hodnotu.',
    'confirmed' => 'Potvrdenie poľa :attribute sa nezhoduje.',
    'contains' => 'Pole :attribute neobsahuje požadovanú hodnotu.',
    'current_password' => 'Heslo je nesprávne.',
    'date' => 'Pole :attribute musí byť platný dátum.',
    'date_equals' => 'Pole :attribute musí byť dátum rovný :date.',
    'date_format' => 'Pole :attribute musí zodpovedať formátu :format.',
    'decimal' => 'Pole :attribute musí mať :decimal desatinných miest.',
    'declined' => 'Pole :attribute musí byť odmietnuté.',
    'declined_if' => 'Pole :attribute musí byť odmietnuté, keď :other je :value.',
    'different' => 'Pole :attribute a :other musia byť rozdielne.',
    'digits' => 'Pole :attribute musí mať :digits číslic.',
    'digits_between' => 'Pole :attribute musí mať medzi :min a :max číslicami.',
    'dimensions' => 'Pole :attribute má neplatné rozmery obrázka.',
    'distinct' => 'Pole :attribute obsahuje duplicitnú hodnotu.',
    'doesnt_contain' => 'Pole :attribute nesmie obsahovať žiadnu z nasledujúcich hodnôt: :values.',
    'doesnt_end_with' => 'Pole :attribute nesmie končiť jednou z nasledujúcich hodnôt: :values.',
    'doesnt_start_with' => 'Pole :attribute nesmie začínať jednou z nasledujúcich hodnôt: :values.',
    'email' => 'Pole :attribute musí byť platná e-mailová adresa.',
    'encoding' => 'Pole :attribute musí byť zakódované v :encoding.',
    'ends_with' => 'Pole :attribute musí končiť jednou z nasledujúcich hodnôt: :values.',
    'enum' => 'Vybraná hodnota :attribute je neplatná.',
    'exists' => 'Vybraná hodnota :attribute je neplatná.',
    'extensions' => 'Pole :attribute musí mať jednu z nasledujúcich prípon: :values.',
    'file' => 'Pole :attribute musí byť súbor.',
    'filled' => 'Pole :attribute musí mať hodnotu.',
    'gt' => [
        'array' => 'Pole :attribute musí mať viac ako :value položiek.',
        'file' => 'Pole :attribute musí byť väčšie ako :value kilobajtov.',
        'numeric' => 'Pole :attribute musí byť väčšie ako :value.',
        'string' => 'Pole :attribute musí mať viac ako :value znakov.',
    ],
    'gte' => [
        'array' => 'Pole :attribute musí mať :value alebo viac položiek.',
        'file' => 'Pole :attribute musí byť väčšie alebo rovné :value kilobajtov.',
        'numeric' => 'Pole :attribute musí byť väčšie alebo rovné :value.',
        'string' => 'Pole :attribute musí mať :value alebo viac znakov.',
    ],
    'hex_color' => 'Pole :attribute musí byť platná hexadecimálna farba.',
    'image' => 'Pole :attribute musí byť obrázok.',
    'in' => 'Vybraná hodnota :attribute je neplatná.',
    'in_array' => 'Pole :attribute musí existovať v :other.',
    'in_array_keys' => 'Pole :attribute musí obsahovať aspoň jeden z nasledujúcich kľúčov: :values.',
    'integer' => 'Pole :attribute musí byť celé číslo.',
    'ip' => 'Pole :attribute musí byť platná IP adresa.',
    'ipv4' => 'Pole :attribute musí byť platná IPv4 adresa.',
    'ipv6' => 'Pole :attribute musí byť platná IPv6 adresa.',
    'json' => 'Pole :attribute musí byť platný JSON reťazec.',
    'list' => 'Pole :attribute musí byť zoznam.',
    'lowercase' => 'Pole :attribute musí byť malými písmenami.',
    'lt' => [
        'array' => 'Pole :attribute musí mať menej ako :value položiek.',
        'file' => 'Pole :attribute musí byť menšie ako :value kilobajtov.',
        'numeric' => 'Pole :attribute musí byť menšie ako :value.',
        'string' => 'Pole :attribute musí mať menej ako :value znakov.',
    ],
    'lte' => [
        'array' => 'Pole :attribute nesmie mať viac ako :value položiek.',
        'file' => 'Pole :attribute musí byť menšie alebo rovné :value kilobajtov.',
        'numeric' => 'Pole :attribute musí byť menšie alebo rovné :value.',
        'string' => 'Pole :attribute nesmie mať viac ako :value znakov.',
    ],
    'mac_address' => 'Pole :attribute musí byť platná MAC adresa.',
    'max' => [
        'array' => 'Pole :attribute nesmie mať viac ako :max položiek.',
        'file' => 'Pole :attribute nesmie byť väčšie ako :max kilobajtov.',
        'numeric' => 'Pole :attribute nesmie byť väčšie ako :max.',
        'string' => 'Pole :attribute nesmie byť dlhšie ako :max znakov.',
    ],
    'max_digits' => 'Pole :attribute nesmie mať viac ako :max číslic.',
    'mimes' => 'Pole :attribute musí byť súbor typu: :values.',
    'mimetypes' => 'Pole :attribute musí byť súbor typu: :values.',
    'min' => [
        'array' => 'Pole :attribute musí mať aspoň :min položiek.',
        'file' => 'Pole :attribute musí mať aspoň :min kilobajtov.',
        'numeric' => 'Pole :attribute musí byť aspoň :min.',
        'string' => 'Pole :attribute musí mať aspoň :min znakov.',
    ],
    'min_digits' => 'Pole :attribute musí mať aspoň :min číslic.',
    'missing' => 'Pole :attribute musí chýbať.',
    'missing_if' => 'Pole :attribute musí chýbať, keď :other je :value.',
    'missing_unless' => 'Pole :attribute musí chýbať, pokiaľ :other nie je :value.',
    'missing_with' => 'Pole :attribute musí chýbať, keď je prítomné :values.',
    'missing_with_all' => 'Pole :attribute musí chýbať, keď sú prítomné :values.',
    'multiple_of' => 'Pole :attribute musí byť násobkom :value.',
    'not_in' => 'Vybraná hodnota :attribute je neplatná.',
    'not_regex' => 'Formát poľa :attribute je neplatný.',
    'numeric' => 'Pole :attribute musí byť číslo.',
    'password' => [
        'letters' => 'Pole :attribute musí obsahovať aspoň jedno písmeno.',
        'mixed' => 'Pole :attribute musí obsahovať aspoň jedno veľké a jedno malé písmeno.',
        'numbers' => 'Pole :attribute musí obsahovať aspoň jedno číslo.',
        'symbols' => 'Pole :attribute musí obsahovať aspoň jeden symbol.',
        'uncompromised' => 'Zadané :attribute sa objavilo v úniku dát. Zvoľte iné :attribute.',
    ],
    'present' => 'Pole :attribute musí byť prítomné.',
    'present_if' => 'Pole :attribute musí byť prítomné, keď :other je :value.',
    'present_unless' => 'Pole :attribute musí byť prítomné, pokiaľ :other nie je :value.',
    'present_with' => 'Pole :attribute musí byť prítomné, keď je prítomné :values.',
    'present_with_all' => 'Pole :attribute musí byť prítomné, keď sú prítomné :values.',
    'prohibited' => 'Pole :attribute je zakázané.',
    'prohibited_if' => 'Pole :attribute je zakázané, keď :other je :value.',
    'prohibited_if_accepted' => 'Pole :attribute je zakázané, keď :other je akceptované.',
    'prohibited_if_declined' => 'Pole :attribute je zakázané, keď :other je odmietnuté.',
    'prohibited_unless' => 'Pole :attribute je zakázané, pokiaľ :other nie je v :values.',
    'prohibits' => 'Pole :attribute zakazuje prítomnosť :other.',
    'regex' => 'Formát poľa :attribute je neplatný.',
    'required' => 'Pole :attribute je povinné.',
    'required_array_keys' => 'Pole :attribute musí obsahovať záznamy pre: :values.',
    'required_if' => 'Pole :attribute je povinné, keď :other je :value.',
    'required_if_accepted' => 'Pole :attribute je povinné, keď :other je akceptované.',
    'required_if_declined' => 'Pole :attribute je povinné, keď :other je odmietnuté.',
    'required_unless' => 'Pole :attribute je povinné, pokiaľ :other nie je v :values.',
    'required_with' => 'Pole :attribute je povinné, keď je prítomné :values.',
    'required_with_all' => 'Pole :attribute je povinné, keď sú prítomné :values.',
    'required_without' => 'Pole :attribute je povinné, keď :values nie je prítomné.',
    'required_without_all' => 'Pole :attribute je povinné, keď nie je prítomné žiadne z :values.',
    'same' => 'Pole :attribute musí zodpovedať :other.',
    'size' => [
        'array' => 'Pole :attribute musí obsahovať :size položiek.',
        'file' => 'Pole :attribute musí mať :size kilobajtov.',
        'numeric' => 'Pole :attribute musí byť :size.',
        'string' => 'Pole :attribute musí mať :size znakov.',
    ],
    'starts_with' => 'Pole :attribute musí začínať jednou z nasledujúcich hodnôt: :values.',
    'string' => 'Pole :attribute musí byť reťazec.',
    'timezone' => 'Pole :attribute musí byť platné časové pásmo.',
    'unique' => ':attribute je už obsadený.',
    'uploaded' => 'Nahrávanie poľa :attribute zlyhalo.',
    'uppercase' => 'Pole :attribute musí byť veľkými písmenami.',
    'url' => 'Pole :attribute musí byť platná URL adresa.',
    'ulid' => 'Pole :attribute musí byť platné ULID.',
    'uuid' => 'Pole :attribute musí byť platné UUID.',

    /*
    |--------------------------------------------------------------------------
    | Custom Validation Language Lines
    |--------------------------------------------------------------------------
    |
    | Here you may specify custom validation messages for attributes using the
    | convention "attribute.rule" to name the lines. This makes it quick to
    | specify a specific custom language line for a given attribute rule.
    |
    */

    'custom' => [
        'attribute-name' => [
            'rule-name' => 'custom-message',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Custom Validation Attributes
    |--------------------------------------------------------------------------
    |
    | The following language lines are used to swap our attribute placeholder
    | with something more reader friendly such as "E-Mail Address" instead
    | of "email". This simply helps us make our message more expressive.
    |
    */

    'attributes' => [
        'name' => 'Názov',
        'email' => 'E-mail',
        'password' => 'Heslo',
        'password_confirmation' => 'Potvrdenie hesla',
        'ico' => 'IČO',
        'dic' => 'DIČ',
        'vat_number' => 'IČ DPH',
        'is_vat_payer' => 'Platca DPH',
        'client_id' => 'Klient',
        'client_name' => 'Názov klienta',
        'type' => 'Typ',
        'city' => 'Mesto',
        'postal_code' => 'PSČ',
        'country' => 'Krajina',
        'street' => 'Ulica',
        'address_line' => 'Adresa',
        'area_sqm' => 'Plocha (m²)',
        'key_count' => 'Počet kľúčov',
        'key_box_code' => 'Kód trezora kľúčov',
        'floor' => 'Poschodie',
        'access_code' => 'Prístupový kód',
        'special_instructions' => 'Špeciálne pokyny',
        'note' => 'Poznámka',
        'phone' => 'Telefón',
        'locale' => 'Jazyk',
        'color' => 'Farba',
        'role_name' => 'Rola',
        'leader_email' => 'E-mail vedúceho',
        'terms_accepted' => 'Podmienky používania',
        'position' => 'Pozícia',
        'is_primary' => 'Primárny kontakt',
        'is_active' => 'Aktívny',
        'search' => 'Vyhľadávanie',
        'sort' => 'Zoradiť',
        'per_page' => 'Počet na stránku',
        'copy_settings' => 'Kopírovať nastavenia',
        'remember' => 'Zapamätať si ma',
        'token' => 'Token',
    ],

];
