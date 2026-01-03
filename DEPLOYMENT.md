# Guide de Déploiement en Production

Ce guide explique comment configurer le déploiement automatique de l'application en production via GitHub Actions et FTP.

## Prérequis

- Un dépôt GitHub configuré
- Un serveur FTP avec accès en production
- Les informations de connexion FTP (serveur, utilisateur, mot de passe, répertoire)

## Configuration des Secrets GitHub

Pour que le workflow GitHub Actions puisse se connecter à votre serveur FTP, vous devez configurer les secrets suivants dans l'**environnement "production"** de votre dépôt GitHub :

1. Allez dans votre dépôt GitHub
2. Cliquez sur **Settings** (Paramètres)
3. Dans le menu de gauche, cliquez sur **Environments**
4. Cliquez sur **production** (ou créez-le s'il n'existe pas)
5. Dans la section **Environment secrets**, cliquez sur **Add environment secret** pour chaque secret suivant :

### Secrets à configurer

| Nom du Secret | Description | Exemple |
|--------------|-------------|---------|
| `FTP_SERVER` | Adresse du serveur FTP | `ftp.monserveur.com` ou `192.168.1.1` |
| `FTP_USERNAME` | Nom d'utilisateur FTP | `mon_utilisateur` |
| `FTP_PASSWORD` | Mot de passe FTP | `mon_mot_de_passe` |
| `FTP_REMOTE_DIR` | Répertoire de destination sur le serveur | `/public_html` ou `/www` ou `/htdocs` |

### Exemple de configuration

```
FTP_SERVER: ftp.example.com
FTP_USERNAME: monuser
FTP_PASSWORD: MonMotDePasse123!
FTP_REMOTE_DIR: /public_html
```

**Important** : Ces secrets doivent être configurés dans l'environnement "production", pas comme des secrets de dépôt. Le workflow utilise `environment: production` pour accéder à ces secrets.

## Déclenchement du Déploiement

Le déploiement se déclenche automatiquement dans les cas suivants :

1. **Push sur les branches principales** : Lors d'un push sur `main` ou `master`
2. **Déclenchement manuel** : Via l'onglet "Actions" de GitHub, vous pouvez déclencher le workflow manuellement

## Processus de Déploiement

Le workflow effectue les étapes suivantes :

1. ✅ Vérification du code source
2. ✅ Configuration de PHP 8.2
3. ✅ Installation des dépendances Composer (mode production)
4. ✅ Optimisation du cache Symfony
5. ✅ Création d'une archive ZIP complète du projet (sauf fichiers sensibles)
6. ✅ Upload de l'archive `deployment.zip` et du script `extract-deployment.php` via FTP
7. ✅ Nettoyage des fichiers temporaires

**Avantages de cette approche :**
- ✅ Un seul fichier à transférer (pas de timeout)
- ✅ Plus rapide et plus fiable
- ✅ Plus simple à gérer
- ✅ Tous les fichiers sont inclus (vendor, config, etc.)

## Configuration du Serveur de Production

### 1. Configuration PHP

