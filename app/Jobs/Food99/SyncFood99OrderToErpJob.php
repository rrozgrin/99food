<?php

declare(strict_types=1);

namespace App\Jobs\Food99;

use Throwable;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Services\Food99\Orders\Food99OrderErpSyncService;

class SyncFood99OrderToErpJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    //variável para controlar o número de tentativas em caso de falha
    public int $tries = 3;

    //variável para controlar o tempo máximo de execução do job
    public int $timeout = 120;

    public function __construct(
        public readonly int $food99OrderId,
    ) {
        $this->onQueue('food99-orders');
    }

    public function handle(Food99OrderErpSyncService $syncService): void
    {
        $syncService->syncOrderById($this->food99OrderId);
    }

    public function failed(Throwable $throwable): void
    {
        Log::error('food99.order_sync.job_failed', [
            'food99_order_id' => $this->food99OrderId,
            'message' => $throwable->getMessage(),
        ]);
    }
}

