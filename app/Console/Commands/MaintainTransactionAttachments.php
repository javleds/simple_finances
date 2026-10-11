<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\TransactionAttachments\MaintainAttachments;
use Illuminate\Console\Command;

class MaintainTransactionAttachments extends Command
{
    protected $signature = 'attachments:maintain {--interval=0}';
    protected $description = 'Clean attachment storage and recover interrupted processing';

    public function handle(MaintainAttachments $maintain): int
    {
        $interval = max(0, (int) $this->option('interval'));
        do {
            $maintain->execute();
            if ($interval > 0) {
                sleep($interval);
            }
        } while ($interval > 0);
        return self::SUCCESS;
    }
}
