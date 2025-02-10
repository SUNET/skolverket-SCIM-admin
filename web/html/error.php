<?php
//Load composer's autoloader
require_once 'vendor/autoload.php';
include_once './config.php'; # NOSONAR

$html = new scimAdminSV\HTML();
$html->showHeaders('SCIM Admin - Problem');

$errorURL = isset($_GET['errorURL']) ?
  'For more info visit this <a href="' . $_GET['errorURL'] . '">support-page</a>.' : '';
$errorURL = str_replace(array('ERRORURL_TS'), array(time()), $errorURL);
$errorURL = isset($_GET['RelayState']) ?
  str_replace(array('ERRORURL_RP'), array($_GET['RelayState'].'shibboleth'), $errorURL) : $errorURL;
$errorURL = isset($_SERVER['Shib-Session-ID']) ?
  str_replace(array('ERRORURL_TID'), array($_SERVER['Shib-Session-ID']), $errorURL) : $errorURL;


switch ($_GET['errorType']) {
  case 'opensaml::saml2md::MetadataException' :
    showMetadataException();
    break;
  case 'opensaml::FatalProfileException' :
    showFatalProfileException();
    break;
  default :
    showInfo();
}
$html->showFooter(false);

function showMetadataException() {?>
        <h1>Okänd Identity Provider</h1>
        <p>För att rapportera detta problem, kontakta <a href="https://www.skolverket.se/om-oss/kontakta-oss">Skolverket</a>.</p>
        <p>Inkludera följande i felmeddelandet:</p>
        <div class="alert-warning">
          <p class="error">Uppslagning av metadata för Identity provider misslyckade för (<?=htmlspecialchars($_GET['requestURL'])?>)</p>
          <p><strong>EntityID:</strong> <?=htmlspecialchars($_GET['entityID'])?></p>
          <p>Tidpunkt : <?=htmlspecialchars($_GET['now'])?></p>
          <p><?=htmlspecialchars($_GET['errorType'])?>: <?=htmlspecialchars($_GET['errorText'])?></p>
        </div>
<?php }

function showFatalProfileException() {?>
        <h1>Oanvändbar Identity Provider</h1>
        <p>Något gick fel vid inloggningen.</p>
        <p>För att rapportera detta problem, kontakta <a href="https://www.skolverket.se/om-oss/kontakta-oss">Skolverket</a>.</p>
        <p>Inkludera följande i felmeddelandet:</p>
        <div class="alert-warning">
          <p><strong>EntityID:</strong> <?=htmlspecialchars($_GET['entityID'])?></p>
          <p>Time : <?=htmlspecialchars($_GET['now'])?></p>
          <p><?=htmlspecialchars($_GET['errorType'])?>: <?=htmlspecialchars($_GET['errorText'])?></p><?php
    print isset($_GET['statusCode']) ?
      "\n          <p>statusCode : " . htmlspecialchars($_GET['statusCode']) . '</p>' : '';
    print isset($_GET['statusCode2']) ?
      "\n          <p>statusCode2 : " . htmlspecialchars($_GET['statusCode2']) . '</p>' : '';
    print isset($_GET['statusMessage']) ?
      "\n          <p>statusMessage : " . htmlspecialchars($_GET['statusMessage']) . '</p>' : '';
    print "\n        </div>\n";
 }

function showInfo() { ?>
    <table>
      <caption>Values</caption>
      <tr><th>Key</th><th>Value</th></tr>
    <?php
    foreach ($_GET as $key => $value) {
      printf('<tr><td>%s = %s</td></tr>%s', $key, htmlspecialchars($value), "\n");
    }
    print "</table>";
    ?>
<?php }