Assurez-vous que votre serveur a :
- PHP >= 8.2
- Les extensions suivantes : `ctype`, `iconv`, `pdo`, `pdo_pgsql`
- Composer installé (si vous n'incluez pas `vendor` dans le déploiement)

### 2. Variables d'Environnement

Sur votre serveur de production, créez un fichier `.env.local` avec les variables suivantes :

```env
APP_ENV=prod
APP_DEBUG=false
APP_SECRET=votre_secret_ici

# Base de données
DATABASE_URL="postgresql://user:password@localhost:5432/dbname?serverVersion=16&charset=utf8"

# Routing
DEFAULT_URI="https://votre-domaine.com"

# JWT (si vous utilisez JWT)
JWT_SECRET_KEY=%kernel.project_dir%/config/jwt/private.pem
JWT_PUBLIC_KEY=%kernel.project_dir%/config/jwt/public.pem
JWT_PASSPHRASE=votre_passphrase

# Mailer (si vous utilisez l'envoi d'emails)
MAILER_DSN=smtp://user:pass@smtp.example.com:587
MAILER_FROM_EMAIL="noreply@votre-domaine.com"
MAILER_FROM_NAME="Votre Application"

# Token de sécurité pour le script de déploiement (optionnel mais recommandé)
DEPLOY_TOKEN=votre_token_secret_ici
```

### 3. Clés JWT

Si vous utilisez JWT, vous devez générer les clés sur le serveur de production :

```bash
php bin/console lexik:jwt:generate-keypair
```

Ou copier les clés depuis votre environnement de développement vers le serveur.

### 4. Permissions

Assurez-vous que les répertoires suivants sont accessibles en écriture :

```bash
chmod -R 775 var/
chmod -R 775 public/
```

### 5. Configuration du Serveur Web

#### Apache

Les fichiers `.htaccess` sont déjà inclus dans le projet et seront déployés automatiquement :

- **`.htaccess` à la racine** : Redirige toutes les requêtes vers `public/`
- **`public/.htaccess`** : Configuration Symfony standard

Assurez-vous que le module `mod_rewrite` est activé sur votre serveur Apache.

#### Nginx

Configuration Nginx exemple :

```nginx
server {
    listen 80;
    server_name votre-domaine.com;
    root /chemin/vers/votre/projet/public;

    location / {
        try_files $uri /index.php$is_args$args;
    }

    location ~ ^/index\.php(/|$) {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_split_path_info ^(.+\.php)(/.*)$;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        fastcgi_param DOCUMENT_ROOT $realpath_root;
    }
}
```

## Installation et Déploiement Automatique

**Le workflow crée une archive ZIP complète** (`deployment.zip`) contenant tout le projet (sauf les fichiers sensibles comme `.env`). Un script de déploiement automatique est inclus pour tout configurer.

### Déploiement Automatique

Le script `deploy.php` automatise complètement le déploiement :
1. ✅ Extrait l'archive `deployment.zip`
2. ✅ Crée les dossiers nécessaires (`var/cache`, `var/log`, `var/sessions`)
3. ✅ Exécute les migrations de base de données
4. ✅ Vide et optimise le cache Symfony
5. ✅ Nettoie les fichiers temporaires (supprime l'archive et le script)

#### Méthode 1 : Via le navigateur (Recommandé)

1. **Configurez un token de sécurité** dans `.env.local` :
   ```env
   DEPLOY_TOKEN=votre_token_secret_ici
   ```

2. **Accédez à** : `https://api.madabooking.mg/deploy.php?token=votre_token_secret_ici`

3. Le script exécute automatiquement toutes les étapes et se supprime à la fin.

#### Méthode 2 : Via SSH (si disponible)

```bash
cd /chemin/vers/votre/projet
php deploy.php
```

Le script se supprime automatiquement après exécution.

**Sécurité :** Le script se supprime automatiquement après utilisation. Si vous l'appelez via le navigateur, configurez toujours `DEPLOY_TOKEN` dans `.env.local` pour protéger l'accès.

## Configuration Apache

Le projet inclut deux fichiers `.htaccess` :

1. **`.htaccess` à la racine** : Redirige toutes les requêtes vers le dossier `public/`
2. **`public/.htaccess`** : Configuration Symfony standard pour le routage

Ces fichiers sont automatiquement déployés avec le workflow. Si vous obtenez une erreur 403, vérifiez que :
- Le module `mod_rewrite` est activé sur votre serveur Apache
- Les fichiers `.htaccess` sont bien présents sur le serveur
- Les permissions des fichiers sont correctes

## Vérification Post-Déploiement

Après chaque déploiement, si vous avez accès SSH, connectez-vous sur votre serveur et exécutez les commandes suivantes :

1. ✅ **Migrations de base de données** :
   ```bash
   php bin/console doctrine:migrations:migrate --no-interaction
   ```

2. ✅ **Optimisation du cache** :
   ```bash
   php bin/console cache:clear --env=prod
   php bin/console cache:warmup --env=prod
   ```

3. ✅ **Vérification de l'application** : Vérifiez que l'application est accessible via votre navigateur (ex: https://api.madabooking.mg/)

## Diagnostic et Dépannage

### Script de Diagnostic

Un script de diagnostic est disponible pour identifier les problèmes de configuration :

1. **Accédez à :** `https://api.madabooking.mg/diagnostic.php`
2. Le script vérifie automatiquement :
   - ✅ Version PHP et extensions requises
   - ✅ Fichiers essentiels présents
   - ✅ Permissions des dossiers
   - ✅ Configuration `.env.local`
   - ✅ Clés JWT
   - ✅ Connexion à la base de données
   - ✅ Logs d'erreur récents

3. **IMPORTANT :** Supprimez le fichier `diagnostic.php` après utilisation pour des raisons de sécurité.

### Erreur de connexion FTP (ECONNRESET ou timeout 900 secondes)

Si vous rencontrez l'erreur `Error: Client is closed because read ECONNRESET` ou `421 No transfer timeout (900 seconds)`, cela signifie que la connexion FTP a été interrompue ou que le timeout du serveur a été atteint.

**Solution actuelle :** Le workflow utilise maintenant une archive compressée (`vendor.tar.gz`) au lieu de transférer ~6765 fichiers individuellement. Cela réduit considérablement le temps de transfert et évite les timeouts.

**Si le problème persiste :**

1. **Vérifier les permissions FTP** : Assurez-vous que l'utilisateur FTP a les permissions nécessaires pour créer des dossiers et transférer des fichiers.

2. **Vérifier la configuration du serveur FTP** :
   - Le serveur doit accepter les connexions depuis GitHub Actions
   - Certains serveurs nécessitent le mode passif FTP (activé par défaut dans l'action)
   - Vérifiez les limites de timeout du serveur FTP (actuellement 900 secondes)

3. **Alternative : Exclure vendor** : Si vous avez accès SSH et Composer sur le serveur, vous pouvez modifier le workflow pour exclure `vendor` et l'installer directement sur le serveur après le déploiement.

4. **Logs détaillés** : Le workflow utilise `log-level: verbose` pour fournir plus d'informations en cas d'erreur.

### Autres erreurs de connexion FTP

- Vérifiez que les secrets GitHub sont correctement configurés dans l'environnement "production"
- Vérifiez que le serveur FTP accepte les connexions depuis GitHub Actions
- Certains serveurs nécessitent un mode passif FTP (déjà activé par défaut)

### Erreurs de permissions

- Vérifiez les permissions des répertoires `var/` et `public/`
- Assurez-vous que le serveur web peut écrire dans ces répertoires

### Erreurs de dépendances

- Si vous utilisez l'option 1 (vendor inclus), vérifiez que tous les fichiers sont bien uploadés
- Si vous utilisez l'option 2, exécutez `composer install` sur le serveur

## Sécurité

⚠️ **Important** : Ne commitez jamais vos fichiers `.env` ou vos clés JWT dans le dépôt Git. Utilisez toujours les secrets GitHub pour les informations sensibles.

## Support

Pour toute question ou problème, consultez :
- [Documentation Symfony](https://symfony.com/doc/current/deployment.html)
- [Documentation GitHub Actions](https://docs.github.com/en/actions)

