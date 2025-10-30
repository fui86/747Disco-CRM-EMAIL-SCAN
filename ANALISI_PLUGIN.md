# Analisi Plugin 747 Disco CRM

## 📋 Panoramica Generale

**Nome Plugin:** 747 Disco CRM - PreventiviParty Enhanced  
**Versione:** 11.8.0  
**Tipo:** Plugin WordPress per gestione CRM di preventivi  
**Scopo:** Sistema completo per la gestione dei preventivi della location 747 Disco, replica del vecchio PreventiviParty con funzionalità avanzate

---

## 🗂️ Struttura del Plugin (da alberatura.txt)

Il file `alberatura.txt` documenta la struttura originale del volume "Disco Dati" su cui era basato il plugin. La struttura è organizzata in modo modulare:

```
747disco-crm.php (file principale)
├── assets/
│   ├── css/ (admin.css, excel-scan.css, frontend.css)
│   └── js/ (admin.js, excel-scan.js, frontend.js, preventivo-form.js)
└── includes/
    ├── admin/ (gestione backend WordPress)
    ├── communication/ (email, messaging, WhatsApp)
    ├── core/ (config, database, auth)
    ├── frontend/ (dashboard utenti, pagine pubbliche)
    ├── generators/ (Excel, PDF, templates)
    ├── handlers/ (AJAX, forms, excel scan, processor)
    └── storage/ (Google Drive, Dropbox, storage manager)
```

---

## 🏗️ Architettura del Plugin

### 1. **Classe Principale: Disco747_CRM_Plugin**
- **Pattern:** Singleton
- **Responsabilità:** Inizializzazione plugin, gestione componenti, hook WordPress
- **Componenti gestiti:**
  - Config Manager
  - Database Manager
  - Auth Manager
  - Admin Manager
  - Storage Manager
  - Email Manager
  - PDF/Excel Generators
  - Forms Handler

### 2. **Core Components (`includes/core/`)**

#### **Disco747_Config** (Singleton)
- Gestione configurazioni centralizzata
- Prefisso opzioni: `disco747_crm_`
- Migrazione automatica da vecchio plugin PreventiviParty
- Configurazioni per:
  - Company info (nome, email, telefono, indirizzo, P.IVA)
  - Storage (Dropbox/Google Drive)
  - Email settings (SMTP, templates)
  - WhatsApp integration
  - Security settings
  - Performance & caching

#### **Disco747_Database**
- Tabella principale: `wp_disco747_preventivi`
- Versioning struttura tabella (aggiornamenti automatici)
- Colonne principali:
  - Dati evento: `data_evento`, `tipo_evento`, `orario_inizio`, `orario_fine`, `numero_invitati`
  - Dati cliente: `nome_cliente`, `nome_referente`, `cognome_referente`, `telefono`, `email`
  - Dati economici: `importo_totale`, `importo_preventivo`, `acconto`, `saldo`
  - Extra: `extra1`, `extra1_importo`, `extra2`, `extra2_importo`, `extra3`, `extra3_importo`
  - Omaggi: `omaggio1`, `omaggio2`, `omaggio3`
  - Metadati: `excel_url`, `pdf_url`, `googledrive_url`, `googledrive_file_id`
- Metodi: insert, update, upsert, get, delete, stats

#### **Disco747_Auth**
- Integrazione con sistema autenticazione WordPress
- Gestione permessi basata su ruoli (`admin`, `staff`)
- Session management semplificato
- Hook AJAX per login/logout

### 3. **Admin Components (`includes/admin/`)**

#### **Disco747_Admin**
- Gestione menu WordPress admin
- Pagine amministrative:
  - Dashboard principale (`disco747-crm`)
  - Impostazioni (`disco747-settings`)
  - Messaggi Automatici (`disco747-messages`)
  - Scansione Excel Auto (`disco747-scan-excel`)
  - View Database (`disco747-view-preventivi`)
  - Debug & Test (solo se debug attivo)
- AJAX handlers per:
  - Batch scan Excel
  - Get/Delete preventivo
  - Export CSV

### 4. **Storage (`includes/storage/`)**

#### **Disco747_Storage_Manager**
- Gestione unificata storage (Google Drive / Dropbox)
- Metodi principali:
  - `upload_file($file_path, $folder_path)` - Upload con supporto cartelle
  - `download_file()` - Download da storage remoto
  - `list_files()` - Lista file
  - `create_folder()` - Crea cartelle
  - `get_active_handler()` - Ottiene handler attivo

