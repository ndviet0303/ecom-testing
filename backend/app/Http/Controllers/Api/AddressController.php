<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Address;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AddressController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $rows = Address::query()->where('user_id', $request->user()->id)->orderByDesc('is_default')->get();

        return response()->json($rows);
    }

    public function show(Request $request, Address $address): JsonResponse
    {
        $this->assertOwns($request, $address);

        return response()->json($address);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'label' => ['nullable', 'string', 'max:100'],
            'recipient_name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'regex:/^(0|\+84)[35789][0-9]{8}$/'],
            'line1' => ['required', 'string', 'max:255'],
            'line2' => ['nullable', 'string', 'max:255'],
            'ward' => ['nullable', 'string', 'max:100'],
            'district' => ['required', 'string', 'max:100'],
            'province' => ['required', 'string', 'max:100'],
            'postal_code' => ['nullable', 'string', 'max:16'],
            'is_default' => ['sometimes', 'boolean'],
        ]);

        $address = DB::transaction(function () use ($request, $validated): Address {
            if (! empty($validated['is_default'])) {
                Address::query()->where('user_id', $request->user()->id)->update(['is_default' => false]);
            }

            return Address::query()->create([
                ...$validated,
                'user_id' => $request->user()->id,
                'country' => 'VN',
                'is_default' => (bool) ($validated['is_default'] ?? false),
            ]);
        });

        return response()->json($address, 201);
    }

    public function update(Request $request, Address $address): JsonResponse
    {
        $this->assertOwns($request, $address);

        $validated = $request->validate([
            'label' => ['nullable', 'string', 'max:100'],
            'recipient_name' => ['sometimes', 'string', 'max:255'],
            'phone' => ['sometimes', 'string', 'regex:/^(0|\+84)[35789][0-9]{8}$/'],
            'line1' => ['sometimes', 'string', 'max:255'],
            'line2' => ['nullable', 'string', 'max:255'],
            'ward' => ['nullable', 'string', 'max:100'],
            'district' => ['sometimes', 'string', 'max:100'],
            'province' => ['sometimes', 'string', 'max:100'],
            'postal_code' => ['nullable', 'string', 'max:16'],
            'is_default' => ['sometimes', 'boolean'],
        ]);

        DB::transaction(function () use ($request, $address, $validated): void {
            if (! empty($validated['is_default'])) {
                Address::query()->where('user_id', $request->user()->id)->update(['is_default' => false]);
            }

            $address->update($validated);
        });

        return response()->json($address->fresh());
    }

    public function destroy(Request $request, Address $address): JsonResponse
    {
        $this->assertOwns($request, $address);
        $address->delete();

        return response()->json(['message' => 'Address deleted.']);
    }

    private function assertOwns(Request $request, Address $address): void
    {
        if ($address->user_id !== $request->user()->id) {
            abort(404);
        }
    }
}
