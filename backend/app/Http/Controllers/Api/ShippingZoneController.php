<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ShippingZone;
use Illuminate\Http\JsonResponse;

class ShippingZoneController extends Controller
{
    public function index(): JsonResponse
    {
        $zones = ShippingZone::query()
            ->where('is_active', true)
            ->orderBy('code')
            ->get();

        return response()->json($zones);
    }
}
