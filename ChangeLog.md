# CHANGELOG MBIETRANSACTIONS FOR [DOLIBARR ERP CRM](https://www.dolibarr.org)

## 1.0

Initial version

## 1.0.3

Fix : remove bad chars in ref

## 1.2.0

Les transactions eTransactions se greffent sur l'onglet « Paiements » de mmipayments (RM2803/RM2804), au lieu de vivre dans une vue à part.
- Hook `addPaymentRows` (contexte `mmipaymentstab`) : les transactions du tiers, du devis, de la commande ou de la facture affichée sont contribuées à l'onglet — réussites **et** échecs, motif de refus en infobulle, marqueur `NX` pour le multi-échéances. Elles s'y trient avec les règlements des autres moyens de paiement sur une seule chronologie : les trois refus qui précèdent un encaissement se lisent enfin d'un coup d'œil.
- Une transaction aboutie dont le paiement Dolibarr existe **enrichit** la ligne du règlement (`fk_paiement`) au lieu d'ajouter une ligne : pas de doublon.
- Hook `doPaymentTabAction` : l'action « Recréer le paiement » est portée depuis l'onglet (POST + jeton CSRF, contrôle de droits, confirmation, redirection anti-repost).
- `mmi_etransactions::object_transactions($objecttype, $id)` généralise `client_transactions()` (conservé comme raccourci) : les transactions sont désormais consultables au niveau du devis, de la commande et de la facture, plus seulement du tiers.
- Suppression de `societe_etransactions.php`, remplacée par l'onglet ; le lien « voir tout l'historique » du bloc de l'onglet Client y renvoie.
- Hook `addGlobalPaymentRows` (contexte `mmipaymentslist`) : alimente la vue « Paiements à recréer » de la liste globale de mmipayments (RM2805) — les transactions abouties dont le paiement Dolibarr n'existe pas (ou plus), **tous tiers confondus**. Nouveau DAO `mmi_etransactions::missing_payments($filters)`. Jusqu'ici cette question n'avait aucune réponse : il fallait ouvrir les fiches une par une.

## 1.1.0

Évolution (RM1811) : l'historique eTransactions passe de l'onglet top-level à un bloc sur l'onglet Client
- Bloc teaser des dernières transactions eTransactions (réussites et échecs) via le hook `addMoreRecentObjects` (contexte `thirdpartycomm`), avec alerte « paiements à recréer » mise en évidence
- Complémentaire (et non redondant) avec le bloc « Derniers règlements » du module mmipayments : les réussites « normales » (succès, paiement présent, paiement simple) — déjà listées comme règlements par mmipayments — sont **atténuées** ici ; le bloc met en avant ce qui est propre à eTransactions : **échecs**, **paiements à recréer**, et **paiements 3x** (marqueur `NX`)
- Lien « voir tout l'historique » vers la page dédiée `societe_etransactions.php` (conservée, porte toujours l'action de recréation)
- Suppression de l'onglet eTransactions top-level (plus de const de tab à activer au déploiement, seulement le hook)
- Le bloc est rendu **sous** le bloc « Derniers règlements » (rang d'exécution des hooks `priority = 60`, défaut Dolibarr 50) : l'ordre porte le sens, le complément vient après le général
- Chargement du fichier de langue `other` pour l'en-tête de colonne « Objet lié » (sans lui, la clé `LinkedObject` s'affichait brute)

## 1.0.4

Ajout (RM1762) : onglet « eTransactions » sur la fiche tiers
- Historique de toutes les transactions eTransactions du client (réussites et échecs)
- Lien vers le paiement Dolibarr associé (et vers l'objet métier Facture/Commande/Propal)
- Recréation du paiement Dolibarr pour une transaction réussie dont le paiement a été supprimé par erreur