#### **Disco747_GoogleDrive**
- Integrazione Google Drive API
- OAuth 2.0 authentication
- Gestione refresh token
- Upload/Download file
- Ricerca file per query

#### **Disco747_Dropbox**
- Integrazione Dropbox API
- Backup storage alternativo

### 5. **Generators (`includes/generators/`)**

#### **Disco747_Excel**
- Generazione preventivi Excel da template
- Template supportati:
  - Menu 7 → `Menu 7.xlsx`
  - Menu 7-4 → `Menu 7 - 4.xlsx`
  - Menu 7-4-7 → `Menu 7 - 4 - 7.xlsx`
- Mapping celle specifiche:
  - B1 → tipo_menu
  - C6-C9 → dati evento
  - C11-C15 → dati referente
  - C17-C19 → omaggi
  - F27-F30 → calcoli economici
  - C33-C35, F33-F35 → extra
- **Calcoli economici:**
  - Sconti all-inclusive per menu (Menu 7: €400, Menu 7-4: €500, Menu 7-4-7: €600)
  - Totale = importo_base + somma(extra)
  - Totale parziale = totale - sconto_all_inclusive
  - Saldo = totale - acconto
- Nome file formato: `[CONF/NO]DD_MM Tipo Evento (Menu X).xlsx`

#### **Disco747_PDF**
- Generazione PDF preventivi (presumibilmente da template)

### 6. **Handlers (`includes/handlers/`)**

#### **Disco747_Excel_Scan_Handler**
- **Funzionalità principale:** Scansione automatica file Excel da Google Drive
- **Processo:**
  1. Ricerca file `.xlsx` in cartelle strutturate: `747-Preventivi/YYYY/MM/`
  2. Download file da Google Drive
  3. Parsing con PhpSpreadsheet
  4. Estrazione dati secondo mapping definito
  5. Salvataggio in tabella `wp_disco747_preventivi`
  6. Deduplicazione tramite `googledrive_file_id`
- **Mapping parsing:**
  - B1 → tipo_menu
  - C6 → data_evento
  - C7 → tipo_evento
  - C8 → orario_evento
  - C9 → numero_invitati
  - C11-C15 → dati referente
  - C17-C19 → omaggi
  - C21, C23 → importi
  - C33-C35, F33-F35 → extra con importi
- **Stati preventivo:** determinati da prefisso filename (`CONF `, `NO `)

#### **Disco747_Forms**
- Gestione form frontend per inserimento preventivi

#### **Disco747_AJAX**
- Handlers AJAX centralizzati

### 7. **Communication (`includes/communication/`)**

#### **Disco747_Email**
- Invio email automatiche
- Template personalizzabili con placeholders (`{{nome_referente}}`, `{{data_evento}}`)
- Supporto SMTP

#### **Disco747_WhatsApp**
- Integrazione WhatsApp per notifiche

#### **Disco747_Messaging**
- Sistema messaging interno

---

## 🔄 Flussi di Lavoro Principali

### 1. **Creazione Preventivo Manuale**
```
Form Preventivo → Salvataggio DB → Generazione Excel → Upload Google Drive → Invio Email/WhatsApp
```

### 2. **Scansione Automatica Excel**
```
Google Drive Scan → Download Excel → Parsing PhpSpreadsheet → Estrazione Dati → Upsert DB → Notifica
```

### 3. **Sincronizzazione Storage**
```
File Locale → Storage Manager → Upload Google Drive/Dropbox → Salvataggio URL → Update DB
```

---

## 📊 Schema Database

### Tabella: `wp_disco747_preventivi`

