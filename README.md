# selfLightningSync

自前で Lightning Sync を実装する
前回こんなのを書いたのですが、その続きです。
[Salesforce の Apex でチームスピリットの出退勤情報から Google カレンダーに登録する処理](https://qiita.com/geeorgey/items/59de75ae7faf351743c1)

Salesforce には Lightning Sync という機能が提供されており、それを使えばこんなことをしなくても同期できるのですが、**Einstein 活動キャプチャという機能を ON にしていると、Lightning Sync が使えない**ので、自前実装するしかありません。なんでそんな仕様なんだろう 🤔

## ToDo

- 第一部：GoogleCalendar の更新情報を Salesforce に同期する
- 第二部：Salesforce の Event 情報の更新を GoogleCalendar に同期する

## 第一部：GoogleCalendar の更新情報を Salesforce に同期する

###必要要件
Google から Salesforce への接続には、別途サーバが必要になります。
なるべく簡単に開発できるようにと思ったのですが、mac 標準で入っている php には JSON エクステンションが入っていないために利用できませんでした。以下のコードを使う場合は、JSON エクステンション、SOAP エクステンションが入った php 環境でお試しください。

### 同期に必要な要件

Salesforce 側に同期するので、今回のスクリプトでは

- Salesforce のユーザを調べて Email と Salesforce の User.id を取得します
- Email リストを使って GoogleCalendarAPI を叩き、カレンダー情報を取得します
- 取得した Google カレンダー情報を Salesforce の Event に同期します
  GoogleAPI には PUSH 通知機能があるのですが、面倒なので今回は使っていません。
  任意の間隔で GoogleAPI を叩き、その時間の間に更新された予定を Salesforce 側に同期しています。

### 処理の流れ

1. Salesforce への認証
   JWT (JSON Web Token) を使用して Salesforce への認証を行います。
   createjwt() 関数で JWT を生成し、auth() 関数で認証リクエストを送信します。
   認証に成功すると、アクセストークンとインスタンス URL を取得します。
2. Salesforce からユーザー情報の取得
   getuserfromsf() 関数を使用して、Salesforce から有効なユーザーのメールアドレスと ID を取得します。
   取得したユーザー情報は、$emailAndIDs 配列に格納されます。
3. Google Calendar API の認証
   getGoogleClient() 関数を使用して、Google Calendar API への認証を行います。
   認証情報は credentials.json ファイルから読み込まれます。
   アクセストークンは token.json ファイルに保存され、次回以降の実行時に再利用されます。
4. Google Calendar からイベントの取得
   $emailAndIDs 配列を使用して、各ユーザーのカレンダーからイベントを取得します。
$service->events->listEvents() メソッドを使用して、過去 5 分以内に更新されたイベントを取得します。
   取得したイベントの情報は、$sObject_Event 配列に格納されます。
5. イベントデータの整形
   取得したイベントデータを、Salesforce に同期するための形式に整形します。
   イベントの開始日時、終了日時、件名、説明、場所などの情報を、$sObject_Event 配列の各要素に設定します。
   イベントが招待イベントの場合や、キャンセルされた場合の処理も行います。
6. Salesforce へのイベントデータの Upsert
   eventsUpsert() 関数を使用して、整形したイベントデータを Salesforce に Upsert します。
   Upsert は、レコードが存在する場合は更新、存在しない場合は新規作成を行います。
   イベントデータは 25 件ずつに分割して処理されます。
   このスクリプトにより、Google Calendar のイベントデータが Salesforce に同期され、両システム間でイベント情報が共有されます。同期は定期的に実行することで、常に最新のイベント情報を維持することができます。

### Google APIs で新規プロジェクトを作成

こちらから：https://console.developers.google.com/apis/
プロジェクト作成後に認証情報ひらき、認証情報を作成＞ OAuth クライアント ID を作成。
<img width="938" alt="oauth.png" src="https://qiita-image-store.s3.ap-northeast-1.amazonaws.com/0/22294/6281b7c4-0c12-ce5b-2b20-b243b536af97.png">
コールバック URL はひとまずこれを入れておきます。なんでもいいです
http://localhost:8080/

<img width="1425" alt="ウェブ_アプリケーション_のクライアン…_–_API_とサービス_–_Lnest-SF-Gcal_–_Google_API_Console.png" src="https://qiita-image-store.s3.ap-northeast-1.amazonaws.com/0/22294/6bb5ede9-6d94-cb6e-edf9-1ed95cc90bb4.png">

JSON ファイルをダウンロードして作業フォルダの中に入れましょう。
その際、ファイル名を
credentials.json
に変えておくと、GoogleAPI のサンプルがそのまま動きます。

ライブラリから以下の 2 つの API を検索して利用開始してください。

- Google Calendar API

Google のドキュメントはこちら
Calendar API のページ：https://developers.google.com/calendar/quickstart/php

ここまで来たら Google Calendar API のサンプルを試してみてください。
上述の Calendar API のページにあるコードをそのまま
index.php
に書いて実行してみます。
ターミナルで
`php index.php`
と打つと、こんなレスポンスが返ってきます。

```Open the following link in your browser:
https://accounts.google.com/o/oauth2/auth?response_type=code&access_type=offline&client_id=4674xxxxxxxx8-xxxxxxxxxxxxxxxxxxxxorp6r9j8bf0n.apps.googleusercontent.com&redirect_uri=http%3A%2F%2Flocalhost:8080%index.php&state&scope=https%3A%2F%2Fwww.googleapis.com%2Fauth%2Fcalendar.readonly&prompt=select_account%20consent
Enter verification code:
```

書かれている通り、URL をブラウザで開きましょう。この部分です。
https://accounts.google.com/o/oauth2/auth?response_type=code&access_type=offline&client_id=46xxxxxx18-cxxxxxxxxxxxxxxxx0n.apps.googleusercontent.com&redirect_uri=http%3A%2F%2Flocalhost:8080%index.php&state&scope=https%3A%2F%2Fwww.googleapis.com%2Fauth%2Fcalendar.readonly&prompt=select_account%20consent

これを開くと、Google の OAuth 画面に行くので自分の Google アカウントで開き、Google Calendar API へのアクセス許可が求められるので承認します。するとこうなります。
![localhost.png](https://qiita-image-store.s3.ap-northeast-1.amazonaws.com/0/22294/704a13a4-c036-bb41-78ab-b5fc1107ef10.png)
そりゃそうです。何も表示できるように作ってないのですから。重要なのは画面表示ではなく、その URL です。
`http://localhost:8080/SyncGoogleCalToSalesforceEvent.php?code=4/ygFFXpRLYvJnbN5LO2EF**********************YIzAt65GxE-vKwYb2mYdTnm--CfIetczXU&scope=https://www.googleapis.com/auth/calendar.readonly`
みたいな URL になっているとおもうのですが、その code= のあと
4/ygFFXpRLYvJ から最後までをコピーして、Enter verification code:のところに入力してください。
そうすることによって、実行したフォルダの中に token.json ができているはずです。これで Google の認証が終わります。
もう一度実行してみると、カレンダーの予定が 10 件表示されることがわかるでしょう。

`$ php index.php`

![credentials_json_—_public.png](https://qiita-image-store.s3.ap-northeast-1.amazonaws.com/0/22294/03bc7c99-8b4e-a30d-98f9-81feafe7c237.png)

###Salesforce へ JWT 接続するための設定をする
やり方はこちらを参照してください
[Salesforce JWT 用の接続アプリを共有して組織への接続を簡単にする
](https://qiita.com/stomita/items/4b0efdec3792b4fa706b)

コールバック URL に http://localhost:1717/OauthRedirect を設定する部分が意味わからなかったのですが、 [そういう仕様](https://developer.salesforce.com/docs/atlas.ja-jp.sfdx_dev.meta/sfdx_dev/sfdx_dev_auth_connected_app.htm) みたいです。

JWT 接続便利。使うべし。

JWT 接続してから REST API 使うサンプルは [こちら](https://pitadigi.jp/salesforcedev/2020/02/10/1166/) を参照。

`$ composer require lcobucci/jwt`
jwt のインストールは必須です。

### Salesforce へデータを同期する

コードはこちら
https://github.com/geeorgey/selfLightningSync/blob/master/php/Googlecal_to_Salesforce.php

Salesforce の REST API を使って更新をします。
Salesforce の Event オブジェクトに
googleCalEventID**c : Google カレンダーの Event.id を入れています
googleCalEventID2**c : こちら、ユニーク項目として設定。 メアド+Event.id 形式にしてあります。Google カレンダーの Event.id が完全にユニークならこちらは不要ですが、仕様がよくわからなかったので。
というカスタム項目を作っています。

フォルダの中に Googlecal_to_Salesforce.php を突っ込んで cron で回してください。
[187 行目](https://github.com/geeorgey/selfLightningSync/blob/master/php/Googlecal_to_Salesforce.php#L187) に-5 分設定をしてあるので、5 分毎の cron を回しています。もっと短くても構わないとは思います。
$crontab -e

```crontabへの記述
*/5 * * * * /usr/bin/php /path to file/Googlecal_to_Salesforce.php 2>&1 | logger -t mycommand -p local0.info
```

##第二部：Salesforce の Event 情報の更新を GoogleCalendar に同期する
Event が更新されたときに、Google カレンダーに同期するしくみ。
コード類はこちら
https://github.com/geeorgey/selfLightningSync/tree/master/Apex
EventAllTrigger.apxt
で Event の更新をキャッチして Google カレンダーに同期をかける。

Event.googleCalEventID_c に Google カレンダーの Event.id が入っているので、それを使って upsert をかける。

##以上
Google カレンダーと Salesforce 側への同期は 5 分に一度。
Salesforce のカレンダーの更新はリアルタイムに処理されます。
