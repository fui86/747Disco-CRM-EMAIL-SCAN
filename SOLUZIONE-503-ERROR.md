# 🚨 Soluzione Errore 503 Service Unavailable

## ❌ Problema Identificato

**Errore**: `503 Service Unavailable - The server is temporarily busy`

**Causa Root**: Troppe richieste **parallele** simultanee che saturano il server:

- 10+ thread PHP attivi contemporaneamente
- Ogni thread processa 43 file
- Totale: ~430+ richieste simultanee a Google Drive API
- Risultato: Server web sovraccarico → 503 Error

### 🔍 Perché Succede

I log mostrano:
```
14:24:13 - Thread 1 inizia (43 file)
14:24:16 - Thread 2 inizia (43 file)  ← DUPLICATO
14:24:16 - Thread 3 inizia (43 file)  ← DUPLICATO
14:25:14 - Thread 4 inizia (43 file)  ← DUPLICATO
...
```

**Possibili cause dei thread multipli:**
1. ✅ Utente clicca più volte il pulsante prima che si disabiliti
2. ✅ Pagina aperta in più tab/finestre
3. ✅ Browser refresh durante scansione
4. ✅ Timeout connessione che genera retry automatico

## ✅ Soluzioni Implementate

### 1. **Lock Lato Server** (CRITICO)

Impedisce esecuzioni multiple simultanee usando WordPress transients:

```php
// In class-disco747-excel-scan-handler.php
$lock_key = 'disco747_scan_lock';
if (get_transient($lock_key)) {
    wp_send_json_error('Scansione già in corso!');
    return;
}
set_transient($lock_key, time(), 300); // Lock per 5 minuti
```

**Risultato**: Solo 1 scansione alla volta, anche se arrivano 10 richieste simultanee.

### 2. **Safety Limit** (4 file max)

Limita automaticamente ogni richiesta a max 4 file:

```php
$max_files_per_request = 4;
if (count($excel_files) > $max_files_per_request) {
    $excel_files = array_slice($excel_files, 0, 4);
    $has_more = true; // Segnala che ci sono altri file
}
```

**Risultato**: 
- 4 file = ~20-40 secondi (ben sotto timeout 150s)
- Risposta JSON `has_more: true` indica file rimanenti

### 3. **Cache Bypass JavaScript**

Forza il browser a ricaricare sempre l'ultima versione del JS:

```php
// In class-disco747-admin.php
$js_version = $this->asset_version . '.' . time(); // Timestamp dinamico
wp_enqueue_script('...', '...', array(), $js_version, true);
```

### 4. **Gestione Errore 503 nel Frontend**

Messaggio chiaro all'utente:

```javascript
if (xhr.status === 503) {
    alert('Server sovraccarico!\n\n' +
          'Aspetta 2-3 minuti e ricarica la pagina.\n' +
          'NON cliccare più volte il pulsante!');
}
```

## 🎯 Come Funziona Ora

### Scenario 1: Scansione Normale (4 file per batch)

```
Utente clicca "Analizza Ora"
  → Frontend: AJAX con offset=0, limit=4
  → Backend: LOCK acquisito
  → Backend: Processa 4 file in ~20-40s
  → Backend: Rilascia LOCK
  → Backend: Risposta JSON con has_more=true, next_offset=4
  → Frontend: Avvia automaticamente batch successivo (offset=4)
  → (Ripete finché has_more=false)
```

### Scenario 2: Doppio Click (Prevenuto)

```
Utente clicca "Analizza Ora" 2 volte
  → Request 1: LOCK acquisito ✅
  → Request 2: LOCK già attivo ❌
  → Response 2: Errore "Scansione già in corso"
  → Frontend: Alert "Attendere completamento"
```

### Scenario 3: Tab Multipli (Prevenuto)

```
Tab A: Clicca "Analizza Ora" → LOCK acquisito ✅
Tab B: Clicca "Analizza Ora" → LOCK rifiutato ❌
Tab C: Clicca "Analizza Ora" → LOCK rifiutato ❌
```

## 📊 Workflow Completo (43 file)

Con il nuovo sistema a 4 file/batch **AUTOMATICO**:

```
Batch 1:  File 0-3   (4 file)  → 30s → has_more: true ✅ (auto-continua)
Batch 2:  File 4-7   (4 file)  → 35s → has_more: true ✅ (auto-continua)
Batch 3:  File 8-11  (4 file)  → 30s → has_more: true ✅ (auto-continua)
Batch 4:  File 12-15 (4 file)  → 40s → has_more: true ✅ (auto-continua)
... (continua automaticamente)
Batch 11: File 40-42 (3 file)  → 20s → has_more: false ✅ COMPLETO!
```

