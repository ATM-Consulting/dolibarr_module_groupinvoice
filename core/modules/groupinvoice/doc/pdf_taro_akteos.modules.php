<?php
/* GroupInvoice management
 * Copyright (C) 2009-2010  Erick Bullier       <eb.dev@ebiconsulting.fr>
 * Copyright (C) 2012-2013  Florian Henry       <florian.henry@open-concept.pro>
 * Copyright (C) 2014       Raphaël Doursenaud  <rdoursenaud@gpcsolutions.fr>
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
 * or see http://www.gnu.org/
 */

/**
 *    \file       core/modules/groupinvoice/doc/pdf_shrimp.modules.php
 *    \ingroup    groupinvoice
 *    \brief      Class to generate groupinvoice documents
 */
require_once dol_buildpath('/groupinvoice/class/groupinvoice.class.php');
require_once dol_buildpath('/groupinvoice/core/modules/groupinvoice/modules_groupinvoice.php');
require_once(DOL_DOCUMENT_ROOT . '/core/lib/pdf.lib.php');
require_once(DOL_DOCUMENT_ROOT . '/core/lib/company.lib.php');
require_once(DOL_DOCUMENT_ROOT . '/compta/bank/class/account.class.php');

/**
 *    Dunnning PDF document class
 */
class pdf_taro_akteos extends ModeleGroupInvoice
{
	/**
	 * The module's version
	 * @var string
	 */
	public $version = '0.0.1';

	/**
	 * Minimum PHP version for the module to work properly
	 * @var array
	 */
	public $phpmin = array(5, 2, 0);

	/**
	 * Type of document
	 * @var string
	 */
	public $type = 'pdf';

	/**
	 * The PDF library document
	 * @var TCPDF|FPDI
	 */
	protected $pdf;

	/**
	 * The document metrics unit
	 * @var string
	 */
	protected $unit;

	/**
	 * The document width
	 * @var float
	 */
	protected $width;

	/**
	 * The document height
	 * @var float
	 */
	protected $height;

	/**
	 * Issuer company
	 * @var Societe
	 */
	protected $issuer;

	/**
	 * Recipient company
	 * @var Societe
	 */
	protected $recipient;

	/**
	 * Footer color
	 * @var array
	 */
	protected $footer_color;

	/**
	 * Text color
	 * @var array
	 */
	protected $text_color;

	/**
	 * Head color
	 * @var array
	 */
	protected $head_color;

	/**
	 * Related groupinvoice
	 * @var GroupInvoice
	 */
	protected $groupinvoice;

	/**
	 * Language object
	 * @var Translate
	 */
	protected $outputlangs;
	
	protected $end_table_Y;

	/**
	 * \brief   Constructor
	 *
	 * @param   DoliDB $db Database handler
	 */
	public function __construct($db)
	{
		global $conf, $langs, $mysoc;

		$langs->load("groupinvoice@groupinvoice");

		$this->db = $db;
		$this->name = "taro_akteos";
		$this->description = $langs->trans('PDFTaroDescription');
		$this->outputlangs=$langs;

		// Options
		$this->option_logo = 1; // Affiche logo
		$this->option_multilang = 1; // Dispo en plusieurs langues
		$this->option_freetext = 1; // Support add of a personalised text

		// Paper
		$this->setFormat(pdf_getFormat());
		$this->orientation = 'P';

		// Margins
		// FIXME: should take units into account. mm assumed.
		$this->left_margin = isset($conf->global->MAIN_PDF_MARGIN_LEFT) ? $conf->global->MAIN_PDF_MARGIN_LEFT : 10;
		$this->right_margin = isset($conf->global->MAIN_PDF_MARGIN_RIGHT) ? $conf->global->MAIN_PDF_MARGIN_RIGHT : 10;
		$this->top_margin = isset($conf->global->MAIN_PDF_MARGIN_TOP) ? $conf->global->MAIN_PDF_MARGIN_TOP : 10;
		$this->bottom_margin = isset($conf->global->MAIN_PDF_MARGIN_BOTTOM) ? $conf->global->MAIN_PDF_MARGIN_BOTTOM : 5;

		// Colors
		$this->text_color = array(0, 0, 0); // Black
		$this->head_color = array(128, 128, 128); // Grey
		$this->footer_color = array(128, 128, 128); // Grey

		// Fonts
		$this->default_font_size = pdf_getPDFFontSize($this->outputlangs);

		// Pages
		$this->pagenb = 0;

		// PDF
		$this->pdf = pdf_getInstance($this->getFormat(), $this->unit, $this->orientation);
		$this->pdf->SetMargins($this->left_margin, $this->top_margin, $this->right_margin);
		$this->pdf->SetAutoPageBreak(false, $this->bottom_margin);
		$this->setXYTopLeftCorner();

		// Get source company
		$this->issuer = $mysoc;

		// Country code fixup
		// FIXME: Is this still needed ?
		if (!$this->issuer->country_code) {
			$this->issuer->country_code = substr(
				$langs->defaultlang,
				-2
			);
		}
	}

	/**
	 * Get the document format as a [width, height] array
	 *
	 * @return array
	 */
	private function getFormat()
	{
		return array($this->width, $this->height);
	}

	/**
	 * Set the document format
	 *
	 * @param array $format A ('width'=>w,'height'=>h,'unit'=>u) document format associative array
	 * @return void
	 */
	private function setFormat($format)
	{
		$this->width = $format['width'];
		$this->height = $format['height'];
		$this->unit = $format['unit'];
	}

	/**
	 * Set the PDF X cordinate relative to the left margin
	 *
	 * @param float $x_offset Positive X offset
	 * @return void
	 */
	private function setXLeftMargin($x_offset = 0.0)
	{
		$this->pdf->setX($this->left_margin + $x_offset);
	}

