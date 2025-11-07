<?php
/**
 * AUTO-UPDATE CALENDARIO MOBILE - 747 Disco CRM
 * 
 * Questo script aggiorna automaticamente il file main-page.php
 * con il CSS mobile compatto.
 * 
 * ISTRUZIONI:
 * 1. Carica QUESTO file nella root di WordPress
 * 2. Visita: https://gestionale.747disco.it/auto-update-calendario.php?secret=747disco2024
 * 3. Lo script aggiornerà automaticamente main-page.php
 * 4. ELIMINA questo file dopo l'uso!
 */

// Sicurezza: Password segreta
define('UPDATE_SECRET', '747disco2024');

if (!isset($_GET['secret']) || $_GET['secret'] !== UPDATE_SECRET) {
    die('❌ Accesso negato. Usa: ?secret=747disco2024');
}

?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Auto-Update Calendario Mobile</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 20px;
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .container {
            max-width: 700px;
            background: white;
            padding: 40px;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        h1 {
            color: #2b1e1a;
            margin-top: 0;
            text-align: center;
        }
        .step {
            background: #f8f9fa;
            border-left: 5px solid #667eea;
            padding: 20px;
            margin: 20px 0;
            border-radius: 8px;
        }
        .step.success {
            border-color: #28a745;
            background: #d4edda;
        }
        .step.error {
            border-color: #dc3545;
            background: #f8d7da;
        }
        .step.warning {
            border-color: #ffc107;
            background: #fff3cd;
        }
        .icon {
            font-size: 32px;
            margin-bottom: 10px;
            display: block;
        }
        button {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 15px 40px;
            border-radius: 10px;
            font-size: 18px;
            font-weight: 700;
            cursor: pointer;
            width: 100%;
            margin-top: 20px;
            transition: transform 0.2s;
        }
        button:hover {
            transform: translateY(-2px);
        }
        button:disabled {
            background: #ccc;
            cursor: not-allowed;
        }
        .code {
            background: #2b2b2b;
            color: #00ff00;
            padding: 15px;
            border-radius: 8px;
            font-family: 'Courier New', monospace;
            overflow-x: auto;
            margin: 10px 0;
            font-size: 12px;
        }
        .warning-box {
            background: #fff3cd;
            border: 2px solid #ffc107;
            padding: 20px;
            border-radius: 10px;
            margin: 20px 0;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔄 Auto-Update Calendario Mobile</h1>
        
        <?php
        
        if (isset($_POST['do_update'])) {
            // Esegui update
            echo '<div class="step">';
            echo '<span class="icon">⚙️</span>';
            echo '<strong>Avvio aggiornamento...</strong>';
            echo '</div>';
            
            // Trova percorso plugin
            $possible_paths = [
                __DIR__ . '/wp-content/plugins/747disco-crm/includes/admin/views/main-page.php',
                __DIR__ . '/includes/admin/views/main-page.php',
                dirname(__DIR__) . '/wp-content/plugins/747disco-crm/includes/admin/views/main-page.php',
                $_SERVER['DOCUMENT_ROOT'] . '/wp-content/plugins/747disco-crm/includes/admin/views/main-page.php',
            ];
            
            $target_file = null;
            foreach ($possible_paths as $path) {
                if (file_exists($path)) {
                    $target_file = $path;
                    break;
                }
            }
            
            if (!$target_file) {
                echo '<div class="step error">';
                echo '<span class="icon">❌</span>';
                echo '<strong>ERRORE:</strong> File main-page.php non trovato!<br><br>';
                echo 'Percorsi cercati:<br>';
                echo '<div class="code">';
                foreach ($possible_paths as $p) {
                    echo htmlspecialchars($p) . "<br>";
                }
                echo '</div>';
                echo '</div>';
            } else {
                // Backup vecchio file
                $backup_file = $target_file . '.backup.' . date('YmdHis');
                if (copy($target_file, $backup_file)) {
                    echo '<div class="step success">';
                    echo '<span class="icon">💾</span>';
                    echo '<strong>Backup creato:</strong><br>';
                    echo '<div class="code">' . htmlspecialchars($backup_file) . '</div>';
                    echo '</div>';
                } else {
                    echo '<div class="step warning">';
                    echo '<span class="icon">⚠️</span>';
                    echo '<strong>Impossibile creare backup</strong> (continuo comunque...)';
                    echo '</div>';
                }
                
                // Leggi file attuale
                $current_content = file_get_contents($target_file);
                
                // Applica le modifiche CSS
                $updates_applied = 0;
                
                // Update 1: Aggiungi cache buster se non presente
                if (strpos($current_content, 'Cache Buster:') === false) {
                    $current_content = str_replace(
                        '</style>',
                        '</style>' . "\n\n<!-- Cache Buster: <?php echo time(); ?> -->\n<script>\n// Force CSS refresh on mobile devices\n(function() {\n    if (window.innerWidth <= 768) {\n        setTimeout(function() {\n            const calendario = document.getElementById('calendario-eventi');\n            if (calendario) {\n                calendario.style.display = 'none';\n                calendario.offsetHeight;\n                calendario.style.display = '';\n            }\n        }, 100);\n    }\n})();\n</script>",
                        $current_content
                    );
                    $updates_applied++;
                }
                
                // Update 2: Sostituisci CSS mobile vecchio con nuovo compatto
                $old_mobile_css = '@media (max-width: 768px) {
    #calendario-eventi {
        margin: 0 -10px 30px -10px;
        border-radius: 15px !important;
    }';
                
                $new_mobile_css = '/* Tablet e Mobile - Calendario Compatto Stile iPhone */
@media (max-width: 768px) {
    #calendario-eventi {
        margin: 0 0 20px 0;
        border-radius: 12px !important;
    }
    
    /* Header compatto */
    #calendario-eventi [style*="padding: 25px 30px"] {
        padding: 12px 10px !important;
    }
    
    /* Selettori mese/anno compatti */
    #calendario-eventi [style*="gap: 15px; margin-bottom: 20px"] {
        gap: 6px !important;
        margin-bottom: 10px !important;
        padding: 0 5px;
    }
    
    #calendario-select-mese,
    #calendario-select-anno {
        padding: 6px 10px !important;
        font-size: 0.75rem !important;
        min-width: 100px !important;
        border-radius: 6px !important;
    }
    
    #calendario-eventi [style*="gap: 15px; margin-bottom: 20px"] button {
        padding: 6px 12px !important;
        font-size: 0.75rem !important;
        border-radius: 6px !important;
    }
    
    #calendario-eventi [style*="gap: 15px; margin-bottom: 20px"] label {
        font-size: 0.7rem !important;
        display: none;
    }
    
    #calendario-eventi [style*="display: flex; justify-content: space-between"] button {
        padding: 6px 10px !important;
        font-size: 1rem !important;
    }
    
    #calendario-eventi h2 {
        font-size: 1.1rem !important;
    }
    
    #calendario-eventi p {
        font-size: 0.7rem !important;
        margin-top: 2px !important;
    }
    
    #calendario-eventi [style*="padding: 20px"] {
        padding: 10px 8px !important;
    }
    
    #calendario-eventi [style*="grid-template-columns: repeat(7, 1fr)"] {
        gap: 2px !important;
        margin-bottom: 12px !important;
    }
    
    #calendario-eventi [style*="grid-template-columns: repeat(7, 1fr)"] > div {
        font-size: 0.6rem !important;
        padding: 5px 0 !important;
    }
    
    #calendario-eventi [style*="aspect-ratio: 1"] {
        font-size: 0.7rem !important;
        min-height: 36px !important;
        padding: 2px !important;
    }
    
    #calendario-eventi [style*="aspect-ratio: 1"] [style*="width: 5px"] {
        width: 3px !important;
        height: 3px !important;
        margin-top: 1px !important;
    }
    
    #calendario-eventi [style*="aspect-ratio: 1"] > div:last-child {
        margin-top: 1px !important;
        gap: 1px !important;
    }
    
    #eventi-giorno {
        padding-top: 12px !important;
        margin-top: 12px !important;
    }
    
    #eventi-giorno-titolo {
        font-size: 0.9rem !important;
        margin-bottom: 10px !important;
    }
    
    #eventi-giorno-lista > div {
        padding: 10px !important;
        margin-bottom: 6px !important;
    }
    
    #eventi-giorno-lista a {
        padding: 6px 10px !important;
        font-size: 0.7rem !important;
    }
}

