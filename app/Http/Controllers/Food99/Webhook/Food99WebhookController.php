<?php

declare(strict_types=1);

namespace App\Http\Controllers\Food99\Webhook;

use App\Http\Controllers\Controller;
use App\Services\Food99\Webhook\Food99WebhookService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class Food99WebhookController extends Controller
{
    /**
     * Recebe callbacks da 99Food.
     */
    public function receive(Request $request, Food99WebhookService $service): JsonResponse
    {
        $payload = $request->json()->all();
        if (! is_array($payload) || $payload === []) {
            return response()->json([
                'errno' => 4001,
                'errmsg' => 'invalid payload',
            ], 400);
        }

        $service->handle(
            payload: $payload,
            rawBody: (string) $request->getContent(),
            didiHeaderSign: $request->header('didi-header-sign'),
            headers: $request->headers->all(),
        );

        return response()->json([
            'errno' => 0,
            'errmsg' => 'ok',
        ]);
    }
}
