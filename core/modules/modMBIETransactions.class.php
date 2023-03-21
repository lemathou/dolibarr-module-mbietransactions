<?php
/**
 * Copyright (C) 2004-2018  Laurent Destailleur     <eldy@users.sourceforge.net>
 * Copyright (C) 2018-2019  Nicolas ZABOURI         <info@inovea-conseil.com>
 * Copyright (C) 2019-2020  Frédéric France         <frederic.france@netlogic.fr>
 * Copyright (C) 2020      	MB Informatique      	<info@mb-informatique.fr>
 * Copyright (C) 2022       Mathieu Moulin          <contact@iprospective.fr>
 */

/**
 * 	\defgroup   mbietransactions     Module MBIETransactions
 *  \brief      MBIETransactions module descriptor.
 *
 *  \file       htdocs/mbietransactions/core/modules/modMBIETransactions.class.php
 *  \ingroup    mbietransactions
 *  \brief      Description and activation file for module MBIETransactions
 */
include_once DOL_DOCUMENT_ROOT.'/core/modules/DolibarrModules.class.php';

/**
 *  Description and activation class for module MBIETransactions
 */
class modMBIETransactions extends DolibarrModules
{
	/**
	 * Constructor. Define names, constants, directories, boxes, permissions
	 *
	 * @param DoliDB $db Database handler
	 */
	public function __construct($db)
	{
		global $conf;
		$this->db = $db;
		$this->numero = 172370;
		$this->rights_class = 'mbietransactions';
		$this->family = "interface";
		$this->module_position = '90';
		$this->name = preg_replace('/^mod/i', '', get_class($this));
		$this->description = "MBIETransactionsDescription";
		$this->descriptionlong = "MBIETransactions description (Long)";
		$this->editor_name = 'Mathieu Moulin iProspective';
		$this->editor_url = 'https://www.iprospective.fr';
		$this->version = '1.0.1';

		$this->const_name = 'MAIN_MODULE_'.strtoupper($this->name);
		$this->picto = 'logo@mbietransactions';
		$this->module_parts = array(
			'triggers' => 1,
			'login' => 0,
			'substitutions' => 0,
			'menus' => 0,
			'tpl' => 0,
			'barcode' => 0,
			'models' => 0,
			'theme' => 0,
			'css' => array(),
			'js' => array(),
			'hooks' => array('invoicecard', 'ordercard', 'propalcard', 'newpayment'),
			'moduleforexternal' => 0,
		);
		$this->dirs = array("/mbietransactions/temp");
		$this->config_page_url = array("setup.php@mbietransactions");
		$this->hidden = false;
		$this->depends = array('modMMICommon', 'modMMIPayments');
		$this->requiredby = array();
		$this->conflictwith = array();
		$this->langfiles = array("mbietransactions@mbietransactions");
		$this->phpmin = array(5, 5);
		$this->need_dolibarr_version = array(8, 0);
		$this->warnings_activation = array();
		$this->warnings_activation_ext = array();
		$this->const = array();

		if (!isset($conf->mbietransactions) || !isset($conf->mbietransactions->enabled)) {
			$conf->mbietransactions = new stdClass();
			$conf->mbietransactions->enabled = 0;
		}

		$this->tabs = array();
		$this->dictionaries = array();
		$this->boxes = array();
		$this->cronjobs = array();

		// Permissions provided by this module
		$this->rights = array();
		$r = 0;
		// Add here entries to declare new permissions
		/* BEGIN MODULEBUILDER PERMISSIONS */
		/*
		$this->rights[$r][0] = $this->numero + $r; // Permission id (must not be already used)
		$this->rights[$r][1] = 'Read objects of MBIETransactions'; // Permission label
		$this->rights[$r][4] = 'myobject'; // In php code, permission will be checked by test if ($user->rights->mbietransactions->level1->level2)
		$this->rights[$r][5] = 'read'; // In php code, permission will be checked by test if ($user->rights->mbietransactions->level1->level2)
		$r++;
		$this->rights[$r][0] = $this->numero + $r; // Permission id (must not be already used)
		$this->rights[$r][1] = 'Create/Update objects of MBIETransactions'; // Permission label
		$this->rights[$r][4] = 'myobject'; // In php code, permission will be checked by test if ($user->rights->mbietransactions->level1->level2)
		$this->rights[$r][5] = 'write'; // In php code, permission will be checked by test if ($user->rights->mbietransactions->level1->level2)
		$r++;
		$this->rights[$r][0] = $this->numero + $r; // Permission id (must not be already used)
		$this->rights[$r][1] = 'Delete objects of MBIETransactions'; // Permission label
		$this->rights[$r][4] = 'myobject'; // In php code, permission will be checked by test if ($user->rights->mbietransactions->level1->level2)
		$this->rights[$r][5] = 'delete'; // In php code, permission will be checked by test if ($user->rights->mbietransactions->level1->level2)
		$r++;
		*/
		/* END MODULEBUILDER PERMISSIONS */

		$this->menu = array();
	}

