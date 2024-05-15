<?php
//Load composer's autoloader
require_once 'vendor/autoload.php';
include_once './config.php'; # NOSONAR

function request($url) {
  $ch = curl_init();
  curl_setopt($ch, CURLOPT_URL, $url);
  curl_setopt($ch, CURLOPT_PORT , 443);
  curl_setopt($ch, CURLOPT_VERBOSE, 0);
  curl_setopt($ch, CURLOPT_HEADER, 0);
  curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 1);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

  $response = curl_exec($ch);

  if (curl_errno($ch) == 0) {
    $info = curl_getinfo($ch);
    switch ($info['http_code']) {
      case 200 :
        return $response;
      default:
        print "<pre>";
        print_r($info);
        print "</pre><br><pre>";
        print $response;
        print "</pre>";
        exit;
        break;
    }
  } else {
    print "Error";
    return false;
  }
}

$API = request($apiUrl.'status/healthy');
$API_json = json_decode($API);
$statusAPI = $API_json->status == 'STATUS_OK_scimapi_';

$auth = request($authUrl.'status/healthy');
$auth_json = json_decode($auth);
$statusAuth = $auth_json->status == 'STATUS_OK_';

if ($statusAPI) {
  if ($statusAuth) {
    print '{"status":"STATUS_OK_","reason":"API and AUTH tested OK"}';
  } else {
    print '{"status":"STATUS_FAIL_","reason":"AUTH Failed"}';
  }
} else {
  print '{"status":"STATUS_FAIL_","reason":"API Failed"}';
}
