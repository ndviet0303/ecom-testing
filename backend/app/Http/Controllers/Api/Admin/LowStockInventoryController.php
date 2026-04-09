<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Inventory;
use Illuminate\Http\JsonResponse;

class LowStockInventoryController extends Controller
{
    public function index(): JsonResponse
    {
        $rows = Inventory::query()
            ->with('product')
            ->where('low_stock_threshold', '>', 0)
            ->whereColumn('on_hand', '<=', 'low_stock_threshold')
            ->orderBy('on_hand')
            ->get();

        return response()->json($rows);
    }
}
