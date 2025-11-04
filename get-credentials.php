#!/usr/bin/env php
<?php
/**
 * Script per recuperare le credenziali Google Drive dal database WordPress
 * 
 * UTILIZZO:
 * 1. Carica questo file nella root di WordPress
 * 2. Esegui: php get-credentials.php
 * 
 * OPPURE da browser:
 * https://gestionale.747disco.it/get-credentials.php
 * 
 * ⚠️ IMPORTANTE: Elimina questo file dopo l'uso per sicurezza!
 */

// ============================================================================
// CONFIGURAZIONE
// ============================================================================

// Cerca wp-load.php automaticamente
$wp_load_paths = [
    __DIR__ . '/wp-load.php',
    dirname(__DIR__) . '/wp-load.php',
    dirname(dirname(__DIR__)) . '/wp-load.php',
];

$wp_loaded = false;
foreach ($wp_load_paths as $path) {
    if (file_exists($path)) {
        require_once($path);
        $wp_loaded = true;
        break;
    }
}

if (!$wp_loaded) {
    die("❌ ERRORE: wp-load.php non trovato!\nSposta questo script nella root di WordPress.\n");
}

// ============================================================================
// FUNZIONI DI OUTPUT
// ============================================================================

function is_cli() {
    return php_sapi_name() === 'cli';
}

function output($message, $is_header = false) {
    if (is_cli()) {
        echo $message . "\n";
    } else {
        if ($is_header) {
            echo "<h2>{$message}</h2>";
        } else {
            echo "<p>{$message}</p>";
        }
    }
}

function output_credential($label, $value, $is_secret = false) {
    if (empty($value)) {
        $display = '❌ Non configurato';
    } elseif ($is_secret) {
        $display = '✅ Presente (' . strlen($value) . ' caratteri)';
        if (!is_cli()) {
            $display .= '<br><input type="text" value="' . esc_attr($value) . '" readonly style="width:100%;font-family:monospace;" onclick="this.select();">';
        }
    } else {
        $display = $value;
    }
    
    if (is_cli()) {
        echo str_pad($label . ':', 25) . $display . "\n";
    } else {
        echo "<tr><th style='text-align:left;padding:8px;'>{$label}:</th><td style='padding:8px;'>{$display}</td></tr>";
    }
}

// ============================================================================
// INIZIO OUTPUT
// ============================================================================

if (!is_cli()) {
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8">';
    echo '<title>Credenziali Google Drive - 747 Disco CRM</title>';
    echo '<style>body{font-family:Arial,sans-serif;margin:40px;background:#f5f5f5;} .container{background:white;padding:30px;border-radius:8px;box-shadow:0 2px 4px rgba(0,0,0,0.1);max-width:800px;margin:0 auto;} table{width:100%;border-collapse:collapse;} th,td{border-bottom:1px solid #ddd;padding:12px;} th{background:#f9f9f9;} code{background:#f4f4f4;padding:2px 6px;border-radius:3px;font-size:12px;} .warning{background:#fff3cd;border:1px solid #ffc107;padding:15px;border-radius:4px;margin:20px 0;} .success{background:#d4edda;border:1px solid #28a745;padding:15px;border-radius:4px;margin:20px 0;}</style>';
    echo '</head><body><div class="container">';
}

output("🔐 RECUPERO CREDENZIALI GOOGLE DRIVE", true);
output("========================================", true);
output("");

// ============================================================================
// RECUPERA CREDENZIALI
// ============================================================================

// Nuovo formato (preferito)
$new_credentials = get_option('disco747_gd_credentials', array());

// Vecchio formato (fallback)
$legacy_credentials = array(
    'client_id' => get_option('preventivi_googledrive_client_id', ''),
    'client_secret' => get_option('preventivi_googledrive_client_secret', ''),
    'redirect_uri' => get_option('preventivi_googledrive_redirect_uri', ''),
    'refresh_token' => get_option('preventivi_googledrive_refresh_token', ''),
    'folder_id' => get_option('preventivi_googledrive_folder_id', '')
);

// Altri formati possibili
$disco747_client_id = get_option('disco747_googledrive_client_id', '');
$disco747_client_secret = get_option('disco747_googledrive_client_secret', '');
$disco747_refresh_token = get_option('disco747_googledrive_refresh_token', '');
$disco747_folder_id = get_option('disco747_googledrive_folder_id', '');

