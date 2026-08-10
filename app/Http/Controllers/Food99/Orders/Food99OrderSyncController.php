<?php

declare(strict_types=1);

namespace App\Http\Controllers\Food99\Orders;

use App\Http\Controllers\Controller;
use App\Services\Auth\UsuarioLogadoService;
use App\Services\Food99\Orders\Food99OrderErpSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class Food99OrderSyncController extends Controller
{
    public function syncQueue(Request $request, Food99OrderErpSyncService $service, UsuarioLogadoService $usuarioLogado): JsonResponse
    {
        $validated = $request->validate([
            'limit' => ['nullable', 'integer', 'min:1', 'max:200'],
            'app_shop_id' => ['nullable', 'string', 'max:255'],
        ]);

        $idCadastro = $usuarioLogado->getIdCadastroLogado();
        if (! is_numeric($idCadastro) || (int) $idCadastro <= 0) {
            return response()->json(
                data: $this->send(
                    conteudo: null,
                    code: 403,
                    msg: 'id_cadastro nao encontrado para o usuario autenticado',
                ),
                status: 403,
            );
        }

        $conteudo = $service->listSyncQueue(
            idCadastro: (int) $idCadastro,
            limit: (int) ($validated['limit'] ?? 50),
            appShopId: isset($validated['app_shop_id']) ? (string) $validated['app_shop_id'] : null,
        );

        return response()->json(
            data: $this->send(conteudo: $conteudo),
        );
    }

    public function show(
        int $food99OrderId,
        Food99OrderErpSyncService $service,
        UsuarioLogadoService $usuarioLogado,
    ): JsonResponse {
        $idCadastro = $usuarioLogado->getIdCadastroLogado();
        if (! is_numeric($idCadastro) || (int) $idCadastro <= 0) {
            return response()->json(
                data: $this->send(
                    conteudo: null,
                    code: 403,
                    msg: 'id_cadastro nao encontrado para o usuario autenticado',
                ),
                status: 403,
            );
        }

        $conteudo = $service->getOrderDetail(
            food99OrderId: $food99OrderId,
            idCadastro: (int) $idCadastro,
        );

        return response()->json(
            data: $this->send(conteudo: $conteudo),
        );
    }

    public function reprocess(
        int $food99OrderId,
        Food99OrderErpSyncService $service,
        UsuarioLogadoService $usuarioLogado,
    ): JsonResponse {
        $idCadastro = $usuarioLogado->getIdCadastroLogado();
        if (! is_numeric($idCadastro) || (int) $idCadastro <= 0) {
            return response()->json(
                data: $this->send(
                    conteudo: null,
                    code: 403,
                    msg: 'id_cadastro nao encontrado para o usuario autenticado',
                ),
                status: 403,
            );
        }

        $conteudo = $service->queueReprocess(
            food99OrderId: $food99OrderId,
            idCadastro: (int) $idCadastro,
        );

        return response()->json(
            data: $this->send(conteudo: $conteudo),
        );
    }
}
