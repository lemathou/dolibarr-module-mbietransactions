<?php
/**
 * Copyright (C) 2026  Mathieu Moulin / iProspective  <contact@iprospective.fr>
 *
 * Onglet "eTransactions" sur la fiche tiers : historique des transactions
 * eTransactions (réussites et échecs), lien vers le paiement Dolibarr associé
 * et action de recréation du paiement Dolibarr s'il a été supprimé par erreur.
 */

require_once 'env.inc.php';
require_once 'main_load.inc.php';

require_once DOL_DOCUMENT_ROOT.'/core/lib/company.lib.php';
require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';

dol_include_once('/mbietransactions/class/mmi_etransactions.class.php');

$langs->loadLangs(array("mbietransactions@mbietransactions", "companies", "bills", "banks"));

$socid = GETPOSTINT('socid');
$action = GETPOST('action', 'aZ09');

// Sécurité : accès limité aux utilisateurs autorisés sur les tiers
if (!$user->hasRight('societe', 'lire')) {
	accessforbidden();
}
$object = new Societe($db);
if ($socid > 0) {
	$object->fetch($socid);
}
if (empty($object->id)) {
	accessforbidden('Tiers introuvable');
}
restrictedArea($user, 'societe', $object->id, '&societe');

/*
 * Actions
 */

$messages = array();

if ($action == 'recreate_payment' && GETPOSTINT('return_id')) {
	if (!$user->hasRight('facture', 'creer') && !$user->hasRight('commande', 'creer')) {
		accessforbidden('Droit de création de paiement requis');
	}
	// Le contrôle du jeton CSRF est assuré automatiquement par main.inc.php (POST + token)

	$res = mmi_etransactions::recreate_payment(GETPOSTINT('return_id'));
	if (!empty($res['success'])) {
		setEventMessages($langs->trans("MBIETransactionsPaymentRecreated", $res['paiement_id']), null, 'mesgs');
	} else {
		setEventMessages(!empty($res['error']) ? $res['error'] : $langs->trans("MBIETransactionsPaymentRecreateError"), null, 'errors');
	}
	// Redirection pour éviter le re-POST
	header('Location: '.$_SERVER['PHP_SELF'].'?socid='.$socid);
	exit;
}

/*
 * View
 */

$form = new Form($db);

$title = $langs->trans("MBIETransactionsTabTitle").' - '.$object->name;
llxHeader('', $title);

$head = societe_prepare_head($object);
print dol_get_fiche_head($head, 'etransactions', $langs->trans("ThirdParty"), -1, 'company');

$linkback = '<a href="'.DOL_URL_ROOT.'/societe/list.php?restore_lastsearch_values=1">'.$langs->trans("BackToList").'</a>';
dol_banner_tab($object, 'socid', $linkback, ($user->socid ? 0 : 1), 'rowid', 'nom');

print '<div class="fichecenter">';
print '<div class="underbanner clearboth"></div>';
print '</div>';

print dol_get_fiche_end();

print '<br>';

// Récupération des transactions
$transactions = mmi_etransactions::client_transactions($object->id);

// Statistiques rapides
$nb_total = count($transactions);
$nb_ok = 0;
$nb_missing = 0;
$total_ok = 0;
foreach ($transactions as $t) {
	if ($t->erreur === '00000') {
		$nb_ok++;
		$total_ok += $t->mt / 100;
		if (empty($t->paiement_id)) {
			$nb_missing++;
		}
	}
}

print '<div class="div-table-responsive">';
print '<table class="noborder centpercent">';

print '<thead>';
print '<tr class="liste_titre">';
print '<th>'.$langs->trans("Date").'</th>';
print '<th>'.$langs->trans("Object").'</th>';
print '<th class="right">'.$langs->trans("Amount").'</th>';
print '<th class="center">'.$langs->trans("Status").'</th>';
print '<th>'.$langs->trans("MBIETransactionsTransRef").'</th>';
print '<th>'.$langs->trans("MBIETransactionsDolibarrPayment").'</th>';
print '</tr>';
print '</thead>';

print '<tbody>';

if (empty($transactions)) {
	print '<tr><td colspan="6" class="opacitymedium center">'.$langs->trans("MBIETransactionsNoTransaction").'</td></tr>';
}

