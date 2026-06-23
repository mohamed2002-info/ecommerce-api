<?php

namespace App\Http\Controllers;

use App\Models\Promotion;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PromotionController extends Controller
{
    /**
     * List all promotions (admin view) with their attached products and a
     * computed `is_live` flag.
     */
    public function index()
    {
        $promotions = Promotion::with(['products:id,name', 'category:id,name'])
            ->orderByDesc('priority')
            ->orderByDesc('created_at')
            ->get()
            ->map(function (Promotion $promo) {
                $data = $promo->toArray();
                $data['is_live'] = $promo->isLive();
                return $data;
            });

        return response()->json($promotions);
    }

    public function show($id)
    {
        $promotion = Promotion::with(['products:id,name', 'category:id,name'])->findOrFail($id);
        $data = $promotion->toArray();
        $data['is_live'] = $promotion->isLive();

        return response()->json($data);
    }

    /**
     * PUBLIC: currently-live promotions, with only the fields a customer banner
     * needs (no internal data like priority, usage caps, audience, etc.).
     */
    public function active()
    {
        $promotions = Promotion::live()
            ->with('category:id,name')
            ->orderByDesc('priority')
            ->get()
            ->map(fn (Promotion $p) => [
                'id' => $p->id,
                'name' => $p->name,
                'description' => $p->description,
                'discount_type' => $p->discount_type,
                'value' => (float) $p->value,
                'ends_at' => optional($p->ends_at)->toIso8601String(),
            ]);

        return response()->json($promotions);
    }

    public function store(Request $request)
    {
        $data = $this->validatePromotion($request);

        $promotion = Promotion::create($this->fillable($data));
        $this->syncProducts($promotion, $data);

        return response()->json([
            'message' => 'Promotion created successfully',
            'promotion' => $promotion->load('products:id,name'),
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $promotion = Promotion::findOrFail($id);
        $data = $this->validatePromotion($request);

        $promotion->update($this->fillable($data));
        $this->syncProducts($promotion, $data);

        return response()->json([
            'message' => 'Promotion updated successfully',
            'promotion' => $promotion->load('products:id,name'),
        ]);
    }

    /**
     * Toggle status between active and paused without a full update.
     */
    public function setStatus(Request $request, $id)
    {
        $promotion = Promotion::findOrFail($id);
        $data = $request->validate([
            'status' => ['required', Rule::in(['active', 'paused'])],
        ]);

        $promotion->update(['status' => $data['status']]);

        return response()->json([
            'message' => "Promotion {$data['status']}",
            'promotion' => $promotion,
        ]);
    }

    public function destroy($id)
    {
        $promotion = Promotion::findOrFail($id);
        $promotion->delete(); // pivot rows cascade

        return response()->json(['message' => 'Promotion deleted successfully']);
    }

    /**
     * Validation shared by store/update, including cross-field rules.
     */
    private function validatePromotion(Request $request): array
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'target_type' => ['required', Rule::in(['product', 'products', 'category', 'all'])],
            'category_id' => 'nullable|exists:categories,id',
            'product_ids' => 'nullable|array',
            'product_ids.*' => 'integer|exists:products,id',
            // Only percentage/fixed are implemented for now; others are rejected.
            'discount_type' => ['required', Rule::in(['percentage', 'fixed'])],
            'value' => 'required|numeric|min:0',
            'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date|after_or_equal:starts_at',
            'priority' => 'nullable|integer|min:0',
            'status' => ['nullable', Rule::in(['active', 'paused'])],
            'max_uses' => 'nullable|integer|min:1',
            'audience' => 'nullable|string|max:255',
        ]);

        // A percentage discount must be 0-100.
        if (($data['discount_type'] ?? null) === 'percentage' && $data['value'] > 100) {
            abort(response()->json([
                'errors' => ['value' => ['A percentage discount cannot exceed 100.']],
            ], 422));
        }

        // Target-type specific requirements.
        if ($data['target_type'] === 'category' && empty($data['category_id'])) {
            abort(response()->json([
                'errors' => ['category_id' => ['A category is required for a category promotion.']],
            ], 422));
        }
        if (in_array($data['target_type'], ['product', 'products'], true) && empty($data['product_ids'])) {
            abort(response()->json([
                'errors' => ['product_ids' => ['Select at least one product for this promotion.']],
            ], 422));
        }

        return $data;
    }

    /**
     * Columns persisted directly on the promotions row.
     */
    private function fillable(array $data): array
    {
        return [
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'target_type' => $data['target_type'],
            'category_id' => $data['target_type'] === 'category' ? $data['category_id'] : null,
            'discount_type' => $data['discount_type'],
            'value' => $data['value'],
            'starts_at' => $data['starts_at'] ?? null,
            'ends_at' => $data['ends_at'] ?? null,
            'priority' => $data['priority'] ?? 0,
            'status' => $data['status'] ?? 'active',
            'max_uses' => $data['max_uses'] ?? null,
            'audience' => $data['audience'] ?? 'all',
        ];
    }

    /**
     * Sync the attached products for product/products targets; clear otherwise.
     */
    private function syncProducts(Promotion $promotion, array $data): void
    {
        if (in_array($data['target_type'], ['product', 'products'], true)) {
            $promotion->products()->sync($data['product_ids'] ?? []);
        } else {
            $promotion->products()->detach();
        }
    }
}
