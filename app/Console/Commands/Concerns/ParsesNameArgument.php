<?php

namespace App\Console\Commands\Concerns;

/**
 * Trait para parsing de nomes com hierarquia de namespace.
 *
 * Interpreta nomes com separadores de namespace (\) para gerar
 * automaticamente a hierarquia de diretórios e namespaces corretos.
 *
 * Regra: os segmentos ANTES do último viram diretórios/namespace.
 * O último segmento é o nome da entidade (usado nos nomes de arquivo).
 * Para nomes simples (sem \), o entityName vira o diretório também.
 *
 * Exemplos de entrada → saída:
 *
 *   "Produto"
 *     → entityName:     "Produto"
 *     → subDirectory:   "Produto"
 *     → subNamespace:   "Produto"
 *     → Arquivo: .../Models/Produto/ProdutoRepositoryInterface.php
 *     → Namespace: App\...\Produto
 *
 *   "BaseErp\Produtos\Produto"
 *     → entityName:     "Produto"
 *     → subDirectory:   "BaseErp/Produtos"
 *     → subNamespace:   "BaseErp\Produtos"
 *     → Arquivo: .../Models/BaseErp/Produtos/ProdutoRepositoryInterface.php
 *     → Namespace: App\...\BaseErp\Produtos
 *
 * @author Rafael Rozgrin <rrozgrin@gmail.com>
 */
trait ParsesNameArgument
{
    /**
     * Analisa o argumento 'name' e retorna um objeto com as partes separadas.
     *
     * @param string $rawName Nome bruto recebido do argumento do comando
     *
     * @return object{
     *     entityName: string,
     *     subDirectory: string,
     *     subNamespace: string,
     *     modelNamespace: string,
     *     modelUseStatement: string,
     * }
     */
    private function parseName(string $rawName): object
    {
        // Normaliza separadores: / → \, \\ → \, remove \ iniciais/finais e espaços
        $normalized = str_replace('/', '\\', $rawName);
        $normalized = (string) preg_replace('/\\\\+/', '\\', $normalized);
        $normalized = trim($normalized, " \t\n\r\0\x0B\\");

        // Separa em partes pelo \
        $parts = explode('\\', $normalized);

        // Última parte é o nome da entidade (ex: Produto)
        $entityName = array_pop($parts);

        // Segmentos anteriores formam os subdiretórios
        // Para nomes simples (sem \), usamos o próprio entityName como diretório
        $subDirectory = count($parts) > 0
            ? implode('/', $parts)
            : $entityName;

        $subNamespace = str_replace('/', '\\', $subDirectory);

        // Namespace do Model (apenas os segmentos-pai)
        // Ex: BaseErp\Produtos → App\Models\BaseErp\Produtos
        // Ex: Produto (simples) → App\Models
        $modelNamespace = count($parts) > 0
            ? 'App\\Models\\' . implode('\\', $parts)
            : 'App\\Models';

        // Use statement completo do Model
        $modelUseStatement = $modelNamespace . '\\' . $entityName;

        return (object) [
            'entityName'        => $entityName,
            'subDirectory'      => $subDirectory,
            'subNamespace'      => $subNamespace,
            'modelNamespace'    => $modelNamespace,
            'modelUseStatement' => $modelUseStatement,
        ];
    }
}
