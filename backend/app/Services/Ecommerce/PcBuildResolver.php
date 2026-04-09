<?php

namespace App\Services\Ecommerce;

use App\Models\Product;
use Illuminate\Database\Eloquent\Collection;

class PcBuildResolver
{
    /**
     * @param  int[]  $productIds
     * @return array{
     *   products: Collection,
     *   metrics: array{
     *     cpu_socket?: string,
     *     mb_socket?: string,
     *     ram_type?: string,
     *     mb_ram_type?: string,
     *     total_tdp_tbp: int,
     *     psu_watts?: int,
     *     gpu_length?: int,
     *     case_gpu_clearance?: int,
     *     cooler_height?: int,
     *     case_cooler_clearance?: int
     *   }
     * }
     */
    public function resolve(array $productIds): array
    {
        $products = Product::query()->whereIn('id', $productIds)->get();
        $metrics = [
            'total_tdp_tbp' => 50, // Base overhead
        ];

        foreach ($products as $product) {
            $cat = strtolower((string) $product->category);
            $specs = $product->specs ?? [];

            if ($cat === 'cpu') {
                $metrics['cpu_socket'] = $specs['socket'] ?? null;
                $metrics['total_tdp_tbp'] += (int) ($specs['tdp'] ?? 0);
            } elseif ($cat === 'motherboard' || $cat === 'mainboard') {
                $metrics['mb_socket'] = $specs['socket'] ?? null;
                $metrics['mb_ram_type'] = $specs['ram_type'] ?? null;
            } elseif ($cat === 'ram') {
                $metrics['ram_type'] = $specs['ram_type'] ?? null;
            } elseif ($cat === 'gpu' || $cat === 'vga') {
                $metrics['gpu_length'] = (int) ($specs['length_mm'] ?? 0);
                $metrics['total_tdp_tbp'] += (int) ($specs['tbp'] ?? $specs['tdp'] ?? 0);
            } elseif ($cat === 'psu' || $cat === 'power supply') {
                $metrics['psu_watts'] = (int) ($specs['wattage'] ?? 0);
            } elseif ($cat === 'case' || $cat === 'chassis') {
                $metrics['case_gpu_clearance'] = (int) ($specs['gpu_clearance_mm'] ?? 0);
                $metrics['case_cooler_clearance'] = (int) ($specs['cooler_clearance_mm'] ?? 0);
            } elseif ($cat === 'cooler' || $cat === 'cpu cooler') {
                $metrics['cooler_height'] = (int) ($specs['height_mm'] ?? 0);
            }
        }

        return [
            'products' => $products,
            'metrics' => $metrics,
        ];
    }
}
