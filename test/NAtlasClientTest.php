<?php

declare(strict_types=1);

namespace NAtlas\Tests;

use NAtlas\Config;
use NAtlas\Exceptions\ValidationException;
use NAtlas\Models\ChatMessage;
use NAtlas\Models\Language;
use PHPUnit\Framework\TestCase;

final class NAtlasClientTest extends TestCase
{
    public function test_language_resolves_codes_and_aliases(): void
    {
        $this->assertSame(Language::Hausa, Language::resolve('ha'));
        $this->assertSame(Language::Hausa, Language::resolve('HAUSA'));
        $this->assertSame(Language::Yoruba, Language::resolve('yorùbá'));
        $this->assertSame(Language::English, Language::resolve('nigerian-english'));
    }

    public function test_unsupported_language_throws(): void
    {
        $this->expectException(ValidationException::class);
        Language::resolve('fr');
    }

    public function test_config_rejects_empty_base_url(): void
    {
        $this->expectException(ValidationException::class);
        new Config(baseUrl: '');
    }

    public function test_config_api_base_appends_v1(): void
    {
        $config = new Config(baseUrl: 'http://localhost:8080');
        $this->assertSame('http://localhost:8080/v1', $config->apiBase());
        $this->assertSame('http://localhost:8080/health', $config->healthUrl());
    }

    public function test_chat_message_factories(): void
    {
        $this->assertSame(['role' => 'user', 'content' => 'hi'], ChatMessage::user('hi')->toArray());
        $this->assertSame(['role' => 'system', 'content' => 'be nice'], ChatMessage::system('be nice')->toArray());
    }
}