<?php
/**
 * DIAGNOSTICA CACHE CALENDARIO - 747 Disco CRM
 * 
 * Questo script verifica se il file main-page.php è aggiornato
 * e fornisce info sulla cache.
 * 
 * ISTRUZIONI:
 * 1. Carica questo file nella root di WordPress
 * 2. Visita: https://tuo-sito.it/check-cache-calendario.php
 * 3. Leggi i risultati
 * 4. ELIMINA questo file dopo l'uso (sicurezza!)
 */

// Sicurezza minima
if (isset($_GET['run']) && $_GET['run'] === 'yes') {
    // OK, procedi
} else {
    die('❌ Accesso negato. Aggiungi ?run=yes alla URL per eseguire il check.');
}

?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Check Cache Calendario - 747 Disco CRM</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f5f5f5;
            padding: 20px;
            margin: 0;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
        }
        h1 {
            color: #2b1e1a;
            margin-top: 0;
        }
        .check {
            background: #f8f9fa;
            border-left: 4px solid #6c757d;
            padding: 15px;
            margin: 15px 0;
            border-radius: 5px;
        }
        .check.success {
            border-color: #28a745;
            background: #d4edda;
        }
        .check.warning {
            border-color: #ffc107;
            background: #fff3cd;
        }
        .check.error {
            border-color: #dc3545;
            background: #f8d7da;
        }
        .code {
            background: #2b2b2b;
            color: #00ff00;
            padding: 15px;
            border-radius: 8px;
            font-family: 'Courier New', monospace;
            overflow-x: auto;
            margin: 10px 0;
        }
        .icon {
            font-size: 24px;
            margin-right: 10px;
        }
        strong {
            color: #2b1e1a;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 Diagnostica Cache Calendario</h1>
        <p style="color: #6c757d;">Controllo aggiornamenti CSS mobile compatto...</p>
        
        <hr style="margin: 30px 0; border: none; border-top: 2px solid #e9ecef;">
        
        <?php
        
        // Check 1: File esiste?
        $file_path = __DIR__ . '/wp-content/plugins/747disco-crm/includes/admin/views/main-page.php';
        
        // Prova percorsi alternativi
        if (!file_exists($file_path)) {
            $file_path = __DIR__ . '/includes/admin/views/main-page.php';
        }
        if (!file_exists($file_path)) {
            $file_path = dirname(__DIR__) . '/includes/admin/views/main-page.php';
        }
        
        if (file_exists($file_path)) {
            echo '<div class="check success">';
            echo '<span class="icon">✅</span>';
            echo '<strong>File trovato:</strong> ' . $file_path;
            echo '</div>';
            
            // Check 2: Data modifica
            $mod_time = filemtime($file_path);
            $mod_date = date('Y-m-d H:i:s', $mod_time);
            $now = time();
            $diff = $now - $mod_time;
            $diff_hours = round($diff / 3600, 1);
            
            $class = ($diff_hours < 1) ? 'success' : 'warning';
            echo '<div class="check ' . $class . '">';
            echo '<span class="icon">🕐</span>';
            echo '<strong>Ultima modifica:</strong> ' . $mod_date . ' (' . $diff_hours . ' ore fa)';
            echo '</div>';
            
            // Check 3: Dimensione file
            $file_size = filesize($file_path);
            $file_size_kb = round($file_size / 1024, 2);
            
            $class = ($file_size_kb > 50) ? 'success' : 'error';
            echo '<div class="check ' . $class . '">';
            echo '<span class="icon">📦</span>';
            echo '<strong>Dimensione:</strong> ' . $file_size_kb . ' KB';
            if ($file_size_kb < 50) {
                echo '<br><em style="color: #dc3545;">⚠️ File troppo piccolo! Potrebbe non essere aggiornato.</em>';
            }
            echo '</div>';
            
            // Check 4: Cerca stringhe specifiche del nuovo CSS
            $content = file_get_contents($file_path);
            
            // Verifica 1: Cache buster
            if (strpos($content, 'Cache Buster:') !== false) {
                echo '<div class="check success">';
                echo '<span class="icon">✅</span>';
                echo '<strong>Cache Buster:</strong> Presente ✓';
                echo '</div>';
            } else {
                echo '<div class="check error">';
                echo '<span class="icon">❌</span>';
                echo '<strong>Cache Buster:</strong> ASSENTE';
                echo '<br><em>Il file NON è stato aggiornato con la versione anti-cache!</em>';
                echo '</div>';
            }
            
            // Verifica 2: CSS Mobile Compatto
            if (strpos($content, 'Mobile Compatto Stile Agenda iPhone') !== false) {
                echo '<div class="check success">';
                echo '<span class="icon">✅</span>';
                echo '<strong>CSS Mobile Compatto:</strong> Presente ✓';
                echo '</div>';
            } else {
                echo '<div class="check error">';
                echo '<span class="icon">❌</span>';
                echo '<strong>CSS Mobile Compatto:</strong> ASSENTE';
                echo '</div>';
            }
            
            // Verifica 3: Min-height 32px (mobile)
            if (preg_match('/min-height:\s*32px/', $content)) {
                echo '<div class="check success">';
                echo '<span class="icon">✅</span>';
                echo '<strong>Giorni 32px (mobile):</strong> Configurato ✓';
                echo '</div>';
            } else {
                echo '<div class="check warning">';
                echo '<span class="icon">⚠️</span>';
                echo '<strong>Giorni 32px:</strong> Non trovato';
                echo '</div>';
            }
            
            // Verifica 4: Script force refresh
            if (strpos($content, 'Force CSS refresh') !== false) {
                echo '<div class="check success">';
                echo '<span class="icon">✅</span>';
                echo '<strong>Script Force Refresh:</strong> Presente ✓';
                echo '</div>';
            } else {
                echo '<div class="check warning">';
                echo '<span class="icon">⚠️</span>';
                echo '<strong>Script Force Refresh:</strong> Non trovato';
                echo '</div>';
            }
            
            // Info aggiuntive
            echo '<hr style="margin: 30px 0; border: none; border-top: 2px solid #e9ecef;">';
            echo '<h3>📊 Statistiche File</h3>';
            
            echo '<div class="check">';
            echo '<strong>Percorso completo:</strong><br>';
            echo '<div class="code">' . htmlspecialchars($file_path) . '</div>';
            echo '</div>';
            
            echo '<div class="check">';
            echo '<strong>Permessi:</strong> ' . substr(sprintf('%o', fileperms($file_path)), -4);
            echo '</div>';
            
            echo '<div class="check">';
            echo '<strong>Owner:</strong> ' . posix_getpwuid(fileowner($file_path))['name'];
            echo '</div>';
            
            // Estratto CSS mobile
            echo '<hr style="margin: 30px 0; border: none; border-top: 2px solid #e9ecef;">';
            echo '<h3>🔍 Estratto CSS Mobile</h3>';
            
            preg_match('/\/\* Mobile Piccolo.*?@media \(max-width: 576px\) \{(.*?)\}/s', $content, $matches);
            
            if (!empty($matches[1])) {
                $css_snippet = substr($matches[1], 0, 500) . '...';
                echo '<div class="code">';
                echo htmlspecialchars($css_snippet);
                echo '</div>';
            } else {
                echo '<div class="check warning">';
                echo '⚠️ Impossibile estrarre CSS mobile';
                echo '</div>';
            }
            
        } else {
            echo '<div class="check error">';
            echo '<span class="icon">❌</span>';
            echo '<strong>ERRORE:</strong> File main-page.php NON trovato!';
            echo '<br><br><em>Percorso cercato: ' . $file_path . '</em>';
            echo '<br><br>Verifica il percorso del plugin.';
            echo '</div>';
        }
        
        // Info ambiente
        echo '<hr style="margin: 30px 0; border: none; border-top: 2px solid #e9ecef;">';
        echo '<h3>🖥️ Info Server</h3>';
        
        echo '<div class="check">';
        echo '<strong>PHP Version:</strong> ' . phpversion();
        echo '</div>';
        
        echo '<div class="check">';
        echo '<strong>Server Time:</strong> ' . date('Y-m-d H:i:s');
        echo '</div>';
        
        echo '<div class="check">';
        echo '<strong>Timezone:</strong> ' . date_default_timezone_get();
        echo '</div>';
        
        // Cache info
        if (function_exists('opcache_get_status')) {
            $opcache = opcache_get_status();
            $class = $opcache['opcache_enabled'] ? 'warning' : 'success';
            echo '<div class="check ' . $class . '">';
            echo '<strong>OPcache:</strong> ' . ($opcache['opcache_enabled'] ? '⚠️ Attivo (potrebbe cachare PHP)' : '✅ Disattivo');
            echo '</div>';
        }
        
        ?>
        
        <hr style="margin: 30px 0; border: none; border-top: 2px solid #e9ecef;">
        
        <h3>💡 Prossimi Step</h3>
        
        <div class="check">
            <strong>1. Se tutto è ✅ verde ma mobile non funziona:</strong><br>
            → Problema di cache BROWSER<br>
            → Svuota cache del telefono (vedi CLEAR-CACHE-ISTRUZIONI.md)
        </div>
        
        <div class="check">
            <strong>2. Se ci sono ❌ rossi:</strong><br>
            → Ricarica il file main-page.php sul server<br>
            → Verifica percorso plugin WordPress
        </div>
        
        <div class="check">
            <strong>3. Dopo aver risolto:</strong><br>
            → <strong>ELIMINA questo file check-cache-calendario.php</strong> (sicurezza!)<br>
            → Test finale su smartphone
        </div>
        
        <hr style="margin: 30px 0; border: none; border-top: 2px solid #e9ecef;">
        
        <p style="text-align: center; color: #6c757d; font-size: 0.9rem;">
            <strong>⚠️ IMPORTANTE:</strong> Elimina questo file dopo l'uso!<br>
            <code>rm check-cache-calendario.php</code>
        </p>
        
    </div>
</body>
</html>
