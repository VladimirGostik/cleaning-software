<?php

declare(strict_types=1);

namespace App\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
enum SupportedLanguage: string
{
    case Slovak = 'sk';
    case English = 'en';

    public function getDisplayName(): string
    {
        return match ($this) {
            self::Slovak => 'Slovenčina',
            self::English => 'English',
        };
    }

    public function getFlag(): string
    {
        return match ($this) {
            self::Slovak => '🇸🇰',
            self::English => '🇬🇧',
        };
    }

    /**
     * @return array<int, string>
     */
    public static function getCodes(): array
    {
        return array_map(fn (self $lang) => $lang->value, self::cases());
    }

    /**
     * @return array<int, array{value: string, label: string, flag: string}>
     */
    public static function getForLanguageSwitcher(): array
    {
        return array_map(
            fn (self $lang) => [
                'value' => $lang->value,
                'label' => $lang->getDisplayName(),
                'flag' => $lang->getFlag(),
            ],
            self::cases(),
        );
    }

    public static function isSupported(string $code): bool
    {
        return in_array($code, self::getCodes(), true);
    }

    public static function getDefault(): self
    {
        return self::Slovak;
    }

    public function label(): string
    {
        return $this->getDisplayName();
    }

    public function color(): string
    {
        return match ($this) {
            self::Slovak => 'blue',
            self::English => 'green',
        };
    }

    public function icon(): string
    {
        return $this->getFlag();
    }

    /**
     * @return array<int, array{id: string, name: string, color: string, icon: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $lang) => [
                'id' => $lang->value,
                'name' => $lang->getDisplayName(),
                'color' => $lang->color(),
                'icon' => $lang->icon(),
            ],
            self::cases(),
        );
    }
}
