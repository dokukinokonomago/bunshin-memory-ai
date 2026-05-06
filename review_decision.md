# Review Decision: Auth 方針

作成: 2026-05-04 04:43:15 JST
最終確認: 2026-05-04 07:28:24 JST

## 判断状況

決定済み。

2026-05-04 07:28:24 JST に、ユーザーが `/api/v1` の正式認証方針として token-first を選択した。以後は session-first ではなく Bearer token 前提で auth 実装、route middleware、管理画面モックアップ接続を進める。

## 判断したいこと

分身AIバックエンドの `/api/v1` を、token-first API として作るか、session-first admin API として作るかを決める。

## 現状

- API は `/api/v1` 配下に JSON endpoint として実装済み。
- `routes/api.php` の protected endpoint は現在 `auth` middleware を使っている。
- `config/auth.php` は Laravel 標準の `web` session guard のみ定義されている。
- `composer.json` に token auth 用 package は未導入。
- Feature test は `$this->actingAs($user)` で認証済み user context を作っている。
- backend automation の対象は API 実装まで。frontend は別 automation で後から再設計する。
- 管理画面モックアップの mock API layer は plain `fetch('/api/v1/...')` で、現時点では token header / CSRF / login flow を固定していない。

## 選択肢 A: token-first

Bearer token で `/api/v1` を認証する。Laravel 標準候補としては Sanctum personal access token 相当を使い、API client は `Authorization: Bearer <token>` を送る。

### 向いている条件

- frontend と backend を別 origin / 別 deploy にしやすくしたい。
- 将来、管理画面以外の client、CLI、mobile、AI worker から同じ API を使う可能性がある。
- API-first backend として、session cookie や CSRF への依存を薄くしたい。
- tenant / owner context を token に紐づく user から安定して決めたい。

### 影響

- `laravel/sanctum` など token guard の導入が必要。
- `auth` middleware を `auth:sanctum` など明示 guard に変更する。
- login / token issuance / token revoke endpoint または管理用 token 発行手順が必要。
- Feature test は token guard 前提に寄せる。既存の domain/API tests は大きく壊さず、認証 helper を共通化できる。

### リスク

- token の保管、失効、漏洩時対応を最初から決める必要がある。
- 管理画面が browser-only なら、localStorage 等への token 保存設計を避けるための追加設計が必要。

## 選択肢 B: session-first

Laravel の `web` session guard を使い、browser 管理画面から cookie + CSRF で `/api/v1` を呼ぶ。

### 向いている条件

- 初期 client が同一 origin の管理画面だけである。
- Laravel の login / logout / password reset を近く実装したい。
- token 管理より、browser session の標準挙動を優先したい。

### 影響

- `web` middleware / session / CSRF と API route の境界を明確にする必要がある。
- 別 origin frontend にすると、cookie domain、SameSite、CORS、CSRF cookie の設計が必要になる。
- 管理画面モックアップの `fetch` に credentials / CSRF header 対応が必要になる。
- API-only client や worker からは使いにくいため、後で token auth を追加する可能性が高い。

### リスク

- API-first の設計に session 前提が入りやすい。
- frontend が別 automation / 別 deploy になる場合、最初の接続確認が token-first より複雑になりやすい。

## 推奨

初期方針は token-first を推奨する。

理由:

- この repo は frontend を持たない API backend として作り直している。
- 管理画面は後続 automation で再設計されるため、同一 origin session 前提に固定しない方が接続設計を選びやすい。
- 将来の AI worker / external client から memory API を使う余地がある。
- `tenant_id` / `owner_user_id` 境界は authenticated user から決まるため、Bearer token でも既存 API の domain 境界設計と相性がよい。

ただし、初期管理画面を Laravel 同一 app 内でだけ配信し、外部 client を当面作らないなら session-first でも成立する。

## 決定内容

1. `/api/v1` の正式認証は token-first とする。
2. API client は `Authorization: Bearer <token>` を送る。
3. Laravel 標準候補として Sanctum personal access token 相当を導入する。
4. protected API routes は `auth:sanctum` など token guard を明示する。
5. 管理画面モックアップ接続も token-first 前提で行う。

