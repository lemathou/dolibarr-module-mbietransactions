<?php
/**
 * Copyright (C) 2020       MB Informatique         <info@mb-informatique.fr>
 * Copyright (C) 2022       Mathieu Moulin          <contact@iprospective.fr>
 */

if (!defined('NOLOGIN')) define("NOLOGIN", 1); // This means this output page does not require to be logged.
if (!defined('NOCSRFCHECK')) define("NOCSRFCHECK", 1); // We accept to go on this page from external web site.

global $db, $conf, $mysoc;

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

//var_dump($mysoc->email); die();

require_once DOL_DOCUMENT_ROOT . '/custom/mmipayments/class/mmi_payments.class.php';
dol_include_once('/mbietransactions/lib/create_link.lib.php');

$error = GETPOST('Erreur');
$autorisation = urldecode(GETPOST('Auto'));
$trans = urldecode(GETPOST('Trans'));
$mt = GETPOST('Mt');
$hash = GETPOST('Ref');

$email_info = 'Réf/Hash: '.$hash."\r\n"
	.'Trans: '.$trans."\r\n"
	.'Mt: '.$mt.' -> '.price(round($mt/100, 2), '', $langs, 0, - 1, - 1, $conf->currency)."\r\n"
	.'Autorisation: '.$autorisation."\r\n"
	.'Error Code: '.$error.($error=='00000' ?' -> OK' :'')."\r\n"
	.($error!='00000' ?'Error : '.mmi_etransactions::error($error)."\r\n" :'');

//var_dump($hash);
// On récupère que le "vrai" hash
$ehash = explode(' ', $hash);
if (count($ehash)>0) {
	if (strlen($ehash[count($ehash)-1])==32)
		$hash = $ehash[count($ehash)-1];
	elseif (strlen($ehash[0])==32)
		$hash = $ehash[0];
}
//var_dump($hash);

$obj = mmi_etransactions::info($hash);

$mail_to = [];
if ($conf->global->MBIETRANSACTIONS_NOTIFICATION_EMAIL)
	$mail_to[] = $conf->global->MBIETRANSACTIONS_NOTIFICATION_EMAIL;


//var_dump(DOL_MAIN_URL_ROOT);
//var_dump($success);

// Introuvable
if (empty($obj)) {
	if ($conf->global->MBIETRANSACTIONS_NOTIFICATION_EMAIL) {
		mmi_etransactions::notification_email(NULL, 'Etransactions : transaction introuvable', $email_info);
	}
	die('Introuvable');
}
$objecttype = $obj->objecttype;
$id = $obj->fk_object;
$email_info .= ($obj->mutiple ?'Plusieurs fois: '.$obj->mutiple."\r\n".'Montant total: '.$obj->amount."\r\n" :'');

// Mal enregistré
if (empty($objecttype) || empty($id)) {
	if ($conf->global->MBIETRANSACTIONS_NOTIFICATION_EMAIL) {
		mmi_etransactions::notification_email(NULL, 'Etransactions : Requête mal enregistrée', $email_info);
	}
	die('Mal enregistré');
}
$object = mmi_etransactions_loadobject($objecttype, $id);
//var_dump($hash.' - '.$object->ref.($object->thirdparty ?' - '.$object->thirdparty->name :'')); die();

// Pas d'objet référent
if (empty($object)) {
	if ($conf->global->MBIETRANSACTIONS_NOTIFICATION_EMAIL) {
		mmi_etransactions::notification_email(NULL, 'Etransactions : Transaction avec objet référent manquant', $email_info);
	}
	die('Objet référent manquant');
}
$ref = (!empty($object->ref) ?'Ref '.$object->ref :'Id '.$object->id);
$email_info .= "\r\n".$objecttype.' '.$ref."\r\n";

$client = $object->thirdparty;
//var_dump($client);
$email_info .= "\r\n".'Client Réf: '.$client->code_client."\r\n"
	.'Client: '.$client->name."\r\n";

if (!$trans) {
	if ($conf->global->MBIETRANSACTIONS_NOTIFICATION_EMAIL) {
		mmi_etransactions::notification_email(NULL, 'Etransactions : transaction sans numéro', $email_info);
	}
	die('Numéro de transaction invalide');
}
$obj_trans = mmi_etransactions::trans_info($hash, $trans, $autorisation);
//var_dump($obj, $obj_trans); die();

// Transaction déjà validée
if ($obj_trans) {
	if ($conf->global->MBIETRANSACTIONS_NOTIFICATION_EMAIL) {
		mmi_etransactions::notification_email(NULL, 'Etransactions : transaction déjà validée', $email_info);
	}
	die('Déjà validé');
}

