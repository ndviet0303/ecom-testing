<?php

namespace App\Services\Ecommerce;

use App\Domain\Ecommerce\Exception\InvalidReturnTransitionException;
use App\Domain\Ecommerce\ReturnRequest\ReturnRequestStateMachine;
use App\Domain\Ecommerce\ReturnRequest\ReturnStatus;
use App\Models\ReturnRequest;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReturnTransitionService
{
    public function __construct(
        private readonly ReturnRequestStateMachine $stateMachine,
        private readonly AuditLogger $auditLogger,
        private readonly InventoryRestockService $inventoryRestockService,
    ) {}

    /**
     * @param  array{staff_note?: string|null}  $options
     */
    public function transition(
        ReturnRequest $returnRequest,
        ReturnStatus $to,
        User $actor,
        ?string $ip = null,
        array $options = [],
    ): ReturnRequest {
        return DB::transaction(function () use ($returnRequest, $to, $actor, $ip, $options): ReturnRequest {
            $returnRequest = ReturnRequest::query()->whereKey($returnRequest->id)->lockForUpdate()->firstOrFail();
            $from = $returnRequest->status;

            try {
                $this->stateMachine->assertCanTransition($from, $to);
            } catch (InvalidReturnTransitionException $e) {
                throw ValidationException::withMessages([
                    'status' => [$e->getMessage()],
                ]);
            }

            $payload = ['status' => $to];
            if (array_key_exists('staff_note', $options)) {
                $payload['staff_note'] = $options['staff_note'];
            }
            $returnRequest->update($payload);

            if ($to === ReturnStatus::Refunded) {
                $returnRequest->loadMissing('orderItem');
                $this->inventoryRestockService->restockLine(
                    $returnRequest->orderItem,
                    $returnRequest->quantity
                );
            }

            $this->auditLogger->log($actor, 'return_request.transition', $returnRequest, [
                'from' => $from->value,
                'to' => $to->value,
            ], $ip);

            return $returnRequest->fresh();
        });
    }
}