## 追加で実装時に決める項目

1. 初期 token 発行を user login endpoint で行うか、管理用 seed / artisan command で始めるか。
2. 管理画面モックアップ側で token をどこに保持するか。
3. token revoke / rotate の最小 endpoint を初期実装に含めるか。

## 決定後の次 task

Sanctum 相当の token auth を導入し、`/api/v1` protected routes の guard と Feature test helper を更新する。その後、管理画面モックアップの mock API layer を token-first の real API client に置き換える。

# Review Decision: root category 削除時の child category 扱い

作成: 2026-05-05 19:10:14 JST
最終確認: 2026-05-06 14:11:07 JST

## 判断状況

決定済み。

`categories.parent_id` による 2 階層 category baseline は実装済み。root category を削除したときに child category をどう扱うかは正式 UX として未決だったが、今回の判断で削除禁止に決定した。

2026-05-06 14:11:07 JST に、ユーザーが正式方針として選択肢 B の「children を持つ category は削除禁止」を採用した。以後、children が存在する category の `DELETE /api/v1/categories/{category}` は `422 Unprocessable Entity` とし、先に children を移動または削除させる。

2026-05-05 20:03:06 JST の automation 入力にも、root 昇格、children あり削除禁止、cascade delete のどれを採用するかの明示決定は含まれていなかった。

2026-05-05 22:03:05 JST の automation 入力にも、上記 3 方針のどれを正式採用するかの明示決定は含まれていなかった。今回も実装変更は行わず、判断待ちを継続する。

2026-05-05 23:03:14 JST の automation 入力にも、上記 3 方針のどれを正式採用するかの明示決定は含まれていなかった。`categories.parent_id` baseline は実装 / targeted test 済みのため、今回も削除方針の実装変更は行わず、判断待ちを継続する。

2026-05-06 01:04:00 JST の automation 入力にも、上記 3 方針のどれを正式採用するかの明示決定は含まれていなかった。`categories.parent_id` baseline は 2026-05-06 00:06:48 JST に補完 / targeted test 済みのため、今回も削除方針の実装変更は行わず、判断待ちを継続する。

2026-05-06 02:02:59 JST の automation 入力にも、上記 3 方針のどれを正式採用するかの明示決定は含まれていなかった。`categories.parent_id` baseline は既に実装 / 検証済みのため、今回も削除方針の実装変更は行わず、判断待ちを継続する。

2026-05-06 03:02:43 JST の automation 入力にも、上記 3 方針のどれを正式採用するかの明示決定は含まれていなかった。`task_board.md` と automation memory 上の次 task は root category 削除方針の人間判断確認であり、`categories.parent_id` baseline は既に実装 / 検証済みのため、今回も削除方針の実装変更は行わず、判断待ちを継続する。

2026-05-06 04:01:13 JST の automation 入力にも、上記 3 方針のどれを正式採用するかの明示決定は含まれていなかった。現在も実装 / Feature test は root 昇格、API contract draft は `422 Unprocessable Entity` の削除禁止方針で差分が残るため、今回も削除方針の実装変更は行わず、判断待ちを継続する。

2026-05-06 10:02:50 JST の automation 入力にも、上記 3 方針のどれを正式採用するかの明示決定は含まれていなかった。`categories.parent_id` baseline は完了済みであり、現在も実装 / Feature test は root 昇格、API contract draft は `422 Unprocessable Entity` の削除禁止方針、OpenAPI は delete 204/401/404 のみで差分が残るため、今回も削除方針の実装変更は行わず、判断待ちを継続する。

2026-05-06 11:01:39 JST の automation 入力にも、上記 3 方針のどれを正式採用するかの明示決定は含まれていなかった。`categories.parent_id` baseline は完了済みであり、現在も実装 / Feature test は root 昇格、API contract draft は `422 Unprocessable Entity` の削除禁止方針、OpenAPI は delete 204/401/404 のみで差分が残るため、今回も削除方針の実装変更は行わず、判断待ちを継続する。

