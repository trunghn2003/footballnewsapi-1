<?php

use Illuminate\Support\Facades\Http;

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$apiKey = env('SOFASCORE_API_KEY', '3ffcbe8639mshed1c7dc03a94db6p16d136jsn775d46322204');
$teamId = 42; // Arsenal

$endpoints = [
    "teams/get-schedule?teamId={$teamId}",
    "teams/get-events?teamId={$teamId}",
    "teams/events?teamId={$teamId}",
    "team/events?teamId={$teamId}",
    "teams/get-next-matches?teamId={$teamId}",
    "teams/get-last-matches?teamId={$teamId}",
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
            // //dd($response->json());
        } else {
            echo "Failed.\n";
        }
    } catch (\Exception $e) {
        echo "Error: " . $e->getMessage() . "\n";
    }
    echo "--------------------------\n";
}
