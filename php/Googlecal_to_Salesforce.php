<?php

use Lcobucci\JWT\Builder;
use Lcobucci\JWT\Signer\Key;
use Lcobucci\JWT\Signer\Rsa\Sha256;
// ログインURL
// 本番: https://login.salesforce.com
// Sandbox: https://test.login.salesforce.com
// スクラッチ組織: https://test.saleforce.com
define('LOGIN_URL', 'https://login.salesforce.com');
// 認証URL
define('AUTH_URL', LOGIN_URL . '/services/oauth2/token');
// コンシューマ鍵
define('CLIENT_ID', '3MVG9xxxxxxxxxxxxxxxxxxxxxxxx');//接続アプリのID
// ユーザID
define('USER_ID', 'y@lne.st');//ログインユーザを指定する
// 認証タイプ
define('GRANT_TYPE', 'urn:ietf:params:oauth:grant-type:jwt-bearer');
chdir(dirname(__FILE__));
function createjwt() {
    $signer = new Sha256();
    $privateKey = new Key('file://server.key');//JWT接続設定を行ったときのkeyを読み込む
    $time = time();
    
    $token = (new Builder())->issuedBy(CLIENT_ID) // iss: コンシューマ鍵
                            ->permittedFor(LOGIN_URL) // aud: SalesforceログインURL
                            ->relatedTo(USER_ID) // sub: SalesforceユーザID
                            ->expiresAt($time + 3 * 60) // exp: 3分以内
                            ->getToken($signer,  $privateKey);
    return $token;
}
function auth() {
    $jwt = createjwt();
    $post = array(
        'grant_type' => GRANT_TYPE,
        'assertion' => $jwt,
    );
    $curl = curl_init();
    curl_setopt( $curl, CURLOPT_URL, AUTH_URL );
    curl_setopt( $curl, CURLOPT_RETURNTRANSFER, 1 );
    curl_setopt( $curl, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_2);
    curl_setopt( $curl, CURLOPT_POSTFIELDS, $post );
    $buf = curl_exec( $curl );
    if ( curl_errno( $curl ) ) {
        exit;
    }
    curl_close( $curl );
    
    $json = json_decode( $buf );
    $accinfo = array(
        // アクセスするためのURL
        'instance_url' => $json->instance_url,
        // アクセスするために使用するBearerトークン
        'access_token' => $json->access_token,
    );
    return $accinfo;
}

function getuserfromsf($accinfo){
    $query = "select email,id from User WHERE isActive = TRUE";//有効なユーザを取得するSOQL
    $url = $accinfo['instance_url'] . "/services/data/v47.0/query?q="  . urlencode($query);
    $header = array(
        'Content-Type: application/json;charset=UTF-8',
        'Authorization: Bearer ' . $accinfo['access_token'],
    );
    $curl = curl_init();
    curl_setopt($curl, CURLOPT_URL, $url);
    curl_setopt($curl, CURLOPT_HEADER, false);
    curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
    curl_setopt($curl, CURLOPT_GET, true);
    curl_setopt($curl, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_2);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true );
    curl_setopt($curl, CURLOPT_VERBOSE, true);
    $response = json_decode(curl_exec($curl), true);
    curl_close($curl);
    return $response;
}

$accinfo = auth();
$response = getuserfromsf($accinfo);

function eventsUpsert($accinfo,$sObject_Event){
    $url = $accinfo['instance_url'] . "/services/data/v47.0/composite/";
    $header = array(
        'Content-Type: application/json;charset=UTF-8',
        'Authorization: Bearer ' . $accinfo['access_token'],
    );

    $curl = curl_init();
    curl_setopt($curl, CURLOPT_URL, $url);
    curl_setopt($curl, CURLOPT_HEADER, false);
    curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
    $to_json_records -> compositeRequest = $sObject_Event;
    curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($to_json_records));
    curl_setopt($curl, CURLOPT_POST, true);
    curl_setopt($curl, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_2);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true );
    curl_setopt($curl, CURLOPT_VERBOSE, true);
    $response = json_decode(curl_exec($curl), true);
    curl_close($curl);    
    return $response;
}

$emailAndIDs = [];//EmailとSalesforceのUseridの配列
foreach($response['records'] as $record){
    if(!empty($record['Email'])){
        $userid = array($record['Email']=>$record['Id']);
        $emailAndIDs = array_merge($emailAndIDs,$userid);
    }
}


/**
 * Returns an authorized API google_client.
 * @return Google_Client the authorized google_client object
 */
