<?php

declare(strict_types=1);

namespace Notur\FullExtension\Http\Controllers;

use Illuminate\Http\JsonResponse;

class ApiController
{
    public function status(): JsonResponse
    {
        return response()->json([
            'ok' => true,
            'extension' => 'notur/full-extension',
        ]);
    }
}
