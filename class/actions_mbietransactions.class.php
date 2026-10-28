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

	// Rang d'exécution des hooks (défaut Dolibarr : 50 ; plus grand = exécuté plus tard).
	// 60 place le bloc eTransactions SOUS le bloc « Derniers règlements » de mmipayments,
	// dont il est le complément (échecs, paiements à recréer, 3x).
	public $priority = 60;

	/**
	 * Hook de l'onglet « Paiements » de mmipayments (contexte mmipaymentstab) :
	 * contribue les transactions eTransactions de l'objet affiché.
	 *
	 * Ce que le socle ne peut pas voir : llx_paiement ne contient que les paiements
	 * aboutis. Les REFUS, les échéanciers 3x et les motifs de rejet ne vivent que
	 * dans les tables du module. C'est tout l'objet de ce greffon.
	 *
	 * Deux cas de figure par transaction :
	 *  - elle a un paiement Dolibarr vivant  -> on ENRICHIT la ligne du socle
	 *    (fk_paiement), pour ne pas la faire apparaître deux fois ;
	 *  - échec, ou réussite dont le paiement a été supprimé -> ligne à part entière,
	 *    marquée comme non-encaissement, avec l'action de recréation le cas échéant.
	 */
	function addPaymentRows($parameters, &$object, &$action, $hookmanager)
	{
		global $langs, $user;

		if (!$this->in_context($parameters, 'mmipaymentstab'))
			return 0;

		$objecttype = !empty($parameters['objecttype']) ? $parameters['objecttype'] : '';
		$id = !empty($parameters['id']) ? (int) $parameters['id'] : 0;
		if (empty($objecttype) || empty($id))
			return 0;

		$langs->loadLangs(array('mbietransactions@mbietransactions', 'compta', 'bills', 'other'));

		// Le socle a résolu le cycle devis / commande / facture : on le suit, sinon
		// l'onglet d'un devis montrerait les règlements de la facture liée mais pas
		// les tentatives CB de cette même facture.
		$cycle = !empty($parameters['cycle']) ? $parameters['cycle'] : array(array('type' => $objecttype, 'id' => $id));

		$transactions = array();
		$seen = array();
		foreach ($cycle as $o) {
			foreach (mmi_etransactions::object_transactions($o['type'], $o['id']) as $t) {
				if (isset($seen[$t->return_id]))
					continue;	// une transaction remonte par chaque document du cycle
				$seen[$t->return_id] = true;
				$transactions[] = $t;
			}
		}
		if (empty($transactions))
			return 0;

		$can_recreate = $user->hasRight('facture', 'creer') || $user->hasRight('commande', 'creer');
		$rows = array();

		foreach ($transactions as $t) {
			$is_ok = ($t->erreur === '00000');
			$is_multiple = (!empty($t->cb_multiple) && (int) $t->cb_multiple > 1);
			$mode = $langs->trans('MBIETransactionsPaymentMeans')
				.($is_multiple ? ' <span class="badge badge-status4 badge-status" title="'.dol_escape_htmltag($langs->trans('MBIETransactionsPaymentMultiple')).'">'.((int) $t->cb_multiple).'X</span>' : '');

			// Transaction aboutie et paiement toujours présent : le socle l'a déjà
			// listée comme règlement — on ne fait que la préciser.
			if ($is_ok && !empty($t->paiement_id)) {
				$rows[] = array(
					'fk_paiement' => (int) $t->paiement_id,
					'mode'        => $mode,
					'status'      => '<span class="badge badge-status4 badge-status">'.$langs->trans('MBIETransactionsStatusOk').'</span>',
				);
				continue;
			}

			$status = $is_ok
				? '<span class="badge badge-status1 badge-status" title="'.dol_escape_htmltag($langs->trans('MBIETransactionsPaymentMissing')).'">'
					.img_warning('').' '.$langs->trans('MBIETransactionsStatusOk').'</span>'
				: '<span class="badge badge-status8 badge-status" title="'.dol_escape_htmltag(mmi_etransactions::error($t->erreur)).'">'
					.$langs->trans('MBIETransactionsStatusKo').'</span>';

			// Réussite dont le paiement Dolibarr a disparu : c'est le cas que l'action
			// de recréation existe pour traiter.
			$actions = '';
			if ($is_ok && $can_recreate) {
				$actions = '<form method="POST" action="'.$_SERVER['PHP_SELF'].'?objecttype='.urlencode($objecttype).'&id='.$id.'" style="display:inline;"'
					.' onsubmit="return confirm(\''.dol_escape_js($langs->trans('MBIETransactionsConfirmRecreate')).'\');">'
					.'<input type="hidden" name="token" value="'.newToken().'">'
					.'<input type="hidden" name="action" value="mbietransactions_recreate">'
					.'<input type="hidden" name="return_id" value="'.((int) $t->return_id).'">'
					.'<button type="submit" class="button smallpaddingimp">'.$langs->trans('MBIETransactionsRecreatePayment').'</button>'
					.'</form>';
			}

			$rows[] = array(
				'date'       => $this->db->jdate($t->return_date),
				'amount'     => $t->mt / 100,
				'ref'        => dol_escape_htmltag($langs->trans('MBIETransactionsTransRef').' '.$t->trans),
				'objects'    => $this->render_transaction_object($t),
				'mode'       => $mode,
				'status'     => $status,
				'actions'    => $actions,
				'is_payment' => false,
			);
		}

		$this->results = array('payment_rows' => $rows);

		return 0;
	}

	/**
	 * Hook de la liste globale de mmipayments (contexte mmipaymentslist) : contribue
	 * les tentatives de paiement CB, tous tiers confondus, selon le statut demandé.
	 *
	 * Le socle n'a rien à mettre dans cette vue — llx_paiement ne contient que les
	 * encaissements aboutis, donc ni les refus, ni les transactions dont le paiement
	 * a disparu. Nous sommes seuls à savoir.
	 *
	 * La pagination est faite en SQL et rendue au socle via `payment_total` : la vue
	 * porte plusieurs milliers de lignes, en charger l'intégralité pour n'en afficher
	 * qu'une page ne tiendrait pas.
	 */
	function addGlobalPaymentRows($parameters, &$object, &$action, $hookmanager)
	{
		global $langs;

		if (!$this->in_context($parameters, 'mmipaymentslist'))
			return 0;

		$filters = !empty($parameters['filters']) ? $parameters['filters'] : array();
		if (empty($filters['mode']) || $filters['mode'] !== 'attempts')
			return 0;	// l'autre mode ne nous concerne pas

		// Compat : socle au contrat 1.4, qui ne connaît que le drapeau missing_payment.
		$status = !empty($filters['status'])
			? $filters['status']
			: (!empty($filters['missing_payment']) ? 'missing' : 'unsettled');

		$langs->loadLangs(array('mbietransactions@mbietransactions', 'compta', 'bills', 'other'));

		$res = mmi_etransactions::global_transactions(array(
			'status'   => $status,
			'socid'    => !empty($filters['socid']) ? $filters['socid'] : 0,
			'datefrom' => !empty($filters['datefrom']) ? $filters['datefrom'] : '',
			'dateto'   => !empty($filters['dateto']) ? $filters['dateto'] : '',
			'limit'    => !empty($filters['limit']) ? $filters['limit'] : 0,
			'offset'   => !empty($filters['offset']) ? $filters['offset'] : 0,
		));
		if (empty($res['total']))
			return 0;

		// Une page vide au-delà de la fin n'est pas une absence de résultat : on rend
		// le total quand même, l'appelant en a besoin pour ramener à la page 1.
		$rows = array();
		foreach ($res['rows'] as $t) {
			$is_ok = ($t->erreur === '00000');
			$has_payment = !empty($t->fk_paiement) && !empty($t->paiement_ref);
			$is_multiple = (!empty($t->cb_multiple) && (int) $t->cb_multiple > 1);

			$soc = '';
			if (!empty($t->socid)) {
				$soc = '<a href="'.DOL_URL_ROOT.'/societe/card.php?socid='.$t->socid.'">'
					.dol_escape_htmltag($t->soc_name ? $t->soc_name : '#'.$t->socid).'</a>';
			}

			if (!$is_ok) {
				$status_html = '<span class="badge badge-status8 badge-status" title="'.dol_escape_htmltag(mmi_etransactions::error($t->erreur)).'">'
					.$langs->trans('MBIETransactionsStatusKo').'</span>';
			} elseif (!$has_payment) {
				// Le cas que la vue « Paiements à recréer » existe pour traiter : la
				// régularisation se fait depuis l'onglet Paiements du document.
				$status_html = '<span class="badge badge-status1 badge-status" title="'.dol_escape_htmltag($langs->trans('MBIETransactionsPaymentMissing')).'">'
					.img_warning('').' '.$langs->trans('MBIETransactionsStatusOk').'</span>';
			} else {
				$status_html = '<span class="badge badge-status4 badge-status">'.$langs->trans('MBIETransactionsStatusOk').'</span>'
					.' <a href="'.DOL_URL_ROOT.'/compta/paiement/card.php?id='.((int) $t->fk_paiement).'">'
					.img_picto('', 'payment', 'class="paddingleft"').dol_escape_htmltag($t->paiement_ref).'</a>';
			}

			$rows[] = array(
				'date'       => $this->db->jdate($t->return_date),
				'amount'     => $t->mt / 100,
				'ref'        => dol_escape_htmltag($langs->trans('MBIETransactionsTransRef').' '.$t->trans),
				'thirdparty' => $soc,
				'objects'    => $this->render_transaction_object($t),
				'mode'       => $langs->trans('MBIETransactionsPaymentMeans')
					.($is_multiple ? ' <span class="badge badge-status4 badge-status">'.((int) $t->cb_multiple).'X</span>' : ''),
				'status'     => $status_html,
				'actions'    => '',
				'is_payment' => false,
			);
		}

		$this->results = array(
			'payment_rows'  => $rows,
			'payment_total' => (int) $res['total'],	// total AVANT pagination : le socle n'a rien à retrancher
		);

		return 0;
	}

	/**
	 * Traite l'action de recréation déclenchée depuis l'onglet « Paiements »
	 * (hook doPaymentTabAction, contexte mmipaymentstab).
	 *
	 * @return int 1 si l'action a été traitée (la page redirige alors en GET), 0 sinon
	 */
	function doPaymentTabAction($parameters, &$object, &$action, $hookmanager)
	{
		global $langs, $user;

		if (!$this->in_context($parameters, 'mmipaymentstab'))
			return 0;
		if ($action !== 'mbietransactions_recreate')
			return 0;

		$langs->loadLangs(array('mbietransactions@mbietransactions'));

		if (!$user->hasRight('facture', 'creer') && !$user->hasRight('commande', 'creer')) {
			accessforbidden('Droit de création de paiement requis');
		}
		// Le contrôle du jeton CSRF est assuré automatiquement par main.inc.php (POST + token)

		$return_id = GETPOSTINT('return_id');
		if (empty($return_id))
			return 0;

		$res = mmi_etransactions::recreate_payment($return_id);
		if (!empty($res['success'])) {
			setEventMessages($langs->trans('MBIETransactionsPaymentRecreated', $res['paiement_id']), null, 'mesgs');
		} else {
			setEventMessages(!empty($res['error']) ? $res['error'] : $langs->trans('MBIETransactionsPaymentRecreateError'), null, 'errors');
		}

		return 1;
	}

	/**
	 * Lien vers l'objet métier porteur d'une transaction.
	 */
	protected function render_transaction_object($t)
	{
		$url = '';
		if ($t->objecttype == 'Facture')
			$url = DOL_URL_ROOT.'/compta/facture/card.php?id='.$t->fk_object;
		elseif ($t->objecttype == 'Commande')
			$url = DOL_URL_ROOT.'/commande/card.php?id='.$t->fk_object;
		elseif ($t->objecttype == 'Propal')
			$url = DOL_URL_ROOT.'/comm/propal/card.php?id='.$t->fk_object;
		$label = dol_escape_htmltag($t->object_ref ? $t->object_ref : $t->objecttype.' #'.$t->fk_object);
		return $url ? '<a href="'.$url.'">'.$label.'</a>' : $label;
	}

	/**
	 * Hook onglet Client (comm/card.php, contexte thirdpartycomm) :
	 * bloc teaser de l'historique eTransactions du tiers (réussites et échecs),
	 * avec alerte « paiements à recréer » et lien vers la page dédiée complète
	 * (societe_etransactions.php) qui porte l'action de recréation.
	 */
	function addMoreRecentObjects($parameters, &$object, &$action, $hookmanager)
	{
		global $langs, $conf, $user;

		if (!$this->in_context($parameters, 'thirdpartycomm'))
			return 0;
		if (empty($object->id) || !$user->hasRight('societe', 'lire'))
			return 0;

		$langs->loadLangs(array('mbietransactions@mbietransactions', 'compta', 'bills', 'other'));

		$transactions = mmi_etransactions::client_transactions($object->id);
		// Tiers sans transaction eTransactions : on n'affiche rien
		if (empty($transactions))
			return 0;

		$nb_missing = 0;
		foreach ($transactions as $t) {
			if ($t->erreur === '00000' && empty($t->paiement_id))
				$nb_missing++;
		}

		// L'historique complet vit désormais dans l'onglet « Paiements » de mmipayments,
		// qui agrège règlements et tentatives de tous les moyens de paiement (RM2803).
		$pageurl = dol_buildpath('/mmipayments/payments.php', 1).'?objecttype=Client&id='.$object->id;
		$max = 5;

		$out = '';
		$out .= '<div class="div-table-responsive-no-min">';
		$out .= '<table class="noborder centpercent">';

		$out .= '<tr class="liste_titre">';
		$out .= '<td colspan="4">'.$langs->trans('MBIETransactionsRecentTitle');
		$out .= ' <a href="'.$pageurl.'" class="marginleftonly">'.$langs->trans('MBIETransactionsSeeAll').'</a>';
		if ($nb_missing > 0) {
			$out .= ' <a href="'.$pageurl.'" class="badge badge-status8 badge-status marginleftonly" title="'.dol_escape_htmltag($langs->trans('MBIETransactionsRecapMissing', $nb_missing)).'">'.img_warning('').' '.$langs->trans('MBIETransactionsRecapMissing', $nb_missing).'</a>';
		}
		$out .= '</td>';
		$out .= '</tr>';

		$out .= '<tr class="liste_titre">';
		$out .= '<th>'.$langs->trans('Date').'</th>';
		$out .= '<th>'.$langs->trans('LinkedObject').'</th>';
		$out .= '<th class="center">'.$langs->trans('Status').'</th>';
		$out .= '<th class="right">'.$langs->trans('Amount').'</th>';
		$out .= '</tr>';

		$i = 0;
		foreach ($transactions as $t) {
			if ($i++ >= $max)
				break;
			$is_ok = ($t->erreur === '00000');
			$is_missing = ($is_ok && empty($t->paiement_id));
			$is_multiple = (!empty($t->cb_multiple) && (int) $t->cb_multiple > 1);
			// « Réussite normale » (succès, paiement présent, non multi-échéances) :
			// déjà visible dans le bloc « Derniers règlements » -> on l'atténue ici
			// pour mettre en avant ce qui est propre à eTransactions (échecs, à recréer, 3x).
			$is_normal = ($is_ok && !$is_missing && !$is_multiple);

			$url = '';
			if ($t->objecttype == 'Facture')
				$url = DOL_URL_ROOT.'/compta/facture/card.php?id='.$t->fk_object;
			elseif ($t->objecttype == 'Commande')
				$url = DOL_URL_ROOT.'/commande/card.php?id='.$t->fk_object;
			elseif ($t->objecttype == 'Propal')
				$url = DOL_URL_ROOT.'/comm/propal/card.php?id='.$t->fk_object;
			$label = dol_escape_htmltag($t->object_ref ? $t->object_ref : $t->objecttype.' #'.$t->fk_object);
			if ($is_multiple)
				$label .= ' <span class="badge badge-status4 badge-status" title="'.dol_escape_htmltag($langs->trans('MBIETransactionsPaymentMultiple')).'">'.((int) $t->cb_multiple).'X</span>';

			$out .= '<tr class="oddeven'.($is_normal ? ' opacitymedium' : '').'">';
			$out .= '<td class="nowraponall">'.dol_print_date($this->db->jdate($t->return_date), 'day').'</td>';
			$out .= '<td>'.($url ? '<a href="'.$url.'">'.$label.'</a>' : $label).'</td>';
			$out .= '<td class="center">';
			if ($is_ok) {
				if (empty($t->paiement_id))
					$out .= '<span class="badge badge-status1 badge-status" title="'.dol_escape_htmltag($langs->trans('MBIETransactionsPaymentMissing')).'">'.img_warning('').' '.$langs->trans('MBIETransactionsStatusOk').'</span>';
				else
					$out .= '<a href="'.DOL_URL_ROOT.'/compta/paiement/card.php?id='.$t->paiement_id.'"><span class="badge badge-status4 badge-status">'.$langs->trans('MBIETransactionsStatusOk').'</span></a>';
			} else {
				$out .= '<span class="badge badge-status8 badge-status" title="'.dol_escape_htmltag(mmi_etransactions::error($t->erreur)).'">'.$langs->trans('MBIETransactionsStatusKo').'</span>';
			}
			$out .= '</td>';
			$out .= '<td class="right nowraponall">'.price($t->mt / 100, 0, $langs, 1, -1, -1, $conf->currency).'</td>';
			$out .= '</tr>';
		}

		$out .= '</table>';
		$out .= '</div>';

		$this->resprints = $out;
		return 0;
	}

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

		// Paylent means activated
		$mean_paypal = getDolGlobalInt('MBIETRANSACTIONS_MEANS_PAYPAL');
		$mean_amex = getDolGlobalInt('MBIETRANSACTIONS_MEANS_AMEX');

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
			print '<span class="buttonpaymentsmall">'
			.'<img src="/custom/mbietransactions/img/cb-visa-mastercard.png" alt="CB Visa Mastercard" class="img_cb" />'
			.($mean_paypal ?'<img src="/custom/mbietransactions/img/paypal.png" alt="Paypal" class="img_paypal" />' :'')
			.($mean_amex ?'<img src="/custom/mbietransactions/img/amex.png" alt="Amex" class="img_amex" />' :'')
			.'</span>';
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
			print '<span class="buttonpaymentsmall">'
			.'<img src="/custom/mbietransactions/img/cb-visa-mastercard.png" alt="CB Visa Mastercard" class="img_cb" />'
			.($mean_paypal ?'<img src="/custom/mbietransactions/img/paypal.png" alt="Paypal" class="img_paypal" />' :'')
			.($mean_amex ?'<img src="/custom/mbietransactions/img/amex.png" alt="Amex" class="img_amex" />' :'')
			.'</span>';
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
			print '<span class="buttonpaymentsmall">'
			.'<img src="/custom/mbietransactions/img/cb-visa-mastercard.png" alt="CB Visa Mastercard" class="img_cb" />'
			.($mean_amex ?'<img src="/custom/mbietransactions/img/amex.png" alt="Amex" class="img_amex" />' :'')
			.'</span>';
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
			print '<span class="buttonpaymentsmall">'
			.'<img src="/custom/mbietransactions/img/cb-visa-mastercard.png" alt="CB Visa Mastercard" class="img_cb" />'
			.($mean_amex ?'<img src="/custom/mbietransactions/img/amex.png" alt="Amex" class="img_amex" />' :'')
			.'</span>';
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