2026-05-06 12:02:45 JST の automation 入力にも、上記 3 方針のどれを正式採用するかの明示決定は含まれていなかった。`categories.parent_id` baseline は完了済みであり、現在も実装 / Feature test は root 昇格、API contract draft は `422 Unprocessable Entity` の削除禁止方針、OpenAPI は delete 204/401/404 のみで差分が残るため、今回も削除方針の実装変更は行わず、判断待ちを継続する。

2026-05-06 13:01:47 JST の automation 入力にも、上記 3 方針のどれを正式採用するかの明示決定は含まれていなかった。`categories.parent_id` baseline は完了済みであり、現在も実装 / Feature test は root 昇格、API contract draft は `422 Unprocessable Entity` の削除禁止方針、OpenAPI は delete 204/401/404 のみで差分が残るため、今回も削除方針の実装変更は行わず、判断待ちを継続する。

2026-05-06 14:01:37 JST の automation 入力にも、上記 3 方針のどれを正式採用するかの明示決定は含まれていなかった。`categories.parent_id` baseline は完了済みであり、現在も実装 / Feature test は root 昇格、API contract draft は `422 Unprocessable Entity` の削除禁止方針、OpenAPI は delete 204/401/404 のみで差分が残るため、今回も削除方針の実装変更は行わず、判断待ちを継続する。

## 判断したいこと

root category / 大カテゴリーが child category / サブカテゴリーを持つ状態で `DELETE /api/v1/categories/{category}` を受けたとき、child category をどう扱うかを決める。

## 実装後の現状

- DB migration は `categories.parent_id` の self FK に `nullOnDelete()` を設定している。
- `CategoryController@destroy` は children を持つ category の削除を `422 Unprocessable Entity` で拒否する。
- children がない category の削除では、対象 category を参照する memory の `category_id` を `null` にしてから category を削除する。
- `CategoryApiTest` は children あり削除禁止、child の parent 維持、memory category 維持、children なし category の通常削除を検証している。
- `docs/architecture/api_contract.md` と OpenAPI には、children を持つ category の削除は `422 Unprocessable Entity`、error field は `children` と記載している。
- `memories.category_id` は原則として末端 category を指すが、root category 直下 memory も許容する設計になっている。

## 選択肢 A: child category を root 昇格する

現在の実装 / test に近い。root category を削除したら、その child category の `parent_id` を `null` にする。

### 向いている条件

- 削除操作をなるべく成功させたい。
- category 階層を軽い分類ラベルとして扱い、root / child の意味差が強くない。
- 初期 UI で child の移動先選択や削除確認を作り込まない。

### 影響

- 現在の migration / controller / Feature test からの変更は最小。
- child category が意図せず大カテゴリーとして表示される可能性がある。
- memory-space の tree 表示で、もともとサブカテゴリーだったものが top-level に出る。

### リスク

- 「学校 > 部活」の `学校` を消したら `部活` が root になるなど、分類の意味が崩れやすい。
- user が削除後の階層変化を予測しにくい。

## 選択肢 B: children を持つ root category は削除禁止にする

children が存在する category の削除は `422 Unprocessable Entity` とし、先に child category を移動または削除させる。

### 向いている条件

- root category / child category の意味を維持したい。
- memory-space の tree 表示で予期しない top-level category を作りたくない。
- 後から「移動して削除」や「children も削除」など明示的な UI を追加したい。

### 影響

- `CategoryController@destroy` に children 存在チェックと validation-style JSON error を追加する。
- `CategoryApiTest` は root 昇格 assertion から 422 assertion に変更する。
- `docs/architecture/api_contract.md` の既存記述と揃う。
- `nullOnDelete()` は DB safety net として残してもよいが、通常 API flow では発火しない。

### リスク

- UI 側で削除できない理由の表示と、移動 / 削除手順の案内が必要になる。
- 初期管理画面モックアップでは 422 表示は可能だが、child 移動導線は最小限になる。

## 選択肢 C: child category も cascade delete する

root category を削除したら child category も削除する。

### 向いている条件

- category 階層を一括管理単位として扱いたい。
- root category を消す操作が、その配下分類も不要という意味で使われる。

### 影響

- child category を参照する memories の `category_id` をどうするか追加設計が必要。
- 多段階削除、確認 UI、audit / undo の検討が必要になる。

