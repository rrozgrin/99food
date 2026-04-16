<?php

namespace App\Console;

use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

/**
 * Kernel de Console personalizado da aplicação.
 *
 * Estende o Kernel padrão do Laravel para adicionar tradução
 * automática das descrições dos comandos Artisan para pt-BR.
 *
 * A tradução é feita via TranslatingCommandLoader, um decorator
 * que envolve o ContainerCommandLoader do Laravel e intercepta
 * cada comando carregado para aplicar a descrição traduzida.
 *
 * Por que um Kernel customizado?
 * Os comandos nativos do Laravel são registrados via deferred providers
 * (carregamento preguiçoso). Isso significa que callbacks como
 * Artisan::starting() são executados ANTES dos comandos serem
 * registrados, impossibilitando a tradução nesse ponto.
 * O Kernel customizado resolve isso ao decorar o command loader
 * APÓS a inicialização completa do Artisan.
 *
 * @see TranslatingCommandLoader — Decorator que aplica as traduções
 *
 * @author Rafael Rozgrin <rrozgrin@gmail.com>
 */
class Kernel extends ConsoleKernel
{
    /**
     * Habilita a auto-discovery de comandos em app/Console/Commands.
     *
     * Necessário porque o framework desativa auto-discovery quando
     * detecta um Kernel customizado (get_class($this) !== __CLASS__
     * no Kernel base). Sem isso, os comandos make:domain, make:dto,
     * make:repository e make:service não seriam encontrados.
     *
     * @return bool
     */
    protected function shouldDiscoverCommands(): bool
    {
        return true;
    }

    /**
     * Obtém a instância do Artisan com command loader traduzido.
     *
     * Sobrescreve o método original para, após a criação da instância
     * do Artisan (que inclui resolveCommands + setContainerCommandLoader),
     * envolver o command loader com o TranslatingCommandLoader.
     *
     * O wrapping só acontece uma vez graças ao cache em $this->artisan.
     *
     * @return \Illuminate\Console\Application
     */
    protected function getArtisan()
    {
        if (is_null($this->artisan)) {
            // Cria a instância padrão com todos os comandos e o loader
            parent::getArtisan();

            $traducoes = self::traducoes();

            // Acessa o command loader via reflection (propriedade privada no Symfony)
            $reflection = new \ReflectionProperty(
                \Symfony\Component\Console\Application::class,
                'commandLoader',
            );
            $loader = $reflection->getValue($this->artisan);

            if ($loader !== null && !$loader instanceof TranslatingCommandLoader) {
                $this->artisan->setCommandLoader(
                    new TranslatingCommandLoader(
                        inner: $loader,
                        translations: $traducoes,
                    )
                );
            }

            // Traduz comandos registrados diretamente (pacotes como
            // laravel/tinker, nunomaduro/collision — não passam pelo loader)
            $reflection = new \ReflectionProperty(
                \Symfony\Component\Console\Application::class,
                'commands',
            );
            $comandosDiretos = $reflection->getValue($this->artisan);

            foreach ($comandosDiretos as $nome => $comando) {
                if (isset($traducoes[$nome])) {
                    $comando->setDescription($traducoes[$nome]);
                }
            }
        }

        return $this->artisan;
    }

