<?php
/**
 * Copyright (C) 2020       MB Informatique         <info@mb-informatique.fr>
 * Copyright (C) 2022       Mathieu Moulin          <contact@iprospective.fr>
 */

dol_include_once('custom/mmicommon/class/mmi_actions.class.php');
dol_include_once('/mbietransactions/class/mmi_etransactions.class.php');

class ActionsMBIETransactions extends MMI_Actions_1_0
{
	const MOD_NAME = 'mbietransactions';

	// Payment page

	/**
	 * Check Object OK
	 */
	function doCheckStatus($parameters, &$object, &$action, $hookmanager)
	{
		$objecttype = get_class($object);

		if (in_array($objecttype, ['Propal'])) {
			// Vérif devis ok, pas relié commande, etc.
		}

		return 0;
	}

	/**
	 * Check Object OK
	 */
	function addOnlinePaymentMeans($parameters, &$object, &$action, $hookmanager)
	{
		$objecttype = get_class($object);

		if (in_array($objecttype, ['Propal', 'Commande', 'Facture'])) {
			$hookmanager->results['useonlinepayment'] = true;
		}

		return 0;
	}
	
	// Boutons moyens de paiement
	function doaddButton($parameters, &$object, &$action, $hookmanager)
	{
		global $db, $langs, $conf;

		// var_dump($object);
		// die();
		$time = time();

		$objecttype = get_class($object);
		$deja = mmi_payments::total_regle($objecttype, $object->id);
		//var_dump($deja);
		$reste = ($deja>0 ?max(0, round($object->total_ttc-$deja, 2)) :$object->total_ttc);
		//var_dump($object->fin_validite, $time, empty($object->fin_validite) || $object->fin_validite < $time);

		print '<div style="width: 800px;">';
		if ($objecttype=='Propal') {
			$fin_validite = $object->fin_validite ?$object->fin_validite+86400 :0;
			$ok = $fin_validite && $fin_validite > $time;
			// if (!empty($object->ref_client))
			// 	echo '<p><b>Référence du projet :</b> '.$object->ref_client.'</p>';
			if (empty($deja) && $fin_validite && $fin_validite < $time)
				echo '<p'.(!$ok ?' style="color: red;"' :'').'>Date de fin de validité de votre Devis : '.date('d/m/Y', $fin_validite).'</p>';
			if (!$ok) {
				$nok_message = '<b style="color: red;">Votre devis est échu, merci de contacter votre conseiller !</b>';
			}
		}
		else {
			$ok = true;
		}
		if ($ok) {
			echo '<p><input type="checkbox" id="cgv" name="cgv" value="1" /> '."J'ai lu les <a href=\"https://www.pisceen.com/fr/a/3-conditions-generales-de-vente\" target=\"_blank\">conditions générales de vente</a> et j'y adhère sans réserve.".'</p>';
		}
		elseif (!empty($nok_message)) {
			echo '<p>'.$nok_message.'</p>';
		}
		echo '</div>';
		//var_dump($object); die();
		echo '<div id="voile" class="voile"></div>';

		print '<div style="width: 400px;"><img src="/custom/mbietransactions/img/ca-e-transactions-bis-400px.png" alt="E-Transactions Crédit Agricole" width="400px" /></div>';

		// Acompte
		// Une seule fois => si déjà alors pas acompte
		if (empty($deja) && empty($parameters['amount']) && (!empty($object->array_options['options_acompte']) || !empty($object->array_options['options_acompte_val']))) {
			if ($acompte = $object->array_options['options_acompte'])
				$amount = round($reste*$acompte/100, 2);
			else
				$amount = round($object->array_options['options_acompte_val'], 2);
			$link_acompte = mmi_etransactions::paymentlink($objecttype, $object->id, $amount, 1, true);
			//var_dump($link); die();

			print '<div class="button buttonpayment" id="div_dopayment_mbietransactions_acompte">
			<input class="" type="submit" id="dopayment_mbietransactions_acompte" name="dopayment_mbietransactions" value="'.$langs->trans("MBIETransactionsDoPaymentAcompte", $amount.'€').'">';
			print '<br />';
			print '<span class="buttonpaymentsmall">
			<img src="/custom/mbietransactions/img/cb-visa-mastercard.png" alt="CB Visa Mastercard" class="img_cb" />
			<img src="/custom/mbietransactions/img/paypal.png" alt="Paypal" class="img_paypal" />
			<img src="/custom/mbietransactions/img/amex.png" alt="Amex" class="img_amex" />
			</span>';
			print '</div>';
		}

		// Paiement normal complet
		if (true) {
			$amount = (!empty($parameters['amount']) ?$parameters['amount'] :$reste);
			//var_dump(get_class($object), $object->id, $amount, 1, true);
			$link = mmi_etransactions::paymentlink($objecttype, $object->id, $amount, 1, true);
			//var_dump($link); die();

			print '<div class="button buttonpayment" id="div_dopayment_mbietransactions">
			<input class="" type="submit" id="dopayment_mbietransactions" name="dopayment_mbietransactions" value="'.$langs->trans("MBIETransactionsDoPayment").'">';
			print '<br />';
			print '<span class="buttonpaymentsmall">
			<img src="/custom/mbietransactions/img/cb-visa-mastercard.png" alt="CB Visa Mastercard" class="img_cb" />
			<img src="/custom/mbietransactions/img/paypal.png" alt="Paypal" class="img_paypal" />
			<img src="/custom/mbietransactions/img/amex.png" alt="Amex" class="img_amex" />
			</span>';
			print '</div>';
		}

		// var_dump($parameters['amount']);
		// var_dump($object->array_options['options_acompte']);
		// var_dump($object->total_ttc);

		// Multiple Reste
		if (!empty($parameters['amount']) && (!empty($object->array_options['options_pay_solde_mult_ok'])) && ($reste>=300 || ($object->total_ttc>300 && $reste===NULL))) {
			$multiple = 3;
			$link_multiple = mmi_etransactions::paymentlink($objecttype, $object->id, $parameters['amount'], $multiple, true);
			print '<div class="button buttonpayment" id="div_dopayment_mbietransactions_multiple">
			<input class="" type="submit" id="dopayment_mbietransactions_multiple" name="dopayment_mbietransactions" value="'.$langs->trans("MBIETransactionsDoPaymentMultiple", $multiple).'">';
			print '<br />';
			print '<span class="buttonpaymentsmall">
			<img src="/custom/mbietransactions/img/cb-visa-mastercard.png" alt="CB Visa Mastercard" class="img_cb" />
			<img src="/custom/mbietransactions/img/amex.png" alt="Amex" class="img_amex" />
			</span>';
			print '</div>';
		}
		// Multiple
		if (empty($parameters['amount']) && ($reste>=300 || ($object->total_ttc>300 && $reste===NULL))) {
			$multiple = 3;
			$link_multiple = mmi_etransactions::paymentlink($objecttype, $object->id, $reste, $multiple, true);
			//var_dump($link); die();

			print '<div class="button buttonpayment" id="div_dopayment_mbietransactions_multiple">
			<input class="" type="submit" id="dopayment_mbietransactions_multiple" name="dopayment_mbietransactions" value="'.$langs->trans("MBIETransactionsDoPaymentMultiple", $multiple).'">';
			print '<br />';
			print '<span class="buttonpaymentsmall">
			<img src="/custom/mbietransactions/img/cb-visa-mastercard.png" alt="CB Visa Mastercard" class="img_cb" />
			<img src="/custom/mbietransactions/img/amex.png" alt="Amex" class="img_amex" />
			</span>';
			print '</div>';
		}

		// Virement
		if (true) {
			require_once DOL_DOCUMENT_ROOT.'/compta/bank/class/account.class.php';
			if($conf->global->PAYMENTBYBANKTRANSFER_ID_BANKACCOUNT) {
				$account = new account($db);
				$account->fetch($conf->global->PAYMENTBYBANKTRANSFER_ID_BANKACCOUNT);

				echo '<div class="button buttonpayment" id="div_dopayment_transfer">
				<input class="" type="submit" id="dopayment_transfer" name="dopayment_transfer" value="'.$langs->trans('MBIETransactionsDoPaymentTransfer').'" />';
				echo '<div class="pay_infos">';
				echo '<p>Il vous faudra transférer le montant de la facture sur notre compte bancaire.</p>
				<p>Vous recevrez votre confirmation de commande par e-mail, comprenant nos coordonnées bancaires et le numéro de commande.</p>
				<p>Nous traiterons votre commande dès la réception du paiement.</p>
				<p class="small">Cliquer pour plus d\'informations</p>';
				echo '</div>';
				echo '</div>';
			}
		}

		// Chèque
		if (true) {
			global $mysoc;
			//var_dump($mysoc);
			echo '<div class="button buttonpayment" id="div_dopayment_cheque">
			<input class="" type="submit" id="dopayment_cheque" name="dopayment_cheque" value="'.$langs->trans('MBIETransactionsDoPaymentCheque').'" />';
			echo '<div class="pay_infos">';
			echo '<p>A l\'ordre de : <b>'.$mysoc->name.'</b></p>';
			echo '<p>'.$mysoc->address.'<br />'.$mysoc->zip.' '.$mysoc->town.'</p>
			<p class="small">Cliquer pour plus d\'informations</p>';
			echo '</div>';
			echo '</div>';
		}

		print '<script>
			$( document ).ready(function() {
				$("#cgv").click(function(e){
					$("#voile").toggle();
				});
				$("#div_dopayment_mbietransactions").click(function(e){
					document.location.href=\''.$link.'\';
					$(this).css( \'cursor\', \'wait\' );
					e.stopPropagation();
					return false;
				});
				$("#div_dopayment_mbietransactions_acompte").click(function(e){
					document.location.href=\''.$link_acompte.'\';
					$(this).css( \'cursor\', \'wait\' );
					e.stopPropagation();
					return false;
				});
				$("#div_dopayment_mbietransactions_multiple").click(function(e){
					document.location.href=\''.$link_multiple.'\';
					$(this).css( \'cursor\', \'wait\' );
					e.stopPropagation();
					return false;
				});
				$("#div_dopayment_transfer input").click(function(e){
					if (confirm("Je confirme ma commande avec obligation de paiement.")) {
						$(this).css( \'cursor\', \'wait\' );
						$(\'input\', this).submit();
						return true;
					}
					else {
						return false;
					}
				});
				$("#div_dopayment_transfer p").click(function(e){
					$("#div_dopayment_transfer input").click();
				});
				$("#div_dopayment_cheque input").click(function(e){
					if (confirm("Je confirme ma commande avec obligation de paiement.")) {
						$(this).css( \'cursor\', \'wait\' );
						return true;
					}
					else {
						return false;
					}
				});
				$("#div_dopayment_cheque p").click(function(e){
					$("#div_dopayment_cheque input").click();
				});
			});
			</script>';

		return 0;
	}

	// Payment means
	function doValidatePayment($parameters, &$object, &$action, $hookmanager)
	{
		//var_dump($parameters); var_dump(get_class($object)); var_dump($action);
		$parameters['validpaymentmethod']['mbietransactions'] = true;
		$parameters['validpaymentmethod']['cheque'] = true;
		$parameters['validpaymentmethod']['transfer'] = true;

		return 0;
	}

	// This hook is used to show the embedded form to make payments with external payment modules (ie Payzen, ...)
	function doPayment($parameters, &$object, &$action, $hookmanager)
	{
		global $db, $conf, $mysoc, $user;
		//var_dump($mysoc); die();

		require_once DOL_DOCUMENT_ROOT.'/compta/bank/class/account.class.php';

		if(($client=$object->thirdparty) && $client->email) {
			$mail_notif_to = [];
			
			if (!empty($conf->global->MBIETRANSACTIONS_NOTIFICATION_EMAIL))
				$mail_notif_to[] = $conf->global->MBIETRANSACTIONS_NOTIFICATION_EMAIL;
			$contacts = $object->liste_contact(-1, 'internal');
			$contacts_ok = false;
			foreach($contacts as $contact) {
				$contacts_ok = true;
				if (!empty($contact->email) && !in_array($contact->email, $mail_notif_to))
					$mail_notif_to[] = $contact->email;
			}
			$contacts = $client->getSalesRepresentatives($user);
			foreach($contacts as $contact) {
				$contacts_ok = true;
				if (!empty($contact['email']) && !in_array($contact['email'], $mail_notif_to))
					$mail_notif_to[] = $contact['email'];
			}
			$to_email = $client->nom.' <'.$client->email.'>';
			$from_email = $mysoc->name.' <'.$mysoc->email.'>';
			$notif_email = (!empty($mail_notif_to) ?'Bcc: '.implode(',', $mail_notif_to)."\r\n" :'');
			// @todo : $mailfile = new CMailFile($subject, $sendto, $from, $message, $filepath, $mimetype, $filename, $sendtocc, $sendtobcc, $deliveryreceipt, -1, '', '', $trackid, '', $sendcontext);
		}

		$object_class = get_class($object);
		if ($object_class=='Propal')
			$otype = 'Devis';
		else
			$otype = $object_class;

		//var_dump($parameters);
		if ($parameters['paymentmethod']=='transfer' && $conf->global->PAYMENTBYBANKTRANSFER_ID_BANKACCOUNT) {
			mmi_etransactions::object_mode_reglement_set($object, 'VIR');

			$account = new account($db);
			$account->fetch($conf->global->PAYMENTBYBANKTRANSFER_ID_BANKACCOUNT);
			//var_dump($account);
			$info = '<h3 class="title">Vous avez choisi de payer par virement</h3>
			<p>Votre demande a bien été prise en considération.</p>
			<p>Merci de nous envoyer votre paiement par virement bancaire,</p>
			<p>Montant du règlement : '.$parameters['amount'].'&nbsp;&euro;</p>
			<p>Code Banque : '.$account->code_banque.'<br />
			Code Guichet :  '.$account->code_guichet.'<br />
			Numéro de compte : '.$account->number.'<br />
			Clé RIB : '.$account->cle_rib.'<br />
			IBAN : <b>'.$account->iban.'</b><br />
			Code BIC / SWIFT : <b>'.$account->bic.'</b></p>
			<p>Adresse de la banque / Domiciliation du compte :</p>
			<p>'.str_replace("\r\n", '<br />', $account->domiciliation).'</p>
			<p>N\'oubliez pas la référence de votre '.$otype.' dans la description du virement :<br /><b>'.$object->ref.'</b></p>'
			.($object->thirdparty && $object->thirdparty->email ?'<p>Un e-mail contenant ces informations a été envoyé sur votre adresse :<br /><b>'.$object->thirdparty->email.'</b></p>' :'')
			.'<p><b>Votre commande sera traitée dès réception de votre virement.</b></p>'
			.($conf->global->MBIETRANSACTIONS_WEBSITE_CONTACT_URL ?'<p>Pour toute question ou information complémentaire,<br />
			merci de contacter notre <a href="'.$conf->global->MBIETRANSACTIONS_WEBSITE_CONTACT_URL.'">support client</a>.</p>' :'');

			echo '<table align="center" width="600"><tr><td><div style="width: 559px;border: 1px solid #aaa;padding: 20px;">
			'.$info.'
			</div></td></tr></table>';
			if($object->thirdparty && $object->thirdparty->email) {
				mail($to_email,
					'=?utf-8?B?'.base64_encode('Votre '.$otype.' '.$object->ref.' en attente de réglement par virement bancaire').'?=',
					$info,
					"Content-Type: text/html; charset=\"UTF-8\";\r\nFrom: ".$from_email."\r\n".$notif_email);
			}
		}
		elseif ($parameters['paymentmethod']=='cheque') {
			mmi_etransactions::object_mode_reglement_set($object, 'CHQ');

			$info = '<h3 class="title">Vous avez choisi de payer par chèque</h3>
			<p>Votre demande a bien été prise en considération.</p>
			<p>Merci de nous envoyer votre paiement par chèque,</p>
			<p>- Montant du règlement : '.$parameters['amount'].'&nbsp;&euro;</p>
			<p>- Payable à l\'ordre de : <b>'.$mysoc->name.'</b>,</p>
			<p>- Envoyer à l\'adresse suivante :</p>
			<p style="margin-left: 40px;"><b>'.$mysoc->address.'<br />'.$mysoc->zip.' '.$mysoc->town.'</b></p>
			<p>- N\'oubliez pas la référence de votre '.$otype.' : <b>'.$object->ref.'</b></p>'
			.($object->thirdparty && $object->thirdparty->email ?'<p>Un e-mail contenant ces informations a été envoyé sur votre adresse : '.$object->thirdparty->email.'</p>' :'')
			.'<p><b>Votre commande sera traitée dès réception de votre chèque.</b></p>'
			.($conf->global->MBIETRANSACTIONS_WEBSITE_CONTACT_URL ?'<p>Pour toute question ou information complémentaire,<br />
			merci de contacter notre <a href="'.$conf->global->MBIETRANSACTIONS_WEBSITE_CONTACT_URL.'">support client</a>.</p>' :'');

			echo '<table align="center" width="600"><tr><td><div style="width: 559px;border: 1px solid #aaa;padding: 20px;">
			'.$info.'
			</div></td></tr></table>';
			if($object->thirdparty && $object->thirdparty->email) {
				mail($to_email,
					'=?utf-8?B?'.base64_encode('Votre '.$otype.' '.$object->ref.' en attente de réglement par chèque').'?=',
					$info, 
					"Content-Type: text/html; charset=\"UTF-8\";\r\nFrom: ".$from_email."\r\n".$notif_email);
			}
		}
		//die('YO');

		return 0;
	}
}

ActionsMBIETransactions::__init();
