<?php

declare(strict_types=1);

namespace NAtlas\Models;

final class ChatResult
{
    /**
     * @param array<string, mixed> $usage
     */
    public function __construct(
        public readonly string $content,
        public readonly string $finishReason,
        public readonly array $usage,
        public readonly Language $language,
    ) {
    }

    public function promptTokens(): int
    {
        return (int) ($this->usage['prompt_tokens'] ?? 0);
    }

    public function completionTokens(): int
    {
        return (int) ($this->usage['completion_tokens'] ?? 0);
    }

    public function totalTokens(): int
    {
        return (int) ($this->usage['total_tokens'] ?? 0);
    }
}