<?php

declare(strict_types=1);

namespace NAtlas;

use NAtlas\Concerns\MakesHttpRequests;
use NAtlas\Exceptions\NAtlasException;
use NAtlas\Models\ChatMessage;
use NAtlas\Models\ChatResult;
use NAtlas\Models\Language;
use NAtlas\Models\StreamChunk;
use NAtlas\Models\TranscriptionResult;

/**
 * PHP client for N-ATLAS — Nigeria's sovereign multilingual LLM.
 *
 * Supports:
 *  - Chat completions (sync + streaming)
 *  - Speech-to-text transcription (Hausa, Igbo, Yoruba, Nigerian English)
 *  - Translation between supported languages
 *  - Voice chat (transcribe + reply in one call)
 *
 * @see https://huggingface.co/NCAIR1/N-ATLaS
 */
final class NAtlasClient
{
    use MakesHttpRequests;

    private const MAX_HISTORY_MESSAGES = 50;

    public function __construct(
        private readonly Config $config,
    ) {
    }

    /**
     * Send a chat message and get a response.
     *
     * @param string|array<ChatMessage|array{role: string, content: string}> $messages
     *        A single string, a ChatMessage, or an array of messages.
     * @param Language|string $language Target language hint (ha, ig, yo, en).
     * @param float $temperature Sampling temperature (0.0–2.0).
     * @param int $maxTokens Maximum tokens to generate.
     * @param float $topP Nucleus sampling.
     * @param string|null $systemPrompt Optional system prompt.
     * @return ChatResult
     */
    public function chat(
        string|array|ChatMessage $messages,
        Language|string $language = Language::English,
        float $temperature = 0.7,
        int $maxTokens = 1024,
        float $topP = 0.9,
        ?string $systemPrompt = null,
    ): ChatResult {
        $lang = Language::resolve($language);
        $normalized = $this->normalizeMessages($messages, $systemPrompt);

        $payload = [
            'model' => $this->config->model,
            'messages' => $normalized,
            'temperature' => $temperature,
            'max_tokens' => $maxTokens,
            'top_p' => $topP,
            'stream' => false,
            'metadata' => ['language' => $lang->value],
        ];

        $response = $this->postJson('/chat/completions', $payload);

        if (!isset($response['choices'][0]['message']['content'])) {
            throw new NAtlasException('Unexpected response shape from N-ATLAS gateway.');
        }

        return new ChatResult(
            content: $response['choices'][0]['message']['content'],
            finishReason: $response['choices'][0]['finish_reason'] ?? 'stop',
            usage: $response['usage'] ?? [],
            language: $lang,
        );
    }

    /**
     * Stream a chat response token by token.
     *
     * @param string|array|ChatMessage $messages
     * @param callable(StreamChunk): void $onChunk Callback invoked for each token.
     */
    public function chatStream(
        string|array|ChatMessage $messages,
        callable $onChunk,
        Language|string $language = Language::English,
        float $temperature = 0.7,
        int $maxTokens = 1024,
        float $topP = 0.9,
        ?string $systemPrompt = null,
    ): void {
        $lang = Language::resolve($language);
        $normalized = $this->normalizeMessages($messages, $systemPrompt);

        $payload = [
            'model' => $this->config->model,
            'messages' => $normalized,
            'temperature' => $temperature,
            'max_tokens' => $maxTokens,
            'top_p' => $topP,
            'stream' => true,
            'metadata' => ['language' => $lang->value],
        ];

        $this->streamJson('/chat/completions', $payload, function (array $data) use ($onChunk, $lang) {
            $delta = $data['choices'][0]['delta']['content'] ?? '';
            if ($delta !== '') {
                $onChunk(new StreamChunk(
                    delta: $delta,
                    finishReason: $data['choices'][0]['finish_reason'] ?? null,
                    language: $lang,
                ));
            }
        });
    }

    /**
     * Transcribe an audio file to text using N-ATLAS ASR.
     *
     * @param string $audioPath Path to audio file (ogg, mp3, wav, m4a).
     * @param Language|string $language Source language.
     * @return TranscriptionResult
     */
    public function transcribe(
        string $audioPath,
        Language|string $language,
    ): TranscriptionResult {
        $lang = Language::resolve($language);

        if (!file_exists($audioPath)) {
            throw new Exceptions\ValidationException("Audio file not found: {$audioPath}");
        }

        $response = $this->postMultipart('/audio/transcriptions', [
            'file' => new \CURLFile($audioPath),
            'model' => 'whisper-1',
            'language' => $lang->value,
        ]);

        return new TranscriptionResult(
            text: $response['text'] ?? '',
            language: $lang,
        );
    }

    /**
     * Translate text between supported languages.
     *
     * Uses N-ATLAS chat with a structured prompt. N-ATLAS does not expose a
     * dedicated translation endpoint, so this wraps the LLM.
     */
    public function translate(
        string $text,
        Language|string $from,
        Language|string $to,
    ): string {
        $fromLang = Language::resolve($from);
        $toLang = Language::resolve($to);

        $prompt = sprintf(
            'Translate the following text from %s to %s. Return only the translation, no explanations or preamble: "%s"',
            $fromLang->label(),
            $toLang->label(),
            $text
        );

        $result = $this->chat(
            messages: $prompt,
            language: $toLang,
            temperature: 0.1,
            maxTokens: 2048,
        );

        return trim($result->content);
    }

    /**
     * Voice chat: transcribe audio then generate a reply in one call.
     *
     * This is the flagship DX method — one call for voice-first applications.
     *
     * @param string $audioPath Path to audio file.
     * @param Language|string $language Language for both transcription and reply.
     * @return array{transcript: TranscriptionResult, reply: ChatResult}
     */
    public function voiceChat(
        string $audioPath,
        Language|string $language = Language::Hausa,
    ): array {
        $transcript = $this->transcribe($audioPath, $language);

        $reply = $this->chat(
            messages: $transcript->text,
            language: $language,
        );

        return [
            'transcript' => $transcript,
            'reply' => $reply,
        ];
    }

    /**
     * Check gateway health.
     *
     * @return array{ok: bool, status: int, body: array}
     */
    public function health(): array
    {
        return $this->getJson('/health', allowErrorStatus: true);
    }

    /**
     * List available models on the gateway.
     */
    public function listModels(): array
    {
        return $this->getJson('/v1/models');
    }

    /**
     * @param string|array|ChatMessage $messages
     * @return array<int, array{role: string, content: string}>
     */
    private function normalizeMessages(
        string|array|ChatMessage $messages,
        ?string $systemPrompt,
    ): array {
        $normalized = [];

        if ($systemPrompt !== null) {
            $normalized[] = ['role' => 'system', 'content' => $systemPrompt];
        }

        if (is_string($messages)) {
            $normalized[] = ['role' => 'user', 'content' => $messages];
            return $normalized;
        }

        if ($messages instanceof ChatMessage) {
            $normalized[] = $messages->toArray();
            return $normalized;
        }

        foreach ($messages as $msg) {
            if ($msg instanceof ChatMessage) {
                $normalized[] = $msg->toArray();
            } elseif (is_array($msg) && isset($msg['role'], $msg['content'])) {
                $normalized[] = ['role' => $msg['role'], 'content' => $msg['content']];
            } else {
                throw new Exceptions\ValidationException(
                    'Each message must be a ChatMessage or an array with role and content keys.'
                );
            }
        }

        // Enforce context window safety.
        if (count($normalized) > self::MAX_HISTORY_MESSAGES) {
            $normalized = array_slice($normalized, -self::MAX_HISTORY_MESSAGES);
        }

        return $normalized;
    }
}