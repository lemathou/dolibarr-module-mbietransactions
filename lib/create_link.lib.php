<?php
/**
 * Copyright (C) 2020       MB Informatique         <info@mb-informatique.fr>
 * Copyright (C) 2022       Mathieu Moulin          <contact@iprospective.fr>
 */

// Usual functions

//dol_include_once('/mmipayments/class/mmi_payments.class.php');
dol_include_once('/mbietransactions/class/mmi_etransactions.class.php');

// @depreacated

function mmi_etransactions_active($objecttype=NULL)
{
	return mmi_etransactions::active($objecttype);
}

function mmi_etransactions_paymentlink($objecttype, $id, $amount=NULL, $multiple=NULL, $autosubmit=false)
{
	return mmi_etransactions::paymentlink($objecttype, $id, $amount, $multiple, $autosubmit);
}

function mmi_etransactions_query($hash)
{
	return mmi_etransactions::query($hash);
}

function mmi_etransactions_info($hash)
{
	return mmi_etransactions::query($hash);
}

function mmi_etransactions_trans_query($hash, $trans, $autorisation)
{
	return mmi_etransactions::trans_query($hash, $trans, $autorisation);
}

function mmi_etransactions_trans_info($hash, $trans, $autorisation)
{
	return mmi_etransactions::trans_query($hash, $trans, $autorisation);
}

function mmi_etransactions_retrieve($hash)
{
	return mmi_etransactions::retrieve($hash);
}

function mmi_etransactions_loadobject($objecttype, $id)
{
	return mmi_payments::loadobject($objecttype, $id);
}

function mmi_etransactions_createhash()
{
	return mmi_etransactions::createhash();
}
