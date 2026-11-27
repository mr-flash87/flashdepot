# FlashDépôt : suivi des dépôts Orange Money entre un distributeur et ses agents

Les agents demandent leurs dépôts en ligne. Le distributeur valide puis fait le transfert lui-même.
Un téléphone Android lit les SMS Orange Money et met à jour **automatiquement** la dette de chaque agent :

- SMS « vous avez transféré … au numéro 44490675 » → **dépôt** : la dette de l'agent augmente, et sa demande passe en « Envoyée » ;
- SMS « Vous avez reçu … du 64318090 » → **remboursement** (total ou partiel) : la dette baisse ;
- un remboursement en **cash** s'enregistre en un clic sur la fiche de l'agent.

```
Agent ──demande──▶ Site FlashDépôt ──notification──▶ Distributeur
                         ▲                              │ compose le transfert (*144#…)
                         │ HTTPS                        ▼
               Téléphone Android ◀──SMS Orange Money── SIM du distributeur
```

| Dossier | Rôle |
|---|---|
| `server/` | Site PHP + MySQL : espace distributeur (`/admin`), espace agent (`/agent`), API du téléphone |
| `android/` | Application Android « FlashDépôt » (Kotlin) qui transmet les SMS Orange Money. L'APK est compilé par GitHub Actions |

## 1. Mettre le site en ligne sur Hostinger

Prérequis : PHP 8.0+, MySQL, HTTPS (certificat SSL gratuit Hostinger).

1. hPanel → **Bases de données MySQL** : créez une base et un utilisateur (notez le nom, l'utilisateur et le mot de passe).
2. hPanel → site → **Avancé → Git** : dépôt `https://github.com/mr-flash87/flashdepot.git`, branche `main`, dossier vide (= `public_html`).
   Tout le repo est copié dans `public_html` : le `.htaccess` de la racine sert le site depuis `server/` et bloque le reste (`.git`, `android`…).
   Activez le **déploiement automatique** (webhook à coller dans GitHub → Settings → Webhooks) : chaque modification de `main` est mise en ligne toute seule.
   (Autre possibilité : FTP avec le workflow « Mise en ligne du site », à lancer à la main, secrets `FTP_SERVER`, `FTP_USERNAME`, `FTP_PASSWORD`, `FTP_DIR`.)
3. Ouvrez `https://votre-site/install.php`, remplissez les accès MySQL et créez le compte du distributeur.
4. Supprimez `install.php` après l'installation (il se bloque de lui-même, mais autant le retirer).

## 2. Installer l'application sur le téléphone du distributeur

1. Récupérez l'APK : GitHub → onglet **Actions** → « Application Android (APK) » → dernier build → **Artifacts** → `flashdepot-apk`.
   Si les secrets FTP sont en place, il est aussi publié sur `https://votre-site/downloads/flashdepot.apk`.
2. Installez-le sur le téléphone qui a la **SIM Orange Money du distributeur**.
3. Sur le site : **Plus → Téléphone → Générer un code**. Dans l'application : adresse du site + code → **Associer**.
4. Acceptez la lecture des SMS et les notifications, et désactivez l'économie de batterie pour l'application
   (sur Tecno, Infinix, itel, Xiaomi : activez aussi le « Démarrage automatique »).
5. Laissez le téléphone branché et connecté à Internet.

Seuls les SMS dont l'expéditeur contient « Orange » sont transmis. Les SMS personnels restent sur le téléphone.

Pour que chaque nouvelle version s'installe par-dessus l'ancienne, créez une clé de signature une fois pour toutes
et mettez-la dans les secrets `ANDROID_KEYSTORE_B64` (fichier .jks en base64, alias `flashdepot`) et `ANDROID_KEYSTORE_PASSWORD`.

## 3. Utilisation

**Distributeur** (`/admin`)
- **Agents → Ajouter** : nom, numéro Orange Money (sert à reconnaître les SMS et à se connecter), code PIN, autres numéros éventuels.
- **Demandes** : valider ou refuser. Après validation, faites le transfert : le SMS confirme la demande tout seul.
  Si un code USSD de dépôt est réglé (Plus → Général), un bouton « Composer » prépare le code. Vous tapez toujours votre code secret vous-même.
- **Fiche agent** : reste à payer, relevé avec le solde après chaque opération, remboursement cash, corrections.
- **Opérations → À attribuer** : argent reçu ou envoyé à un numéro inconnu. Attribuez-le à un agent (le numéro est retenu) ou ignorez-le.
- **Opérations → À vérifier** : SMS dont le solde ne colle pas (SMS manqué ou faux SMS). Vérifiez dans Orange Money avant de confirmer.
- **Telegram** (facultatif) : chaque demande, dépôt et remboursement arrive en message.

**Agent** (`/agent`) : connexion avec son numéro et son code PIN (ce n'est **pas** son code secret Orange Money).
Il voit sa dette, demande un dépôt, suit ses demandes et son relevé.

## Sécurité

- **Expéditeur** : seuls les SMS d'Orange Money sont pris en compte.
- **ID de transaction unique** : un même SMS n'est jamais compté deux fois.
- **Contrôle du solde** : le nouveau solde annoncé doit être l'ancien solde ± le montant. Sinon l'opération est mise « À vérifier » et ne compte pas.
- **Dépôt saisi à la main puis SMS en retard** : rapprochés, jamais comptés deux fois.
- Aucun code secret Orange Money n'est demandé ni stocké. Connexions limitées (8 essais / 15 min), formulaires protégés (CSRF).
