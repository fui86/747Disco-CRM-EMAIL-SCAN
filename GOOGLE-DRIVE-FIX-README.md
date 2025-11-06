# 🔧 Fix Configurazione Google Drive OAuth2 - 747 Disco CRM

## 📋 Problema Risolto

Il plugin mostrava sempre "Google Drive Non Configurato" anche dopo aver inserito le credenziali OAuth2. Questo era causato da **credenziali salvate in chiavi diverse** del database WordPress.

## ✅ Modifiche Applicate

### 1. **Aggiornamento classe GoogleDrive** (`includes/storage/class-disco747-googledrive.php`)

- ✅ **Metodo `get_oauth_credentials()`**: Ora cerca le credenziali in **tutte le possibili chiavi**:
  - `disco747_gd_credentials` (array unificato)
  - `disco747_googledrive_*` (nuovo sistema)
  - `preventivi_googledrive_*` (vecchio sistema - compatibilità)

- ✅ **Metodo `is_oauth_configured()`**: Log dettagliato per debug della configurazione

- ✅ **Metodo `exchange_code_for_tokens()`**: Salva il refresh_token in **tutte le chiavi** per compatibilità

- ✅ **Metodo `generate_auth_url()`**: Usa il redirect_uri corretto dalla pagina Storage

- ✅ **Nuovo metodo `sync_credentials()`**: Sincronizza automaticamente le credenziali tra tutte le chiavi

### 2. **Aggiornamento Settings Helper** (`includes/admin/class-disco747-settings-helper.php`)

- ✅ Quando salvi le credenziali, il sistema le sincronizza automaticamente in tutte le chiavi

### 3. **Aggiornamento Credentials Utils** (`includes/credentials-utils.php`)

- ✅ Nuova funzione `disco747_sync_googledrive_credentials()` per forzare la sincronizzazione
- ✅ Funzione `disco747_repair_configuration()` aggiornata per sincronizzare automaticamente

### 4. **Script di Utility** (`sync-google-credentials.php`)

- ✅ Script standalone per sincronizzare le credenziali esistenti
- ✅ Interfaccia web con diagnostica completa

---

## 🚀 Come Completare la Configurazione

### Opzione 1: Usa lo Script di Sincronizzazione (Raccomandato)

1. **Apri il browser** e vai su:
   ```
   https://tuosito.it/wp-content/plugins/747disco-crm/sync-google-credentials.php
   ```
   *(Sostituisci `tuosito.it` con il tuo dominio)*

2. Lo script mostrerà:
   - 📊 Stato attuale delle credenziali
   - 🔄 Esecuzione della sincronizzazione
   - ✅ Conferma di successo

3. Clicca su **"Vai a Storage Cloud"** per verificare

### Opzione 2: Autorizza OAuth2 dalla Pagina Storage

1. Vai su **WordPress Admin** → **747 Disco CRM** → **Storage Cloud**

2. Nella sezione **Google Drive**, dovresti vedere:
   - ✅ Client ID: presente
   - ✅ Client Secret: presente
   - ⚠️ Refresh Token: mancante (se non hai ancora autorizzato)

3. Clicca sul pulsante **"Autorizza Google Drive"**

4. Si aprirà una finestra popup di Google:
   - Seleziona il tuo account Google
   - Clicca su **"Consenti"**
   - La finestra si chiuderà automaticamente

5. La pagina si ricaricherà e vedrai:
   ```
   ✅ Google Drive Configurato e Connesso
   ```

---

## 🔍 Verifica della Configurazione

### Check 1: Stato Storage

Vai su **747 Disco CRM** → **Storage Cloud**

Dovresti vedere:
```
✅ Google Drive Connesso
I file vengono salvati automaticamente nel cloud.
```

### Check 2: Test Connessione

Clicca sul pulsante **"Test Connessione"**

Risultato atteso:
```
✅ Connessione Google Drive OK
Utente: tuoemail@gmail.com
```

### Check 3: Log di Debug

Se hai `DISCO747_CRM_DEBUG` abilitato nel file principale, controlla i log in `/wp-content/debug.log`:

```
[747Disco-CRM] [GoogleDrive] [INFO] ✅ Credenziali trovate in disco747_googledrive_* (nuovo sistema)
[747Disco-CRM] [GoogleDrive] [INFO] 🔍 Verifica configurazione OAuth:
[747Disco-CRM] [GoogleDrive] [INFO]    - Client ID: presente
[747Disco-CRM] [GoogleDrive] [INFO]    - Client Secret: presente
[747Disco-CRM] [GoogleDrive] [INFO]    - Refresh Token: presente
[747Disco-CRM] [GoogleDrive] [INFO]    - Risultato: ✅ CONFIGURATO
```

