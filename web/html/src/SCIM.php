<?php
namespace scimAdminSV;

use PDO;
use PDOException;

class SCIM {
  private $scope = '';
  private $authURL = '';
  private $keyName = '';
  private $certFile = '';
  private $keyFile = '';
  private $apiURL = '';
  private $adminUsers = array();
  private $adminAccess = 0;
  private $scopeConfigured = false;
  private $userListFetched = false;
  private $userList = array();
  private $sentryDSN = '';

  const SCIM_USERS = 'Users/';
  const SCIM_GROUPS = 'Groups/';

  const SQL_INSTANCE = ':Instance';

  const SCIM_NUTID_SCHEMA = 'https://scim.eduid.se/schema/nutid/user/v1';

  public function __construct() {
    include __DIR__ . '/../config.php'; # NOSONAR
    try {
      $this->Db = new PDO("mysql:host=$dbServername;dbname=$dbName", $dbUsername, $dbPassword);
      // set the PDO error mode to exception
      $this->Db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    } catch(PDOException $e) {
      echo "Error: " . $e->getMessage();
    }

    $this->scope = str_replace('/','',$_SERVER['CONTEXT_PREFIX']);
    $this->authURL =  $authUrl;
    $this->keyName = $keyName;
    $this->certFile = $authCert;
    $this->keyFile = $authKey;
    $this->apiURL = $apiUrl;
    $this->sentryDSN = $sentryDSN;
    if (isset($instances[$this->scope])) {
      $this->scopeConfigured = true;
      $this->adminUsers = $instances[$this->scope]['adminUsers'];

      // Get token from DB. If no param exists create
      $paramsHandler = $this->Db->prepare('SELECT `value` FROM params WHERE `id` = :Id AND `instance` = :Instance;');
      $paramsHandler->bindValue(':Id', 'token');
      $paramsHandler->bindValue(self::SQL_INSTANCE, $this->scope);
      $paramsHandler->execute();
      if ($param = $paramsHandler->fetch(PDO::FETCH_ASSOC)) {
        $this->token = $param['value'];
      } else {
        $addParamsHandler = $this->Db->prepare('INSERT INTO params (`instance`,`id`, `value`)
          VALUES ( :Instance, ' ."'token', '')");
        $addParamsHandler->bindValue(self::SQL_INSTANCE, $this->scope);
        $addParamsHandler->execute();
        $this->getToken();
      }
    }
  }

  private function getToken() {
    $access = new \stdClass();
    $access->scope = $this->scope;
    $access->type = 'scim-api';

    $accessToken = new \stdClass();
    $accessToken->flags = array('bearer');
    $accessToken->access = array($access);

    $data = new \stdClass();
    $data->access_token = array($accessToken);
    $data->client = new \stdClass();
    $data->client->key = $this->keyName;

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $this->authURL . 'transaction');
    curl_setopt($ch, CURLOPT_PORT , 443);
    curl_setopt($ch, CURLOPT_VERBOSE, 0);
    curl_setopt($ch, CURLOPT_HEADER, 0);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
      'Accept: */*',
      'Content-Type: application/json'
    ));

    curl_setopt($ch, CURLOPT_SSLCERT, $this->certFile);

