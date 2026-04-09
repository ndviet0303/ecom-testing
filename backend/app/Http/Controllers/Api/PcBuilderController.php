<?php

namespace App\Http\Controllers\Api;

use App\Domain\Ecommerce\Compatibility\PcBuildCompatibility;
use App\Domain\Ecommerce\Exception\InvalidDomainArgumentException;
use App\Http\Controllers\Controller;
use App\Services\Ecommerce\PcBuildResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PcBuilderController extends Controller
{
    public function __construct(
        private readonly PcBuildResolver $resolver,
        private readonly PcBuildCompatibility $checker
    ) {}

    /**
     * POST /api/v1/pc-builder/validate
     */
    public function validateBuild(Request $request): JsonResponse
    {
        $request->validate([
            'product_ids' => ['required', 'array', 'min:1'],
            'product_ids.*' => ['integer', 'exists:products,id'],
            'psu_headroom' => ['nullable', 'numeric', 'min:0'],
        ]);

        $resolved = $this->resolver->resolve($request->json('product_ids'));
        $metrics = $resolved['metrics'];
        $errors = [];

        try {
            // 1. Socket Check
            if (isset($metrics['cpu_socket'], $metrics['mb_socket'])) {
                $this->checker->assertCpuSocketMatchesMotherboard(
                    $metrics['cpu_socket'],
                    $metrics['mb_socket']
                );
            }

            // 2. RAM Check
            if (isset($metrics['ram_type'], $metrics['mb_ram_type'])) {
                $this->checker->assertRamTypeMatchesMotherboard(
                    $metrics['ram_type'],
                    $metrics['mb_ram_type']
                );
            }

            // 3. PSU Check
            if (isset($metrics['psu_watts'])) {
                $this->checker->assertPsuAdequate(
                    $metrics['psu_watts'],
                    $metrics['total_tdp_tbp'],
                    (float) ($request->json('psu_headroom') ?? 0.2)
                );
            }

            // 4. GPU Clearance
            if (isset($metrics['gpu_length'], $metrics['case_gpu_clearance']) && $metrics['gpu_length'] > 0 && $metrics['case_gpu_clearance'] > 0) {
                $this->checker->assertGpuFitsCase(
                    $metrics['gpu_length'],
                    $metrics['case_gpu_clearance']
                );
            }

            // 5. Cooler Clearance
            if (isset($metrics['cooler_height'], $metrics['case_cooler_clearance']) && $metrics['cooler_height'] > 0 && $metrics['case_cooler_clearance'] > 0) {
                $this->checker->assertCoolerFitsCase(
                    $metrics['cooler_height'],
                    $metrics['case_cooler_clearance']
                );
            }

        } catch (InvalidDomainArgumentException $e) {
            $errors[] = $e->getMessage();
        }

        return response()->json([
            'valid' => count($errors) === 0,
            'errors' => $errors,
            'estimated_wattage' => $metrics['total_tdp_tbp'],
            'components_count' => count($resolved['products']),
        ]);
    }
}