	/**
	 * Set the PDF X coordinate relative to the right margin
	 *
	 * @param float $x_offset Negative X offset
	 * @return void
	 */
	private function setXRightMargin($x_offset = 0.0)
	{
		$this->pdf->setX(-$this->right_margin - $x_offset);
	}

	/**
	 * Set the PDF Y coordinate relative to the top margin
	 *
	 * @param float $y_offset Positive X offset
	 * @return void
	 */
	private function setYTopMargin($y_offset = 0.0)
	{
		$this->pdf->setY($this->top_margin + $y_offset);
	}

	/**
	 * Set the PDF XY coordinates relative to the top left corner including margins
	 *
	 * @param float $x_offset Positive X offset
	 * @param float $y_offset Positive Y offset
	 * @return void
	 */
	private function setXYTopLeftCorner($x_offset = 0.0, $y_offset = 0.0)
	{
		$this->pdf->setXY($this->left_margin + $x_offset, $this->top_margin + $y_offset);
	}

	/**
	 * Set the PDF XY coordinates relative to the top right corner including margins
	 *
	 * @param float $x_offset Negative X offset
	 * @param float $y_offset Positive Y offset
	 * @return void
	 */
	private function setXYTopRightCorner($x_offset = 0.0, $y_offset = 0.0)
	{
		$this->pdf->setXY(-$this->right_margin - $x_offset, $this->top_margin + $y_offset);
	}

	/**
	 * Get the printable width
	 *
	 * @return float Printable width
	 */
	private function getPrintableWidth()
	{
		return $this->width - $this->left_margin - $this->right_margin;
	}

	/**
	 * Get the remaining width from current X position
	 *
	 * @return float Remaining width from current X position
	 */
	private function getRemainingWidth()
	{
		return $this->width - $this->pdf->getX() - $this->right_margin;
	}

	/**
	 * \brief   Write document file on disk
	 *
	 * @param GroupInvoice $groupinvoice GroupInvoice object
	 * @param Translate $outputlangs Lang object for output language
	 * @return int          1=ok, 0=ko
	 */
	public function write_file($groupinvoice, $outputlangs)
	{
		global $user, $langs, $conf;

		$this->groupinvoice = $groupinvoice;

		$this->outputlangs = $outputlangs;
		if (!is_object($this->outputlangs)) {
			$this->outputlangs = $langs;
		}
		
		$this->outputlangs->load("groupinvoice@groupinvoice");
		$this->outputlangs->load("bills");
		$this->outputlangs->load("companies");

		// Destinations
		// Definition of $dir and $file
		if ($this->groupinvoice->specimen) {
			$dest_dir = $conf->groupinvoice->dir_output;
			$dest_file = $dest_dir . "/SPECIMEN.pdf";
		} else {
			$objectref = dol_sanitizeFileName($this->groupinvoice->ref);
			$dest_dir = $conf->groupinvoice->dir_output . "/" . $objectref;
			$dest_file = $dest_dir . "/" . $objectref . ".pdf";
		}
	
		// Create target dir if need be
		if (!file_exists($dest_dir)) {
			if (dol_mkdir($dest_dir) < 0) {
				$this->error = $langs->trans("ErrorCanNotCreateDir", $dest_dir);
				return 0;
			}
		}

		if (file_exists($dest_dir)) {
			// We get rid of the predefined headers and footers
			// FIXME: we should extend these methods to provide our own customized header and footer rather than re-inventing the wheel
			if (class_exists('TCPDF')) {
				$this->pdf->setPrintHeader(false);
				$this->pdf->setPrintFooter(false);
			}

			$this->pdf->Open();

			$this->pdf->SetTitle(
				$this->outputlangs->convToOutputCharset(
					$this->outputlangs->transnoentities('GroupInvoice') . " " . $this->groupinvoice->ref
				)
			);
			$this->pdf->SetSubject($this->outputlangs->transnoentities($this->name));
			$this->pdf->SetCreator("Dolibarr " . DOL_VERSION);
			$this->pdf->SetAuthor($this->outputlangs->convToOutputCharset($user->fullname));
			$this->pdf->SetKeyWords(
				$this->outputlangs->convToOutputCharset(
					$this->groupinvoice->ref
				) . " " . $this->outputlangs->transnoentities("Document")
			);
			if ($conf->global->MAIN_DISABLE_PDF_COMPRESSION) {
				$this->pdf->SetCompression(false);
			}

			// Company
			$this->recipient = new Societe($this->db);
			$this->recipient->fetch($this->groupinvoice->fk_company);
			

			$this->printPage();
			
			$this->mergeInvoicePDF();

			$this->pdf->Close();
			$this->pdf->Output($dest_file, 'F');

			// Set file permissions
			if (!empty($conf->global->MAIN_UMASK)) {
				@chmod($dest_file, octdec($conf->global->MAIN_UMASK));
			}

			return 1; // No error
		} else {
			$this->error = $langs->trans("ErrorConstantNotDefined", "AGF_OUTPUTDIR");
			return 0;
		}
		$this->error = $langs->trans("ErrorUnknown");
		return 0; // Default error
	}

