<?php
/*
 * Copyright (C) 2010 Regis Houssin <regis@dolibarr.fr>
 * Copyright (C) 2014 Florian Henry <florian.henry@open-concept.pro>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 * or see http://www.gnu.org/
 */

/**
 * \file groupinvoice/core/modules/groupinvoice/mod_groupinvoice_universal.php
 * \ingroup groupinvoice
 * \brief Fichier contenant la classe du modele de numerotation de reference de projet Universal
 */
dol_include_once ( '/groupinvoice/core/modules/groupinvoice/modules_groupinvoice.php' );

/**
 * Classe du modele de numerotation de reference de Universal
 */
class mod_groupinvoice_universal extends ModeleNumRefGroupInvoice {
	var $version = 'dolibarr'; // 'development', 'experimental', 'dolibarr'
	var $error = '';
	var $nom = 'Universal';

	/**
	 * Renvoi la description du modele de numerotation
	 *
	 * @return string Texte descripif
	 */
	function info() {

		global $conf, $langs;
		
		$langs->load ( "groupinvoice@groupinvoice" );
		$langs->load ( "admin" );
		
		$form = new Form ( $this->db );
		
		$texte = $langs->trans ( 'GenericNumRefModelDesc' ) . "<br>\n";
		$texte .= '<form action="' . $_SERVER ["PHP_SELF"] . '" method="POST">';
		$texte .= '<input type="hidden" name="token" value="' . $_SESSION ['newtoken'] . '">';
		$texte .= '<input type="hidden" name="action" value="updateMask">';
		$texte .= '<input type="hidden" name="maskconstgroupinvoice" value="AGF_UNIVERSAL_MASK">';
		$texte .= '<table class="nobordernopadding" width="100%">';
		
		$tooltip = $langs->trans ( "GenericMaskCodes", $langs->transnoentities ( "GroupInvoice" ), $langs->transnoentities ( "GroupInvoice" ) );
		$tooltip .= $langs->trans ( "GenericMaskCodes2" );
		$tooltip .= $langs->trans ( "GenericMaskCodes3" );
		$tooltip .= $langs->trans ( "GenericMaskCodes4a", $langs->transnoentities ( "GroupInvoice" ), $langs->transnoentities ( "GroupInvoice" ) );
		$tooltip .= $langs->trans ( "GenericMaskCodes5" );
		
		// Parametrage du prefix
		$texte .= '<tr><td>' . $langs->trans ( "Mask" ) . ':</td>';
		$texte .= '<td align="right">' . $form->textwithpicto ( '<input type="text" class="flat" size="24" name="maskgroupinvoice" value="' . $conf->global->GROUPINVOICE_UNIVERSAL_MASK . '">', $tooltip, 1, 1 ) . '</td>';
		
		$texte .= '<td align="left" rowspan="2">&nbsp; <input type="submit" class="button" value="' . $langs->trans ( "Modify" ) . '" name="Button"></td>';
		
		$texte .= '</tr>';
		
		$texte .= '</table>';
		$texte .= '</form>';
		
		return $texte;
	}

	/**
	 * Renvoi un exemple de numerotation
	 *
	 * @return string Example
	 */
	function getExample() {

		global $conf, $langs, $mysoc;
		
		$old_code_client = $mysoc->code_client;
		$mysoc->code_client = 'CCCCCCCCCC';
		$numExample = $this->getNextValue ( $mysoc, '' );
		$mysoc->code_client = $old_code_client;
		
		if (! $numExample) {
			$numExample = $langs->trans ( 'NotConfigured' );
		}
		return $numExample;
	}

	/**
	 * Return next value
	 *
	 * @param Societe $objsoc party
	 * @param groupinvoice $object
	 * @return string if OK, 0 if KO
	 */
	function getNextValue($objsoc, $object) {

		global $db, $conf;
		
		require_once (DOL_DOCUMENT_ROOT . "/core/lib/functions2.lib.php");
		
		// On defini critere recherche compteur
		$mask = $conf->global->GROUPINVOICE_UNIVERSAL_MASK;
		
		if (! $mask) {
			$this->error = 'NotConfigured';
			return 0;
		}
		
		$numFinal = get_next_value ( $db, $mask, 'groupinvoice', 'ref', '', $objsoc->code_client, $object->dated );
		
		return $numFinal;
	}
}

?>