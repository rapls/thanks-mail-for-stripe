# WordPress 7.0 互換性テスト — Thanks Mail for Stripe

| 項目 | 内容 |
|------|------|
| プラグインバージョン | 1.1.2 |
| WordPress | 7.0 正式版 (db_version 61833) ※2026-05-21 更新。RC4 で初回検証 |
| PHP 要求 | 7.4 以上 |
| テスト実施日 | 2026-05-20 (RC4) / 2026-05-21 (正式版・実機検証) |
| サーバー環境 | Local by Flywheel (hash.local) |

---

## 0. WordPress 7.0 正式版 実機検証結果 (2026-05-21)

ローカル環境 (hash.local / WP 7.0 正式版 / WP_DEBUG 有効) で実際に有効化した状態を DB・ログから確認。

| # | 検証項目 | 結果 | 確認方法 |
|---|----------|------|----------|
| 0.1 | WordPress 7.0 正式版で稼働 | ✅ | db_version 61833、wp_version 7.0 |
| 0.2 | プラグインが有効化されている | ✅ | active_plugins に thanks-mail-for-stripe を確認 |
| 0.3 | 有効化フックでテーブル作成成功 | ✅ | wp_tmfs_sent_emails テーブル存在 |
| 0.4 | デフォルト設定が保存されている | ✅ | tmfs_settings オプション存在 |
| 0.5 | プラグイン起因の PHP エラー/警告/Deprecated なし | ✅ | debug.log 内 `plugins/thanks-mail-for-stripe` 由来エラー **0 件** (WP_DEBUG 有効、ログ全体では他プラグイン由来 3,951 件あり) |
| 0.6 | JIT 翻訳警告 (`_load_textdomain_just_in_time`) なし | ✅ | debug.log に該当なし |

**実機検証の結論:** WordPress 7.0 正式版において、有効化・テーブル作成・設定保存まで正常に完了し、WP_DEBUG 有効下でもプラグイン起因のエラーは一切出力されていません。

> 注: Webhook 受信・メール送信・設定画面操作は外部 Stripe との連携が必要なため、本検証(DB/ログ確認)には含まれません。第2章のチェックリストで手動確認してください。

## 凡例

- ✅ 合格 / ❌ 不合格 / ⚠️ 注意 / ⏭️ 未実施 / 🔹 静的解析で事前検証済み

---

## 1. 事前静的検証 (コード解析) — 全項目合格

| # | 項目 | 結果 | 備考 |
|---|------|------|------|
| 1.1 | プラグインヘッダー (Version 1.1.2 / Requires 5.0 / PHP 7.4 / Tested up to 7.0) | ✅ 🔹 | 正常 |
| 1.2 | 廃止/削除済み PHP 関数の不使用 (create_function, each, mysql_*, FILTER_SANITIZE_STRING, money_format) | ✅ 🔹 | grep で該当なし。PHP 8.1/8.2/8.3/8.4 でも安全 |
| 1.3 | 廃止 WP 関数の不使用 (get_page_by_title, wp_get_http 等) | ✅ 🔹 | 該当なし |
| 1.4 | PHP 8.2+ 動的プロパティ問題なし | ✅ 🔹 | typed property (`private static ?self $instance`) のみ。動的代入なし |
| 1.5 | ABSPATH ガード存在 | ✅ 🔹 | メインファイル, uninstall.php とも `defined('ABSPATH')` |
| 1.6 | activation/deactivation/uninstall フック | ✅ 🔹 | dbDelta でテーブル作成、uninstall で DROP + delete_option |
| 1.7 | 翻訳の JIT ロード警告 (WP 6.7+) なし | ✅ 🔹 | 翻訳関数 (`__`等95箇所) はすべて init 以降のフック内 (admin_menu / admin_init / admin_enqueue_scripts / rest_api_init / 設定ページ描画)。プラグインロード時に翻訳呼び出しなし |
| 1.8 | REST API 全ルートに permission_callback 設定 | ✅ 🔹 | webhook=`__return_true` (Stripe署名で検証), test/reset=`current_user_can('manage_options')` |
| 1.9 | テンプレートのエスケープ処理 | ✅ 🔹 | esc_html_e / esc_attr / esc_url / checked / selected / settings_fields 使用 |
| 1.10 | webhook 署名検証 (HMAC-SHA256 + タイムスタンプ許容) | ✅ 🔹 | hash_equals でタイミング攻撃対策あり |
| 1.11 | SQL は $wpdb->prepare / esc_sql 使用 | ✅ 🔹 | テーブル名は $wpdb->prefix 由来、値は prepare 済み |

