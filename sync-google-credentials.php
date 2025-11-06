<?php
/**
 * Script di utility per sincronizzare le credenziali Google Drive
 * 
 * ISTRUZIONI:
 * 1. Carica questo file via FTP nella root del plugin (dove si trova 747disco-crm.php)
 * 2. Vai su: https://tuosito.it/wp-admin/admin.php?page=disco747-system&sync_credentials=1
 * 3. Oppure esegui questo script direttamente: https://tuosito.it/wp-content/plugins/747disco-crm/sync-google-credentials.php
 * 
 * @package Disco747_CRM
 * @version 1.0.0
 */

// Carica WordPress
$wp_load_path = dirname(dirname(dirname(dirname(__FILE__)))) . '/wp-load.php';
if (file_exists($wp_load_path)) {
    require_once $wp_load_path;
} else {
    die('❌ Impossibile caricare WordPress. Verifica il percorso.');
}

// Verifica permessi admin
if (!current_user_can('manage_options')) {
    wp_die('❌ Accesso negato. Solo amministratori possono eseguire questo script.');
}

// Header
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <title>Sincronizzazione Credenziali Google Drive - 747 Disco CRM</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
            background: #f0f0f1;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        h1 {
            color: #1d2327;
            border-bottom: 2px solid #c28a4d;
            padding-bottom: 10px;
        }
        .status {
            padding: 15px;
            border-radius: 5px;
            margin: 15px 0;
        }
        .success {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }
        .error {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }
        .warning {
            background: #fff3cd;
            border: 1px solid #ffeeba;
            color: #856404;
        }
        .info {
            background: #d1ecf1;
            border: 1px solid #bee5eb;
            color: #0c5460;
        }
        code {
            background: #f8f9fa;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: monospace;
        }
        pre {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            overflow-x: auto;
        }
        .button {
            display: inline-block;
            background: #c28a4d;
            color: white;
            padding: 10px 20px;
            text-decoration: none;
            border-radius: 5px;
            margin-top: 15px;
        }
        .button:hover {
            background: #b8794a;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔧 Sincronizzazione Credenziali Google Drive</h1>
        
        <div class="info">
            <strong>ℹ️ Info:</strong> Questo script sincronizza le credenziali Google Drive tra tutte le chiavi del database WordPress.
        </div>
        
        <?php
        // Esegui sincronizzazione
        if (function_exists('disco747_sync_googledrive_credentials')) {
            echo '<h2>📊 Stato Attuale</h2>';
            
            // Mostra credenziali esistenti
            echo '<h3>Credenziali trovate:</h3>';
            echo '<pre>';
            echo '🔑 disco747_googledrive_client_id: ' . (get_option('disco747_googledrive_client_id') ? '✅ Presente' : '❌ Mancante') . "\n";
            echo '🔑 disco747_googledrive_client_secret: ' . (get_option('disco747_googledrive_client_secret') ? '✅ Presente' : '❌ Mancante') . "\n";
            echo '🔑 disco747_googledrive_redirect_uri: ' . (get_option('disco747_googledrive_redirect_uri') ? '✅ Presente' : '❌ Mancante') . "\n";
            echo '🔑 disco747_googledrive_refresh_token: ' . (get_option('disco747_googledrive_refresh_token') ? '✅ Presente' : '❌ Mancante') . "\n";
            echo "\n";
            echo '🔑 preventivi_googledrive_client_id: ' . (get_option('preventivi_googledrive_client_id') ? '✅ Presente' : '❌ Mancante') . "\n";
            echo '🔑 preventivi_googledrive_refresh_token: ' . (get_option('preventivi_googledrive_refresh_token') ? '✅ Presente' : '❌ Mancante') . "\n";
            echo "\n";
            $gd_creds = get_option('disco747_gd_credentials');
            echo '🔑 disco747_gd_credentials (array): ' . (!empty($gd_creds) && !empty($gd_creds['client_id']) ? '✅ Presente' : '❌ Mancante') . "\n";
            echo '</pre>';
            
            echo '<h2>🔄 Esecuzione Sincronizzazione...</h2>';
            
            $result = disco747_sync_googledrive_credentials();
            
            if ($result['success']) {
                echo '<div class="status success">';
                echo '<strong>✅ Successo!</strong><br>';
                echo $result['message'] . '<br>';
                if (isset($result['is_configured'])) {
                    echo '<br>Stato configurazione: ' . ($result['is_configured'] ? '✅ <strong>CONFIGURATO</strong>' : '❌ Non configurato') . '<br>';
                }
                echo '</div>';
            } else {
                echo '<div class="status error">';
                echo '<strong>❌ Errore</strong><br>';
                echo $result['message'];
                echo '</div>';
            }
            
            // Mostra credenziali dopo sincronizzazione
            echo '<h3>Credenziali dopo sincronizzazione:</h3>';
            echo '<pre>';
            echo '🔑 disco747_googledrive_client_id: ' . (get_option('disco747_googledrive_client_id') ? '✅ Presente' : '❌ Mancante') . "\n";
            echo '🔑 disco747_googledrive_client_secret: ' . (get_option('disco747_googledrive_client_secret') ? '✅ Presente' : '❌ Mancante') . "\n";
            echo '🔑 disco747_googledrive_redirect_uri: ' . (get_option('disco747_googledrive_redirect_uri') ? '✅ Presente' : '❌ Mancante') . "\n";
            echo '🔑 disco747_googledrive_refresh_token: ' . (get_option('disco747_googledrive_refresh_token') ? '✅ Presente' : '❌ Mancante') . "\n";
            echo "\n";
            echo '🔑 preventivi_googledrive_client_id: ' . (get_option('preventivi_googledrive_client_id') ? '✅ Presente' : '❌ Mancante') . "\n";
            echo '🔑 preventivi_googledrive_refresh_token: ' . (get_option('preventivi_googledrive_refresh_token') ? '✅ Presente' : '❌ Mancante') . "\n";
            echo "\n";
            $gd_creds = get_option('disco747_gd_credentials');
            echo '🔑 disco747_gd_credentials (array): ' . (!empty($gd_creds) && !empty($gd_creds['client_id']) ? '✅ Presente' : '❌ Mancante') . "\n";
            echo '</pre>';
            
        } else {
            echo '<div class="status error">';
            echo '<strong>❌ Errore:</strong> Funzione disco747_sync_googledrive_credentials non disponibile.<br>';
            echo 'Assicurati che il file <code>includes/credentials-utils.php</code> sia caricato correttamente.';
            echo '</div>';
        }
        
        // Test configurazione
        if (function_exists('disco747_has_google_credentials')) {
            echo '<h2>🧪 Test Configurazione</h2>';
            $is_configured = disco747_has_google_credentials();
            
            if ($is_configured) {
                echo '<div class="status success">';
                echo '<strong>✅ Google Drive è configurato correttamente!</strong><br>';
                echo 'Tutte le credenziali necessarie sono presenti.';
                echo '</div>';
            } else {
                echo '<div class="status warning">';
                echo '<strong>⚠️ Google Drive non è completamente configurato</strong><br>';
                echo 'Manca il refresh_token. Devi completare l\'autorizzazione OAuth nella pagina Storage.';
                echo '</div>';
            }
        }
        ?>
        
        <h2>📝 Prossimi Passi</h2>
        <div class="info">
            <ol>
                <li>Vai alla pagina <strong>Storage Cloud</strong> del plugin</li>
                <li>Verifica che le credenziali siano visibili</li>
                <li>Se non hai ancora il <strong>Refresh Token</strong>, clicca su "Autorizza Google Drive"</li>
                <li>Completa il flusso OAuth2 con Google</li>
                <li>Il plugin salverà automaticamente il refresh token</li>
            </ol>
        </div>
        
        <a href="<?php echo admin_url('admin.php?page=disco747-storage'); ?>" class="button">
            📁 Vai a Storage Cloud
        </a>
        
        <a href="<?php echo admin_url('admin.php?page=disco747-crm'); ?>" class="button">
            🏠 Torna alla Dashboard
        </a>
    </div>
</body>
</html>
