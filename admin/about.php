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
 * 	\file		admin/about.php
 * 	\ingroup	groupinvoice
 * 	\brief		This file is the groupinvoice module's about page
 */
// Dolibarr environment
$res = @include("../../main.inc.php"); // From htdocs directory
if (! $res) {
    $res = @include("../../../main.inc.php"); // From "custom" directory
}


// Libraries
require_once DOL_DOCUMENT_ROOT . "/core/lib/admin.lib.php";
require_once '../lib/groupinvoice.lib.php';

dol_include_once('/groupinvoice/lib/php-markdown/Michelf/Markdown.php');

use \Michelf\Markdown;

//require_once "../class/myclass.class.php";
// Translations
$langs->load("groupinvoice@groupinvoice");

// Access control
if (! $user->admin) {
    accessforbidden();
}

// Parameters
$action = GETPOST('action', 'alpha');

/*
 * Actions
 */

/*
 * View
 */
$page_name = "GroupInvoiceAbout";
llxHeader('', $langs->trans($page_name));

// Subheader
$linkback = '<a href="' . DOL_URL_ROOT . '/admin/modules.php">'
    . $langs->trans("BackToModuleList") . '</a>';
print_fiche_titre($langs->trans($page_name), $linkback);

// Configuration header
$head = groupinvoiceAdminPrepareHead();
dol_fiche_head(
    $head,
    'about',
    $langs->trans("Module103086Name"),
    0,
    'groupinvoice@groupinvoice'
);

// About page goes here
echo $langs->trans("GroupInvoiceAboutPage");

echo '<br>';

$buffer = file_get_contents(dol_buildpath('/groupinvoice/README.md', 0));
echo Markdown::defaultTransform($buffer);

echo '<br>',
'<a href="' . dol_buildpath('/groupinvoice/COPYING', 1) . '">',
'<img src="' . dol_buildpath('/groupinvoice/img/gplv3.png', 1) . '"/>',
'</a>';

// Page end
dol_fiche_end();
llxFooter();
$db->close();
