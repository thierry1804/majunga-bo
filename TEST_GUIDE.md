# Guide de test - GET Users

## Routes disponibles

### Authentification
- `POST /api/login` - Connexion (obtient un token JWT)
- `POST /api/register` - Inscription (crée un compte et obtient un token)
- `GET /api/me` - Valide le token et retourne les infos utilisateur
- `POST /api/refresh` - Renouvelle le token JWT

### Utilisateurs
- `GET /api/users` - Liste tous les utilisateurs (nécessite `ROLE_ADMIN`)
- `GET /api/users/{id}` - Récupère un utilisateur spécifique (nécessite `ROLE_USER`)

## Méthode 1 : Via Swagger UI (Recommandé)

### Endpoints d'authentification

#### Vérifier la validité du token : `GET /api/me`
- Cliquez sur "Try it out"
- Le token sera automatiquement inclus depuis "Authorize"
- Si le token est valide, vous verrez vos informations utilisateur
- Si le token est expiré, vous recevrez une erreur 401

#### Renouveler le token : `POST /api/refresh`
- Cliquez sur "Try it out"
- Le token sera automatiquement inclus depuis "Authorize"
- Vous recevrez un nouveau token valide pour 30 minutes
- **Astuce** : Utilisez cet endpoint avant l'expiration du token actuel

### Endpoints utilisateurs

1. **Accéder à Swagger** : Ouvrez `http://localhost:8000/` dans votre navigateur

2. **Créer un utilisateur admin** (si pas encore fait) :
   ```bash
   php bin/console app:create-admin
   ```

3. **S'authentifier** :
   - Dans Swagger, trouvez l'endpoint `POST /api/login`
   - Cliquez sur "Try it out"
   - Entrez les identifiants :
     ```json
     {
       "email": "admin@example.com",
       "password": "admin123"
     }
   ```
   - Cliquez sur "Execute"
   - **Copiez le token** retourné dans la réponse

4. **Autoriser dans Swagger** :
   - Cliquez sur le bouton **"Authorize"** (en haut à droite)
   - Dans la section "bearerAuth", entrez votre token JWT
   - **Important** : Entrez seulement le token, sans le préfixe "Bearer " (Swagger l'ajoute automatiquement)
   - Cliquez sur "Authorize" puis "Close"

5. **Tester GET /api/users** :
   - Trouvez l'endpoint `GET /api/users`
   - Cliquez sur "Try it out"
   - Cliquez sur "Execute"
   - Vous devriez voir la liste des utilisateurs

## Méthode 2 : Via cURL

### Étape 1 : Se connecter et obtenir le token

```bash
curl -X POST http://localhost:8000/api/login \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@example.com","password":"admin123"}'
```

**Réponse** :
```json
{
  "token": "eyJ0eXAiOiJKV1QiLCJhbGc..."
}
```

### Étape 2 : Utiliser le token pour GET /api/users

```bash
# Avec application/json (format JSON standard)
curl -X GET http://localhost:8000/api/users \
  -H "Authorization: Bearer VOTRE_TOKEN_ICI" \
  -H "Accept: application/json"

# Ou avec application/ld+json (format JSON-LD par défaut d'API Platform)
curl -X GET http://localhost:8000/api/users \
  -H "Authorization: Bearer VOTRE_TOKEN_ICI" \
  -H "Accept: application/ld+json"
```

### Étape 3 : Récupérer un utilisateur spécifique

```bash
# Avec application/json
curl -X GET http://localhost:8000/api/users/1 \
  -H "Authorization: Bearer VOTRE_TOKEN_ICI" \
  -H "Accept: application/json"

# Ou avec application/ld+json
curl -X GET http://localhost:8000/api/users/1 \
  -H "Authorization: Bearer VOTRE_TOKEN_ICI" \
  -H "Accept: application/ld+json"
```

## Méthode 3 : Via Postman ou Insomnia

1. **Créer une requête POST** vers `http://localhost:8000/api/login`
   - Body (JSON) :
     ```json
     {
       "email": "admin@example.com",
       "password": "admin123"
     }
     ```
   - Copiez le token de la réponse

2. **Créer une requête GET** vers `http://localhost:8000/api/users`
   - Headers :
     - `Authorization: Bearer VOTRE_TOKEN_ICI`
     - `Accept: application/json`

## Créer un utilisateur admin

Si vous n'avez pas encore d'utilisateur admin, utilisez la commande :

```bash
php bin/console app:create-admin
```

Ou avec des paramètres personnalisés :

```bash
php bin/console app:create-admin --email=admin@monapp.com --password=monMotDePasse
```

## Exemples de réponses

### GET /api/users (liste)
```json
{
  "hydra:member": [
    {
      "id": 1,
      "email": "admin@example.com"
    },
    {
      "id": 2,
      "email": "user@example.com"
    }
  ],
  "hydra:totalItems": 2
}
```

### GET /api/users/{id}
```json
{
  "id": 1,
  "email": "admin@example.com"
}
```

## Erreurs possibles

- **401 Unauthorized** : Token manquant ou invalide
- **403 Forbidden** : L'utilisateur n'a pas le rôle `ROLE_ADMIN` pour GET /api/users
- **404 Not Found** : L'utilisateur avec cet ID n'existe pas