	/**
	 * \brief   Prints the page body
	 *
	 * @return void
	 */
	private function printPage()
	{
		global $conf;
		// Init fonts and colors
		$this->pdf->SetFont(pdf_getPDFFont($this->outputlangs), '', $this->default_font_size);
		$this->pdf->SetTextColorArray($this->text_color);

		$footer_height = $this->newPage()+25;

		/*
		 * Page body
		 */

		if (! empty($conf->global->MAIN_MULTILANGS)) {
			$currenttext_before= $conf->global->{'GROUPINVOICE_TEXT_BEFORE'.$this->outputlangs->defaultlang};
			$currenttext_after= $conf->global->{'GROUPINVOICE_TEXT_AFTER'.$this->outputlangs->defaultlang};
		}else {
			$currenttext_before= $conf->global->GROUPINVOICE_TEXT_BEFORE;
			$currenttext_after= $conf->global->GROUPINVOICE_TEXT_AFTER;
		}
		
		// Letter text first part
		/*$this->setXLeftMargin();
		$this->pdf->setY($this->pdf->getY() + 2);
		$this->pdf->SetFont(pdf_getPDFFont($this->outputlangs), '', $this->default_font_size);
		$this->pdf->writeHTMLCell(
			$this->width - $this->left_margin - $this->right_margin,
			0,
			$this->left_margin,
			$this->pdf->getY(),
			$currenttext_before,
			0,
			1
		);*/
		
		// Affiche notes
		if (! empty($this->groupinvoice->note_public))
		{
			$tab_top = 88;
		
			$this->pdf->SetFont('','', $this->default_font_size - 1);
			$this->pdf->writeHTMLCell(190, 3, $this->left_margin, $tab_top, dol_htmlentitiesbr($this->groupinvoice->note_public), 0, 1);
			$nexY = $this->pdf->GetY();
			$height_note=$nexY-$tab_top;
		
			// Rect prend une longueur en 3eme param
			$this->pdf->SetDrawColor(192,192,192);
			$this->pdf->Rect($this->left_margin, $tab_top-1, $this->width-$this->left_margin-$this->right_margin, $height_note+1);
		
			$tab_height = $tab_height - $height_note;
			$tab_top = $nexY+6;
		}
		else
		{
			$height_note=0;
		}
		
		// Invoice list
		$this->setXLeftMargin();
		//$this->pdf->setY($this->pdf->getY() + 2);
		$this->printTable($this->height - $footer_height);

		// Letter text second part
		$this->setXLeftMargin();
		$this->pdf->setY($this->pdf->getY() + 2);
		$this->pdf->SetFont(pdf_getPDFFont($this->outputlangs), '', $this->default_font_size);
		$this->pdf->writeHTMLCell(
			$this->width - $this->left_margin - $this->right_margin,
			0,
			$this->left_margin,
			$this->pdf->getY(),
			$currenttext_after,
			0,
			1
		);
		
		$this->printInformationTables();

		// TODO: Concat invoices with "DUPLICATA" watermark
	}

	/**
	 * \brief      Show page header
	 *
	 * @return void
	 */
	private function printHeader()
	{
		$this->outputlangs->load("main");

		$this->setXYTopLeftCorner();

		$logo_height = $this->printLogo();
		// TODO: Implement
		$address_height = $this->printAddress();
		$doc_infos_height = $this->printDocInfos();

		$this->setXLeftMargin();
		$this->setYTopMargin(max($logo_height, $address_height, $doc_infos_height));

		pdf_pagehead($this->pdf, $this->outputlangs, $this->height);
	}

	/**
	 * \brief   Show page footer
	 *
	 * @return float Footer Y start
	 */
	private function printFooter()
	{
		global $conf,$langs,$mysoc;
		
		// Logo en haut à gauche
		$logo=$conf->mycompany->dir_output.'/logos/footer.jpg';
		
		if (is_readable($logo))
		{
			$heightLogo=pdf_getHeightForLogo($logo);
			$this->pdf->Image($logo,  $this->left_margin, $this->height-$heightLogo-10, 0, 0, '', '', '', false, 300, '', false, false, 0, false, false, true);	// width=0 (auto)
		}

		return $heightLogo;
	}

	/**
	 * Add company logo to the document
	 *
	 * @return float Height
	 */
	private function printLogo()
	{
		global $conf;

		// FIXME: doesn't look like good practice
		$logo = $conf->mycompany->dir_output . '/logos/' . $this->issuer->logo;
		if ($this->issuer->logo) {
			if (is_readable($logo)) {
				$logo_height = pdf_getHeightForLogo($logo);
				$this->pdf->Image($logo, $this->pdf->getX(), $this->pdf->getY(), 0, $logo_height); // width=0 (auto)
				return $logo_height;
			}
		}

		// Text fallback
		$text = $this->issuer->name;
		$this->pdf->MultiCell(0, 4, $this->outputlangs->convToOutputCharset($text), 0, 'L');
		return $this->pdf->getY();
	}

