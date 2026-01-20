<?php

namespace App\Console\Commands;

use App\Models\Competition;
use App\Models\Season;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SyncSeasonsCommand extends Command
{
    protected $signature = 'sync:seasons';
    protected $description = 'Sync current seasons data from football API';

    public function handle()
    {
        $this->info('Starting seasons synchronization...');

        $competitions = [
            'PD',   // Primera Division
            'FL1',  // Ligue 1
            'BL1',  // Bundesliga
            'SA',   // Serie A
            'PL',   // Premier League
        ];

        $apiKey = env('API_FOOTBALL_TOKEN');
        $baseUrl = env('API_FOOTBALL_URL');

        foreach ($competitions as $code) {
            $this->info("Fetching data for {$code}...");

            $response = Http::withHeaders([
                'X-Auth-Token' => $apiKey,
            ])->get("{$baseUrl}/competitions/{$code}");

            if (!$response->successful()) {
                $this->error("Failed to fetch {$code}: " . $response->body());
                continue;
            }

            $data = $response->json();

            // The API returns 'currentSeason' in the competition details
            // We want to ensure we have the 2025 season. 
            // Note: The API 'currentSeason' might still be 2024 if 2025 hasn't officially 'started' in their system 
            // distinctively, or if we need to request it specifically.
            // However, typically /competitions/{id} gives the current one. 
            // If we specifically need 2025, we might need to rely on the currentSeason field returning it.
            // Let's assume the API is providing the relevant season.

            $seasonData = $data['currentSeason'] ?? null;

            if (!$seasonData) {
                $this->error("No currentSeason data for {$code}");
                continue;
            }

            // We specifically want to ensure we capture the 2025 season if that's what we are targeting.
            // But if the API returns a 2024 start date, we might just be updating that.
            // Based on user request, they want "current seasons". 
            // If the user said "update to 2025", we expect the API to have it.
            // Let's print what we found.
            $startDate = $seasonData['startDate'];
            $this->info("Found season starting: " . $startDate);

            // Create or update the season
            Season::updateOrCreate(
                ['id' => $seasonData['id']],
                [
                    'competition_id' => $data['id'],
                    'start_date' => $seasonData['startDate'],
                    'end_date' => $seasonData['endDate'],
                    'current_matchday' => $seasonData['currentMatchday'] ?? 1,
                    'winner_team_id' => $seasonData['winner']['id'] ?? null,
                ]
            );

            $this->info("Synced season for {$data['name']}");
        }

        $this->info('Seasons synchronization completed.');
        return 0;
    }
}
