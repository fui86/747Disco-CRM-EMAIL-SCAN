# ?? SOLUZIONE ERRORE 503 - Batch Scan Google Drive

## ?? PROBLEMA IDENTIFICATO

**Errore:** `503 Service Unavailable` durante la scansione batch di file Excel da Google Drive.

**Causa:** Stai processando **35 file Excel in una singola richiesta HTTP**, causando:
- ?? Timeout PHP (30-60 secondi di default)
- ?? Esaurimento memoria
- ?? Il server blocca la richiesta per protezione

**Dai log vedo:**
```
[747Disco-Scan] Trovati 35 file Excel da Google Drive
[747Disco-Scan] Processando file: No 31_01 18 anni di Chiara...
[747Disco-Scan] Processando file: Conf 30_01 18 anni di Livia...
... (35 file in sequenza)
admin-ajax.php:1 Failed to load resource: 503
```

---

## ? SOLUZIONE: CHUNKING AUTOMATICO

### Strategia
Invece di processare tutti i 35 file in una volta, li dividiamo in **batch pi? piccoli (10 file alla volta)** con pause tra un batch e l'altro.

### Vantaggi
- ? Nessun timeout del server
- ? Gestione progressiva con feedback visivo
- ? Liberazione memoria dopo ogni file
- ? Possibilit? di interrompere/riprendere
- ? Performance migliorate

---

## ?? IMPLEMENTAZIONE

### STEP 1: Modifica Backend PHP

File da modificare: `includes/storage/class-disco747-googledrive-sync.php`

Aggiungi il metodo ottimizzato:

```php
/**
 * ? METODO OTTIMIZZATO: Scansione batch con chunking
 * Processa un massimo di $limit file per chiamata
 * 
 * @param int $offset Punto di partenza
 * @param int $limit Numero massimo di file da processare
 * @return array Risultati con flag has_more
 */
public function scan_excel_files_chunked($offset = 0, $limit = 10) {
    $this->log('=== SCAN BATCH CHUNKED (Offset: ' . $offset . ', Limit: ' . $limit . ') ===');
    
    try {
        // 1. Lista TUTTI i file (con cache per evitare ripetizioni)
        $cache_key = 'disco747_excel_files_list_' . md5(serialize([$anno ?? 'all', $mese ?? 'all']));
        $all_files = get_transient($cache_key);
        
        if (false === $all_files) {
            $all_files = $this->list_all_excel_files();
            set_transient($cache_key, $all_files, 5 * MINUTE_IN_SECONDS);
        }
        
        $total_files = count($all_files);
        
        // 2. Slice per ottenere solo il chunk corrente
        $files_chunk = array_slice($all_files, $offset, $limit);
        $files_count = count($files_chunk);
        
        $this->log("Chunk: processando {$files_count} file di {$total_files} totali");
        
        // 3. Processa solo questo chunk
        $results = [
            'processed' => 0,
            'saved' => 0,
            'errors' => 0,
            'offset' => $offset,
            'limit' => $limit,
            'total' => $total_files,
            'has_more' => ($offset + $limit) < $total_files,
            'next_offset' => $offset + $limit,
            'files' => []
        ];
        
        foreach ($files_chunk as $file) {
            try {
                // Processa singolo file
                $data = $this->process_single_excel_file($file);
                
                if ($data) {
                    $saved_id = $this->database->upsert_preventivo_by_file_id($data);
                    
                    $results['files'][] = [
                        'name' => $file['name'],
                        'status' => 'success',
                        'id' => $saved_id
                    ];
                    
                    $results['saved']++;
                }
                
                $results['processed']++;
                
                // ? IMPORTANTE: Libera memoria dopo ogni file
                gc_collect_cycles();
                
            } catch (\Exception $e) {
                $this->log('Errore file ' . $file['name'] . ': ' . $e->getMessage(), 'ERROR');
                
                $results['files'][] = [
                    'name' => $file['name'],
                    'status' => 'error',
                    'error' => $e->getMessage()
                ];
                
                $results['errors']++;
            }
        }
        
        // 4. Log finale chunk
        $this->log("? Chunk completato: {$results['processed']} processati, {$results['saved']} salvati, {$results['errors']} errori");
        
        // 5. Cancella cache se completato
        if (!$results['has_more']) {
            delete_transient($cache_key);
            $this->log('?? SCANSIONE COMPLETATA!');
        }
        
        return $results;
        
    } catch (\Exception $e) {
        $this->log('? Errore scan chunked: ' . $e->getMessage(), 'ERROR');
        
        return [
            'processed' => 0,
            'saved' => 0,
            'errors' => 1,
            'error_message' => $e->getMessage(),
            'has_more' => false
        ];
    }
}

/**
 * Helper: Lista tutti i file Excel (da usare con cache)
 */
private function list_all_excel_files() {
    // Usa la logica esistente per ottenere tutti i file
    // ma senza processarli
    $files = [];
    
    // ... codice esistente per listare file da Google Drive ...
    
    return $files;
}

/**
 * Helper: Processa un singolo file Excel
 */
private function process_single_excel_file($file) {
    // Logica esistente per:
    // 1. Download file
    // 2. Parsing Excel
    // 3. Estrazione dati
    
    // ... usa il codice esistente ...
    
    return $data;
}
```

