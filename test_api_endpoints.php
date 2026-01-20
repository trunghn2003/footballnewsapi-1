<?php

use Illuminate\Support\Facades\Http;

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$apiKey = env('SOFASCORE_API_KEY', '3ffcbe8639mshed1c7dc03a94db6p16d136jsn775d46322204');
$date = '2025-08-23';
$fixtureId = '12437005'; // Known working ID from previous steps (497681 mapped to 12437005)

$endpoints = [
    // Control test
    "matches/get-incidents?matchId={$fixtureId}",

    // Search tests
    "teams/search?name=Arsenal",
    "team/search?name=Arsenal",
    "search/teams?name=Arsenal",
    "teams/list?name=Arsenal",

    // Date lists again with variations
    "matches/list-by-date?date={$date}",
    "v1/events/schedule/date?date={$date}",
];

foreach ($endpoints as $ep) {
    echo "Testing endpoint: $ep\n";
    $url = "https://sofascore.p.rapidapi.com/" . $ep;
    try {
        $response = Http::withHeaders([
            'x-rapidapi-host' => "sofascore.p.rapidapi.com",
            "x-rapidapi-key" => $apiKey
        ])->get($url);

        echo "Status: " . $response->status() . "\n";
        if ($response->successful()) {
            echo "SUCCESS! Response: " . substr($response->body(), 0, 200) . "...\n";
        } else {
            echo "Failed.\n";
        }
    } catch (\Exception $e) {
        echo "Error: " . $e->getMessage() . "\n";
    }
    echo "--------------------------\n";
}
