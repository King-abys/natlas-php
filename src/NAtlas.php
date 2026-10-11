<?php

declare(strict_types=1);

namespace NAtlas;

/**
 * Static facade for quick SDK initialization.
 *
 * Usage:
 *   $client = NAtlas::create();
 *   $reply = $client->chat('Sannu!', Language::Hausa);
 */
final class NAtlas
{
    public static function create(?Config $config = null): NAtlasClient
    {
        return new NAtlasClient($config ?? Config::fromEnvironment());
    }
}