	/**
	 * Print the addresses blocks
	 *
	 * @return void
	 */
	private function printAddress()
	{
		// FIXME: refactor, this stinks

		global $conf;
		
		$this->outputlangs->load('dict');

		$issuer_address = pdf_build_address($this->outputlangs, $this->issuer);

		// Show sender
		$posy = 42;
		$posx = $this->left_margin;
		if (!empty($conf->global->MAIN_INVERT_SENDER_RECIPIENT)) {
			$posx = $this->width - $this->right_margin - 80;
		}
		$hautcadre = 30;

		// Show sender frame
		$this->pdf->SetTextColor(0, 0, 0);
		$this->pdf->SetFont('', '', $this->default_font_size - 2);
		$this->pdf->SetXY($posx, $posy - 5);
		$this->pdf->MultiCell(66, 5, $this->outputlangs->transnoentities("BillFrom") . ":", 0, 'L');
		$this->pdf->SetXY($posx, $posy);
		$this->pdf->SetFillColor(230, 230, 230);
		$this->pdf->MultiCell(82, $hautcadre, "", 0, 'R', 1);
		$return = $this->pdf->getY();
		$this->pdf->SetTextColor(0, 0, 60);

		// Show sender name
		$this->pdf->SetXY($posx + 2, $posy + 3);
		$this->pdf->SetFont('', 'B', $this->default_font_size);
		$this->pdf->MultiCell(80, 4, $this->outputlangs->convToOutputCharset($this->issuer->name), 0, 'L');
		$posy = $this->pdf->getY();

		// Show sender information
		$this->pdf->SetXY($posx + 2, $posy);
		$this->pdf->SetFont('', '', $this->default_font_size - 1);
		$this->pdf->MultiCell(80, 4, $issuer_address, 0, 'L');

		// If BILLING contact is defined, we use it
		$usecontact = false;
		$arrayidcontact = $this->recipient->getIdContact('external', 'BILLING');
		if (count($arrayidcontact) > 0) {
			$usecontact = true;
			$result = $this->recipient->fetch_contact($arrayidcontact[0]);
		}

		// Recipient name
		if (!empty($usecontact)) {
			// On peut utiliser le nom de la societe du contact
			if (!empty($conf->global->MAIN_USE_COMPANY_NAME_OF_CONTACT)) {
				$socname = $this->recipient->contact->socname;
			} else {
				$socname = $this->recipient->nom;
			}
			$carac_client_name = $this->outputlangs->convToOutputCharset($socname);
		} else {
			$carac_client_name = $this->outputlangs->convToOutputCharset($this->recipient->nom);
		}

		$carac_client = pdf_build_address(
			$this->outputlangs,
			$this->emetteur,
			$this->recipient,
			($usecontact ? $this->recipient->contact : ''),
			$usecontact,
			'target'
		);

		// Show recipient
		$widthrecbox = 100;
		if ($this->width < 210) {
			$widthrecbox = 84;
		} // To work with US executive format
		$posy = 42;
		$posx = $this->width - $this->right_margin - $widthrecbox;
		if (!empty($conf->global->MAIN_INVERT_SENDER_RECIPIENT)) {
			$posx = $this->left_margin;
		}

		// Show recipient frame
		$this->pdf->SetTextColor(0, 0, 0);
		$this->pdf->SetFont('', '', $this->default_font_size - 2);
		$this->pdf->SetXY($posx + 2, $posy - 5);
		$this->pdf->MultiCell($widthrecbox, 5, $this->outputlangs->transnoentities("BillTo") . ":", 0, 'L');
		$this->pdf->Rect($posx, $posy, $widthrecbox, $hautcadre);

	
		//Get the first contact of the first invoice
		$invoices = $this->groupinvoice->getInvoices();
		if (count($invoices)>0) {
			$invoice= $invoices[0];
			$arrayidcontact=$invoice->getIdContact('external','BILLING');
			if (count($arrayidcontact) > 0)
			{
				$usecontact=true;
				$result=$invoice->fetch_contact($arrayidcontact[0]);
			}
			// Show Special contact name
			if (is_array($invoice->array_options) && key_exists('options_conatct_cust',$invoice->array_options) && !empty($invoice->array_options['options_conatct_cust'])) {

			}elseif (!empty($invoice->contact)) {

				$carac_client_name=$this->outputlangs->convToOutputCharset($invoice->contact->socname);
		
			}
		}
		
		// Show recipient name
		$this->pdf->SetXY($posx + 2, $posy + 3);
		$this->pdf->SetFont('', 'B', $this->default_font_size);
		$this->pdf->MultiCell($widthrecbox, 4, $carac_client_name, 0, 'L');
		
		//Get the first contact of the first invoice
		$invoices = $this->groupinvoice->getInvoices();
		if (count($invoices)>0) {
			$invoice= $invoices[0];
			$arrayidcontact=$invoice->getIdContact('external','BILLING');
			if (count($arrayidcontact) > 0)
			{
				$usecontact=true;
				$result=$invoice->fetch_contact($arrayidcontact[0]);
			}
			// Show Special contact name
			if (is_array($invoice->array_options) && key_exists('options_conatct_cust',$invoice->array_options) && !empty($invoice->array_options['options_conatct_cust'])) {
				$this->pdf->SetXY($posx+2,$posy+7);
				$this->pdf->SetFont('','', $default_font_size);
				$this->pdf->MultiCell($widthrecbox, 4, $invoice->array_options['options_conatct_cust'], 0, 'L');
			}elseif (!empty($invoice->contact)) {
				$this->pdf->SetXY($posx+2,$posy+7);
				$this->pdf->SetFont('','', $default_font_size);
				$this->pdf->MultiCell($widthrecbox, 4, $this->outputlangs->convToOutputCharset($invoice->contact->getFullName($this->outputlangs,1)), 0, 'L');	
				$carac_client_name=$this->outputlangs->convToOutputCharset($invoice->contact->socname);
				
				$invoice->contact->fetch_thirdparty();
				
				$carac_client = pdf_build_address(
					$this->outputlangs,
					$this->emetteur,
					$invoice->contact->thirdparty,
					'',
					false,
					'target'
				);
				
				
			}
		}

		// Show recipient information
		$this->pdf->SetFont('', '', $this->default_font_size - 1);
		$this->pdf->SetXY($posx + 2, $posy + 7 + (dol_nboflines_bis($carac_client_name, 50) * 4));
		$this->pdf->MultiCell($widthrecbox, 4, $carac_client, 0, 'L');

		return $return;
	}

