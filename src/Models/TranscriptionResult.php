<?php

declare(strict_types=1);

namespace NAtlas\Models;

final class TranscriptionResult
{
    public function __construct(
        public readonly string $text,
        public readonly Language $language,
    ) {
    }
}