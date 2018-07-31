<?php
/* GroupInvoice management
 * Copyright (C) 2014 Raphaël Doursenaud <rdoursenaud@gpcsolutions.fr>
 * Copyright (C) 2014 Florian HENRY <florian.henry@open-concept.pro>
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
 *    \file         create.php
 *    \ingroup      groupinvoice
 *    \brief        Creation page
 */


// Load environment
$res = 0;
if (!$res && file_exists("../main.inc.php")) {
	$res = @include("../main.inc.php");
}
if (!$res && file_exists("../../main.inc.php")) {
	$res = @include("../../main.inc.php");
}
if (!$res && file_exists("../../../main.inc.php")) {
	$res = @include("../../../main.inc.php");
}
if (!$res) {
	die("Main include failed");
}

global $db, $langs, $user;

// Access control
// Restrict access to users with invoice reading permissions
restrictedArea($user, 'facture');
if (
	$user->societe_id > 0 // External user
) {
	accessforbidden();
}

require_once 'class/groupinvoice.class.php';
require_once 'class/groupinvoiceinvoice.class.php';
require_once 'lib/groupinvoice.lib.php';
require_once 'core/modules/groupinvoice/modules_groupinvoice.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/date.lib.php';
require_once DOL_DOCUMENT_ROOT . '/core/class/html.formother.class.php';


// Load translation files required by the page
$langs->load('groupinvoice@groupinvoice');
$langs->load('bills');

// Get parameters
$id = GETPOST('id', 'int');
$action = GETPOST('action', 'alpha');
$company_id = GETPOST('company', 'int');
$search = GETPOST('button_search_x','int');
$search_month=GETPOST('search_month', 'alpha');
$search_year=GETPOST('search_year', 'int');

if (empty($search_month)) $search_month=dol_print_date(dol_now(),'%m');
if (empty($search_year)) $search_year=dol_print_date(dol_now(),'%Y');

// Objects
$groupinvoice = new GroupInvoice($db);
$company = new Societe($db);

// Load objects
if($id) {
	$groupinvoice->fetch($id);
}
if($company_id) {
	$company->fetch($company_id);
}

/*
 * ACTIONS
 */
if($action === 'create') {
	$invoices = GETPOST('invoices', 'array');

	$groupinvoice->dated = dol_mktime(0, 0, 0, GETPOST('dtgroupinvmonth','int'), GETPOST('dtgroupinvday','int'), GETPOST('dtgroupinvyear','int'));

	$groupinvoice->mode_creation='manual';
	$groupinvoice->model_pdf=$conf->global->GROUPINVOICE_ADDON_PDF;

	$obj = empty ( $conf->global->GROUPINVOICE_ADDON ) ? 'mod_groupinvoice_simple' : $conf->global->GROUPINVOICE_ADDON;
	$path_rel = dol_buildpath ( '/groupinvoice/core/modules/groupinvoice/' . $conf->global->GROUPINVOICE_ADDON . '.php' );
	if (! empty ( $conf->global->GROUPINVOICE_ADDON ) && is_readable ( $path_rel )) {
		dol_include_once ( '/groupinvoice/core/modules/groupinvoice/' . $conf->global->GROUPINVOICE_ADDON . '.php' );
		$modGroupInvoice= new $obj ();
		$defaultref = $modGroupInvoice->getNextValue ( $soc, $groupinvoice );
	}

	$groupinvoice->ref=$defaultref;

	$result = $groupinvoice->create($user, $company, $invoices);
	if ($result<O) {
		setEventMessage($groupinvoice->error,'errors');
	} else {


		//Create the PDF on groupinvoice creation
		$outputlangs = $langs;
		$newlang = '';
		if ($conf->global->MAIN_MULTILANGS && empty($newlang)) {
			$newlang = $company->default_lang;
		}
		if (!empty($newlang)) {
			$outputlangs = new Translate("", $conf);
			$outputlangs->setDefaultLang($newlang);
		}
		$result = groupinvoice_pdf_create($db, $groupinvoice, $groupinvoice->model_pdf, $outputlangs, $hidedetails, $hidedesc, $hideref);

		if ($result <= 0) {
			dol_print_error($db, $result);
			exit();
		}


		Header ('Location:'.dol_buildpath('/groupinvoice/groupinvoice.php?id='.$groupinvoice->id,1));
	}


}

