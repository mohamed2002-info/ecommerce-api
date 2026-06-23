# Promotion System

A flexible, backend-authoritative discount engine. Admins create promotions;
the server computes every price, so customers always see — and are charged —
the correct discounted amount.

## Architecture overview

```
Admin UI (Angular)            Customer UI (Angular)
  /admin/promotions             product card / details / cart
        │                              │
        ▼  (admin token)               ▼  (reads decorated prices)
  PromotionController  ───────►  ProductController / CartController / OrderController
        │                              │
        └──────────────┬───────────────┘
                       ▼
               PromotionService  ◄── the single source of truth for pricing
                       │
                       ▼
          promotions + promotion_product (DB)
```

The **`PromotionService`** is the only place that decides "what does this
product cost right now." Product listing, product detail, the cart, and order
confirmation all call it, so there is one consistent answer and the client
never supplies a price.

## Data model

`promotions` table:

| Column | Meaning |
|--------|---------|
| `name`, `description` | display / admin notes |
| `target_type` | `product` \| `products` \| `category` \| `all` |
| `category_id` | for `category` targets |
| `discount_type` | `percentage` \| `fixed` (BOGO/free_shipping/custom reserved for phase 2) |
| `value` | percent (0–100) or fixed amount |
| `starts_at`, `ends_at` | scheduling window (null = open-ended) |
| `priority` | higher wins when promotions overlap |
| `status` | `active` \| `paused` |
| `max_uses`, `uses_count`, `audience`, `auto_random_weekly` | phase-2 scaffolding |

`promotion_product` pivot attaches specific products to product/products
promotions.

## Resolution & conflict rules

When several **live** promotions target the same product, exactly one wins:

1. **Highest `priority`.**
2. Tie → **largest actual discount** for that product.
3. Tie → **newest** promotion.

Only one promotion ever applies per product, so discounts never stack
unexpectedly. "Live" means `status = active` AND now is within
`[starts_at, ends_at]`. Paused, future, and expired promotions are ignored.

## How prices flow

- **`GET /api/products`, `GET /api/products/{id}`** — each product is decorated
  with `original_price`, `discounted_price`, `on_sale`, and a `promotion`
  summary (`{id, name, discount_type, value, ends_at}`).
- **`GET /api/cart`** — each line carries the decorated product plus
  `unit_price` and `line_total`; the response includes a cart `subtotal`.
- **`POST /api/orders/confirm`** — totals are recomputed from
  `PromotionService::effectiveUnitPrice()` at checkout time. A discount that
  expired between page load and checkout is **not** honored, and order items
  store the price actually charged.

## API (admin, requires `auth:sanctum` + `admin`)

| Method | Path | Purpose |
|--------|------|---------|
| GET | `/api/promotions` | list all (with `is_live`) |
| GET | `/api/promotions/{id}` | show one |
| POST | `/api/promotions` | create |
| PUT | `/api/promotions/{id}` | update |
| PATCH | `/api/promotions/{id}/status` | activate / pause |
| DELETE | `/api/promotions/{id}` | delete |

### Create payload example

```json
{
  "name": "Summer Sale",
  "target_type": "products",
  "product_ids": [1, 2, 3],
  "discount_type": "percentage",
  "value": 20,
  "priority": 1,
  "starts_at": "2026-06-01T00:00:00",
  "ends_at": "2026-06-30T23:59:59",
  "status": "active"
}
```

Validation enforces: percentage ≤ 100, a category for `category` targets, at
least one product for `product`/`products` targets, and `ends_at >= starts_at`.

## Customer experience

- Product cards/detail show a **`-XX%` badge**, the **discounted price** with the
  original struck through, and a **live countdown** to `ends_at`.
- The cart shows per-line was/now prices, a **"You save"** line, and the
  promotion-adjusted total.
- Everything is automatic — there are no coupon codes to enter; eligible
  discounts apply by virtue of the active promotion.

## Admin experience

`/admin/promotions` (admin-only route, guarded client- and server-side):
list table with live/scheduled/paused status, create/edit modal with a product
picker and category/all targeting, one-click pause/activate, and delete.

## Tests

`tests/Feature/PromotionEngineTest.php` and `PromotionApiTest.php` cover discount
math, expiry/pause/future gating, category & all-catalog targeting, conflict
resolution, the decorated payload, admin authorization, and checkout totals
(including that expired promos are not honored). Run with `php artisan test`.

## Phase-2 (scaffolded, not yet enforced)

BOGO, free shipping, custom rules, user-group audiences, max-usage limits, and a
weekly auto-random-product picker. The schema columns and enum values already
exist; the engine treats them as no-ops and the admin validation currently
restricts `discount_type` to `percentage`/`fixed`.
