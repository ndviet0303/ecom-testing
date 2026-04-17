<?php

namespace App\Http\Controllers\Api\Admin;

use App\Domain\Ecommerce\ReturnRequest\ReturnStatus;
use App\Http\Controllers\Controller;
use App\Models\ReturnRequest;
use App\Services\Ecommerce\ReturnTransitionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReturnRequestAdminController extends Controller
{
    public function __construct(
        private readonly ReturnTransitionService $returnTransitionService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', ReturnRequest::class);

        $status = $request->query('status');

        $q = ReturnRequest::query()
            ->with(['user', 'order', 'orderItem.product'])
            ->latest();

        if (is_string($status) && $status !== '') {
            $q->where('status', $status);
        }

        return response()->json($q->paginate(50));
    }

    public function update(Request $request, ReturnRequest $returnRequest): JsonResponse
    {
        $this->authorize('transition', $returnRequest);

        $validated = $request->validate([
            'status' => ['required', 'string'],
            'staff_note' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ]);

        $to = ReturnStatus::tryFrom($validated['status']);
        if ($to === null) {
            abort(422, 'Invalid return status.');
        }

        $options = [];
        if (array_key_exists('staff_note', $validated)) {
            $options['staff_note'] = $validated['staff_note'];
        }

        $returnRequest = $this->returnTransitionService->transition(
            $returnRequest,
            $to,
            $request->user(),
            $request->ip(),
            $options
        );

        return response()->json($returnRequest->load(['user', 'order', 'orderItem.product']));
    }
}
