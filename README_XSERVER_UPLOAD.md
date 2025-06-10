# 📁 エックスサーバーアップロード用ファイル

**作成日**: 2025年6月1日  
**目的**: 面会予約システムのエックスサーバー移行  

## 📋 ファイル構成

### 📄 アップロード対象ファイル

1. **mobile-reservation-next-day-only-fixed.html** (22KB)
   - 面会予約システムのメインファイル（NEWバージョン）
   - 重複チェック無効化済み
   - JavaScript修正済み
   - 注意事項: 「こちらは明日の面会予約専用システムです」

2. **mobile-reservation-api.php** (6.8KB)
   - LINEWORKS API連携ファイル
   - JWT認証処理
   - カレンダー登録機能

3. **private_20250529134836.key** (1.7KB)
   - LINEWORKS認証用秘密鍵
   - ⚠️ 重要: パーミッション600に設定必須

## 🚀 エックスサーバー設置手順

### Step 1: フォルダ作成
```
エックスサーバー ファイルマネージャー
→ public_html
→ 新しいフォルダ作成: reservation
```

### Step 2: ファイルアップロード
```
以下の3ファイルを reservation フォルダにアップロード:
- mobile-reservation-next-day-only-fixed.html
- mobile-reservation-api.php
- private_20250529134836.key
```

### Step 3: パーミッション設定
```
private_20250529134836.key のパーミッションを 600 に変更
（ファイルマネージャーで右クリック → パーミッション変更）
```

### Step 4: PHP設定確認
```
PHP8.0以上に設定
（エックスサーバーパネル → PHP Ver.切替）
```

## 🌐 予想URL

### メインシステム
```
https://あなたのドメイン/reservation/mobile-reservation-next-day-only-fixed.html
```

### API エンドポイント
```
https://あなたのドメイン/reservation/mobile-reservation-api.php
```

## 🔧 設置後の確認項目

### 1. 基本動作確認
- [ ] ページが正常に表示される
- [ ] 利用者名・面会者名の入力ができる
- [ ] 時間選択ができる
- [ ] 予約ボタンが押せる

### 2. API連携確認
- [ ] 予約送信時にエラーが出ない
- [ ] LINEWORKSカレンダーに登録される
- [ ] 成功メッセージが表示される

### 3. セキュリティ確認
- [ ] 秘密鍵ファイルのパーミッションが600
- [ ] HTTPS接続で表示される
- [ ] 外部からアクセスできる

## ⚠️ 注意事項

### セキュリティ
- 秘密鍵ファイルは絶対に外部から直接アクセスできないよう設定
- 可能であればBasic認証の設定を推奨

### バックアップ
- 設置前に現在のCloudflare URLを控えておく
- 移行後も一定期間は両方を並行運用推奨

### 空メール返信システム
- 移行完了後は空メール返信URLの更新が必要
- `/home/rootmax/mobile-reservation-collection/empty_mail_url_responder.py` 内のURL変更

## 📞 サポート情報

### 必要なPHP拡張
- OpenSSL（JWT認証用）
- cURL（API通信用）
- JSON（データ処理用）

### トラブル時の確認
1. PHPエラーログの確認
2. ファイルパーミッションの確認
3. LINEWORKS API設定の確認

---

**移行準備完了**: このフォルダの内容をそのままエックスサーバーにアップロードしてください