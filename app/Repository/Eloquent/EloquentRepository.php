<?php

namespace App\Repository\Eloquent;

use Illuminate\Database\Eloquent\Model;

/**
 * Camada de configuração de conexão do banco de dados.
 *
 * Estende AbstractEloquentRepository definindo qual conexão MySQL
 * o Model Eloquent deve utilizar. Serve como ponto de configuração
 * para clusters MySQL ou ambientes de teste.
 *
 * Em testes (APP_ENV=testing), a conexão é alterada automaticamente
 * para 'testing' (SQLite em memória), garantindo isolamento.
 *
 * Todos os repositórios concretos devem estender AbstractBaseRepository
 * (que estende esta classe), nunca esta diretamente.
 *
 * @see AbstractEloquentRepository — Implementação base do Repository
 * @see AbstractBaseRepository — Extensão com suporte a DataTables
 *
 * @author Rafael Rozgrin <rrozgrin@gmail.com>
 */
abstract class EloquentRepository extends AbstractEloquentRepository
{
    /**
     * Nome da conexão de banco de dados a ser utilizada.
     */
    protected string $connection = 'mysql';

    /**
     * Configura o model com a conexão apropriada ao ambiente.
     *
     * @param Model $model Instância do Model Eloquent do domínio
     */
    public function __construct(Model $model)
    {
        // Em ambiente de testes, usa conexão SQLite em memória
        if (config('app.env') === 'testing') {
            $this->connection = 'testing';
        }

        // Define a conexão no model antes de qualquer operação
        $model->setConnection($this->connection);
        $this->model = $model;

        parent::__construct();
    }
}