// Merge tutte le fonti (priorità: nuovo > disco747 > legacy)
$credentials = array(
    'client_id' => $new_credentials['client_id'] ?? $disco747_client_id ?: $legacy_credentials['client_id'],
    'client_secret' => $new_credentials['client_secret'] ?? $disco747_client_secret ?: $legacy_credentials['client_secret'],
    'redirect_uri' => $new_credentials['redirect_uri'] ?? $legacy_credentials['redirect_uri'],
    'refresh_token' => $new_credentials['refresh_token'] ?? $disco747_refresh_token ?: $legacy_credentials['refresh_token'],
    'folder_id' => $new_credentials['folder_id'] ?? $disco747_folder_id ?: $legacy_credentials['folder_id']
);

// Access token (temporaneo)
$access_token = get_option('disco747_googledrive_access_token', '');
$token_expires = get_option('disco747_googledrive_token_expires', 0);

// Storage type
$storage_type = get_option('disco747_storage_type', 'googledrive');

// ============================================================================
// MOSTRA RISULTATI
// ============================================================================

if (!is_cli()) echo '<table>';

output("📦 INFORMAZIONI GENERALI", true);
output_credential("Storage Attivo", $storage_type);
output_credential("Dominio WordPress", get_site_url());
output("");

output("🔑 CREDENZIALI OAUTH 2.0", true);
output_credential("Client ID", $credentials['client_id'], false);
output_credential("Client Secret", $credentials['client_secret'], true);
output_credential("Redirect URI", $credentials['redirect_uri'], false);
output_credential("Refresh Token", $credentials['refresh_token'], true);
output("");

output("📁 CONFIGURAZIONE DRIVE", true);
output_credential("Folder ID", $credentials['folder_id'], false);
output_credential("Access Token", !empty($access_token) ? 'Presente (temporaneo)' : 'Assente', false);
if (!empty($access_token) && $token_expires > 0) {
    $expires_in = $token_expires - time();
    $expires_str = $expires_in > 0 ? "Scade tra " . round($expires_in / 60) . " minuti" : "SCADUTO";
    output_credential("Scadenza Token", $expires_str, false);
}

if (!is_cli()) echo '</table>';

// ============================================================================
// REDIRECT URI PER SOTTODOMINIO
// ============================================================================

output("");
output("🌐 REDIRECT URI PER SOTTODOMINIO", true);

$current_domain = parse_url(get_site_url(), PHP_URL_HOST);
$new_redirect_uri = admin_url('admin.php?page=disco747-settings&tab=googledrive');

if (!is_cli()) {
    echo '<div class="success">';
    echo '<strong>Redirect URI per questo dominio:</strong><br>';
    echo '<code>' . esc_html($new_redirect_uri) . '</code><br><br>';
    echo '<input type="text" value="' . esc_attr($new_redirect_uri) . '" readonly style="width:100%;padding:10px;font-family:monospace;font-size:14px;" onclick="this.select();">';
    echo '<p style="margin-top:10px;"><strong>👉 Aggiungi questo URI su Google Cloud Console!</strong></p>';
    echo '</div>';
} else {
    output("Redirect URI per questo dominio:");
    output($new_redirect_uri);
    output("");
    output("👉 Copia questo URI e aggiungilo su Google Cloud Console");
}

// ============================================================================
// COMANDI SQL DI BACKUP
// ============================================================================

output("");
output("💾 COMANDI SQL DI BACKUP", true);

if (!is_cli()) echo '<div class="warning">';
output("Salva questi comandi per backup/ripristino:");
output("");

global $wpdb;
$table = $wpdb->options;

if (!empty($credentials['client_id'])) {
    $client_id_sql = $wpdb->prepare(
        "INSERT INTO {$table} (option_name, option_value) VALUES ('disco747_googledrive_client_id', %s) ON DUPLICATE KEY UPDATE option_value=VALUES(option_value);",
        $credentials['client_id']
    );
    if (is_cli()) {
        output($client_id_sql);
    } else {
        echo '<code style="display:block;background:#f4f4f4;padding:10px;margin:5px 0;font-size:11px;overflow-x:auto;">' . esc_html($client_id_sql) . '</code>';
    }
}

