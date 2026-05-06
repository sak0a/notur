<?php

declare(strict_types=1);

namespace Notur\Models;

use Illuminate\Database\Eloquent\Model;

class RemotePushApiKey extends Model
{
    protected $table = 'notur_remote_push_keys';

    protected $fillable = [
        'name',
        'prefix',
        'token_hash',
        'last_used_at',
        'last_used_ip',
        'revoked_at',
        'created_by_user_id',
    ];

    protected $casts = [
        'last_used_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    public static function generatePlaintext(): string
    {
        $random = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');

        return 'notur_' . $random;
    }

    public static function hashToken(string $plaintext): string
    {
        return hash('sha256', $plaintext);
    }

    public static function prefixOf(string $plaintext): string
    {
        return substr($plaintext, 0, 12);
    }

    public function isActive(): bool
    {
        return $this->revoked_at === null;
    }
}
