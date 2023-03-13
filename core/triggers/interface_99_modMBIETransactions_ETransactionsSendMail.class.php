<?php
/**
 * Copyright (C) 2020       MB Informatique         <info@mb-informatique.fr>
 * Copyright (C) 2022       MMI Mathieu Moulin      <contact@iprospective.fr>
 */

require_once DOL_DOCUMENT_ROOT.'/core/triggers/dolibarrtriggers.class.php';

/**
 *  Class of triggers for MyModule module
 */
class InterfaceEtransactionsSendMail extends DolibarrTriggers
{
	/**
	 * @var DoliDB Database handler
	 */
	protected $db;

	/**
	 * Constructor
	 *
	 * @param DoliDB $db Database handler
	 */
	public function __construct($db)
	{
		$this->db = $db;

		$this->name = preg_replace('/^Interface/i', '', get_class($this));
		$this->family = "demo";
		$this->description = "MBI ETransactions triggers";
		$this->version = 'development';
		$this->picto = 'mymodule@mymodule';
	}

	/**
	 * Trigger name
	 *
	 * @return string Name of trigger file
	 */
	public function getName()
	{
		return $this->name;
	}

	/**
	 * Trigger description
	 *
	 * @return string Description of trigger file
	 */
	public function getDesc()
	{
		return $this->description;
	}

	/**
	 * Function called when a Dolibarrr business event is done.
	 * All functions "runTrigger" are triggered if file
	 * is inside directory core/triggers
	 *
	 * @param string 		$action 	Event action code
	 * @param CommonObject 	$object 	Object
	 * @param User 			$user 		Object user
	 * @param Translate 	$langs 		Object langs
	 * @param Conf 			$conf 		Object conf
	 * @return int              		<0 if KO, 0 if no triggered ran, >0 if OK
	 */
	public function runTrigger($action, $object, User $user, Translate $langs, Conf $conf)
	{
		if (empty($conf->mbietransactions->enabled)) return 0;

		global $db;
		$langs->loadLangs(array("mbietransactions@mbietransactions"));
		$extrafields = $object->array_options;
		// Disabled by MMI
        if (false && ($action == "BILL_PAYED" || $action == "BILL_SENTBYMAIL") && $object->mode_reglement_code == "CBI") {
			require_once DOL_DOCUMENT_ROOT.'/core/class/CMailFile.class.php';
			require_once DOL_DOCUMENT_ROOT.'/compta/facture/class/facture.class.php';
            dol_syslog("Trigger 'MBIETransactions Send Mail' for action '$action' launched. id=".$object->id);
			$facture = new Facture($db);
			$facture->fetch($object->id);
			$facture->fetch_thirdparty();
			$totalttc = $facture->multicurrency_total_ttc;
			$dejaregle = $facture->getSommePaiement(($conf->multicurrency->enabled && $facture->multicurrency_tx != 1) ? 1 : 0);
			$resteapayer = price2num($totalttc - $dejaregle);

			// Infos du mail

			$outputlangs = $langs;

			$substit= getCommonSubstitutionArray($outputlangs, 0, NULL, $object);
			complete_substitutions_array($substit, $outputlangs, $object, array());

			$substit['{REF}'] = $object->ref; // DEPRECATED -> USE __REF__ INSTEAD
			$substit['{CLIENT}'] = $facture->thirdparty->name; // DEPRECATED -> USE __THIRDPARTY_NAME__ INSTEAD
			$substit['{URL}'] =  $extrafields['options_mbi_payment_link'];
			$substit['{IMG}'] = "<img src='" . $extrafields['options_mbi_image_link'] . "'>";

            if ($action == "BILL_PAYED") {
				$subject = $conf->global->MBIETRANSACTIONS_SUBJECT_PAID_INVOICE_MAIL;
				$message = $conf->global->MBIETRANSACTIONS_TEMPLATE_PAID_INVOICE_MAIL;
				$filepath = array(DOL_DATA_ROOT . "/facture/" . $facture->ref . "/" . $facture->ref . ".pdf");
				$mimetype = array("application/pdf");
				$filename = array($facture->ref . ".pdf");
			} else if ($action == "BILL_SENTBYMAIL") {
				$subject =  $conf->global->MBIETRANSACTIONS_SUBJECT_PAYMENT_MAIL;
				$message = $conf->global->MBIETRANSACTIONS_TEMPLATE_PAYMENT_MAIL;
				$filepath = array();
				$mimetype = array();
				$filename = array();
			}
			$sendto = $facture->thirdparty->email;
			$from = $conf->global->MAIN_INFO_SOCIETE_MAIL;
			$subject = make_substitutions($subject, $substit);
            $message = make_substitutions($message, $substit);
			$cc = !empty($conf->global->MBIETRANSACTIONS_CC_EMAILS) ? $conf->global->MBIETRANSACTIONS_CC_EMAILS : "";
			$deliveryreceipt = $conf->global->MBIETRANSACTIONS_DELIVERY_RECEIPT_EMAIL == "1" ? 1 : 0;

			// Génération du PDF

			$hidedetails = !empty($conf->global->MAIN_GENERATE_DOCUMENTS_HIDE_DETAILS) ? 1 : 0;
			$hidedesc = !empty($conf->global->MAIN_GENERATE_DOCUMENTS_HIDE_DESC) ? 1 : 0;
			$hideref = !empty($conf->global->MAIN_GENERATE_DOCUMENTS_HIDE_REF) ? 1 : 0;
			$facture->generateDocument("crabe", $langs, $hidedetails, $hidedesc, $hideref);

			// Envoi du mail

			$mailfile = new CMailFile($subject, $sendto, $from, $message, $filepath, $mimetype, $filename, $cc, "", $deliveryreceipt, 1);
			$mailfile->sendfile();
        }
		return 0;
	}
}
