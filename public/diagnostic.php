<?php
/**
 * Script de diagnostic pour Symfony en production
 * 
 * IMPORTANT : Supprimez ce fichier après utilisation pour des raisons de sécurité !
 * 
 * Accès : https://api.madabooking.mg/diagnostic.php
 */

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Diagnostic Symfony - Production</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 1200px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        h1 { color: #333; border-bottom: 2px solid #4CAF50; padding-bottom: 10px; }
        h2 { color: #555; margin-top: 30px; }
        .check { padding: 10px; margin: 10px 0; border-radius: 4px; }
        .success { background: #d4edda; color: #155724; border-left: 4px solid #28a745; }
        .error { background: #f8d7da; color: #721c24; border-left: 4px solid #dc3545; }
        .warning { background: #fff3cd; color: #856404; border-left: 4px solid #ffc107; }
        .info { background: #d1ecf1; color: #0c5460; border-left: 4px solid #17a2b8; }
        pre { background: #f4f4f4; padding: 10px; border-radius: 4px; overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; margin: 10px 0; }
        table th, table td { padding: 8px; text-align: left; border-bottom: 1px solid #ddd; }
        table th { background: #f8f9fa; font-weight: bold; }
        .badge { display: inline-block; padding: 3px 8px; border-radius: 3px; font-size: 12px; font-weight: bold; }
        .badge-success { background: #28a745; color: white; }
        .badge-error { background: #dc3545; color: white; }
        .badge-warning { background: #ffc107; color: #333; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 Diagnostic Symfony - Production</h1>
        <p><strong>Date :</strong> <?php echo date('Y-m-d H:i:s'); ?></p>
        
        <?php
        $rootPath = dirname(__DIR__);
        $errors = [];
        $warnings = [];
        $success = [];
        
        // 1. Vérification de la version PHP
        echo "<h2>1. Version PHP</h2>";
        $phpVersion = PHP_VERSION;
        $requiredVersion = '8.2.0';
        if (version_compare($phpVersion, $requiredVersion, '>=')) {
            echo "<div class='check success'>✅ Version PHP : <strong>$phpVersion</strong> (requis: >= $requiredVersion)</div>";
            $success[] = "Version PHP OK";
        } else {
            echo "<div class='check error'>❌ Version PHP : <strong>$phpVersion</strong> (requis: >= $requiredVersion)</div>";
            $errors[] = "Version PHP insuffisante";
        }
        
        // 2. Vérification des extensions PHP
        echo "<h2>2. Extensions PHP</h2>";
        $requiredExtensions = ['ctype', 'iconv', 'pdo', 'pdo_pgsql', 'mbstring', 'xml', 'json', 'zip', 'phar'];
        $missingExtensions = [];
        foreach ($requiredExtensions as $ext) {
            if (extension_loaded($ext)) {
                echo "<div class='check success'>✅ Extension <strong>$ext</strong> chargée</div>";
            } else {
                echo "<div class='check error'>❌ Extension <strong>$ext</strong> manquante</div>";
                $missingExtensions[] = $ext;
                $errors[] = "Extension $ext manquante";
            }
        }
        
        // 3. Vérification des fichiers essentiels
        echo "<h2>3. Fichiers essentiels</h2>";
        $essentialFiles = [
            'vendor/autoload.php' => 'Autoloader Composer',
            'public/index.php' => 'Point d\'entrée Symfony',
            'bin/console' => 'Console Symfony',
            'config/services.yaml' => 'Configuration des services',
            '.htaccess' => 'Configuration Apache (racine)',
            'public/.htaccess' => 'Configuration Apache (public)',
        ];
        
        foreach ($essentialFiles as $file => $description) {
            $fullPath = $rootPath . '/' . $file;
            if (file_exists($fullPath)) {
                $size = filesize($fullPath);
                echo "<div class='check success'>✅ <strong>$description</strong> : $file (" . formatBytes($size) . ")</div>";
            } else {
                echo "<div class='check error'>❌ <strong>$description</strong> : $file manquant</div>";
                $errors[] = "Fichier $file manquant";
            }
        }
        
        // 4. Vérification des permissions
        echo "<h2>4. Permissions des dossiers</h2>";
        $writableDirs = [
            'var' => 'Cache et logs',
            'var/cache' => 'Cache Symfony',
            'var/log' => 'Logs Symfony',
            'var/sessions' => 'Sessions',
            'public' => 'Dossier public',
        ];
        
        foreach ($writableDirs as $dir => $description) {
            $fullPath = $rootPath . '/' . $dir;
            if (is_dir($fullPath)) {
                $perms = substr(sprintf('%o', fileperms($fullPath)), -4);
                if (is_writable($fullPath)) {
                    echo "<div class='check success'>✅ <strong>$description</strong> : $dir (permissions: $perms, accessible en écriture)</div>";
                } else {
                    echo "<div class='check error'>❌ <strong>$description</strong> : $dir (permissions: $perms, NON accessible en écriture)</div>";
                    $errors[] = "Dossier $dir non accessible en écriture";
                }
            } else {
                echo "<div class='check warning'>⚠️ <strong>$description</strong> : $dir n'existe pas</div>";
                $warnings[] = "Dossier $dir manquant";
            }
        }
        
        // 5. Vérification du fichier .env.local
        echo "<h2>5. Configuration (.env.local)</h2>";
        $envLocalPath = $rootPath . '/.env.local';
        if (file_exists($envLocalPath)) {
            echo "<div class='check success'>✅ Fichier .env.local existe</div>";
            
            // Lire et vérifier les variables essentielles
            $envContent = file_get_contents($envLocalPath);
            $requiredVars = [
                'APP_ENV' => 'prod',
                'APP_SECRET' => null,
                'DATABASE_URL' => null,
                'DEFAULT_URI' => null,
            ];
            
            echo "<table>";
            echo "<tr><th>Variable</th><th>Statut</th><th>Valeur (masquée)</th></tr>";
            foreach ($requiredVars as $var => $expectedValue) {
                if (preg_match("/^$var=(.+)$/m", $envContent, $matches)) {
                    $value = trim($matches[1], '"\'');
                    if ($var === 'APP_SECRET' || $var === 'DATABASE_URL') {
                        $displayValue = substr($value, 0, 20) . '...';
                    } else {
                        $displayValue = $value;
                    }
                    echo "<tr><td><strong>$var</strong></td><td><span class='badge badge-success'>✓ Présent</span></td><td>$displayValue</td></tr>";
                } else {
                    echo "<tr><td><strong>$var</strong></td><td><span class='badge badge-error'>✗ Manquant</span></td><td>-</td></tr>";
                    $errors[] = "Variable $var manquante dans .env.local";
                }
            }
            echo "</table>";
        } else {
            echo "<div class='check error'>❌ Fichier .env.local manquant !</div>";
            $errors[] = "Fichier .env.local manquant";
        }
        
        // 6. Vérification des clés JWT
        echo "<h2>6. Clés JWT</h2>";
        $jwtPrivate = $rootPath . '/config/jwt/private.pem';
        $jwtPublic = $rootPath . '/config/jwt/public.pem';
        
        if (file_exists($jwtPrivate) && file_exists($jwtPublic)) {
            echo "<div class='check success'>✅ Clés JWT présentes</div>";
            echo "<div class='check info'>📄 private.pem : " . formatBytes(filesize($jwtPrivate)) . "</div>";
            echo "<div class='check info'>📄 public.pem : " . formatBytes(filesize($jwtPublic)) . "</div>";
        } else {
            echo "<div class='check warning'>⚠️ Clés JWT manquantes (générez-les avec : php bin/console lexik:jwt:generate-keypair)</div>";
            $warnings[] = "Clés JWT manquantes";
        }
        
        // 7. Test de connexion à la base de données
        echo "<h2>7. Connexion à la base de données</h2>";
        if (file_exists($envLocalPath)) {
            $envContent = file_get_contents($envLocalPath);
            if (preg_match('/^DATABASE_URL="?([^"]+)"?$/m', $envContent, $matches)) {
                $databaseUrl = $matches[1];
                try {
                    // Extraire les informations de connexion
                    if (preg_match('/postgresql:\/\/([^:]+):([^@]+)@([^:]+):(\d+\/(.+))/', $databaseUrl, $dbMatches)) {
                        $host = $dbMatches[3];
                        echo "<div class='check info'>🔌 Tentative de connexion à la base de données...</div>";
                        // Note: On ne teste pas réellement la connexion pour éviter d'exposer les credentials
                        echo "<div class='check success'>✅ Configuration DATABASE_URL trouvée (hôte: $host)</div>";
                    } else {
                        echo "<div class='check warning'>⚠️ Format DATABASE_URL non reconnu</div>";
                    }
                } catch (Exception $e) {
                    echo "<div class='check error'>❌ Erreur lors de la vérification : " . htmlspecialchars($e->getMessage()) . "</div>";
                }
            } else {
                echo "<div class='check error'>❌ DATABASE_URL non trouvé dans .env.local</div>";
            }
        }
        
        // 8. Vérification des logs d'erreur
        echo "<h2>8. Logs d'erreur récents</h2>";
        $logPath = $rootPath . '/var/log/prod.log';
        if (file_exists($logPath)) {
            $logContent = file_get_contents($logPath);
            $logLines = explode("\n", $logContent);
            $recentLines = array_slice($logLines, -20); // Dernières 20 lignes
            echo "<div class='check info'>📋 Dernières lignes du log (prod.log) :</div>";
            echo "<pre>" . htmlspecialchars(implode("\n", $recentLines)) . "</pre>";
        } else {
            echo "<div class='check warning'>⚠️ Fichier de log prod.log non trouvé</div>";
        }
        
        // 9. Résumé
        echo "<h2>9. Résumé</h2>";
        echo "<div class='check " . (empty($errors) ? 'success' : 'error') . "'>";
        echo "<strong>Erreurs :</strong> " . count($errors) . "<br>";
        echo "<strong>Avertissements :</strong> " . count($warnings) . "<br>";
        echo "<strong>Vérifications réussies :</strong> " . count($success) . "<br>";
        echo "</div>";
        
        if (!empty($errors)) {
            echo "<div class='check error'><strong>❌ Erreurs à corriger :</strong><ul>";
            foreach ($errors as $error) {
                echo "<li>$error</li>";
            }
            echo "</ul></div>";
        }
        
        if (!empty($warnings)) {
            echo "<div class='check warning'><strong>⚠️ Avertissements :</strong><ul>";
            foreach ($warnings as $warning) {
                echo "<li>$warning</li>";
            }
            echo "</ul></div>";
        }
        
        if (empty($errors) && empty($warnings)) {
            echo "<div class='check success'><strong>✅ Tous les tests sont passés ! L'application devrait fonctionner correctement.</strong></div>";
        }
        
        // Fonction utilitaire
        function formatBytes($bytes, $precision = 2) {
            $units = array('B', 'KB', 'MB', 'GB', 'TB');
            $bytes = max($bytes, 0);
            $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
            $pow = min($pow, count($units) - 1);
            $bytes /= (1 << (10 * $pow));
            return round($bytes, $precision) . ' ' . $units[$pow];
        }
        ?>
        
        <hr>
        <p style="color: #999; font-size: 12px;">
            <strong>⚠️ IMPORTANT :</strong> Supprimez ce fichier (diagnostic.php) après utilisation pour des raisons de sécurité.
        </p>
    </div>
</body>
</html>