    curl_setopt($ch, CURLOPT_SSLKEY, $this->keyFile);
    curl_setopt($ch, CURLOPT_CAINFO, "/etc/ssl/certs/ca-certificates.crt");
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 1);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));

    $response = curl_exec($ch);
    if (curl_errno($ch) == 0) {
      $info = curl_getinfo($ch);
      curl_close($ch);
      switch ($info['http_code']) {
        case 200 :
        case 201 :
          $token = json_decode($response);
          $tokenValue = $token->access_token->value;

          $tokenHandler = $this->Db->prepare("UPDATE params
            SET `value` = :Token
            WHERE `id` = 'token' AND `instance` = :Instance");
          $tokenHandler->bindValue(':Token', $tokenValue);
          $tokenHandler->bindValue(self::SQL_INSTANCE, $this->scope);
          $tokenHandler->execute();
          $this->token = $tokenValue;
          break;
        default:
          print "<pre>";
          print_r($info);
          print "</pre>";
          print $response;
          $this->sendSentry('Error from auth-server : '. $response);
          exit;
          break;
      }
    } else {
      $this->sendSentry('Error on request to auth-server');
      print "Error on request to auth-server";
      curl_close($ch);
      exit;
    }
  }

  private function request($method, $part, $data= '', $extraHeaders = array(), $first = true) {
    $ch = curl_init();
    switch ($method) {
      case 'POST' :
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        break;
      case 'PUT' :
        curl_setopt($ch, CURLOPT_POST, 0);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        break;
      case 'DELETE' :
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        break;
      default :
        # GET
        curl_setopt($ch, CURLOPT_POST, 0);
        break;
    }
    $headers = array(
      'Accept: */*',
      'Content-Type: application/scim+json',
      'Authorization: Bearer ' . $this->token
    );
    curl_setopt($ch, CURLOPT_URL, $this->apiURL. htmlspecialchars($part));
    curl_setopt($ch, CURLOPT_PORT , 443);
    curl_setopt($ch, CURLOPT_VERBOSE, 0);
    curl_setopt($ch, CURLOPT_HEADER, 0);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array_merge($headers, $extraHeaders));
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 1);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

    $response = curl_exec($ch);

    if (curl_errno($ch) == 0) {
      $info = curl_getinfo($ch);
      switch ($info['http_code']) {
        case 200 :
          // User updated
        case 201 :
        case 204 :
          // User removed
          return $response;
        case 401 :
          $result = json_decode($response);
          switch ($result->detail) {
            case'Bearer token error' :
              if ($first) {
                $this->getToken();
                return $this->request($method, $part, $data, $extraHeaders, false);
              } else {
                // Capture a message
                $this->sendSentry('Failed to get Bearer token');
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
          break;
        default:
          print "<pre>";
          print_r($info);
          print "</pre><br><pre>";
          print $response;
          print "</pre><br><pre>";
          print $data;
          print "</pre>";
          $this->sendSentry('Error from scim-server : '. $response);
          exit;
          break;
      }
    } else {
      print "Error";
      return false;
    }
  }

  public function getAllUsers() {
    if ($this->userListFetched) {
      return $this->userList;
    }
    $totalResults = 5000;
    $index = 1;
    while ($index < $totalResults) {
      $idList = $this->request('POST',
        self::SCIM_USERS.'.search','{"schemas": ["urn:ietf:params:scim:api:messages:2.0:SearchRequest"],
        "attributes" : ["givenName", "familyName", "formatted", "externalId"],
        "filter": "meta.lastModified ge \"1900-01-01\"", "startIndex": '.$index.', "count": 300}');
      $index += 300;
      $idListArray = json_decode($idList);
      if (isset($idListArray->schemas) &&
        $idListArray->schemas[0] == 'urn:ietf:params:scim:api:messages:2.0:ListResponse' ) {
        $totalResults = $idListArray->totalResults;
        foreach ($idListArray->Resources as $Resource) {
          if (substr($Resource->externalId,0,3) != 'ma-') {
            $this->userList[$Resource->id] = array('id' => $Resource->id,
              'externalId' => $Resource->externalId,
              'fullName' => '', 'attributes' => false);
            $user = $this->request('GET', self::SCIM_USERS.$Resource->id);
            $userArray = json_decode($user);
            if (isset ($userArray->{self::SCIM_NUTID_SCHEMA})) {
              $this->userList[$Resource->id] = $this->checkNutid(
                $userArray->{self::SCIM_NUTID_SCHEMA},$this->userList[$Resource->id]);
            }
            if (isset($userArray->name->formatted)) {
              $this->userList[$Resource->id]['fullName'] = $userArray->name->formatted;
            } else {
              $this->userList[$Resource->id]['fullName'] = isset($userArray->name->givenName) ? $userArray->name->givenName : '';
              $this->userList[$Resource->id]['fullName'] .= ' ';
              $this->userList[$Resource->id]['fullName'] .= isset($userArray->name->familyName) ?
                $userArray->name->familyName : '';
            }
            unset($userArray);
            unset($user);
          }
        }
      } else {
        printf('Unknown schema : %s', $idListArray->schemas[0]);
        return false;
      }
    }
    $this->userListFetched = true;
    return $this->userList;
  }

  private function checkNutid($nutid, $userList) {
    if (isset($nutid->profiles) && sizeof((array)$nutid->profiles) && isset($nutid->profiles->connectIdp)) {
      $userList['attributes'] = $nutid->profiles->connectIdp->attributes;
    }
    return $userList;
  }

  public function getUser($id) {
    $user = $this->request('GET', self::SCIM_USERS.$id);
    return json_decode($user);
  }

  public function removeUser($id, $version) {
    return $this->request('DELETE', self::SCIM_USERS.$id, '', array('if-match: ' . $version));
  }

  public function getIdFromExternalId($externalId) {
    $request =
      sprintf('{"schemas": ["urn:ietf:params:scim:api:messages:2.0:SearchRequest"],
        "filter": "externalId eq \"%s\"", "startIndex": 1, "count": 1}',
        $externalId);
    $userInfo = $this->request('POST', self::SCIM_USERS.'.search', $request);
    $userArray = json_decode($userInfo);
    if ($userArray->totalResults == 1 && isset($userArray->Resources[0]->id)) {
      return $userArray->Resources[0]->id;
    } else {
      return false;
    }
  }

  public function createIdFromExternalId($externalId, $givenName, $familyName, $eppn) {
    $request =
      sprintf('{"schemas": ["urn:ietf:params:scim:schemas:core:2.0:User","https://scim.eduid.se/schema/nutid/user/v1"],
        "externalId": "%s",
        "name": {"familyName": "%s", "givenName": "%s", "formatted": "%s"},
        "https://scim.eduid.se/schema/nutid/user/v1":
        {"profiles": {"connectIdp": {"attributes": {"eduPersonPrincipalName": "%s"}}}}}',
        $externalId, $familyName, $givenName, $givenName . ' ' . $familyName, $eppn);

    $userInfo = $this->request('POST', self::SCIM_USERS, $request);
    $userArray = json_decode($userInfo);
    if (isset($userArray->id)) {
      return $userArray->id;
    } else {
      return false;
    }
  }

  public function updateId($id, $data, $version) {
    return $this->request('PUT', self::SCIM_USERS.$id, $data, array('if-match: ' . $version));
  }

  public function checkAccess($adminUser) {
    if (isset($this->adminUsers[$adminUser])) {
      $this->adminAccess = $this->adminUsers[$adminUser];
      return true;
    }
    return false;
  }

  public function getAdminAccess() {
    return $this->adminAccess;
  }

  public function validateID($id) {
    return filter_var($id, FILTER_VALIDATE_REGEXP,
      array("options"=>array("regexp"=>"/^[a-z,0-9]{8}-[a-z,0-9]{4}-[a-z,0-9]{4}-[a-z,0-9]{4}-[a-z,0-9]{12}$/")));
  }

  public function checkScopeConfigured() {
    return $this->scopeConfigured;
  }

  public function checkScopeExists($scope) {
    include './config.php'; # NOSONAR
    return isset($instances[$scope]);
  }

  public function getScope() {
    return $this->scope;
  }

  public function getGroupId($groupName) {
    $request =
    sprintf('{"schemas": ["urn:ietf:params:scim:api:messages:2.0:SearchRequest"],
      "filter": "displayName eq \"%s\""}',
      $groupName);
    $groupInfo = $this->request('POST', self::SCIM_GROUPS.'.search', $request);
    $groupArray = json_decode($groupInfo);
    if ($groupArray->totalResults == 1 && isset($groupArray->Resources[0]->id)) {
      return $groupArray->Resources[0]->id;
    } else {
      return false;
    }
  }

  public function createGroup($groupName) {
    $request =
      sprintf('{ "schemas": ["urn:ietf:params:scim:schemas:core:2.0:Group"], "displayName": "%s", "members": [] }',
        $groupName);
    $groupInfo = $this->request('POST', self::SCIM_GROUPS, $request);
    $groupArray = json_decode($groupInfo);
    if (isset($groupArray->id)) {
      return $groupArray->id;
    } else {
      return false;
    }
  }

  public function getGroup($id) {
    /*
    admin-gruppen kan heta vad som helst men ett förslag är "Organization Managers"
    elev-konto-managers måste ligga i en grupp med namnet "Account Managers" (edited) 
    */
    $group = $this->request('GET', self::SCIM_GROUPS.$id);
    return json_decode($group);
  }

  public function updateGroup($id, $data, $version) {
    return $this->request('PUT', self::SCIM_GROUPS.$id, $data, array('if-match: ' . $version));
  }

  private function sendSentry($message) {
    $client = new \Raven_Client($this->sentryDSN);
    $client->getIdent($client->captureMessage($message));
  }
}
