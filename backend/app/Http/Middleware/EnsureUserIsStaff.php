<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsStaff
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || ! in_array($user->role, [User::ROLE_ADMIN, User::ROLE_STAFF], true)) {
            abort(403, 'Staff access required.');
        }

        return $next($request);
    }
}
