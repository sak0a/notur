<?php

declare(strict_types=1);

namespace Notur\FullExtension\Http\Controllers;

use Illuminate\View\View;

class AdminController
{
    public function index(): View
    {
        return view('notur-full-extension::admin.index', [
            'title' => 'Notur Full Example',
        ]);
    }
}
