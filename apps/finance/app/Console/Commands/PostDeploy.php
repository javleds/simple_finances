<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;

class PostDeploy extends Command
{
    public function handle(): int
    {
        if ($this->shouldRunMigrations()) {
            $status = $this->call('migrate', ['--force' => true]);

            if (self::SUCCESS !== $status) {
                return $status;
            }
        }

        $status = $this->call('money:storage', ['--check' => true]);
        if (self::SUCCESS !== $status) {
            return $status;
        }

        foreach (['view:clear', 'config:cache', 'route:cache', 'event:cache'] as $command) {
            $status = $this->call($command);

            if (self::SUCCESS !== $status) {
                return $status;
            }
        }

        return self::SUCCESS;
    }

    protected $signature = 'app:post-deploy {--migrate : Run database migrations without asking for confirmation}';

    protected $description = 'Command description';

    private function shouldRunMigrations(): bool
    {
        if ((bool) $this->option('migrate')) {
            return true;
        }

        return $this->confirm('Do you want to run database migrations?', false);
    }
}
