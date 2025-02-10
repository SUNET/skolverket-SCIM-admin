<?php
const SCIM_NUTID_SCHEMA = 'https://scim.eduid.se/schema/nutid/user/v1';
const LI_ITEM = '                <li>%s - %s</li>%s';

//Load composer's autoloader
require_once 'vendor/autoload.php';

include_once './config.php'; # NOSONAR

$html = new scimAdminSV\HTML();

$scim = new scimAdminSV\SCIM();

$errors = '';
$errorURL = isset($_SERVER['Meta-errorURL']) ?
  '<a href="' . $_SERVER['Meta-errorURL'] . '">Mer information</a><br>' : '<br>';
$errorURL = str_replace(array('ERRORURL_TS', 'ERRORURL_RP', 'ERRORURL_TID'),
  array(time(), 'https://'. $_SERVER['SERVER_NAME'] . '/shibboleth', $_SERVER['Shib-Session-ID']), $errorURL);

if (isset($_SERVER['eduPersonPrincipalName'])) {
  $AdminUser = $_SERVER['eduPersonPrincipalName'];
} elseif (isset($_SERVER['subject-id'])) {
  $AdminUser = $_SERVER['subject-id'];
} else {
  $errors .= 'Missing eduPersonPrincipalName in SAML response ' .
    str_replace(array('ERRORURL_CODE', 'ERRORURL_CTX'),
    array('IDENTIFICATION_FAILURE', 'eduPersonPrincipalName'), $errorURL);
}

if (isset($_SERVER['displayName'])) {
  $fullName = $_SERVER['displayName'];
} elseif (isset($_SERVER['givenName'])) {
  $fullName = $_SERVER['givenName'];
  if(isset($_SERVER['sn'])) {
    $fullName .= ' ' .$_SERVER['sn'];
  }
} else {
  $fullName = '';
}

if (isset($_SERVER['eduPersonAssurance'])) {
  $acceptedAssurance = false;
  foreach (explode(';', $_SERVER['eduPersonAssurance']) as $subAssurance) {
    if ($subAssurance == 'http://www.swamid.se/policy/assurance/al3') {
      $acceptedAssurance = true;
    }
  }
  if (! $acceptedAssurance) {
    $errors .= 'Kontot måste vara minst LoA2!';
  }
} else {
  $errors .= 'Missing eduPersonAssurance in SAML response ' .
    str_replace(array('ERRORURL_CODE', 'ERRORURL_CTX'),
    array('IDENTIFICATION_FAILURE', 'eduPersonAssurance'), $errorURL);
}

if ($scim->checkScopeConfigured()) {
  if (! $scim->checkAccess($AdminUser)) {
    $errors .= $AdminUser . ' is not allowed to login to this page.';
  }
} else {
  $userScope = preg_replace('/(.+)@/i', '', $AdminUser);
  if ($scim->checkScopeExists($userScope)) {
    header('Location: https://' . $_SERVER['HTTP_HOST'] . '/' . $userScope . '/');
    exit;
  } else {
    $errors .= $userScope . ' is not configured for this service.';
  }
}

if ($errors != '') {
  $html->showHeaders('SCIM Admin - Problem');
  printf('%s    <div class="row alert alert-danger" role="alert">
      <div class="col">%s        <b>Fel:</b><br>%s        %s%s      </div>%s    </div>%s',
    "\n", "\n", "\n", str_ireplace("\n", "<br>", $errors), "\n", "\n","\n");
  printf('    <div class="row alert alert-info" role="info">%s      <div class="col">
        Loggat in med fel Idp / Konto ?<br>Du har loggat in med <b>%s</b>.<br>Klicka <a href="%s">här</a> för att logga ut.
      </div>%s    </div>%s',
     "\n", $_SERVER['Shib-Identity-Provider'],
     'https://'. $_SERVER['SERVER_NAME'] . '/Shibboleth.sso/Logout?return=/', "\n", "\n");
  $html->showFooter(false);
  exit;
}

$displayName = '<div> Logged in as : <br> ' . $fullName . ' (' . $AdminUser .')<br><a href="/Shibboleth.sso/Logout?return=/"><button class="btn btn-primary btn-sm">Logga ut</button></a></div>';
$html->setDisplayName($displayName);
$html->showHeaders('SCIM Admin');

