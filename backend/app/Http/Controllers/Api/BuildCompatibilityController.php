<?php

namespace App\Http\Controllers\Api;

use App\Domain\Ecommerce\Compatibility\PcBuildCompatibility;
use App\Domain\Ecommerce\Exception\InvalidDomainArgumentException;
use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BuildCompatibilityController extends Controller
{
    public function validateBuild(Request $request, PcBuildCompatibility $checker): JsonResponse
    {
        if (is_array($request->input('product_ids'))) {
            return $this->validateByProductIds($request, $checker);
        }

        $validated = $request->validate([
            'cpu_socket' => ['nullable', 'string', 'max:50'],
            'motherboard_socket' => ['nullable', 'string', 'max:50'],
            'ram_type' => ['nullable', 'string', 'max:20'],
            'motherboard_ram_type' => ['nullable', 'string', 'max:20'],
            'psu_watts' => ['nullable', 'integer', 'min:1'],
            'estimated_system_watts' => ['nullable', 'integer', 'min:0'],
            'psu_headroom' => ['nullable', 'numeric', 'min:0'],
            'gpu_length_mm' => ['nullable', 'integer', 'min:1'],
            'case_max_gpu_length_mm' => ['nullable', 'integer', 'min:1'],
        ]);

        try {
            if (! empty($validated['cpu_socket']) && ! empty($validated['motherboard_socket'])) {
                $checker->assertCpuSocketMatchesMotherboard(
                    $validated['cpu_socket'],
                    $validated['motherboard_socket']
                );
            }

            if (! empty($validated['ram_type']) && ! empty($validated['motherboard_ram_type'])) {
                $checker->assertRamTypeMatchesMotherboard(
                    $validated['ram_type'],
                    $validated['motherboard_ram_type']
                );
            }

            if (isset($validated['psu_watts'], $validated['estimated_system_watts'])) {
                $checker->assertPsuAdequate(
                    (int) $validated['psu_watts'],
                    (int) $validated['estimated_system_watts'],
                    (float) ($validated['psu_headroom'] ?? 0.2)
                );
            }

            if (isset($validated['gpu_length_mm'], $validated['case_max_gpu_length_mm'])) {
                $checker->assertGpuFitsCase(
                    (int) $validated['gpu_length_mm'],
                    (int) $validated['case_max_gpu_length_mm']
                );
            }
        } catch (InvalidDomainArgumentException $e) {
            return response()->json([
                'ok' => false,
                'message' => $e->getMessage(),
            ], 422);
        }

        return response()->json(['ok' => true, 'message' => 'Build checks passed for provided fields.']);
    }

    private function validateByProductIds(Request $request, PcBuildCompatibility $checker): JsonResponse
    {
        $validated = $request->validate([
            'product_ids' => ['required', 'array', 'min:1'],
            'product_ids.*' => ['integer', 'exists:products,id'],
        ]);
        $products = Product::query()->whereIn('id', $validated['product_ids'])->get();

        $cpu = $products->first(fn (Product $product): bool => strtoupper((string) $product->category) === 'CPU');
        $mb = $products->first(
            fn (Product $product): bool => strtoupper((string) $product->category) === 'MOTHERBOARD'
        );
        $ram = $products->first(fn (Product $product): bool => strtoupper((string) $product->category) === 'RAM');
        $gpu = $products->first(fn (Product $product): bool => strtoupper((string) $product->category) === 'GPU');
        $psu = $products->first(fn (Product $product): bool => strtoupper((string) $product->category) === 'PSU');

        $estimatedWatts = 50
            + (int) data_get($cpu?->specs, 'tdp', 0)
            + (int) data_get($gpu?->specs, 'tbp', 0);
        $errors = [];

        try {
            if ($cpu && $mb) {
                $checker->assertCpuSocketMatchesMotherboard(
                    (string) data_get($cpu->specs, 'socket', ''),
                    (string) data_get($mb->specs, 'socket', '')
                );
            }
            if ($ram && $mb) {
                $checker->assertRamTypeMatchesMotherboard(
                    (string) data_get($ram->specs, 'ram_type', ''),
                    (string) data_get($mb->specs, 'ram_type', '')
                );
            }
            if ($psu) {
                $checker->assertPsuAdequate(
                    (int) data_get($psu->specs, 'wattage', 0),
                    $estimatedWatts,
                    0.2
                );
            }
        } catch (InvalidDomainArgumentException $e) {
            $errors[] = $e->getMessage();
        }

        return response()->json([
            'valid' => count($errors) === 0,
            'errors' => $errors,
            'estimated_wattage' => $estimatedWatts,
        ]);
    }
}