	/**
	 * Print document informations
	 *
	 * @return float Height
	 */
	private function printDocInfos()
	{
		$start_y = $this->pdf->getY();

		// FIXME: this works with millimeters, may not work with other units
		$company_header_width = 100;

		// Document title
		$this->setXYTopRightCorner($company_header_width);
		$this->pdf->SetFont('', 'B', $this->default_font_size + 3);
		$this->pdf->SetTextColorArray($this->text_color);
		$title = $this->outputlangs->transnoentities("GroupInvoicePDFTitle");
		$this->pdf->MultiCell($company_header_width, 4, $title, '', 'R');

		// Reference
		$this->setXRightMargin($company_header_width);
		$this->pdf->SetFont('', '', $this->default_font_size);
		$this->pdf->SetTextColorArray($this->text_color);
		$this->pdf->MultiCell(
			100,
			4,
			$this->outputlangs->transnoentities("Ref") . " : " . $this->outputlangs->convToOutputCharset(
				$this->groupinvoice->ref
			),
			'',
			'R'
		);

		// Customer code
		$this->setXRightMargin($company_header_width);
		$this->pdf->SetTextColorArray($this->text_color);
		$this->pdf->MultiCell(
			100,
			3,
			$this->outputlangs->transnoentities("CustomerCode") . " : "  . $this->outputlangs->convToOutputCharset(
				$this->recipient->code_client
			),
			'',
			'R'
		);

		// Date
		$this->setXRightMargin($company_header_width);
		$this->pdf->SetTextColorArray($this->text_color);
		$this->pdf->MultiCell(
			100,
			3,
			$this->outputlangs->transnoentities("Date") . " : "  . dol_print_date(
				$this->groupinvoice->dated,
				"day",
				false,
				$this->outputlangs
			),
			'',
			'R'
		);

		return $this->pdf->GetY() - $start_y;
	}

	/**
	 * Prints the table
	 *
	 * @param float $max_y_position Bottom limit for page breaking management
	 * @return void
	 */
	private function printTable($max_y_position)
	{
		
		$this->setXLeftMargin();
		$table_start = array($this->pdf->getX(), $this->pdf->getY());
		$this->printTableHeader($table_start);

		// Lines
		$invoices = $this->groupinvoice->getInvoices();
		$this->printLines($invoices, $max_y_position, $table_start);
		// TODO: Print total
		$total_rest = 0;
		foreach($invoices as $invoice) {
			/* @var $invoice Facture */
			$total_ht += $invoice->total_ht;
			$total_ttc += $invoice->total_ttc;
			$total_tva += $invoice->total_tva;
		}

		$line_height = 4;
		$col_width = $this->getPrintableWidth() / 5;
		if($this->pdf->getY() > $max_y_position-25)$this->newPage();
		$this->end_table_Y=$this->pdf->getY();
		
		// Total HT text
		$this->setXRightMargin($col_width * 2);
		$this->pdf->SetFont(pdf_getPDFFont($this->outputlangs), '', $this->default_font_size -2);
		$this->pdf->Cell(
			$col_width,
			$line_height,
			$this->outputlangs->transnoentities("TotalHT"),
			0,
			0,
			"L",
			0
		);

		// Total HT
		$this->setXRightMargin($col_width);
		$this->pdf->SetFont(pdf_getPDFFont($this->outputlangs), 'B', $this->default_font_size-2);
		$this->pdf->Cell(
			$col_width,
			$line_height,
			$this->outputlangs->convToOutputCharset(
				price($total_ht, 0, $this->outputlangs, 1, 2, 2)
			),
			0,
			1,
			"R",
			0
		);
		
		// Total TVA text
		$this->setXRightMargin($col_width*2);
		$this->pdf->SetFont(pdf_getPDFFont($this->outputlangs), '', $this->default_font_size-2);
		$this->pdf->Cell(
			$col_width,
			$line_height,
			$this->outputlangs->transnoentities("TotalVAT"),
			0,
			0,
			"L",
			0
		);
		
		// Total TVA
		$this->setXRightMargin($col_width);
		$this->pdf->SetFont(pdf_getPDFFont($this->outputlangs), 'B', $this->default_font_size-2);
		$this->pdf->Cell(
			$col_width,
			$line_height,
			$this->outputlangs->convToOutputCharset(
				price($total_tva, 0, $this->outputlangs, 1, 2, 2)
			),
			0,
			1,
			"R",
			0
		);
		
		// Total TTC text
		$this->setXRightMargin($col_width*2);
		$this->pdf->SetFont(pdf_getPDFFont($this->outputlangs), '', $this->default_font_size-2);
		$this->pdf->Cell(
			$col_width,
			$line_height,
			$this->outputlangs->transnoentities("TotalTTC"),
			0,
			0,
			"L",
			1
		);
		
		// Total TTC
		$this->setXRightMargin($col_width);
		$this->pdf->SetFont(pdf_getPDFFont($this->outputlangs), 'B', $this->default_font_size-2);
		$this->pdf->Cell(
			$col_width,
			$line_height,
			$this->outputlangs->convToOutputCharset(
				price($total_ttc, 0, $this->outputlangs, 1, 2, 2)
			),
			0,
			1,
			"R",
			1
		);
		
		$this->outputlangs->load('agefodd@agefodd');
		$this->setXRightMargin($col_width*2);
		$this->pdf->SetFont ( pdf_getPDFFont ( $this->outputlangs ), 'B', $this->default_font_size - 3 );
		$this->pdf->Cell(
			$col_width, 
			$line_height, 
			$this->outputlangs->convToOutputCharset('Ref '.$this->outputlangs->transnoentities("AgfRecallInvoiceNum",$this->groupinvoice->ref)), 
			//$this->outputlangs->convToOutputCharset($this->groupinvoice->ref),
			0,
			1,
			'L',
			0);


	}

