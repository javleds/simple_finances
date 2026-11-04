<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Legal\GetLegalDocuments;
use Illuminate\Console\Command;

class ChatLegalHashes extends Command
{
    public function handle(GetLegalDocuments $documents): int
    {
        $result = $documents->execute();
        if (! $result['available']) {
            $this->error('Legal documents are not configured.');

            return self::FAILURE;
        }
        $this->line('MCP_LEGAL_TERMS_HASH=' . $result['documents']['terms']['hash']);
        $this->line('MCP_LEGAL_PRIVACY_HASH=' . $result['documents']['privacy']['hash']);

        return self::SUCCESS;
    }

    protected $signature = 'chat:legal-hashes';

    protected $description = 'Print current legal document hashes required by the private MCP service';
}
