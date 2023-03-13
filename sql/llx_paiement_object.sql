CREATE TABLE IF NOT EXISTS `llx_paiement_object` (
  `rowid` int(11) NOT NULL,
  `fk_paiement` int(11) DEFAULT NULL,
  `objecttype` enum('Commande','Propal','Client') NOT NULL,
  `fk_object` int(11) DEFAULT NULL,
  `amount` double(24,8) DEFAULT 0.00000000,
  `multicurrency_code` varchar(255) DEFAULT NULL,
  `multicurrency_tx` double(24,8) DEFAULT 1.00000000,
  `multicurrency_amount` double(24,8) DEFAULT 0.00000000
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE `llx_paiement_object`
  ADD PRIMARY KEY (`rowid`),
  ADD UNIQUE KEY `uk_paiement_facture` (`fk_paiement`,`objecttype`,`fk_object`) USING BTREE,
  ADD KEY `idx_paiement_facture_fk_paiement` (`fk_paiement`),
  ADD KEY `idx_paiement_facture_fk_facture` (`objecttype`,`fk_object`) USING BTREE;

ALTER TABLE `llx_paiement_object`
  DROP FOREIGN KEY `llx_paiement_object_ibfk_1`;
ALTER TABLE `llx_paiement_object`
  ADD CONSTRAINT `llx_paiement_object_ibfk_1`
  FOREIGN KEY (`fk_paiement`)
  REFERENCES `llx_paiement`(`rowid`)
  ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `llx_paiement_object`
  MODIFY `rowid` int(11) NOT NULL AUTO_INCREMENT;

