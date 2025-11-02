# ?? FIX TIMEOUT 503 - RIEPILOGO MODIFICHE

## ?? Problema Identificato

### Errore Originale
- **Errore 503**: Service Unavailable durante scansione Google Drive
- **Causa**: Timeout server (> 60 secondi) per elaborazione 35+ file Excel in sequenza

### Nuovo Errore (dopo prime modifiche)
- **Errore 500**: Internal Server Error  
- **Causa**: Fatal Error PHP - File Excel vuoto/corrotto scaricato da Google Drive
```
PHP Fatal error: PhpOffice\PhpSpreadsheet\Exception: 
Your requested sheet index: -1 is out of bounds. The actual number of sheets is 0.
```

## ? Soluzioni Implementate

### 1. **Batch Processing** (Elaborazione a Lotti)
**File**: `includes/storage/class-disco747-googledrive-sync.php`

- ? Metodo `scan_excel_files_batch()` modificato per supportare:
  - **offset**: Punto di partenza (default: 0)
  - **batch_size**: File per batch (default: 10)
  - **caching**: Lista file salvata in WordPress transient (5 min)
- ? Timeout esteso: 120 secondi per batch
- ? Ritorna `has_more` e `next_offset` per continuazione
- ? Try-catch su 3 livelli: Exception, PhpSpreadsheet\Reader\Exception, Throwable

### 2. **AJAX Handler Ottimizzato**
**File**: `includes/admin/ajax-handlers.php`

- ? Timeout aumentato: `set_time_limit(120)`
- ? Memory limit: `ini_set('memory_limit', '512M')`
- ? Accetta parametri `offset` e `batch_size`
- ? Chiama `GoogleDrive_Sync->scan_excel_files_batch()` con offset
- ? Risponde con info batch per elaborazione progressiva

### 3. **JavaScript Ricorsivo con Retry**
**File**: `includes/admin/views/excel-scan-page.php`

- ? Funzione `startBatchScan(year, month, offset)` ricorsiva
- ? Progress bar real-time con percentuale esatta
- ? Retry automatico su timeout (max 2 tentativi)
- ? Pausa 500ms tra batch
- ? Timeout AJAX: 90 secondi per batch
- ? Statistiche cumulative

### 4. **Validazione File Scaricati**
**File**: `includes/storage/class-disco747-googledrive.php`

- ? Verifica contenuto non vuoto prima di salvare
- ? Verifica dimensione minima (1KB)
- ? Verifica file esiste e non ? vuoto dopo salvataggio
- ? Logging dettagliato con dimensioni file

**File**: `includes/storage/class-disco747-googledrive-sync.php`

- ? Validazione in `download_file_to_temp()`:
  - Contenuto non vuoto
  - File salvato correttamente
  - Dimensione minima 1KB
- ? Validazione in `extract_data_from_excel()`:
  - File esiste
  - File non vuoto
  - PhpSpreadsheet caricato con successo
  - Foglio Excel presente

### 5. **Handler Legacy Disabilitato**
**File**: `includes/handlers/class-disco747-excel-scan-handler.php`

- ? Hook AJAX `disco747_batch_scan_excel` commentato
- ? Inizializzazione `new Disco747_Excel_Scan_Handler()` commentata
- ?? Classe mantenuta solo per compatibilit?

## ?? Comportamento Atteso

### Prima (? Errore)
```
1 chiamata AJAX ? 35 file ? 2-3 sec = 90+ secondi ? TIMEOUT 503
```

### Dopo (? Soluzione)
```
Batch 1: File 0-9   (10 file ? 2 sec = 20 sec) ? ? Pausa 500ms
Batch 2: File 10-19 (10 file ? 2 sec = 20 sec) ? ? Pausa 500ms
Batch 3: File 20-29 (10 file ? 2 sec = 20 sec) ? ? Pausa 500ms
Batch 4: File 30-34 (5 file ? 2 sec = 10 sec)  ?

Totale: 4 chiamate AJAX, ognuna sotto i 30 secondi ?
```

### Gestione File Corrotti
- File vuoto/corrotto: **SKIP** con log errore
- Errore non blocca l'intero batch
- Statistiche mostrano errori separati
- Elaborazione continua con file successivi

## ?? Risposta AJAX Ottimizzata

```json
{
  "success": true,
  "data": {
    "complete": false,          // true solo sull'ultimo batch
    "total_files": 35,
    "current_offset": 0,
    "batch_size": 10,
    "processed_in_batch": 10,
    "new_records": 5,
    "updated_records": 5,
    "errors": 0,
    "has_more": true,           // true se ci sono altri batch
    "next_offset": 10,          // offset per prossima chiamata
    "progress_percent": 28,     // % completamento
    "message": "Batch 0-10 completato. Continuando..."
  }
}
```

## ?? Test Consigliati

1. **Seleziona un mese con 5-10 file** (es. solo Gennaio 2025)
2. Clicca "Analizza Ora"
3. Osserva:
   - Progress bar che avanza gradualmente
   - Log debug con batch progressivi
   - Statistiche che si aggiornano
4. Verifica che file corrotti vengano skippati senza bloccare tutto

## ?? Configurazione Batch Size

Modifica in `excel-scan-page.php` riga ~353:
```javascript
batch_size: 10  // ? 5-15 consigliato (10 default)
```

**Raccomandazioni per batch_size:**
- **5**: Shared hosting lento
- **10**: Hosting normale (Hostinger) ? CONSIGLIATO
- **15**: VPS/Server dedicato

## ?? Note Importanti

1. **I file devono essere aggiornati sul server** - Le modifiche potrebbero non essere attive se il server ha cache
2. **Verifica che non ci siano fatal error nei log** dopo l'aggiornamento
3. **Il vecchio handler ? disabilitato** - Se il sistema continua a usarlo, c'? un problema di cache o caricamento file

## ?? Debug Persistente

Se l'errore continua:

1. **Verifica quale handler viene chiamato**:
   - Guarda i log: cerca `[Batch-Scan-AJAX] ========== INIZIO BATCH SCAN OTTIMIZZATO ==========`
   - Se non lo vedi, il vecchio handler ? ancora attivo

2. **Clear cache WordPress**:
   ```bash
   wp cache flush
   ```

3. **Verifica file caricati sul server**:
   - Controlla che i file modificati siano effettivamente sul server
   - Timestamp file deve corrispondere alle modifiche

4. **Abilita WP_DEBUG** per vedere tutti gli errori:
   ```php
   define('WP_DEBUG', true);
   define('WP_DEBUG_LOG', true);
   ```

## ?? File Modificati

- ? `includes/storage/class-disco747-googledrive-sync.php` - Batch processing ottimizzato
- ? `includes/admin/ajax-handlers.php` - Handler AJAX con offset/batch_size
- ? `includes/admin/views/excel-scan-page.php` - JavaScript ricorsivo con retry
- ? `includes/storage/class-disco747-googledrive.php` - Validazione download
- ? `includes/handlers/class-disco747-excel-scan-handler.php` - Disabilitato (legacy)

## ?? Prossimi Passi

1. Aggiorna i file sul server
2. Clear cache WordPress e browser
3. Testa con un mese con pochi file
4. Se funziona, testa con anno intero
5. Monitora i log per file corrotti/vuoti
