<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Money\ActivateExactMoneyStorage;
use App\Services\Money\AuditExactMoneyStorage;
use App\Services\Money\RequireExactMoneyStorage;
use Illuminate\Console\Command;

use function json_encode;

use const JSON_PRETTY_PRINT;
use const JSON_THROW_ON_ERROR;

use RuntimeException;

final class ExactMoneyStorageCommand extends Command
{
    public function handle(AuditExactMoneyStorage $audit, ActivateExactMoneyStorage $activate, RequireExactMoneyStorage $ready): int
    {
        try {
            if ($this->option('check')) {
                $ready->execute();
                $this->info('Exact monetary storage is ready.');

                return self::SUCCESS;
            }
            $report = $this->option('activate')
                ? $activate->execute((string) $this->option('approve'))
                : $audit->execute();
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

        return $report['blocked'] ? self::FAILURE : self::SUCCESS;
    }

    protected $signature = 'money:storage {--check : Require activated MySQL decimal storage without modifying data} {--activate : Activate reviewed decimal columns while all writers are stopped} {--approve= : SHA-256 approval from the current audit}';

    protected $description = 'Audit or activate exact monetary storage without rebuilding financial history';
}
