<?php

namespace App\Http\Controllers\Api\Webhooks;

use App\Http\Controllers\Controller;
use App\Services\Payments\SePayWebhookHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SePayWebhookController extends Controller
{
    public function __construct(
        private readonly SePayWebhookHandler $handler
    ) {
    }

    /**
     * IPN SePay — POST JSON theo tài liệu; trả 200/201 + {"success": true}.
     *
     * @see https://docs.sepay.vn/tich-hop-webhooks.html
     */
    public function __invoke(Request $request): JsonResponse
    {
        $secretKey = config('sepay.secret_key');
        if (is_string($secretKey) && $secretKey !== '') {
            $headerSecret = $request->header('X-Secret-Key');
            if (!is_string($headerSecret) || trim($headerSecret) !== trim($secretKey)) {
                return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
            }
        }

        $apiKey = config('sepay.webhook_api_key');
        if ($secretKey === null || $secretKey === '') {
            if (is_string($apiKey) && $apiKey !== '') {
                $auth = (string) $request->header('Authorization', '');
                $xApiKey = (string) $request->header('X-Api-Key', '');
                $expectedAuth = 'apikey ' . strtolower(trim($apiKey));
                $normalizedAuth = strtolower(trim($auth));

                if (
                    $normalizedAuth !== $expectedAuth
                    && strtolower(trim($xApiKey)) !== strtolower(trim($apiKey))
                    && $normalizedAuth !== strtolower(trim($apiKey))
                ) {
                    return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
                }
            }
        }

        $payload = $request->json()->all();
        if (!is_array($payload)) {
            $payload = $request->all();
        }

        $result = $this->handler->handle($payload);

        return response()->json([
            'success' => true,
            'result' => $result,
        ], 200);
    }
}