// OK
if ($objecttype=='Propal')
	$object_url = 'https://'.$_SERVER['HTTP_HOST'].'/comm/propal/card.php?id='.$id;
elseif ($objecttype=='Commande')
	$object_url = 'https://'.$_SERVER['HTTP_HOST'].'/commande/card.php?id='.$id;
elseif ($objecttype=='Facture')
	$object_url = 'https://'.$_SERVER['HTTP_HOST'].'/compta/facture/card.php?id='.$id;
	
$accountid = $conf->global->MBIETRANSACTIONS_BANK_ACCOUNT;
//var_dump($accountid); die();
$amount = $mt / 100;

$resql2 = $db->query("SELECT *
	FROM " . MAIN_DB_PREFIX . "c_paiement
	WHERE code = 'CBI' AND entity = " . $conf->entity);
if ($resql2) {
	$obj2 = $db->fetch_object($resql2);
	if ($obj2) {
		$paiement_mode = $obj2->id;
	}
}

// Insert MBE Return

$sql = "INSERT INTO `".MAIN_DB_PREFIX."mbi_etransactions_return`
	(`fk_mbi_etransactions`, `mt`, `auto`, `erreur`, `trans`)
	VALUES
	(".$obj->rowid.", ".(is_numeric($mt) ?"'".$mt."'" :'NULL').", '".$autorisation."', '".$error."', '".$trans."')";
//echo $sql;
$q = $db->query($sql); //  AND `return_tms` IS NULL ?
$return_id = $db->last_insert_id(MAIN_DB_PREFIX.'mbi_etransactions_return');
//var_dump($db); die();
//var_dump($q);
//var_dump($return_id); die();

// Récupération liste contacts
//var_dump($object);
$contacts = $object->liste_contact(-1, 'internal');
$contacts_ok = false;
foreach($contacts as $contact) {
	$contacts_ok = true;
	if (!empty($contact->email) && !in_array($contact->email, $mail_to))
		$mail_to[] = $contact->email;
}
$contacts = $client->getSalesRepresentatives($user);
foreach($contacts as $contact) {
	$contacts_ok = true;
	if (!empty($contact['email']) && !in_array($contact['email'], $mail_to))
		$mail_to[] = $contact['email'];
}

// Foireux
if ($error !== '00000' || empty($autorisation) || empty($trans)) {
	if (!empty($mail_to)) {
		$ref = (!empty($object->ref) ?'Ref '.$object->ref :'Id '.$object->id);
		mmi_etransactions::notification_email(implode(',', $mail_to), 'Etransactions : Erreur retour paiement '.$objecttype.' '.$ref, $email_info);
	}
}
// Insert Paiement OK
else {
	$user = new User($db);
	$user->fetch(1); // Admin @todo créer user spécifique pour trucs auto ?

	$nb = mmi_etransactions::trans_query_nb($hash, $trans);
	if ($nb>1)
		$email_info .= "\r\n".'Echéance n°: '.$nb."\r\n";
		
	if (!empty($mail_to)) {
		mmi_etransactions::notification_email(implode(',', $mail_to),
			'Etransactions : Retour paiement '.$objecttype.' '.$ref.($nb>1 ?' - Echéance n°'.$nb :''),
			"Rendez-vous dans le Backoffice :\r\n- ".$object_url."\r\n"
			."\r\n".$email_info);
	}

	// Modification statut
	if($objecttype == 'Propal') {
		//var_dump($user, $object::STATUS_SIGNED);
		if (! in_array($object->statut, [2, 4])) // $object::STATUS_SIGNED OR $object::STATUS_BILLED
			$r = $object->closeProposal($user, $object::STATUS_SIGNED, 'Suite paiement Etransactions');
		//var_dump($r);
	}

	$infos = [
		//'date' => dol_now(), // automatique
		'amount' => $amount,
		'mode' => $paiement_mode,
		'num' => $trans,
		'note' => 'Etransactions Trans '.$trans.' pour '.$objecttype.' '.$ref,
		'accountid' => $accountid,
		//'chqemetteur' => '', // pas obligatoire
		//'chqbank' => '', // pas obligatoire
		'module_oid' => $return_id,
	];
	$paiement_id = mmi_payments::add($objecttype, $id, $infos);

	if ($paiement_id) {
		// Pour faciliter les liaisons dans l'autre sens
		// Ne sert plus à rien en fait car on peux join dans l'autre sens7
		// @depreacated Obsolète
		$sql = "UPDATE `".MAIN_DB_PREFIX."mbi_etransactions_return`
			SET fk_paiement=".$paiement_id."
			WHERE rowid=".$return_id;
		//echo $sql;
		$q = $db->query($sql); // AND `return_tms` IS NULL ?
	}
}

