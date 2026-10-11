<?php

require __DIR__ . '/../vendor/autoload.php';

use NAtlas\NAtlas;
use NAtlas\Models\Language;

$client = NAtlas::create();

$client->chatStream(
    messages: 'Sannu! Yaya kake?',
    onChunk: function ($chunk) {
        echo $chunk->delta;
        flush();
    },
    language: Language::Hausa,
);

echo PHP_EOL;