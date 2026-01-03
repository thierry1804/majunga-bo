<?php
/**
 * Script de correction automatique de la configuration
 * 
 * IMPORTANT : Supprimez ce fichier après utilisation pour des raisons de sécurité !
 * 
 * Accès : https://api.madabooking.mg/fix-config.php
 */

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Correction Configuration - Production</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        h1 { color: #333; border-bottom: 2px solid #4CAF50; padding-bottom: 10px; }
        .check { padding: 10px; margin: 10px 0; border-radius: 4px; }
        .success { background: #d4edda; color: #155724; border-left: 4px solid #28a745; }
        .error { background: #f8d7da; color: #721c24; border-left: 4px solid #dc3545; }
        .warning { background: #fff3cd; color: #856404; border-left: 4px solid #ffc107; }
        pre { background: #f4f4f4; padding: 10px; border-radius: 4px; overflow-x: auto; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔧 Correction Configuration - Production</h1>
        
        <?php
        $rootPath = dirname(__DIR__);
        $fixes = [];
        $errors = [];
        
        // 1. Corriger APP_ENV dans .env.local
        echo "<h2>1. Correction de APP_ENV</h2>";
        $envLocalPath = $rootPath . '/.env.local';
        if (file_exists($envLocalPath)) {
            $envContent = file_get_contents($envLocalPath);
            if (preg_match('/^APP_ENV=(.+)$/m', $envContent, $matches)) {
                $currentEnv = trim($matches[1], '"\'');
                if ($currentEnv !== 'prod') {
                    $envContent = preg_replace('/^APP_ENV=(.+)$/m', 'APP_ENV=prod', $envContent);
                    if (file_put_contents($envLocalPath, $envContent)) {
                        echo "<div class='check success'>✅ APP_ENV corrigé : '$currentEnv' → 'prod'</div>";
                        $fixes[] = "APP_ENV corrigé";
                    } else {
                        echo "<div class='check error'>❌ Impossible d'écrire dans .env.local (vérifiez les permissions)</div>";
                        $errors[] = "Impossible de modifier .env.local";
                    }
                } else {
                    echo "<div class='check success'>✅ APP_ENV est déjà configuré sur 'prod'</div>";
                }
            } else {
                echo "<div class='check warning'>⚠️ APP_ENV non trouvé dans .env.local</div>";
            }
        } else {
            echo "<div class='check error'>❌ Fichier .env.local non trouvé</div>";
            $errors[] = ".env.local manquant";
        }
        
        // 2. Créer les dossiers manquants
        echo "<h2>2. Création des dossiers manquants</h2>";
        $requiredDirs = [
            'var/cache' => 'Cache Symfony',
            'var/log' => 'Logs Symfony',
            'var/sessions' => 'Sessions',
        ];
        
        foreach ($requiredDirs as $dir => $description) {
            $fullPath = $rootPath . '/' . $dir;
            if (!is_dir($fullPath)) {
                if (mkdir($fullPath, 0777, true)) {
                    echo "<div class='check success'>✅ Dossier créé : $dir ($description)</div>";
                    $fixes[] = "Dossier $dir créé";
                } else {
                    echo "<div class='check error'>❌ Impossible de créer le dossier : $dir (vérifiez les permissions)</div>";
                    $errors[] = "Impossible de créer $dir";
                }
            } else {
                echo "<div class='check success'>✅ Dossier existe déjà : $dir</div>";
            }
            
            // S'assurer que les permissions sont correctes
            if (is_dir($fullPath)) {
                chmod($fullPath, 0777);
            }
        }
        
        // 3. Vérifier APP_DEBUG
        echo "<h2>3. Vérification de APP_DEBUG</h2>";
        if (file_exists($envLocalPath)) {
            $envContent = file_get_contents($envLocalPath);
            if (preg_match('/^APP_DEBUG=(.+)$/m', $envContent, $matches)) {
                $currentDebug = trim($matches[1], '"\'');
                if (strtolower($currentDebug) !== 'false' && $currentDebug !== '0') {
                    $envContent = preg_replace('/^APP_DEBUG=(.+)$/m', 'APP_DEBUG=false', $envContent);
                    if (file_put_contents($envLocalPath, $envContent)) {
                        echo "<div class='check success'>✅ APP_DEBUG corrigé : '$currentDebug' → 'false'</div>";
                        $fixes[] = "APP_DEBUG corrigé";
                    }
                } else {
                    echo "<div class='check success'>✅ APP_DEBUG est déjà configuré sur 'false'</div>";
                }
            } else {
                // Ajouter APP_DEBUG si absent
                $envContent .= "\nAPP_DEBUG=false\n";
                if (file_put_contents($envLocalPath, $envContent)) {
                    echo "<div class='check success'>✅ APP_DEBUG ajouté : 'false'</div>";
                    $fixes[] = "APP_DEBUG ajouté";
                }
            }
        }
        
        // 4. Résumé
        echo "<h2>4. Résumé</h2>";
        if (!empty($fixes)) {
            echo "<div class='check success'><strong>✅ Corrections effectuées :</strong><ul>";
            foreach ($fixes as $fix) {
                echo "<li>$fix</li>";
            }
            echo "</ul></div>";
        }
        
        if (!empty($errors)) {
            echo "<div class='check error'><strong>❌ Erreurs :</strong><ul>";
            foreach ($errors as $error) {
                echo "<li>$error</li>";
            }
            echo "</ul></div>";
        }
        
        if (empty($fixes) && empty($errors)) {
            echo "<div class='check success'><strong>✅ Aucune correction nécessaire. La configuration est correcte.</strong></div>";
        }
        
        if (!empty($fixes)) {
            echo "<div class='check warning'><strong>⚠️ Important :</strong> Après ces corrections, vous devrez peut-être vider le cache :<br><pre>php bin/console cache:clear --env=prod</pre></div>";
        }
        ?>
        
        <hr>
        <p style="color: #999; font-size: 12px;">
            <strong>⚠️ IMPORTANT :</strong> Supprimez ce fichier (fix-config.php) après utilisation pour des raisons de sécurité.
        </p>
    </div>
</body>
</html>

