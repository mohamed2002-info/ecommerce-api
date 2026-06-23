<?php

namespace Tests\Feature;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AssistantTest extends TestCase
{
    use RefreshDatabase;

    public function test_assistant_requires_a_message(): void
    {
        config(['services.gemini.key' => 'test-key']);

        $this->postJson('/api/assistant', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors('message');
    }

    public function test_assistant_returns_503_when_not_configured(): void
    {
        config(['services.gemini.key' => null]);

        $this->postJson('/api/assistant', ['message' => 'hi'])
            ->assertStatus(503)
            ->assertJson(['ok' => false]);
    }

    public function test_assistant_returns_a_reply_and_grounds_the_prompt_in_the_catalog(): void
    {
        config(['services.gemini.key' => 'test-key']);
        $product = Product::factory()->create(['name' => 'Acme Laptop Pro', 'price' => 1000]);

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    ['content' => ['parts' => [['text' => 'I recommend the Acme Laptop Pro.']]]],
                ],
            ], 200),
        ]);

        $response = $this->postJson('/api/assistant', [
            'message' => 'Which laptop should I buy?',
        ]);

        $response->assertStatus(200)
            ->assertJson(['ok' => true])
            ->assertJsonFragment(['reply' => 'I recommend the Acme Laptop Pro.']);

        // The outgoing prompt must contain the real product (catalog grounding).
        Http::assertSent(function ($request) use ($product) {
            $body = json_encode($request->data());
            return str_contains($body, $product->name);
        });
    }

    public function test_assistant_handles_upstream_failure_gracefully(): void
    {
        config(['services.gemini.key' => 'test-key']);

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response('error', 500),
        ]);

        $this->postJson('/api/assistant', ['message' => 'hi'])
            ->assertStatus(502)
            ->assertJson(['ok' => false]);
    }
}
