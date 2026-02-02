<?php

declare(strict_types=1);

namespace Notur\Models;

use Illuminate\Database\Eloquent\Model;

class ExtensionMigration extends Model
{
    protected $table = 'notur_migrations';

    protected $fillable = [
        'extension_id',
        'migration',
        'batch',
    ];

    public $timestamps = false;
}
