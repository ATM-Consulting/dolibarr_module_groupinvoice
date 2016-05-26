-- GroupInvoice management
-- Copyright (C) 2014 Raphaël Doursenaud <rdoursenaud@gpcsolutions.fr>
--
-- This program is free software: you can redistribute it and/or modify
-- it under the terms of the GNU General Public License as published by
-- the Free Software Foundation, either version 3 of the License, or
-- (at your option) any later version.
--
-- This program is distributed in the hope that it will be useful,
-- but WITHOUT ANY WARRANTY; without even the implied warranty of
-- MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
-- GNU General Public License for more details.
--
-- You should have received a copy of the GNU General Public License
-- along with this program.  If not, see <http://www.gnu.org/licenses/>.

ALTER TABLE llx_groupinvoice_invoice ADD PRIMARY KEY pk_groupinvoice_invoice (fk_groupinvoice, fk_invoice);
ALTER TABLE llx_groupinvoice_invoice ADD INDEX idx_groupinvoice_invoice_fk_groupinvoice (fk_groupinvoice);
ALTER TABLE llx_groupinvoice_invoice ADD INDEX idx_groupinvoice_invoice_fk_invoice (fk_invoice);

ALTER TABLE llx_groupinvoice_invoice ADD CONSTRAINT fk_groupinvoice_invoice_fk_groupinvoice FOREIGN KEY (fk_groupinvoice) REFERENCES llx_groupinvoice (rowid);
ALTER TABLE llx_groupinvoice_invoice ADD CONSTRAINT fk_groupinvoice_invoice_fk_invoice FOREIGN KEY (fk_invoice) REFERENCES llx_facture (rowid);
