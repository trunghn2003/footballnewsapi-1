<?php

namespace App\Console\Commands;

use App\Models\Fixture;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Carbon\Carbon;

class MapSofascoreFixtureIdsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'fixture:map-ids {--limit=10} {--days=60}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Map local fixtures to Sofascore IDs by searching teams and matching dates';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $limit = $this->option('limit');
        $days = $this->option('days');
        $startDate = now()->subDays($days);

        $this->info("Looking for fixtures without Sofascore ID since $startDate...");

        $fixtures = Fixture::whereNull('id_fixture')
            ->where('utc_date', '>=', $startDate)
            ->where('status', 'FINISHED')
            ->with(['homeTeam', 'awayTeam'])
            ->orderBy('utc_date', 'desc')
            ->limit($limit)
            ->get();

        $this->info("Found " . $fixtures->count() . " fixtures to map.");

        $apiKey = env('SOFASCORE_API_KEY', '3ffcbe8639mshed1c7dc03a94db6p16d136jsn775d46322204');
        $headers = [
            'x-rapidapi-host' => "sofascore.p.rapidapi.com",
            "x-rapidapi-key" => $apiKey
        ];

        foreach ($fixtures as $fixture) {
            $homeName = $fixture->homeTeam->name ?? 'Unknown';
            $awayName = $fixture->awayTeam->name ?? 'Unknown';
            $this->info("Processing: {$homeName} vs {$awayName} ({$fixture->utc_date->format('Y-m-d')})");

            if ($homeName === 'Unknown') {
                $this->warn("Skipping: No home team found for fixture {$fixture->id}");
                continue;
            }

            // 1. Search for Home Team
            $teamName = $homeName;

            // Simple sanitation?
            // $teamName = Str::slug($teamName, ' '); 

            $searchUrl = "https://sofascore.p.rapidapi.com/teams/search";
            try {
                $response = Http::withHeaders($headers)->get($searchUrl, ['name' => $teamName]);

                if (!$response->successful()) {
                    $this->error("Search failed for $teamName");
                    continue;
                }

                $teams = $response->json('teams');
                if (empty($teams)) {
                    $this->warn("No teams found for $teamName");
                    continue;
                }

                // Take first likely match or filter
                // Ideally, check country/sport, but for now take first match
                $sofascoreTeamId = $teams[0]['id'];
                $this->info("Found Team: {$teams[0]['name']} (ID: $sofascoreTeamId)");

                // 2. Get Matches for Team
                // Try 'get-last-matches' for finished
                $matchesUrl = "https://sofascore.p.rapidapi.com/teams/get-last-matches";
                // If scheduled, use get-next-matches. But we filtered by FINISHED.

                $matchesResponse = Http::withHeaders($headers)->get($matchesUrl, ['teamId' => $sofascoreTeamId]);

                if (!$matchesResponse->successful()) {
                    $this->error("Could not fetch matches for team $sofascoreTeamId");
                    continue;
                }

                $events = $matchesResponse->json('events');
                if (empty($events)) {
                    $this->warn("No recent matches found for team ID $sofascoreTeamId");
                    continue;
                }

                // 3. Match with Fixture Date
                $foundId = null;
                $fixtureDate = $fixture->utc_date->format('Y-m-d');
                $this->info("   > Target Date: $fixtureDate");

                foreach ($events as $event) {
                    // Check date (startTimestamp is unix)
                    if (isset($event['startTimestamp'])) {
                        $eventDateObj = Carbon::createFromTimestamp($event['startTimestamp'], 'UTC');
                        $eventDate = $eventDateObj->format('Y-m-d');

                        // Debug output (verbose only?)
                        // $this->line("   - Checking event: $eventDate vs $fixtureDate");

                        // Allow +/- 1 day match due to timezone diffs
                        if ($eventDate == $fixtureDate || $eventDateObj->diffInDays($fixture->utc_date) <= 1) {
                            $foundId = $event['id'];
                            $this->info("MATCH FOUND! ID: $foundId ({$event['homeTeam']['name']} vs {$event['awayTeam']['name']} on $eventDate)");
                            break;
                        }
                    }
                }

                if ($foundId) {
                    $fixture->id_fixture = $foundId;
                    $fixture->save();
                    $this->info("Updated fixture {$fixture->id} with Sofascore ID $foundId");
                } else {
                    $this->warn("No match found near date $fixtureDate for team $teamName. Last event date: " . (isset($eventDate) ? $eventDate : 'N/A'));
                }

                // Rate limiting
                sleep(1);
            } catch (\Exception $e) {
                $this->error("Error: " . $e->getMessage());
            }
        }

        return 0;
    }
}
