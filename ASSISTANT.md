# AI Shopping Assistant ("TechBot")

A floating chat widget that helps customers compare products and choose the best
option for their needs. Powered by **Google Gemini 2.5 Flash**, grounded in the
store's own live catalog.

## How it works

```
Angular chat widget ──POST /api/assistant──► Laravel ChatController
                                                  │  (GEMINI_API_KEY from .env)
                                                  ▼
                                          GeminiService → Gemini 2.5 Flash
                                                  │
   builds a system prompt embedding every product (with current promotion
   prices, via PromotionService) → Gemini recommends ONLY from that list
```

The browser only ever talks to the Laravel API. **The Gemini key never leaves
the server.**

## Configuration

In `.env`:

```
GEMINI_API_KEY=your-key-here        # from Google AI Studio
GEMINI_MODEL=gemini-2.5-flash       # optional override
```

After changing `.env`, run `php artisan config:clear` and restart the server.
If the key is empty, the endpoint returns `503` and the widget shows a friendly
"not available" message — nothing breaks.

## Endpoint

`POST /api/assistant` — **public, rate-limited to 15 requests/min per IP**
(throttle middleware) so it can't be abused as a free LLM proxy.

Request:
```json
{ "message": "I want a gaming laptop under 3500 DT", "history": [ {"role":"user","text":"..."}, {"role":"assistant","text":"..."} ] }
```
Response:
```json
{ "ok": true, "reply": "..." }
```

- `message`: required, max 1000 chars.
- `history`: optional prior turns; the server keeps only the last 8 to bound
  prompt size and cost.

## Grounding & safety

- The system prompt lists every product (name, category, promo-aware price,
  description) and instructs Gemini to recommend **only** from it — no invented
  products or specs.
- Prices reflect active promotions automatically (reuses `PromotionService`).
- `thinkingConfig.thinkingBudget = 0` — Gemini 2.5 Flash otherwise spends its
  token budget on internal "thinking" and can return empty; disabling it sends
  tokens to the actual answer.
- Transient `503` (model overloaded) responses are retried twice with backoff.

## Frontend

- [chat-widget.component](../../ecomerce/e-commerce_Website/src/app/chat/chat-widget.component.ts):
  floating 🤖 button (bottom-right) on every page, fully themed via the design
  tokens (works in light and dark mode), with typing indicator and error states.
- [assistant.service](../../ecomerce/e-commerce_Website/src/app/services/assistant.service.ts):
  posts the message + history to `/api/assistant`.

## Tests

`tests/Feature/AssistantTest.php` (uses `Http::fake()`, no real key/network):
validates input, 503 when unconfigured, a grounded reply containing real
catalog products, and graceful handling of an upstream failure.

## Cost note

Gemini 2.5 Flash is inexpensive (fractions of a cent per message at this prompt
size). The rate limit plus the 8-turn history cap keep usage bounded.
