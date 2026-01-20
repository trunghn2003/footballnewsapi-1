<?php

use Illuminate\Support\Facades\Http;

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$apiKey = env('SOFASCORE_API_KEY', '3ffcbe8639mshed1c7dc03a94db6p16d136jsn775d46322204');

// 1. Search for a team to get an ID
echo "Searching for Arsenal...\n";
$searchUrl = "https://sofascore.p.rapidapi.com/teams/search?name=Arsenal";
try {
    $response = Http::withHeaders([
        'x-rapidapi-host' => "sofascore.p.rapidapi.com",
        "x-rapidapi-key" => $apiKey
    ])->get($searchUrl);

    if ($response->successful()) {
        $data = $response->json();
        $teamId = $data['teams'][0]['id'] ?? null;
        echo "Found Team ID: $teamId\n";

        if ($teamId) {
            // 2. Test Squad Endpoint options
            $endpoints = [
                "teams/get-squad?teamId={$teamId}",
                "team/get-players?teamId={$teamId}",
                "team/players?teamId={$teamId}"
            ];

            foreach ($endpoints as $ep) {
                echo "Testing: $ep\n";
                $resp = Http::withHeaders([
                    'x-rapidapi-host' => "sofascore.p.rapidapi.com",
                    "x-rapidapi-key" => $apiKey
                ])->get("https://sofascore.p.rapidapi.com/" . $ep);

                echo "Status: " . $resp->status() . "\n";
                if ($resp->successful()) {
                    echo "Response: " . substr($resp->body(), 0, 500) . "\n";
                }
            }
        }
    } else {
        echo "Search failed.\n";
    }
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
