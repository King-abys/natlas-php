<?php

declare(strict_types=1);

namespace NAtlas;

use NAtlas\Exceptions\ValidationException;

/**
 * Configuration for the N-ATLAS client.
 *
 * Reads from environment variables by default:
 *   NATLAS_BASE_URL, NATLAS_API_KEY, NATLAS_MODEL, NATLAS_TIMEOUT, NATLAS_MAX_RETRIES
 */
final class Config
{
    public function __construct(
        public readonly string $baseUrl,
        public readonly ?string $apiKey = null,
        public readonly string $model = 'NCAIR1/N-ATLaS',
        public readonly int $timeout = 60,
        public readonly int $maxRetries = 2,
    ) {
        if (empty(trim($this->baseUrl))) {
            throw new ValidationException('baseUrl must not be empty.');
        }
    }

    public static function fromEnvironment(): self
    {
        return new self(
            baseUrl: rtrim((string) ($_ENV['NATLAS_BASE_URL'] ?? getenv('NATLAS_BASE_URL') ?: 'http://localhost:8080'), '/'),
            apiKey: ($_ENV['NATLAS_API_KEY'] ?? getenv('NATLAS_API_KEY')) ?: null,
            model: ($_ENV['NATLAS_MODEL'] ?? getenv('NATLAS_MODEL')) ?: 'NCAIR1/N-ATLaS',
            timeout: (int) (($_ENV['NATLAS_TIMEOUT'] ?? getenv('NATLAS_TIMEOUT')) ?: 60),
            maxRetries: (int) (($_ENV['NATLAS_MAX_RETRIES'] ?? getenv('NATLAS_MAX_RETRIES')) ?: 2),
        );
    }

    /**
     * Returns the base URL with /v1 suffix for API calls.
     */
    public function apiBase(): string
    {
        return $this->baseUrl . '/v1';
    }

    public function healthUrl(): string
    {
        return $this->baseUrl . '/health';
    }
}