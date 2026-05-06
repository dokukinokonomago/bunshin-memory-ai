<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('memory-space');
});

Route::view('/memory-space', 'memory-space')->name('memory-space');

Route::get('/admin', function () {
    $html = file_get_contents(base_path('docs/references/admin-ui-mockup/index.html'));
    $html = strtr($html, [
        'href="styles.css"' => 'href="/admin-assets/styles.css"',
        'src="app.js"' => 'src="/admin-assets/app.js"',
    ]);

    return response($html)->header('Content-Type', 'text/html; charset=UTF-8');
})->name('admin');

Route::get('/admin-assets/{asset}', function (string $asset) {
    $contentTypes = [
        'app.js' => 'application/javascript; charset=UTF-8',
        'styles.css' => 'text/css; charset=UTF-8',
    ];

    return response()
        ->file(base_path("docs/references/admin-ui-mockup/{$asset}"), [
            'Content-Type' => $contentTypes[$asset],
        ]);
})->where('asset', 'styles\.css|app\.js');
