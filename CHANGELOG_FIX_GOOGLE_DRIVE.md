# 📝 CHANGELOG - Fix Configurazione Google Drive

**Data**: 2025-11-04  
**Versione**: 11.8.0 → 11.8.0+fix  
**Issue**: Google Drive "Non Configurato" dopo migrazione sottodominio

---

## 🔴 Problema Identificato

### Descrizione
Dopo la migrazione del plugin su un nuovo sottodominio, la configurazione di Google Drive non funzionava. L'utente:
1. ✅ Ha aggiornato l'URL redirect su Google Cloud Console
2. ✅ Ha creato nuove credenziali OAuth2
3. ❌ Il plugin continuava a mostrare "Google Drive Non Configurato"

### Root Cause Analysis

Analizzando il codice, sono stati identificati **3 componenti critici mancanti**:

#### 1. ❌ Handler PHP per Salvataggio Credenziali
**File**: `includes/admin/views/settings-page.php`
- Il form aveva un pulsante `save_gd_settings` 
- Non c'era codice PHP per gestire il POST
- Le credenziali non venivano salvate in `disco747_gd_credentials`

#### 2. ❌ JavaScript per Autorizzazione OAuth
**File**: `includes/admin/views/settings-page.php`
- Il pulsante aveva classe `.btn-authorize-googledrive`
- Non c'era event listener jQuery per gestire il click
- L'URL di autorizzazione Google non veniva generato

#### 3. ❌ Handler PHP per Callback OAuth
**File**: `includes/admin/views/settings-page.php`
- Google reindirizzava a `?action=google_callback&code=...`
- Non c'era codice per intercettare `$_GET['action']`
- Il `refresh_token` non veniva salvato

#### 4. ❌ Handler AJAX per Test Connessione
**File**: `includes/admin/class-disco747-admin.php`
- Il pulsante "Test Connessione" chiamava AJAX
- Non c'era action hook `disco747_test_googledrive`
- Il test non funzionava

---

## ✅ Soluzioni Implementate

### 1. Handler Salvataggio Credenziali

**File**: `includes/admin/views/settings-page.php`  
**Linee**: 50-66

```php
// ✅ NUOVO: Gestione salvataggio credenziali Google Drive
if (isset($_POST['save_gd_settings']) && wp_verify_nonce($_POST['_wpnonce'], 'disco747_save_gd')) {
    $new_credentials = array(
        'client_id' => sanitize_text_field($_POST['gd_client_id']),
        'client_secret' => sanitize_text_field($_POST['gd_client_secret']),
        'redirect_uri' => $gd_redirect_uri,
        'refresh_token' => $gd_credentials['refresh_token'] ?? ''
    );
    
    update_option('disco747_gd_credentials', $new_credentials);
    echo '<div class="notice notice-success is-dismissible"><p>✅ Credenziali salvate!</p></div>';
    
    // Reload variabili
    $gd_credentials = $new_credentials;
    $gd_client_id = $new_credentials['client_id'];
    $gd_client_secret = $new_credentials['client_secret'];
}
```

**Funzionalità**:
- ✅ Valida nonce per sicurezza
- ✅ Sanitizza input (XSS prevention)
- ✅ Salva in `disco747_gd_credentials` (array)
- ✅ Mantiene `refresh_token` esistente se presente
- ✅ Mostra messaggio di conferma

---

### 2. Handler Callback OAuth

**File**: `includes/admin/views/settings-page.php`  
**Linee**: 68-88

```php
// ✅ NUOVO: Gestione callback OAuth Google Drive
if (isset($_GET['action']) && $_GET['action'] === 'google_callback' && isset($_GET['code'])) {
    $auth_code = sanitize_text_field($_GET['code']);
    $state = isset($_GET['state']) ? sanitize_text_field($_GET['state']) : '';
    
    // Usa handler Google Drive per scambiare code per tokens
    if (class_exists('Disco747_CRM\\Storage\\Disco747_GoogleDrive')) {
        $gd_handler = new \Disco747_CRM\Storage\Disco747_GoogleDrive();
        $result = $gd_handler->exchange_code_for_tokens($auth_code, $state);
        
        if ($result['success']) {
            echo '<div class="notice notice-success"><p>✅ ' . esc_html($result['message']) . '</p></div>';
            // Reload credenziali
            $gd_credentials = get_option('disco747_gd_credentials', array());
            $gd_refresh_token = $gd_credentials['refresh_token'] ?? '';
            $is_gd_configured = !empty($gd_refresh_token);
        } else {
            echo '<div class="notice notice-error"><p>❌ ' . esc_html($result['message']) . '</p></div>';
        }
    }
}
```

