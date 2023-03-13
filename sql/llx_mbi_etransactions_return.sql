CREATE TABLE IF NOT EXISTS `llx_mbi_etransactions_return` (
  `rowid` int(11) NOT NULL,
  `fk_mbi_etransactions` int(11) NOT NULL,
  `tms` timestamp NOT NULL DEFAULT current_timestamp(),
  `mt` int(11) DEFAULT NULL,
  `auto` varchar(8) DEFAULT NULL,
  `erreur` varchar(8) DEFAULT NULL,
  `trans` varchar(16) DEFAULT NULL,
  `fk_paiement` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE `llx_mbi_etransactions_return`
  ADD PRIMARY KEY (`rowid`),
  ADD KEY `fk_paiement` (`fk_paiement`),
  ADD KEY `fk_mbi_etransactions` (`fk_mbi_etransactions`);

ALTER TABLE `llx_mbi_etransactions_return`
  ADD FOREIGN KEY (`fk_mbi_etransactions`) REFERENCES `llx_mbi_etransactions_hash`(`rowid`),
  ADD FOREIGN KEY (`fk_paiement`) REFERENCES `llx_paiement`(`rowid`);

ALTER TABLE `llx_mbi_etransactions_return`
  MODIFY `rowid` int(11) NOT NULL AUTO_INCREMENT;
