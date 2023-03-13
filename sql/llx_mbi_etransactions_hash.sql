CREATE TABLE IF NOT EXISTS `llx_mbi_etransactions_hash` (
  `rowid` int(11) NOT NULL,
  `objecttype` enum('Facture','Commande','Propal','Client') NOT NULL,
  `fk_object` int(11) NOT NULL,
  `amount` decimal(10,2) DEFAULT NULL,
  `multiple` tinyint(3) UNSIGNED DEFAULT NULL,
  `tag` varchar(32) DEFAULT NULL,
  `hash` varchar(255) NOT NULL,
  `tms` timestamp NOT NULL DEFAULT current_timestamp(),
  `info` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE `llx_mbi_etransactions_hash`
  ADD PRIMARY KEY (`rowid`),
  ADD KEY `objecttype` (`objecttype`,`fk_object`),
  ADD KEY `hash` (`hash`);

ALTER TABLE `llx_mbi_etransactions_hash`
  MODIFY `rowid` int(11) NOT NULL AUTO_INCREMENT;
