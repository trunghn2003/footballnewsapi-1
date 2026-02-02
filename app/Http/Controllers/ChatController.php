<?php

namespace App\Http\Controllers;

use App\Services\ChatService;
use Illuminate\Http\Request;

class ChatController extends Controller
{
    public function __construct(private ChatService $chatService) {}

    /**
     * Handle chat request
     */
    public function chat(Request $request)
    {
        $request->validate([
            'message' => 'required|string|max:1000',
        ]);

        $message = $request->input('message');

        $result = $this->chatService->handleMessage($message);

        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'message' => $result['message'],
                'error' => $result['error'] ?? null
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => $result['message'],
            'intent' => $result['intent'],
            'context_used' => $result['context_used']
        ]);
    }
}
