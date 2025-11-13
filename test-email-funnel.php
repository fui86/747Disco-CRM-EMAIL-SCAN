<?php
/**
 * Test Email Funnel - 747 Disco CRM
 * 
 * ISTRUZIONI:
 * 1. Copia questo file nella ROOT di WordPress (stessa cartella di wp-config.php)
 * 2. Modifica la riga 19 con la TUA email di test
 * 3. Visita: https://tuosito.it/test-email-funnel.php
 * 4. Controlla la tua casella email
 * 5. CANCELLA questo file dopo il test (per sicurezza)
 */

// Carica WordPress
require_once(__DIR__ . '/wp-load.php');

// ===================================================================
// CONFIGURAZIONE - Modifica qui
// ===================================================================
$EMAIL_TEST = 'tua-email@test.com'; // ← CAMBIA CON LA TUA EMAIL
$STEP_NUMBER = 1; // 1, 2 o 3 (quale template testare)

// 🔐 PASSWORD TEMPORANEA (per sicurezza, modifica questa password!)
$TEST_PASSWORD = 'test747disco'; // ← CAMBIA QUESTA PASSWORD
// ===================================================================

// Verifica password o admin loggato
$is_authenticated = false;

// Check 1: Se loggato come admin WordPress
if (is_user_logged_in() && current_user_can('manage_options')) {
    $is_authenticated = true;
}

// Check 2: Se password corretta tramite URL (?password=xxx)
if (isset($_GET['password']) && $_GET['password'] === $TEST_PASSWORD) {
    $is_authenticated = true;
}

