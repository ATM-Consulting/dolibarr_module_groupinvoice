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
 *	\file		index.php
 *	\ingroup	groupinvoice
 *	\brief		List page
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

// Access control
// Restrict access to users with invoice reading permissions
restrictedArea($user, 'groupinvoice');
if (
	$user->societe_id > 0 // External user
) {
	accessforbidden();
}

require_once 'class/groupinvoice.class.php';
require_once 'lib/groupinvoice.lib.php';
require_once DOL_DOCUMENT_ROOT . '/core/class/html.formother.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formfile.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/pdf.lib.php';
require_once DOL_DOCUMENT_ROOT . '/contact/class/contact.class.php';

// Load translation files required by the page
$langs->load("groupinvoice@groupinvoice");

// Get parameters
$id = GETPOST('id', 'int');
$action = GETPOST('action', 'alpha');
$search_ref=GETPOST('search_ref', 'alpha');
$search_date=dol_mktime ( 0, 0, 0, GETPOST ( 'search_datemonth', 'int' ), GETPOST ( 'search_dateday', 'int' ), GETPOST ( 'search_dateyear', 'int' ) );
$search_soc=GETPOST('search_soc', 'alpha');
$search_mode=GETPOST('search_mode', 'alpha');
if ($search_mode==-1) $search_mode='';
$search_sale=GETPOST('search_sale','alpha');
$option = GETPOST('option');
$sendmail=GETPOST('sendmail');
if (!empty($sendmail)) $action='sendmail';

// Do we click on search criteria ?
if (GETPOST ( "button_search_x" )) {
	$action='';
}

$sortorder = GETPOST ( 'sortorder', 'alpha' );
$sortfield = GETPOST ( 'sortfield', 'alpha' );
$page = GETPOST ( 'page', 'int' );

if (empty($sortfield)) $sortfield='societe.nom';
if (empty($sortorder)) $sortorder='asc';

$diroutputpdf=$conf->groupinvoice->dir_output . '/merged';

$offset = $conf->liste_limit * $page;
$pageprev = $page - 1;
$pagenext = $page + 1;

/*
 * ACTIONS
 */

// Do we click on purge search criteria ?
if (GETPOST ( "button_removefilter_x" )) {
	$search_ref = '';
	$search_soc = '';
	$search_date = '';
	$search_mode = '';
	$search_sale='';
}



$filter_search_title ='';
$filter = array ();
if (! empty ( $search_id )) {
	$filter ['groupinvoice.ref'] = $search_ref;
	$filter_search_title .= '&search_ref=' . $search_ref;
}
if (! empty ( $search_soc )) {
	$filter ['societe.nom'] = $search_soc;
	$filter_search_title .= '&search_soc=' . $search_soc;
}
if (! empty ( $search_date )) {
	$filter ['groupinvoice.dated'] = $db->idate($search_date);
	$filter_search_title .= '&search_datemonth=' . dol_print_date ( $search_date, '%m' ) . '&search_dateday=' . dol_print_date ( $search_date, '%d' ) . '&search_dateyear=' . dol_print_date ( $search_date, '%Y' );
}
if (! empty ( $search_mode )) {
	$filter ['groupinvoice.mode_creation'] = $search_mode;
	$filter_search_title .= '&search_mode=' . $search_mode;
}
if (! empty ( $search_sale )) {
	$filter ['salesman.fk_user'] = $search_sale;
	$filter_search_title .= '&search_sale=' . $search_sale;
}