function getGoogleClient()
{
    $google_client = new Google_Client();
    $google_client->setApplicationName('G Suite Directory API PHP Quickstart');
    $google_client->setScopes([//scope変更するときはtoken.jsonを削除すればやり直せる
        Google_Service_Directory::ADMIN_DIRECTORY_USER_READONLY,
        Google_Service_Calendar::CALENDAR_READONLY
    ]);
    $google_client->setAuthConfig('credentials.json');
    $google_client->setAccessType('offline');
    $google_client->setPrompt('select_account consent');

    // Load previously authorized token from a file, if it exists.
    // The file token.json stores the user's access and refresh tokens, and is
    // created automatically when the authorization flow completes for the first
    // time.
    $tokenPath = 'token.json';
    if (file_exists($tokenPath)) {
        $accessToken = json_decode(file_get_contents($tokenPath), true);
        $google_client->setAccessToken($accessToken);
    }

    // If there is no previous token or it's expired.
    if ($google_client->isAccessTokenExpired()) {
        // Refresh the token if possible, else fetch a new one.
        if ($google_client->getRefreshToken()) {
            $google_client->fetchAccessTokenWithRefreshToken($google_client->getRefreshToken());
        } else {
            // Request authorization from the user.
            $authUrl = $google_client->createAuthUrl();
            printf("Open the following link in your browser:\n%s\n", $authUrl);
            print 'Enter verification code: ';
            $authCode = trim(fgets(STDIN));

            // Exchange authorization code for an access token.
            $accessToken = $google_client->fetchAccessTokenWithAuthCode($authCode);
            $google_client->setAccessToken($accessToken);

            // Check to see if there was an error.
            if (array_key_exists('error', $accessToken)) {
                throw new Exception(join(', ', $accessToken));
            }
        }
        // Save the token to a file.
        if (!file_exists(dirname($tokenPath))) {
            mkdir(dirname($tokenPath), 0700, true);
        }
        file_put_contents($tokenPath, json_encode($google_client->getAccessToken()));
    }
    return $google_client;
}

// Get the API client and construct the service object.
$client = getGoogleClient();
$service = new Google_Service_Calendar($client);

$uid = 0;
$updateEvents = array();
$recid = 0;
$sObject_Event[] = new stdclass();
foreach ($emailAndIDs as $line){//ユーザーのEmailを使ってループ
	$calendarId = array_keys($emailAndIDs)[$uid];
	$OwnerId = array_values($emailAndIDs)[$uid];
	if($calendarId){
	    $optParams = array(
	        //一度に取得するカレンダー情報量。最大2500
            'maxResults' => 2500,
	        //更新時間順に取得する(古い順)ただし、updateMinの所で過去5分以内の更新のみ取得するように制限してある
            'orderBy' => 'updated',
    	    //更新時間5分以内のレコードを取得する
	        'updatedMin' => date('c',strtotime( "-5 min" )),
    	);
	$results = $service->events->listEvents($calendarId, $optParams);
	$uid++ ;


	if (count($results->getItems()) == 0) {
	  print "No upcoming events found.\n";
	} else {
	  foreach ($results->getItems() as $event) {
	    $id = $event->id;
		$email = $calendarId;
		$gcaluID = $email.$id;
		if(empty($event->end->dateTime)){
			$endDate = $event->end->date;
		}else{
            $end = $event->end->dateTime;
		}
		if(empty($event->start->dateTime)){
		    $startDate = $event->start->date;
		    $allDayDuration = (strtotime($endDate) - strtotime($startDate))/ ( 60 );
		}else{
		    $start = $event->start->dateTime;
		}
		$attendeesOmitted = $event->attendeesOmitted;
	    $status = $event->status;
		$org = $event->organizer->email;
		//招待イベントの時の処理を書く
		if($calendarId !== $org){
			$ischild = "TRUE";
			}else{
			$ischild = "FALSE";
		}
		$sObject_Event[$recid]->referenceId = $id;
        if ($attendeesOmitted == true || $status == 'cancelled') {
            $sObject_Event[$recid]->method = "DELETE";
        }else{
            $sObject_Event[$recid]->method = "PATCH";
        }
		$sObject_Event[$recid]->url = "/services/data/v47.0/sobjects/Event/googleCalEventID2__c/".$gcaluID;//外部IDにgoogleCalEventID2__cを利用
		$sObject_Event[$recid]->body->googleCalEventID__c = $id;
		$sObject_Event[$recid]->body->OWNERID = $OwnerId;
		if(empty($start)) {
            $sObject_Event[$recid]->body->ActivityDate = $startDate;
            $sObject_Event[$recid]->body->IsAllDayEvent = true;
            $sObject_Event[$recid]->body->DurationInMinutes = $allDayDuration;
		}else{
    		$sObject_Event[$recid]->body->StartDateTime = $start;
		}
		if(empty($end)) {
		}else{
    		$sObject_Event[$recid]->body->EndDateTime = $end;
		}
        $sObject_Event[$recid]->body->Subject = $event->getSummary();
        if(strpos($event->description,'</a>') === false){//URLが含まれていない場合は改行コードを変換する
            $sObject_Event[$recid]->body->Description = str_replace('<br>', '\r\n', $event->description);//カレンダーの改行<br>を改行コードに変換
        }else{
            $sObject_Event[$recid]->body->Description = str_replace('<br>', ' ', $event->description);//カレンダーの改行<br>をスペース変換(URL含む場合は改行する方法が無い)
        }
		$sObject_Event[$recid]->body->Location = $event->location;
		$sObject_Event[$recid]->body->WhatId = '0061xxxxxxxxxxxxx';//全員で共有する為に便宜上特定の商談に予定を紐付けてしまっている。不要であればこの行は削除
		$recid++ ;
	  }
	}

    }
}

// SFへのUPdateここから
$arr = array_chunk($sObject_Event,25);//25件以上一度に処理できない
for($arc = 0;$arc < count($arr);$arc++){
    $SF_upsert_response = eventsUpsert($accinfo,$arr[$arc]);
}
