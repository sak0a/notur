<?php

declare(strict_types=1);

namespace Notur\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InstalledExtension extends Model
{
    protected $table = 'notur_extensions';

    protected $fillable = [
        'extension_id',
        'name',
        'version',
        'enabled',
        'manifest',
        'source',
        'pushed_via_key_id',
        'last_pushed_at',
        'last_push_error',
        'package_checksum',
        'package_size',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'manifest' => 'array',
        'last_pushed_at' => 'datetime',
        'package_size' => 'integer',
    ];

    public function pushedViaKey(): BelongsTo
    {
        return $this->belongsTo(RemotePushApiKey::class, 'pushed_via_key_id');
    }
}