---

## 📝 Configurazione Google Cloud Console

Se non hai ancora creato le credenziali OAuth2:

### 1. Vai su Google Cloud Console
```
https://console.cloud.google.com/apis/credentials?project=ambient-sphere-466314-c9
```

### 2. Crea Credenziali OAuth 2.0

1. Clicca su **"CREATE CREDENTIALS"** → **"OAuth client ID"**
2. Tipo applicazione: **"Web application"**
3. Nome: `747 Disco CRM - Production`

### 3. Authorized Redirect URIs

Aggiungi **ESATTAMENTE** questo URI:
```
https://tuodominio.it/wp-admin/admin.php?page=disco747-storage&oauth_callback=googledrive
```

⚠️ **IMPORTANTE**: 
- Sostituisci `tuodominio.it` con il tuo dominio reale
- Includi `https://` all'inizio
- L'URI deve essere **identico** (case-sensitive)

### 4. Copia le Credenziali

Dopo aver creato le credenziali, copia:
- **Client ID**: `xxxxx.apps.googleusercontent.com`
- **Client Secret**: `GOCSPX-xxxxx`

### 5. Abilita Google Drive API

1. Vai su **APIs & Services** → **Library**
2. Cerca **"Google Drive API"**
3. Clicca su **"ENABLE"**

---

## 🐛 Troubleshooting

### Problema: "Redirect URI mismatch"

**Causa**: L'URI nella Google Console non corrisponde a quello usato dal plugin.

**Soluzione**:
1. Vai su Google Console
2. Modifica le credenziali OAuth2
3. Verifica che l'URI sia esattamente:
   ```
   https://tuodominio.it/wp-admin/admin.php?page=disco747-storage&oauth_callback=googledrive
   ```

### Problema: "Access blocked: 747 Disco CRM has not completed the Google verification process"

**Causa**: App in modalità test con troppi utenti.

**Soluzione**:
1. Vai su Google Console → **OAuth consent screen**
2. Aggiungi il tuo email come "Test user"
3. Oppure pubblica l'app (richiede verifica Google)

### Problema: "Refresh token non ricevuto"

**Causa**: Google non restituisce il refresh_token se l'app è già autorizzata.

**Soluzione**:
1. Vai su https://myaccount.google.com/permissions
2. Trova "747 Disco CRM" e rimuovi l'accesso
3. Riautorizza nuovamente dal plugin
4. Assicurati che l'URL contenga `prompt=consent`

### Problema: Credenziali ancora non riconosciute

**Soluzione**:
1. Esegui lo script di sincronizzazione: `/sync-google-credentials.php`
2. Oppure disattiva e riattiva il plugin
3. Controlla i log di debug

---

## 📂 Struttura Cartelle Google Drive

Quando un preventivo viene salvato, il plugin crea automaticamente questa struttura:

```
Google Drive/
└── 747-Preventivi/
    └── 2025/
        └── Novembre/
            └── Preventivo_2025-11-15_Mario_Rossi.pdf
```

La cartella viene determinata dalla **data dell'evento** (non dalla data di creazione).

---

## 🔐 Sicurezza

Le credenziali sono salvate nel database WordPress con le seguenti chiavi:

- `disco747_gd_credentials` (array serializzato)
- `disco747_googledrive_client_id`
- `disco747_googledrive_client_secret`
- `disco747_googledrive_refresh_token`
- `disco747_googledrive_redirect_uri`
- `disco747_googledrive_folder_id`

⚠️ **Non condividere mai** il Client Secret o il Refresh Token!

---

## 📞 Supporto

Se il problema persiste dopo aver seguito questa guida:

1. **Controlla i log**: `/wp-content/debug.log`
2. **Esegui lo script di sincronizzazione**
3. **Verifica le credenziali** nella Google Console
4. **Testa la connessione** dalla pagina Storage

---

## ✨ Riepilogo Veloce

```bash
1. ✅ Le modifiche al codice sono già applicate
2. 🔄 Esegui: /sync-google-credentials.php
3. 🔑 Vai su Storage Cloud
4. 🚀 Clicca "Autorizza Google Drive"
5. ✅ Completa l'autorizzazione OAuth2
6. 🎉 Google Drive è configurato!
```

---

**Data Fix**: 2025-11-04  
**Versione Plugin**: 11.8.0  
**Autore**: 747 Disco Team
