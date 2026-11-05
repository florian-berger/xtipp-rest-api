<?php
namespace FlorianBerger\RestApi\Services;

use App\Models\User;
use Carbon\CarbonInterface;
use FlorianBerger\RestApi\Models\ApiKey;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * Create, revoke, regenerate, delete and find the API key of a user
 */
class ApiKeyService {
    /** Prefix of every key, so that a leaked key can be recognized (e.g. by secret scanners) */
    public const KEY_PREFIX = 'xtk_';

    /** Length of the random part of the key (32 random bytes as base64url without padding) */
    private const RANDOM_LENGTH = 43;

    /** Characters of the key which are shown on the key page, after the prefix */
    private const DISPLAY_LENGTH = 4;

    /** Minimum seconds between two updates of "last_used_at" */
    private const TOUCH_INTERVAL = 60;

    /**
     * Hash of a key, which is stored. The key is random with 256 bit, so a fast hash is enough.
     *
     * @param string $key
     *
     * @return string
     */
    public function hash(string $key): string {
        return hash('sha256', $key);
    }

    /**
     * Check the format of a key without touching the database
     *
     * @param string $key
     *
     * @return bool
     */
    public function hasValidFormat(string $key): bool {
        return strlen($key) === strlen(self::KEY_PREFIX) + self::RANDOM_LENGTH
            && str_starts_with($key, self::KEY_PREFIX)
            && preg_match('/^[A-Za-z0-9_-]+$/', substr($key, strlen(self::KEY_PREFIX))) === 1;
    }

    /**
     * Generate a new random key
     *
     * @return string
     */
    private function generateKey(): string {
        return self::KEY_PREFIX . rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    }

    /**
     * Get the key of a user
     *
     * @param User $user
     *
     * @return ApiKey|null
     */
    public function findForUser(User $user): ?ApiKey {
        return ApiKey::where('user_id', $user->id)->first();
    }

    /**
     * Find the key entry of a key sent by a client
     *
     * @param string $key
     *
     * @return ApiKey|null
     */
    public function findByKey(string $key): ?ApiKey {
        if (!$this->hasValidFormat($key)) {
            return null;
        }

        return ApiKey::where('key_hash', $this->hash($key))->first();
    }

    /**
     * Create the key of a user
     *
     * @param User                 $user
     * @param CarbonInterface|null $expiresAt
     *
     * @return string|null The key (only available now) or null, if the user has a key already
     */
    public function create(User $user, ?CarbonInterface $expiresAt = null): ?string {
        $key = $this->generateKey();

        try {
            ApiKey::create([
                'user_id'    => $user->id,
                'key_prefix' => substr($key, 0, strlen(self::KEY_PREFIX) + self::DISPLAY_LENGTH),
                'key_hash'   => $this->hash($key),
                'expires_at' => $expiresAt,
            ]);
        } catch (UniqueConstraintViolationException) {
            // Unique index on "user_id": also a double click or a parallel request creates only one key
            return null;
        }

        return $key;
    }

    /**
     * Replace the secret of the key of a user, the old secret stops working at once
     *
     * @param User                 $user
     * @param CarbonInterface|null $expiresAt New expiry, null keeps the key without expiry
     *
     * @return string|null The new key (only available now) or null, if the user has no key
     */
    public function regenerate(User $user, ?CarbonInterface $expiresAt = null): ?string {
        $apiKey = $this->findForUser($user);
        if ($apiKey === null) {
            return null;
        }

        $key = $this->generateKey();
        $apiKey->forceFill([
            'key_prefix'   => substr($key, 0, strlen(self::KEY_PREFIX) + self::DISPLAY_LENGTH),
            'key_hash'     => $this->hash($key),
            'expires_at'   => $expiresAt,
            'revoked_at'   => null,
            'last_used_at' => null,
        ])->save();

        return $key;
    }

    /**
     * Revoke the key of a user, it stays visible
     *
     * @param User $user
     *
     * @return bool False, if the user has no key
     */
    public function revoke(User $user): bool {
        $apiKey = $this->findForUser($user);
        if ($apiKey === null) {
            return false;
        }

        if (!$apiKey->isRevoked()) {
            $apiKey->forceFill(['revoked_at' => now()])->save();
        }

        return true;
    }

    /**
     * Delete the key of a user
     *
     * @param User $user
     *
     * @return bool False, if the user has no key
     */
    public function delete(User $user): bool {
        return ApiKey::where('user_id', $user->id)->delete() > 0;
    }

    /**
     * Remember the use of a key, at most once per minute to avoid a write on every call
     *
     * @param ApiKey $apiKey
     *
     * @return void
     */
    public function touch(ApiKey $apiKey): void {
        if ($apiKey->last_used_at === null || $apiKey->last_used_at->diffInSeconds(now(), true) >= self::TOUCH_INTERVAL) {
            ApiKey::where('id', $apiKey->id)->update(['last_used_at' => now()]);
        }
    }
}
