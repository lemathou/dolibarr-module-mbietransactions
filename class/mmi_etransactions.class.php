<?php

dol_include_once('/custom/mmipayments/class/mmi_payments.class.php');

class mmi_etransactions
{

public static function __init()
{

}

public static function active($objecttype=NULL)
{
	global $conf;

	if (is_string($objecttype) && in_array($objecttype, ['Facture', 'Commande', 'Propal', 'Client']))
		return $conf->global->MBIETRANSACTIONS_TEST == "1" || (!empty($conf->global->MBIETRANSACTIONS_SHOP_ID) && !empty($conf->global->MBIETRANSACTIONS_RANK) && !empty($conf->global->MBIETRANSACTIONS_ID) && !empty($conf->global->MBIETRANSACTIONS_KEY) && !empty($conf->global->MBIETRANSACTIONS_BANK_ACCOUNT));
	else
		return false;
}

public static function payment_insert()
{

}

public static function paymentlink($objecttype, $id, $amount=NULL, $multiple=NULL, $autosubmit=false)
{
	global $db;
	
	if ($amount===NULL) {
		$total_regle = mmi_payments::total_regle($objecttype, $id);
		$object = mmi_payments::loadobject($objecttype, $id);
		$amount = round($object->total_ttc - $total_regle, 2);
	}

	$sql = "SELECT mh.`hash`
		FROM ".MAIN_DB_PREFIX."mbi_etransactions_hash mh
		LEFT JOIN ".MAIN_DB_PREFIX."mbi_etransactions_return mr
			ON mr.fk_mbi_etransactions=mh.rowid
		WHERE mr.`rowid` IS NULL
			AND mh.`objecttype`='".$objecttype."' AND mh.`fk_object` = '" . $id . "'
			AND mh.`amount`".(!empty($amount) && is_numeric($amount) ?'='.$amount :' IS NULL')."
			AND mh.`multiple`".(is_numeric($multiple) && $multiple>1 ?'='.$multiple :' IS NULL');
	//echo $sql;
	$q = $db->query($sql);
	//var_dump($q);
	if ($q) {
		if (!list($hash)=$q->fetch_row()) {
			$hash = static::createhash();
			$sql = "INSERT INTO " . MAIN_DB_PREFIX . "mbi_etransactions_hash
				(objecttype, fk_object, amount, multiple, hash)
				VALUES ('".$objecttype."', '" . $id . "', ".(!empty($amount) && is_numeric($amount) ?$amount :'NULL').", ".(is_numeric($multiple) && $multiple>1 ?$multiple :'NULL').", '" . $hash . "')";
			$db->query($sql);
			//echo $sql;
		}
		return dol_buildpath('/mbietransactions/payment.php', 2).'?hashp='.$hash.($autosubmit ?'&autosubmit' :'');
	}
}

public static function query($hash)
{
	global $db;

	$sql = "SELECT *
		FROM ".MAIN_DB_PREFIX."mbi_etransactions_hash
		WHERE `hash`='".$hash."'";
	//echo $sql;
	$resql = $db->query($sql);
	if (!($resql && ($obj = $db->fetch_object($resql))))
		return;

	return $obj;
}

public static function info($hash)
{
	return static::query($hash);
}

public static function trans_query_nb($hash, $trans, $ok=true)
{
	global $db;

	$sql = "SELECT COUNT(*)
		FROM ".MAIN_DB_PREFIX."mbi_etransactions_return mr
		INNER JOIN ".MAIN_DB_PREFIX."mbi_etransactions_hash mh ON mh.rowid=mr.fk_mbi_etransactions
		WHERE mh.`hash`='".$hash."' AND mr.`trans`='".$trans."'".($ok ?" AND mr.`erreur`='00000'" :'');
	//echo $sql;
	$resql = $db->query($sql);
	if (!$resql)
		return;
	if (!(list($nb) = $db->fetch_row($resql)))
		return;

	return $nb;
}

public static function trans_query($hash, $trans, $autorisation)
{
	global $db;

	$sql = "SELECT mr.*
		FROM ".MAIN_DB_PREFIX."mbi_etransactions_return mr
		INNER JOIN ".MAIN_DB_PREFIX."mbi_etransactions_hash mh ON mh.rowid=mr.fk_mbi_etransactions
		WHERE mh.`hash`='".$hash."' AND mr.`trans`='".$trans."' AND mr.`auto`='".$autorisation."'";
	//echo $sql;
	$resql = $db->query($sql);
	if (!$resql)
		return;
	if (!($obj = $db->fetch_object($resql)))
		return;

	return $obj;
}

public static function trans_info($hash, $trans, $autorisation)
{
	return static::trans_query($hash, $trans, $autorisation);
}

public static function retrieve($hash)
{
	if (!($obj = static::info($hash)))
		return;

	$objecttype = $obj->objecttype;
	$id = $obj->fk_object;

	return mmi_payments::loadobject($objecttype, $id);
}

public static function createhash()
{
	include_once DOL_DOCUMENT_ROOT.'/core/lib/security2.lib.php';
	$hashp = getRandomPassword(true);
	return $hashp;
}

public static function notification_email($email_to, $subject, $message)
{
	global $conf, $mysoc;
	
	if (empty($email_to))
		$email_to = (!empty($conf->global->MBIETRANSACTIONS_NOTIFICATION_EMAIL) ?$conf->global->MBIETRANSACTIONS_NOTIFICATION_EMAIL :$mysoc->email);
	
	$email_from = (!empty($conf->global->MBIETRANSACTIONS_NOTIFICATION_FROM_EMAIL) ?$conf->global->MBIETRANSACTIONS_NOTIFICATION_FROM_EMAIL :$mysoc->email);
	
	return mail($email_to,
		$subject,
		$message,
		$email_headers = 'From: '.$email_from."\r\n"
			.'Content-Type: text/plain; charset=UTF-8'."\r\n");
}

public static function object_mode_reglement_set($object, $code)
{
	global $db, $user;

	$sql = 'SELECT id
		FROM '.MAIN_DB_PREFIX.'c_paiement
		WHERE code="'.$code.'"';
	$q = $db->query($sql);
	if ($q && (list($pid)=$db->fetch_row($q))) {
		//echo $pid;
		$object->mode_reglement_id = $pid;
		//var_dump($object);
		$object->update($user);
	}
}

public static function error($code_error)
{
	switch ($code_error) {
	  case '00000':
		return 'Transaction approuvée. Opération réussie.';
		break;
	  case '00001':
		return 'La connexion au centre d\'autorisation a échoué, ou le client a annulé.';
		break;
	  case '00003':
		return 'Erreur de la plateforme bancaire.';
		break;
	  case '00004':
		return 'Numéro de porteur ou cryptogramme visuel invalide.';
		break;
	  case '00006':
		return 'Accès refusé.';
		break;
	  case '00008':
		return 'Date de fin de validité incorrecte.';
		break;
	  case '00009':
		return 'Erreur de création d\'un abonnement.';
		break;
	  case '00010':
		return 'Devise inconnue.';
		break;
	  case '00011':
		return 'Montant incorrect.';
		break;
	  case '00015':
		return 'Paiement déjà effectué.';
		break;
	  case '00016':
		return 'Abonnement déjà existant.';
		break;
	  case '00021':
		return 'Carte non autorisée.';
		break;
	  case '00029':
		return 'Carte non conforme.';
		break;
	  case '00030':
		return 'Temps d\'attente de plus de 15 minutes sur la page de paiements.';
		break;
	  case '00033':
		return 'Code pays de l\'adresse IP du navigateur de l\'acheteur non autorisé.';
		break;
	  case '00040':
		return 'Opération sans authentification 3-DSecure, bloquée par le filtre.';
		break;
	  case '00100':
		return 'Transaction approuvée ou traitée avec succès.';
		break;
	  case '00101':
		return 'Contacter l\'émetteur de carte.';
		break;
	  case '00102':
		return 'Contacter l\'émetteur de carte.';
		break;
	  case '00103':
		return 'Commerçant invalide.';
		break;
	  case '00104':
		return 'Conserver la carte.';
		break;
	  case '00105':
		return 'Ne pas honorer.';
		break;
	  case '00107':
		return 'Conserver la carte, conditions spéciales.';
		break;
	  case '00108':
		return 'Approuver après identification du porteur.';
		break;
	  case '00112':
		return 'Transaction invalide.';
		break;
	  case '00113':
		return 'Montant invalide.';
		break;
	  case '00114':
		return 'Numéro de porteur invalide.';
		break;
	  case '00115':
		return 'Emetteur de carte inconnu.';
		break;
	  case '00117':
		return 'Annulation client.';
		break;
	  case '00119':
		return 'Répéter la transaction ultérieurement.';
		break;
	  case '00120':
		return 'Réponse erronée (erreur dans le domaine serveur).';
		break;
	  case '00124':
		return 'Mise à jour de fichier non supportée.';
		break;
	  case '00125':
		return 'Impossible de localiser l\'enregistrement dans le fichier.';
		break;
	  case '00126':
		return 'Enregistrement dupliqué, ancien enregistrement remplacé.';
		break;
	  case '00127':
		return 'Erreur en « edit » sur champ de mise à jour fichier.';
		break;
	  case '00128':
		return 'Accès interdit au fichier.';
		break;
	  case '00129':
		return 'Mise à jour de fichier impossible.';
		break;
	  case '00130':
		return 'Erreur de format.';
		break;
	  case '00133':
		return 'Carte expirée.';
		break;
	  case '00138':
		return 'Nombre d\'essais code confidentiel dépassé.';
		break;
	  case '00141':
		return 'Carte perdue.';
		break;
	  case '00143':
		return 'Carte volée.';
		break;
	  case '00151':
		return 'Provision insuffisante ou crédit dépassé.';
		break;
	  case '00154':
		return 'Date de validité de la carte dépassée.';
		break;
	  case '00155':
		return 'Code confidentiel erroné.';
		break;
	  case '00156':
		return 'Carte absente du fichier.';
		break;
	  case '00157':
		return 'Transaction non permise à ce porteur.';
		break;
	  case '00158':
		return 'Transaction interdite au terminal.';
		break;
	  case '00159':
		return 'Suspicion de fraude.';
		break;
	  case '00160':
		return 'L\'accepteur de carte doit contacter l\'acquéreur.';
		break;
	  case '00161':
		return 'Dépasse la limite du montant de retrait.';
		break;
	  case '00163':
		return 'Règles de sécurité non respectées.';
		break;
	  case '00168':
		return 'Réponse non parvenue ou reçue trop tard.';
		break;
	  case '00175':
		return 'Nombre d\'essais code confidentiel dépassé.';
		break;
	  case '00176':
		return 'Porteur déjà en opposition, ancien enregistrement conservé.';
		break;
	  case '00189':
		return 'Echec de l’authentification.';
		break;
	  case '00190':
		return 'Arrêt momentané du système.';
		break;
	  case '00191':
		return 'Emetteur de cartes inaccessible.';
		break;
	  case '00194':
		return 'Demande dupliquée.';
		break;
	  case '00196':
		return 'Mauvais fonctionnement du système.';
		break;
	  case '00197':
		return 'Echéance de la temporisation de surveillance globale.';
		break;
	  case '99999':
		return 'Opération en attente de validation par l\'émetteur du moyen de paiement.';
		break;
	  default:
		return 'Erreur inconnue.';
	}
}

}

mmi_etransactions::__init();
