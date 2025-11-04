# 🔧 Guida Configurazione Google Drive - 747 Disco CRM

## ❌ Problema Risolto

**Situazione**: Dopo la migrazione su sottodominio, Google Drive risultava "Non Configurato" nonostante le credenziali inserite.

**Causa**: Mancavano 3 componenti essenziali nel codice:
1. ❌ Handler PHP per salvare le credenziali Google Drive
2. ❌ JavaScript per gestire il pulsante "Autorizza Accesso"
3. ❌ Handler PHP per ricevere il callback OAuth di Google

## ✅ Modifiche Applicate

### File Modificati

1. **`/includes/admin/views/settings-page.php`**
   - ✅ Aggiunto handler per salvare credenziali (`save_gd_settings`)
   - ✅ Aggiunto handler per callback OAuth (`action=google_callback`)
   - ✅ Aggiunto JavaScript per pulsante autorizzazione
   - ✅ Aggiunto handler AJAX per test connessione

2. **`/includes/admin/class-disco747-admin.php`**
   - ✅ Aggiunto hook AJAX: `disco747_test_googledrive`
   - ✅ Aggiunto metodo: `handle_test_googledrive()`

3. **`/includes/storage/class-disco747-storage-manager.php`**
   - ✅ Aggiunto metodo: `get_googledrive()` (alias compatibilità)

---

## 📋 Procedura di Configurazione (Step-by-Step)

### Step 1: Preparazione Google Cloud Console

1. Vai su: https://console.cloud.google.com/apis/credentials?project=ambient-sphere-466314-c9
2. Seleziona il tuo progetto esistente
3. Vai su **"Credentials"** → **"OAuth 2.0 Client IDs"**
4. Clicca sull'OAuth Client che vuoi usare (o creane uno nuovo)

### Step 2: Aggiorna Authorized Redirect URIs

Nel tuo caso, l'URL redirect è:
```
https://[TUO-SOTTODOMINIO]/wp-admin/admin.php?page=disco747-settings&action=google_callback
```

**Esempio** (sostituisci con il tuo sottodominio):
```
https://crm.747disco.it/wp-admin/admin.php?page=disco747-settings&action=google_callback
```

⚠️ **IMPORTANTE**: L'URL deve essere **ESATTAMENTE** uguale, inclusi `/wp-admin/` e tutti i parametri.

**Come aggiungerlo**:
1. Nella sezione "Authorized redirect URIs"
2. Clicca su **"+ ADD URI"**
3. Incolla l'URL completo
4. Clicca su **"SAVE"**

### Step 3: Copia Client ID e Client Secret

1. Nella pagina OAuth Client, copia:
   - **Client ID**: `xxxxx.apps.googleusercontent.com`
   - **Client Secret**: `GOCSPX-xxxxx`

### Step 4: Configura il Plugin WordPress

1. Accedi al tuo WordPress sul sottodominio
2. Vai su **PreventiviParty → Impostazioni**
3. Scorri fino alla sezione **"📁 Configurazione Google Drive OAuth 2.0"**

#### 4.1 - Copia l'URL Redirect

Nella sezione blu troverai l'URL redirect:
```
🔗 URL Redirect (Copia in Google Cloud Console)
[URL completo qui]
📋 Copia URL
```

Clicca su **"📋 Copia URL"** e assicurati che sia lo stesso inserito in Google Cloud Console.

#### 4.2 - Inserisci le Credenziali

1. Incolla il **Client ID** nel primo campo
2. Incolla il **Client Secret** nel secondo campo
3. Clicca su **"💾 Salva Configurazione"**

✅ Dovresti vedere: **"✅ Credenziali Google Drive salvate! Ora puoi autorizzare l'accesso."**

### Step 5: Autorizza l'Accesso

1. Clicca sul pulsante verde **"🔗 Autorizza Accesso Google Drive"**
2. Verrai reindirizzato a Google
3. Seleziona l'account Google da usare
4. Clicca su **"Consenti"** per dare i permessi
5. Verrai reindirizzato automaticamente al plugin

✅ Dovresti vedere: **"✅ Google Drive configurato con successo!"**

### Step 6: Verifica la Connessione

1. La sezione Google Drive mostrerà: **"✅ Google Drive Configurato e Connesso"**
2. Apparirà il pulsante **"🔬 Test Connessione"**
3. Clicca su **"🔬 Test Connessione"**
4. Dovresti vedere un popup: **"✅ Connessione Google Drive OK!"** con nome utente ed email

---

