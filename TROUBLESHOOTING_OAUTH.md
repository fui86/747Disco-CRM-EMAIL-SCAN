# 🔧 Troubleshooting Google Drive OAuth2

## ✅ Modifiche Completate

### 1. **Plugin Principale (`747disco-crm.php`)**
- ✅ Aggiunte proprietà `$googledrive_handler` e `$dropbox_handler`
- ✅ Inizializzazione dei handler in `init_core_components()`
- ✅ Aggiunto metodo pubblico `get_googledrive_handler()`
- ✅ Aggiunto metodo pubblico `get_dropbox_handler()`

### 2. **Pagine Impostazioni**
- ✅ Aggiunto gestore callback OAuth in `settings-page.php` e `settings-page1.php`
- ✅ Aggiunto form handler per salvare credenziali
- ✅ Migliorata logica verifica configurazione (usa handler + fallback)
- ✅ Aggiunte info di debug visibili nella pagina

### 3. **Classe GoogleDrive**
- ✅ Metodo `exchange_code_for_tokens()` già presente e funzionante
- ✅ Metodo `is_oauth_configured()` già presente
- ✅ Metodo `generate_auth_url()` già presente

## 🔍 Come Verificare il Problema

### Passo 1: Controlla le Info di Debug
Nella pagina **PreventiviParty → Impostazioni**, scorri alla sezione **"📁 Configurazione Google Drive OAuth 2.0"**.

Dovresti vedere una sezione **"🔍 Debug Info"** che mostra:

```
Client ID: ✅ Presente / ❌ Mancante
Client Secret: ✅ Presente / ❌ Mancante  
Refresh Token: ✅ Presente (XX chars) / ❌ Mancante
Access Token: ✅ Presente (XX chars) / ❌ Mancante
Redirect URI: ✅ [URL] / ❌ Mancante
```

### Passo 2: Identifica il Problema

#### Scenario A: "Client ID: ❌ Mancante" o "Client Secret: ❌ Mancante"
**Problema:** Le credenziali non sono state salvate

**Soluzione:**
1. Inserisci Client ID e Client Secret nei campi
2. Clicca **"💾 Salva Configurazione"**
3. Verifica che appaia: "✅ Credenziali Google Drive salvate!"
4. Ricarica la pagina
5. Le info di debug dovrebbero ora mostrare "✅ Presente"

#### Scenario B: Client ID e Secret presenti, ma "Refresh Token: ❌ Mancante"
**Problema:** L'autorizzazione OAuth non è stata completata

**Soluzione:**
1. Verifica che il Redirect URI in Google Cloud Console sia ESATTAMENTE uguale a quello mostrato nella pagina
2. Clicca **"🔗 Autorizza Accesso Google Drive"**
3. Nella popup di Google, seleziona il tuo account e clicca "Consenti"
4. Dovresti vedere: "✅ Google Drive configurato con successo!"
5. Dopo 2 secondi verrai reindirizzato
6. Refresh Token dovrebbe ora mostrare "✅ Presente"

#### Scenario C: Tutto presente ma ancora "❌ Google Drive Non Configurato"
**Problema:** La verifica dello stato non funziona correttamente

**Soluzione di debug:**
1. Apri la console del browser (F12)
2. Vai su **Network** 
3. Ricarica la pagina impostazioni
4. Controlla se ci sono errori JavaScript
5. Controlla i log PHP (se hai accesso):
   ```
   tail -f /path/to/wordpress/wp-content/debug.log | grep "747Disco"
   ```

### Passo 3: Controlla il Database Direttamente

Accedi al database WordPress e verifica:

```sql
-- Controlla le credenziali salvate
SELECT * FROM wp_options WHERE option_name = 'disco747_gd_credentials';

-- Controlla il refresh token
SELECT * FROM wp_options WHERE option_name LIKE '%googledrive%';
```

Dovresti vedere:
- `disco747_gd_credentials`: un array serializzato con client_id, client_secret, redirect_uri, refresh_token
- `disco747_googledrive_access_token`: il token di accesso attuale
- `disco747_googledrive_token_expires`: timestamp di scadenza

### Passo 4: Verifica URL di Redirect

**CRITICO:** Il Redirect URI deve essere IDENTICO in entrambi i posti:

1. **Google Cloud Console** → Credentials → OAuth 2.0 Client → "Authorized redirect URIs"
2. **Pagina Impostazioni WordPress** → "🔗 URL Redirect"

Esempio:
```
https://tuodominio.com/wp-admin/admin.php?page=disco747-settings&action=google_callback
```

