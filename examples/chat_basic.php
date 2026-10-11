<?php

require __DIR__ . '/../vendor/autoload.php';

use NAtlas\NAtlas;
use NAtlas\Models\Language;

$client = NAtlas::create();

$reply = $client->chat(
    messages: 'Menene ake nufi da gwagwarmaya?',
    language: Language::Hausa,
);

echo $reply->content . PHP_EOL;