foreach ($transactions as $t) {
	$is_ok = ($t->erreur === '00000');

	// URL de l'objet métier
	$object_url = '';
	if ($t->objecttype == 'Facture') {
		$object_url = DOL_URL_ROOT.'/compta/facture/card.php?id='.$t->fk_object;
	} elseif ($t->objecttype == 'Commande') {
		$object_url = DOL_URL_ROOT.'/commande/card.php?id='.$t->fk_object;
	} elseif ($t->objecttype == 'Propal') {
		$object_url = DOL_URL_ROOT.'/comm/propal/card.php?id='.$t->fk_object;
	}
	$object_label = $t->objecttype.' '.($t->object_ref ? $t->object_ref : '#'.$t->fk_object);
	if (!empty($t->cb_multiple) && (int) $t->cb_multiple > 1) {
		$object_label .= ' <span class="opacitymedium">('.((int) $t->cb_multiple).'X)</span>';
	}

	print '<tr class="oddeven">';

	// Date
	print '<td class="nowraponall">'.dol_print_date($db->jdate($t->return_date), 'dayhour').'</td>';

	// Objet
	print '<td>';
	if ($object_url) {
		print '<a href="'.$object_url.'">'.$object_label.'</a>';
	} else {
		print $object_label;
	}
	print '</td>';

	// Montant
	print '<td class="right nowraponall">'.price($t->mt / 100, 0, $langs, 1, -1, -1, $conf->currency).'</td>';

	// Statut
	print '<td class="center">';
	if ($is_ok) {
		print '<span class="badge badge-status4 badge-status">'.$langs->trans("MBIETransactionsStatusOk").'</span>';
	} else {
		print '<span class="badge badge-status8 badge-status" title="'.dol_escape_htmltag(mmi_etransactions::error($t->erreur)).'">'.$langs->trans("MBIETransactionsStatusKo").' ('.$t->erreur.')</span>';
		print '<br><span class="opacitymedium small">'.dol_escape_htmltag(mmi_etransactions::error($t->erreur)).'</span>';
	}
	print '</td>';

	// Référence transaction
	print '<td class="nowraponall"><span class="opacitymedium small">'.$langs->trans("MBIETransactionsTrans").':</span> '.dol_escape_htmltag($t->trans);
	if (!empty($t->auto)) {
		print '<br><span class="opacitymedium small">'.$langs->trans("MBIETransactionsAuto").':</span> '.dol_escape_htmltag($t->auto);
	}
	print '</td>';

	// Paiement Dolibarr
	print '<td>';
	if (!empty($t->paiement_id)) {
		// Paiement existant : lien dans les deux sens
		print '<a href="'.DOL_URL_ROOT.'/compta/paiement/card.php?id='.$t->paiement_id.'">';
		print img_picto('', 'payment', 'class="paddingright"').($t->paiement_ref ? $t->paiement_ref : $langs->trans("Payment").' #'.$t->paiement_id);
		print '</a>';
	} elseif ($is_ok) {
		// Transaction réussie sans paiement Dolibarr : proposer la recréation
		print '<span class="error">'.img_warning().' '.$langs->trans("MBIETransactionsPaymentMissing").'</span> ';
		print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'?socid='.$object->id.'" style="display:inline;" onsubmit="return confirm(\''.dol_escape_js($langs->trans("MBIETransactionsConfirmRecreate")).'\');">';
		print '<input type="hidden" name="token" value="'.newToken().'">';
		print '<input type="hidden" name="action" value="recreate_payment">';
		print '<input type="hidden" name="return_id" value="'.$t->return_id.'">';
		print '<button type="submit" class="button smallpaddingimp">'.$langs->trans("MBIETransactionsRecreatePayment").'</button>';
		print '</form>';
	} else {
		print '<span class="opacitymedium">-</span>';
	}
	print '</td>';

	print '</tr>';
}

print '</tbody>';
print '</table>';
print '</div>';

// Récapitulatif
print '<br>';
print '<div class="opacitymedium">';
print $langs->trans("MBIETransactionsRecapTransactions", $nb_total, $nb_ok).' &mdash; ';
print $langs->trans("MBIETransactionsRecapTotalOk").' : '.price($total_ok, 0, $langs, 1, -1, -1, $conf->currency);
if ($nb_missing > 0) {
	print ' &mdash; <span class="error">'.$langs->trans("MBIETransactionsRecapMissing", $nb_missing).'</span>';
}
print '</div>';

llxFooter();
$db->close();
