# Guide de Déploiement en Production

Ce guide explique comment configurer le déploiement automatique de l'application en production via GitHub Actions et FTP.

## Prérequis

- Un dépôt GitHub configuré
- Un serveur FTP avec accès en production
- Les informations de connexion FTP (serveur, utilisateur, mot de passe, répertoire)

## Configuration des Secrets GitHub

Pour que le workflow GitHub Actions puisse se connecter à votre serveur FTP, vous devez configurer les secrets suivants dans votre dépôt GitHub :

1. Allez dans votre dépôt GitHub
2. Cliquez sur **Settings** (Paramètres)
3. Dans le menu de gauche, cliquez sur **Secrets and variables** > **Actions**
4. Cliquez sur **New repository secret** pour chaque secret suivant :

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
5. ✅ Préparation des fichiers pour le déploiement
6. ✅ Upload des fichiers via FTP
7. ✅ Nettoyage des fichiers temporaires

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

Créez un fichier `.htaccess` dans le répertoire `public/` (si ce n'est pas déjà fait) :

```apache
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteCond %{REQUEST_URI} !^/public/
    RewriteRule ^(.*)$ public/$1 [L]
</IfModule>
```

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

## Options de Déploiement

### Option 1 : Déploiement avec Vendor (Recommandé pour FTP)

Le workflow actuel inclut le dossier `vendor` dans le déploiement. C'est pratique si votre serveur n'a pas Composer installé.

### Option 2 : Installation de Vendor sur le Serveur

Si votre serveur a Composer installé, vous pouvez modifier le workflow pour exclure `vendor` et l'installer directement sur le serveur :

1. Modifiez le workflow pour ne pas copier `vendor`
2. Après le déploiement, connectez-vous en SSH et exécutez :
   ```bash
   composer install --no-dev --optimize-autoloader
   ```

## Vérification Post-Déploiement

Après chaque déploiement, vérifiez :

1. ✅ L'application est accessible
2. ✅ Les migrations de base de données sont à jour :
   ```bash
   php bin/console doctrine:migrations:migrate --no-interaction
   ```
3. ✅ Le cache est optimisé :
   ```bash
   php bin/console cache:clear --env=prod
   php bin/console cache:warmup --env=prod
   ```

## Dépannage

### Erreur de connexion FTP

- Vérifiez que les secrets GitHub sont correctement configurés
- Vérifiez que le serveur FTP accepte les connexions depuis GitHub Actions
- Certains serveurs nécessitent un mode passif FTP

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

