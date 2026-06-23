<?php

namespace App\Http\Controllers;

use App\Services\GeminiService;
use Illuminate\Http\Request;

class ChatController extends Controller
{
    public function __construct(private GeminiService $gemini)
    {
    }

    /**
     * Shopping-assistant chat endpoint.
     *
     * Public but rate-limited (see routes/api.php) so it can't be abused as a
     * free LLM proxy. History is capped to keep prompts small and predictable.
     */
    public function chat(Request $request)
    {
        $data = $request->validate([
            'message' => 'required|string|max:1000',
            'history' => 'nullable|array|max:20',
            'history.*.role' => 'required_with:history|string|in:user,assistant',
            'history.*.text' => 'required_with:history|string|max:4000',
        ]);

        if (! $this->gemini->isConfigured()) {
            return response()->json([
                'ok' => false,
                'message' => 'The shopping assistant is not available right now.',
            ], 503);
        }

        // Keep only the last few turns to bound prompt size and cost.
        $history = array_slice($data['history'] ?? [], -8);

        $result = $this->gemini->chat($data['message'], $history);

        if (! $result['ok']) {
            return response()->json([
                'ok' => false,
                'message' => $result['error'] ?? 'The assistant could not answer right now.',
            ], 502);
        }

        return response()->json([
            'ok' => true,
            'reply' => $result['reply'],
        ]);
    }
}
