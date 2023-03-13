<?php
/**
 * Copyright (C) 2020       MB Informatique         <info@mb-informatique.fr>
 * Copyright (C) 2022       Mathieu Moulin          <contact@iprospective.fr>
 */

if (!defined('NOLOGIN')) define("NOLOGIN", 1); // This means this output page does not require to be logged.
if (!defined('NOCSRFCHECK')) define("NOCSRFCHECK", 1); // We accept to go on this page from external web site.

global $db, $conf, $langs;

$confError = false;

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

//require_once DOL_DOCUMENT_ROOT.'/core/lib/functions.lib.php';
dol_include_once('/mbietransactions/lib/create_link.lib.php');

$langs->loadLangs(array("mbietransactions@mbietransactions"));

$hash = $_GET['hashp'];
$autosubmit = isset($_GET['autosubmit']);

if ($obj=mmi_etransactions::info($hash)) {
	$objecttype = $obj->objecttype;
	$id = $obj->fk_object;
} else {
	$confError = true;
}
//var_dump($obj);

if (empty($objecttype))
	die('Unknown Hash');
//var_dump($objecttype); var_dump($id); die();

if (!mmi_etransactions::active($objecttype))
	$confError = true;

if (!($object = mmi_payments::loadobject($objecttype, $id)))
	die('Unknown Payment Object');

$totalttc = $object->total_ttc; // TOTAL TTC
if ($objecttype=='Facture') {
	$dejaregle = $object->getSommePaiement(($conf->multicurrency->enabled && $object->multicurrency_tx != 1) ? 1 : 0); // DEJA REGLE
	$resteapayer = ($totalttc - $dejaregle - $object->getSumCreditNotesUsed() - $object->getSumDepositsUsed()); // RESTE A PAYER
}
else {
	$dejaregle = mmi_payments::total_regle($objecttype, $object->id);;
	$resteapayer = ($totalttc - $dejaregle);
}
$extrafields2 = $object->array_options;

// Multiple : total ou solde
if ($obj->multiple) {
	$resteapayer = price2num($resteapayer);
	$extrafields2['options_mbi_payment_multiple'] = $obj->multiple;
}
// Acompte : d'un coup
elseif ($obj->amount && empty($dejaregle)) {
	$resteapayer = price2num($obj->amount);
	$extrafields2['options_mbi_payment_deposit'] = $obj->amount;
}
// Montant à la main
elseif ($obj->amount) {
	$resteapayer = price2num($obj->amount);
}

if ($resteapayer == 0) {
	$confError = true;
}