---

### STEP 2: Modifica AJAX Handler

File: `includes/admin/ajax-handlers.php` o simile

```php
/**
 * ? AJAX Handler per batch scan chunked
 */
public function handle_batch_scan_chunked() {
    try {
        // 1. Verifica permessi
        if (!current_user_can('manage_options')) {
            throw new \Exception('Permessi insufficienti');
        }
        
        // 2. Parametri
        $offset = isset($_POST['offset']) ? intval($_POST['offset']) : 0;
        $limit = isset($_POST['limit']) ? intval($_POST['limit']) : 10;
        
        $this->log("?? Batch scan chunked richiesto: offset={$offset}, limit={$limit}");
        
        // 3. Verifica classe GoogleDrive Sync
        if (!class_exists('Disco747_CRM\\Storage\\Disco747_GoogleDrive_Sync')) {
            throw new \Exception('GoogleDrive_Sync non disponibile');
        }
        
        // 4. Inizializza handler
        $googledrive_handler = new \Disco747_CRM\Storage\Disco747_GoogleDrive();
        $gdrive_sync = new \Disco747_CRM\Storage\Disco747_GoogleDrive_Sync($googledrive_handler);
        
        if (!$gdrive_sync->is_available()) {
            throw new \Exception('GoogleDrive Sync non disponibile');
        }
        
        // 5. Esegui scan chunked
        $result = $gdrive_sync->scan_excel_files_chunked($offset, $limit);
        
        // 6. Risposta JSON
        wp_send_json_success($result);
        
    } catch (\Exception $e) {
        $this->log('? Errore batch scan chunked: ' . $e->getMessage(), 'ERROR');
        wp_send_json_error(['message' => $e->getMessage()]);
    }
}

// Registra hook AJAX
add_action('wp_ajax_disco747_batch_scan_chunked', array($this, 'handle_batch_scan_chunked'));
```

---

### STEP 3: Modifica Frontend JavaScript

File: `assets/js/excel-scan.js`

