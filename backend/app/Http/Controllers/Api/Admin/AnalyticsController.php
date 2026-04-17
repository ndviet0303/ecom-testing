<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AnalyticsController extends Controller
{
    public function salesReport(Request $request): JsonResponse
    {
        // Thống kê doanh thu theo ngày trong 30 ngày qua
        $salesTrend = Order::query()
            ->where('status', '!=', 'cancelled')
            ->where('created_at', '>=', now()->subDays(30))
            ->select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('SUM(total_cents) as revenue'),
                DB::raw('COUNT(*) as order_count')
            )
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        // Thống kê tổng quan
        $stats = [
            'total_revenue' => Order::where('status', '!=', 'cancelled')->sum('total_cents'),
            'total_orders' => Order::count(),
            'pending_orders' => Order::where('status', 'pending')->count(),
            'monthly_growth' => 15.5, // Giả định cho demo
        ];

        return response()->json([
            'trend' => $salesTrend,
            'stats' => $stats
        ]);
    }

    public function topProducts(Request $request): JsonResponse
    {
        $products = OrderItem::query()
            ->select('product_id', DB::raw('SUM(quantity) as total_sold'), DB::raw('SUM(quantity * unit_price_cents) as total_revenue'))
            ->with('product:id,name,image_url')
            ->groupBy('product_id')
            ->orderByDesc('total_sold')
            ->limit(5)
            ->get();

        return response()->json($products);
    }
}