// Grosse parenthèse
if (!$confError) {
	// Mode test
	if ($conf->global->MBIETRANSACTIONS_TEST) {
		$pbx_site = $conf->global->MBIETRANSACTIONS_TEST_SHOP_ID;
		$pbx_rang = $conf->global->MBIETRANSACTIONS_TEST_RANK;
		$pbx_identifiant = $conf->global->MBIETRANSACTIONS_TEST_ID;
		$key = $conf->global->MBIETRANSACTIONS_TEST_KEY;
		$pbx_porteur = $conf->global->MAIN_INFO_SOCIETE_MAIL;
		$serveurs = array('preprod-tpeweb.e-transactions.fr');
	}
	// Mode prod
	else {
		$pbx_site = $conf->global->MBIETRANSACTIONS_SHOP_ID;
		$pbx_rang = $conf->global->MBIETRANSACTIONS_RANK;
		$pbx_identifiant = $conf->global->MBIETRANSACTIONS_ID;
		$key = $conf->global->MBIETRANSACTIONS_KEY;
		$pbx_porteur = $object->thirdparty->email;
		$serveurs = array('tpeweb.e-transactions.fr', 'tpeweb1.e-transactions.fr');
	}
	
	$pbx_total = $resteapayer * 100;
	//var_dump($object); die();
	$usercode = '';

	$contacts = $object->liste_contact(-1, 'internal');
	foreach($contacts as $contact) {
		//var_dump($contact);
		// Contact suivi commande
		if (in_array($contact['fk_c_type_contact'], [91, 31, 50, 140, 70])) {
			$contactuser = new User($db);
			$contactuser->fetch($contact['id']);
			//var_dump($user); die();
			$usercode = $contactuser->accountancy_code;
		}
	}
	if (empty($usercode) && $object->thirdparty) {
		$contactusers = $object->thirdparty->getSalesRepresentatives($user);
		foreach($contactusers as $contactuser2) {
			$contactuser = new User($db);
			$contactuser->fetch($contactuser2['id']);
			//var_dump($extrafields); die();
			//var_dump($user); die();
			$usercode = $contactuser->accountancy_code;
			break;
		}
	}
	$pbx_cmd = $object->ref.($object->thirdparty ?' - '.$object->thirdparty->name :'').(!empty($usercode) ?' - '.$usercode :'').' - '.$hash;
	//var_dump($pbx_cmd); die();
	
	if ($extrafields2['options_mbi_payment_deposit'] > 0 && $extrafields2['options_mbi_payment_deposit'] > $dejaregle) {
		$pbx_total = ($extrafields2['options_mbi_payment_deposit'] - $dejaregle) * 100;
	}
	$pbx_total = str_replace(",", "", $pbx_total);
	$pbx_total = str_replace(".", "", $pbx_total);

	if ($extrafields2['options_mbi_payment_multiple'] == "2" && empty($extrafields2['options_mbi_payment_deposit'])) {
		$total = $resteapayer * 100;
		$pbx_total = floor($total / 2);
		$pbx_2mont1 = $total - $pbx_total;
		$pbx_date1 = date('d/m/Y',strtotime('+1 month'));
	} else if ($extrafields2['options_mbi_payment_multiple'] == "3" && empty($extrafields2['options_mbi_payment_deposit'])) {
		$total = $resteapayer * 100;
		$pbx_total = floor($total / 3);
		$pbx_2mont1 = $pbx_total;
		$pbx_2mont2 = $total - ($pbx_total + $pbx_2mont1);
		$pbx_date1 = date('d/m/Y',strtotime('+1 month'));
		$pbx_date2 = date('d/m/Y',strtotime('+2 month'));
	}

	$pbx_effectue = dol_buildpath($path . '/mbietransactions/accepted.php', 2);
	$pbx_annule = dol_buildpath($path . '/mbietransactions/canceled.php', 2);
	$pbx_refuse = dol_buildpath($path . '/mbietransactions/refused.php', 2);
	$pbx_repondre_a = str_replace('http:', 'https:', dol_buildpath($path . '/mbietransactions/retour.php', 2));
	$pbx_retour = 'Mt:M;Ref:R;Auto:A;Erreur:E;Trans:T';


// --------------- TESTS DE DISPONIBILITE DES SERVEURS ---------------

	$serveurOK = "";
	foreach ($serveurs as $serveur) {
		$doc = new DOMDocument();
		$doc->loadHTMLFile('https://' . $serveur . '/load.html');
		$server_status = "";
		$element = $doc->getElementById('server_status');
		if ($element) {
			$server_status = $element->textContent;
		}
		if ($server_status == "OK") {
			$serveurOK = $serveur;
			break;
		}
	}

	if (!$serveurOK) {
		die("Erreur : Aucun serveur fonctionnel n'a été trouvé");
	}

	$serveurOK = 'https://' . $serveurOK . '/cgi/MYchoix_pagepaiement.cgi';

// --------------- TRAITEMENT DES VARIABLES ---------------

	$dateTime = date("c");

	$msg = "PBX_SITE=" . $pbx_site .
		"&PBX_RANG=" . $pbx_rang .
		"&PBX_IDENTIFIANT=" . $pbx_identifiant .
		"&PBX_TOTAL=" . $pbx_total .
		"&PBX_DEVISE=978" .
		"&PBX_CMD=" . $pbx_cmd .
		"&PBX_PORTEUR=" . $pbx_porteur .
		"&PBX_REPONDRE_A=" . $pbx_repondre_a .
		"&PBX_RETOUR=" . $pbx_retour .
		"&PBX_EFFECTUE=" . $pbx_effectue .
		"&PBX_ANNULE=" . $pbx_annule .
		"&PBX_REFUSE=" . $pbx_refuse .
		"&PBX_HASH=SHA512" .
		"&PBX_TIME=" . $dateTime;

	if ($extrafields2['options_mbi_payment_multiple'] == "2" && empty($extrafields2['options_mbi_payment_deposit'])) {
		$msg .= "&PBX_2MONT1=" . $pbx_2mont1;
		$msg .= "&PBX_DATE1=" . $pbx_date1;
	} else if ($extrafields2['options_mbi_payment_multiple'] == "3" && empty($extrafields2['options_mbi_payment_deposit'])) {
		$msg .= "&PBX_2MONT1=" . $pbx_2mont1;
		$msg .= "&PBX_DATE1=" . $pbx_date1;
		$msg .= "&PBX_2MONT2=" . $pbx_2mont2;
		$msg .= "&PBX_DATE2=" . $pbx_date2;
	}

	$binKey = pack("H*", $key);
	$hmac = strtoupper(hash_hmac('sha512', $msg, $binKey));

	$form = "<form id='payment_form' method='POST' action='" . $serveurOK . "'>"
	."<input type='hidden' name='PBX_SITE' value='" . $pbx_site . "'>"
	."<input type='hidden' name='PBX_RANG' value='" . $pbx_rang . "'>"
	."<input type='hidden' name='PBX_IDENTIFIANT' value='" . $pbx_identifiant . "'>"
	."<input type='hidden' name='PBX_TOTAL' value='" . $pbx_total . "'>"
	."<input type='hidden' name='PBX_DEVISE' value='978'>"
	."<input type='hidden' name='PBX_CMD' value='" . $pbx_cmd . "'>"
	."<input type='hidden' name='PBX_PORTEUR' value='" . $pbx_porteur . "'>"
	."<input type='hidden' name='PBX_REPONDRE_A' value='" . $pbx_repondre_a . "'>"
	."<input type='hidden' name='PBX_RETOUR' value='" . $pbx_retour . "'>"
	."<input type='hidden' name='PBX_EFFECTUE' value='" . $pbx_effectue . "'>"
	."<input type='hidden' name='PBX_ANNULE' value='" . $pbx_annule . "'>"
	."<input type='hidden' name='PBX_REFUSE' value='" . $pbx_refuse . "'>"
	."<input type='hidden' name='PBX_HASH' value='SHA512'>"
	."<input type='hidden' name='PBX_TIME' value='" . $dateTime . "'>";
	if ($extrafields2['options_mbi_payment_multiple'] == "2" && empty($extrafields2['options_mbi_payment_deposit'])) {
		$form .= "<input type='hidden' name='PBX_2MONT1' value='" . $pbx_2mont1 . "'>";
		$form .= "<input type='hidden' name='PBX_DATE1' value='" . $pbx_date1 . "'>";
	} else if ($extrafields2['options_mbi_payment_multiple'] == "3" && empty($extrafields2['options_mbi_payment_deposit'])) {
		$form .= "<input type='hidden' name='PBX_2MONT1' value='" . $pbx_2mont1 . "'>";
		$form .= "<input type='hidden' name='PBX_DATE1' value='" . $pbx_date1 . "'>";
		$form .= "<input type='hidden' name='PBX_2MONT2' value='" . $pbx_2mont2 . "'>";
		$form .= "<input type='hidden' name='PBX_DATE2' value='" . $pbx_date2 . "'>";
	}
	$form .= "<input type='hidden' name='PBX_HMAC' value='" . $hmac . "'>";
	$form .= "<input class='button' type='submit' value='" . $langs->trans("MBIETransactionsPaymentPageContinue") . "'>";
	$form .= "</form>";
	
	// REDIRECTION DIRECTE

	if ($autosubmit) {
		echo $form;
		echo '<script type="text/javascript">document.getElementById(\'payment_form\').submit();</script>';
		die();
	}
	
	// AFFICHAGE COMPLET

	echo "<head><meta name='robots' content='noindex,nofollow'><title>" . $langs->trans("MBIETransactionsPaymentPageTitle") . "</title>";
	echo "<link rel='stylesheet' type='text/css' href='" . DOL_URL_ROOT . $conf->css . "?lang=" . $langs->defaultlang . "'>";
	echo "<link rel='stylesheet' type='text/css' href='" . DOL_URL_ROOT . $path . "/mbietransactions/style.css'></head>";
	echo "<div id='logo'>";
	if (!empty($mysoc->logo)) {
		echo "<img width='150px;' id='paymentlogo' title='" . $conf->global->MAIN_INFO_SOCIETE_NOM . "' src='" . DOL_URL_ROOT . "/viewimage.php?modulepart=mycompany&amp;file=" . urlencode('logos/' . $mysoc->logo) . "'>";
	}
	echo "</div>";

	echo "<div id='payment-content'>";
	echo "<h1>" . $langs->trans("MBIETransactionsPaymentPageH1") . "</h1><br />";
	echo "<p style='font-size: 1.2em;'>" . $langs->trans("MBIETransactionsPaymentRecapTitle") . " <strong>" . $conf->global->MAIN_INFO_SOCIETE_NOM . "</strong></p>";
	echo "<p style='font-size: 1.2em;'>" . $langs->trans("MBIETransactionsPaymentRecapVerify") . " <br />" . $langs->trans("MBIETransactionsPaymentRecapVerify2") . " <strong>" . $conf->global->MAIN_INFO_SOCIETE_MAIL . "</strong> " . $langs->trans("MBIETransactionsPaymentRecapVerify3") . "</p>";
	echo "<table id='payment-table' style='font-size: 1.2em;'>";
	echo "<tr class='liste_total'><td colspan='2'>" . $langs->trans("MBIETransactionsPaymentRecapInfos") . "</td></tr>";
	echo "<tr><td class='payment-row-left'>" . $langs->trans("MBIETransactionsPaymentRecapRecipient") . "</td><td class='payment-row-right'><strong>" . $conf->global->MAIN_INFO_SOCIETE_NOM . "</strong></td></tr>";
	echo "<tr><td class='payment-row-left'>" . $langs->trans("MBIETransactionsPaymentRecapInvoiceRef") . "</td><td class='payment-row-right'><strong>" . $object->ref . "</strong></td></tr>";
	echo "<tr><td class='payment-row-left'>" . $langs->trans("MBIETransactionsPaymentRecapTransactionRef") . "</td><td class='payment-row-right'><strong>" . $pbx_cmd . "</strong></td></tr>";
	echo "<tr><td class='payment-row-left'>" . $langs->trans("MBIETransactionsPaymentRecapName") . "</td><td class='payment-row-right'><strong>" . $object->thirdparty->name . "</strong></td></tr>";
	echo "<tr><td class='payment-row-left'>" . $langs->trans("MBIETransactionsPaymentRecapEmail") . "</td><td class='payment-row-right'><strong>" . $object->thirdparty->email . "</strong></td></tr>";
	echo "<tr class='liste_total'><td colspan'2'>&nbsp;</td></tr>";
	echo "<tr><td class='payment-row-left'>" . $langs->trans("MBIETransactionsPaymentRecapInvoiceAmount") . "</td><td class='payment-row-right'><strong>" . price($totalttc) . " € TTC</strong></td></tr>";
	echo "<tr><td class='payment-row-left'>" . $langs->trans("MBIETransactionsPaymentRecapAlreadypaid") . "</td><td class='payment-row-right'><strong>" . price($dejaregle) . " € TTC</strong></td></tr>";
	// Acompte paramétré
	if (!empty($extrafields2['options_mbi_payment_deposit']) && empty($dejaregle)) {
		echo "<tr><td class='payment-row-left'><strong>" . $langs->trans("MBIETransactionsPaymentRecapAdvance") . "</strong></td><td class='payment-row-right'><strong>" . price($pbx_total / 100) . " € TTC</strong></td></tr>";
	} else {
		echo "<tr><td class='payment-row-left'><strong>" . $langs->trans("MBIETransactionsPaymentRecapPaymentAmount") . "</strong></td><td class='payment-row-right'><strong>" . price($pbx_total / 100) . " € TTC</strong></td></tr>";
	}
	// Paiement en plusieurs fois
	if ($extrafields2['options_mbi_payment_multiple'] == "2" && empty($extrafields2['options_mbi_payment_deposit'])) {
		echo "<tr><td>" . $langs->trans("MBIETransactionsPaymentPageDeadline") . " : " . price($pbx_2mont1 / 100) . " € TTC - " . $pbx_date1 . "</td></tr>";
	} else if ($extrafields2['options_mbi_payment_multiple'] == "3" && empty($extrafields2['options_mbi_payment_deposit'])) {
		echo "<tr><td>" . $langs->trans("MBIETransactionsPaymentPageDeadline") . " : " . price($pbx_2mont1 / 100) . " € TTC - " . $pbx_date1 . "</td></tr>";
		echo "<tr><td>" . $langs->trans("MBIETransactionsPaymentPageDeadline") . " : " . price($pbx_2mont2 / 100) . " € TTC - " . $pbx_date2 . "</td></tr>";
	}

	echo "<tr class='liste_total'><td colspan='2' class='payment-button'>".$form."</td></tr></table></div><br><br>";

	echo "<div><img style='width:250px' src='" . DOL_URL_ROOT . $path . "/mbietransactions/img/ca-e-transactions-cb-visa-mastercard.jpg'>";
	echo "<p>" . $conf->global->MAIN_INFO_SOCIETE_NOM . " © " . date('Y') . "<br><small>";
	if ($conf->global->MAIN_INFO_SIRET) {
		echo "SIRET: " . $conf->global->MAIN_INFO_SIRET;
	}
	echo "<br>" . $conf->global->MAIN_INFO_SOCIETE_ADDRESS . " " . $conf->global->MAIN_INFO_SOCIETE_ZIP . " " . $conf->global->MAIN_INFO_SOCIETE_TOWN . " - " . $conf->global->MAIN_INFO_SOCIETE_TEL . "</small></p>";
	echo "</div>";

}
// ERREUR de configuration
else {

	echo "<head><meta name='robots' content='noindex,nofollow'><title>" . $langs->trans("MBIETransactionsPaymentPageTitle") . "</title>";
	echo "<link rel='stylesheet' type='text/css' href='" .  DOL_URL_ROOT . $conf->css . "?lang=" . $langs->defaultlang . "'>";
	echo "<link rel='stylesheet' type='text/css' href='" . DOL_URL_ROOT . $path . "/mbietransactions/style.css'></head>";
	echo "<div id='logo'>";
	if (!empty($mysoc->logo)) {
		echo "<img width='150px;' id='paymentlogo' title='" . $conf->global->MAIN_INFO_SOCIETE_NOM . "' src='" . DOL_URL_ROOT . "/viewimage.php?modulepart=mycompany&amp;file=" . urlencode('logos/'.$mysoc->logo) ."'>";
	}
	echo "</div>";

	if ($resteapayer == 0) {
		echo "<div id='payment-content'>
	<h1 style='font-size: 1.6em;'>" . $langs->trans("MBIETransactionsPaymentPageH1") . "</h1>
	<p style='font-size: 1.2em;'>" . $langs->trans("MBIETransactionsErrorConfigAlreadyPaid") . "</p>
	</div>";
	} else {
		echo "<div id='payment-content'>
	<h1 style='font-size: 1.6em;'>" . $langs->trans("MBIETransactionsPaymentPageH1") . "</h1>
	<p style='font-size: 1.2em;'>" . $langs->trans("MBIETransactionsErrorConfig") . " <strong>" . $conf->global->MAIN_INFO_SOCIETE_MAIL . "</strong></p>
	</div>";
	}
	?>
	<br /><br />
	<div>
		<img style="width:250px" src="<?php echo DOL_URL_ROOT . $path . "/mbietransactions/img/ca-e-transactions-cb-visa-mastercard.jpg"; ?>" />
		<p><?php echo $conf->global->MAIN_INFO_SOCIETE_NOM; ?> © <?php echo date('Y') ?><br><small><?php if ($conf->global->MAIN_INFO_SIRET) { echo "SIRET: " . $conf->global->MAIN_INFO_SIRET; } ?><br><?php echo $conf->global->MAIN_INFO_SOCIETE_ADDRESS; ?> <?php echo $conf->global->MAIN_INFO_SOCIETE_ZIP; ?> <?php echo $conf->global->MAIN_INFO_SOCIETE_TOWN; ?> - <?php echo $conf->global->MAIN_INFO_SOCIETE_TEL; ?></small></p>
	</div>

	<?php
}
