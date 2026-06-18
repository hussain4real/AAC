<?php

namespace App\Support\Sdk;

use App\Enums\Environment;
use App\Http\Middleware\AuthenticateSdkClient;
use App\Models\Application;
use App\Models\Credential;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * The authenticated SDK caller context resolved by {@see AuthenticateSdkClient}
 * and shared with the SDK/runtime controllers.
 */
final readonly class SdkContext
{
    public function __construct(
        public Credential $credential,
        public Application $application,
        public Environment $environment,
    ) {}

    /**
     * Resolve the SDK context bound to the current request.
     *
     * @throws RuntimeException when the request was not authenticated by the SDK middleware
     */
    public static function fromRequest(Request $request): self
    {
        $context = $request->attributes->get('sdk_context');

        if (! $context instanceof self) {
            throw new RuntimeException('No SDK context is bound to the request. Did the sdk.auth middleware run?');
        }

        return $context;
    }
}
