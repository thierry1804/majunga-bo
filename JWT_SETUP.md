# Configuration JWT

## Configuration pour HMAC (HS256)

Avec l'algorithme HS256, vous n'avez pas besoin de générer des clés RSA. Une simple clé secrète suffit.

### Étape 1 : Créer ou modifier le fichier `.env.local`

Créez un fichier `.env.local` à la racine du projet avec le contenu suivant :

```env
###> lexik/jwt-authentication-bundle ###
JWT_SECRET_KEY=wybzpMCc0dBbxgGpd+NA/Fj5hYNj523rd96dJY+NORSdFzyodd7m5LpdHkpjm/6G6P5ee7jDlUg6ycEZ46lAMQ==
JWT_PASSPHRASE=
###< lexik/jwt-authentication-bundle ###
```

**Important :** Pour la production, générez une nouvelle clé secrète avec :
```bash
php -r "echo base64_encode(random_bytes(64));"
```

### Étape 2 : Vider le cache

```bash
php bin/console cache:clear
```

### Étape 3 : Tester l'authentification

1. **Inscription** : `POST /api/register`
   ```json
   {
     "email": "user@example.com",
     "password": "password123"
   }
   ```

2. **Connexion** : `POST /api/login`
   ```json
   {
     "email": "user@example.com",
     "password": "password123"
   }
   ```

3. **Utiliser le token** : Ajoutez l'en-tête `Authorization: Bearer <token>` pour accéder aux routes protégées.

## Note

Si vous préférez utiliser RSA (RS256) au lieu de HMAC, vous devrez :
1. Installer l'extension OpenSSL PHP
2. Générer les clés avec : `php bin/console lexik:jwt:generate-keypair`
3. Modifier `signature_algorithm` à `RS256` dans `config/packages/lexik_jwt_authentication.yaml`

