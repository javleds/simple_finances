<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class PostDeploy extends Command
{
    protected $signature = 'app:post-deploy {--migrate : Run database migrations without asking for confirmation}';

    protected $description = 'Command description';

    public function handle(): int
    {
        if ($this->shouldRunMigrations()) {
            $status = $this->call('migrate', ['--force' => true]);

            if ($status !== self::SUCCESS) {
                return $status;
            }
        }

        foreach (['config:cache', 'route:cache', 'event:cache'] as $command) {
            $status = $this->call($command);

            if ($status !== self::SUCCESS) {
                return $status;
            }
        }

        return self::SUCCESS;
    }

    private function shouldRunMigrations(): bool
    {
        if ((bool) $this->option('migrate')) {
            return true;
        }

        return $this->confirm('Do you want to run database migrations?', false);
    }
}
