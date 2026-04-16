<?php

declare(strict_types=1);

namespace App\Services\Food99\Catalog;

use Throwable;
use App\Exceptions\ApiException;
use App\Services\Food99\Traits\InteractsWithFood99Api;
use App\Services\Auth\UsuarioLogadoService;
use App\Repository\Contracts\Models\Food99\Auth\Food99ShopRepositoryInterface;
use App\Repository\Contracts\Models\Food99\Catalog\Food99PublishJobRepositoryInterface;
use App\Repository\Contracts\Models\Food99\Catalog\Food99ShopItemRepositoryInterface;

/**
 * Servico de publicacao de catalogo na 99Food.
 *
 * Responsabilidades:
 * - Montar payload via Food99CatalogPayloadService
 * - Enviar catalogo para endpoint de upload da 99Food
 * - Registrar job de publicacao em food99_publish_jobs
 * - Atualizar publish_status nos itens publicados
 *
 * @author Rafael Rozgrin <rrozgrin@gmail.com>
 */
class Food99CatalogPublishService
{
    use InteractsWithFood99Api;

    /**
     * Montagem do payload de catalogo.
     */
    private readonly Food99CatalogPayloadService $payloadService;

    /**
     * Repositorio de jobs de publicacao.
     */
    private readonly Food99PublishJobRepositoryInterface $publishJobRepository;

    /**
     * Usuario autenticado via JWT.
     */
    private readonly UsuarioLogadoService $usuarioLogado;

    /**
     * Repositorio de lojas da 99Food.
     */
    private readonly Food99ShopRepositoryInterface $shopRepository;

    /**
     * Repositorio de itens da 99Food.
     */
    private readonly Food99ShopItemRepositoryInterface $shopItemRepository;

    public function __construct(
        Food99CatalogPayloadService $payloadService,
        Food99PublishJobRepositoryInterface $publishJobRepository,
        UsuarioLogadoService $usuarioLogado,
        Food99ShopRepositoryInterface $shopRepository,
        Food99ShopItemRepositoryInterface $shopItemRepository,
    ) {
        $this->payloadService = $payloadService;
        $this->publishJobRepository = $publishJobRepository;
        $this->usuarioLogado = $usuarioLogado;
        $this->shopRepository = $shopRepository;
        $this->shopItemRepository = $shopItemRepository;
    }

    /**
     * Publica o catalogo da loja na API da 99Food.
     *
     * Fluxo:
     * 1. Monta e valida payload via Food99CatalogPayloadService
     * 2. Verifica presenca do auth_token local
     * 3. Cria registro em food99_publish_jobs (status=pending)
     * 4. Envia payload para /v1/item/item/upload
     * 5. Em sucesso: marca itens como published, finaliza job
     * 6. Em falha: marca itens como failed, finaliza job e relanca excecao
     *
     * @param string             $appShopId          app_shop_id da loja
     * @param int|null           $triggeredByUserId  ID do usuario ERP Hub que disparou a publicacao
     * @param array<int, string> $appItemIds         Filtro opcional de itens para publicar
     *
     * @return array<string, mixed> Resultado da publicacao
     */
    public function publishCatalog(string $appShopId, ?int $triggeredByUserId, array $appItemIds = []): array
    {
        // 1. Monta e valida payload local (lanca 404/422 se catalogo invalido)
        $preview = $this->payloadService->buildUploadPayloadPreview(
            appShopId: $appShopId,
            appItemIdsFilter: $appItemIds,
        );

        // 2. Valida auth_token
        if (! (bool) ($preview['token_found'] ?? false)) {
            throw new ApiException(
                msg: 'Token de autenticacao nao encontrado para a loja. Execute food99/auth/token/get antes de publicar.',
                code: 422,
            );
        }

        $food99ShopId = (int) $preview['food99_shop_id'];

        // Payload que sera enviado para a API externa
        $catalogPayload = (array) ($preview['payload'] ?? []);
        $publishedAppItemIds = array_column((array) ($catalogPayload['items'] ?? []), 'app_item_id');
        $publishedItemPayloadMap = $this->mapPublishedItemPayloadByAppItemId(
            itemsPayload: (array) ($catalogPayload['items'] ?? []),
        );

        // Corpo da requisicao: credenciais + app_shop_id + catalogo
        $requestBody = array_merge(
            $this->food99Credentials(),
            ['app_shop_id' => $appShopId],
            $catalogPayload,
        );

        // 3. Cria job com status pending
        $job = $this->publishJobRepository->create([
            'food99_shop_id' => $food99ShopId,
            'event_type' => 'upload',
            'status' => 'pending',
            'triggered_by_user_id' => $triggeredByUserId,
            'started_at' => now(),
            'request_payload' => $requestBody,
        ]);

        $jobId = (int) ($job->id ?? 0);

        try {
            // 4. Envia para a 99Food
            $response = $this->food99Request(
                method: 'POST',
                path: '/v1/item/item/upload',
                json: $requestBody,
            );

            // 5. Atualiza publish_status dos itens
            $this->markItemsPublished(
                food99ShopId: $food99ShopId,
                appItemIds: $publishedAppItemIds,
                itemPayloadByAppItemId: $publishedItemPayloadMap,
            );

            // Finaliza job como sucesso
            if ($jobId > 0) {
                $this->publishJobRepository->update(
                    [
                        'status' => 'success',
                        'finished_at' => now(),
                        'response_payload' => $response,
                    ],
                    $jobId,
                );
            }

            return [
                'app_shop_id' => $appShopId,
                'job_id' => $jobId,
                'status' => 'success',
                'published_items' => count($publishedAppItemIds),
                'published_app_item_ids' => $publishedAppItemIds,
                'stats' => $preview['stats'],
                'response' => $response,
            ];
        } catch (Throwable $e) {
            // 6. Finaliza job como falha
            if ($jobId > 0) {
                $this->publishJobRepository->update(
                    [
                        'status' => 'failed',
                        'finished_at' => now(),
                        'error_message' => $e->getMessage(),
                    ],
                    $jobId,
                );
            }

            $this->markItemsFailed(
                food99ShopId: $food99ShopId,
                appItemIds: $publishedAppItemIds,
                errorMessage: $e->getMessage(),
            );

            throw $e;
        }
    }

