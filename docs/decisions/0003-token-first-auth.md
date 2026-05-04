# 0003: API 認証は token-first とする

日付: 2026-05-04

## 決定

分身AIバックエンドの `/api/v1` は token-first API として実装する。API client は `Authorization: Bearer <token>` を送る。Laravel 側は Sanctum personal access token 相当を第一候補とし、protected routes は token guard を明示する。

## 背景

この repository は fresh backend として作り直しており、旧 Blade UI は破棄済みである。管理画面モックアップは実 API に接続する対象になったが、本格 frontend 再設計とは分離して扱う。将来、管理画面以外の client、AI worker、CLI から同じ API を使う可能性があるため、session cookie / CSRF に依存しない API-first の認証方式を採用する。

## 影響

- token auth package または内部の Sanctum 相当実装と token guard を導入する。
- `routes/api.php` の protected endpoints は `auth:sanctum` など明示 guard に寄せる。
- Feature test は token guard 前提の helper を用意する。
- 管理画面モックアップ接続は Bearer token を送る API client として実装する。
- session-first の cookie / CSRF 前提は採用しない。

## 実装メモ

初期実装は external package を追加せず、`personal_access_tokens` table、`PersonalAccessToken` model、`sanctum` guard 名の request guard で Sanctum personal access token 相当の contract を実装する。後で Laravel Sanctum package に置き換える場合も、API route 側の `auth:sanctum` contract と Bearer token client contract は維持する。

## 次の task

管理画面モックアップを Bearer token 前提で実 API に接続する。
