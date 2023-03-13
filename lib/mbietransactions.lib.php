<?php

// Copyright (C) 2020      MB Informatique      <info@mb-informatique.fr>

/**
 * \file    mbietransactions/lib/mbietransactions.lib.php
 * \ingroup mbietransactions
 * \brief   Library files with common functions for MBIETransactions
 */

/**
 * Prepare admin pages header
 *
 * @return array
 */
function mbietransactionsAdminPrepareHead()
{
	global $langs, $conf;

	$langs->load("mbietransactions@mbietransactions");

	$h = 0;
	$head = array();

	$head[$h][0] = dol_buildpath("/mbietransactions/admin/setup.php", 1);
	$head[$h][1] = $langs->trans("Settings");
	$head[$h][2] = 'settings';
	$h++;

	$head[$h][0] = dol_buildpath("/mbietransactions/admin/about.php", 1);
	$head[$h][1] = $langs->trans("About");
	$head[$h][2] = 'about';
	$h++;

	complete_head_from_modules($conf, $langs, null, $head, $h, 'mbietransactions');

	return $head;
}