	/**
	 *  Function called when module is enabled.
	 *  The init function add constants, boxes, permissions and menus (defined in constructor) into Dolibarr database.
	 *  It also creates data directories
	 *
	 *  @param      string  $options    Options when enabling module ('', 'noboxes')
	 *  @return     int             	1 if OK, 0 if KO
	 */
	public function init($options = '')
	{
		global $conf, $langs;

		$langs->loadLangs(array("mbietransactions@mbietransactions"));

		$result = $this->_load_tables('/mbietransactions/sql/');
		if ($result < 0) return -1; // Do not activate module if error 'not allowed' returned when loading module SQL queries (the _load_table run sql with run_sql with the error allowed parameter set to 'default')

		// Create extrafields during init
		include_once DOL_DOCUMENT_ROOT.'/core/class/extrafields.class.php';
		$extrafields = new ExtraFields($this->db);
		$extrafields->addExtraField('acompte', $langs->trans("MBIETransactionsAcompte"), 'double', 100, "4,2", 'propal', 0, 0, '', 0, true, '', -1, 0);
		$extrafields->addExtraField('acompte_val', $langs->trans("MBIETransactionsAcompteVal"), 'price', 100, "8,2", 'propal', 0, 0, '', 0, true, '', -1, 0);
		$extrafields->addExtraField('acompte', $langs->trans("MBIETransactionsAcompte"), 'double', 100, "4,2", 'commande', 0, 0, '', 0, true, '', -1, 0);
		$extrafields->addExtraField('acompte_val', $langs->trans("MBIETransactionsAcompteVal"), 'price', 100, "8,2", 'commande', 0, 0, '', 0, true, '', -1, 0);
		//$extrafields->addExtraField('mbi_payment_link', $langs->trans("MBIETransactionsPaymentLink"), 'text', 100, '2000', 'facture', 0, 0, '', '', 0, '', '1', '', '', $conf->entity, '', '1', 0, 0);
		//$extrafields->addExtraField('mbi_image_link', $langs->trans("MBIETransactionsTrustImage"), 'text', 100, '2000', 'facture', 0, 0, '', '', 0, '', '1', '', '', $conf->entity, '', '1', 0, 0);
		//$extrafields->addExtraField('mbi_payment_deposit', $langs->trans("MBIETransactionsAdvanceAmountTTC"), 'varchar', 100, '2000', 'facture', 0, 0, '', '', 1, '', '1', '', '', $conf->entity, '', '1', 0, 0);
		//$extrafields->addExtraField('mbi_payment_multiple', $langs->trans("MBIETransactionsPaymentMultiple"), 'select', 100, '', 'facture', 0, 0, '', 'a:1:{s:7:"options";a:2:{i:2;s:1:"2";i:3;s:1:"3";}}', 1, '', '1', '', '', $conf->entity, '', '1', 0, 0);

		// Permissions
		$this->remove($options);

		$sql = array(
			"INSERT IGNORE INTO " . MAIN_DB_PREFIX . "c_paiement (entity, code, libelle, type, active, position) VALUES (" . $conf->entity . ", 'CBI', 'Carte internet', 2, 1, 0)",
			//"INSERT IGNORE INTO " . MAIN_DB_PREFIX . "const (name, value) VALUES ('MBIETRANSACTIONS_TEMPLATE_PAYMENT_MAIL', '" . $langs->trans("MBIETRANSACTIONS_TEMPLATE_PAYMENT_MAIL_DEFAULT") . "')",
			//"INSERT IGNORE INTO " . MAIN_DB_PREFIX . "const (name, value) VALUES ('MBIETRANSACTIONS_TEMPLATE_PAID_INVOICE_MAIL', '" . $langs->trans("MBIETRANSACTIONS_TEMPLATE_PAID_INVOICE_MAIL_DEFAULT") . "')",
			"INSERT IGNORE INTO " . MAIN_DB_PREFIX . "const (name, value) VALUES ('MBIETRANSACTIONS_TEST', '1')",
			//"INSERT IGNORE INTO " . MAIN_DB_PREFIX . "const (name, value) VALUES ('MBIETRANSACTIONS_SUBJECT_PAYMENT_MAIL', '" . $langs->trans("MBIETransactionsMailTitlePaymentLink") . "')",
			//"INSERT IGNORE INTO " . MAIN_DB_PREFIX . "const (name, value) VALUES ('MBIETRANSACTIONS_SUBJECT_PAID_INVOICE_MAIL', '" . $langs->trans("MBIETransactionsMailTitlePaidInvoice") . "')"
		);

		return $this->_init($sql, $options);
	}

	/**
	 *  Function called when module is disabled.
	 *  Remove from database constants, boxes and permissions from Dolibarr database.
	 *  Data directories are not deleted
	 *
	 *  @param      string	$options    Options when enabling module ('', 'noboxes')
	 *  @return     int                 1 if OK, 0 if KO
	 */
	public function remove($options = '')
	{
		$sql = array();
		return $this->_remove($sql, $options);
	}
}
