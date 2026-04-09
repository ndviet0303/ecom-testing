<?php

namespace App\Policies;

use App\Models\ReturnRequest;
use App\Models\User;

class ReturnRequestPolicy
{
    /** Danh sách đầy đủ trong back-office (middleware staff đã chặn khách). */
    public function viewAny(User $user): bool
    {
        return $user->canAccessStaffRoutes();
    }

    /** Chi tiết một yêu cầu: chủ đơn hoặc nhân sự. */
    public function view(User $user, ReturnRequest $returnRequest): bool
    {
        return $returnRequest->user_id === $user->id || $user->canAccessStaffRoutes();
    }

    public function create(User $user): bool
    {
        return true;
    }

    /** Đổi trạng thái RMA (máy trạng thái + audit). */
    public function transition(User $user, ReturnRequest $returnRequest): bool
    {
        return $user->canAccessStaffRoutes();
    }
}
