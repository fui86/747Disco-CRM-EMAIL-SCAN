<?php
/**
 * 🔓 SCRIPT EMERGENZA: Sblocca Lock Scansione
 * 
 * ISTRUZIONI:
 * 1. Carica questo file nella root del sito WordPress
 * 2. Vai su: https://gestionale.747disco.it/UNLOCK-NOW.php
 * 3. Vedrai "✅ Lock rilasciato!"
 * 4. RIMUOVI SUBITO questo file dal server
 * 5. Riprova la scansione
 */

// Carica WordPress
require_once(__DIR__ . '/wp-load.php');

// Verifica admin
if (!current_user_can('manage_options')) {
    die('❌ Accesso negato: devi essere amministratore');
}

// Rilascia TUTTI i lock possibili
$deleted_count = 0;

// Lock principale
if (delete_transient('disco747_scan_lock')) {
    $deleted_count++;
    echo "✅ Lock 'disco747_scan_lock' rilasciato<br>";
} else {
    echo "ℹ️ Lock 'disco747_scan_lock' non trovato<br>";
}

// Pulizia manuale dal database (safety)
global $wpdb;

$options_deleted = $wpdb->query("
    DELETE FROM {$wpdb->options} 
    WHERE option_name LIKE '%disco747_scan_lock%'
    OR option_name LIKE '%_transient_disco747_scan_lock%'
    OR option_name LIKE '%_transient_timeout_disco747_scan_lock%'
");

$deleted_count += $options_deleted;

echo "<br><strong>🎉 OPERAZIONE COMPLETATA!</strong><br>";
echo "✅ Totale record eliminati: {$deleted_count}<br><br>";

echo "📋 <strong>PROSSIMI PASSI:</strong><br>";
echo "1. ⚠️ <strong>RIMUOVI QUESTO FILE (UNLOCK-NOW.php) DAL SERVER SUBITO!</strong><br>";
echo "2. Torna alla pagina \"Scansione File Excel\"<br>";
echo "3. Clicca \"Analizza Ora\"<br>";
echo "4. La scansione dovrebbe partire senza errori<br><br>";

echo "🔍 <strong>Verifica nel database:</strong><br>";
$remaining = $wpdb->get_var("
    SELECT COUNT(*) 
    FROM {$wpdb->options} 
    WHERE option_name LIKE '%disco747_scan_lock%'
");

if ($remaining > 0) {
    echo "⚠️ Ancora {$remaining} record lock presenti (potrebbero essere timeout record, normale)<br>";
} else {
    echo "✅ Nessun record lock presente nel database<br>";
}

echo "<br><hr><br>";
echo "<strong style='color: red; font-size: 18px;'>⚠️ IMPORTANTE: ELIMINA QUESTO FILE ADESSO!</strong><br>";
echo "<code>rm /path/to/UNLOCK-NOW.php</code> oppure cancellalo via FTP<br>";
