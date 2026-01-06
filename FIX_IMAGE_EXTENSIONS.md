# Correction du problème d'extensions d'images (GD/Imagick)

## Diagnostic

Les extensions GD et Imagick sont installées et disponibles en CLI, mais ne sont pas chargées dans le contexte du serveur web.

## Solutions selon votre configuration

### Solution 1 : Si vous utilisez le serveur de développement Symfony

Si vous utilisez `symfony server:start` ou `php bin/console server:start`, redémarrez simplement le serveur :

```bash
# Arrêter le serveur (Ctrl+C)
# Puis relancer
symfony server:start
# ou
php -S 127.0.0.1:8000 -t public
```

### Solution 2 : Si vous utilisez Apache

Redémarrez Apache pour charger les extensions :

```bash
sudo systemctl restart apache2
# ou
sudo service apache2 restart
```

### Solution 3 : Vérifier que les extensions sont activées pour Apache

Les extensions sont déjà activées (fichiers de configuration présents), mais vérifiez :

```bash
# Vérifier les extensions activées pour Apache
php -v  # Utilise le CLI, mais vérifiez avec le script de diagnostic
```

### Solution 4 : Vérifier avec le script de diagnostic

1. Accédez à : `http://127.0.0.1:8000/check-extensions.php`
2. Vérifiez que `gd.loaded` et `imagick.loaded` sont à `true`
3. Vérifiez que `gd.functions.imagewebp` est à `true`

### Solution 5 : Si les extensions ne sont toujours pas chargées

Si après redémarrage les extensions ne sont toujours pas disponibles, installez-les explicitement :

```bash
# Pour Debian/Ubuntu
sudo apt-get update
sudo apt-get install php8.4-gd php8.4-imagick

# Redémarrer le serveur web
sudo systemctl restart apache2
# ou redémarrer le serveur Symfony
```

## Vérification finale

Après avoir appliqué la solution, testez à nouveau l'upload d'image. L'erreur devrait disparaître et vous devriez voir un message de succès.

## Nettoyage

Une fois le problème résolu, vous pouvez supprimer le script de diagnostic :

```bash
rm public/check-extensions.php
```