if (!empty($credentials['client_secret'])) {
    $client_secret_sql = $wpdb->prepare(
        "INSERT INTO {$table} (option_name, option_value) VALUES ('disco747_googledrive_client_secret', %s) ON DUPLICATE KEY UPDATE option_value=VALUES(option_value);",
        $credentials['client_secret']
    );
    if (is_cli()) {
        output($client_secret_sql);
    } else {
        echo '<code style="display:block;background:#f4f4f4;padding:10px;margin:5px 0;font-size:11px;overflow-x:auto;">' . esc_html($client_secret_sql) . '</code>';
    }
}

if (!empty($credentials['refresh_token'])) {
    $refresh_token_sql = $wpdb->prepare(
        "INSERT INTO {$table} (option_name, option_value) VALUES ('disco747_googledrive_refresh_token', %s) ON DUPLICATE KEY UPDATE option_value=VALUES(option_value);",
        $credentials['refresh_token']
    );
    if (is_cli()) {
        output($refresh_token_sql);
    } else {
        echo '<code style="display:block;background:#f4f4f4;padding:10px;margin:5px 0;font-size:11px;overflow-x:auto;">' . esc_html($refresh_token_sql) . '</code>';
    }
}

if (!is_cli()) echo '</div>';

// ============================================================================
// ISTRUZIONI FINALI
// ============================================================================

output("");
output("📋 PROSSIMI PASSI", true);

if (!is_cli()) echo '<ol style="line-height:2;">';

if (is_cli()) {
    output("1. Copia il Client ID e Client Secret");
    output("2. Vai su Google Cloud Console");
    output("3. Aggiungi il nuovo Redirect URI (vedi sopra)");
    output("4. Su WordPress sottodominio:");
    output("   - Vai in Impostazioni > Google Drive");
    output("   - Incolla Client ID e Secret");
    output("   - Incolla il Redirect URI");
    output("   - Autorizza Google Drive");
    output("   - Copia il Refresh Token");
    output("5. ELIMINA questo file per sicurezza!");
} else {
    echo '<li>Copia il <strong>Client ID</strong> e <strong>Client Secret</strong> qui sopra</li>';
    echo '<li>Vai su <a href="https://console.cloud.google.com/apis/credentials" target="_blank">Google Cloud Console</a></li>';
    echo '<li>Clicca sulle credenziali OAuth 2.0 esistenti</li>';
    echo '<li>Aggiungi il nuovo <strong>Redirect URI</strong> (senza rimuovere il vecchio)</li>';
    echo '<li>Salva su Google Cloud</li>';
    echo '<li>Su WordPress sottodominio:
        <ul>
            <li>Vai in <strong>PreventiviParty → Impostazioni → Google Drive</strong></li>
            <li>Incolla Client ID e Secret</li>
            <li>Incolla il Redirect URI</li>
            <li>Clicca "Autorizza Google Drive"</li>
            <li>Copia il Refresh Token che appare</li>
            <li>Salva</li>
        </ul>
    </li>';
    echo '<li><strong style="color:red;">⚠️ ELIMINA questo file (get-credentials.php) per sicurezza!</strong></li>';
    echo '</ol>';
}

output("");
output("⚠️  SICUREZZA: Elimina questo file dopo l'uso!", true);
output("    rm get-credentials.php");

if (!is_cli()) {
    echo '</div></body></html>';
}

// ============================================================================
// VERIFICA STATO
// ============================================================================

$has_all = !empty($credentials['client_id']) && 
           !empty($credentials['client_secret']) && 
           !empty($credentials['refresh_token']);

if (!$has_all) {
    if (is_cli()) {
        output("");
        output("❌ ATTENZIONE: Alcune credenziali mancano!");
        output("   Configura prima Google Drive sul dominio principale.");
        exit(1);
    } else {
        echo '<div class="warning" style="background:#f8d7da;border-color:#dc3545;color:#721c24;">';
        echo '<strong>❌ ATTENZIONE:</strong> Alcune credenziali mancano!<br>';
        echo 'Configura prima Google Drive sul dominio principale.';
        echo '</div>';
    }
}

exit(0);
