<?php

namespace App\Support\Runtime\Db;

use App\Models\DataSource;
use App\Support\Runtime\ToolExecutionException;
use App\Support\Secrets\Contracts\SecretVault;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Facades\Config;
use Throwable;

/**
 * Resolves the read-only database connection a {@see DataSource} queries through.
 *
 * The source references an approved, ops-provisioned connection by name (a
 * replica or reporting schema configured out-of-band in `config/database.php`) —
 * MAAC never stores a connection string or host. When the source binds a vault
 * secret, its credential is read from the vault and injected over the base
 * connection at query time, so a central rotation takes effect on the next query
 * without MAAC persisting the password. The referenced connection must exist; an
 * unconfigured name is a controlled misconfiguration.
 */
class DataSourceConnection
{
    public function __construct(
        private readonly SecretVault $vault,
        private readonly DatabaseManager $manager,
    ) {}

    /**
     * Resolve the connection to query the data source through.
     *
     * @throws ToolExecutionException
     */
    public function resolve(DataSource $source): ConnectionInterface
    {
        $base = Config::get("database.connections.{$source->connection}");

        if (! is_array($base)) {
            throw ToolExecutionException::dbMisconfigured(
                "The data source [{$source->slug}] references an unconfigured connection [{$source->connection}].",
            );
        }

        $credential = $source->resolveCredential($this->vault);

        if ($credential === null) {
            return $this->established($source->connection);
        }

        // Inject the vault-resolved credential over the approved base connection
        // and reconnect, so MAAC never persists the password and a rotation is
        // picked up on the next query.
        $name = $source->ephemeralConnectionName();
        Config::set("database.connections.{$name}", [...$base, 'password' => $credential]);
        $this->manager->purge($name);

        return $this->established($name);
    }

    /**
     * Resolve the named connection and force it to connect now, so a connection
     * failure surfaces as a controlled {@see ToolExecutionException} rather than
     * a raw error mid-query.
     *
     * @throws ToolExecutionException
     */
    private function established(string $name): ConnectionInterface
    {
        $connection = $this->manager->connection($name);

        try {
            $connection->getPdo();
        } catch (Throwable $exception) {
            throw ToolExecutionException::dbConnectionFailed($exception->getMessage());
        }

        return $connection;
    }
}