if (isset($_POST['action'])) {
  switch($_POST['action']) {
    case 'saveUser' :
      if ( $scim->getAdminAccess() > 19 ) {
        $id = isset($_POST['id']) ? $scim->validateID($_POST['id']) : false;
        if ($id) {
          saveUser($id);
        }
      }
      showMenu();
      listUsers($id);
      createAddUserForm();
      listGroupMembers();
      break;
    case 'createUser' :
      showMenu(2);
      if ( $scim->getAdminAccess() > 9 ) {
        createAddUserForm(true);
      }
      listUsers('', false);
      listGroupMembers();
      break;
    case 'addGroupMember' :
      if ( $scim->getAdminAccess() > 19 ) {
        addGroupMember($_POST['group'], $_POST['addMember']);
      }
      showMenu(3);
      listGroupMembers(true);
      listUsers('', false);
      createAddUserForm(false);
      break;
    default :
  }
} elseif (isset($_GET['action'])) {
  switch ($_GET['action']) {
    case 'editUser' :
      $id = isset($_GET['id']) ? $scim->validateID($_GET['id']) : false;
      if ( $scim->getAdminAccess() > 9 && $id) {
        editUser($id);
      } else {
        showMenu();
        listUsers($id);
        createAddUserForm();
        listGroupMembers();
      }
      break;
    case 'listUsers' :
      $id = isset($_GET['id']) ? $scim->validateID($_GET['id']) : false;
      showMenu();
      listUsers($id);
      createAddUserForm();
      listGroupMembers();
      break;
    case 'removeUser' :
      if ( $scim->getAdminAccess() > 19 ) {
        $id = isset($_GET['id']) ? $scim->validateID($_GET['id']) : false;
        if ($id) {
          removeUser($id);
        }
      }
      showMenu();
      listUsers($id);
      createAddUserForm();
      listGroupMembers();
      break;
    case 'removeGroupMember' :
      if ( $scim->getAdminAccess() > 19 ) {
        removeGroupMember($_GET['group'], $_GET['member']);
      }
      showMenu(3);
      listGroupMembers(true);
      listUsers('', false);
      createAddUserForm();
      break;
    default:
      # listUsers
      showMenu();
      listUsers();
      createAddUserForm();
      listGroupMembers();
      break;
  }
} else {
  showMenu();
  listUsers('', true);
  createAddUserForm();
  listGroupMembers();
}
print "        <br>\n";
$html->showFooter(true);

