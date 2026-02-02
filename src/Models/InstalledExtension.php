<?php

declare(strict_types=1);

namespace Notur\Models;

use Illuminate\Database\Eloquent\Model;

class InstalledExtension extends Model
{
    protected $table = 'notur_extensions';

    protected $fillable = [
        'extension_id',
        'name',
        'version',
        'enabled',
        'manifest',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'manifest' => 'array',
    ];
}
