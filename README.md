# Thanks Mail for Stripe

WordPress で Stripe Payment Links の決済完了後、カスタマイズされたサンクスメールを自動送信するプラグインです。

📖 **詳しい解説記事**: [Thanks Mail for Stripe 導入ガイド｜Stripe Payment Linksの決済後にサンクスメールを自動送信](https://raplsworks.com/thanks-mail-for-stripe/)

## Features

- **Stripe Payment Links 対応** — Stripe ダッシュボードで作成したペイメントリンクの決済に対応
- **10種類の動的テンプレート** — Payment Link ID または言語（ロケール）で自動マッチング
- **日本語・英語・多言語対応** — i18n 準備完了
- **Webhook 統合** — Stripe イベントを自動監視、決済完了で即座にメール送信
- **カスタマイズ可能** — テンプレート変数（`{brand}` / `{session_id}` / `{email}`）を件名・本文に動的挿入
- **設定画面シンプル** — WordPress 管理画面で完結、コード編集不要

## Installation

### WordPress.org から（推奨）

1. WordPress管理画面 → **プラグイン** → **新規追加**
2. 「Thanks Mail for Stripe」で検索 → **インストール**
3. 有効化

### GitHub から

1. [Releases](../../releases) から最新版の ZIP をダウンロード
2. WordPress管理画面 → **プラグイン** → **新規追加** → **プラグインのアップロード**
3. 有効化

## セットアップ

### 前提条件

- **WordPress 5.0以上**
- **PHP 7.4以上**
- **Stripe アカウント** — Payment Links と Webhook を作成できるダッシュボードへのアクセス権

### クイックスタート

1. プラグイン有効化後、管理画面の **Thanks Mail for Stripe** 設定ページを開く
2. 設定ページに表示される **Webhook URL** をコピーし、Stripe ダッシュボードで Webhook エンドポイントとして登録（下記「Webhook の設定」参照）
3. Stripe が発行した **署名シークレット（`whsec_...`）** を設定ページの **Webhook Secret** 欄に貼り付ける
4. 送信元（From）・ブランド名、および Payment Link ごとのメールテンプレート（最大10種類）を設定して保存
5. WordPress のメーラー設定（`wp_mail()` が正常に動作すること）を確認

詳しくは [導入ガイド](https://raplsworks.com/thanks-mail-for-stripe/) を参照。

## よくある質問 / トラブルシューティング

### Q: Stripe Webhook が動かない

**A:** Stripe ダッシュボード → **Webhook** で、このサイトの Webhook URL を登録する必要があります。

URL: `https://yoursite.com/wp-json/thanks-mail/v1/webhook`

イベントタイプ: `checkout.session.completed`（必要に応じて `checkout.session.async_payment_succeeded` も）

あわせて、設定ページの **Webhook Secret** に Stripe の署名シークレットが入力されていること、プラグインが **有効** になっていることを確認してください。

### Q: メールが届かない

**A:** WordPress のメーラー設定を確認してください。

- **wp_mail()** が正常に機能しているか確認
- SMTP プラグイン（WP Mail SMTP など）を導入している場合、設定を確認
- スパムフォルダを確認

### Q: テンプレート変数が展開されない

**A:** 以下の変数が使用可能です：

```
{brand}        — ブランド名（設定ページで指定）
{session_id}   — Stripe Checkout Session ID（照合用）
{email}        — 送信先メールアドレス
```

件名・本文のどちらでも使用できます。

詳しくは [導入ガイド](https://raplsworks.com/thanks-mail-for-stripe/) を参照。

### Q: 複数の Payment Link で異なるメールを送りたい

**A:** Payment Link ごとに異なるテンプレートを割り当てられます。

管理画面で Payment Link ID と テンプレートの対応表を設定。

---

## Webhook の設定（重要）

Payment Links での決済完了通知を受け取るために、Stripe ダッシュボードでの Webhook 登録が必須です。

### Webhook URL

```
https://yoursite.com/wp-json/thanks-mail/v1/webhook
```

（正確な URL はプラグイン設定ページにも表示されます）

### リッスンするイベント

- `checkout.session.completed` — 決済完了時
- `checkout.session.async_payment_succeeded` — 非同期決済が後から成功した時

Stripe ダッシュボード → **開発者** → **Webhook** → **エンドポイントを追加** で登録し、発行された署名シークレット（`whsec_...`）を設定ページの **Webhook Secret** に入力してください。署名は HMAC-SHA256 で検証されます。

---

## Documentation

- [導入ガイド](https://raplsworks.com/thanks-mail-for-stripe/)
- [WordPress.org プラグインページ](https://wordpress.org/plugins/thanks-mail-for-stripe/)

---

## ユースケース

- **オンライン販売** — 商品購入後にサンクスメール自動送信
- **サービス申し込み** — 申し込み完了メール
- **寄付・支援受付** — 寄付受領確認メール
- **イベント参加費** — チケット購入完了通知

---

## Development

### Requirements

- WordPress 5.0以上
- PHP 7.4以上
- Stripe アカウント（Payment Links / Webhook）

### Contributing

バグ報告・機能要望は [Issues](../../issues) までお願いします。Pull Request も歓迎です。

## Changelog

詳細は [readme.txt](./readme.txt) をご覧ください。

## Author

**Rapls（ラプルス）**  
フリーランス Web 開発者 / WordPress Polyglots PTE（日本語翻訳責任者）

- 🌐 [Rapls Works](https://raplsworks.com/)
- 📋 [WordPress.org プロフィール](https://profiles.wordpress.org/rapls/)
- 🐙 [GitHub](https://github.com/raplsworks)

## License

GPL v2 or later