if ($action == "builddoc")
{
	if (is_array($_POST['toGenerate']))
	{		
		$arrayofinclusion=array();
		foreach($_POST['toGenerate'] as $tmppdf) $arrayofinclusion[]=preg_quote($tmppdf.'.pdf','/');
		$groupinvoices = dol_dir_list($conf->groupinvoice->dir_output,'all',1,implode('|',$arrayofinclusion),'\.meta$|\.png','date',SORT_DESC);
	
		// liste les fichiers
		$files = array();
		$factures_bak = $groupinvoices ;
		foreach($_POST['toGenerate'] as $basename){
			foreach($groupinvoices as $groupinvoicefile){
				if(strstr($groupinvoicefile["name"],$basename)){
					$files[] = $conf->groupinvoice->dir_output.'/'.$basename.'/'.$groupinvoicefile["name"];
				}
			}
		}
	
		// Define output language (Here it is not used because we do only merging existing PDF)
		$outputlangs = $langs;
		$newlang='';
		if ($conf->global->MAIN_MULTILANGS && empty($newlang) && GETPOST('lang_id')) $newlang=GETPOST('lang_id');
		if (! empty($newlang))
		{
			$outputlangs = new Translate("",$conf);
			$outputlangs->setDefaultLang($newlang);
		}
	
		// Create empty PDF
		$pdf=pdf_getInstance();
		if (class_exists('TCPDF'))
		{
			$pdf->setPrintHeader(false);
			$pdf->setPrintFooter(false);
		}
		$pdf->SetFont(pdf_getPDFFont($outputlangs));
	
		if (! empty($conf->global->MAIN_DISABLE_PDF_COMPRESSION)) $pdf->SetCompression(false);
	
		// Add all others
		foreach($files as $file)
		{
			// Charge un document PDF depuis un fichier.
			$pagecount = $pdf->setSourceFile($file);
			for ($i = 1; $i <= $pagecount; $i++)
			{
			$tplidx = $pdf->importPage($i);
			$s = $pdf->getTemplatesize($tplidx);
			$pdf->AddPage($s['h'] > $s['w'] ? 'P' : 'L');
				$pdf->useTemplate($tplidx);
			}
			}
	
			// Create output dir if not exists
			dol_mkdir($diroutputpdf);
	
			// Save merged file
			$filename=strtolower(dol_sanitizeFileName($langs->transnoentities("GroupInvoiceContactFileName")));
				
					if ($pagecount)
					{
						$now=dol_now();
						$file=$diroutputpdf.'/'.$filename.'_'.dol_print_date($now,'dayhourlog').'.pdf';
						$pdf->Output($file,'F');
						if (! empty($conf->global->MAIN_UMASK))
							@chmod($file, octdec($conf->global->MAIN_UMASK));
						}
					else
					{
						setEventMessage($langs->trans('NoPDFAvailableForChecked'),'errors');
					}
	}
	else
	{
		setEventMessage($langs->trans('NoGroupInvoiceSelected'),'errors');
	}
}
elseif ($action=='remove_file') {

	require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';

	$langs->load("other");
	$upload_dir = $diroutputpdf;
	$file = $upload_dir . '/' . GETPOST('file');
	$ret=dol_delete_file($file,0,0,0,'');
	if ($ret) setEventMessage($langs->trans("FileWasRemoved", GETPOST('urlfile')));
	else setEventMessage($langs->trans("ErrorFailToDeleteFile", GETPOST('urlfile')), 'errors');
	$action='';

}
/*elseif($action == 'sendmail') {
	
	$langs->load('mails');
	$langs->load("commercial");
	
	
	// For each groupinvoice selected
	foreach ($_POST['sendmailgroupinvoice'] as $id_groupinvoice) {
		
		$error = 0;
		
		$groupinvoicebymail = new GroupInvoice($db);
		$groupinvoicebymail->fetch($id_groupinvoice);
		$companymail = new Societe($db);
		$companymail->fetch($groupinvoicebymail->fk_company);
		
		// Define output language
		$outputlangs = $langs;
		$newlang = '';
		if ($conf->global->MAIN_MULTILANGS && empty($newlang))
			$newlang = $companymail->default_lang;
		if (! empty($newlang)) {
			$outputlangs = new Translate("", $conf);
			$outputlangs->setDefaultLang($newlang);
			$outputlangs->load('groupinvoice@groupinvoice');
		}
		
		// Get the first contact of the first invoice
		$invoices = $groupinvoicebymail->getInvoices();
		if (count($invoices) > 0) {
			$invoice = $invoices[0];
			$arrayidcontact = $invoice->getIdContact('external', 'BILLING');
			if (count($arrayidcontact) > 0) {
				$usecontact = true;
				$result = $invoice->fetch_contact($arrayidcontact[0]);
			}
			if (! empty($invoice->contact)) {
				$custcontact = $invoice->contact->getFullName($outputlangs, 1);
			}
		}
		
		if (empty($invoice->contact->email)) {
			setEventMessage($langs->trans('CannotSendMailGroupInvoiceInvoiceContact',$groupinvoicebymail->ref), 'errors');
		}else {
			$sendto = $invoice->contact->getFullName($outputlangs) . ' <' . $invoice->contact->email . '>';
		}
		
		if (empty($user->email)) {
			setEventMessage($langs->trans('CannotSendMailGroupInvoiceEmailFrom',$groupinvoicebymail->ref), 'errors');
			$error ++;
		}

		if (! $error) {
			$from = $user->getFullName($outputlangs) . ' <' . $user->email . '>';
			$replyto = $user->getFullName($outputlangs) . ' <' . $user->email . '>';
			$message = str_replace("__SIGNATURE__",$user->signature,$outputlangs->transnoentities('SendReminderGroupInvoiceRef'));
			$sendtocc = '';
			
			//Determine for akteos the Requester contact of the session/invoice
			if (($companymail->typent_code!='TE_OPCA') && ($companymail->typent_code!='TE_PAY')) {
				dol_include_once('/agefodd/class/agefodd_session_element.class.php');
				dol_include_once('/agefodd/class/agsession.class.php');
				$agf_fin=new Agefodd_session_element($db);
				$email_to_send=array($invoice->contact->id);
				foreach($invoices as $invoice) {
					$agf_fin->fetch_element_by_id($invoice->id,'fac');
					if (is_array($agf_fin->lines) && count($agf_fin->lines)>0) {
						foreach($agf_fin->lines as $line) {
							$session=new Agsession($db);
							$session->fetch($line->fk_session_agefodd);
							if (!empty($session->fk_socpeople_requester)) {
								$contact_requester=new Contact($db);
								$contact_requester->fetch($session->fk_socpeople_requester);
								if (!empty($contact_requester->email) && (!in_array($contact_requester->id,$email_to_send))) {
									if (!empty($sendto)) $sendto .= ", ";
									$sendto .= $contact_requester->getFullName($outputlangs) . ' <' . $contact_requester->email . '>';
									$email_to_send[]=$contact_requester->id;
								}
							}
						}
					}
				}
			}
			
			
			$deliveryreceipt = 0;
			$subject = $mysoc->name . '-' . $outputlangs->transnoentities('SendReminderGroupInvoiceTopic');
			
			// Create form object
			include_once DOL_DOCUMENT_ROOT . '/core/class/html.formmail.class.php';
			$formmail = new FormMail($db);
			
			$filename = dol_sanitizeFileName($groupinvoicebymail->ref);
			$filedir = $conf->groupinvoice->dir_output . '/' . $filename;
			$file = $filedir . '/' . $groupinvoicebymail->ref . '.pdf';
			
			$attachedfiles = $formmail->get_attached_files();
			$filepath = array($file);
			$filename = array($groupinvoicebymail->ref . '.pdf');
			$mimetype = array(dol_mimetype($groupinvoicebymail->ref . '.pdf'));
			
			// Send mail
			if (! empty($sendto) && !empty($from)) {
				//For test email
				//$message=$sendto.'<BR>'.$message;
				//$sendto='lrostand@akteos.fr,florian.henry@open-concept.pro';
				
				require_once DOL_DOCUMENT_ROOT . '/core/class/CMailFile.class.php';
				$mailfile = new CMailFile($subject, $sendto, $from, $message, $filepath, $mimetype, $filename, $sendtocc, '', $deliveryreceipt, -1);
				if (! empty($mailfile->error)) {
					setEventMessage($mailfile->error, 'errors');
				} else {
					$result = $mailfile->sendfile();
					if ($result) {
						$error = 0;
						
						$result = $groupinvoicebymail->createAction($from, $sendto, $sendtoid, $sendtocc, $subject, $message, $user);
						if ($result < 0) {
							$error ++;
							setEventMessage($groupinvoicebymail->error, 'errors');
						}
						
						if (empty($error)) {
							// Appel des triggers
							include_once DOL_DOCUMENT_ROOT . '/core/class/interfaces.class.php';
							$interface = new Interfaces($db);
							$result = $interface->run_triggers('GROUPINVOICE_SENTBYMAIL', $groupinvoicebymail, $user, $langs, $conf);
							if ($result < 0) {
								$error ++;
								setEventMessage($interface->error, 'errors');
							}
						}
						// Fin appel triggers
						
						if (empty($error)) {
							// Redirect here
							// This avoid sending mail twice if going out and then back to page
							$mesg = $langs->trans('MailSuccessfulySent', $mailfile->getValidAddress($from, 2), $mailfile->getValidAddress($sendto, 2));
							setEventMessage($mesg, 'mesgs');
							$action = '';
						}
					} else {
						$langs->load("other");
						if ($mailfile->error) {
							$mesg = $langs->trans('ErrorFailedToSendMail', $from, $sendto);
							setEventMessage($mailfile->error . '<BR>' . $mesg, 'errors');
						} else {
							setEventMessage('No mail sent. Feature is disabled by option MAIN_DISABLE_ALL_MAILS', 'errors');
						}
					}
				}
			}
		}
	}
	$action = '';
}*/