if (!$is_authenticated) {
    die('
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Test Email Funnel - Accesso Richiesto</title>
        <style>
            body { font-family: Arial, sans-serif; padding: 40px; background: #f5f5f5; text-align: center; }
            .box { max-width: 500px; margin: 0 auto; background: white; padding: 40px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
            h1 { color: #c28a4d; margin-bottom: 20px; }
            .error { background: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; margin-bottom: 20px; }
            .info { background: #d1ecf1; color: #0c5460; padding: 15px; border-radius: 5px; margin-top: 20px; text-align: left; }
            code { background: #f8f9fa; padding: 2px 6px; border-radius: 3px; }
        </style>
    </head>
    <body>
        <div class="box">
            <h1>🔒 Accesso Richiesto</h1>
            <div class="error">
                <strong>❌ Accesso negato</strong><br>
                Devi essere loggato come admin WordPress o fornire la password.
            </div>
            <div class="info">
                <strong>💡 Come accedere:</strong><br><br>
                <strong>Opzione 1:</strong> Effettua il login come admin WordPress e poi ricarica questa pagina.<br><br>
                <strong>Opzione 2:</strong> Aggiungi la password nell\'URL:<br>
                <code>?password=' . esc_html($TEST_PASSWORD) . '</code><br><br>
                Esempio:<br>
                <code>https://tuosito.it/test-email-funnel.php?password=' . esc_html($TEST_PASSWORD) . '</code>
            </div>
        </div>
    </body>
    </html>
    ');
}

// Header HTML
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Test Email Funnel - 747 Disco</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 40px; background: #f5f5f5; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #c28a4d; border-bottom: 3px solid #c28a4d; padding-bottom: 15px; }
        .success { background: #d4edda; color: #155724; padding: 15px; border-radius: 5px; border-left: 4px solid #28a745; margin: 20px 0; }
        .error { background: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; border-left: 4px solid #dc3545; margin: 20px 0; }
        .info { background: #d1ecf1; color: #0c5460; padding: 15px; border-radius: 5px; border-left: 4px solid #17a2b8; margin: 20px 0; }
        .code { background: #f8f9fa; padding: 10px; border-radius: 5px; font-family: monospace; white-space: pre-wrap; }
        .btn { background: #c28a4d; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; display: inline-block; margin-top: 10px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🧪 Test Email Funnel - 747 Disco CRM</h1>
        
<?php

// Simula un preventivo con dati di esempio
$preventivo_test = (object) array(
    'id' => 999,
    'preventivo_id' => 'TEST-' . date('Ymd-His'),
    'nome_referente' => 'Mario',
    'cognome_referente' => 'Rossi',
    'nome_cliente' => 'Mario Rossi',
    'email' => $EMAIL_TEST,
    'telefono' => '+39 347 1811119',
    'tipo_evento' => 'Compleanno 18 anni',
    'data_evento' => date('Y-m-d', strtotime('+30 days')), // 30 giorni da oggi
    'numero_invitati' => 80,
    'tipo_menu' => 'Menu 74',
    'importo_totale' => 2500.00,
    'acconto' => 500.00
);

echo "<div class='info'>";
echo "<h3>📧 Invio email di test a:</h3>";
echo "<p><strong>" . esc_html($EMAIL_TEST) . "</strong></p>";
echo "<p><small>Se vuoi cambiare email, modifica la riga 19 di questo file.</small></p>";
echo "</div>";

// Carica template dal database
global $wpdb;
$sequences_table = $wpdb->prefix . 'disco747_funnel_sequences';

$step = $wpdb->get_row($wpdb->prepare(
    "SELECT * FROM {$sequences_table} 
     WHERE funnel_type = 'pre_conferma' AND step_number = %d 
     LIMIT 1",
    $STEP_NUMBER
));

if (!$step) {
    echo "<div class='error'>";
    echo "<h3>❌ Errore: Template non trovato</h3>";
    echo "<p>Il template Step {$STEP_NUMBER} non esiste nel database.</p>";
    echo "<p><strong>Soluzione:</strong> Esegui prima il file <code>fix-funnel-templates.sql</code> per caricare i template.</p>";
    echo "</div>";
    exit;
}

echo "<div class='info'>";
echo "<h3>📄 Template caricato:</h3>";
echo "<ul>";
echo "<li><strong>Step:</strong> {$step->step_number} - {$step->step_name}</li>";
echo "<li><strong>Subject:</strong> {$step->email_subject}</li>";
echo "<li><strong>Body Length:</strong> " . strlen($step->email_body) . " caratteri</li>";
echo "<li><strong>Days Offset:</strong> +{$step->days_offset} giorni</li>";
echo "<li><strong>Send Time:</strong> {$step->send_time}</li>";
echo "</ul>";
echo "</div>";

// Verifica che il body non sia vuoto
if (empty($step->email_body)) {
    echo "<div class='error'>";
    echo "<h3>❌ Errore: Email body vuoto</h3>";
    echo "<p>Il template è vuoto nel database.</p>";
    echo "</div>";
    exit;
}

// Verifica che il body non contenga CSS visibile (tag <style> rimosso ma contenuto rimasto)
if (preg_match('/\.preheader\s*\{/i', $step->email_body) && !preg_match('/<style/i', $step->email_body)) {
    echo "<div class='error'>";
    echo "<h3>⚠️ Warning: Template potrebbe essere corrotto</h3>";
    echo "<p>Il CSS sembra essere presente come testo invece che dentro tag &lt;style&gt;.</p>";
    echo "<p><strong>Soluzione:</strong> Esegui <code>fix-funnel-templates.sql</code> per ricaricare template puliti.</p>";
    echo "</div>";
}

// Invia email
try {
    $funnel_manager = new \Disco747_CRM\Funnel\Disco747_Funnel_Manager();
    $sent = $funnel_manager->send_email_to_customer($preventivo_test, $step);
    
    if ($sent) {
        echo "<div class='success'>";
        echo "<h3>✅ Email inviata con successo!</h3>";
        echo "<p>Controlla la casella: <strong>{$EMAIL_TEST}</strong></p>";
        echo "<p>L'email dovrebbe arrivare tra pochi secondi/minuti.</p>";
        echo "<h4>Cosa verificare nell'email ricevuta:</h4>";
        echo "<ul>";
        echo "<li>✅ Layout nero/oro corretto</li>";
        echo "<li>✅ <strong>NESSUN CSS visibile</strong> come testo all'inizio</li>";
        echo "<li>✅ Tabelle formattate con bordi arrotondati</li>";
        echo "<li>✅ Box omaggi visibile (sfondo giallo)</li>";
        echo "<li>✅ Link WhatsApp funzionanti</li>";
        echo "<li>✅ Placeholder sostituiti (Mario, Compleanno 18 anni, etc.)</li>";
        echo "</ul>";
        echo "</div>";
        
        // Log info
        echo "<div class='info'>";
        echo "<h3>📝 Dettagli tecnici:</h3>";
        echo "<ul>";
        echo "<li><strong>From:</strong> 747 Disco &lt;info@gestionale.747disco.it&gt;</li>";
        echo "<li><strong>Content-Type:</strong> text/html; charset=UTF-8</li>";
        echo "<li><strong>Placeholder sostituiti:</strong></li>";
        echo "</ul>";
        echo "<div class='code'>";
        echo "{{nome}} → Mario\n";
        echo "{{cognome}} → Rossi\n";
        echo "{{tipo_evento}} → Compleanno 18 anni\n";
        echo "{{data_evento}} → " . date('d/m/Y', strtotime($preventivo_test->data_evento)) . "\n";
        echo "{{preventivo_id}} → " . $preventivo_test->preventivo_id . "\n";
        echo "</div>";
        echo "</div>";
        
    } else {
        echo "<div class='error'>";
        echo "<h3>❌ Errore nell'invio dell'email</h3>";
        echo "<p>La funzione wp_mail() ha restituito false.</p>";
        echo "<h4>Possibili cause:</h4>";
        echo "<ul>";
        echo "<li>Configurazione SMTP non corretta</li>";
        echo "<li>Server email non raggiungibile</li>";
        echo "<li>Email bloccata da spam filter</li>";
        echo "</ul>";
        echo "<p><strong>Soluzione:</strong> Controlla il file <code>/wp-content/debug.log</code> per dettagli.</p>";
        echo "</div>";
    }
    
} catch (Exception $e) {
    echo "<div class='error'>";
    echo "<h3>❌ Eccezione PHP:</h3>";
    echo "<p>" . esc_html($e->getMessage()) . "</p>";
    echo "</div>";
}

// Log check
echo "<div class='info'>";
echo "<h3>📊 Controlla i log:</h3>";
echo "<p>File log WordPress: <code>/wp-content/debug.log</code></p>";
echo "<p>Cerca questa riga:</p>";
echo "<div class='code'>[747Disco-Funnel] ✉️ Email inviata a {$EMAIL_TEST}</div>";
echo "</div>";

?>
        
        <hr style="margin: 30px 0;">
        
        <h3>🧪 Testa altri step:</h3>
        <a href="?step=1" class="btn">Test Step 1 (Day +1)</a>
        <a href="?step=2" class="btn">Test Step 2 (Day +2)</a>
        <a href="?step=3" class="btn">Test Step 3 (Day +3)</a>
        
        <div style="margin-top: 30px; padding: 15px; background: #fff3cd; border-left: 4px solid #ffc107; border-radius: 5px;">
            <strong>⚠️ SICUREZZA:</strong> Cancella questo file dopo il test!<br>
            <code>rm test-email-funnel.php</code> o elimina via FTP.
        </div>
        
    </div>
</body>
</html>

<?php
// Leggi parametro step dalla URL
if (isset($_GET['step'])) {
    $STEP_NUMBER = intval($_GET['step']);
    // Ricarica la pagina con il nuovo step
    if ($STEP_NUMBER >= 1 && $STEP_NUMBER <= 3) {
        // Script già eseguito sopra, non serve fare altro
    }
}
?>
