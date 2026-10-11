<?php

require __DIR__ . '/../vendor/autoload.php';

use NAtlas\NAtlas;
use NAtlas\Models\Language;

$client = NAtlas::create();

$result = $client->voiceChat(__DIR__ . '/voice_note.ogg', Language::Igbo);

echo "You said: {$result['transcript']->text}" . PHP_EOL;
echo "N-ATLAS: {$result['reply']->content}" . PHP_EOL;