/* Mobile Piccolo - Ultra Compatto Stile Agenda iPhone */
@media (max-width: 576px) {
    #calendario-eventi {
        margin: 0 0 15px 0;
    }
    
    #calendario-eventi [style*="padding: 25px 30px"] {
        padding: 10px 8px !important;
    }
    
    #calendario-eventi [style*="gap: 15px; margin-bottom: 20px"] {
        flex-direction: row !important;
        gap: 4px !important;
        margin-bottom: 8px !important;
        padding: 0 3px;
        justify-content: center !important;
    }
    
    #calendario-select-mese,
    #calendario-select-anno {
        padding: 5px 8px !important;
        font-size: 0.7rem !important;
        min-width: 85px !important;
        width: auto !important;
    }
    
    #calendario-eventi [style*="gap: 15px; margin-bottom: 20px"] button {
        padding: 5px 10px !important;
        font-size: 0.7rem !important;
        width: auto !important;
    }
    
    #calendario-eventi h2 {
        font-size: 1rem !important;
    }
    
    #calendario-eventi p {
        font-size: 0.65rem !important;
        margin-top: 1px !important;
    }
    
    #calendario-eventi [style*="display: flex; justify-content: space-between"] button {
        padding: 5px 8px !important;
        font-size: 0.9rem !important;
    }
    
    #calendario-eventi [style*="padding: 20px"] {
        padding: 8px 5px !important;
    }
    
    #calendario-eventi [style*="grid-template-columns: repeat(7, 1fr)"] {
        gap: 1px !important;
        margin-bottom: 10px !important;
    }
    
    #calendario-eventi [style*="grid-template-columns: repeat(7, 1fr)"] > div {
        font-size: 0.55rem !important;
        padding: 3px 0 !important;
        letter-spacing: -0.3px;
    }
    
    #calendario-eventi [style*="aspect-ratio: 1"] {
        font-size: 0.65rem !important;
        min-height: 32px !important;
        padding: 1px !important;
        font-weight: 500 !important;
    }
    
    #calendario-eventi [style*="aspect-ratio: 1"] [style*="width: 5px"],
    #calendario-eventi [style*="aspect-ratio: 1"] [style*="width: 3px"] {
        width: 2.5px !important;
        height: 2.5px !important;
        margin-top: 0px !important;
    }
    
    #calendario-eventi [style*="aspect-ratio: 1"] > div:last-child {
        margin-top: 0px !important;
        gap: 1px !important;
    }
    
    #eventi-giorno {
        padding-top: 10px !important;
        margin-top: 10px !important;
    }
    
    #eventi-giorno-titolo {
        font-size: 0.85rem !important;
        margin-bottom: 8px !important;
    }
    
    #eventi-giorno-lista > div {
        padding: 8px !important;
        margin-bottom: 5px !important;
        border-radius: 8px !important;
    }
    
    #eventi-giorno-lista [style*="font-weight: 700"] {
        font-size: 0.85rem !important;
    }
    
    #eventi-giorno-lista [style*="font-size: 0.85rem"] {
        font-size: 0.7rem !important;
    }
    
    #eventi-giorno-lista a {
        padding: 5px 8px !important;
        font-size: 0.65rem !important;
        border-radius: 12px !important;
    }
}

