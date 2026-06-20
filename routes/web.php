<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('memory-space');
});

Route::view('/memory-space', 'memory-space')->name('memory-space');

Route::get('/demo-category-images/{image}', function (string $image) {
    $images = [
        'current-self.png' => 'tmp/slide_deck/slides/01_cover.png',
        'personality.png' => 'tmp/slide_deck/slides/05_privacy.png',
        'black-history.png' => 'tmp/slide_deck/slides/02_problem.png',
        'searching.png' => 'tmp/slide_deck/slides/03_solution.png',
        'turning-point.png' => 'tmp/slide_deck/slides/03_solution.png',
        'memoriru-birth.png' => 'tmp/slide_deck/assets/memory-space-ui.png',
        'business-mental-health.png' => 'tmp/slide_deck/slides/06_value.png',
        'life-events-family.png' => 'tmp/slide_deck/slides/07_close.png',
    ];

    abort_unless(isset($images[$image]), 404);

    return response()->file(base_path($images[$image]), [
        'Content-Type' => 'image/png',
    ]);
})->where('image', '[A-Za-z0-9_-]+\.png');

Route::get('/admin', function () {
    $stylesVersion = filemtime(base_path('docs/references/admin-ui-mockup/styles.css')) ?: time();
    $appVersion = filemtime(base_path('docs/references/admin-ui-mockup/app.js')) ?: time();
    $html = file_get_contents(base_path('docs/references/admin-ui-mockup/index.html'));
    $html = strtr($html, [
        'href="styles.css"' => 'href="/admin-assets/styles.css?v='.$stylesVersion.'"',
        'src="app.js"' => 'src="/admin-assets/app.js?v='.$appVersion.'"',
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
