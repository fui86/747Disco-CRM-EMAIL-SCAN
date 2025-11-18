# 🔧 File da Modificare per il Fix Rinomina

## ✅ Modifiche già Applicate Automaticamente

### 1. `/workspace/includes/storage/class-disco747-googledrive.php`
**RIGA 639** - Aggiunto metodo `rename_file()`

Questo metodo è già stato aggiunto e funziona correttamente.

### 2. `/workspace/includes/handlers/class-disco747-forms.php`
**Tutte le modifiche sono già state applicate:**

- ✅ RIGA 30: Rimosso trait (ora tutto integrato direttamente)
- ✅ RIGA 234: Query aggiornata per leggere `googledrive_file_id`
- ✅ RIGA 331: Aggiunta chiamata a `handle_google_drive_rename()`
- ✅ RIGA 334-362: Skip rigenerazione se rinomina succede
- ✅ RIGA 1031: Salva `googledrive_file_id` nel database
- ✅ RIGA 161-165: Salva `file_id` dopo upload
- ✅ RIGA 1087-1153: Aggiunti metodi helper `handle_google_drive_rename()` e `get_googledrive_instance()`

## ✅ TUTTO PRONTO!

**Non devi modificare nulla manualmente!** Tutte le modifiche sono già state applicate automaticamente.

## 🧪 Test Immediato

Prova subito:

1. Vai su un preventivo esistente
2. Cambia lo stato da "Attivo" a "Annullato"
3. Salva

**Verifica:**
- ✅ Nel log vedrai: `[Forms] File rinominato su Google Drive con successo!`
- ✅ Su Google Drive il file avrà "NO " davanti
- ✅ NON ci saranno file duplicati

## 📊 File Modificati

| File | Stato | Righe Aggiunte |
|------|-------|----------------|
| `includes/storage/class-disco747-googledrive.php` | ✅ Modificato | +60 righe |
| `includes/handlers/class-disco747-forms.php` | ✅ Modificato | +90 righe |
| **TOTALE** | **2 file** | **~150 righe** |

## 🎯 Cosa Fa il Fix

### Prima:
```
Cambio stato → Rigenera Excel → Upload nuovo file → ❌ 2 file su Drive
```

### Dopo:
```
Cambio stato → Rinomina file esistente su Drive → ✅ 1 solo file
```

## 🔍 Verifica che Funzioni

Controlla il log dopo aver modificato un preventivo:

**✅ Successo:**
```
[Forms] Nome file cambiato:
[Forms]    Vecchio: 17_11 18 Anni di Melissa (Menu 7).xlsx  
[Forms]    Nuovo: NO 17_11 18 Anni di Melissa (Menu 7).xlsx
[Forms] Rinomina file su Google Drive (ID: 1Wtw...)...
[GoogleDrive] Rinomina file...
[GoogleDrive] ✅ File rinominato con successo
[Forms] ✅ Rinomina completata, salto rigenerazione Excel
```

**⚠️ Fallback (preventivi vecchi senza file_id):**
```
[Forms] googledrive_file_id mancante, impossibile rinominare
[Forms] Rinomina fallita o non necessaria, procedo con rigenera + upload...
```

---

## ✅ CONCLUSIONE

**Tutto è già configurato e pronto all'uso!**

Nessun file aggiuntivo creato, tutto integrato nei file esistenti del plugin.
