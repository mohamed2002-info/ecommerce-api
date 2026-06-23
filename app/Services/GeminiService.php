<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Thin client for Google's Gemini API, scoped to a single use case: a shopping
 * assistant that recommends from THIS store's live catalog only.
 *
 * The API key lives in config/services.php (from .env) and never leaves the
 * server. The product list (with current promotion pricing) is injected into
 * the system prompt so the model can only recommend real, in-stock items.
 */
class GeminiService
{
    private const ENDPOINT = 'https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent';

    public function __construct(private PromotionService $promotions)
    {
    }

    public function isConfigured(): bool
    {
        return ! empty(config('services.gemini.key'));
    }

    /**
     * Send a conversation to Gemini and return the assistant's reply text.
     *
     * @param  array<int,array{role:string,text:string}>  $history  prior turns
     * @return array{ok:bool, reply?:string, error?:string}
     */
    public function chat(string $userMessage, array $history = []): array
    {
        if (! $this->isConfigured()) {
            return ['ok' => false, 'error' => 'The assistant is not configured yet.'];
        }

        $model = config('services.gemini.model', 'gemini-2.5-flash');
        $url = sprintf(self::ENDPOINT, $model);

        $payload = [
            'system_instruction' => [
                'parts' => [['text' => $this->systemPrompt()]],
            ],
            'contents' => $this->buildContents($history, $userMessage),
            'generationConfig' => [
                'temperature' => 0.4,
                'maxOutputTokens' => 1200,
                // Gemini 2.5 Flash can spend the whole token budget on internal
                // "thinking" and return an empty answer. Disable it so tokens go
                // to the actual reply.
                'thinkingConfig' => [
                    'thinkingBudget' => 0,
                ],
            ],
        ];

        try {
            // Retry transient 503 "model overloaded" responses (and network
            // blips) a couple of times with a short backoff.
            $response = Http::timeout(30)
                ->retry(2, 600, function ($exception, $request) {
                    return $exception instanceof \Illuminate\Http\Client\ConnectionException;
                }, throw: false)
                ->withHeaders(['x-goog-api-key' => config('services.gemini.key')])
                ->post($url, $payload);

            // Manual retry for 503 (retry() above only covers connection errors).
            $attempts = 0;
            while ($response->status() === 503 && $attempts < 2) {
                usleep(700000);
                $response = Http::timeout(30)
                    ->withHeaders(['x-goog-api-key' => config('services.gemini.key')])
                    ->post($url, $payload);
                $attempts++;
            }
        } catch (\Throwable $e) {
            Log::error('Gemini request failed: ' . $e->getMessage());
            return ['ok' => false, 'error' => 'The assistant is temporarily unavailable. Please try again.'];
        }

        if (! $response->successful()) {
            Log::warning('Gemini non-200: ' . $response->status() . ' ' . $response->body());
            $msg = $response->status() === 503
                ? 'The assistant is very busy right now. Please try again in a moment.'
                : 'The assistant could not answer right now. Please try again.';
            return ['ok' => false, 'error' => $msg];
        }

        $text = data_get($response->json(), 'candidates.0.content.parts.0.text');

        if (! $text) {
            return ['ok' => false, 'error' => 'The assistant returned an empty response.'];
        }

        return ['ok' => true, 'reply' => trim($text)];
    }

    /**
     * Build the Gemini `contents` array from prior history + the new message.
     * Gemini uses roles 'user' and 'model'.
     */
    private function buildContents(array $history, string $userMessage): array
    {
        $contents = [];

        foreach ($history as $turn) {
            $role = ($turn['role'] ?? 'user') === 'assistant' ? 'model' : 'user';
            $text = (string) ($turn['text'] ?? '');
            if ($text !== '') {
                $contents[] = ['role' => $role, 'parts' => [['text' => $text]]];
            }
        }

        $contents[] = ['role' => 'user', 'parts' => [['text' => $userMessage]]];

        return $contents;
    }

    /**
     * The system prompt: defines the assistant's behavior and embeds the live
     * catalog so recommendations are grounded in real products.
     */
    private function systemPrompt(): string
    {
        $catalog = $this->catalogText();

        return <<<PROMPT
You are "TechBot", the shopping assistant for an online electronics store.
Your job is to help customers compare products and choose the best option for
their needs (budget, use case, preferences).

STRICT RULES:
- Only recommend products from the CATALOG below. Never invent products,
  specs, or prices that are not listed.
- If the catalog has nothing suitable, say so honestly and suggest the closest
  options.
- Prices are in DT (Tunisian Dinar). If a product is on sale, mention the
  discounted price.
- Always refer to products by their exact name so the customer can find them.
- Be concise and friendly. When comparing, give a short reason for your pick.
- If asked about anything unrelated to shopping for these products, gently
  steer back to helping them choose a product.

CATALOG (the only products you may recommend):
{$catalog}
PROMPT;
    }

    /**
     * Compact, promotion-aware product list for the prompt.
     */
    private function catalogText(): string
    {
        $products = Product::with('subCategory')->get();
        $resolved = $this->promotions->resolveForProducts($products);

        if ($products->isEmpty()) {
            return '(no products currently available)';
        }

        return $products->map(function (Product $p) use ($resolved) {
            $pricing = $this->promotions->pricingPayload($p, $resolved[$p->id] ?? null);
            $price = $pricing['on_sale']
                ? "{$pricing['discounted_price']} DT (on sale, was {$pricing['original_price']} DT)"
                : "{$pricing['original_price']} DT";
            $category = $p->subCategory->name ?? 'Uncategorized';

            return "- {$p->name} | {$category} | {$price} | {$p->description}";
        })->implode("\n");
    }
}
