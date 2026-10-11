<?php

require __DIR__ . '/../vendor/autoload.php';

use NAtlas\NAtlas;
use NAtlas\Models\Language;

$client = NAtlas::create();

$result = $client->transcribe(__DIR__ . '/sample.wav', Language::Yoruba);

echo "Transcription: {$result->text}" . PHP_EOL;