## 🔍 Troubleshooting

### Problema: "Redirect URI mismatch"

**Causa**: L'URL redirect in Google Cloud Console non corrisponde esattamente.

**Soluzione**:
1. Vai su Google Cloud Console
2. Verifica che l'URL sia **identico** (carattere per carattere)
3. Assicurati di aver cliccato "SAVE" dopo averlo aggiunto
4. Attendi 1-2 minuti per la propagazione delle modifiche

### Problema: "Refresh token non ricevuto"

**Causa**: Google non invia il refresh token se l'app è già stata autorizzata.

**Soluzione**:
1. Vai su: https://myaccount.google.com/permissions
2. Trova l'app "747 Disco CRM" (o il nome del tuo progetto)
3. Clicca su **"Rimuovi accesso"**
4. Torna al plugin e riautorizza con il pulsante verde
5. Google ora ti chiederà nuovamente i permessi e invierà il refresh token

### Problema: "Invalid credentials"

**Causa**: Client ID o Client Secret errati.

**Soluzione**:
1. Vai su Google Cloud Console
2. Copia nuovamente le credenziali
3. Assicurati di non aver copiato spazi prima/dopo
4. Salva nuovamente nel plugin

### Problema: "Google Drive non disponibile"

**Causa**: API Google Drive non abilitata nel progetto.

**Soluzione**:
1. Vai su: https://console.cloud.google.com/apis/library
2. Cerca "Google Drive API"
3. Clicca su **"ENABLE"**
4. Attendi l'attivazione (30-60 secondi)
5. Riprova l'autorizzazione

---

## 📊 Verifica Configurazione Completa

Una volta configurato correttamente, dovresti vedere:

### ✅ Nella pagina Impostazioni:

```
📁 Configurazione Google Drive OAuth 2.0
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

✅ Google Drive Configurato e Connesso
Connessione OAuth2 attiva. I preventivi vengono salvati automaticamente.

🔑 Token attivo: ••••abc12345
```

### ✅ Funzionalità Attive:

- [ ] **Salvataggio automatico PDF** su Google Drive
- [ ] **Scansione automatica Excel** da Google Drive
- [ ] **Organizzazione cartelle** per Anno/Mese
- [ ] **Link condivisione** automatici

---

## 🔐 Sicurezza

### Dati Salvati nel Database WordPress:

- `disco747_gd_credentials` → Array con:
  - `client_id` (pubblico)
  - `client_secret` (sensibile, ma criptato da WordPress)
  - `redirect_uri` (pubblico)
  - `refresh_token` (sensibile, per rinnovo automatico token)

- `disco747_googledrive_access_token` → Token temporaneo (scade ogni ora)
- `disco747_googledrive_token_expires` → Timestamp scadenza

⚠️ **Non condividere mai**:
- Client Secret
- Refresh Token
- Access Token

---

## 📝 Log e Debug

Se hai problemi, puoi controllare i log WordPress:

### Via WordPress Debug Log:
```php
// In wp-config.php, abilita:
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
```

### Via Browser Console:
1. Apri la pagina Impostazioni
2. Premi F12 (DevTools)
3. Vai su "Console"
4. Riprova l'autorizzazione
5. Dovresti vedere: `🔗 URL autorizzazione: https://accounts.google.com/...`

---

## 🆘 Supporto

Se continui ad avere problemi:

1. **Verifica che tutte le modifiche siano state salvate**:
   - Ricarica la pagina con CTRL+F5 (svuota cache)
   - Verifica che il pulsante "Autorizza" funzioni

2. **Controlla i log PHP**:
   - `wp-content/debug.log` (se WP_DEBUG attivo)
   - Log server PHP (chiedi al tuo hosting)

3. **Testa l'API manualmente**:
   - Usa il pulsante "🔬 Test Connessione"
   - Guarda i messaggi di errore specifici

---

## ✅ Checklist Finale

Prima di considerare la configurazione completa, verifica:

- [ ] URL redirect aggiunto in Google Cloud Console
- [ ] Client ID e Client Secret salvati nel plugin
- [ ] Autorizzazione completata con successo
- [ ] Test connessione passato
- [ ] Storage Type impostato su "Google Drive"
- [ ] Messaggio "✅ Google Drive Configurato" visibile

---

**Data ultima modifica**: 2025-11-04
**Versione plugin**: 11.8.0+fix
**Autore fix**: AI Assistant

---

🎉 **Configurazione completata!** Ora i tuoi preventivi verranno salvati automaticamente su Google Drive.