| Colonna | Tipo | Descrizione |
|---------|------|-------------|
| `id` | bigint(20) | Primary key |
| `preventivo_id` | varchar(50) | ID preventivo generato (#001, #002...) |
| `data_evento` | date | Data evento |
| `tipo_evento` | varchar(100) | Tipo evento (compleanno, matrimonio...) |
| `tipo_menu` | varchar(50) | Menu selezionato (Menu 7, Menu 7-4, Menu 7-4-7) |
| `numero_invitati` | int(11) | Numero invitati |
| `orario_evento` | varchar(50) | Orario evento |
| `orario_inizio` | varchar(50) | Orario inizio (default: 20:30) |
| `orario_fine` | varchar(50) | Orario fine (default: 01:30) |
| `nome_cliente` | varchar(200) | Nome cliente |
| `nome_referente` | varchar(100) | Nome referente |
| `cognome_referente` | varchar(100) | Cognome referente |
| `telefono` | varchar(50) | Telefono |
| `email` | varchar(100) | Email |
| `importo_totale` | decimal(10,2) | Importo base |
| `importo_preventivo` | decimal(10,2) | Importo totale (base + extra) |
| `acconto` | decimal(10,2) | Acconto versato |
| `saldo` | decimal(10,2) | Saldo residuo |
| `omaggio1-3` | varchar(200) | Omaggi inclusi |
| `extra1-3` | varchar(200) | Descrizione extra |
| `extra1_importo - extra3_importo` | decimal(10,2) | Importo extra |
| `note_aggiuntive` | text | Note pubbliche |
| `note_interne` | text | Note interne staff |
| `stato` | varchar(20) | Stato (attivo, confermato, annullato) |
| `excel_url` | text | URL file Excel |
| `pdf_url` | text | URL file PDF |
| `googledrive_url` | text | URL Google Drive |
| `googledrive_file_id` | varchar(100) | ID file Google Drive (per deduplicazione) |
| `created_at` | datetime | Data creazione |
| `created_by` | bigint(20) | ID utente creatore |
| `updated_at` | datetime | Data ultimo aggiornamento |

**Indici:**
- PRIMARY KEY (`id`)
- KEY `preventivo_id`
- KEY `data_evento`
- KEY `stato`
- KEY `googledrive_file_id`

---

## 🎯 Funzionalità Chiave

### ✅ Implementate

1. **Gestione Preventivi Completa**
   - Creazione manuale via form
   - Modifica e cancellazione
   - Visualizzazione dashboard

2. **Generazione Excel**
   - Template predefiniti per menu
   - Calcoli economici automatici
   - Naming convention standardizzata

3. **Scansione Automatica**
   - Scan ricorsivo Google Drive
   - Parsing Excel con PhpSpreadsheet
   - Deduplicazione intelligente

4. **Storage Cloud**
   - Google Drive integration
   - Dropbox support
   - Upload automatico strutturato

5. **Comunicazione**
   - Email automatiche
   - WhatsApp notifications
   - Template personalizzabili

6. **Dashboard Admin**
   - Statistiche preventivi
   - Export CSV
   - Filtri avanzati

### ⚠️ Note Tecniche

1. **Dipendenze:**
   - WordPress 5.8+
   - PHP 7.4+
   - PhpSpreadsheet (per Excel generation/parsing)
   - Google Drive API v3
   - Dropbox API

2. **File Duplicati:**
   - `includes/admin/` contiene molti file duplicati:
     - `class-disco747-admin(1).php` fino a `(5).php`
     - `excel-scan-page(1).php` fino a `(3).php`
     - `form-preventivo(1).php`
     - `settings-page1.php`
   - **Raccomandazione:** Pulizia file duplicati/backup

3. **Configurazione Storage:**
   - Google Drive richiede OAuth 2.0 setup
   - Token refresh automatico
   - Folder structure: `747-Preventivi/YYYY/MM/`

4. **Compatibilità:**
   - Migrazione automatica da vecchio plugin "PreventiviParty"
   - Tabella legacy: `wp_preventivi_party`

---

## 🔍 Osservazioni da alberatura.txt

Il file `alberatura.txt` mostra:
- **Numero serie volume:** 3459-92AD
- **Percorso originale:** Disco Dati (D:)
- **Struttura cartelle:** Mantenuta nel plugin WordPress
- **File mancanti rispetto ad alberatura:**
  - `class-disco747-excel-parser.php` (potrebbe essere sostituito da `class-disco747-excel-scan-handler.php`)

---

## 📝 Raccomandazioni

1. **Pulizia Codice:**
   - Rimuovere file duplicati in `includes/admin/`
   - Consolidare versioni multiple delle classi

2. **Documentazione:**
   - Aggiungere PHPDoc completo
   - Documentare API interne

3. **Testing:**
   - Unit test per calcoli economici
   - Integration test per Google Drive sync
   - Test parsing Excel

4. **Performance:**
   - Caching per query frequenti
   - Batch processing per scan multipli
   - Rate limiting per API Google Drive

5. **Sicurezza:**
   - Sanitizzazione input completata
   - Nonce verification presente
   - Capability checks implementati

---

## 🚀 Versioni e Changelog

- **v11.8.0** - Versione corrente
- **v11.9.0** - Fix Google Drive check (priorità token)
- **v12.0.0-STEP1** - Excel Generator con mapping completo

---

## 📞 Supporto

- **Author:** 747 Disco Team
- **Website:** https://747disco.it
- **Text Domain:** disco747

---

*Analisi completata il: {{timestamp}}*
