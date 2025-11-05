# 🐛 ABILITA DEBUG COMPLETO - 747 Disco CRM

## ✅ **STATO ATTUALE DEL DEBUG:**

| Livello | Stato | Dove si trova |
|---------|-------|---------------|
| **Plugin Core** | ✅ `true` | `747disco-crm.php` linea 45 |
| **Plugin Option** | ❌ Da abilitare | Database WordPress |
| **WordPress Debug** | ❓ Da verificare | `wp-config.php` |

---

## 🚀 **METODO 1: Via SSH (VELOCE - 30 secondi)**

### **A. Abilita Debug Plugin:**

```bash
# Connettiti via SSH
ssh u123456789@gestionale.747disco.it -p 65002

# Vai nella root di WordPress
cd ~/domains/gestionale.747disco.it/public_html

# Abilita debug mode del plugin
wp option update disco747_debug_mode 1

# Verifica che sia abilitato
wp option get disco747_debug_mode
```

Dovresti vedere: `1` ✅

---

### **B. Abilita WordPress Debug:**

Apri il file `wp-config.php`:

```bash
nano wp-config.php
```

Cerca questa riga (di solito vicino alla riga 80):
```php
define( 'WP_DEBUG', false );
```

**Sostituiscila con:**
```php
// ✅ DEBUG ABILITATO - 747 Disco CRM
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', true );
define( 'WP_DEBUG_DISPLAY', false );
define( 'SCRIPT_DEBUG', true );
@ini_set( 'display_errors', 0 );
@ini_set( 'log_errors', 1 );
@ini_set( 'error_log', '/home/u123456789/domains/gestionale.747disco.it/public_html/wp-content/debug.log' );
```

Salva con: `CTRL+O` → `INVIO` → `CTRL+X`

---

### **C. Verifica Debug Attivo:**

```bash
# Verifica wp-config.php
grep "WP_DEBUG" wp-config.php

# Verifica opzione plugin
wp option get disco747_debug_mode

# Crea file di log (se non esiste)
touch wp-content/debug.log
chmod 644 wp-content/debug.log

# Monitora i log in tempo reale
tail -f wp-content/debug.log
```

---

## 🗄️ **METODO 2: Via phpMyAdmin (MEDIO - 1 minuto)**

### **A. Abilita Debug Plugin:**

1. Accedi a **hPanel Hostinger**
2. Vai su **Database → phpMyAdmin**
3. Seleziona il database WordPress (es. `u123456789_wp`)
4. Clicca su **SQL** in alto
5. Incolla questo codice:

```sql
INSERT INTO wp_options (option_name, option_value, autoload) 
VALUES ('disco747_debug_mode', '1', 'yes')
ON DUPLICATE KEY UPDATE option_value = '1';
```

6. Clicca **Esegui**
7. Dovresti vedere: "1 row affected" ✅

---

### **B. Abilita WordPress Debug:**

1. In **hPanel → File Manager**
2. Apri `public_html/wp-config.php`
3. Cerca `define( 'WP_DEBUG', false );`
4. Sostituisci con:

```php
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', true );
define( 'WP_DEBUG_DISPLAY', false );
```

5. Salva il file

---

## 🖥️ **METODO 3: Via WordPress Admin (FACILE - 30 secondi)**

1. Vai su: **PreventiviParty → Impostazioni**
2. Scorri fino a **"Sezione Debug"**
3. Spunta: ☑️ **"Abilita Modalità Debug"**
4. Clicca **"Salva Modifiche"**

✅ Fatto!

---

## 📊 **DOVE VEDERE I LOG:**

### **1. Log Plugin (dettagliato):**
```bash
tail -f ~/domains/gestionale.747disco.it/public_html/wp-content/debug.log
```

### **2. Log PHP del server:**
```bash
tail -f ~/logs/error.log
```

### **3. Log Browser (JavaScript):**
- Apri DevTools: `F12`
- Tab **Console**
- Cerca messaggi con `🔍 Debug:` o `[747]`

---

## 🎯 **COME TESTARE IL DEBUG:**

Dopo aver abilitato tutto:

