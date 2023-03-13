<?php
/* Copyright (C) 2004-2017 Laurent Destailleur  <eldy@users.sourceforge.net>
 * Copyright (C) 2020      MB Informatique      <info@mb-informatique.fr>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * \file    mbietransactions/admin/setup.php
 * \ingroup mbietransactions
 * \brief   MBIETransactions setup page.
 */

// Load Dolibarr environment
require_once '../env.inc.php';
require_once '../main_load.inc.php';

$arrayofparameters = array(
	'MBIETRANSACTIONS_TEST'=>array('css'=>'minwidth500', 'enabled'=>1),
	'MBIETRANSACTIONS_TEST_RANK'=>array('css'=>'minwidth500', 'enabled'=>1),
	'MBIETRANSACTIONS_TEST_ID'=>array('css'=>'minwidth500', 'enabled'=>1),
	'MBIETRANSACTIONS_TEST_SHOP_ID'=>array('css'=>'minwidth500', 'enabled'=>1),
	'MBIETRANSACTIONS_TEST_KEY'=>array('css'=>'minwidth500', 'enabled'=>1),
	
	'MBIETRANSACTIONS_RANK'=>array('css'=>'minwidth500', 'enabled'=>1),
	'MBIETRANSACTIONS_ID'=>array('css'=>'minwidth500', 'enabled'=>1),
	'MBIETRANSACTIONS_SHOP_ID'=>array('css'=>'minwidth500', 'enabled'=>1),
	'MBIETRANSACTIONS_KEY'=>array('css'=>'minwidth500', 'enabled'=>1),
	
	'MBIETRANSACTIONS_DELIVERY_RECEIPT_EMAIL'=>array('css'=>'minwidth500', 'enabled'=>1),
	'MBIETRANSACTIONS_CC_EMAILS'=>array('css'=>'minwidth500', 'enabled'=>1),
	'MBIETRANSACTIONS_NOTIFICATION_FROM_EMAIL'=>array('css'=>'minwidth500', 'enabled'=>1),
	'MBIETRANSACTIONS_NOTIFICATION_EMAIL'=>array('css'=>'minwidth500', 'enabled'=>1),
	'MBIETRANSACTIONS_WEBSITE_CONTACT_URL'=>array('css'=>'minwidth500', 'enabled'=>1),
	'MBIETRANSACTIONS_BANK_ACCOUNT'=>array('css'=>'minwidth500', 'enabled'=>1),
	'MBIETRANSACTIONS_SUBJECT_PAYMENT_MAIL'=>array('css'=>'minwidth500', 'enabled'=>1),
	'MBIETRANSACTIONS_TEMPLATE_PAYMENT_MAIL'=>array('css'=>'minwidth500', 'enabled'=>1),
	'MBIETRANSACTIONS_SUBJECT_PAID_INVOICE_MAIL'=>array('css'=>'minwidth500', 'enabled'=>1),
	'MBIETRANSACTIONS_TEMPLATE_PAID_INVOICE_MAIL'=>array('css'=>'minwidth500', 'enabled'=>1)
);

require_once('../../mmicommon/admin/mmisetup_1.inc.php');
