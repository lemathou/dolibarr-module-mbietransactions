CREATE TABLE `llx_paiement_extrafields` (
  `rowid` int(11) NOT NULL,
  `tms` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `fk_object` int(11) NOT NULL,
  `import_key` varchar(14) DEFAULT NULL,
  `fk_module` int(11) DEFAULT NULL,
  `fk_module_oid` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE `llx_paiement_extrafields`
  ADD PRIMARY KEY (`rowid`),
  ADD UNIQUE KEY `fk_object` (`fk_object`),
  ADD KEY `fk_module` (`fk_module`,`fk_module_oid`);

ALTER TABLE `llx_paiement_extrafields`
  MODIFY `rowid` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `llx_paiement_extrafields`
  DROP FOREIGN KEY `llx_paiement_object_ibfk_1`;
ALTER TABLE `llx_paiement_extrafields`
  ADD CONSTRAINT `llx_paiement_object_ibfk_1`
  FOREIGN KEY (`fk_object`)
  REFERENCES `llx_paiement`(`rowid`)
  ON DELETE CASCADE ON UPDATE CASCADE;