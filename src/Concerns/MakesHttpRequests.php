<?php

declare(strict_types=1);

namespace NAtlas\Concerns;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use NAtlas\Exceptions\AuthenticationException;
use NAtlas\Exceptions\ConnectionException;
use NAtlas\Exceptions\NAtlasException;
use Psr\Http\Message\ResponseInterface;

trait MakesHttpRequests
{
    private ?Client $http = null;

    private function getHttpClient(): Client
    {
        if ($this->http === null) {
            $headers = [
                'Accept' => 'application/json',
                'User-Agent' => 'natlas-php/1.0',
            ];

            if ($this->config->apiKey !== null && $this->config->apiKey !== '') {
                $headers['Authorization'] = 'Bearer ' . $this->config->apiKey;
            }

            $this->http = new Client([
                'base_uri' => $this->config->apiBase(),
                'timeout' => $this->config->timeout,
                'connect_timeout' => 10,
                'headers' => $headers,
                'http_errors' => false,
            ]);
        }

        return $this->http;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function postJson(string $path, array $payload): array
    {
        $response = $this->requestWithRetries('POST', $path, [
            'json' => $payload,
        ]);

        return $this->decodeJson($response);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function postMultipart(string $path, array $payload): array
    {
        $response = $this->requestWithRetries('POST', $path, [
            'multipart' => array_map(
                fn ($key, $value) => ['name' => $key, 'contents' => $value],
                array_keys($payload),
                array_values($payload)
            ),
        ]);

        return $this->decodeJson($response);
    }

    /**
     * @return array<string, mixed>
     */
    private function getJson(string $path, bool $allowErrorStatus = false): array
    {
        $response = $this->requestWithRetries('GET', $path, []);

        if (!$allowErrorStatus && $response->getStatusCode() >= 400) {
            $this->throwForStatus($response);
        }

        $body = $this->decodeJson($response);

        if ($allowErrorStatus) {
            return [
                'ok' => $response->getStatusCode() < 400,
                'status' => $response->getStatusCode(),
                'body' => $body,
            ];
        }

        return $body;
    }

    /**
     * Stream an SSE response line by line.
     *
     * @param array<string, mixed> $payload
     * @param callable(array<string, mixed>): void $onData
     */
    private function streamJson(string $path, array $payload, callable $onData): void
    {
        $client = $this->getHttpClient();

        try {
            $response = $client->post($path, [
                'json' => $payload + ['stream' => true],
                'stream' => true,
            ]);
        } catch (ConnectException $e) {
            throw new ConnectionException(
                'Could not connect to N-ATLAS gateway: ' . $e->getMessage(),
                previous: $e
            );
        }

        if ($response->getStatusCode() >= 400) {
            $this->throwForStatus($response);
        }

        $body = $response->getBody();
        $buffer = '';

        while (!$body->eof()) {
            $buffer .= $body->read(8192);

            while (($pos = strpos($buffer, "\n")) !== false) {
                $line = substr($buffer, 0, $pos);
                $buffer = substr($buffer, $pos + 1);

                $line = trim($line);
                if ($line === '' || !str_starts_with($line, 'data:')) {
                    continue;
                }

                $data = trim(substr($line, 5));
                if ($data === '[DONE]') {
                    return;
                }

                $decoded = json_decode($data, true);
                if (is_array($decoded)) {
                    $onData($decoded);
                }
            }
        }
    }

    /**
     * @param array<string, mixed> $options
     */
    private function requestWithRetries(
        string $method,
        string $path,
        array $options,
    ): ResponseInterface {
        $client = $this->getHttpClient();
        $attempts = 0;
        $lastException = null;

        while ($attempts <= $this->config->maxRetries) {
            try {
                $response = $client->request($method, $path, $options);

                if ($response->getStatusCode() >= 500 && $attempts < $this->config->maxRetries) {
                    $attempts++;
                    usleep(200_000 * $attempts);
                    continue;
                }

                if ($response->getStatusCode() >= 400) {
                    $this->throwForStatus($response);
                }

                return $response;
            } catch (ConnectException $e) {
                $lastException = $e;
                $attempts++;
                if ($attempts > $this->config->maxRetries) {
                    break;
                }
                usleep(200_000 * $attempts);
            }
        }

        throw new ConnectionException(
            'Could not connect to N-ATLAS gateway after retries: ' . ($lastException?->getMessage() ?? 'unknown'),
            previous: $lastException
        );
    }

    private function throwForStatus(ResponseInterface $response): void
    {
        $status = $response->getStatusCode();
        $body = (string) $response->getBody();
        $message = $body !== '' ? $body : "HTTP {$status}";

        match (true) {
            $status === 401 || $status === 403 => throw new AuthenticationException(
                "N-ATLAS gateway rejected the API key (HTTP {$status}): {$message}"
            ),
            default => throw new NAtlasException(
                "N-ATLAS gateway error (HTTP {$status}): {$message}"
            ),
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeJson(ResponseInterface $response): array
    {
        $body = (string) $response->getBody();

        if ($body === '') {
            return [];
        }

        try {
            return json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new NAtlasException(
                'Invalid JSON from N-ATLAS gateway: ' . $e->getMessage(),
                previous: $e
            );
        }
    }
}