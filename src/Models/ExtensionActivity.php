<?php

declare(strict_types=1);

namespace Notur\Models;

use Illuminate\Database\Eloquent\Model;

class ExtensionActivity extends Model
{
    protected $table = 'notur_activity_logs';

    protected $fillable = [
        'extension_id',
        'action',
        'summary',
        'context',
    ];

    protected $casts = [
        'context' => 'array',
    ];
}
