# Bonnes Pratiques de Sécurité Implémentées

Ce document décrit les bonnes pratiques de sécurité mises en place dans l'application.

## ✅ Implémentations

### 1. Durée de vie des tokens optimisée

- **Access Token** : 30 minutes (1800 secondes)
- Configuration : `config/packages/lexik_jwt_authentication.yaml`
- **Avantage** : Limite l'exposition en cas de vol de token

### 2. Endpoints de validation et renouvellement

#### `/api/me` (GET)
- **Usage** : Valider la validité du token et obtenir les informations de l'utilisateur
- **Authentification** : Requise (JWT)
- **Réponse** :
  ```json
  {
    "valid": true,
    "user": {
      "id": 1,
      "email": "user@example.com",
      "roles": ["ROLE_USER"]
    }
  }
  ```

#### `/api/refresh` (POST)
- **Usage** : Obtenir un nouveau token sans se reconnecter
- **Authentification** : Requise (JWT valide)
- **Réponse** :
  ```json
  {
    "token": "nouveau_token_jwt",
    "user": {
      "id": 1,
      "email": "user@example.com"
    }
  }
  ```

### 3. Rate Limiting

#### Protection contre les attaques par force brute

**Login** (`/api/login`) :
- **Limite** : 5 tentatives par minute
- **Configuration** : `config/packages/framework.yaml` et `config/packages/security.yaml`
- **Comportement** : Blocage temporaire après 5 échecs

**Register** (`/api/register`) :
- **Limite** : 3 tentatives par heure
- **Protection** : Contre la création massive de comptes

### 4. Configuration de sécurité

- **HTTPS recommandé** : En production, utilisez toujours HTTPS
- **CORS configuré** : `config/packages/nelmio_cors.yaml`
- **Firewall stateless** : Pas de session côté serveur pour l'API

## 🔄 Workflow recommandé

### 1. Connexion initiale
```bash
POST /api/login
{
  "email": "user@example.com",
  "password": "password123"
}
```

### 2. Utilisation du token
- Stocker le token en mémoire (JavaScript) ou dans un httpOnly cookie
- Utiliser le token dans l'en-tête : `Authorization: Bearer <token>`

### 3. Vérification périodique
```bash
GET /api/me
Authorization: Bearer <token>
```

### 4. Renouvellement avant expiration
```bash
POST /api/refresh
Authorization: Bearer <token>
```

## 📋 Bonnes pratiques à suivre

### Côté Client

1. **Stockage du token** :
   - ✅ Mémoire JavaScript (perdu au rechargement)
   - ✅ httpOnly cookie (pour refresh token)
   - ❌ localStorage (vulnérable au XSS)
   - ❌ sessionStorage (perdu à la fermeture)

2. **Gestion de l'expiration** :
   - Vérifier régulièrement avec `/api/me`
   - Renouveler avec `/api/refresh` avant expiration
   - Gérer la déconnexion automatique si le token est expiré

3. **Sécurité** :
   - Ne jamais exposer le token dans les logs
   - Utiliser HTTPS en production
   - Implémenter une déconnexion automatique après inactivité

### Côté Serveur

1. **Clés secrètes** :
   - Stocker dans `.env.local` (non versionné)
   - Utiliser des clés différentes par environnement
   - Rotation régulière des clés

2. **Monitoring** :
   - Surveiller les tentatives de connexion échouées
   - Logger les accès aux endpoints sensibles
   - Alerter en cas d'activité suspecte

## 🚀 Améliorations futures possibles

1. **Refresh Token** :
   - Implémenter un système de refresh token séparé
   - Durée de vie plus longue (7-30 jours)
   - Stockage en base de données pour révocation

2. **Blacklist de tokens** :
   - Système de révocation des tokens
   - Invalidation lors de la déconnexion
   - Invalidation lors du changement de mot de passe

3. **2FA (Two-Factor Authentication)** :
   - Authentification à deux facteurs
   - SMS, Email, ou TOTP

4. **OAuth2 / OpenID Connect** :
   - Support des providers externes
   - Google, Facebook, GitHub, etc.

## 📚 Références

- [OWASP JWT Security Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/JSON_Web_Token_for_Java_Cheat_Sheet.html)
- [Symfony Security Documentation](https://symfony.com/doc/current/security.html)
- [Lexik JWT Authentication Bundle](https://github.com/lexik/LexikJWTAuthenticationBundle)