/* Mobile Extra Piccolo - Massima Compattezza */
@media (max-width: 400px) {
    #calendario-eventi {
        margin: 0 0 12px 0;
    }
    
    #calendario-eventi [style*="padding: 25px 30px"] {
        padding: 8px 5px !important;
    }
    
    #calendario-select-mese,
    #calendario-select-anno {
        padding: 4px 6px !important;
        font-size: 0.65rem !important;
        min-width: 75px !important;
    }
    
    #calendario-eventi [style*="gap: 15px; margin-bottom: 20px"] button {
        padding: 4px 8px !important;
        font-size: 0.65rem !important;
    }
    
    #calendario-eventi h2 {
        font-size: 0.9rem !important;
    }
    
    #calendario-eventi p {
        font-size: 0.6rem !important;
    }
    
    #calendario-eventi [style*="padding: 20px"] {
        padding: 6px 3px !important;
    }
    
    #calendario-eventi [style*="aspect-ratio: 1"] {
        font-size: 0.6rem !important;
        min-height: 30px !important;
    }
}';
                
                if (strpos($current_content, $old_mobile_css) !== false) {
                    $current_content = str_replace($old_mobile_css, $new_mobile_css, $current_content);
                    $updates_applied++;
                }
                
                // Salva file aggiornato
                if (file_put_contents($target_file, $current_content)) {
                    echo '<div class="step success">';
                    echo '<span class="icon">✅</span>';
                    echo '<strong>File aggiornato con successo!</strong><br><br>';
                    echo '📝 Modifiche applicate: ' . $updates_applied . '<br>';
                    echo '📁 File: <div class="code">' . htmlspecialchars($target_file) . '</div>';
                    echo '</div>';
                    
                    echo '<div class="step warning">';
                    echo '<span class="icon">📱</span>';
                    echo '<strong>IMPORTANTE:</strong> Ora devi:<br><br>';
                    echo '1. ✅ Svuotare la cache del browser sul tuo smartphone<br>';
                    echo '2. ✅ Ricaricare la pagina del CRM<br>';
                    echo '3. ✅ Verificare che il calendario sia compatto<br>';
                    echo '4. 🗑️ <strong>ELIMINARE questo file auto-update-calendario.php</strong>';
                    echo '</div>';
                    
                } else {
                    echo '<div class="step error">';
                    echo '<span class="icon">❌</span>';
                    echo '<strong>ERRORE:</strong> Impossibile scrivere il file!<br><br>';
                    echo 'Verifica i permessi del file:<br>';
                    echo '<div class="code">chmod 644 ' . htmlspecialchars($target_file) . '</div>';
                    echo '</div>';
                }
            }
            
        } else {
            // Form iniziale
            ?>
            
            <div class="step">
                <span class="icon">ℹ️</span>
                <strong>Questo script aggiornerà automaticamente:</strong><br><br>
                • CSS mobile compatto (giorni 32-36px)<br>
                • Cache buster automatico<br>
                • Script force refresh<br>
                • Supporto iPhone/Android ottimizzato
            </div>
            
            <div class="warning-box">
                <span style="font-size: 48px;">⚠️</span><br>
                <strong style="font-size: 20px;">BACKUP AUTOMATICO</strong><br>
                <p style="margin: 10px 0 0 0;">
                    Prima di modificare, verrà creato un backup del file originale.
                </p>
            </div>
            
            <form method="post">
                <button type="submit" name="do_update" value="1">
                    🚀 AGGIORNA CALENDARIO MOBILE
                </button>
            </form>
            
            <div class="step" style="margin-top: 30px; font-size: 12px; color: #6c757d;">
                <strong>🔒 Sicurezza:</strong> Questo script usa una password segreta nella URL.<br>
                <strong>🗑️ Post-uso:</strong> Elimina questo file dopo l'aggiornamento!
            </div>
            
            <?php
        }
        ?>
        
    </div>
</body>
</html>
