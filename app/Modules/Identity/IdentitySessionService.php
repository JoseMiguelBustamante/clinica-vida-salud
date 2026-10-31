<?php

declare(strict_types=1);

namespace App\Modules\Identity;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

final class IdentitySessionService
{
    public function __construct(private IdentityRepository $identities) {}

    public function authenticate(string $identifier, string $password, string $ip): User
    {
        if (strlen($password) > 72) {
            throw new AuthenticationRejected;
        }

        $key = 'identity:'.hash('sha256', $identifier.$ip);
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw new TooManyLoginAttempts(RateLimiter::availableIn($key));
        }

        $matches = $this->identities->matching($identifier);
        $user = $matches->count() === 1 ? $matches->first() : null;
        if (! $this->verifyPassword($user, $password)) {
            RateLimiter::hit($key, 60);

            throw new AuthenticationRejected;
        }

        return $user;
    }

    public function representation(User $user): array
    {
        return $this->identities->representation($user);
    }

    public function forgetLoginAttempts(string $identifier, string $ip): void
    {
        RateLimiter::clear('identity:'.hash('sha256', $identifier.$ip));
    }

    private function verifyPassword(?User $user, string $password): bool
    {
        $dummyHash = Hash::make(bin2hex(random_bytes(32)));
        $eligible = $user !== null && $user->activo;
        $verified = Hash::check($password, $eligible ? $user->getAuthPassword() : $dummyHash);

        return $eligible && $verified;
    }
}