/*
 * VIEW
 */
$form = new Form($db);
$formother = new FormOther ( $db );

$title = $langs->trans('NewGroupInvoice');

llxHeader('', $title);



echo '<form name="groupinvoice" action="', $_SERVER["PHP_SELF"], '" method="post">';


// We need a company to continue processing
if ($company_id <= 0) {

	print_fiche_titre($langs->trans('NewGroupInvoiceUnique'));
	echo '<table class="border allwidth">';
	echo '<tr>',
	'<td class="fieldrequired">',
	$langs->trans('Customer'),
	'</td>',
	'<td>',
	$form->select_thirdparty_list('', 'company', 's.client = 1 OR s.client = 3', 1),
	'</td>',
	'</tr>',
	'</table>';

	// Create button
	echo '<p class="center">',
	'<button type="submit" class="button" name="action" value="company">',
	$langs->trans('OK'),
	'</button>',
	'</p>';

	echo '</form>';

	// Page end
	llxFooter();
	$db->close();
	exit();
}

// Thirdparty
echo '<tr>',
'<td class="fieldrequired">',
$langs->trans('Customer'),
'</td>',
'<td>',
$company->getNomUrl(1),
'<input type="hidden" name="company" value="', $company_id, '">',
'</td>',
'</tr>';




// Date
echo '<tr>',
'<td class="fieldrequired">',
$langs->trans ( 'InvoiceValidPeriod' ) .': '
,$langs->trans ( 'Month' ) . ':<input class="flat" type="text" size="4" name="search_month" value="' . $search_month . '">'
,$langs->trans ( 'Year' ) . ':' . $formother->selectyear ( $search_year ? $search_year : - 1, 'search_year', 1, 20, 5 ),
'<input class="liste_titre" type="image" name="button_search" src="' . DOL_URL_ROOT . '/theme/' . $conf->theme . '/img/search.png" value="' . dol_escape_htmltag ( $langs->trans ( "Search" ) ) . '" title="' . dol_escape_htmltag ( $langs->trans ( "Search" ) ) . '">',
'</tr>';

echo '</table>';

// Unpaid invoices list
$list = getListOfInvoices($db, $company,$search_month,$search_year);
if ($list) {
	echo '<table class="liste">',
	'<tr class="liste_titre">',
	'<th>',
	$langs->trans('Use'),
	'</th>',
	'<th>',
	$langs->trans('Ref'),
	'</th>',
	'<th>',
	$langs->trans('Date'),
	'</th>',
	'<th>',
	$langs->trans('Late'), ' (', $langs->trans('days'), ')',
	'</th>',
	'<th class="right">',
	$langs->trans('Amount'),
	'</th>',
	'<th class="right">',
	$langs->trans('Rest'),
	'</th>',
	'</tr>';

	foreach ($list as $invoice) {
		echo '<tr>',
		'<td>',
		'<input name="invoices[]" value="', $invoice->id, '" type="checkbox" checked="checked">',
		'</td>',
		'<td>',
		$invoice->getNomUrl(1),
		'</td>',
		'<td>',
		dol_print_date($invoice->date, 'day'),
		'</td>',
		'<td>',
		num_between_day($invoice->date_lim_reglement, dol_now()),
		'</td>',
		'<td class="right">',
		price($invoice->total_ttc),
		'</td>',
		'<td class="right">',
		price(getRest($invoice), 1, $langs, 1, 2, 2),
		'</td>',
		'</tr>';
	}

	// TODO: add a total

	echo '</table>';

	// Create button
	echo '<p class="center">',
	$langs->trans('Date').' ',$form->select_date(dol_now(),'dtgroupinv',0,0,0,'',1,1),
	'<button type="submit" class="button" name="action" value="create">',
	$langs->trans('Create'),
	'</button>',
	'</p>';
} else {
	/*
	 * This customer don't have unpaid invoices
	 * Let's get him back to client selection
	 */
	echo '<p>',
	'<em>',
	$langs->trans('NoInvoice'),
	'</em>',
	'</p>',
	'<p class="center">',
	'<button type="submit" class="button" name="action" value="reset">',
	$langs->trans('OK'),
	'</button>',
	'</p>';
}

echo '</form>';

// Page end
llxFooter();
$db->close();