```javascript
/**
 * ? BATCH SCAN RICORSIVO con Chunking
 * Processa file in gruppi da 10 con feedback progressivo
 */
async function startBatchScanChunked() {
    console.log('[Excel-Scan] ?? Avvio batch scan con chunking...');
    
    // 1. Setup UI
    const $button = jQuery('#start-batch-scan');
    const $progressContainer = jQuery('#scan-progress-container');
    const $progressBar = jQuery('#scan-progress-bar');
    const $progressText = jQuery('#scan-progress-text');
    const $resultsDiv = jQuery('#scan-results');
    
    $button.prop('disabled', true).text('? Scansione in corso...');
    $progressContainer.show();
    $progressBar.css('width', '0%');
    $resultsDiv.empty();
    
    // 2. Variabili di tracking
    let offset = 0;
    let limit = 10; // File per batch
    let totalProcessed = 0;
    let totalSaved = 0;
    let totalErrors = 0;
    let hasMore = true;
    let grandTotal = null;
    
    // 3. Loop ricorsivo
    while (hasMore) {
        try {
            console.log(`[Excel-Scan] ?? Batch #${Math.floor(offset / limit) + 1}: offset=${offset}, limit=${limit}`);
            
            // 3a. Chiamata AJAX per questo chunk
            const response = await jQuery.ajax({
                url: disco747ExcelScanData.ajaxurl,
                type: 'POST',
                data: {
                    action: 'disco747_batch_scan_chunked',
                    offset: offset,
                    limit: limit,
                    nonce: disco747ExcelScanData.nonce
                },
                timeout: 60000 // 60 secondi per chunk
            });
            
            console.log('[Excel-Scan] ? Risposta ricevuta:', response);
            
            if (response.success && response.data) {
                const data = response.data;
                
                // 3b. Aggiorna contatori
                totalProcessed += data.processed || 0;
                totalSaved += data.saved || 0;
                totalErrors += data.errors || 0;
                hasMore = data.has_more || false;
                grandTotal = data.total || grandTotal;
                
                // 3c. Aggiorna progress bar
                if (grandTotal > 0) {
                    const percentage = Math.round((totalProcessed / grandTotal) * 100);
                    $progressBar.css('width', percentage + '%');
                    $progressText.text(
                        `Processati: ${totalProcessed}/${grandTotal} file (${percentage}%) | ` +
                        `Salvati: ${totalSaved} | Errori: ${totalErrors}`
                    );
                }
                
                // 3d. Mostra file processati in questo chunk
                if (data.files && data.files.length > 0) {
                    data.files.forEach(file => {
                        const icon = file.status === 'success' ? '?' : '?';
                        $resultsDiv.append(
                            `<div class="scan-result-item ${file.status}">${icon} ${file.name}</div>`
                        );
                    });
                }
                
                // 3e. Avanza offset per prossimo batch
                offset = data.next_offset || (offset + limit);
                
                // 3f. Pausa tra batch (per dare respiro al server)
                if (hasMore) {
                    await new Promise(resolve => setTimeout(resolve, 500)); // 500ms delay
                }
                
            } else {
                throw new Error(response.data?.message || 'Errore sconosciuto');
            }
            
        } catch (error) {
            console.error('[Excel-Scan] ? Errore batch:', error);
            
            $resultsDiv.append(
                `<div class="scan-result-item error">? Errore: ${error.message || error}</div>`
            );
            
            totalErrors++;
            hasMore = false; // Stop su errore
        }
    }
    
    // 4. Completamento
    console.log('[Excel-Scan] ?? Scansione completata!');
    
    $button.prop('disabled', false).text('?? Avvia Scansione');
    
    $resultsDiv.prepend(
        `<div class="scan-summary success">
            <h4>?? Scansione Completata!</h4>
            <p><strong>Totale processati:</strong> ${totalProcessed}</p>
            <p><strong>Totale salvati:</strong> ${totalSaved}</p>
            <p><strong>Totale errori:</strong> ${totalErrors}</p>
        </div>`
    );
    
    // 5. Ricarica lista preventivi
    if (typeof loadPreventivi === 'function') {
        loadPreventivi();
    }
}

// Bind evento click
jQuery(document).ready(function($) {
    $('#start-batch-scan').on('click', function(e) {
        e.preventDefault();
        startBatchScanChunked();
    });
});
```

---

### STEP 4: CSS per Progress Bar

File: `assets/css/admin.css` o `excel-scan.css`

```css
/* Progress Container */
#scan-progress-container {
    margin: 20px 0;
    padding: 20px;
    background: #f9f9f9;
    border: 1px solid #ddd;
    border-radius: 4px;
    display: none;
}

/* Progress Bar */
.scan-progress-bar-wrapper {
    width: 100%;
    height: 30px;
    background: #e0e0e0;
    border-radius: 4px;
    overflow: hidden;
    margin-bottom: 10px;
}

