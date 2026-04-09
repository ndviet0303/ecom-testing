<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $logs = AuditLog::query()
            ->with('user:id,name,email')
            ->orderByDesc('id')
            ->paginate(min((int) $request->query('per_page', 50), 200));

        return response()->json($logs);
    }
}
