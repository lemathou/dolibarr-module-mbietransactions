<?php

// Copyright (C) 2020      MB Informatique      <info@mb-informatique.fr>

if (!defined('NOLOGIN')) define("NOLOGIN", 1); // This means this output page does not require to be logged.
if (!defined('NOCSRFCHECK')) define("NOCSRFCHECK", 1); // We accept to go on this page from external web site.

global $db, $conf, $langs;

try {
	if (!file_exists('../../main.inc.php'))
		throw new Exception ('Does not exist');
	else
		require '../../main.inc.php';
	$path = "/custom";
}
catch(Exception $e) {
	require '../main.inc.php';
	$path = "";
}

$langs->loadLangs(array("mbietransactions@mbietransactions"));

echo "<head><meta name='robots' content='noindex,nofollow'><title>" . $langs->trans("MBIETransactionsPaymentPageTitle") . "</title>";
echo "<link rel='stylesheet' type='text/css' href='" .  DOL_URL_ROOT . $conf->css . "?lang=" . $langs->defaultlang . "'>";
echo "<link rel='stylesheet' type='text/css' href='" . DOL_URL_ROOT . $path . "/mbietransactions/style.css'></head>";
echo "<div id='logo'>";
if (!empty($mysoc->logo)) {
	echo "<img width='150px;' id='paymentlogo' title='" . $conf->global->MAIN_INFO_SOCIETE_NOM . "' src='" . DOL_URL_ROOT . "/viewimage.php?modulepart=mycompany&amp;file=" . urlencode('logos/'.$mysoc->logo) ."'>";
}
echo "</div>";

echo "<div id='payment-content'>
<h1 style='font-size: 1.6em;'>" . $langs->trans("MBIETransactionsPaymentPageH1") . "</h1>
<p style='font-size: 1.2em;'>" . $langs->trans("MBIETransactionsPaymentAccepted") . "<strong> " . $conf->global->MAIN_INFO_SOCIETE_NOM . "</strong> " . $langs->trans("MBIETransactionsPaymentAccepted2") . "</p>
<p style='font-size: 1.2em;'>" . $langs->trans("MBIETransactionsPaymentAccepted3") . "</p>
</div>";

?>

<br><br>
<div>
	<img style="width:250px" src="<?php echo DOL_URL_ROOT . $path . "/mbietransactions/img/ca-e-transactions-cb-visa-mastercard.jpg"; ?>" />
	<p><?php echo $conf->global->MAIN_INFO_SOCIETE_NOM; ?> © <?php echo date('Y') ?><br><small><?php if ($conf->global->MAIN_INFO_SIRET) { echo "SIRET: " . $conf->global->MAIN_INFO_SIRET; } ?><br><?php echo $conf->global->MAIN_INFO_SOCIETE_ADDRESS; ?> <?php echo $conf->global->MAIN_INFO_SOCIETE_ZIP; ?> <?php echo $conf->global->MAIN_INFO_SOCIETE_TOWN; ?> - <?php echo $conf->global->MAIN_INFO_SOCIETE_TEL; ?></small></p>
</div>
