<?php

declare(strict_types=1);

namespace NAtlas\Models;

final class StreamChunk
{
    public function __construct(
        public readonly string $delta,
        public readonly ?string $finishReason,
        public readonly Language $language,
    ) {
    }

    public function isFinal(): bool
    {
        return $this->finishReason !== null;
    }
}