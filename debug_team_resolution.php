<?php

require __DIR__ . '/vendor/autoload.php';

use Illuminate\Support\Facades\Http;

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$apiKey = env('SOFASCORE_API_KEY', '3ffcbe8639mshed1c7dc03a94db6p16d136jsn775d46322204');
$fixtureId = '13511930';
$teamName = 'Feyenoord Rotterdam';

echo "Debugging resolution for $teamName using fixture $fixtureId...\n";

// 1. Test get-lineups
echo "1. Testing matches/get-lineups...\n";
$response = Http::withHeaders([
    'x-rapidapi-host' => "sofascore.p.rapidapi.com",
    "x-rapidapi-key" => $apiKey
])->get("https://sofascore.p.rapidapi.com/matches/get-lineups", [
    'matchId' => $fixtureId
]);

echo "Status: " . $response->status() . "\n";
if ($response->successful()) {
    $data = $response->json();
    echo "Home Team: " . ($data['home']['team']['name'] ?? 'N/A') . " (ID: " . ($data['home']['team']['id'] ?? 'N/A') . ")\n";
    echo "Away Team: " . ($data['away']['team']['name'] ?? 'N/A') . " (ID: " . ($data['away']['team']['id'] ?? 'N/A') . ")\n";

    // Check structure. Maybe it's data['home']['id'] or data['home']['team']['id']?
    // In FixtureService we used data['home']['players']...
    // Let's print keys of 'home'
    if (isset($data['home'])) {
        print_r(array_keys($data['home']));
    }
} else {
    echo "Response: " . $response->body() . "\n";
}

// 2. Test Search
echo "\n2. Testing teams/search...\n";
$response = Http::withHeaders([
    'x-rapidapi-host' => "sofascore.p.rapidapi.com",
    "x-rapidapi-key" => $apiKey
])->get("https://sofascore.p.rapidapi.com/teams/search", [
    'name' => $teamName
]);
echo "Status: " . $response->status() . "\n";
if ($response->successful()) {
    $data = $response->json();
    if (!empty($data['teams'])) {
        foreach ($data['teams'] as $team) {
            echo "Found: " . $team['name'] . " (ID: " . $team['id'] . ")\n";
        }
    } else {
        echo "No teams found.\n";
    }
} else {
    echo "Response: " . $response->body() . "\n";
}
