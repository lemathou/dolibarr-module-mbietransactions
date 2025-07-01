<?php
/**
 * Copyright (C) 2020       MB Informatique         <info@mb-informatique.fr>
 * Copyright (C) 2022       Mathieu Moulin          <contact@iprospective.fr>
 */

dol_include_once('mmicommon/class/mmi_actions.class.php');
dol_include_once('mbietransactions/class/mmi_etransactions.class.php');

class ActionsMBIETransactions extends MMI_Actions_1_0
{
	const MOD_NAME = 'mbietransactions';

	// Payment page

	/**
	 * Check Object OK
	 */
	function doCheckStatus($parameters, &$object, &$action, $hookmanager)
	{
		$this->doValidatePayment($parameters, $object, $action, $hookmanager);
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

		// Wrapper
		print '<div id="div_dopayment_mbietransactions">';
		// Logo
		print '<div style=""><img src="/custom/mbietransactions/img/ca-e-transactions-bis-400px.png" alt="E-Transactions Crédit Agricole" width="400px" /></div>';

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

			print '<div class="button buttonpayment" id="div_dopayment_mbietransactions_simple">
			<input class="" type="submit" id="dopayment_mbietransactions_simple" name="dopayment_mbietransactions" value="'.$langs->trans("MBIETransactionsDoPayment").'">';
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
		print '</div>';

		print '<script>
			$( document ).ready(function() {
				$("#div_dopayment_mbietransactions_simple").click(function(e){
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
			});
			</script>';

		return 0;
	}

	// Payment means
	function doValidatePayment($parameters, &$object, &$action, $hookmanager)
	{
		//var_dump($parameters); var_dump(get_class($object)); var_dump($action);
		$parameters['validpaymentmethod']['mbietransactions'] = true;

		return 0;
	}

	// Payment means
	function getValidPayment($parameters, &$object, &$action, $hookmanager)
	{
		global $conf;

		//var_dump($parameters); var_dump(get_class($object)); var_dump($action);
		$this->results['validpaymentmethod']['mbietransactions'] = true;

		return 0;
	}
}

ActionsMBIETransactions::__init();
