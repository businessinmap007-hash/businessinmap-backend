<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserConsent;

/**
 * Records each new user's acceptance of the terms + privacy documents at their
 * current versions. Called once from the signup paths (web + API) so every
 * account carries a consent audit trail. Idempotent per (user, document, version).
 */
class LegalConsentService
{
    public function termsVersion(): string
    {
        return (string) config('legal.terms_version', '1');
    }

    public function privacyVersion(): string
    {
        return (string) config('legal.privacy_version', '1');
    }

    /** Record consent to the current terms + privacy for a freshly-created user. */
    public function recordSignupConsent(User $user, ?string $ip = null): void
    {
        $this->record($user, UserConsent::DOCUMENT_TERMS, $this->termsVersion(), $ip);
        $this->record($user, UserConsent::DOCUMENT_PRIVACY, $this->privacyVersion(), $ip);
    }

    private function record(User $user, string $document, string $version, ?string $ip): void
    {
        UserConsent::query()->firstOrCreate(
            ['user_id' => (int) $user->id, 'document' => $document, 'version' => $version],
            ['accepted_at' => now(), 'ip' => $ip],
        );
    }
}
