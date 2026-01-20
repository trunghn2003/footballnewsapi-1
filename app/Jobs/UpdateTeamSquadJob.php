<?php

namespace App\Jobs;

use App\Models\Team;
use App\Models\Person;
use App\Models\LineupPlayer;
use App\Models\Fixture;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class UpdateTeamSquadJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private ?int $teamId;

    /**
     * Create a new job instance.
     *
     * @param int|null $teamId specific team ID to update, or null for logic handling
     * @return void
     */
    public function __construct(?int $teamId = null)
    {
        $this->teamId = $teamId;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        if ($this->teamId) {
            $team = Team::find($this->teamId);
            if ($team) {
                $this->updateTeamSquad($team);
            }
        }
    }

    private function updateTeamSquad(Team $team)
    {
        $apiKey = env('SOFASCORE_API_KEY', '3ffcbe8639mshed1c7dc03a94db6p16d136jsn775d46322204');
        $sofascoreId = $team->sofascore_id;

        // 1. Try to find Sofascore ID if missing
        if (!$sofascoreId) {
            Log::info("Team {$team->name} (ID: {$team->id}) has no sofascore_id. Attempting to resolve...");

            // Try via Fixtures
            $fixture = Fixture::where(function ($q) use ($team) {
                $q->where('home_team_id', $team->id)
                    ->orWhere('away_team_id', $team->id);
            })
                ->whereNotNull('id_fixture')
                ->latest('utc_date')
                ->first();

            if ($fixture) {
                Log::info("Found associated fixture: {$fixture->id_fixture}");
                // Fetch match details to get team IDs
                try {
                    $response = Http::withHeaders([
                        'x-rapidapi-host' => "sofascore.p.rapidapi.com",
                        "x-rapidapi-key" => $apiKey
                    ])->get("https://sofascore.p.rapidapi.com/matches/get-incidents", [
                        'matchId' => $fixture->id_fixture
                    ]);

                    if ($response->successful()) {
                        $data = $response->json();
                        // Usually get-incidents response structure includes 'homeTeam' and 'awayTeam' objects at root or in match details?
                        // Wait, get-incidents might NOT return team metadata in root. 
                        // Let's use 'matches/detail' or just rely on the structure if we know it.
                        // Actually, 'matches/get-lineups' definitely has it in 'home' / 'away' keys but strictly lineups.
                        // Safe bet: "can we search team by name?"
                        // Or use 'matches/detail' if it exists. 
                        // Let's assume we can use the lineups endpoint as it definitely has the team ID in 'home.id' / 'away.id' 
                        // BUT get-incidents was used in FixtureService.
                        // Let's try searching by name if fixture lookup fails complexity.

                        // Revised strategy for ID resolution: Search by Name.
                        // Search is safer if we match name closely or just grab the first result if name is unique enough.
                        // But wait, the fixture path is best.
                        // Let's call get-lineups for that fixture.
                        $lineupResponse = Http::withHeaders([
                            'x-rapidapi-host' => "sofascore.p.rapidapi.com",
                            "x-rapidapi-key" => $apiKey
                        ])->get("https://sofascore.p.rapidapi.com/matches/get-lineups", [
                            'matchId' => $fixture->id_fixture
                        ]);

                        if ($lineupResponse->successful()) {
                            $lData = $lineupResponse->json();
                            // Check home or away
                            if ($fixture->home_team_id == $team->id && isset($lData['home']['id'])) {
                                $sofascoreId = $lData['home']['id'];
                            } elseif ($fixture->away_team_id == $team->id && isset($lData['away']['id'])) {
                                $sofascoreId = $lData['away']['id'];
                            }
                        }
                    }
                } catch (\Exception $e) {
                    Log::error("Failed to resolve Sofascore ID from fixture: " . $e->getMessage());
                }
            }

            // If still no ID, try Search
            if (!$sofascoreId) {
                try {
                    $searchUrl = "https://sofascore.p.rapidapi.com/teams/search";
                    $response = Http::withHeaders([
                        'x-rapidapi-host' => "sofascore.p.rapidapi.com",
                        "x-rapidapi-key" => $apiKey
                    ])->get($searchUrl, ['name' => $team->name]);

                    if ($response->successful()) {
                        $data = $response->json();
                        if (!empty($data['teams'][0]['id'])) {
                            $sofascoreId = $data['teams'][0]['id'];
                            Log::info("Resolved Sofascore ID for {$team->name} via Search: $sofascoreId");
                        }
                    }
                } catch (\Exception $e) {
                    Log::error("Failed to resolve Sofascore ID via Search: " . $e->getMessage());
                }
            }

            if ($sofascoreId) {
                $team->sofascore_id = $sofascoreId;
                $team->save();
            } else {
                Log::warning("Could not resolve Sofascore ID for team: {$team->name}");
                return;
            }
        }

        // 2. Fetch Squad
        Log::info("Fetching squad for Team {$team->name} (Sofascore ID: $sofascoreId)");
        try {
            $response = Http::withHeaders([
                'x-rapidapi-host' => "sofascore.p.rapidapi.com",
                "x-rapidapi-key" => $apiKey
            ])->get("https://sofascore.p.rapidapi.com/teams/get-squad", [
                'teamId' => $sofascoreId
            ]);

            if ($response->successful()) {
                $data = $response->json();
                if (isset($data['players'])) {
                    $this->saveSquad($team, $data['players']);
                }
            } else {
                Log::error("Failed to fetch squad: " . $response->status());
                Log::error("Response: " . $response->body());
            }
        } catch (\Exception $e) {
            Log::error("Exception fetching squad: " . $e->getMessage());
        }
    }

    private function saveSquad(Team $team, array $playersData)
    {
        DB::beginTransaction();
        try {
            // We can detach all players first or sync. 
            // PersonTeam is a pivot. 
            // $team->players()->sync(...) works if we have the IDs.
            // But we need to update Person table first.

            $personIds = [];

            foreach ($playersData as $item) {
                $pData = $item['player'];

                // If ID is missing, skip
                if (!isset($pData['id'])) continue;

                $sofascorePlayerId = $pData['id'];

                // Create or Update Person
                $person = Person::updateOrCreate(
                    ['id' => $sofascorePlayerId],
                    [
                        'name' => $pData['name'],
                        'short_name' => $pData['shortName'] ?? null,
                        'position' => $pData['position'] ?? null,
                        'shirt_number' => $pData['jerseyNumber'] ?? null,
                        'height' => $pData['height'] ?? null,
                        'preferred_foot' => $pData['preferredFoot'] ?? null,
                        'nationality' => $pData['country']['name'] ?? null,
                        'date_of_birth' => isset($pData['dateOfBirthTimestamp']) ? date('Y-m-d', $pData['dateOfBirthTimestamp']) : null,
                        'last_updated' => now()
                    ]
                );

                $personIds[] = $person->id;

                // We should also make sure the pivot 'person_team' exists.
                // Syncing at the end is cleaner, but we might want to preserve history?
                // The current structure seems to rely on 'person_team' table.
            }

            // Sync players to team
            // Assuming the pivot table has no extra critical data we'd lose by syncing (like contract dates etc which aren't in this API)
            // But if we have other data, we should be careful. 
            // TeamService uses $this->personRepository->syncPerson which does:
            // $person->teams()->syncWithoutDetaching([$teamId]);
            // Here we want to update the CURRENT squad.
            // So we should probably sync (which detaches others).

            $team->players()->sync($personIds);

            DB::commit();
            Log::info("Squad updated for team {$team->name}. " . count($personIds) . " players.");
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Error saving squad: " . $e->getMessage());
        }
    }
}
