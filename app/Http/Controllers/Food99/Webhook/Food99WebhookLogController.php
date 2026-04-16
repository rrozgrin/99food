<?php

declare(strict_types=1);

namespace App\Http\Controllers\Food99\Webhook;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use App\Services\Auth\UsuarioLogadoService;
use App\Services\Food99\Webhook\Food99WebhookLogService;

class Food99WebhookLogController extends Controller
{
    /**
     * Lista logs de webhooks recebidos, escopado pelo usuario autenticado.
     */
    public function index(Request $request, Food99WebhookLogService $service, UsuarioLogadoService $usuarioLogado): JsonResponse
    {
        $validated = $request->validate([
            'event_names' => ['nullable', 'array', 'min:1'],
            'event_names.*' => ['required', 'string', 'in:orderNew,orderFinish,orderCancel,unknown'],
            'statuses' => ['nullable', 'array', 'min:1'],
            'statuses.*' => ['required', 'string', 'in:received,processed,failed'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:200'],
            'app_shop_id' => ['nullable', 'string', 'max:255'],
        ]);

        $idCadastro = $usuarioLogado->getIdCadastroLogado();
        if (! is_numeric($idCadastro) || (int) $idCadastro <= 0) {
            return response()->json([
                'errno' => 4031,
                'errmsg' => 'id_cadastro nao encontrado para o usuario autenticado',
            ], 403);
        }

        $conteudo = $service->listLogs(
            idCadastro: (int) $idCadastro,
            eventNames: (array) ($validated['event_names'] ?? []),
            statuses: (array) ($validated['statuses'] ?? []),
            limit: (int) ($validated['limit'] ?? 50),
            appShopId: isset($validated['app_shop_id']) ? (string) $validated['app_shop_id'] : null,
        );

        return response()->json(
            data: $this->send(conteudo: $conteudo),
        );
    }
}