	/**
	 * Prints the table header
	 *
	 * @param array $table_start The element starting coordinates (x, y)
	 * @return void
	 */
	private function printTableHeader($table_start) {
		// Header
		$table_header_height = 4;

		$this->outputlangs->load('agefodd@agefodd');
		$this->outputlangs->load('products');
		
		$str='<table border="0"><tr style="background-color: #e6e6e6;">';
		$str.='<td style="border: 1px solid gray;" width="10%">'.$this->outputlangs->transnoentities('Invoice').'</td>';
		$str.='<td style="border: 1px solid gray;" width="11%">'.$this->outputlangs->transnoentities('AgfRefDossier').'</td>';
		$str.='<td style="border: 1px solid gray;" width="17%">'.$this->outputlangs->transnoentities('AgfLieu').'</td>';
		$str.='<td style="border: 1px solid gray;" width="9%">'. $this->outputlangs->transnoentities('AgfPDFFichePeda1').'</td>';
		$str.='<td style="border: 1px solid gray;" width="10%">'.$this->outputlangs->transnoentities('Date').'</td>';
		$str.='<td style="border: 1px solid gray;" width="33%">'.$this->outputlangs->transnoentities('Product').'</td>';
		$str.='<td style="border: 1px solid gray;" align="right" width="10%">'.$this->outputlangs->transnoentities('AmountHT').'</td>';
		$str.='</tr></table>';
		
		$this->pdf->setXY($this->pdf->getX(), $this->pdf->getY());
		//$this->pdf->SetFillColor(230, 230, 230);
		$this->pdf->SetFont('', '', $this->default_font_size-2);
		$this->pdf->writeHTMLCell ( 0, 5, $this->left_margin, $this->pdf->getY(), $this->outputlangs->convToOutputCharset ( $str ), 0, 1, false, true, 'C', true );
		
	}

	/**
	 * Print a new page with it's footer and header
	 *
	 * @return float Footer Y start coordinate
	 */
	private function newPage()
	{
		$this->pdf->AddPage();
		$this->pagenb++;
		$footer_height = $this->printFooter();
		$this->printHeader();

		return $footer_height;
	}

