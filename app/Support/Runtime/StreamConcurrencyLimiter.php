<?php

namespace App\Support\Runtime;

use Illuminate\Contracts\Cache\Lock;
use Illuminate\Support\Facades\Cache;

/**
 * Bounded, crash-safe stream slots scoped to one SDK application. Cache locks
 * provide atomic admission and expire if a worker dies before releasing one.
 */
class StreamConcurrencyLimiter
{
    public function acquire(string $applicationId): ?Lock
    {
        $limit = max(1, (int) config('maacc.runtime.stream.max_concurrent_per_application', 5));
        $ttl = max(5, (int) ceil((float) config('maacc.runtime.stream.max_seconds', 60)) + 10);

        for ($slot = 1; $slot <= $limit; $slot++) {
            $lock = Cache::lock("maacc:stream:{$applicationId}:{$slot}", $ttl);

            if ($lock->get()) {
                return $lock;
            }
        }

        return null;
    }
}