### **Test 1 - Genera Preventivo:**
```bash
# In un terminale SSH lascia i log aperti:
tail -f wp-content/debug.log

# In un altro terminale o nel browser:
# Vai su PreventiviParty → Nuovo Preventivo → Compila → Salva
```

Dovresti vedere nei log:
```
[2025-11-04 10:30:15] PDF Generator v12.3.0 inizializzato
[2025-11-04 10:30:16] [FORM] Dati ricevuti: {"nome":"Test",...}
[2025-11-04 10:30:17] [EXCEL] Generazione preventivo...
[2025-11-04 10:30:18] [GOOGLEDRIVE] Upload in corso...
```

---

### **Test 2 - Autorizzazione Google Drive:**
```bash
# Lascia i log aperti
tail -f wp-content/debug.log

# Clicca su "Autorizza Accesso Google Drive"
```

Dovresti vedere:
```
[2025-11-04 10:31:20] URL autorizzazione generato: https://accounts.google.com/o/oauth2/v2/auth?client_id=...
[2025-11-04 10:31:22] [OAUTH] Callback ricevuto
[2025-11-04 10:31:23] [OAUTH] Token ricevuto: ya29.a0...
```

---

## 🔥 **COMANDI TUTTO-IN-UNO (Copia e Incolla):**

### **Via SSH - Abilita Tutto:**
```bash
# Connettiti
ssh u123456789@gestionale.747disco.it -p 65002

# Vai nella root WordPress
cd ~/domains/gestionale.747disco.it/public_html

# Abilita debug plugin
wp option update disco747_debug_mode 1

# Backup wp-config.php
cp wp-config.php wp-config.php.backup

# Abilita WP_DEBUG
sed -i "s/define( 'WP_DEBUG', false );/define( 'WP_DEBUG', true );\ndefine( 'WP_DEBUG_LOG', true );\ndefine( 'WP_DEBUG_DISPLAY', false );/g" wp-config.php

# Crea file di log
touch wp-content/debug.log
chmod 644 wp-content/debug.log

# Verifica
echo "✅ Debug Mode Plugin: $(wp option get disco747_debug_mode)"
echo "✅ WP_DEBUG: $(grep 'WP_DEBUG' wp-config.php | head -1)"
echo "✅ Log file: $(ls -lh wp-content/debug.log)"

# Monitora i log
echo "📊 Apertura log in tempo reale (CTRL+C per uscire):"
tail -f wp-content/debug.log
```

---

## ⚠️ **IMPORTANTE - Disabilita Debug in Produzione:**

Quando hai finito il debug, **disabilita tutto**:

```bash
# Disabilita debug plugin
wp option update disco747_debug_mode 0

# Ripristina wp-config.php
cp wp-config.php.backup wp-config.php

# Oppure modifica manualmente:
sed -i "s/define( 'WP_DEBUG', true );/define( 'WP_DEBUG', false );/g" wp-config.php
```

⚠️ **Lasciare il debug attivo in produzione può:**
- Rallentare il sito
- Riempire il disco con log
- Esporre informazioni sensibili

---

## 📱 **Debug da Browser (No SSH):**

Se non puoi usare SSH, puoi vedere i log dal browser:

1. Abilita debug da **PreventiviParty → Impostazioni**
2. Vai su: `https://gestionale.747disco.it/wp-content/debug.log`
3. Premi `F5` per aggiornare durante i test

---

## 🆘 **Problemi Comuni:**

### **"Permission denied" su debug.log**
```bash
chmod 644 wp-content/debug.log
chown www-data:www-data wp-content/debug.log
```

### **"WP-CLI not found"**
Usa phpMyAdmin (Metodo 2) oppure:
```bash
# Scarica WP-CLI
curl -O https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar
php wp-cli.phar option update disco747_debug_mode 1
```

### **Log non si popola**
Verifica che il plugin sia attivo:
```bash
wp plugin list | grep 747disco
```

---

**Pronto! Abilitato il debug, dimmi cosa vedi nei log quando provi a generare un preventivo!** 🔍

---

**File Utili Creati:**
- ✅ `/workspace/enable-debug.sql` - SQL per abilitare debug
- ✅ `/workspace/ABILITA_DEBUG.md` - Questa guida completa
