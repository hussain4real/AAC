<?php

namespace App\Support;

use Illuminate\Validation\ValidationException;

class EnterpriseReadiness
{
    /**
     * Whether self-service account registration is inside an approved window.
     */
    public function registrationEnabled(): bool
    {
        return (bool) config('maacc.readiness.registration_enabled', false);
    }

    /**
     * Whether authenticated users may create additional tenant teams.
     */
    public function teamCreationEnabled(): bool
    {
        return (bool) config('maacc.readiness.team_creation_enabled', false);
    }

    /**
     * Reject account registration while enterprise onboarding is frozen.
     *
     * @throws ValidationException
     */
    public function ensureRegistrationEnabled(): void
    {
        if (! $this->registrationEnabled()) {
            throw ValidationException::withMessages([
                'email' => $this->message(),
            ]);
        }
    }

    /**
     * Reject tenant creation while enterprise onboarding is frozen.
     *
     * @throws ValidationException
     */
    public function ensureTeamCreationEnabled(): void
    {
        if (! $this->teamCreationEnabled()) {
            throw ValidationException::withMessages([
                'name' => $this->message(),
            ]);
        }
    }

    /**
     * Shared, non-sensitive readiness state for console and auth surfaces.
     *
     * @return array{status: string, message: string, registrationEnabled: bool, teamCreationEnabled: bool, realSensitiveDataEnabled: bool, changeOwner: string|null}
     */
    public function sharedState(): array
    {
        $changeOwner = config('maacc.readiness.change_owner');

        return [
            'status' => (string) config('maacc.readiness.status', 'non_enterprise'),
            'message' => $this->message(),
            'registrationEnabled' => $this->registrationEnabled(),
            'teamCreationEnabled' => $this->teamCreationEnabled(),
            'realSensitiveDataEnabled' => (bool) config('maacc.readiness.real_sensitive_data_enabled', false),
            'changeOwner' => is_string($changeOwner) && $changeOwner !== '' ? $changeOwner : null,
        ];
    }

    private function message(): string
    {
        return (string) config(
            'maacc.readiness.message',
            'Enterprise onboarding and real sensitive-data onboarding are paused pending readiness approval.',
        );
    }
}
