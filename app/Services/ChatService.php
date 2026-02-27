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
            // Call Python RAG Service
            $response = \Illuminate\Support\Facades\Http::post('http://127.0.0.1:8001/chat', [
                'message' => $message,
            ]);

            if ($response->successful()) {
                $data = $response->json();
                return [
                    'success' => true,
                    'message' => $data['response'],
                    'intent' => $data['intent'] ?? 'rag_search',
                    'context_used' => !empty($data['rag_context'])
                ];
            } else {
                Log::error("Python Service Error: " . $response->body());
                return [
                    'success' => false,
                    'message' => "My brain is currently offline (Python Service Error).",
                    'error' => $response->body()
                ];
            }
        } catch (\Exception $e) {
            Log::error("ChatService Error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => "Sorry, I encountered an internal error.",
                'error' => $e->getMessage()
            ];
        }
    }
}
