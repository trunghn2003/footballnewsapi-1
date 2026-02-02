<?php

namespace App\Services;

use App\Repositories\FixtureRepository;
use App\Repositories\StandingRepository;
use App\Repositories\TeamRepository;
use Illuminate\Support\Facades\Log;

class ChatService
{
    public function __construct(
        private GeminiService $geminiService,
        private TeamRepository $teamRepository,
        private FixtureRepository $fixtureRepository,
        private StandingRepository $standingRepository
    ) {}

    /**
     * Handle a user message
     * 
     * @param string $message
     * @return array
     */
    public function handleMessage(string $message): array
    {
        try {
            // 1. Extract Intent
            $intentData = $this->geminiService->extractIntent($message);
            $intent = $intentData['intent'];
            $entities = $intentData['entities'];

            Log::info("Chat Intent: $intent", $entities);

            // 2. Data Retrieval based on Intent
            $context = "";

            switch ($intent) {
                case 'fixture_schedule':
                case 'match_result':
                    $teamName = $entities['team'] ?? null;
                    if ($teamName) {
                        $team = $this->teamRepository->findByName($teamName);
                        // dd($team, $teamName);
                        if ($team) {
                            $status = ($intent === 'match_result') ? 'FINISHED' : 'SCHEDULED';
                            $fixtures = $this->fixtureRepository->getFixtures([
                                'teamId' => $team->id,
                                'status' => $status,
                                'recently' => 1 // simplified, maybe adjust
                            ], 5, 1);
                            $context = "Recent/Upcoming fixtures for {$team->name}:\n";
                            foreach ($fixtures->items() as $fixture) {
                                $context .= "{$fixture->utc_date}: {$fixture->homeTeam->name} {$fixture->full_time_home_score} - {$fixture->full_time_away_score} {$fixture->awayTeam->name} (Status: {$fixture->status})\n";
                            }
                            // dd($context);
                        } else {
                            $context = "Could not find team named '$teamName'.";
                        }
                    }
                    break;

                case 'standings':
                    $teamName = $entities['team'] ?? null;
                    if ($teamName) {
                        $team = $this->teamRepository->findByName($teamName);
                        if ($team) {
                            // Logic to get standings for team's competition
                            // This depends on how StandingRepository works. 
                            // Assuming we can search team's standing or general standing.
                            // For simplicity, let's say we just pass team info for now or empty if complex.
                            // In real impl, get competition ID from team, then get standings.
                            $context = "Asked for standings of $teamName. (Specific standing retrieval simplified for prototype)";
                        }
                    }
                    break;

                case 'prediction':
                    $context = "User asked for prediction. If a specific match is mentioned, use FixturePredictService logic here. For now, general prediction context.";
                    break;

                default:
                    // General chat, no specific DB data needed potentially
                    break;
            }

            // 3. Generate Answer
            $response = $this->geminiService->answerQuestion($message, $context);

            if (!$response['success']) {
                return [
                    'success' => false,
                    'message' => "I'm having trouble connecting to my brain right now.",
                    'error' => $response['error'] ?? 'Unknown error'
                ];
            }

            return [
                'success' => true,
                'message' => $response['answer'],
                'intent' => $intent,
                'context_used' => (bool) $context
            ];
        } catch (\Exception $e) {
            Log::error("ChatService Error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => "Sorry, I encountered an error extracting information.",
                'error' => $e->getMessage()
            ];
        }
    }
}