    /**
     * Lista jobs de publicacao de uma loja.
     *
     * @param string $appShopId app_shop_id da loja
     * @param int    $limit     Numero maximo de jobs retornados
     *
     * @return array<string, mixed> Historico de jobs da loja
     */
    public function listJobs(string $appShopId, int $limit = 20): array
    {
        $shop = $this->resolveOwnedShopByAppShopId($appShopId);
        $food99ShopId = (int) $shop->id;

        $jobs = $this->publishJobRepository
            ->findLatestByShopId($food99ShopId, $limit)
            ->map(static fn (object $row): array => (array) $row)
            ->values()
            ->all();

        return [
            'app_shop_id' => $appShopId,
            'food99_shop_id' => $food99ShopId,
            'jobs' => $jobs,
        ];
    }

    /**
     * Atualiza publish_status para 'published' nos itens publicados com sucesso.
     *
     * @param int                      $food99ShopId         ID interno da loja
     * @param array<int, string>       $appItemIds           IDs externos dos itens publicados
     * @param array<string, array>     $itemPayloadByAppItemId Payload efetivamente enviado por item
     */
    private function markItemsPublished(int $food99ShopId, array $appItemIds, array $itemPayloadByAppItemId = []): void
    {
        $this->shopItemRepository->markPublishedByShopAndAppItemIds(
            food99ShopId: $food99ShopId,
            appItemIds: $appItemIds,
            itemPayloadByAppItemId: $itemPayloadByAppItemId,
        );
    }

    /**
     * Indexa payload de itens por app_item_id para persistencia em payload_snapshot.
     *
     * @param array<int, mixed> $itemsPayload Lista de itens enviada na publicacao
     *
     * @return array<string, array> [app_item_id => payload_do_item]
     */
    private function mapPublishedItemPayloadByAppItemId(array $itemsPayload): array
    {
        $payloadMap = [];

        foreach ($itemsPayload as $itemPayload) {
            if (! is_array($itemPayload)) {
                continue;
            }

            $appItemId = trim((string) data_get($itemPayload, 'app_item_id'));
            if ($appItemId === '') {
                continue;
            }

            $payloadMap[$appItemId] = $itemPayload;
        }

        return $payloadMap;
    }

    /**
     * Atualiza publish_status para 'failed' nos itens que falharam na publicacao.
     *
     * @param int             $food99ShopId ID interno da loja
     * @param array<int, string> $appItemIds   IDs externos dos itens
     * @param string          $errorMessage Mensagem de erro da falha
     */
    private function markItemsFailed(int $food99ShopId, array $appItemIds, string $errorMessage): void
    {
        $this->shopItemRepository->markFailedByShopAndAppItemIds(
            food99ShopId: $food99ShopId,
            appItemIds: $appItemIds,
            errorMessage: $errorMessage,
        );
    }

    /**
     * Resolve loja local do cliente autenticado.
     *
     * @param string $appShopId app_shop_id informado
     *
     * @return object Registro da loja
     */
    private function resolveOwnedShopByAppShopId(string $appShopId): object
    {
        $idCadastro = $this->resolveAuthenticatedIdCadastro();
        $shop = $this->shopRepository->findOwnedByAppShopId($idCadastro, $appShopId);

        if (! is_object($shop)) {
            throw new ApiException(
                msg: 'Loja nao encontrada para o cliente logado e app_shop_id informado.',
                code: 404,
            );
        }

        return $shop;
    }

    /**
     * Resolve o id_cadastro do usuario autenticado.
     *
     * @return int ID do cadastro logado
     */
    private function resolveAuthenticatedIdCadastro(): int
    {
        $idCadastro = $this->usuarioLogado->getIdCadastroLogado();
        if (! is_numeric($idCadastro) || (int) $idCadastro <= 0) {
            throw new ApiException(
                msg: 'Nao foi possivel identificar o id_cadastro do usuario logado.',
                code: 403,
            );
        }

        return (int) $idCadastro;
    }
}
