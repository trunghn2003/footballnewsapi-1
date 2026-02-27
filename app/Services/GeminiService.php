<?php

namespace App\Services;

use Gemini\Data\GenerationConfig;
use Gemini\Enums\HarmBlockThreshold;
use Gemini\Data\SafetySetting;
use Gemini\Enums\HarmCategory;
use Gemini\Laravel\Facades\Gemini;
use Illuminate\Support\Facades\Log;
use Gemini\Enums\ModelType;

class GeminiService
{
    /**
     * Generate content using Gemini AI
     *
     * @param string $prompt The prompt to send to Gemini
     * @return array Response containing the generated content or error
     */
    public function generateContent(string $prompt): array
    {
        try {
            // Configure safety settings
            $safetySettingDangerousContent = new SafetySetting(
                category: HarmCategory::HARM_CATEGORY_DANGEROUS_CONTENT,
                threshold: HarmBlockThreshold::BLOCK_ONLY_HIGH
            );

            $safetySettingHateSpeech = new SafetySetting(
                category: HarmCategory::HARM_CATEGORY_HATE_SPEECH,
                threshold: HarmBlockThreshold::BLOCK_ONLY_HIGH
            );

            // Configure generation parameters
            $generationConfig = new GenerationConfig(
                maxOutputTokens: 1000,
                temperature: 0.7,
                topP: 0.8,
                topK: 40
            );


            $response = Gemini::generativeModel('models/gemini-2.0-flash')
                // ->withModel('models/gemini-2.0-flash-lite')
                ->withSafetySetting($safetySettingDangerousContent)
                ->withSafetySetting($safetySettingHateSpeech)
                ->withGenerationConfig($generationConfig)
                ->generateContent($prompt);


            $text = $response->text();

            // Extract win probabilities
            preg_match('/Home Win: (\d+)%/', $text, $homeWinMatches);
            preg_match('/Draw: (\d+)%/', $text, $drawMatches);
            preg_match('/Away Win: (\d+)%/', $text, $awayWinMatches);

            // Extract predicted score
            preg_match('/Predicted score: (\d+)-(\d+)/', $text, $scoreMatches);

            // Extract key factors
            preg_match('/Key factors influencing the prediction:(.*?)Confidence level:/s', $text, $factorsMatches);
            $factorsText = $factorsMatches[1] ?? '';
            $factors = [];
            preg_match_all('/- (.*?)(?:\n|$)/', $factorsText, $factorMatches);
            if (!empty($factorMatches[1])) {
                $factors = $factorMatches[1];
            }

            // Extract confidence level
            preg_match('/Confidence level: (\d+)%/', $text, $confidenceMatches);

            return [
                'success' => true,
                'win_probability' => [
                    'home' => (int)($homeWinMatches[1] ?? 0),
                    'draw' => (int)($drawMatches[1] ?? 0),
                    'away' => (int)($awayWinMatches[1] ?? 0)
                ],
                'predicted_score' => [
                    'home' => (int)($scoreMatches[1] ?? 0),
                    'away' => (int)($scoreMatches[2] ?? 0)
                ],
                'key_factors' => $factors,
                'confidence_level' => (int)($confidenceMatches[1] ?? 0),
                'raw_response' => $text
            ];
        } catch (\Exception $e) {
            Log::error("Gemini API error: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    /**
     * Answer a question based on provided context
     *
     * @param string $question The user's question
     * @param string $context The data/context to help answer the question
     * @return array Response containing the answer
     */
    public function answerQuestion(string $question, string $context = ''): array
    {
        try {
            // Configure safety settings (reusing exisiting ones or new ones)
            $safetySettingDangerousContent = new SafetySetting(
                category: HarmCategory::HARM_CATEGORY_DANGEROUS_CONTENT,
                threshold: HarmBlockThreshold::BLOCK_ONLY_HIGH
            );

            $safetySettingHateSpeech = new SafetySetting(
                category: HarmCategory::HARM_CATEGORY_HATE_SPEECH,
                threshold: HarmBlockThreshold::BLOCK_ONLY_HIGH
            );

            // Configure generation parameters for query answering which might need to be more creative or diverse
            $generationConfig = new GenerationConfig(
                maxOutputTokens: 500,
                temperature: 0.5, // Slightly lower temperature for more factual answers
                topP: 0.8,
                topK: 40
            );

            $prompt = "Context information is below.\n---------------------\n";
            $prompt .= $context;
            $prompt .= "\n---------------------\n";
            $prompt .= "Given the context information and not prior knowledge, answer the query.\n";
            $prompt .= "Query: " . $question . "\n";
            $prompt .= "Answer in the same language as the query (likely Vietnamese).";

            $response = Gemini::generativeModel('models/gemini-2.0-flash')
                ->withSafetySetting($safetySettingDangerousContent)
                ->withSafetySetting($safetySettingHateSpeech)
                ->withGenerationConfig($generationConfig)
                ->generateContent($prompt);

            return [
                'success' => true,
                'answer' => $response->text(),
                'raw_response' => $response->text()
            ];
        } catch (\Exception $e) {
            Log::error("Gemini Chat API error: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Extract intent and entities from a user query
     *
     * @param string $query The user's query
     * @return array Extracted intent and entities
     */
    public function extractIntent(string $query): array
    {
        try {
            $prompt = "Analyze the following user query about football and extract the intent and entities.\n";
            $prompt .= "Possible intents: 'fixture_schedule' (when is next match, match time), 'match_result' (score, result, who won), 'standings' (rank, table, position), 'team_info', 'player_info', 'prediction' (who will win, predict), 'general_chat'.\n";
            $prompt .= "For entities (teams, players, competitions), return a list of possible names/variations to aid search (e.g. if user says 'MU', return ['MU', 'Man Utd', 'Manchester United']).\n";
            $prompt .= "Return ONLY a JSON object with this structure: { \"intent\": \"string\", \"entities\": { \"teams\": [\"string\"], \"players\": [\"string\"], \"competitions\": [\"string\"] } }\n";
            $prompt .= "Query: " . $query;

            $result = Gemini::generativeModel('models/gemini-2.0-flash')
                ->generateContent($prompt);

            $text = $result->text();

            // Clean up code blocks if present
            $text = str_replace(['```json', '```'], '', $text);

            $data = json_decode($text, true);

            return [
                'success' => true,
                'intent' => $data['intent'] ?? 'general_chat',
                'entities' => [
                    'teams' => $data['entities']['teams'] ?? [],
                    'players' => $data['entities']['players'] ?? [],
                    'competitions' => $data['entities']['competitions'] ?? []
                ]
            ];
        } catch (\Exception $e) {
            Log::error("Gemini Intent Extraction error: " . $e->getMessage());
            // Fallback
            return [
                'success' => true,
                'intent' => 'general_chat',
                'entities' => []
            ];
        }
    }
}
