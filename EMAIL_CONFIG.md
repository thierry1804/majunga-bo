# Configuration Email SMTP

Ce document explique comment configurer l'envoi d'emails via SMTP dans l'application.

## Variables d'environnement requises

Ajoutez les variables suivantes dans votre fichier `.env` :

```env
# Configuration SMTP
MAILER_DSN=smtp://username:password@smtp.example.com:587
MAILER_FROM_EMAIL=noreply@example.com
MAILER_FROM_NAME="Mon Application"
```

## Format du DSN SMTP

Le format du DSN SMTP est le suivant :

```
smtp://[username[:password]@]host[:port]
```

### Exemples de configuration

#### Gmail
```env
MAILER_DSN=smtp://votre-email@gmail.com:votre-mot-de-passe-app@smtp.gmail.com:587
MAILER_FROM_EMAIL=votre-email@gmail.com
MAILER_FROM_NAME="Mon Application"
```

**Note** : Pour Gmail, vous devez utiliser un "Mot de passe d'application" et non votre mot de passe habituel.

#### Outlook/Office 365
```env
MAILER_DSN=smtp://votre-email@outlook.com:votre-mot-de-passe@smtp-mail.outlook.com:587
MAILER_FROM_EMAIL=votre-email@outlook.com
MAILER_FROM_NAME="Mon Application"
```

#### Serveur SMTP personnalisé
```env
MAILER_DSN=smtp://username:password@smtp.example.com:587
MAILER_FROM_EMAIL=noreply@example.com
MAILER_FROM_NAME="Mon Application"
```

### Options supplémentaires pour le DSN

Vous pouvez ajouter des options de sécurité :

```env
# Avec TLS
MAILER_DSN=smtp://username:password@smtp.example.com:587?encryption=tls

# Avec SSL
MAILER_DSN=smtp://username:password@smtp.example.com:465?encryption=ssl

# Avec authentification
MAILER_DSN=smtp://username:password@smtp.example.com:587?auth_mode=login
```

## Endpoint API

### POST /api/send-email

Envoie un email via SMTP.

**Authentification** : Requise (JWT)

**Corps de la requête** :
```json
{
  "to": "destinataire@example.com",
  "subject": "Sujet de l'email",
  "body": "Corps du message",
  "isHtml": false,
  "cc": "cc@example.com",
  "bcc": "bcc@example.com"
}
```

**Paramètres** :
- `to` (obligatoire) : Adresse email du destinataire
- `subject` (obligatoire) : Sujet de l'email
- `body` (obligatoire) : Corps du message (texte ou HTML)
- `isHtml` (optionnel) : `true` si le corps est en HTML, `false` par défaut
- `cc` (optionnel) : Adresse email en copie
- `bcc` (optionnel) : Adresse email en copie invisible

**Réponse en cas de succès** (200) :
```json
{
  "message": "Email envoyé avec succès",
  "to": "destinataire@example.com",
  "subject": "Sujet de l'email"
}
```

**Réponse en cas d'erreur** (400) :
```json
{
  "message": "Erreurs de validation",
  "errors": {
    "to": "Le champ \"to\" (destinataire) est obligatoire"
  }
}
```

## Exemple d'utilisation avec cURL

```bash
curl -X POST http://localhost:8000/api/send-email \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer VOTRE_TOKEN_JWT" \
  -d '{
    "to": "destinataire@example.com",
    "subject": "Test d'\''envoi",
    "body": "Ceci est un message de test.",
    "isHtml": false
  }'
```

## Exemple avec HTML

```bash
curl -X POST http://localhost:8000/api/send-email \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer VOTRE_TOKEN_JWT" \
  -d '{
    "to": "destinataire@example.com",
    "subject": "Email HTML",
    "body": "<h1>Bonjour</h1><p>Ceci est un <strong>message HTML</strong>.</p>",
    "isHtml": true
  }'
```

## Dépannage

### Erreur de connexion SMTP

Vérifiez :
1. Les identifiants SMTP sont corrects
2. Le port est correct (587 pour TLS, 465 pour SSL)
3. Le serveur SMTP est accessible depuis votre serveur
4. Le firewall n'bloque pas le port SMTP

### Erreur d'authentification

Vérifiez :
1. Le nom d'utilisateur et le mot de passe sont corrects
2. Pour Gmail, utilisez un "Mot de passe d'application"
3. L'authentification est activée sur le serveur SMTP

### Emails non reçus

Vérifiez :
1. Les emails ne sont pas dans les spams
2. L'adresse email de l'expéditeur est valide
3. Le serveur SMTP n'a pas de restrictions d'envoi