function listUsers($id='0-0', $shown = true) {
  global $scim;
  $users = $scim->getAllUsers();
  printf('        <table id="list-users-table" class="table table-striped table-bordered list-users"%s>
          <thead>
            <tr><th>eduID - Unikt ID</th><th>Namn</th></tr>
          </thead>
          <tbody>%s', $shown ? '' : ' hidden', "\n");
  foreach ($users as $user) {
    if (substr($user['externalId'],0,3) != 'ma-') {
      showUser($user, $id);
    }
  }
  printf('          <tbody>%s        </table>%s', "\n", "\n");
}

function showUser($user, $id) {
  printf('            <tr class="collapsible" data-id="%s" onclick="showId(\'%s\')">
              <td>%s</td>
              <td>%s</td>
            </tr>
            <tr class="content" style="display: %s;">
              <td colspan="2">
                <a a href="?action=editUser&id=%s"><button class="btn btn-primary btn-sm">Redigera</button></a>
                <a a href="?action=removeUser&id=%s"><button class="btn btn-primary btn-sm">Radera</button></a>
              </td>
            </tr>%s',
    $user['externalId'], $user['externalId'], isset($user['attributes']->eduPersonPrincipalName) ? $user['attributes']->eduPersonPrincipalName : '', $user['fullName'],
    $id == $user['id'] ? 'table-row' : 'none', $user['id'], $user['id'], "\n");
}

function editUser($id) {
  global $scim;

  $userArray = $scim->getUser($id);
  printf('        <form method="POST">
          <input type="hidden" name="action" value="saveUser">
          <input type="hidden" name="id" value="%s">
          <table id="entities-table" class="table table-striped table-bordered">
            <tbody>
              <tr><th>eduID - Unikt ID</th><td>%s</td></tr>
              <tr><th>Förnamn</th><td><input type="text" name="givenName" value="%s"></td></tr>
              <tr><th>Efternamn</th><td><input type="text" name="familyName" value="%s"></td></tr>%s',
    htmlspecialchars($id), substr($userArray->externalId, 0, 11),
    isset($userArray->name->givenName) ? $userArray->name->givenName : '',
    isset($userArray->name->familyName) ? $userArray->name->familyName : '',
    "\n");
  printf('            </tbody>
          </table>
          <div class="buttons">
            <button type="submit" class="btn btn-primary">Submit</button>
          </div>
        </form>
        <div class="buttons">
          <a href="?action=listUsers&id=%s"><button class="btn btn-secondary">Cancel</button></a>
        </div>%s',
    htmlspecialchars($id), "\n");
  if (isset($_GET['debug'])) {
    print "<pre>";
    print_r($userArray);
    #print json_encode($userArray);
    print "</pre>";
  }
}

function saveUser($id) {
  global $scim;
  if (isset($_POST['givenName']) && isset($_POST['familyName'])) {
    $userArray = $scim->getUser($id);

    $version = $userArray->meta->version;
    unset($userArray->meta);

    $userArray->name->givenName = $_POST['givenName'];
    $userArray->name->familyName = $_POST['familyName'];
    $userArray->name->formatted = $_POST['givenName'] . ' ' . $_POST['familyName'];

    $scim->updateId($id,json_encode($userArray),$version);
  }
}

function removeUser($id) {
  global $scim;
  $userArray = $scim->getUser($id);

  $version = $userArray->meta->version;

  $scim->removeUser($id,$version);
}

function createAddUserForm($shown = false) {
  printf('        <form id="create-user-form" method="POST"%s>
          <input type="hidden" name="action" value="createUser">
          <table id="user-table" class="table table-striped table-bordered">
            <tbody>
              <tr><th>eduID - Unikt ID</th><th>Förnamn</th><th>Efternamn</th><th></th></tr>%s',
    $shown ? '' : 'style="display: none;"', "\n");
  if (isset($_POST['populateUsers'])) {
    $usersArea = '';
    $lines = explode("\r\n", $_POST['users']);
    foreach ($lines as $line) {
      $fields = explode(";", $line);
      $error = '';
      $uniqID = '';
      $givenName = '';
      $familyName = '';
      if (isset($fields[0])) {
        $uniqID = $fields[0];
        if (strlen($fields[0]) != 11) {
          $error .= 'Felaktigt format på ID:t;';
        }
      } else {
        $error .= 'Saknar ett ID;';
      }

      if (isset($fields[1]) && $fields[1] != '') {
        $givenName = $fields[1];
      } else {
        $error .= 'Saknar ett förnamn;';
      }

      if (isset($fields[2]) && $fields[2] != '') {
        $familyName = $fields[2];
      } else {
        $error .= 'Saknar ett efternamn;';
      }

      if ($error == '') {
        $error = 'OK';
      }
      printf('              <tr>
                <td><input type="text" name="uniqID[]" value="%s"></td>
                <td><input type="text" name="givenName[]" value="%s"></td>
                <td><input type="text" name="familyName[]" value="%s"></td>
                <td>%s</td>
              </tr>%s', htmlspecialchars($uniqID), htmlspecialchars($givenName), htmlspecialchars($familyName), $error, "\n");
      $usersArea .= htmlspecialchars($uniqID) . ';' . htmlspecialchars($givenName) . ';' . htmlspecialchars($familyName) . ';' . $error . "\n";
    }
  } elseif (isset($_POST['createUsers'])) {
    $usersArea = '';
    $index = 0;
    while (isset($_POST['uniqID'][$index])) {
      $error = '';
      $uniqID = '';
      $givenName = '';
      $familyName = '';
      if (isset($_POST['uniqID'][$index])) {
        $uniqID = $_POST['uniqID'][$index];
        if (strlen($uniqID) != 11) {
          $error .= 'Felaktigt format på ID:t;';
        }
      } else {
        $error .= 'Saknar ett ID;';
      }

      if (isset($_POST['givenName'][$index]) && $_POST['givenName'][$index] != '') {
        $givenName = $_POST['givenName'][$index];
      } else {
        $error .= 'Saknar ett förnamn;';
      }

      if (isset($_POST['familyName'][$index]) && $_POST['familyName'][$index] != '') {
        $familyName = $_POST['familyName'][$index];
      } else {
        $error .= 'Saknar ett efternamn;';
      }

      if ($error == '') {
        $error = createUser($uniqID, $givenName, $familyName);
        printf('              <tr>
                <td>%s</td>
                <td>%s</td>
                <td>%s</td>
                <td>%s</td>
              </tr>', htmlspecialchars($uniqID), htmlspecialchars($givenName), htmlspecialchars($familyName), $error);

        $usersArea .= htmlspecialchars($uniqID) . ';' . htmlspecialchars($givenName) . ';' . htmlspecialchars($familyName) . ';' . $error . "\n";
      } elseif ($uniqID == '' && $givenName == '' && $familyName == '') {
        # Empty row. Skip
      } else {
        printf('              <tr>
                <td><input type="text" name="uniqID[]" value="%s"></td>
                <td><input type="text" name="givenName[]" value="%s"></td>
                <td><input type="text" name="familyName[]" value="%s"></td>
                <td>%s</td>
              </tr>%s', htmlspecialchars($uniqID), htmlspecialchars($givenName), htmlspecialchars($familyName), $error, "\n");
        $usersArea .= htmlspecialchars($uniqID) . ';' . htmlspecialchars($givenName) . ';' . htmlspecialchars($familyName) . ';' . $error . "\n";
      }
      $index ++;
    }
  } elseif (isset($_POST['users'])) {
    $usersArea = $_POST['users'];
  } else {
    $usersArea = '';
  }

  printf('              <tr>
                <td><input type="text" name="uniqID[]"></td>
                <td><input type="text" name="givenName[]"></td>
                <td><input type="text" name="familyName[]"></td>
                <td></td>
              </tr>
            </tbody>
          </table>
          <div class="buttons">
            <button type="submit" name="createUsers" class="btn btn-primary">Skapa användare</button>
          </div>
          <textarea id="users" name="users" rows="4" cols="100" placeholder="Unikt ID;Förnamn;Efternamn
kazof-vagus;Björn;Mattsson">%s</textarea>
          <div class="buttons">
            <button type="submit" name="populateUsers" class="btn btn-primary">Fyll i användare</button>
          </div>
        </form>%s', rtrim($usersArea), "\n");
}

function createUser($uniqID, $givenName, $familyName) {
  global $scim, $backendScope;
  $externalID = $uniqID . '@' . $backendScope;
  $eppn = $uniqID . '@' . $scim->getScope();
  if ($scim->getIdFromExternalId($externalID)) {
    return 'Kontot fanns redan';
  } else {
    if ($scim->createIdFromExternalId($externalID, $givenName, $familyName, $eppn)) {
      return 'Kontot skapat';
    } else {
      return 'Problem att skapa konto. Kontakta Admin';
    }
  }
}

function showMenu($show = 1) {
  global $scim, $result;
  printf ('        <label for="select">Välj</label>
        <div class="select">
          <select id="selectList">
            <option value="List Users">Lista användare</option>
            <option value="Create Users"%s>Skapa användare</option>
            <option value="Groups"%s>Grupper</option>
          </select>
        </div>
        <div class="result">%s</div>
        <br>
        <br>
        %s', $show == 2 ? ' selected' : '', $show == 3 ? ' selected' : '', $result, "\n");
}

function listGroupMembers($shown = false) {
  global $scim;
  #"Account Managers"
  if (! $accountManagersId = $scim->getGroupId("Account Managers")) {
    # Only run create if above fails. If added in same if clause both is run if 1:s is OK
    if (! $accountManagersId = $scim->createGroup("Account Managers")) { # NOSONAR
      printf ('Problem creating group "Account Managers"');
      exit;
    }
  }
  if ($accountManagersId) {
    $group = $scim->getGroup($accountManagersId);
    printf('        <table id="list-elev-admins-table" class="table table-striped table-bordered elev-admins"%s>
          <thead>
            <tr><th colspan="2">Rättighet att skapa elever</th></tr>
            <tr><th>Unikt ID</th><th>Namn</th></tr>
          </thead>
          <tbody>%s', $shown ? '' : ' hidden', "\n");
    $users = $scim->getAllUsers();
    foreach ($group->members as $member) {
      printf('            <tr>
            <td>%s <a href="?action=removeGroupMember&group=%s&member=%s">
              <button class="btn btn-primary btn-sm">Radera</button>
            </a></td>
            <td>%s</td>
          </tr>%s',
        $users[$member->value]['attributes']->eduPersonPrincipalName,
        $accountManagersId, $member->value,
        $member->display, "\n");
      unset($users[$member->value]);
    }
    printf ('            <tr><td colspan="2">Lägg till fler : <br>
          <form id="add-group-member" method="POST">
            <input type="hidden" name="action" value="addGroupMember">
            <input type="hidden" name="group" value="%s">
            <select id="addMember" name="addMember">%s', $accountManagersId, "\n");
    foreach ($users as $user) {
      if (isset($user['attributes']->eduPersonPrincipalName) && substr($user['externalId'],0,3) != 'ma-') {
        printf ('              <option value="%s">%s</option>%s', $user['id'], $user['fullName'], "\n");
      }
    }
    printf ('            </select>
            <div class="buttons">
              <button type="submit" name="addUsers" class="btn btn-primary">Lägg till användare</button>
            </div>
          </form>
        </td></tr>%s', "\n");
    printf('          <tbody>%s        </table>%s', "\n", "\n");
  }
}

function addGroupMember($groupId, $memberId) {
  global $scim;
  $ref = '$ref';
  $group = $scim->getGroup($groupId);
  $member = $scim->getUser($memberId);

  $newMember = new \stdClass();
  $newMember->value = $member->id;
  $newMember->$ref = $member->meta->location;
  $newMember->display = $member->name->formatted;
  $group->members[] = $newMember;

  $version = $group->meta->version;
  unset($group->meta);
  $scim->updateGroup($groupId, json_encode($group), $version);
}

function removeGroupMember($groupId, $memberId) {
  global $scim;
  $group = $scim->getGroup($groupId);

  foreach ($group->members as $key => $member) {
    if ($group->members[$key]->value == $memberId) {
      unset($group->members[$key]);
    }
  }
  sort($group->members);
  $version = $group->meta->version;
  unset($group->meta);
  $scim->updateGroup($groupId, json_encode($group), $version);
}
