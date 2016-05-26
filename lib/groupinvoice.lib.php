<?php
/* <one line to give the program's name and a brief idea of what it does.>
 * Copyright (C) <year>  <name of author>
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
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

/**
 *	\file		lib/groupinvoice.lib.php
 *	\ingroup	groupinvoice
 *	\brief		This file is an example module library
 *				Put some comments here
 */

/**
 * Prepare header for admin page
 *
 * @return array Page tabs
 */
function groupinvoiceAdminPrepareHead()
{
    global $langs, $conf;

    $langs->load("groupinvoice@groupinvoice");

    $h = 0;
    $head = array();

    $head[$h][0] = dol_buildpath("/groupinvoice/admin/admin_groupinvoice.php", 1);
    $head[$h][1] = $langs->trans("GroupInvoiceSetup");
    $head[$h][2] = 'settings';
    $h++;
    $head[$h][0] = dol_buildpath("/groupinvoice/admin/about.php", 1);
    $head[$h][1] = $langs->trans("About");
    $head[$h][2] = 'about';
    $h++;

    // Show more tabs from modules
    // Entries must be declared in modules descriptor with line
    //$this->tabs = array(
    //	'entity:+tabname:Title:@groupinvoice:/groupinvoice/mypage.php?id=__ID__'
    //); // to add new tab
    //$this->tabs = array(
    //	'entity:-tabname:Title:@groupinvoice:/groupinvoice/mypage.php?id=__ID__'
    //); // to remove a tab
    complete_head_from_modules($conf, $langs, $object, $head, $h, 'groupinvoice_admin');

    return $head;
}

/**
 * Prepare header for page
 *
 * @return array Page tabs
 */
function groupinvoice_prepare_head($object)
{
	global $langs, $conf;

	$langs->load("groupinvoice@groupinvoice");

	$h = 0;
	$head = array();
	
	$head[0][0] = dol_buildpath("/groupinvoice/groupinvoice.php", 1).'?id='.$object->id;
	$head[0][1] = $langs->trans('GroupInvoiceRecord');
	$head[0][2] = 'groupinvoice';
	$h++;

	$head[$h][0] = dol_buildpath("/groupinvoice/note.php", 1).'?id='.$object->id;
	$head[$h][1] = $langs->trans("Notes");
	$head[$h][2] = 'note';
	$h++;

	complete_head_from_modules($conf, $langs, $object, $head, $h, 'groupinvoice');

    return $head;
}

/**
 * List all invoices late and unpaid
 *
 * @param DoliDb $db Database connection object
 * @param Societe $company Company filter
 * @param string $monthperiod month invoice validation date filter
 * @param int $yearperiod year invoice validation date filter 
 * @return array List of unpaid invoices objects
 */
function getListOfInvoices($db, $company = null, $monthperiod=0, $yearperiod=0)
{
	global $conf;

	$list = array();

	$sql = 'SELECT
	rowid
	FROM ' . MAIN_DB_PREFIX . 'facture
	WHERE
	-- Multicompany support
	entity = ' . $conf->entity;
	if($company->id) {
		$sql .= '
			-- Filter by company
			AND fk_soc = ' . $company->id;
	}
	$sql .= '
	-- Standard, replacement and deposit invoices
	AND type IN (0, 1, 3)
	-- Openned or partially paid
	AND fk_statut IN (1, 2)
	-- Not fully paid
	AND paye <> 1
	-- Not forcibly closed
	AND close_note IS NULL ';
	
	if (!empty($yearperiod)) {
		$sql .= ' AND YEAR(date_valid)='.$yearperiod;
	}
	if (!empty($monthperiod)) {
		$sql .= ' AND MONTH(date_valid) IN ('.$monthperiod.')';
	}

	dol_syslog('groupinvoice.lib.php:: sql='.$sql);
	$resql = $db->query($sql);

	if($resql) {
		$i = 0;
		while($i < $db->num_rows($resql)) {
			$invoice = new Facture($db);
			$invoice->fetch($db->fetch_object($resql)->rowid);
			array_push($list, $invoice);
			$i++;
		}
	}

	return $list;
}

/**
 * Compute the rest from an invoice
 *
 * @param Facture $invoice The invoice to process
 * @return float
 */
function getRest($invoice)
{
	// TODO: add to upstream invoice object
	return $invoice->total_ttc -
		$invoice->getSommePaiement() -
		$invoice->getSumDepositsUsed() -
		$invoice->getSumCreditNotesUsed();
}

/**
 * Get List of oppened GroupInvoice
 *
 * @param DoliDb $db Database connection object
 * @param array $filter Array of filters to apply on request
 * @param string $sortfield sort field
 * @param string $sortorder sort order
 * @param int $limit page
 * @param int $offset
 * @return float
 */
function getListOfOpennedGroupInvoices($db,$filter=array(),$sortfield='',$sortorder='',$limit=0, $offset=0)
{
	// FIXME: only return openned groupinvoices
	global $conf;

	$list = array();

	$sql  = 'SELECT
	groupinvoice.rowid
	FROM ' . MAIN_DB_PREFIX . 'groupinvoice as groupinvoice
	LEFT OUTER JOIN ' . MAIN_DB_PREFIX . 'societe as societe ON groupinvoice.fk_company=societe.rowid
	LEFT OUTER JOIN ' . MAIN_DB_PREFIX . 'societe_commerciaux as salesman ON salesman.fk_soc=societe.rowid
	WHERE
	-- Multicompany support
	groupinvoice.entity = ' . $conf->entity  ;
	
	if (count($filter) > 0) {
		foreach ($filter as $key => $value) {
			if ($key=='id') {
				$sql .= ' AND groupinvoice.rowid = ' . $value;
			} elseif (strpos($key, 'date')!==false) {
				$sql .= ' AND ' . $key . ' = \'' .  $value . '\'';
			} else {
				$sql .= ' AND ' . $key . ' LIKE \'%' . $db->escape($value). '%\'';
			}
		}
	}
	if (!empty($sortfield)) {
		$sql.= " ORDER BY ".$sortfield." ".$sortorder;
	}
	if (! empty($limit)) {
		$sql .= ' ' . $db->plimit($limit + 1, $offset);
	}

	dol_syslog('groupinvoice.lib.php::getListOfOpennedGroupInvoices sql='.$sql);
	$resql = $db->query($sql);

	if ($resql) {
		$i = 0;
		while($i < $db->num_rows($resql)){
			array_push($list, $db->fetch_object($resql)->rowid);
			$i++;
		}
	}else {
		setEventMessage($db->lasterror(),'errors');
	}

	return $list;
}