**静的解析の結論:** WordPress 7.0-RC4 で動作を妨げるコード上の問題は検出されませんでした。

---

## 2. 実機テスト項目 (要手動確認)

### インストール / 有効化

| # | 項目 | 期待結果 | 結果 |
|---|------|----------|------|
| 2.1 | ZIP インストール → 有効化 | フェイタル/警告なし | ⏭️ |
| 2.2 | 有効化で `wp_{prefix}tmfs_sent_emails` テーブル作成 | dbDelta で作成 | ⏭️ |
| 2.3 | デフォルト設定 `tmfs_settings` 保存 | wp_options に作成 | ⏭️ |
| 2.4 | 設定 > Thanks Mail for Stripe メニュー表示 | 表示 | ⏭️ |
| 2.5 | 有効化時 PHP Notice/Warning なし | debug.log クリーン | ⏭️ |

### 設定画面

| # | 項目 | 期待結果 | 結果 |
|---|------|----------|------|
| 2.6 | 設定保存 (Webhook Secret / From / Brand) | 値反映 | ⏭️ |
| 2.7 | テンプレート追加/削除 (1〜10個) | JS で増減 | ⏭️ |
| 2.8 | テンプレート個別リセット | デフォルト復元 | ⏭️ |
| 2.9 | 全体リセット (REST /reset) | 初期化 | ⏭️ |
| 2.10 | Webhook URL コピーボタン | クリップボードコピー | ⏭️ |

### Webhook / メール送信

| # | 項目 | 期待結果 | 結果 |
|---|------|----------|------|
| 2.11 | テストメール送信 (REST /test) | 受信確認 | ⏭️ |
| 2.12 | Stripe テストモードで `checkout.session.completed` 受信 | 200 応答 | ⏭️ |
| 2.13 | 署名検証 (不正署名は 400) | 拒否される | ⏭️ |
| 2.14 | 言語判定 (Payment Link ID → locale フォールバック) | 正しいテンプレート選択 | ⏭️ |
| 2.15 | 重複送信防止 (同 session_id は再送しない) | already_sent: true | ⏭️ |
| 2.16 | レート制限 (60秒10回超で 429) | 429 応答 | ⏭️ |
| 2.17 | プレースホルダ ({brand}/{session_id}/{email}) 置換 | 正しく置換 | ⏭️ |
| 2.18 | 送信ログがDB記録 + 管理画面表示 | 一覧表示 | ⏭️ |

### WP 7.0 固有

| # | 項目 | 期待結果 | 結果 |
|---|------|----------|------|
| 2.19 | サイトヘルス > 重要な問題なし | エラーなし | ⏭️ |
| 2.20 | REST API `/wp-json/thanks-mail/v1/webhook` が登録される | ルート存在 | ⏭️ |
| 2.21 | `_load_textdomain_just_in_time` 警告なし | debug.log クリーン | ⏭️ |
| 2.22 | 日本語環境で UI が日本語表示 | 翻訳適用 | ⏭️ |

### 無効化 / アンインストール

| # | 項目 | 期待結果 | 結果 |
|---|------|----------|------|
| 2.23 | 無効化でデータ保持 (再有効化可) | テーブル/設定維持 | ⏭️ |
| 2.24 | アンインストールでテーブル DROP + オプション削除 | 完全削除 | ⏭️ |

---

## 3. テスト準備

1. `wp-config.php` で `WP_DEBUG` / `WP_DEBUG_LOG` を有効化、`WP_DEBUG_DISPLAY` を false
2. `wp-content/debug.log` を空にする
3. Stripe テストモードの Webhook Signing Secret (whsec_...) を用意
4. プラグインを `thanks-mail-for-stripe.zip` (v1.1.2) からインストール
5. テストメール → 実 Webhook の順で実施

---

## 4. 結論

静的解析 (11項目) はすべて合格。WordPress 7.0-RC4 における既知の互換性破壊要因 (廃止関数・JIT翻訳警告・REST permission_callback 必須化・PHP 8.x 動的プロパティ) はいずれも該当なし。実機テスト (2章) を実施して最終確認してください。
