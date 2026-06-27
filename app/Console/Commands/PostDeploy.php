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
            $this->call('migrate', ['--force' => true]);
        }

        $this->call('config:cache');
        $this->call('route:cache');
        $this->call('icons:cache');
        $this->call('event:cache');

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
