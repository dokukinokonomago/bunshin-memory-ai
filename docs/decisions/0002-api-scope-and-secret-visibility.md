# 0002: API 実装までを対象にし、secret は通常 list から除外する

日付: 2026-05-04

## 決定

- 旧 UI は完全に破棄する。
- frontend は別 automation で後から再設計する。
- この backend automation は API 実装までを対象にする。
- `visibility=secret` の記憶は通常の `GET /memories` から除外する。
- `secret` の記憶は、明示的に呼び出された場合だけ取得できるようにする。

## 背景

旧 MVP は Blade UI と画面主導の実装を含んでいたが、新規 backend は API-first で再構築する。frontend は別 automation で扱うため、この repository では旧 UI を温存・復元せず、API 契約と backend 実装に集中する。

秘匿記憶は通常表示に混ざると期待と異なるため、default list からは除外する。一方で、本人が明示的に対象を呼び出す場合は取得できる必要がある。

## 実装メモ

- `Memory` query の default scope または repository/service 層で `visibility != secret` を default にする。
- `GET /api/v1/memories?visibility=secret` は明示取得として扱う。
- `GET /api/v1/memories/{memory}` は ID 指定の明示取得として扱うが、owner / tenant 認可は必須にする。
- Feature test では default list に secret が出ないこと、明示取得では出ることを固定する。