    /**
     * Mapa de traduções: nome do comando → descrição em pt-BR.
     *
     * Centraliza todas as traduções dos comandos Artisan nativos
     * do Laravel. Comandos customizados da aplicação (make:domain,
     * make:dto, etc.) já possuem descrições em pt-BR nas próprias
     * classes e não precisam de tradução aqui.
     *
     * @return array<string, string>
     */
    private static function traducoes(): array
    {
        return [
            // =================================================================
            // Comandos make:* (scaffolding)
            // =================================================================
            'make:cache-table'         => 'Cria uma migration para a tabela de cache do banco de dados',
            'make:cast'                => 'Cria uma nova classe de cast Eloquent personalizada',
            'make:channel'             => 'Cria uma nova classe de canal de broadcast',
            'make:class'               => 'Cria uma nova classe',
            'make:command'             => 'Cria um novo comando Artisan',
            'make:component'           => 'Cria uma nova classe de componente de view',
            'make:config'              => 'Cria um novo arquivo de configuração',
            'make:controller'          => 'Cria uma nova classe de controller',
            'make:enum'                => 'Cria um novo enum',
            'make:event'               => 'Cria uma nova classe de evento',
            'make:exception'           => 'Cria uma nova classe de exceção personalizada',
            'make:factory'             => 'Cria uma nova factory de model',
            'make:interface'           => 'Cria uma nova interface',
            'make:job'                 => 'Cria uma nova classe de job (fila)',
            'make:job-middleware'       => 'Cria um novo middleware de job',
            'make:listener'            => 'Cria uma nova classe de listener de evento',
            'make:mail'                => 'Cria uma nova classe de e-mail',
            'make:middleware'          => 'Cria uma nova classe de middleware HTTP',
            'make:migration'           => 'Cria um novo arquivo de migration',
            'make:model'               => 'Cria uma nova classe de model Eloquent',
            'make:notification'        => 'Cria uma nova classe de notificação',
            'make:notifications-table' => 'Cria uma migration para a tabela de notificações',
            'make:observer'            => 'Cria uma nova classe de observer',
            'make:policy'              => 'Cria uma nova classe de policy (autorização)',
            'make:provider'            => 'Cria uma nova classe de service provider',
            'make:queue-batches-table' => 'Cria uma migration para a tabela de batches da fila',
            'make:queue-failed-table'  => 'Cria uma migration para a tabela de jobs falhos da fila',
            'make:queue-table'         => 'Cria uma migration para a tabela de jobs da fila',
            'make:request'             => 'Cria uma nova classe de form request (validação)',
            'make:resource'            => 'Cria um novo resource de API',
            'make:rule'                => 'Cria uma nova regra de validação',
            'make:scope'               => 'Cria uma nova classe de scope Eloquent',
            'make:seeder'              => 'Cria uma nova classe de seeder',
            'make:session-table'       => 'Cria uma migration para a tabela de sessões',
            'make:test'                => 'Cria uma nova classe de teste',
            'make:trait'               => 'Cria uma nova trait',
            'make:view'                => 'Cria uma nova view Blade',

            // =================================================================
            // Servidor e console
            // =================================================================
            'serve'                    => 'Inicia o servidor de desenvolvimento do PHP',
            'tinker'                   => 'Abre o console interativo da aplicação (REPL)',
            'test'                     => 'Executa os testes da aplicação',
            'inspire'                  => 'Exibe uma citação inspiradora',

            // =================================================================
            // Banco de dados e migrations
            // =================================================================
            'migrate'                  => 'Executa as migrations pendentes do banco de dados',
            'migrate:fresh'            => 'Remove todas as tabelas e re-executa todas as migrations',
            'migrate:install'          => 'Cria a tabela de controle das migrations',
            'migrate:refresh'          => 'Reverte e re-executa todas as migrations',
            'migrate:reset'            => 'Reverte todas as migrations do banco de dados',
            'migrate:rollback'         => 'Reverte o último lote de migrations',
            'migrate:status'           => 'Exibe o status de cada migration',
            'db:seed'                  => 'Executa os seeders do banco de dados',
            'db:wipe'                  => 'Remove todas as tabelas, views e tipos do banco',
            'db:show'                  => 'Exibe informações sobre o banco de dados',
            'db:table'                 => 'Exibe informações sobre uma tabela do banco',
            'db:monitor'               => 'Monitora o número de conexões do banco de dados',

            // =================================================================
            // Rotas
            // =================================================================
            'route:list'               => 'Lista todas as rotas registradas da aplicação',
            'route:cache'              => 'Cria um arquivo de cache de rotas para registro mais rápido',
            'route:clear'              => 'Remove o arquivo de cache de rotas',

            // =================================================================
            // Configuração e cache
            // =================================================================
            'config:cache'             => 'Cria um arquivo de cache com todas as configurações',
            'config:clear'             => 'Remove o arquivo de cache de configurações',
            'config:show'              => 'Exibe o valor de uma configuração',
            'cache:clear'              => 'Limpa o cache da aplicação',
            'cache:forget'             => 'Remove um item do cache',
            'cache:prune-stale-tags'   => 'Remove tags de cache obsoletas (somente Redis)',
            'config:publish'           => 'Publica arquivos de configuração na aplicação',

            // =================================================================
            // Views
            // =================================================================
            'view:cache'               => 'Compila todos os templates Blade da aplicação',
            'view:clear'               => 'Limpa todos os templates Blade compilados',

            // =================================================================
            // Eventos
            // =================================================================
            'event:list'               => 'Lista os eventos e listeners da aplicação',
            'event:cache'              => 'Descobre e faz cache dos eventos e listeners da aplicação',
            'event:clear'              => 'Limpa todos os eventos e listeners em cache',

            // =================================================================
            // Filas (Queue)
            // =================================================================
            'queue:work'               => 'Inicia o processamento de jobs da fila',
            'queue:listen'             => 'Escuta uma fila específica',
            'queue:restart'            => 'Reinicia os workers da fila após o job atual',
            'queue:retry'              => 'Reprocessa um job que falhou',
            'queue:retry-batch'        => 'Reprocessa os jobs falhos de um batch',
            'queue:failed'             => 'Lista todos os jobs que falharam na fila',
            'queue:flush'              => 'Remove todos os jobs que falharam na fila',
            'queue:forget'             => 'Remove um job falho específico da fila',
            'queue:clear'              => 'Remove todos os jobs de uma fila específica',
            'queue:monitor'            => 'Monitora o tamanho das filas especificadas',
            'queue:pause'              => 'Pausa o processamento de jobs de uma fila específica',
            'queue:resume'             => 'Retoma o processamento de uma fila pausada',
            'queue:prune-batches'      => 'Remove entradas antigas da tabela de batches',
            'queue:prune-failed'       => 'Remove entradas antigas da tabela de jobs falhos',

            // =================================================================
            // Agendamento (Schedule)
            // =================================================================
            'schedule:list'            => 'Lista os comandos agendados',
            'schedule:run'             => 'Executa os comandos agendados',
            'schedule:work'            => 'Inicia o worker de agendamento',
            'schedule:test'            => 'Executa um comando agendado para teste',
            'schedule:clear-cache'     => 'Remove os arquivos de mutex criados pelo agendador',
            'schedule:interrupt'       => 'Interrompe a execução atual do agendador',
            'schedule:pause'           => 'Pausa o agendador',
            'schedule:resume'          => 'Retoma o agendador pausado',

            // =================================================================
            // Storage e arquivos
            // =================================================================
            'storage:link'             => 'Cria os links simbólicos de storage configurados',
            'storage:unlink'           => 'Remove os links simbólicos de storage',

            // =================================================================
            // Aplicação
            // =================================================================
            'key:generate'             => 'Gera a chave de criptografia da aplicação (APP_KEY)',
            'env:encrypt'              => 'Criptografa o arquivo .env',
            'env:decrypt'              => 'Descriptografa o arquivo .env',
            'optimize'                 => 'Otimiza o cache de configuração, rotas e eventos',
            'optimize:clear'           => 'Remove todos os caches de otimização',
            'about'                    => 'Exibe informações gerais sobre a aplicação',
            'clear-compiled'           => 'Remove o arquivo de classes compiladas',
            'down'                     => 'Coloca a aplicação em modo de manutenção',
            'up'                       => 'Retira a aplicação do modo de manutenção',

            // =================================================================
            // Demais comandos do framework
            // =================================================================
            'db'                       => 'Inicia uma sessão CLI do banco de dados',
            'docs'                     => 'Acessa a documentação do Laravel',
            'env'                      => 'Exibe o ambiente atual do framework',
            'pail'                     => 'Acompanha os logs da aplicação em tempo real',
            'reload'                   => 'Recarrega os serviços em execução',
            'completion'               => 'Gera o script de auto-complete para o shell',
            'help'                     => 'Exibe ajuda para um comando',
            'list'                     => 'Lista os comandos disponíveis',
            'auth:clear-resets'        => 'Remove tokens de reset de senha expirados',
            'channel:list'             => 'Lista todos os canais de broadcast privados registrados',
            'install:api'              => 'Cria o arquivo de rotas API e instala Laravel Sanctum ou Passport',
            'install:broadcasting'     => 'Cria o arquivo de rotas de canais de broadcast',
            'jwt:secret'               => 'Define a chave secreta JWT usada para assinar os tokens',
            'jwt:generate-certs'       => 'Gera um novo par de certificados JWT',
            'lang:publish'             => 'Publica os arquivos de idioma disponíveis para customização',
            'model:prune'              => 'Remove models que não são mais necessários',
            'model:show'               => 'Exibe informações sobre um model Eloquent',
            'package:discover'         => 'Reconstrói o manifesto de pacotes em cache',
            'schema:dump'              => 'Gera o dump do schema de um banco de dados',
            'stub:publish'             => 'Publica os stubs disponíveis para customização',
            'vendor:publish'           => 'Publica assets de pacotes vendor',
        ];
    }
}