❌ **ERRORI COMUNI:**
- `http://` invece di `https://`
- Spazio extra prima o dopo l'URL
- Manca `&action=google_callback`
- Dominio diverso (es. www. presente in uno ma non nell'altro)

## 🔄 Flusso Completo OAuth2

Ecco cosa DOVREBBE succedere:

```
1. Utente va su Impostazioni
   → Debug Info mostra: Client ID ❌, Refresh Token ❌

2. Utente inserisce Client ID e Client Secret
   → Clicca "Salva Configurazione"
   → Messaggio: "✅ Credenziali salvate!"
   → Debug Info mostra: Client ID ✅, Client Secret ✅, Refresh Token ❌

3. Utente clicca "Autorizza Accesso Google Drive"
   → Si apre popup Google
   → JavaScript chiama: generate_auth_url() 
   → Redirect a Google OAuth

4. Utente autorizza su Google
   → Google fa redirect a: ...?code=XXX&state=YYY
   → Callback handler intercetta la richiesta
   → Chiama: exchange_code_for_tokens(code, state)
   
5. Exchange Code for Tokens
   → Invia code a Google OAuth endpoint
   → Google restituisce: access_token + refresh_token
   → Salva in database: disco747_gd_credentials['refresh_token']
   → Salva: disco747_googledrive_access_token
   → Messaggio: "✅ Google Drive configurato con successo!"

6. Dopo redirect
   → Pagina Impostazioni ricaricata
   → Debug Info mostra: TUTTO ✅
   → Status: "✅ Google Drive Configurato e Connesso"
```

## 🐛 Log di Debug

Se il problema persiste, controlla questi file di log:

### WordPress Debug Log
```bash
# Abilita debug in wp-config.php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);

# Controlla il log
tail -f wp-content/debug.log
```

Cerca righe con:
```
[747Disco-CRM] [GoogleDrive] [INFO] ...
[747Disco-CRM] [GoogleDrive] [ERROR] ...
[747Disco] Errore verifica GoogleDrive: ...
```

### Browser Console
Apri DevTools (F12) e cerca:
```javascript
console errors
Network errors (tab Network)
Failed requests to googleapis.com
```

## 📝 Checklist Completa

Prima di contattare il supporto, verifica:

- [ ] Client ID e Client Secret inseriti e salvati
- [ ] Redirect URI identico in Google Console e WordPress
- [ ] Google Drive API abilitata in Google Cloud Console
- [ ] Nessun errore nella console del browser
- [ ] Debug Info mostra Client ID ✅ e Client Secret ✅
- [ ] Hai cliccato "Autorizza Accesso Google Drive"
- [ ] Hai autorizzato l'accesso nella popup di Google
- [ ] Hai visto il messaggio "✅ Google Drive configurato con successo!"
- [ ] Dopo il redirect, Debug Info mostra Refresh Token ✅

## 🆘 Se Niente Funziona

1. **Resetta completamente:**
   ```sql
   DELETE FROM wp_options WHERE option_name LIKE '%disco747%google%';
   DELETE FROM wp_options WHERE option_name = 'disco747_gd_credentials';
   ```

2. **Ricomincia da zero:**
   - Vai su Google Cloud Console
   - ELIMINA le vecchie credenziali OAuth
   - CREA nuove credenziali OAuth 2.0
   - Assicurati che il Redirect URI sia corretto
   - Torna su WordPress e riconfigura

3. **Verifica permessi file:**
   ```bash
   # Il plugin deve poter scrivere nel database
   # Verifica che WordPress possa fare update_option()
   ```

4. **Prova con browser in incognito:**
   - Svuota cache del browser
   - Cancella cookies per il dominio
   - Prova in modalità incognito

## 📧 Info da Fornire al Supporto

Se devi contattare il supporto, fornisci:

1. Screenshot della sezione Debug Info
2. URL del tuo sito (dominio)
3. Versione PHP: `<?php echo phpversion(); ?>`
4. Versione WordPress: (vedi Dashboard)
5. Contenuto di questi errori:
   - Errori JavaScript nella console
   - Errori PHP nel debug.log
6. Screenshot di Google Cloud Console → Credentials
7. Conferma che hai seguito TUTTI i passi sopra

## ✅ Test Finale

Una volta configurato correttamente, testa:

1. Vai su **PreventiviParty → Nuovo Preventivo**
2. Compila e salva un preventivo
3. Controlla che venga caricato su Google Drive
4. Verifica la struttura cartelle: `/747-Preventivi/ANNO/MESE/`
5. Clicca sul link "📂 Visualizza File Drive" per vedere i file

---

**Data ultima modifica:** 2025-11-04
**Versione plugin:** 11.8.0
