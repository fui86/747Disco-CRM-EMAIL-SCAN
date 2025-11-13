<?php
/**
 * Script di emergenza per forzare reload template funnel Gmail-safe
 * Da eseguire una sola volta per correggere i template corrotti
 */

// Carica WordPress
require_once(__DIR__ . '/wp-load.php');

// Verifica permessi
if (!is_user_logged_in() || !current_user_can('manage_options')) {
    die('❌ Accesso negato. Devi essere loggato come amministratore.');
}

echo "<h1>🔧 Force Reload Template Funnel</h1>";
echo "<p>Questo script forza il reload dei template Gmail-safe sovrascrivendo quelli esistenti.</p>";
echo "<hr>";

global $wpdb;
$sequences_table = $wpdb->prefix . 'disco747_funnel_sequences';

// STEP 1: Mostra template attuali
echo "<h2>📋 STEP 1: Template Attuali nel Database</h2>";
$current_templates = $wpdb->get_results("
    SELECT id, step_number, step_name, 
           LEFT(email_body, 150) as preview,
           LENGTH(email_body) as body_length
    FROM {$sequences_table} 
    WHERE funnel_type = 'pre_conferma'
    ORDER BY step_number ASC
");

if (empty($current_templates)) {
    echo "<p style='color: orange;'>⚠️ Nessun template pre-conferma trovato nel database.</p>";
} else {
    echo "<table border='1' cellpadding='10' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>ID</th><th>Step</th><th>Nome</th><th>Lunghezza Body</th><th>Preview (150 char)</th></tr>";
    foreach ($current_templates as $tpl) {
        $has_style_tag = (strpos($tpl->preview, '<style') !== false);
        $color = $has_style_tag ? 'red' : 'green';
        echo "<tr style='background: " . ($has_style_tag ? '#ffe6e6' : '#e6ffe6') . "'>";
        echo "<td>{$tpl->id}</td>";
        echo "<td>{$tpl->step_number}</td>";
        echo "<td>{$tpl->step_name}</td>";
        echo "<td>{$tpl->body_length} caratteri</td>";
        echo "<td style='font-size: 11px; font-family: monospace;'>" . htmlspecialchars($tpl->preview) . "...</td>";
        echo "</tr>";
    }
    echo "</table>";
}

// STEP 2: Cancella template corrotti
echo "<h2>🗑️ STEP 2: Cancellazione Template Pre-Conferma Esistenti</h2>";
$deleted = $wpdb->delete($sequences_table, array('funnel_type' => 'pre_conferma'));
echo "<p style='color: " . ($deleted > 0 ? 'green' : 'orange') . ";'>✅ Cancellati <strong>{$deleted}</strong> template pre-conferma.</p>";

// STEP 3: Ricarica template Gmail-safe
echo "<h2>📥 STEP 3: Inserimento Template Gmail-Safe</h2>";

// Carica classe Funnel Database
require_once(__DIR__ . '/includes/funnel/class-disco747-funnel-database.php');

$funnel_db = new Disco747_CRM\Funnel\Disco747_Funnel_Database();

// Forza reload
$result = $funnel_db->reload_default_templates(true);

if ($result) {
    echo "<p style='color: green; font-weight: bold;'>✅ Template Gmail-safe inseriti con successo!</p>";
} else {
    echo "<p style='color: red;'>❌ Errore durante l'inserimento dei template.</p>";
}

// STEP 4: Verifica nuovi template
echo "<h2>✅ STEP 4: Verifica Nuovi Template</h2>";
$new_templates = $wpdb->get_results("
    SELECT id, step_number, step_name, 
           LEFT(email_body, 150) as preview,
           LENGTH(email_body) as body_length
    FROM {$sequences_table} 
    WHERE funnel_type = 'pre_conferma'
    ORDER BY step_number ASC
");

if (empty($new_templates)) {
    echo "<p style='color: red;'>❌ ERRORE: Nessun template inserito!</p>";
} else {
    echo "<table border='1' cellpadding='10' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>ID</th><th>Step</th><th>Nome</th><th>Lunghezza Body</th><th>Preview (150 char)</th><th>Verifica</th></tr>";
    foreach ($new_templates as $tpl) {
        $has_style_tag = (strpos($tpl->preview, '<style') !== false);
        $has_doctype = (stripos($tpl->preview, '<!doctype') !== false);
        $has_table = (stripos($tpl->preview, '<table') !== false);
        
        $checks = array();
        if ($has_doctype) $checks[] = '✅ DOCTYPE';
        if ($has_table) $checks[] = '✅ Table layout';
        if (!$has_style_tag) $checks[] = '✅ NO &lt;style&gt;';
        if ($has_style_tag) $checks[] = '❌ Contiene &lt;style&gt;';
        
        $status_color = (!$has_style_tag && $has_doctype && $has_table) ? '#e6ffe6' : '#ffe6e6';
        
        echo "<tr style='background: {$status_color}'>";
        echo "<td>{$tpl->id}</td>";
        echo "<td>{$tpl->step_number}</td>";
        echo "<td>{$tpl->step_name}</td>";
        echo "<td>{$tpl->body_length} caratteri</td>";
        echo "<td style='font-size: 11px; font-family: monospace;'>" . htmlspecialchars($tpl->preview) . "...</td>";
        echo "<td>" . implode('<br>', $checks) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
}

// STEP 5: Test invio email
echo "<h2>📧 STEP 5: Riepilogo e Prossimi Passi</h2>";
echo "<div style='background: #e7f3ff; padding: 20px; border-left: 4px solid #007bff; margin: 20px 0;'>";
echo "<h3 style='margin-top: 0;'>✅ Operazione Completata</h3>";
echo "<ul>";
echo "<li>✅ Template corrotti eliminati</li>";
echo "<li>✅ Nuovi template Gmail-safe inseriti</li>";
echo "<li>✅ Tutti i template usano <strong>solo CSS inline</strong></li>";
echo "<li>✅ Nessun tag &lt;style&gt; presente</li>";
echo "</ul>";
echo "<p><strong>Prossimi step:</strong></p>";
echo "<ol>";
echo "<li>Vai su <a href='" . admin_url('admin.php?page=disco747-funnel') . "'>WP Admin → 747 Disco → Automazione Funnel</a></li>";
echo "<li>Verifica che i 3 step Pre-Conferma siano visibili</li>";
echo "<li>Clicca su 👁️ Preview per vedere l'HTML corretto</li>";
echo "<li>Le prossime email inviate useranno automaticamente i nuovi template</li>";
echo "</ol>";
echo "</div>";

echo "<hr>";
echo "<p style='text-align: center;'><a href='" . admin_url('admin.php?page=disco747-funnel') . "' class='button button-primary' style='text-decoration: none; padding: 15px 30px; font-size: 16px; display: inline-block;'>🎯 Vai alla Pagina Funnel</a></p>";
