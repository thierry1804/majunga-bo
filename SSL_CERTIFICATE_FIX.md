# Solution pour l'erreur ERR_CERT_AUTHORITY_INVALID

## Problème
Le serveur Symfony utilise HTTPS avec un certificat auto-signé, ce qui provoque l'erreur `ERR_CERT_AUTHORITY_INVALID` dans le navigateur.

## Solutions

### Solution 1 : Utiliser HTTP en développement (Recommandé)

#### Option A : Modifier l'URL dans le frontend

Dans votre fichier `madabookingApi.ts`, changez l'URL de base :

```typescript
// Avant
const API_BASE_URL = 'https://127.0.0.1:8000';

// Après
const API_BASE_URL = 'http://127.0.0.1:8000';
```

Ou utilisez une variable d'environnement :

```typescript
const API_BASE_URL = process.env.REACT_APP_API_URL || 'http://127.0.0.1:8000';
```

Puis créez un fichier `.env` dans votre projet frontend :
```env
REACT_APP_API_URL=http://127.0.0.1:8000
```

#### Option B : Démarrer le serveur Symfony en HTTP

```bash
# Arrêter le serveur actuel
symfony server:stop

# Démarrer en HTTP (sans TLS)
symfony server:start --no-tls
```

### Solution 2 : Accepter le certificat dans le navigateur

1. Ouvrez `https://127.0.0.1:8000` dans votre navigateur
2. Cliquez sur "Avancé" ou "Advanced"
3. Cliquez sur "Continuer vers 127.0.0.1 (non sécurisé)" ou "Proceed to 127.0.0.1 (unsafe)"
4. Le certificat sera accepté pour cette session

**Note** : Cette solution fonctionne uniquement pour les requêtes du navigateur, pas pour les appels fetch/axios depuis le code JavaScript.

### Solution 3 : Configurer le frontend pour ignorer les erreurs de certificat (DÉVELOPPEMENT UNIQUEMENT)

⚠️ **ATTENTION** : Cette solution ne doit être utilisée qu'en développement !

#### Pour React/Next.js avec fetch :

```typescript
// Dans votre fichier madabookingApi.ts ou un fichier de configuration
const fetchWithIgnoreSSL = async (url: string, options: RequestInit = {}) => {
  // En développement uniquement
  if (process.env.NODE_ENV === 'development') {
    // Utiliser HTTP au lieu de HTTPS
    url = url.replace('https://', 'http://');
  }
  return fetch(url, options);
};
```

#### Pour Node.js/Electron :

Si vous utilisez Node.js ou Electron, vous pouvez désactiver la vérification SSL :

```typescript
// ⚠️ DÉVELOPPEMENT UNIQUEMENT
if (process.env.NODE_ENV === 'development') {
  process.env.NODE_TLS_REJECT_UNAUTHORIZED = '0';
}
```

### Solution 4 : Utiliser un proxy de développement

Si vous utilisez Create React App ou Vite, configurez un proxy dans votre `package.json` ou `vite.config.js` :

#### Pour Create React App (package.json) :
```json
{
  "proxy": "http://127.0.0.1:8000"
}
```

Puis dans votre code, utilisez des URLs relatives :
```typescript
const API_BASE_URL = '/api'; // Au lieu de 'https://127.0.0.1:8000/api'
```

#### Pour Vite (vite.config.js) :
```javascript
export default {
  server: {
    proxy: {
      '/api': {
        target: 'http://127.0.0.1:8000',
        changeOrigin: true,
        secure: false
      }
    }
  }
}
```

## Recommandation

Pour le développement local, utilisez **HTTP** au lieu de HTTPS. C'est plus simple et évite tous les problèmes de certificat.

Pour la production, utilisez toujours HTTPS avec un certificat valide (Let's Encrypt, etc.).

## Vérification

Après avoir appliqué une solution, testez avec :

```bash
curl http://127.0.0.1:8000/api/login -X POST \
  -H "Content-Type: application/json" \
  -d '{"email":"test@example.com","password":"test"}'
```

Si vous obtenez une réponse (même une erreur d'authentification), c'est que la connexion fonctionne.

