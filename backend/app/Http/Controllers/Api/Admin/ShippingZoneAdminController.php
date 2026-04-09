<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\ShippingZone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShippingZoneAdminController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(ShippingZone::query()->orderBy('code')->get());
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:32', 'unique:shipping_zones,code'],
            'name' => ['required', 'string', 'max:255'],
            'rate_per_kg_cents' => ['required', 'integer', 'min:0'],
            'free_shipping_from_subtotal_cents' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $zone = ShippingZone::query()->create([
            ...$validated,
            'free_shipping_from_subtotal_cents' => (int) ($validated['free_shipping_from_subtotal_cents'] ?? 0),
            'is_active' => (bool) ($validated['is_active'] ?? true),
        ]);

        return response()->json($zone, 201);
    }

    public function update(Request $request, ShippingZone $shippingZone): JsonResponse
    {
        $validated = $request->validate([
            'code' => ['sometimes', 'string', 'max:32', 'unique:shipping_zones,code,'.$shippingZone->id],
            'name' => ['sometimes', 'string', 'max:255'],
            'rate_per_kg_cents' => ['sometimes', 'integer', 'min:0'],
            'free_shipping_from_subtotal_cents' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $shippingZone->update($validated);

        return response()->json($shippingZone->fresh());
    }

    public function destroy(ShippingZone $shippingZone): JsonResponse
    {
        $shippingZone->delete();

        return response()->json(['message' => 'Shipping zone deleted.']);
    }
}
