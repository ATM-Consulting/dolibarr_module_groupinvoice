<?php
/* Copyright (C) 2004      Rodolphe Quiedeville <rodolphe@quiedeville.org>
 * Copyright (C) 2004-2012 Laurent Destailleur  <eldy@users.sourceforge.net>
 * Copyright (C) 2004      Eric Seigne          <eric.seigne@ryxeo.com>
 * Copyright (C) 2005-2012 Regis Houssin        <regis.houssin@capnetworks.com>
 * Copyright (C) 2014      Florian Henry		  	<florian.henry@open-concept.pro>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

/**
 *	\file		groupinvoice.php
 *	\ingroup	groupinvoice
 *	\brief		note page
 */

// Load environment
$res = 0;
if (! $res && file_exists("../main.inc.php")) {
	$res = @include("../main.inc.php");
}
if (! $res && file_exists("../../main.inc.php")) {
	$res = @include("../../main.inc.php");
}
if (! $res && file_exists("../../../main.inc.php")) {
	$res = @include("../../../main.inc.php");
}
if (! $res) {
	die("Main include failed");
}
require_once 'class/groupinvoice.class.php';
require_once 'lib/groupinvoice.lib.php';


$id = GETPOST('id','int');
$action=GETPOST('action','alpha');

// Access control
// Restrict access to users with invoice reading permissions
restrictedArea($user, 'groupinvoice');
if (
	$user->societe_id > 0 // External user
) {
	accessforbidden();
}


$groupinvoice = new GroupInvoice($db);

if($id) {
	$groupinvoice->fetch($id);
	$company = new Societe($db);
	$company->fetch($groupinvoice->fk_company);
} else {
	// FIXME: be nicer
	exit("Please provide a groupinvoice ID");
}


/******************************************************************************/
/*                     Actions                                                */
/******************************************************************************/

if ($action == 'setnote_public')
{
	$groupinvoice->fetch($id);
	$result=$groupinvoice->update_note(dol_html_entity_decode(GETPOST('note_public'), ENT_QUOTES),'_public');
	if ($result < 0) setEventMessage($object->error,'errors');
}

else if ($action == 'setnote_private')
{
	$groupinvoice->fetch($id);
	$result=$groupinvoice->update_note(dol_html_entity_decode(GETPOST('note_private'), ENT_QUOTES),'_private');
	if ($result < 0) setEventMessage($object->error,'errors');
}


/******************************************************************************/
/* Affichage fiche                                                            */
/******************************************************************************/

$title = $langs->trans('Module103086Name');

llxHeader('',$title);

$form = new Form($db);

$now=dol_now();


$head = groupinvoice_prepare_head($groupinvoice);
dol_fiche_head($head, 'note', $langs->trans('Module103086Name'), 0, 'groupinvoice');


echo '<table class="border allwidth">',
// Ref
'<tr>',
'<td class="table-key-border-col">',
$langs->trans('Ref'),
'</td>',
'<td class="table-val-border-col">',
$groupinvoice->ref,
'</td>',
'</tr>',
// Date
'<tr>',
'<td class="table-key-border-col">',
$langs->trans('Date'),
'</td>',
'<td class="table-val-border-col">',
dol_print_date($groupinvoice->dated, 'day'),
'</td>',
'</tr>',
// Thirdparty
'<tr>',
'<td class="table-key-border-col">',
$langs->trans('Customer'),
'</td>',
'<td class="table-val-border-col">',
$company->getNomUrl(1, 'customer'),
'</td>',
'</tr>',
// Amount
'<tr>',
'<td class="table-key-border-col">',
$langs->trans('Amount'),
'</td>',
'<td class="table-val-border-col right">',
price($groupinvoice->amount, 0, $langs, 1, -1, -1, $conf->currency),
'</td>',
'</tr>',
// Rest
'<tr>',
'<td class="table-key-border-col">',
$langs->trans('Rest'),
'</td>',
'<td class="table-val-border-col right">',
'<b>',
price($groupinvoice->getRest(), 0, $langs, 1, -1, -1, $conf->currency),
'</b>',
'</td>',
'</tr>',
'</table>';

print '<br>';
$permission=1;
$object=$groupinvoice;
include DOL_DOCUMENT_ROOT.'/core/tpl/notes.tpl.php';

dol_fiche_end();



llxFooter();
$db->close();
?>
