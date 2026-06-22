<?php

namespace App\Console\Commands;

use App\Support\Sdk\ContractFixtures;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Generates (or, with --check, verifies) the shared SDK contract fixture suite
 * at packages/sdk-fixtures/contract.json from MAAC's own logic (Phase 6C).
 *
 * `--check` is the CI tripwire: it fails when the committed fixtures drift from
 * what MAAC currently produces, so a server-side response-shape or rule change
 * cannot land without regenerating the fixtures — which then fails any SDK
 * language that has not been updated to match.
 */
class GenerateSdkFixtures extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'maac:sdk-fixtures {--check : Fail if the committed fixtures are out of date instead of writing them}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate or verify the shared SDK contract fixture suite (packages/sdk-fixtures/contract.json)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $path = base_path('packages/sdk-fixtures/contract.json');
        $fresh = ContractFixtures::toJson();

        if ($this->option('check')) {
            $current = File::exists($path) ? File::get($path) : null;

            if ($current === $fresh) {
                $this->info('SDK contract fixtures are up to date.');

                return self::SUCCESS;
            }

            $this->error('SDK contract fixtures are out of date. Run `php artisan maac:sdk-fixtures` and commit the result.');

            return self::FAILURE;
        }

        File::ensureDirectoryExists(dirname($path));
        File::put($path, $fresh);

        $this->info('Wrote SDK contract fixtures to packages/sdk-fixtures/contract.json.');

        return self::SUCCESS;
    }
}
