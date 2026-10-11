<?php

declare(strict_types=1);

namespace NAtlas\Models;

use NAtlas\Exceptions\ValidationException;

/**
 * Languages supported by N-ATLAS.
 */
enum Language: string
{
    case English = 'en';
    case Hausa = 'ha';
    case Igbo = 'ig';
    case Yoruba = 'yo';

    /**
     * Resolve a language code or alias to a Language enum.
     */
    public static function resolve(self|string $language): self
    {
        if ($language instanceof self) {
            return $language;
        }

        $normalized = strtolower(trim($language));

        return match ($normalized) {
            'en', 'english', 'nigerian-english', 'pcm' => self::English,
            'ha', 'hausa', 'hausa language' => self::Hausa,
            'ig', 'igbo', 'igbo language' => self::Igbo,
            'yo', 'yoruba', 'yorùbá', 'yoruba language' => self::Yoruba,
            default => throw new ValidationException(
                "Unsupported language: '{$language}'. Supported: en, ha, ig, yo."
            ),
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::English => 'English',
            self::Hausa => 'Hausa',
            self::Igbo => 'Igbo',
            self::Yoruba => 'Yoruba',
        };
    }

    public function nativeLabel(): string
    {
        return match ($this) {
            self::English => 'English',
            self::Hausa => 'Hausa',
            self::Igbo => 'Igbo',
            self::Yoruba => 'Yorùbá',
        };
    }
}