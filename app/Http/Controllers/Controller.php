<?php

namespace App\Http\Controllers;

use App\Services\Traits\SendResponse;

/**
 * Controller base da aplicação.
 *
 * Todos os controllers da API devem estender esta classe.
 * A trait SendResponse é incluída aqui para que todos os controllers
 * tenham acesso ao método send() para respostas padronizadas.
 *
 * Exemplo de uso em um controller de domínio:
 *
 *     class ProdutosController extends Controller
 *     {
 *         public function index(ProdutosService $service): object
 *         {
 *             return $this->send(conteudo: $service->listarTodos());
 *         }
 *     }
 *
 * @see SendResponse — Trait de respostas padronizadas
 *
 * @author Rafael Rozgrin <rrozgrin@gmail.com>
 */
abstract class Controller
{
    use SendResponse;
}
