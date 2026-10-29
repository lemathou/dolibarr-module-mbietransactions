# MBIETransactions — Paiement Monetico (MBE) : transactions CB, retours et rapprochement.

> Module Dolibarr **MBIETransactions** (version 1.3.0), branche `20.0`.

## Fonctionnalités

### Fonctionnalités principales

- Paiement CB Monetico (`payment.php`) et pages de retour (`accepted.php`, `canceled.php`, `refused.php`, `retour.php`).
- Journal des retours et rapprochement, triggers e-mail, hooks facture/commande/devis/paiement/tiers.
- Onglet Paiements et liens de paiement sécurisés.

### Pages

- `accepted.php`
- `admin/about.php`
- `admin/setup.php`
- `canceled.php`
- `payment.php`
- `refused.php`
- `retour.php`

### Permissions

- `mbietransactions.pay_solde_mult.write` — MBIETransactionsPaymentSoldeMultOK

### Configuration

| Constante | Type | Rôle |
|---|---|---|
| `MBIETRANSACTIONS_TEST` | yesno | Mode Test |
| `MBIETRANSACTIONS_TEST_INFOS` | separator | Configuration du mode test |
| `MBIETRANSACTIONS_TEST_RANK` | string | Numéro de rang en mode Test |
| `MBIETRANSACTIONS_TEST_ID` | string | Identifiant E-Transactions/Paybox en mode Test |
| `MBIETRANSACTIONS_TEST_SHOP_ID` | string | Numéro de site en mode Test |
| `MBIETRANSACTIONS_TEST_KEY` | string | Clé publique en mode Test |
| `MBIETRANSACTIONS_PROD_INFOS` | separator | Configuration du mode production |
| `MBIETRANSACTIONS_RANK` | string | Numéro de rang |
| `MBIETRANSACTIONS_ID` | string | Identifiant E-Transactions/Paybox |
| `MBIETRANSACTIONS_SHOP_ID` | string | Numéro de site |
| `MBIETRANSACTIONS_KEY` | string | Clé publique |
| `MBIETRANSACTIONS_OPTIONS` | separator | Options |
| `MBIETRANSACTIONS_DELIVERY_RECEIPT_EMAIL` | yesno | Accusé de réception |
| `MBIETRANSACTIONS_CC_EMAILS` | string | Adresses en copie des emails |
| `MBIETRANSACTIONS_NOTIFICATION_FROM_EMAIL` | string | Email expéditeur des notifications |
| `MBIETRANSACTIONS_NOTIFICATION_EMAIL` | string | Email de réception des notifications |
| `MBIETRANSACTIONS_WEBSITE_CONTACT_URL` | string | URL de contact sur le site web |
| `MBIETRANSACTIONS_BANK_ACCOUNT` | string | Compte bancaire à créditer |
| `MBIETRANSACTIONS_SUBJECT_PAYMENT_MAIL` | string | Sujet du mail de paiement |
| `MBIETRANSACTIONS_TEMPLATE_PAYMENT_MAIL` | string | Modèle du mail de paiement |
| `MBIETRANSACTIONS_SUBJECT_PAID_INVOICE_MAIL` | string | Sujet du mail avec facture acquitée |
| `MBIETRANSACTIONS_TEMPLATE_PAID_INVOICE_MAIL` | string | Modèle du mail de la facture acquitée |
| `MBIETRANSACTIONS_RESTE_USE_CREDITS_DEPOSITS` | yesno | Déduire les crédits dispo et acomptes (non imputés) du reste à payer |
| `MBIETRANSACTIONS_MEANS` | separator | Moyens de paiement |
| `MBIETRANSACTIONS_MEANS_PAYPAL` | yesno | PayPal activé |
| `MBIETRANSACTIONS_MEANS_AMEX` | yesno | AMEX Activé |

### Hooks

- Contextes : `invoicecard`, `ordercard`, `propalcard`, `newpayment`, `thirdpartycomm`, `mmipaymentstab`, `mmipaymentslist`
- Callbacks : `addPaymentRows`, `addGlobalPaymentRows`, `doPaymentTabAction`, `render_transaction_object`, `addMoreRecentObjects`, `doCheckStatus`, `addOnlinePaymentMeans`, `doaddButton`, `doValidatePayment`, `getValidPayment`

### Triggers

- `core/triggers/interface_99_modMBIETransactions_ETransactionsSendMail.class.php`

### Classes et composants

- `class/actions_mbietransactions.class.php`
- `class/mmi_etransactions.class.php`
- `lib/create_link.lib.php`
- `lib/mbietransactions.lib.php`

## Prérequis

- Dolibarr >= 8.0 (branche `20.0` : Dolibarr 20.0).
- Modules MMI : `mmicommon`, `mmipayments`. **`mmicommon` doit être activé en premier.**

## Installation

1. Copier le module dans `htdocs/custom/mbietransactions` :

   ```sh
   cd htdocs/custom
   git clone <dépôt> mbietransactions
   ```

2. Dans Dolibarr : **Configuration → Modules**, activer d'abord `mmicommon`, puis ce module.
3. Vérifier les permissions dans la fiche des utilisateurs concernés.

## Licence

GPLv3 ou ultérieure — voir `COPYING`. Documentation sous GFDL.

## Changelog

Voir [CHANGELOG.md](CHANGELOG.md) (évolutions datées).
