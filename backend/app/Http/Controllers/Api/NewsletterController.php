<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\NewsletterSubscriber;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NewsletterController extends Controller
{
    public function subscribe(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
        ]);

        $email = strtolower($validated['email']);

        NewsletterSubscriber::query()->updateOrCreate(
            ['email' => $email],
            [
                'subscribed_at' => now(),
                'unsubscribed_at' => null,
            ]
        );

        return response()->json(['message' => 'Đã đăng ký nhận tin.'], 201);
    }

    public function unsubscribe(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
        ]);

        NewsletterSubscriber::query()
            ->where('email', strtolower($validated['email']))
            ->update(['unsubscribed_at' => now()]);

        return response()->json(['message' => 'Đã hủy đăng ký.']);
    }
}
