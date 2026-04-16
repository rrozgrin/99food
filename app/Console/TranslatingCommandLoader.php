<?php

namespace App\Console;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\CommandLoader\CommandLoaderInterface;

/**
 * Decorator que envolve o CommandLoader padrão do Laravel
 * e traduz as descrições dos comandos para pt-BR sob demanda.
 *
 * Funciona como um proxy transparente: quando o Symfony solicita
 * um comando via get(), o loader original resolve o comando e,
 * se houver tradução mapeada, a descrição é atualizada antes
 * de retornar a instância.
 *
 * Essa abordagem é necessária porque os comandos nativos do Laravel
 * são carregados via deferred providers (lazy loading), portanto
 * não estão disponíveis nos callbacks Artisan::starting().
 *
 * @see \Illuminate\Console\ContainerCommandLoader — Loader original do Laravel
 * @see Kernel — Registra este decorator em getArtisan()
 *
 * @author Rafael Rozgrin <rrozgrin@gmail.com>
 */
class TranslatingCommandLoader implements CommandLoaderInterface
{
    /**
     * @param CommandLoaderInterface $inner       Loader original a ser decorado
     * @param array<string, string>  $translations Mapa [nome_comando => descrição_ptBR]
     */
    public function __construct(
        private readonly CommandLoaderInterface $inner,
        private readonly array $translations,
    ) {}

    /**
     * Carrega um comando pelo nome e aplica tradução se disponível.
     *
     * @param string $name Nome do comando (ex: 'make:model')
     * @return Command Instância do comando com descrição traduzida
     */
    public function get(string $name): Command
    {
        $command = $this->inner->get($name);

        if (isset($this->translations[$name])) {
            $command->setDescription($this->translations[$name]);
        }

        return $command;
    }

    /**
     * Verifica se o loader possui o comando especificado.
     *
     * Delega diretamente ao loader original.
     *
     * @param string $name Nome do comando
     * @return bool
     */
    public function has(string $name): bool
    {
        return $this->inner->has($name);
    }

    /**
     * Retorna todos os nomes de comandos disponíveis no loader.
     *
     * Delega diretamente ao loader original.
     *
     * @return string[]
     */
    public function getNames(): array
    {
        return $this->inner->getNames();
    }
}
