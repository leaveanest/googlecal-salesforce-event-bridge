# selfLightningSync
自前でLightning Syncを実装する
前回こんなのを書いたのですが、その続きです。
[SalesforceのApexでチームスピリットの出退勤情報からGoogleカレンダーに登録する処理](https://qiita.com/geeorgey/items/59de75ae7faf351743c1)

SalesforceにはLightning Syncという機能が提供されており、それを使えばこんなことをしなくても同期できるのですが、__Einstein活動キャプチャという機能をONにしていると、Lightning Syncが使えない__ので、自前実装するしかありません。なんでそんな仕様なんだろう🤔

## ToDo
- 第一部：GoogleCalendarの更新情報をSalesforceに同期する
- 第二部：SalesforceのEvent情報の更新をGoogleCalendarに同期する

## 第一部：GoogleCalendarの更新情報をSalesforceに同期する
###必要要件
GoogleからSalesforceへの接続には、別途サーバが必要になります。
なるべく簡単に開発できるようにと思ったのですが、mac標準で入っているphpにはJSONエクステンションが入っていないために利用できませんでした。以下のコードを使う場合は、JSONエクステンション、SOAPエクステンションが入ったphp環境でお試しください。
### 同期に必要な要件
Salesforce側に同期するので、今回のスクリプトでは
- Salesforceのユーザを調べてEmailとSalesforceのUser.idを取得します
- Emailリストを使ってGoogleCalendarAPIを叩き、カレンダー情報を取得します
- 取得したGoogleカレンダー情報をSalesforceのEventに同期します
GoogleAPIにはPUSH通知機能があるのですが、面倒なので今回は使っていません。
任意の間隔でGoogleAPIを叩き、その時間の間に更新された予定をSalesforce側に同期しています。

##Google APIの利用を開始する
PHPから使いますので、こちらをインストールします。
[Google APIs Client Library for PHP](https://github.com/googleapis/google-api-php-client)
書かれているとおりcomposerをインストールする必要があります。既にインストール済み環境の場合はインストール部分はスキップしてください。
手順は
- Composerのインストール
- フォルダが無いので作る
- $ sudo mkdir -p /usr/local/bin
- インストールするためのコマンドはこちらに書かれています。https://getcomposer.org/download/
- インストールしたらcomposer.pharを移動。
- mv composer.phar /usr/local/bin/composer
ここまでcomposerのインストール部分。

- Google APIs Client Library for PHPをインストールする
- $ composer require google/apiclient:"^2.0"

cd docker
docker-compose build
docker-compose up -d 
docker-compose exec gcaltosf php /usr/src/app/Googlecal_to_Salesforce.php

### Google APIsで新規プロジェクトを作成
こちらから：https://console.developers.google.com/apis/
プロジェクト作成後に認証情報ひらき、認証情報を作成＞OAuthクライアントIDを作成。
<img width="938" alt="oauth.png" src="https://qiita-image-store.s3.ap-northeast-1.amazonaws.com/0/22294/6281b7c4-0c12-ce5b-2b20-b243b536af97.png">
コールバックURLはひとまずこれを入れておきます。なんでもいいです
http://localhost:8080/

<img width="1425" alt="ウェブ_アプリケーション_のクライアン…_–_API_とサービス_–_Lnest-SF-Gcal_–_Google_API_Console.png" src="https://qiita-image-store.s3.ap-northeast-1.amazonaws.com/0/22294/6bb5ede9-6d94-cb6e-edf9-1ed95cc90bb4.png">

JSONファイルをダウンロードして作業フォルダの中に入れましょう。
その際、ファイル名を
credentials.json
に変えておくと、GoogleAPIのサンプルがそのまま動きます。

ライブラリから以下の2つのAPIを検索して利用開始してください。
- Google Calendar API

Googleのドキュメントはこちら
Calendar APIのページ：https://developers.google.com/calendar/quickstart/php

ここまで来たらGoogle Calendar APIのサンプルを試してみてください。
上述のCalendar APIのページにあるコードをそのまま
index.php
に書いて実行してみます。
ターミナルで
```php index.php```
と打つと、こんなレスポンスが返ってきます。
```Open the following link in your browser:
https://accounts.google.com/o/oauth2/auth?response_type=code&access_type=offline&client_id=4674xxxxxxxx8-xxxxxxxxxxxxxxxxxxxxorp6r9j8bf0n.apps.googleusercontent.com&redirect_uri=http%3A%2F%2Flocalhost:8080%index.php&state&scope=https%3A%2F%2Fwww.googleapis.com%2Fauth%2Fcalendar.readonly&prompt=select_account%20consent
Enter verification code:
```
書かれている通り、URLをブラウザで開きましょう。この部分です。
https://accounts.google.com/o/oauth2/auth?response_type=code&access_type=offline&client_id=46xxxxxx18-cxxxxxxxxxxxxxxxx0n.apps.googleusercontent.com&redirect_uri=http%3A%2F%2Flocalhost:8080%index.php&state&scope=https%3A%2F%2Fwww.googleapis.com%2Fauth%2Fcalendar.readonly&prompt=select_account%20consent

これを開くと、GoogleのOAuth画面に行くので自分のGoogleアカウントで開き、Google Calendar APIへのアクセス許可が求められるので承認します。するとこうなります。
![localhost.png](https://qiita-image-store.s3.ap-northeast-1.amazonaws.com/0/22294/704a13a4-c036-bb41-78ab-b5fc1107ef10.png)
そりゃそうです。何も表示できるように作ってないのですから。重要なのは画面表示ではなく、そのURLです。
```http://localhost:8080/SyncGoogleCalToSalesforceEvent.php?code=4/ygFFXpRLYvJnbN5LO2EF**********************YIzAt65GxE-vKwYb2mYdTnm--CfIetczXU&scope=https://www.googleapis.com/auth/calendar.readonly```
みたいなURLになっているとおもうのですが、その code= のあと
4/ygFFXpRLYvJから最後までをコピーして、Enter verification code:のところに入力してください。
そうすることによって、実行したフォルダの中にtoken.jsonができているはずです。これでGoogleの認証が終わります。
もう一度実行してみると、カレンダーの予定が10件表示されることがわかるでしょう。

```$ php index.php```

![credentials_json_—_public.png](https://qiita-image-store.s3.ap-northeast-1.amazonaws.com/0/22294/03bc7c99-8b4e-a30d-98f9-81feafe7c237.png)

###SalesforceへJWT接続するための設定をする
やり方はこちらを参照してください
[Salesforce JWT用の接続アプリを共有して組織への接続を簡単にする
](https://qiita.com/stomita/items/4b0efdec3792b4fa706b)

コールバックURLにhttp://localhost:1717/OauthRedirect を設定する部分が意味わからなかったのですが、 [そういう仕様](https://developer.salesforce.com/docs/atlas.ja-jp.sfdx_dev.meta/sfdx_dev/sfdx_dev_auth_connected_app.htm) みたいです。

JWT接続便利。使うべし。

JWT接続してからREST API使うサンプルは [こちら](https://pitadigi.jp/salesforcedev/2020/02/10/1166/) を参照。

```$ composer require lcobucci/jwt```
jwtのインストールは必須です。

### Salesforceへデータを同期する
コードはこちら
https://github.com/geeorgey/selfLightningSync/blob/master/php/Googlecal_to_Salesforce.php

SalesforceのREST APIを使って更新をします。
SalesforceのEventオブジェクトに
googleCalEventID__c : GoogleカレンダーのEvent.idを入れています
googleCalEventID2__c : こちら、ユニーク項目として設定。 メアド+Event.id 形式にしてあります。GoogleカレンダーのEvent.idが完全にユニークならこちらは不要ですが、仕様がよくわからなかったので。
というカスタム項目を作っています。

フォルダの中にGooglecal_to_Salesforce.phpを突っ込んでcronで回してください。
[187行目](https://github.com/geeorgey/selfLightningSync/blob/master/php/Googlecal_to_Salesforce.php#L187) に-5分設定をしてあるので、5分毎のcronを回しています。もっと短くても構わないとは思います。
$crontab -e
``` crontabへの記述
*/5 * * * * /usr/bin/php /path to file/Googlecal_to_Salesforce.php 2>&1 | logger -t mycommand -p local0.info 
```

##第二部：SalesforceのEvent情報の更新をGoogleCalendarに同期する
Eventが更新されたときに、Googleカレンダーに同期するしくみ。
コード類はこちら
https://github.com/geeorgey/selfLightningSync/tree/master/Apex
EventAllTrigger.apxt
でEventの更新をキャッチしてGoogleカレンダーに同期をかける。

Event.googleCalEventID_c に Googleカレンダーの Event.idが入っているので、それを使ってupsertをかける。

##以上
GoogleカレンダーとSalesforce側への同期は5分に一度。
Salesforceのカレンダーの更新はリアルタイムに処理されます。