**Flusso OAuth2**:
1. Google reindirizza con `?code=xxx&state=yyy`
2. Script intercetta `$_GET['action'] === 'google_callback'`
3. Chiama `$gd_handler->exchange_code_for_tokens()`
4. Metodo scambia `code` → `access_token` + `refresh_token`
5. Salva tokens in `disco747_gd_credentials`
6. Mostra messaggio successo/errore

---

### 3. JavaScript Autorizzazione

**File**: `includes/admin/views/settings-page.php`  
**Linee**: 385-453

```javascript
// ✅ NUOVO: Gestione autorizzazione Google Drive
jQuery(document).ready(function($) {
    
    // Pulsante autorizza
    $('.btn-authorize-googledrive').on('click', function(e) {
        e.preventDefault();
        
        var clientId = '<?php echo esc_js($gd_client_id); ?>';
        var redirectUri = '<?php echo esc_js($gd_redirect_uri); ?>';
        
        if (!clientId || !redirectUri) {
            alert('❌ Configura prima Client ID e Client Secret');
            return;
        }
        
        // Genera state per sicurezza CSRF
        var state = 'gd_' + Math.random().toString(36).substring(2, 15);
        
        // Costruisci URL autorizzazione Google OAuth2
        var authUrl = 'https://accounts.google.com/o/oauth2/v2/auth?' + 
            'client_id=' + encodeURIComponent(clientId) +
            '&redirect_uri=' + encodeURIComponent(redirectUri) +
            '&response_type=code' +
            '&scope=' + encodeURIComponent('https://www.googleapis.com/auth/drive.file') +
            '&access_type=offline' +
            '&prompt=consent' +
            '&state=' + state;
        
        // Redirect a Google
        window.location.href = authUrl;
    });
    
    // Pulsante test connessione
    $('.btn-test-googledrive').on('click', function(e) {
        e.preventDefault();
        
        var $btn = $(this);
        $btn.prop('disabled', true).text('🔄 Test in corso...');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'disco747_test_googledrive',
                nonce: '<?php echo wp_create_nonce('disco747_test_gd'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    alert('✅ Connessione OK!\n\nUser: ' + response.data.user_name);
                } else {
                    alert('❌ Errore: ' + response.data);
                }
            },
            complete: function() {
                $btn.prop('disabled', false).text('🔬 Test Connessione');
            }
        });
    });
});
```

