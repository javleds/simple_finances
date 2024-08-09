<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class PostDeploy extends Command
{
    protected $signature = 'app:post-deploy {--migrate}';

    protected $description = 'Command description';

    public function handle(): void
    {
        $this->call('migrate');
        $this->call('config:cache');
        $this->call('route:cache');
        $this->call('icons:cache');
        $this->call('event:cache');
        $this->call('filament:cache-components');
    }
}