	/**
	 * Print the table lines
	 *
	 * @param array $invoices Invoices list
	 * @param float $max_y_position Maximum Y position for manual page break management
	 * @param array $table_start Table start coordinates (x, y)
	 * @return void
	 */
	private function printLines($invoices, $max_y_position, $table_start)
	{
		// Dimensions
		$line_height = 4;
		$col_width = $this->getPrintableWidth() / 8;
		
	
		$current_line = 0;
		$oldref='';
		$traineelist='';
		foreach ($invoices as $invoice) {
			$current_line_line = 0;
			foreach($invoice->lines as $invoicelines) {
				/* @var $line Facture */
				$current_line++;
				$current_line_line++;
				$table_content_start = $this->pdf->getY();
				
				//FIXME : hack for akteos
				
				dol_include_once('/agefodd/class/agefodd_session_element.class.php');
				dol_include_once('/agefodd/class/agsession.class.php');
				dol_include_once('/agefodd/class/agefodd_session_stagiaire.class.php');
				$refdossier=array();
				$location=array();
				$duration=array();
				$date_session=array();
				$trainee=array();
				$session_product=array();
				$session_label=array();
				$agf_fin=new Agefodd_session_element($this->db);
				$agf_fin->fetch_element_by_id($invoice->id,'fac');
				if (is_array($agf_fin->lines) && count($agf_fin->lines)>0) {
					foreach($agf_fin->lines as $linefin) {
				
						$refdossier[] =  $linefin->fk_session_agefodd.'_'.$invoice->socid;
				
						$session=new Agsession($this->db);
						$session->fetch($linefin->fk_session_agefodd);
				
				
						$location[]=$session->placecode;
						$duration[]=$session->duree_session.' ' . $this->outputlangs->transnoentities('Hour') . 's';
						$date_session[]=dol_print_date($session->dated,'day','tzserver',$this->outputlangs);
						$session_product[]=$session->fk_product;
						$session_label[$session->fk_product]=$session->intitule_custo;
				
				
						$session_trainee= new Agefodd_session_stagiaire($this->db);
						$session_trainee->fetch_stagiaire_per_session($linefin->fk_session_agefodd,$invoice->socid,1);
				
						if (is_array($session_trainee->lines) && count($session_trainee->lines)>0) {
							foreach($session_trainee->lines as $traineeline) {
								if ($traineeline->status_in_session==3) {
									$trainee[]=$traineeline->nom.' '.$traineeline->prenom;
								}
							}
						}
					}
				}
				
				$str='<table border="0" cellpadding="2px" cellspacing="0"><tr>';
				if ($invoice->ref==$oldref) {
					$str.='<td style="border: 1px solid gray;" width="10%"></td>';
					$str.='<td style="border: 1px solid gray;" width="11%"></td>';
					$str.='<td style="border: 1px solid gray;" width="17%"></td>';
					$str.='<td style="border: 1px solid gray;" width="9%"></td>';
					$str.='<td style="border: 1px solid gray;" width="10%"></td>';
				} else {
					$str.='<td style="border: 1px solid gray;" width="10%">'.$invoice->ref.'</td>';
					$str.='<td style="border: 1px solid gray;" width="11%">'.implode(',',$refdossier).'</td>';
					$str.='<td style="border: 1px solid gray;" width="17%">'.implode(',',$location).'</td>';
					$str.='<td style="border: 1px solid gray;" width="9%">'.implode(',',$duration).'</td>';
					$str.='<td style="border: 1px solid gray;" width="10%">'.implode(',',$date_session).'</td>';
					
				}
				
				
				if (in_array($invoicelines->fk_product,$session_product)) {
					$product_desc =$invoicelines->product_label.':'.$session_label[$invoicelines->fk_product];
				} else {
					$product_desc=$invoicelines->product_label;
				}
				
				$str.='<td style="border: 1px solid gray;" width="33%">'.$product_desc.'</td>';
				$str.='<td style="border: 1px solid gray;" align="right" width="10%">'.price($invoicelines->total_ht).'</td>';
				
				$str.='</tr>';
				
				if ($current_line_line==count($invoice->lines)) {
					if (count($trainee)>0) {
						$str.='<tr><td colspan="7" style="border: 1px solid gray;">'.implode(', ',$trainee).'</td></tr>';
					} 
					$str.='<tr style="background-color: #e6e6e6;"><td colspan="7" style="border: 1px solid gray; height:3px;"></td></tr>';
				}
				
				
				$str.='</table>';
				$this->pdf->setXY($this->pdf->getX(), $this->pdf->getY());
				$this->pdf->SetFont('', '', $this->default_font_size-3);
				$this->pdf->writeHTMLCell ( 0, 0, $this->left_margin, $this->pdf->getY(), $this->outputlangs->convToOutputCharset ( $str ), 0, 1, false, true, 'L', true );
				
				$oldref=$invoice->ref;					
				
				// Page break management
				
				if ($this->pdf->GetY() > $max_y_position - $line_height) {
					$this->newPage();
					// Don't reprint header if we were displaying the last line before breaking
					if ($current_line !== count($invoicelines)) {
						//$table_start = array($this->pdf->getX(), $this->pdf->getY() + 2);
						$this->printTableHeader($table_start);
					}
				}
			}
		}
		
	
	}
	
	
	/**
	 *   Show miscellaneous information (payment mode, payment term, ...)
	 *   @return	void
	 */
	private function printInformationTables()
	{
		global $conf;
		
		$invoices = $this->groupinvoice->getInvoices();
		if (count($invoices)>0) {
			$object = $invoices[0];
			
			$posy=$this->end_table_Y;
		
			$default_font_size = pdf_getPDFFontSize($this->outputlangs);
		
			$this->pdf->SetFont('','', $default_font_size - 1);
		
			// If France, show VAT mention if not applicable
			if ($this->issuer->country_code == 'FR' && $this->franchise == 1)
			{
				$this->pdf->SetFont('','B', $default_font_size - 2);
				$this->pdf->SetXY($this->left_margin, $posy);
				$this->pdf->MultiCell(100, 3, $this->outputlangs->transnoentities("VATIsNotUsedForInvoice"), 0, 'L', 0);
		
				$posy=$this->pdf->GetY()+4;
			}
		
			$posxval=52;
		
			// Show payments conditions
			if ($object->type != 2 && ($object->cond_reglement_code || $object->cond_reglement))
			{
				$this->pdf->SetFont('','B', $default_font_size - 2);
				$this->pdf->SetXY($this->left_margin, $posy);
				$titre = $this->outputlangs->transnoentities("PaymentConditions").':';
				$this->pdf->MultiCell(80, 4, $titre, 0, 'L');
		
				$this->pdf->SetFont('','', $default_font_size - 2);
				$this->pdf->SetXY($posxval, $posy);
				$lib_condition_paiement=$this->outputlangs->transnoentities("PaymentCondition".$object->cond_reglement_code)!=('PaymentCondition'.$object->cond_reglement_code)?$this->outputlangs->transnoentities("PaymentCondition".$object->cond_reglement_code):$this->outputlangs->convToOutputCharset($object->cond_reglement_doc);
				$lib_condition_paiement=str_replace('\n',"\n",$lib_condition_paiement);
				$this->pdf->MultiCell(80, 4, $lib_condition_paiement,0,'L');
		
				$posy=$this->pdf->GetY()+3;
			}
		
			if ($object->type != 2)
			{
				// Check a payment mode is defined
				if (empty($object->mode_reglement_code)
				&& ! $conf->global->FACTURE_CHQ_NUMBER
				&& ! $conf->global->FACTURE_RIB_NUMBER)
				{
					$this->pdf->SetXY($this->left_margin, $posy);
					$this->pdf->SetTextColor(200,0,0);
					$this->pdf->SetFont('','B', $default_font_size);
					$this->pdf->MultiCell(80, 3, $this->outputlangs->transnoentities("ErrorNoPaiementModeConfigured"),0,'L',0);
					$this->pdf->SetTextColor(0,0,0);
		
					$posy=$this->pdf->GetY()+1;
				}
		
				// Show payment mode
				if ($object->mode_reglement_code
				&& $object->mode_reglement_code != 'CHQ'
					&& $object->mode_reglement_code != 'VIR')
				{
					$this->pdf->SetFont('','B', $default_font_size - 2);
					$this->pdf->SetXY($this->left_margin, $posy);
					$titre = $this->outputlangs->transnoentities("PaymentMode").':';
					$this->pdf->MultiCell(80, 5, $titre, 0, 'L');
		
					$this->pdf->SetFont('','', $default_font_size - 2);
					$this->pdf->SetXY($posxval, $posy);
					$lib_mode_reg=$this->outputlangs->transnoentities("PaymentType".$object->mode_reglement_code)!=('PaymentType'.$object->mode_reglement_code)?$this->outputlangs->transnoentities("PaymentType".$object->mode_reglement_code):$this->outputlangs->convToOutputCharset($object->mode_reglement);
					$this->pdf->MultiCell(80, 5, $lib_mode_reg,0,'L');
		
					$posy=$this->pdf->GetY()+2;
				}
		
				// Show payment mode CHQ
				if (empty($object->mode_reglement_code) || $object->mode_reglement_code == 'CHQ')
				{
					// Si mode reglement non force ou si force a CHQ
					if (! empty($conf->global->FACTURE_CHQ_NUMBER))
					{
						$diffsizetitle=(empty($conf->global->PDF_DIFFSIZE_TITLE)?3:$conf->global->PDF_DIFFSIZE_TITLE);
		
						if ($conf->global->FACTURE_CHQ_NUMBER > 0)
						{
							$account = new Account($this->db);
							$account->fetch($conf->global->FACTURE_CHQ_NUMBER);
		
							$this->pdf->SetXY($this->left_margin, $posy);
							$this->pdf->SetFont('','B', $default_font_size - $diffsizetitle);
							$this->pdf->MultiCell(100, 3, $this->outputlangs->transnoentities('PaymentByChequeOrderedTo',$account->proprio),0,'L',0);
							$posy=$this->pdf->GetY()+1;
		
							if (empty($conf->global->MAIN_PDF_HIDE_CHQ_ADDRESS))
							{
								$this->pdf->SetXY($this->left_margin, $posy);
								$this->pdf->SetFont('','', $default_font_size - $diffsizetitle);
								$this->pdf->MultiCell(100, 3, $this->outputlangs->convToOutputCharset($account->owner_address), 0, 'L', 0);
								$posy=$this->pdf->GetY()+2;
							}
						}
						if ($conf->global->FACTURE_CHQ_NUMBER == -1)
						{
							$this->pdf->SetXY($this->left_margin, $posy);
							$this->pdf->SetFont('','B', $default_font_size - $diffsizetitle);
							$this->pdf->MultiCell(100, 3, $this->outputlangs->transnoentities('PaymentByChequeOrderedTo',$this->issuer->name),0,'L',0);
							$posy=$this->pdf->GetY()+1;
		
							if (empty($conf->global->MAIN_PDF_HIDE_CHQ_ADDRESS))
							{
								$this->pdf->SetXY($this->marge_gauche, $posy);
								$this->pdf->SetFont('','', $default_font_size - $diffsizetitle);
								$this->pdf->MultiCell(100, 3, $this->outputlangs->convToOutputCharset($this->issuer->getFullAddress()), 0, 'L', 0);
								$posy=$this->pdf->GetY()+2;
							}
						}
					}
				}
		
				// If payment mode not forced or forced to VIR, show payment with BAN
				if (empty($object->mode_reglement_code) || $object->mode_reglement_code == 'VIR')
				{
					if (! empty($object->fk_bank) || ! empty($conf->global->FACTURE_RIB_NUMBER))
					{
						$bankid=(empty($object->fk_bank)?$conf->global->FACTURE_RIB_NUMBER:$object->fk_bank);
						$account = new Account($this->db);
						$account->fetch($bankid);
		
						$curx=$this->left_margin;
						$cury=$posy;
		
						$posy=pdf_bank($this->pdf,$this->outputlangs,$curx,$cury,$account,0,$default_font_size);
		
						$posy+=2;
					}
				}
				
				// Show num TVA intra Sender
				$this->pdf->SetXY($this->left_margin,$this->pdf->getY());
				$this->pdf->SetFont('','', $default_font_size - 4);
				$this->pdf->MultiCell(0, 4, $this->outputlangs->transnoentities("VATIntraShort").': '.$this->outputlangs->convToOutputCharset($this->issuer->tva_intra), 0, 'L');
				
				// Show num TVA intra Sender
				$this->outputlangs->load('agefodd@agefodd');
				$this->pdf->SetXY($this->left_margin,$this->pdf->getY());
				$this->pdf->SetFont('','', $default_font_size - 4);
				$this->pdf->MultiCell(0, 4, $this->outputlangs->transnoentities("AgfNumAct").': '.$this->outputlangs->convToOutputCharset($conf->global->AGF_ORGANISME_NUM), 0, 'L');
					
				// Show num TVA intra Sender
				if ($this->outputlangs->defaultlang=='fr_FR') {
					$this->outputlangs->load('agefodd@agefodd');
					$this->pdf->SetXY($this->left_margin,$this->pdf->getY());
					$this->pdf->SetFont('','', $default_font_size - 4);
					$this->pdf->MultiCell(0, 4, $this->outputlangs->transnoentities("TVA acquittée d’après encaissement"), 0, 'L');
				}
			}
		}

	}
	
	/**
	 * Add the Invoices PDF
	 *
	 * @return void
	 */
	private function mergeInvoicePDF() {
		
		global $conf;
		//Get invoice list
		$invoices = $this->groupinvoice->getInvoices();
		if (count ( $invoices ) > 0) {
			foreach ( $invoices as $invoice ) {
				//Get invoice PDF file name
				$objectref = dol_sanitizeFileName($invoice->ref);
				$dir = $conf->facture->dir_output . "/" . $objectref;
				$file = $dir . "/" . $objectref . ".pdf";
				dol_syslog ( get_class ( $this ) . ':: $file=' . $file, LOG_DEBUG );
				// If file really exists
				if (is_file ( $file )) {
					$count = $this->pdf->setSourceFile ( $file );
					// import all page
					for($i = 1; $i <= $count; $i ++) {
						// New page
						$this->pdf->AddPage ();
						
						//Merge Invoice PDF
						$tplIdx = $this->pdf->importPage ( $i );
						
						$this->pdf->useTemplate ( $tplIdx, 0, 0, $this->width );
						if (method_exists ( $this->pdf, 'AliasNbPages' ))
							$this->pdf->AliasNbPages ();
					}
				}
			}
		}
	}
}
