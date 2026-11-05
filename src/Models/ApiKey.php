<?php
namespace FlorianBerger\RestApi\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The API key of a user (one per user), only its hash is stored
 *
 * @property int                             $id
 * @property int                             $user_id
 * @property string                          $key_prefix
 * @property string                          $key_hash
 * @property \Illuminate\Support\Carbon|null $last_used_at
 * @property \Illuminate\Support\Carbon|null $expires_at
 * @property \Illuminate\Support\Carbon|null $revoked_at
 */
class ApiKey extends Model {
    /** @var string */
    protected $table = 'plugin_rest_api_keys';

    /** @var string[] */
    protected $fillable = ['user_id', 'key_prefix', 'key_hash', 'last_used_at', 'expires_at', 'revoked_at'];

    /** @var string[] */
    protected $casts = [
        'last_used_at' => 'datetime',
        'expires_at'   => 'datetime',
        'revoked_at'   => 'datetime',
    ];

    /**
     * Owner of the key, also a deleted user, so that the key can be rejected
     *
     * @return BelongsTo
     */
    public function user(): BelongsTo {
        return $this->belongsTo(User::class)->withTrashed();
    }

    /**
     * Check, if the key was revoked
     *
     * @return bool
     */
    public function isRevoked(): bool {
        return $this->revoked_at !== null;
    }

    /**
     * Check, if the key is expired
     *
     * @return bool
     */
    public function isExpired(): bool {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    /**
     * Check, if the key can be used
     *
     * @return bool
     */
    public function isActive(): bool {
        return !$this->isRevoked() && !$this->isExpired();
    }

    /**
     * Status for the key page
     *
     * @return string "active", "revoked" or "expired"
     */
    public function status(): string {
        return $this->isRevoked() ? 'revoked' : ($this->isExpired() ? 'expired' : 'active');
    }
}
