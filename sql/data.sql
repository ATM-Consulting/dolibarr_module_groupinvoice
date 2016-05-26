DELETE FROM llx_c_actioncomm WHERE code LIKE 'AC_GRPINV%';

INSERT INTO llx_c_actioncomm (id, code, type, libelle, module, active, todo, position) VALUES (1030861, 'AC_GRPINV_S', 'groupinvoice', 'Send invoice statement by mail', 'groupinvoice', 1, NULL, 10);
