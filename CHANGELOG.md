# Changelog — MBIETransactions

Toutes les évolutions notables, avec dates (ISO).

## [1.3.0] — 2026-10-28

### Modifié
- Migration Dolibarr 20.0 (RM3565).
- Documentation : README complet (fonctionnalités, prérequis, configuration) et CHANGELOG daté (RM3569).

## Historique daté

- 2026-10-28 — Merge 19.0-mmi into 20.0
- 2026-08-25 — Merge branch '2817-liste-globale-des-paiements-sous-onglet' into '19.0-mmi'
- 2026-08-25 — RM2817 — Contribue toutes les tentatives CB à la vue globale, paginées en SQL
- 2026-08-24 — Merge branch '2804-cycle-devis-commande-facture' into '19.0-mmi'
- 2026-08-24 — RM2804 — Suivre le cycle devis / commande / facture transmis par le socle
- 2026-08-24 — Merge branch '2804-mbietransactions-greffer-les-tentatives' into '19.0-mmi'
- 2026-08-24 — RM2804 — Alimenter la vue « Paiements à recréer » de la liste globale
- 2026-08-24 — RM2804 — Greffer les tentatives de paiement CB sur l'onglet Paiements de mmipayments
- 2026-08-24 — Merge branch '1811-etrans-bloc-client' into '19.0-mmi'
- 2026-08-24 — RM1811 — Bloc eTransactions sous le bloc règlements + libellé « Objet lié » traduit
- 2026-06-09 — RM1811 — Teaser eTransactions : réussites normales discrètes, mise en avant échecs/à-recréer/3x
- 2026-06-02 — RM1811 (WIP) — Historique eTransactions sur l'onglet Client + suppression de l'onglet
- 2026-06-01 — Merge branch '1762-etransactions-historique' into '19.0-mmi'
- 2026-06-01 — RM1762 pisceen/dolibarr : onglet eTransactions sur fiche tiers (historique + lien paiement + recréation)
- 2026-01-06 — Fix Ref remove bad chars
- 2026-01-06 — Fix Ref remove bad chars
- 2025-10-08 — Fix payment 3DS using up2pay Formatage() PHP function
- 2025-10-07 — Fix payment 3DS try to use all possible phones
- 2025-10-06 — Fix payment error bad session : add timestamp into ref
- 2025-09-23 — Fix tel etransactions up2Pay 3DSecure NOT international
- 2025-09-23 — Fix tel etransactions up2Pay 3DSecure NOT international
- 2025-08-19 — Fix Use invoice contact as thirdparty if set
- 2025-08-04 — Fix payment balance to be paid on invoice calculation : use deposits only if FACTURE_SUPPLIER_DEPOSITS_ARE_JUST_PAYMENTS set
- 2025-08-04 — Fix remove echo
- 2025-08-04 — Add option to deduct automatically unused credit notes and deposits to invoie payment
