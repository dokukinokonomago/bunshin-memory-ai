<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>記憶の宇宙 - {{ config('app.name', '分身AI') }}</title>

        @vite(['resources/css/memory-space.css', 'resources/js/memory-space.js'])
    </head>
    <body>
        <main id="memory-space-root" class="memory-space" data-api-base="/api/v1">
            <canvas id="memory-space-canvas" class="memory-space__canvas" aria-label="記憶の宇宙"></canvas>

            <button
                id="controls-toggle"
                class="panel-toggle panel-toggle--controls"
                type="button"
                aria-controls="memory-space-controls"
                aria-expanded="false"
            >操作</button>

            <button
                id="list-toggle"
                class="panel-toggle panel-toggle--list"
                type="button"
                aria-controls="memory-list"
                aria-expanded="false"
            >記憶</button>

            <section id="memory-space-controls" class="memory-space__toolbar" aria-label="API controls" hidden>
                <div class="field field--base">
                    <label for="api-base">API</label>
                    <input id="api-base" name="api-base" type="text" value="/api/v1" autocomplete="off">
                </div>
                <div class="field field--token">
                    <label for="api-token">Bearer</label>
                    <input id="api-token" name="api-token" type="password" autocomplete="off">
                </div>
                <div class="field">
                    <label for="period-filter">年代</label>
                    <select id="period-filter" name="period-filter">
                        <option value="">すべて</option>
                    </select>
                </div>
                <div class="field">
                    <label for="category-filter">カテゴリ</label>
                    <select id="category-filter" name="category-filter">
                        <option value="">すべて</option>
                    </select>
                </div>
                <label class="check-field" for="include-descendants">
                    <input id="include-descendants" type="checkbox" checked>
                    <span>配下</span>
                </label>
                <button id="load-space" class="button button--primary" type="button">同期</button>
                <button id="unlock-secret" class="button" type="button">解除</button>
            </section>

            <section id="space-status" class="memory-space__status" aria-live="polite"></section>

            <aside class="memory-space__summary" aria-label="Memory summary">
                <div class="summary-metric">
                    <span id="metric-category-count">0</span>
                    <small>カテゴリ</small>
                </div>
                <div class="summary-metric">
                    <span id="metric-memory-count">0</span>
                    <small>記憶</small>
                </div>
                <div class="summary-metric">
                    <span id="metric-secret-count">0</span>
                    <small>locked</small>
                </div>
            </aside>

            <aside id="memory-list" class="memory-space__list" aria-label="Memory list" hidden></aside>

            <aside id="memory-detail" class="memory-space__detail" aria-label="Memory detail" hidden>
                <button id="detail-close" class="detail-close" type="button" aria-label="Close detail">×</button>
                <div id="detail-crumb" class="detail-crumb"></div>
                <h1 id="detail-title" class="detail-title"></h1>
                <p id="detail-body" class="detail-body"></p>
                <div id="detail-emotions" class="detail-section"></div>
                <div id="detail-beliefs" class="detail-section"></div>
                <div id="detail-tags" class="detail-section"></div>
            </aside>

            <dialog id="unlock-dialog" class="unlock-dialog">
                <form id="unlock-form" method="dialog">
                    <h2>Secret unlock</h2>
                    <label for="unlock-password">Password</label>
                    <input id="unlock-password" name="unlock-password" type="password" autocomplete="current-password">
                    <div id="unlock-error" class="unlock-error" aria-live="polite"></div>
                    <div class="dialog-actions">
                        <button id="unlock-cancel" class="button" type="button">閉じる</button>
                        <button class="button button--primary" type="submit">解除</button>
                    </div>
                </form>
            </dialog>
        </main>
    </body>
</html>
