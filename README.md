# N-ATLAS PHP SDK

PHP client for [N-ATLAS](https://huggingface.co/NCAIR1/N-ATLaS) — Nigeria's sovereign multilingual LLM supporting **Hausa**, **Igbo**, **Yorùbá** and **Nigerian-accented English**.

Built for the **NITDA National AI Innovation Challenge 2026**, Developer Infrastructure track.

## Why PHP?

PHP powers the majority of Nigerian web infrastructure — WordPress, Laravel, and agency-built applications. This SDK lets PHP developers integrate N-ATLAS chat, speech-to-text, and voice pipelines **without spinning up a Python microservice**.

## Installation

```bash
composer require king-abys/natlas-php
```

## Self-Hosting the Gateway

>  **There is no public N-ATLAS API.** You must run a gateway that serves the model behind an OpenAI-compatible endpoint.

The SDK talks to any OpenAI-compatible `/v1` endpoint. Recommended options:

- **Modal** (recommended, scales to zero) — see the [N-ATLAS Kit gateway](https://github.com/Kambah123/N-ATLAS-Kit)
- **vLLM** — `vllm serve NCAIR1/N-ATLaS`
- **Ollama** (via GGUF) — see community quantisations
- **LM Studio** — load a GGUF build and start the local server

## Quickstart

```php
use NAtlas\NAtlas;
use NAtlas\Models\Language;

$client = NAtlas::create();

$reply = $client->chat(
    messages: 'Menene ake nufi da gwagwarmaya?',
    language: Language::Hausa,
);

echo $reply->content;
```

## Configuration

Copy `.env.example` to `.env` and set:

```env
NATLAS_BASE_URL=https://your-gateway.modal.run
NATLAS_API_KEY=your_key_here
NATLAS_MODEL=NCAIR1/N-ATLaS
```

Or pass a `Config` object directly:

```php
$client = NAtlas::create(new Config(
    baseUrl: 'http://localhost:8080',
    apiKey: 'optional',
));
```

## Features

| Method | Description |
|--------|-------------|
| `chat()` | Synchronous chat completion |
| `chatStream()` | Streaming chat with token-by-token callback |
| `transcribe()` | Speech-to-text (Hausa, Igbo, Yoruba, English) |
| `translate()` | Translation between supported languages |
| `voiceChat()` | Transcribe + reply in one call |
| `health()` | Gateway liveness check |
| `listModels()` | List available models |

### Streaming

```php
$client->chatStream(
    messages: 'Sannu!',
    onChunk: fn ($chunk) => print($chunk->delta),
    language: Language::Hausa,
);
```

### Voice Chat (One Call)

```php
$result = $client->voiceChat('audio.ogg', Language::Hausa);

echo $result['transcript']->text;  // what was said
echo $result['reply']->content;    // N-ATLAS response
```

## Architecture

```
NAtlasClient
├── MakesHttpRequests (trait) → Guzzle, retries, SSE streaming
├── Config → env-driven, immutable
└── Models → ChatMessage, ChatResult, StreamChunk, Language, TranscriptionResult
```

## License

MIT — see [LICENSE](LICENSE).

---

**Attribution:** N-ATLaS is an initiative of the Federal Ministry of Communications, Innovation and Digital Economy, and powered by Awarri Technologies.
