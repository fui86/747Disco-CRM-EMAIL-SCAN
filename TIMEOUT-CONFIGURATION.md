# ⏱️ Configurazione Timeout - 747 Disco CRM

Guida rapida per configurare i timeout per scansioni massive di file Excel da Google Drive.

## 📊 Configurazione Attuale

| Parametro | Valore | Descrizione |
|-----------|--------|-------------|
| **PHP Timeout** | 15 minuti | Tempo massimo esecuzione script PHP |
| **AJAX Timeout** | 15 minuti | Tempo massimo attesa risposta JavaScript |
| **Google Drive API** | 2 minuti | Timeout singola richiesta API |
| **Rate Limiting** | 100ms | Pausa tra download file consecutivi |
| **Memory Limit** | 512M | Memoria PHP disponibile |

---

## 🔧 Come Aumentare i Timeout

### Opzione 1: File Centralizzato (Consigliato)

Modifica il file: `includes/config-timeouts.php`

```php
// Per scansioni con 500+ file, aumenta a 30 minuti:
define('DISCO747_SCAN_PHP_TIMEOUT', 1800); // 30 minuti

// AJAX deve essere >= PHP timeout
define('DISCO747_SCAN_JS_TIMEOUT', 1800000); // 30 minuti in ms

// Richieste Google Drive API
define('DISCO747_GDRIVE_API_TIMEOUT', 180); // 3 minuti

// Velocizza riducendo pausa (con cautela!)
define('DISCO747_RATE_LIMIT_USLEEP', 50000); // 50ms (più veloce)

// Aumenta memoria per file Excel molto grandi
define('DISCO747_MEMORY_LIMIT', '1024M'); // 1GB
```

### Opzione 2: Modifica Manuale nei File

Se preferisci modificare direttamente nei file:

**PHP Timeout** - `includes/handlers/class-disco747-excel-scan-handler.php`:
```php
@set_time_limit(1800); // 30 minuti
```

**AJAX Timeout** - `assets/js/excel-scan.js`:
```javascript
timeout: 1800000, // 30 minuti
```

---

## 📈 Raccomandazioni per Volume File

| Numero File | PHP Timeout | AJAX Timeout | Rate Limit |
|-------------|-------------|--------------|------------|
| **< 50 file** | 5 min | 5 min | 200ms |
| **50-100 file** | 10 min | 10 min | 100ms |
| **100-200 file** | 15 min | 15 min | 100ms |
| **200-500 file** | 30 min | 30 min | 50ms |
| **500+ file** | 45 min | 45 min | 50ms |

---

## ⚠️ Limiti Server

Verifica che il tuo hosting permetta:
- ✅ `max_execution_time` >= timeout configurato
- ✅ `memory_limit` >= 512M (1GB consigliato)
- ✅ `upload_max_filesize` >= 20M
- ✅ `post_max_size` >= 20M

Per verificare, vai su: **PreventiviParty > Debug & Test > Info Sistema**

---

## 🚀 Ottimizzazioni Avanzate

### Riduci Rate Limiting (più veloce, ma attenzione ai limiti Google!)
```php
define('DISCO747_RATE_LIMIT_USLEEP', 50000); // 50ms invece di 100ms
```

### Aumenta Memory per File Grandi
```php
define('DISCO747_MEMORY_LIMIT', '1024M'); // 1GB invece di 512M
```

### Timeout Custom per Operazioni Specifiche
```php
// Nel tuo codice custom:
disco747_set_scan_timeout(3600); // 1 ora per operazioni speciali
```

---

## 📝 Note

- **Dopo modifiche**: Svuota cache browser (Ctrl+F5)
- **Test consigliato**: Prima con pochi file, poi aumenta gradualmente
- **Monitoraggio**: Controlla i log per timeout effettivi
- **Google Drive Quota**: Max 1000 richieste/100 secondi/utente

---

## 🆘 Troubleshooting

### "Errore connessione" anche dopo 15 minuti
→ Aumenta `DISCO747_SCAN_PHP_TIMEOUT` e `DISCO747_SCAN_JS_TIMEOUT`

### "Memory exhausted"
→ Aumenta `DISCO747_MEMORY_LIMIT` a 1024M o 2048M

### "Rate limit exceeded" da Google Drive
→ Aumenta `DISCO747_RATE_LIMIT_USLEEP` a 200000 (200ms)

### Server timeout prima di PHP timeout
→ Contatta hosting per aumentare `max_execution_time` del server

---

## 📞 Supporto

Per assistenza: 747 Disco Team