#scan-progress-bar {
    height: 100%;
    background: linear-gradient(90deg, #4CAF50, #45a049);
    width: 0%;
    transition: width 0.3s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: bold;
}

/* Progress Text */
#scan-progress-text {
    text-align: center;
    font-size: 14px;
    color: #333;
    margin-top: 10px;
}

/* Results */
#scan-results {
    margin-top: 20px;
    max-height: 400px;
    overflow-y: auto;
}

.scan-result-item {
    padding: 8px 12px;
    margin: 5px 0;
    border-radius: 4px;
    font-size: 13px;
}

.scan-result-item.success {
    background: #d4edda;
    border-left: 4px solid #28a745;
}

.scan-result-item.error {
    background: #f8d7da;
    border-left: 4px solid #dc3545;
}

.scan-summary {
    padding: 15px;
    border-radius: 4px;
    margin-bottom: 15px;
}

.scan-summary.success {
    background: #d4edda;
    border: 1px solid #28a745;
}

.scan-summary h4 {
    margin: 0 0 10px 0;
    color: #155724;
}

.scan-summary p {
    margin: 5px 0;
    color: #155724;
}
```

---

## ?? CONFIGURAZIONE PARAMETRI

### Parametri Ottimizzabili

| Parametro | Valore Default | Descrizione | Consigliato |
|-----------|---------------|-------------|-------------|
| `$limit` | 10 | File per batch | 5-15 |
| `setTimeout` | 500ms | Pausa tra batch | 300-1000ms |
| `timeout` AJAX | 60000ms | Timeout per richiesta | 30000-90000ms |
| Cache transient | 5 min | Durata cache lista file | 5-10 min |

### Come Modificare

**Per processare meno file per batch (server lento):**
```javascript
let limit = 5; // Invece di 10
```

**Per aumentare pausa tra batch:**
```javascript
await new Promise(resolve => setTimeout(resolve, 1000)); // 1 secondo
```

---

## ?? TROUBLESHOOTING

### Problema: Ancora timeout dopo fix
**Soluzione:** Riduci `limit` a 5 file per batch

### Problema: Progress bar non si aggiorna
**Soluzione:** Verifica che il CSS sia caricato e `#scan-progress-container` esista nel DOM

### Problema: Cache non si svuota
**Soluzione:** Cancella manualmente:
```php
delete_transient('disco747_excel_files_list_*');
```

### Problema: Memoria esaurita
**Soluzione:** Aggiungi all'inizio del metodo:
```php
ini_set('memory_limit', '256M');
```

---

## ?? PERFORMANCE ATTESE

### Prima (SENZA chunking)
- ? 35 file in 1 richiesta
- ? Timeout dopo ~30 secondi
- ? Nessun feedback durante elaborazione
- ? Tutto o niente (fallisce completamente)

### Dopo (CON chunking)
- ? 35 file in 4 batch (10+10+10+5)
- ? ~2-3 minuti totali
- ? Progress bar in tempo reale
- ? Robusto (continua anche con errori singoli)

---

## ?? CHECKLIST IMPLEMENTAZIONE

- [ ] Modificato `class-disco747-googledrive-sync.php` (metodo chunked)
- [ ] Modificato AJAX handler (registrato action `disco747_batch_scan_chunked`)
- [ ] Modificato `excel-scan.js` (funzione ricorsiva)
- [ ] Aggiunto CSS per progress bar
- [ ] Testato con pochi file (5-10)
- [ ] Testato con tutti i file (35+)
- [ ] Verificato log senza errori 503
- [ ] Progress bar funzionante
- [ ] Preventivi salvati correttamente nel DB

---

## ?? SUPPORTO

Se dopo l'implementazione riscontri ancora problemi, controlla:

1. **PHP Error Log:** `/error_log` o nel pannello hosting
2. **Browser Console:** F12 ? Console tab
3. **Network Tab:** F12 ? Network ? Filtra "disco747"

Aumenta timeout PHP in `.htaccess` o `php.ini` (se hai accesso):
```apache
php_value max_execution_time 300
php_value max_input_time 300
php_value memory_limit 256M
```

---

**Buon lavoro! ??**
