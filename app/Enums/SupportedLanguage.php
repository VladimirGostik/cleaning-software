<?php

declare(strict_types=1);

namespace App\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
enum SupportedLanguage: string
{
    case Slovak = 'sk';
    case English = 'en';
    case Ukrainian = 'uk';

    public function label(): string
    {
        return match ($this) {
            self::Slovak => 'Slovenčina',
            self::English => 'English',
            self::Ukrainian => 'Українська',
        };
    }

    public function flag(): string
    {
        return match ($this) {
            self::Slovak => '🇸🇰',
            self::English => '🇬🇧',
            self::Ukrainian => '🇺🇦',
        };
    }

    /**
     * @return array<int, string>
     */
    public static function codes(): array
    {
        return array_map(fn (self $c) => $c->value, self::cases());
    }

    /**
     * @return array<int, array{code:string, label:string, flag:string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $c) => ['code' => $c->value, 'label' => $c->label(), 'flag' => $c->flag()],
            self::cases(),
        );
    }

    public static function default(): self
    {
        return self::Slovak;
    }

    public static function isSupported(?string $code): bool
    {
        return $code !== null && in_array($code, self::codes(), true);
    }
}
