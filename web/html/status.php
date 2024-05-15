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
  #curl_setopt($ch, CURLOPT_HTTPHEADER, array_merge($headers, $extraHeaders));
  curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 1);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

  $response = curl_exec($ch);

  if (curl_errno($ch) == 0) {
    $info = curl_getinfo($ch);
    switch ($info['http_code']) {
      case 200 :
      #case 201 :
      #case 204 :
        return $response;
/*      case 401 :
        $result = json_decode($response);
        switch ($result->detail) {
          case'Bearer token error' :
            if ($first) {
              $this->getToken();
              return $this->request($method, $part, $data, $extraHeaders, false);
            } else {
              print "Fail to get Bearer token";
              exit;
            }
            break;
          case 'Data owner requested in access token denied' :
            print "We doesn't have access to this Data-owner via the configured key.";
            exit;
            break;
          default :
            print_r($result);
            exit;
        }
        break;
      case 404 :
        $result = json_decode($response);
        if ($result->detail == 'User not found') {
          return 'User didn\'t exists';
        } else {
          print_r($result);
          exit;
        }
        break;
      case 422 :
        $result = json_decode($response);
        if ($result->scimType == 'invalidSyntax') {
          print "<pre>";
          print_r($result->detail);
          print "</pre>";
          exit;
        } else {
          print "<pre>";
          print_r($result);
          print "</pre>";
          exit;
        }
        break;*/
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

#$authUrl = 'https://auth-test.sunet.se/transaction';
#$apiUrl = "https://api.dev.eduid.se/scim/test/";
$API = request($apiUrl.'status/healthy');
$API_json = json_decode($API);
$statusAPI = $API_json->status == 'STATUS_OK_scimapi_';

$auth = request($authUrl.'status/healthy');
$auth_json = json_decode($auth);
$statusAuth = $auth_json->status == 'STATUS_OK_';

if ($statusAPI) {
  if ($statusAuth) {
    print ('{"status":"STATUS_OK_","reason":"API and AUTH tested OK"}');
  } else {
    print ('{"status":"STATUS_FAIL_","reason":"AUTH Failed"}');
  }
} else {
  print ('{"status":"STATUS_FAIL_","reason":"API Failed"}');
}
