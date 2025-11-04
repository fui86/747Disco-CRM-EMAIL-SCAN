# 🚀 Guida Installazione - 747 Disco CRM

## 📋 Prerequisiti Server

### 1. PHP >= 7.4
```bash
php -v
```

### 2. Estensioni PHP Richieste
```bash
php -m | grep -E 'zip|xml|gd|mbstring|curl'
```

Devono essere installate:
- ✅ `php-zip`
- ✅ `php-xml`
- ✅ `php-gd`
- ✅ `php-mbstring`
- ✅ `php-curl`

### 3. Composer
```bash
composer --version
```

Se non è installato:
```bash
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
```

---

## 🔧 INSTALLAZIONE SOTTODOMINIO

### PASSO 1: Copia Plugin

```bash
# Da dominio principale
cd /percorso/747disco.it/wp-content/plugins/

# Copia su sottodominio
cp -r 747disco-crm/ /percorso/gestionale.747disco.it/wp-content/plugins/
```

### PASSO 2: Installa Dipendenze PHP

```bash
# Entra nella cartella del plugin
cd /percorso/gestionale.747disco.it/wp-content/plugins/747disco-crm/

# Installa con Composer
composer install --no-dev --optimize-autoloader

# Verifica installazione
ls -la vendor/
```

**✅ Output atteso:**
```
vendor/
  ├── autoload.php
  ├── phpoffice/
  │   └── phpspreadsheet/
  ├── dompdf/
  │   └── dompdf/
  └── ... altre dipendenze
```

### PASSO 3: Verifica Permessi

```bash
# Imposta proprietario corretto
chown -R www-data:www-data 747disco-crm/

# Imposta permessi
chmod -R 755 747disco-crm/
chmod -R 775 747disco-crm/vendor/
```

### PASSO 4: Attiva Plugin WordPress

```bash
# Via WP-CLI (se disponibile)
wp plugin activate 747disco-crm

# OPPURE da interfaccia
# → https://gestionale.747disco.it/wp-admin/plugins.php
```

---

## 🔐 CONFIGURAZIONE GOOGLE DRIVE

### 1. Google Cloud Console

👉 https://console.cloud.google.com/

**Vai su:** API e servizi → Credenziali

**Aggiungi Redirect URI:**
```
https://gestionale.747disco.it/wp-admin/admin.php?page=disco747-settings&tab=googledrive
```

**⚠️ MANTIENI anche il vecchio URI:**
```
https://747disco.it/wp-admin/admin.php?page=disco747-settings&tab=googledrive
```

**Salva le modifiche!**

### 2. WordPress Sottodominio

👉 https://gestionale.747disco.it/wp-admin/admin.php?page=disco747-settings

**Tab "Google Drive":**

```
Client ID: [Da Google Cloud Console]
Client Secret: [Da Google Cloud Console]
Redirect URI: https://gestionale.747disco.it/wp-admin/admin.php?page=disco747-settings&tab=googledrive
```

**Clicca "Autorizza Google Drive"**
→ Login Google
→ Copia Refresh Token
→ Salva

---

## 🧪 TEST FUNZIONALITÀ

### 1. Test Lettura Excel
```bash
# Verifica che PhpSpreadsheet sia caricabile
php -r "require 'vendor/autoload.php'; echo class_exists('\PhpOffice\PhpSpreadsheet\IOFactory') ? 'OK' : 'ERRORE';"
```

### 2. Test Generazione PDF
```bash
# Verifica che Dompdf sia caricabile
php -r "require 'vendor/autoload.php'; echo class_exists('\Dompdf\Dompdf') ? 'OK' : 'ERRORE';"
```

### 3. Test Google Drive
- Vai su: Excel Scan page
- Verifica che veda i file su Google Drive
- Prova a scansionare un file Excel

### 4. Test Completo
- Crea un preventivo
- Verifica che generi il PDF
- Verifica che lo carichi su Google Drive
- Controlla che parta il funnel

---

## ❌ TROUBLESHOOTING

### Errore: "vendor/autoload.php not found"

**Causa:** Dipendenze non installate

**Soluzione:**
```bash
cd /percorso/plugin/747disco-crm/
composer install
```

---

### Errore: "Class 'PhpOffice\PhpSpreadsheet\IOFactory' not found"

**Causa:** Composer non ha installato PhpSpreadsheet

**Soluzione:**
```bash
composer require phpoffice/phpspreadsheet
composer dump-autoload
```

---

### Errore: "Class 'Dompdf\Dompdf' not found"

**Causa:** Dompdf non installato

**Soluzione:**
```bash
composer require dompdf/dompdf
composer dump-autoload
```

---

### Errore: "Could not open input file: composer.phar"

**Causa:** Composer non installato

**Soluzione:**
```bash
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
```

---

### Errore: "The zip extension is not installed"

**Causa:** Estensione PHP zip mancante

**Soluzione:**
```bash
# Ubuntu/Debian
sudo apt install php-zip php-xml php-gd php-mbstring
sudo service apache2 restart

# CentOS/RHEL
sudo yum install php-zip php-xml php-gd php-mbstring
sudo service httpd restart
```

---

### Warning: "allow_url_fopen is disabled"

**Causa:** Configurazione PHP restrittiva

**Soluzione:**
Edita `php.ini`:
```ini
allow_url_fopen = On
```

Riavvia Apache/Nginx.

---

## 📊 VERIFICA FINALE

### Checklist Installazione Completa:

```bash
# 1. Composer installato
composer --version

# 2. Dipendenze installate
ls vendor/autoload.php

# 3. Estensioni PHP OK
php -m | grep -E 'zip|xml|gd'

# 4. Plugin attivo in WordPress
wp plugin list | grep 747disco-crm

# 5. Permessi corretti
ls -la vendor/

# 6. Google Drive configurato
# → Test da interfaccia web
```

---

## 🆘 SUPPORTO

Se tutto fallisce, contattami con:

1. Output di: `composer diagnose`
2. Output di: `php -v`
3. Output di: `php -m`
4. Screenshot errori WordPress

---

## 📝 NOTE

- La cartella `vendor/` **NON** va committata su Git (già in `.gitignore`)
- Ogni server/sottodominio deve avere le sue dipendenze installate
- Composer va eseguito **DOPO** aver copiato il plugin
- Le credenziali Google sono condivise, solo il Redirect URI cambia

---

## ✅ COMANDI RAPIDI

### Setup Completo da Zero:
```bash
# Copia plugin
cp -r 747disco-crm/ /destinazione/

# Entra nella cartella
cd /destinazione/747disco-crm/

# Installa dipendenze
composer install --no-dev --optimize-autoloader

# Fix permessi
chown -R www-data:www-data .
chmod -R 755 .

# Attiva
wp plugin activate 747disco-crm
```

**Fatto! 🚀**
