<?php

namespace App\Console\Commands;

use App\Support\Governance\RetentionPruner;
use Illuminate\Console\Command;

/**
 * Redacts run payloads and deletes audit events past their governance retention
 * windows. Scheduled daily (see routes/console.php).
 */
class PruneRunDataCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'maac:prune-run-data';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Redact run payloads and delete audit events past their governance retention windows';

    /**
     * Execute the console command.
     */
    public function handle(RetentionPruner $pruner): int
    {
        $result = $pruner->prune();

        $this->info("Redacted {$result['runs']} run payload field(s); deleted {$result['audits']} audit event(s).");

        return self::SUCCESS;
    }
}
