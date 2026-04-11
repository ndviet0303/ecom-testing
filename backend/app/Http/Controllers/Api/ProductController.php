<?php

namespace App\Http\Controllers\Api;

use App\Domain\Ecommerce\Exception\InvalidDomainArgumentException;
use App\Domain\Ecommerce\Pricing\ProductPriceValidator;
use App\Http\Controllers\Controller;
use App\Models\Inventory;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\DB;

class ProductController extends Controller
{
    public function __construct(
        private readonly ProductPriceValidator $priceValidator
    ) {}

    public function index(Request $request): JsonResponse
    {
        $category = $request->query('category');
        $search = $request->query('q');
        $perPage = min((int) $request->query('per_page', 15), 100);

        $query = Product::query()->with('inventory');

        if ($category) {
            $query->where('category', $category);
        }

        if ($search) {
            $query->where(function ($q) use ($search): void {
                $q->where('name', 'like', '%'.$search.'%')
                    ->orWhere('sku', 'like', '%'.$search.'%');
            });
        }

        $results = $query->orderBy('id')->paginate($perPage);

        return response()->json($results);
    }

    public function show(Product $product): JsonResponse
    {
        $product->load('inventory');

        return response()->json($product);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'sku' => ['required', 'string', 'max:64', 'unique:products,sku'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'category' => ['required', 'string', 'max:100'],
            'brand' => ['nullable', 'string', 'max:100'],
            'base_price_cents' => ['required', 'integer', 'min:1'],
            'sale_price_cents' => ['nullable', 'integer', 'min:0'],
            'specs' => ['nullable', 'array'],
            'image_url' => ['nullable', 'string', 'max:500'],
            'warranty_months' => ['nullable', 'integer', 'min:0'],
            'initial_on_hand' => ['nullable', 'integer', 'min:0'],
            'low_stock_threshold' => ['nullable', 'integer', 'min:0'],
        ]);

        try {
            $this->priceValidator->validate(
                (int) $validated['base_price_cents'],
                array_key_exists('sale_price_cents', $validated) && $validated['sale_price_cents'] !== null
                    ? (int) $validated['sale_price_cents']
                    : null
            );
        } catch (InvalidDomainArgumentException $e) {
            throw ValidationException::withMessages([
                'price' => [$e->getMessage()],
            ]);
        }

        $product = Product::query()->create([
            'sku' => $validated['sku'],
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'category' => $validated['category'],
            'brand' => $validated['brand'] ?? null,
            'base_price_cents' => $validated['base_price_cents'],
            'sale_price_cents' => $validated['sale_price_cents'] ?? null,
            'specs' => $validated['specs'] ?? null,
            'image_url' => $validated['image_url'] ?? null,
            'warranty_months' => (int) ($validated['warranty_months'] ?? 0),
        ]);

        Inventory::query()->create([
            'product_id' => $product->id,
            'on_hand' => (int) ($validated['initial_on_hand'] ?? 0),
            'reserved' => 0,
            'low_stock_threshold' => (int) ($validated['low_stock_threshold'] ?? 0),
        ]);

        $product->load('inventory');

        return response()->json($product, 201);
    }

    public function update(Request $request, Product $product): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'category' => ['sometimes', 'string', 'max:100'],
            'brand' => ['nullable', 'string', 'max:100'],
            'base_price_cents' => ['sometimes', 'integer', 'min:1'],
            'sale_price_cents' => ['nullable', 'integer', 'min:0'],
            'specs' => ['nullable', 'array'],
            'image_url' => ['nullable', 'string', 'max:500'],
            'warranty_months' => ['sometimes', 'integer', 'min:0'],
            'low_stock_threshold' => ['sometimes', 'integer', 'min:0'],
        ]);

        $base = (int) ($validated['base_price_cents'] ?? $product->base_price_cents);
        $sale = array_key_exists('sale_price_cents', $validated)
            ? $validated['sale_price_cents']
            : $product->sale_price_cents;
        $saleInt = $sale === null ? null : (int) $sale;

        try {
            $this->priceValidator->validate($base, $saleInt);
        } catch (InvalidDomainArgumentException $e) {
            throw ValidationException::withMessages([
                'price' => [$e->getMessage()],
            ]);
        }

        foreach (['name', 'description', 'category', 'brand', 'specs', 'image_url', 'warranty_months'] as $field) {
            if (array_key_exists($field, $validated)) {
                $product->{$field} = $validated[$field];
            }
        }

        if (array_key_exists('base_price_cents', $validated)) {
            $product->base_price_cents = (int) $validated['base_price_cents'];
        }

        if (array_key_exists('sale_price_cents', $validated)) {
            $product->sale_price_cents = $validated['sale_price_cents'];
        }

        $product->save();

        if (array_key_exists('low_stock_threshold', $validated)) {
            $product->inventory?->update([
                'low_stock_threshold' => (int) $validated['low_stock_threshold'],
            ]);
        }

        $product->load('inventory');

        return response()->json($product);
    }

    public function destroy(Product $product): JsonResponse
    {
        $product->delete();

        return response()->json(['message' => 'Product deleted.']);
    }
}