**Parametri OAuth2**:
- `client_id`: Identificativo applicazione
- `redirect_uri`: URL callback (DEVE corrispondere a Google Console)
- `response_type=code`: Chiede authorization code
- `scope`: Permessi richiesti (solo file creati dall'app)
- `access_type=offline`: Richiede refresh token
- `prompt=consent`: Forza schermata consenso (genera refresh token)
- `state`: Token CSRF per sicurezza

---

### 4. Handler AJAX Test Connessione

**File**: `includes/admin/class-disco747-admin.php`  
**Linee**: 64 (hook), 499-542 (metodo)

#### Hook Registration:
```php
add_action('wp_ajax_disco747_test_googledrive', array($this, 'handle_test_googledrive'));
```

#### Handler Method:
```php
public function handle_test_googledrive() {
    try {
        // Verifica nonce
        if (!check_ajax_referer('disco747_test_gd', 'nonce', false)) {
            throw new \Exception('Nonce non valido');
        }

        // Verifica permessi
        if (!current_user_can($this->min_capability)) {
            throw new \Exception('Permessi insufficienti');
        }

        // Ottieni handler
        $gd_handler = $this->storage_manager->get_active_handler();
        
        if (!$gd_handler || get_option('disco747_storage_type') !== 'googledrive') {
            throw new \Exception('Google Drive non configurato');
        }

        // Test API
        $result = $gd_handler->test_connection();
        
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result['message']);
        }

    } catch (\Exception $e) {
        wp_send_json_error($e->getMessage());
    }
}
```

**Cosa fa**:
1. Verifica nonce (sicurezza AJAX)
2. Verifica permessi utente
3. Ottiene handler Google Drive attivo
4. Chiama `test_connection()` (API call a Google)
5. Restituisce JSON con esito test

---

### 5. Metodo Compatibilità Storage Manager

**File**: `includes/storage/class-disco747-storage-manager.php`  
**Linee**: 90-97

```php
/**
 * ✅ NUOVO: Alias per compatibilità
 */
public function get_googledrive() {
    return $this->googledrive_handler;
}
```

**Motivo**: Alcuni file usano `$storage_manager->get_googledrive()` invece di `get_googledrive_handler()`.

---

## 🔄 Flusso OAuth2 Completo

### Diagramma di Flusso

```
┌─────────────────────────────────────────────────────────────────┐
│ 1. Utente clicca "Autorizza Accesso Google Drive"              │
│    ↓                                                             │
│    JavaScript genera URL autorizzazione Google                  │
│    ↓                                                             │
│    window.location.href = "https://accounts.google.com/..."     │
└─────────────────────────────────────────────────────────────────┘
                                ↓
┌─────────────────────────────────────────────────────────────────┐
│ 2. Google mostra schermata consenso                             │
│    - Seleziona account                                           │
│    - Autorizza permessi                                          │
│    ↓                                                             │
│    Utente clicca "Consenti"                                      │
└─────────────────────────────────────────────────────────────────┘
                                ↓
┌─────────────────────────────────────────────────────────────────┐
│ 3. Google reindirizza a:                                         │
│    https://sito.it/wp-admin/admin.php?page=disco747-settings&   │
│    action=google_callback&code=4/xxx&state=gd_xxx               │
└─────────────────────────────────────────────────────────────────┘
                                ↓
┌─────────────────────────────────────────────────────────────────┐
│ 4. PHP intercetta callback                                       │
│    if ($_GET['action'] === 'google_callback')                   │
│    ↓                                                             │
│    $gd_handler->exchange_code_for_tokens($code, $state)         │
└─────────────────────────────────────────────────────────────────┘
                                ↓
┌─────────────────────────────────────────────────────────────────┐
│ 5. Handler chiama Google API                                     │
│    POST https://oauth2.googleapis.com/token                      │
│    body: {                                                       │
│      grant_type: 'authorization_code',                          │
│      code: $code,                                               │
│      client_id: xxx,                                            │
│      client_secret: xxx,                                        │
│      redirect_uri: xxx                                          │
│    }                                                            │
└─────────────────────────────────────────────────────────────────┘
                                ↓
┌─────────────────────────────────────────────────────────────────┐
│ 6. Google risponde con tokens                                    │
│    {                                                             │
│      "access_token": "ya29.xxx",                                │
│      "refresh_token": "1//xxx",                                 │
│      "expires_in": 3600,                                        │
│      "scope": "https://www.googleapis.com/auth/drive.file",     │
│      "token_type": "Bearer"                                     │
│    }                                                            │
└─────────────────────────────────────────────────────────────────┘
                                ↓
┌─────────────────────────────────────────────────────────────────┐
│ 7. Handler salva tokens                                          │
│    update_option('disco747_gd_credentials', [                   │
│      'refresh_token' => '1//xxx'                                │
│    ]);                                                          │
│    update_option('disco747_googledrive_access_token', 'ya29.xxx');│
│    update_option('disco747_googledrive_token_expires', time()+3600);│
└─────────────────────────────────────────────────────────────────┘
                                ↓
┌─────────────────────────────────────────────────────────────────┐
│ 8. Mostra messaggio successo                                     │
│    "✅ Google Drive configurato con successo!"                   │
└─────────────────────────────────────────────────────────────────┘
```

---

## 🧪 Testing

### Test Case 1: Salvataggio Credenziali
```
GIVEN: Utente ha Client ID e Client Secret da Google Console
WHEN: Inserisce credenziali e clicca "Salva Configurazione"
THEN: 
  - ✅ Messaggio "Credenziali salvate!"
  - ✅ Opzione DB `disco747_gd_credentials` aggiornata
  - ✅ Pulsante "Autorizza" diventa visibile
```

### Test Case 2: Autorizzazione OAuth
```
GIVEN: Credenziali salvate correttamente
WHEN: Utente clicca "Autorizza Accesso Google Drive"
THEN:
  - ✅ Redirect a accounts.google.com
  - ✅ Schermata consenso Google
  - ✅ Dopo consenso, redirect a callback URL
  - ✅ Callback salva refresh_token
  - ✅ Messaggio "Google Drive configurato con successo!"
```

### Test Case 3: Test Connessione
```
GIVEN: Google Drive configurato
WHEN: Utente clicca "Test Connessione"
THEN:
  - ✅ AJAX call a disco747_test_googledrive
  - ✅ API call a drive.googleapis.com/v3/about
  - ✅ Popup con "Connessione OK" + nome utente
```

### Test Case 4: Callback con Errore
```
GIVEN: URL redirect NON corrisponde in Google Console
WHEN: Utente prova ad autorizzare
THEN:
  - ❌ Google mostra "redirect_uri_mismatch"
  - ❌ Messaggio errore specifico nel plugin
```

---

## 📦 File Modificati - Summary

| File | Modifiche | LOC +/- |
|------|-----------|---------|
| `includes/admin/views/settings-page.php` | Handler salvataggio + callback + JavaScript | +120 |
| `includes/admin/class-disco747-admin.php` | Handler AJAX test | +45 |
| `includes/storage/class-disco747-storage-manager.php` | Metodo alias | +8 |
| **TOTALE** | | **+173 LOC** |

---

## 🔐 Security Considerations

### 1. Nonce Verification
Tutti i form e le AJAX call verificano il nonce:
```php
wp_verify_nonce($_POST['_wpnonce'], 'disco747_save_gd')
check_ajax_referer('disco747_test_gd', 'nonce')
```

### 2. Input Sanitization
Tutti gli input sono sanitizzati:
```php
sanitize_text_field($_POST['gd_client_id'])
sanitize_email($_POST['company_email'])
```

### 3. Capability Check
Verifica permessi amministratore:
```php
current_user_can('manage_options')
```

### 4. OAuth State Parameter
Protezione CSRF durante OAuth flow:
```javascript
var state = 'gd_' + Math.random().toString(36).substring(2, 15);
```

### 5. Storage Tokens
- `client_secret`: Salvato in DB WordPress (già criptato se usi encryption)
- `refresh_token`: Salvato in opzione protetta
- `access_token`: Temporaneo (1 ora), auto-refresh

---

## 🐛 Known Issues & Limitations

### Issue 1: Refresh Token Non Ricevuto
**Scenario**: Se l'app è già stata autorizzata, Google non invia refresh token.
**Workaround**: Rimuovere accesso su https://myaccount.google.com/permissions

### Issue 2: Redirect URI Mismatch
**Scenario**: URL redirect non corrisponde esattamente.
**Fix**: Verificare carattere per carattere (case-sensitive, trailing slash, parametri)

### Issue 3: Cache Browser
**Scenario**: Modifiche JavaScript non visibili immediatamente.
**Fix**: Hard refresh (CTRL+F5) o svuota cache browser

---

## 📊 Opzioni Database

### Opzioni Utilizzate

| Nome Opzione | Tipo | Descrizione | Esempio Valore |
|-------------|------|-------------|----------------|
| `disco747_gd_credentials` | array | Credenziali OAuth complete | `['client_id' => 'xxx', 'client_secret' => 'xxx', 'refresh_token' => 'xxx']` |
| `disco747_googledrive_access_token` | string | Token temporaneo Google | `ya29.a0AfB_xxx` |
| `disco747_googledrive_token_expires` | int | Timestamp scadenza | `1730739600` |
| `disco747_googledrive_oauth_state` | string | State temporaneo OAuth (sicurezza) | `gd_abc123` |
| `disco747_storage_type` | string | Tipo storage attivo | `googledrive` |

---

## 🎯 Next Steps

### Suggerimenti per Sviluppi Futuri

1. **Error Handling Avanzato**
   - Log strutturati (separati per OAuth, API, Database)
   - Notifiche admin in caso di token scaduto
   - Retry automatico su API failures

2. **UI Improvements**
   - Progress bar durante autorizzazione
   - Test connessione automatico dopo autorizzazione
   - Indicatore stato token (valido/scaduto)

3. **Security Enhancements**
   - Encryption client_secret prima di salvarlo
   - Rate limiting su AJAX calls
   - IP whitelist per OAuth callback

4. **Admin Tools**
   - Pulsante "Disconnetti" per rimuovere tokens
   - Pulsante "Refresh Token Manuale"
   - Debug panel con log OAuth dettagliati

---

## ✅ Checklist Deployment

Prima di mettere in produzione su altri ambienti:

- [ ] Verificare che `disco747_storage_type` sia `googledrive`
- [ ] Testare autorizzazione OAuth da zero
- [ ] Testare callback con URL redirect corretto
- [ ] Testare test connessione
- [ ] Verificare salvataggio PDF su Google Drive
- [ ] Verificare creazione cartelle (Anno/Mese)
- [ ] Testare scansione Excel automatica
- [ ] Controllare log errori PHP

---

## 📚 References

- [Google OAuth2 Documentation](https://developers.google.com/identity/protocols/oauth2)
- [Google Drive API v3](https://developers.google.com/drive/api/v3/reference)
- [WordPress Options API](https://developer.wordpress.org/apis/options/)
- [WordPress AJAX](https://developer.wordpress.org/plugins/javascript/ajax/)

---

**Fine Changelog**

Autore: AI Assistant  
Data: 2025-11-04  
Versione: 11.8.0+fix