### リスク

- child category の削除は取り返しがつきにくく、誤操作時の影響が大きい。
- memory 自体は残すとしても分類情報が広範囲に失われる。

## 推奨

初期実装の正式方針は、選択肢 B の「children を持つ root category は削除禁止」を推奨する。

理由:

- root / child の 2 階層は memory-space 画面の主要ナビゲーションになるため、暗黙の root 昇格は表示上の意味が崩れやすい。
- cascade delete は category metadata の喪失範囲が大きく、初期実装としては強すぎる。
- 既存 API contract draft は削除禁止方針を既に書いており、実装をそこへ揃える方が設計 docs と一致する。
- 初期管理画面モックアップは 422 / validation error 表示を扱えるため、削除禁止の feedback は最小変更で接続できる。

## 決定内容

1. children を持つ category の `DELETE` は `422 Unprocessable Entity` で拒否する。
2. error field は `children` とし、message は「子カテゴリを持つカテゴリは削除できません。」を返す。
3. 将来 UI で「child を別 root へ移動してから削除」または「children も削除」を提供するかは別 task で検討する。

## 決定後の次 task

完了済み。`CategoryController@destroy` に children 存在チェックを追加し、`CategoryApiTest` を 422 方針へ変更し、`docs/architecture/api_contract.md` / OpenAPI を実装に合わせた。

# Review Decision: secret unlock password 方針

作成: 2026-05-06 15:03:18 JST
最終確認: 2026-05-06 15:03:18 JST

## 判断状況

未決。

2026-05-06 15:03:18 JST の automation 入力には、secret unlock password を account password と共用し続けるか、専用 unlock password に分離するかの明示決定は含まれていなかった。

現状 baseline は、`POST /api/v1/secret-unlocks` で認証済み user の account password hash を検証し、短時間有効な unlock token を発行する。専用 password / recovery / rotation は未実装で、後続 task として検討対象に残っている。

## 判断したいこと

`visibility=secret` の memory を記憶の海 / 宇宙画面で追加認可後に返すための unlock password を、account password と共用するか、専用 password として分離するかを決める。

## 選択肢 A: account password と共用する

初期 baseline のまま、user の account password hash を secret unlock password として使う。

### 向いている条件

- 初期実装を軽く保ちたい。
- account login / password reset の標準導線に secret unlock も乗せたい。
- 専用 unlock password の設定 UI、recovery、rotation をまだ作らない。

### 影響

- 現行 code / tests / API contract からの変更は最小。
- user は account password だけを覚えればよい。
- account password を知っている人は secret memory unlock も可能になる。

### リスク

- account password と secret unlock の意味が分離されない。
- 将来「ログインできるが secret は見せない」権限分離をしたい場合に再設計が必要になる。

## 選択肢 B: 専用 unlock password に分離する

secret memory unlock 専用の password hash を user scoped に持ち、account password とは別に検証する。

### 向いている条件

- secret memory を account login より強い追加保護として扱いたい。
- 家族 / 代理入力 / 管理者操作などで、ログイン権限と secret 閲覧権限を分けたい。
- unlock password の rotation や recovery を独立させたい。

### 影響

- user scoped unlock credential の保存先と migration が必要。
- 設定 / 変更 / recovery / 初期化 flow の API と UI が必要。
- `SecretUnlockController` と Feature test を専用 password 検証に更新する。

### リスク

- 初期実装の scope が広がる。
- 専用 password を忘れた場合の recovery 方針を決めないと運用不能になりやすい。

## 推奨

正式運用では選択肢 B の専用 unlock password 分離を推奨する。ただし、recovery / rotation / 設定 UI まで同時に設計する必要があるため、初期接続検証の間は現行 baseline の account password 共用を暫定維持してもよい。

## 決定内容

未決。明示決定があるまで、現行 baseline の account password hash 検証から実装変更しない。

## 決定後の次 task

専用 unlock password に分離する場合は、user scoped unlock credential の migration / model / validation / tests を小さい task として切る。account password 共用を正式採用する場合は、docs / OpenAPI に暫定ではなく正式方針として反映する。