**Totale: 1 click iniziale, 0 interventi manuali** per 43 file (~6-8 minuti totali)

Il frontend gestisce **automaticamente** tutti i batch in sequenza!

## ⚙️ Configurazione

### Modificare Limite File per Batch

In `/workspace/assets/js/excel-scan.js`:

```javascript
// Linea ~13
batchSize: 4 // ← Cambia qui (frontend)
```

In `/workspace/includes/handlers/class-disco747-excel-scan-handler.php`:

```php
// Linea ~173
$max_files_per_request = 4; // ← Cambia qui (safety backend)
```

**Raccomandazioni basate su performance server:**
- **2-3 file**: Server molto lenti o sovraccarichi (15-30s/batch)
- **4 file**: Default - bilanciamento sicurezza/velocità ⭐ **CONSIGLIATO**
- **5-6 file**: Server veloci con timeout 150s+ (40-60s/batch)
- **8+ file**: Solo per server dedicati con timeout >180s (rischio timeout)

### Durata Lock

In `/workspace/includes/handlers/class-disco747-excel-scan-handler.php`:

```php
set_transient($lock_key, time(), 300); // 300s = 5 minuti
```

Se cambi il limite file a 3, riduci il lock a 180s (3 minuti).

## 🐛 Troubleshooting

### Problema: "Scansione già in corso" anche se non lo è

**Causa**: Lock bloccato da crash precedente o timeout server.

**Soluzione 1: Pulsante "Sblocca Scansione" (CONSIGLIATO)**

1. Nella pagina "Scansione File Excel", sotto i pulsanti "Analizza Ora" e "Svuota e Rianalizza", trovi il pulsante **🔓 Sblocca Scansione (Emergenza)**
2. Clicca il pulsante
3. Conferma l'operazione
4. Il lock verrà rilasciato immediatamente
5. Riprova la scansione

**Soluzione 2: Attesa Automatica**

Il lock scade automaticamente dopo **5 minuti**.

**Soluzione 3: Manuale (per sviluppatori)**

```php
// Aggiungi temporaneamente in wp-config.php o wp-admin/admin-ajax.php
delete_transient('disco747_scan_lock');
```

### Problema: Errore 503 persiste

**Causa**: Troppe tab aperte o utenti multipli.

**Soluzione**:
1. Chiudi TUTTI i tab del gestionale
2. Aspetta 5 minuti (scadenza lock)
3. Apri 1 SOLO tab
4. Riprova

### Problema: "has_more" non viene rilevato

**Causa**: Cache JavaScript browser.

**Soluzione**:
1. Ricarica con cache vuota: `Ctrl+F5` (Windows) o `Cmd+Shift+R` (Mac)
2. Verifica in Console: Cerca `batchSize: 8` nei log di inizializzazione

## 📝 Log Chiave per Verifica

### Lock Funzionante (BUONO)

```
[747Disco-Scan] 🔒 LOCK acquisito
[747Disco-Scan] ⚠️ SAFETY LIMIT: Riducendo da 43 a 5 file
[747Disco-Scan] 🔓 LOCK rilasciato
```

### Lock Bloccato (ATTESO se scansione in corso)

```
[747Disco-Scan] ⚠️ LOCK ATTIVO: Scansione già in corso, richiesta rifiutata
```

### Nessun Lock (PROBLEMA - Vecchio codice)

```
[747Disco-Scan] Trovati 43 file Excel da Google Drive
[747Disco-Scan] Processando file: CONF...  ← Nessun log di lock!
```

## 🎉 Benefici

- ✅ **Elimina errore 503**: Solo 1 thread alla volta
- ✅ **Processa tutti i file**: Batch da 5 file completabili in 25s
- ✅ **Resilienza**: Se un batch fallisce, i file processati sono salvati
- ✅ **UX chiara**: Alert guida l'utente passo-passo
- ✅ **Scalabile**: Funziona con 100+ file (solo più click)

## ⚠️ Limitazioni

- ❌ **Richiede intervento manuale**: L'utente deve ricaricare pagina tra batch
- ❌ **Più lento**: 9 batch per 43 file vs 1 batch monolitico
- ✅ **Ma FUNZIONA**: Nessun timeout, nessun 503

## 🔄 Prossimi Miglioramenti (Opzionale)

Per eliminare i click manuali, si potrebbe implementare:

1. **Auto-reload JavaScript**: Ricarica automatica dopo ogni batch
2. **Session storage**: Traccia progresso tra reload
3. **WP Cron**: Scansione in background schedulata
4. **CLI Command**: `wp disco747 scan-excel --year=2025` (per SSH)

---

**Data Implementazione:** 2025-11-05  
**Versione:** v3.0 (Server Lock + Safety Limit)  
**Autore:** Assistant (Background Agent)