/*
 * VIEW
 */
$title = $langs->trans('Module103086Name');

$form=new Form($db);
$formother=new FormOther($db);
$formfile = new FormFile($db);

llxHeader('',$title);

// Count total nb of records
$nbtotalofrecords = 0;
if (empty ( $conf->global->MAIN_DISABLE_FULL_SCANLIST )) {
	$allgroupinvoice = getListOfOpennedGroupInvoices($db,$filter,$sortfield,$sortorder,0,0);
	$nbtotalofrecords=count($allgroupinvoice);
}
$list = getListOfOpennedGroupInvoices($db,$filter,$sortfield,$sortorder,$limit,$offset);


print_barre_liste ( $title, $page, $_SERVEUR ['PHP_SELF'], $filter_search_title, $sortfield, $sortorder, '', count($list), $nbtotalofrecords );


if($list) {
	
	echo '<form method="post" action="' . $_SERVER ['PHP_SELF'] . '" name="search_form">' . "\n";
	
	echo $langs->trans ( 'SalesRepresentatives' ) . ': ';
	echo $formother->select_salesrepresentatives ( $search_sale, 'search_sale', $user );
	
	
	echo '<table class="liste allwidth">',
	'<tr class="liste_titre">';

	// Table headers
	print_liste_field_titre($langs->trans('Ref'), $_SERVEUR ['PHP_SELF'], "groupinvoice.ref", "", $filter_search_title, '', $sortfield, $sortorder);
	print_liste_field_titre($langs->trans('Date'), $_SERVEUR ['PHP_SELF'], "groupinvoice.dated", "", $filter_search_title, '', $sortfield, $sortorder);
	print_liste_field_titre($langs->trans('Customer'), $_SERVEUR ['PHP_SELF'], "societe.nom", "", $filter_search_title, '', $sortfield, $sortorder);
	//print_liste_field_titre($langs->trans('CreationMode'), $_SERVEUR ['PHP_SELF'], "groupinvoice.mode_creation", "", $filter_search_title, '', $sortfield, $sortorder);
	print_liste_field_titre($langs->trans('Amount'), $_SERVEUR ['PHP_SELF'], "", "", $filter_search_title, '', $sortfield, $sortorder);
	print_liste_field_titre($langs->trans('Rest'), $_SERVEUR ['PHP_SELF'], "", "", $filter_search_title, '', $sortfield, $sortorder);
	print_liste_field_titre($langs->trans('LastSendEmailDate'), $_SERVEUR ['PHP_SELF'], "", "", $filter_search_title, '', $sortfield, $sortorder);
	print_liste_field_titre($langs->trans('PDFMerge'), $_SERVEUR ['PHP_SELF'], "", "", $filter_search_title, 'align=middle', $sortfield, $sortorder);
	print_liste_field_titre($langs->trans('SendEmail'), $_SERVEUR ['PHP_SELF'], "", "", $filter_search_title, 'align=middle', $sortfield, $sortorder);
	echo '</tr>';
	
	'<tr class="liste_titre">';
	
	// Table headers
	echo '<td class="liste_titre">';
	echo '<input type="text" class="flat" name="search_ref" value="' . $search_ref . '" size="4">';
	echo '</td>';
	
	echo '<td class="liste_titre">';
	echo $form->select_date ( $search_date, 'search_date', 0, 0, 1, 'search_form' );
	echo '</td>';
	
	
	echo '<td class="liste_titre">';
	echo '<input type="text" class="flat" name="search_soc" value="' . $search_soc . '" size="20">';
	echo '</td>';
	
	
	/*echo '<td class="liste_titre">';
	echo $form->selectarray ( 'search_mode', array('manual'=>$langs->trans('GroupInvoiceCreaMode_manual'),'auto'=>$langs->trans('GroupInvoiceCreaMode_auto')), $search_mode, 1 );
	echo '</td>';*/
	
	echo'<td class="liste_titre"></td>';
	echo'<td class="liste_titre"></td>';
	echo'<td class="liste_titre"></td>';
	echo'<td class="liste_titre"></td>';
	echo '<td class="liste_titre" align="right"><input class="liste_titre" type="image" name="button_search" src="' . DOL_URL_ROOT . '/theme/' . $conf->theme . '/img/search.png" value="' . dol_escape_htmltag ( $langs->trans ( "Search" ) ) . '" title="' . dol_escape_htmltag ( $langs->trans ( "Search" ) ) . '">';
	echo '&nbsp; ';
	echo '<input type="image" class="liste_titre" name="button_removefilter" src="' . DOL_URL_ROOT . '/theme/' . $conf->theme . '/img/searchclear.png" value="' . dol_escape_htmltag ( $langs->trans ( "RemoveFilter" ) ) . '" title="' . dol_escape_htmltag ( $langs->trans ( "RemoveFilter" ) ) . '">';
	echo '</td>';
	echo '</tr>';

	
	$var = true;
	// List available groupinvoices
	foreach ($list as $id) {

		$groupinvoice = new GroupInvoice($db);
		$groupinvoice->fetch($id);
		
		$rest_to_pay=$groupinvoice->getRest();
		
			$var = ! $var;
		
			$company = new Societe($db);
			$company->fetch($groupinvoice->fk_company);
			
			$filename=dol_sanitizeFileName($groupinvoice->ref);
			$filedir=$conf->groupinvoice->dir_output . '/' . dol_sanitizeFileName($groupinvoice->ref);

			echo '<tr '
			,$bc[$var]
			,'>',
			'<td>',
			$groupinvoice->getNameUrl(),
			$formfile->getDocumentsLink($groupinvoice->element, $filename, $filedir),
			'</td>',
			'<td>',
			dol_print_date($groupinvoice->dated, 'day'),
			'</td>',
			'<td>',
			$company->getNomUrl(1, 'customer'),
			'</td>';
			/*'<td>',
			$langs->trans('GroupInvoiceCreaMode_'.$groupinvoice->mode_creation),
			'</td>',*/
			echo '<td>',
			price($groupinvoice->amount, 1, $langs, 1, 2, 2, $langs->getCurrencySymbol ( $conf->currency )),
			'</td>',
			'<td>',
			price($rest_to_pay, 1, $langs, 1, 2, 2, $langs->getCurrencySymbol ( $conf->currency )),
			'</td>',
			'<td>',
			$groupinvoice->getLastActionEmailSend('daytextshort');
			'</td>';
			
			if (! empty($formfile->numoffiles))
				$groupinvoicecheckboxmerge= '<input id="cb'.$groupinvoice->id.'" class="flat checkformerge" type="checkbox" name="toGenerate[]" value="'.$groupinvoice->ref.'">';
			else
				$groupinvoicecheckboxmerge= '&nbsp;';
			
			echo '<td align="middle">',
			$groupinvoicecheckboxmerge,
			'</td>';
			
			echo '<td align="middle">',
			'<input id="mail'.$groupinvoice->id.'" class="flat" type="checkbox" name="sendmailgroupinvoice[]" value="'.$groupinvoice->id.'">';
			
			
			
			echo '</td>',
			'</tr>';
		
	}
	echo '</table>';
	
	
	// Action button "Send Mail"
	echo '<p class="right">',
	'<input type="submit" value="'.$langs->trans('SendMail').'" name="sendmail" class="butAction">',
	'</p>';
	
	
	$genallowed=1;
	$delallowed=1;
	
	echo '<br>';
	echo '<input type="hidden" name="option" value="'.$option.'">';
	$formfile->show_documents('unpaid','',$diroutputpdf,$_SERVER ['PHP_SELF'],$genallowed,$delallowed,'',1,0,0,48,1,$param,$langs->trans("PDFMerge"),$langs->trans("PDFMerge"));
	echo  '</form>';
	
	//TODO : Hack to update link on document beacuse merge unpaid is always link to unpaid invoice ...
	echo '<script type="text/javascript">
		jQuery(document).ready(function () {
                    	jQuery(function() {
                        	$("a[data-ajax|=\'false\'][href*=\'unpaid\']") 
								.each(function()
								   { 
								      this.href = this.href.replace(/unpaid/, 
								         "groupinvoice");
									  this.href =this.href.replace(/file=/, 
								         "file=merged/")
								   });
                        });
                    });
		</script>';
	
	echo '</form>';
} else {
	// No openned groupinvoice
	echo '<p>',
	$langs->trans('NoOpennedGroupInvoice');
	'</p>'
	;
}

// Action button "New"
echo '<p class="right">',
	'<a href="create.php" class="butAction">',
	$langs->trans('NewGroupInvoice'),
	'</a>',
	'</p>';

// Page end
llxFooter();
$db->close();
