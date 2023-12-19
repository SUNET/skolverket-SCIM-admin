<?php
$dbServername = "mariadb";
$dbUsername = "admin";
$dbPassword = "adminpwd";
$dbName = "scim";

$authUrl = 'https://auth-test.sunet.se/transaction';  # URL to the auth server to get token
$keyName = '<Name of Key>';                           # Naame of key in auth-server
$authCert = "<full path in OS>/authcert.pem";
$authKey = "<full path in OS>/authkey.pem";
$apiUrl = "https://api.dev.eduid.se/scim/test/";      # URL to the SCIM API

$instances = array (
  'sunet.se'=> array (
    'sourceIdP' => 'https://idp.sunet.se/idp',
    'backendIdP' => 'https://login.idp.eduid.se/idp.xml',
    # Array ('Shibb-name in apache' => 'name in satosa internal/SCIM')

    'attributes2migrate' => array (
      'eduPersonPrincipalName' => 'eduPersonPrincipalName'
    ),
    'adminUsers' => array (
      # user => level. 0-9 = view users 10 > Edit users,  20 > Invite new users
      'bjorn@sunet.se' => 20,
      'kazof-vagus@eduid.se' => 20
    )
  ),
);
