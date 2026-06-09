# CHANGELOG MBIETRANSACTIONS FOR [DOLIBARR ERP CRM](https://www.dolibarr.org)

## 1.0

Initial version

## 1.0.3

Fix : remove bad chars in ref

## 1.1.0

Évolution (RM1811) : l'historique eTransactions passe de l'onglet top-level à un bloc sur l'onglet Client
- Bloc teaser des dernières transactions eTransactions (réussites et échecs) via le hook `addMoreRecentObjects` (contexte `thirdpartycomm`), avec alerte « paiements à recréer » mise en évidence
- Complémentaire (et non redondant) avec le bloc « Derniers règlements » du module mmipayments : les réussites « normales » (succès, paiement présent, paiement simple) — déjà listées comme règlements par mmipayments — sont **atténuées** ici ; le bloc met en avant ce qui est propre à eTransactions : **échecs**, **paiements à recréer**, et **paiements 3x** (marqueur `NX`)
- Lien « voir tout l'historique » vers la page dédiée `societe_etransactions.php` (conservée, porte toujours l'action de recréation)
- Suppression de l'onglet eTransactions top-level (plus de const de tab à activer au déploiement, seulement le hook)

## 1.0.4

Ajout (RM1762) : onglet « eTransactions » sur la fiche tiers
- Historique de toutes les transactions eTransactions du client (réussites et échecs)
- Lien vers le paiement Dolibarr associé (et vers l'objet métier Facture/Commande/Propal)
- Recréation du paiement Dolibarr pour une transaction réussie dont le paiement a été supprimé par erreur

