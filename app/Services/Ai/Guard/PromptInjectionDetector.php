<?php

declare(strict_types=1);

namespace App\Services\Ai\Guard;

/** Catches known shapes only and needs no AI provider; the hard limits must hold when it misses. */
final readonly class PromptInjectionDetector
{
    public const int THRESHOLD = 3;

    private const int MAX_ENCODED_CHUNKS = 20;

    private const int MAX_ENCODED_LENGTH = 4000;

    private const string SENSITIVE_TOOLS = 'prepare_action|confirm_action|delete_global_task|update_document_template|issue_invoice|send_invoice|send_quote|sign_contract|cancel_invoice|delete_invoice_draft|delete_quote|delete_task|delete_expense|create_invoice_draft|update_invoice_draft|update_quote_draft|assign_task|create_expense';

    /** @var array<string, array{0: int, 1: list<string>}> */
    private const array RULES = [
        'override_instructions' => [3, [
            '/\b(?:ignore|disregard|forget|override|bypass|pay no attention to|do not follow|don\'?t follow)\b.{0,40}\b(?:previous|prior|above|earlier|preceding|system|original|all|any|your)\b.{0,30}\b(?:instructions?|prompts?|rules?|directions?|guidelines?|guidance)\b/su',
            '/\b(?:ignore|disregard|forget|override|bypass|pay no attention to)\b.{0,40}\b(?:previous|prior|above|earlier|preceding|system)\b.{0,30}\b(?:messages?|context)\b/su',
            '/\b(?:ignoruj(?:te)?|zabudni(?:te)?|zapomen(?:te)?|nedbaj(?:te)?|obid(?:te)?|prepis(?:te)?|nerespektuj(?:te)?|neber(?:te)? do uvahy)\b.{0,40}\b(?:pokyny|pokynov|pokynu|instrukcie|instrukcii|instrukce|prikazy|pravidla|pravidiel|prompt)\b/su',
            '/(?:^|[^\p{L}])(?:ігноруй(?:те)?|проігноруй(?:те)?|забудь(?:те)?|нехтуй(?:те)?)(?:[^\p{L}]).{0,40}(?:інструкці|вказівк|правил|підказк)/su',
        ]],
        // Weak alone: folded Slovak "Systém: kľúče u vrátnika" looks the same.
        'role_label' => [2, [
            '/(?:^|[\n\r"\'>;.!?])\s*(?:system|developer|assistant)\s*:\s*\S/u',
        ]],
        'role_spoofing' => [3, [
            '/(?:^|[\n\r"\'>;.!?])\s*(?:system|developer|assistant)\s*:\s*(?:you\b|delete|send|issue|ignore|confirm|sign|cancel|forward|call|execute|zmaz|posli|vystav|ignoruj|potvrd|podpis|zrus)/u',
            '/<\/?\s*(?:system|instructions?|assistant|developer|tool_output|untrusted)[^>]{0,40}>/u',
            '/\[\/?(?:inst|sys)\]/u',
            '/<\|[a-z_]{2,20}\|>/u',
            '/#{2,}\s*(?:system|instructions?|pokyny)\b/u',
        ]],
        'persona_change' => [3, [
            '/\b(?:you are now|from now on you|act as (?:an? |the )?(?:admin|administrator|root|system|developer)|pretend (?:to be|you are)|new instructions|updated instructions|developer mode|jailbreak)\b/u',
            '/\b(?:odteraz si|od teraz si|teraz si (?:admin|administrator|system|vyvojar)|nove (?:pokyny|instrukcie)|sprava pre (?:ai|claude|chatbot)|pokyny pre (?:ai|asistenta|model|claude|chatbot)|instrukcie pre (?:ai|asistenta|model|claude|chatbot))\b/u',
            '/\b(?:instructions?|note|message) (?:for|to) (?:the )?(?:ai|model|claude|llm|chatbot|assistant(?=\s*(?:[:,.!\-—]|$)))/mu',
            '/\b(?:attention|dear|hey) (?:ai|claude|llm|chatbot|assistant(?=\s*(?:[:,.!\-—]|$)))/mu',
        ]],
        'prompt_extraction' => [3, [
            '/\b(?:reveal|show|print|repeat|output|leak)\b.{0,30}\b(?:system prompt|your (?:instructions|prompt|rules))\b/su',
            '/\bsystem(?:ovy|ovu|ove)? prompt\w*\b/u',
            '/\b(?:ukaz|vypis|prezrad|zopakuj)\b.{0,30}\b(?:svoje|tvoje|systemove)\b.{0,20}\b(?:instrukcie|pokyny|prompt)\b/su',
        ]],
        'tool_directive' => [3, [
            '/\b(?:confirm_action|delete_global_task)\b/u',
            '/\b(?:call|use|run|invoke|execute|trigger|zavolaj|zavolajte|spusti|spustite|pouzi|pouzite|vykonaj|vykonajte)\b.{0,25}\b(?:'.self::SENSITIVE_TOOLS.')\b/su',
        ]],
        'data_exfiltration' => [3, [
            '/!\[[^\]]{0,100}\]\(\s*https?:\/\//u',
            '/\b(?:send|forward|post|upload|exfiltrate|leak|transfer|email|include|append|posli|poslite|preposli|odosli|odoslite|nahraj)\b.{0,60}\b(?:token|tokens|password|passwords|api key|secret|heslo|hesla|prihlasovacie udaje)\b.{0,80}(?:https?:\/\/|[^\s"]+@[^\s"]+\.\w{2,})/su',
            '/\b(?:send|forward|post|upload|exfiltrate|leak|transfer|email|posli|poslite|preposli|odosli|odoslite|nahraj)\b.{0,80}\b(?:(?:customer|client|employee|user)s? (?:data|details|list|records|contacts|emails)|klientov a ich udaje|zakaznikov a ich udaje|(?:udaje|zoznam) (?:klientov|zakaznikov|zamestnancov))\b.{0,40}(?:https?:\/\/|[^\s"]+@[^\s"]+\.\w{2,})/su',
            '/\b(?:send|forward|post|upload|exfiltrate|leak|transfer|email|posli|poslite|preposli|odosli|odoslite|nahraj)\b.{0,60}\b(?:(?:all|every|entire|whole) (?:the )?(?:data|records|database|invoices)|vsetky (?:udaje|data|faktury a)|celu databazu)\b.{0,40}(?:https?:\/\/)/su',
        ]],
        'false_approval' => [2, [
            '/\b(?:user|owner|admin|administrator|manager)\b.{0,20}\b(?:approved|confirmed|authori[sz]ed|consented|said yes)\b/su',
            '/\b(?:pouzivatel|majitel|vlastnik|administrator|admin)\b.{0,20}\b(?:schvalil|potvrdil|povolil|odsuhlasil|suhlasil)\w*\b/su',
            '/\bpre-?(?:approved|authori[sz]ed)\b/u',
        ]],
        'confirmation_bypass' => [2, [
            '/\b(?:without (?:asking|confirmation|confirming|approval|telling)|do not (?:ask|tell|inform|notify|mention)|don\'?t (?:ask|tell|inform|notify|mention)|skip (?:the )?(?:confirmation|preview)|no (?:further )?confirmation)\b/u',
            '/\b(?:bez (?:potvrdenia|opytania|schvalenia|vedomia)|nepytaj sa|nehovor|neinformuj|neupozornuj|nespominaj)\b/u',
        ]],
        'urgent_action' => [1, [
            '/\b(?:confirm|execute|issue|send|sign|delete|cancel)\b.{0,30}\b(?:immediately|right now|automatically)\b/su',
            '/\b(?:potvrd|odosli|odoslite|vystav|podpis|zmaz|zrus|posli)\w*\b.{0,30}\b(?:okamzite|ihned|automaticky)\b/su',
        ]],
    ];

    public function scan(string $text): InjectionVerdict
    {
        // Invalid UTF-8 makes every /u pattern fail, which would read as "no match".
        $text = mb_scrub($text, 'UTF-8');
        $rules = $this->hiddenCharacterRules($text);
        $variants = $this->variants($text);
        $evidence = null;

        foreach (self::RULES as $name => [, $patterns]) {
            $match = $this->firstMatch($patterns, $variants);

            if ($match !== null) {
                $rules[$name] = true;
                $evidence ??= $match;
            }
        }

        if ($this->hasEncodedInstructions($variants[0], $text)) {
            $rules['encoded_payload'] = true;
        }

        return $this->verdict(array_keys($rules), $evidence);
    }

    /** @param  list<string>  $rules */
    private function verdict(array $rules, ?string $evidence): InjectionVerdict
    {
        $score = 0;

        foreach ($rules as $rule) {
            $score += match ($rule) {
                'hidden_tag_characters', 'encoded_payload' => self::THRESHOLD,
                'bidi_controls' => 2,
                'zero_width_characters' => 1,
                default => self::RULES[$rule][0] ?? 0,
            };
        }

        return new InjectionVerdict($score >= self::THRESHOLD, $score, $rules, $evidence);
    }

    /** @return array<string, true> */
    private function hiddenCharacterRules(string $text): array
    {
        $rules = [];

        if (preg_match('/[\x{E0000}-\x{E007F}]/u', $text) === 1) {
            $rules['hidden_tag_characters'] = true;
        }

        if (preg_match('/[\x{202A}-\x{202E}\x{2066}-\x{2069}]/u', $text) === 1) {
            $rules['bidi_controls'] = true;
        }

        if (preg_match('/[\x{00AD}\x{034F}\x{115F}\x{1160}\x{180E}\x{200B}\x{200C}\x{2060}-\x{2064}\x{3164}\x{FE00}-\x{FE0E}\x{FEFF}\x{FFA0}\x{E0100}-\x{E01EF}]/u', $text) === 1) {
            $rules['zero_width_characters'] = true;
        }

        return $rules;
    }

    /** @return list<string> */
    private function variants(string $text): array
    {
        $uncollapsed = $this->fold($text);
        $folded = $this->collapse($uncollapsed);

        $mapped = strtr($folded, [
            'а' => 'a', 'е' => 'e', 'о' => 'o', 'р' => 'p', 'с' => 'c', 'у' => 'y', 'х' => 'x', 'і' => 'i', 'ѕ' => 's', 'ј' => 'j',
            'α' => 'a', 'ε' => 'e', 'ι' => 'i', 'κ' => 'k', 'ν' => 'v', 'ο' => 'o', 'ρ' => 'p', 'τ' => 't', 'υ' => 'y', 'χ' => 'x',
            '0' => 'o', '1' => 'i', '3' => 'e', '4' => 'a', '5' => 's', '7' => 't', '$' => 's',
        ]);

        // A single separator splits letters of a word; a wider gap still separates words.
        $squeezed = $this->collapse((string) preg_replace('/(?<=\b\p{L})[ .\-_](?=\p{L}\b)/u', '', $uncollapsed));

        return array_values(array_unique([$folded, $mapped, $squeezed]));
    }

    private function collapse(string $text): string
    {
        return (string) preg_replace('/[ \t\x{00A0}]+/u', ' ', $text);
    }

    private function fold(string $text): string
    {
        // A joiner or emoji selector between letters also splits a word; the shown text keeps them for emoji.
        $stripped = str_replace(["\u{200D}", "\u{FE0F}"], '', PromptGuard::stripInvisible($text));
        $stripped = (string) preg_replace_callback(
            '/[\x{FF01}-\x{FF5E}]/u',
            static fn (array $match): string => mb_chr((int) mb_ord($match[0]) - 0xFEE0) ?: '',
            str_replace("\u{3000}", ' ', $stripped),
        );

        // Tool output is JSON, so line breaks and quotes inside values arrive escaped.
        $unescaped = (string) preg_replace_callback(
            '/\\\\u([0-9a-fA-F]{4})/',
            static fn (array $match): string => mb_chr((int) hexdec($match[1])) ?: '',
            $stripped,
        );
        $unescaped = strtr($unescaped, ['\\n' => "\n", '\\r' => "\n", '\\t' => ' ', '\\"' => '"', '\\/' => '/', '\\\\' => '\\']);

        $lower = mb_strtolower($unescaped);

        return strtr($lower, [
            'á' => 'a', 'ä' => 'a', 'č' => 'c', 'ď' => 'd', 'é' => 'e', 'ě' => 'e', 'í' => 'i', 'ĺ' => 'l', 'ľ' => 'l',
            'ň' => 'n', 'ó' => 'o', 'ô' => 'o', 'ŕ' => 'r', 'ř' => 'r', 'š' => 's', 'ť' => 't', 'ú' => 'u', 'ů' => 'u', 'ý' => 'y', 'ž' => 'z',
        ]);
    }

    /**
     * @param  list<string>  $patterns
     * @param  list<string>  $variants
     */
    private function firstMatch(array $patterns, array $variants): ?string
    {
        foreach ($patterns as $pattern) {
            foreach ($variants as $variant) {
                if (preg_match($pattern, $variant, $match) === 1) {
                    return $match[0];
                }
            }
        }

        return null;
    }

    private function hasEncodedInstructions(string $folded, string $original): bool
    {
        if (preg_match_all('/[A-Za-z0-9+\/]{40,}={0,2}/', $original, $matches) === false) {
            return $folded !== '';
        }

        foreach (array_slice($matches[0], 0, self::MAX_ENCODED_CHUNKS) as $chunk) {
            $decoded = strlen($chunk) <= self::MAX_ENCODED_LENGTH ? base64_decode($chunk, true) : false;

            if ($decoded === false || ! mb_check_encoding($decoded, 'UTF-8')) {
                continue;
            }

            $variants = $this->variants($decoded);

            foreach (self::RULES as [$weight, $patterns]) {
                if ($weight === self::THRESHOLD && $this->firstMatch($patterns, $variants) !== null) {
                    return true;
                }
            }
        }

        return false;
    }
}
