# 📦 Installazione Dipendenze PHP - 747 Disco CRM

## ✅ File `composer.json` Creato

Ho creato il file `/workspace/composer.json` con le dipendenze necessarie:

```json
{
    "require": {
        "php": ">=7.4",
        "phpoffice/phpspreadsheet": "^1.29",
        "dompdf/dompdf": "^2.0"
    }
}
```

## 🚀 Come Installare le Dipendenze

### **Opzione 1: Via SSH (Consigliata)**

Se hai accesso SSH al tuo server:

```bash
# 1. Connettiti al server
ssh utente@gestionale.747disco.it

# 2. Vai nella cartella del plugin
cd /path/to/wordpress/wp-content/plugins/747disco-crm/

# 3. Installa Composer (se non installato)
curl -sS https://getcomposer.org/installer | php

# 4. Installa le dipendenze
php composer.phar install --no-dev --optimize-autoloader

# 5. Verifica installazione
ls -la vendor/
```

### **Opzione 2: Via FTP + Locale**

Se non hai SSH, puoi installare in locale e caricare via FTP:

```bash
# 1. Sul tuo computer locale (con PHP installato):
cd /percorso/locale/del/plugin/

# 2. Installa Composer globalmente
# Windows: Scarica da https://getcomposer.org/Composer-Setup.exe
# Mac: brew install composer
# Linux: apt install composer

# 3. Installa le dipendenze
composer install --no-dev --optimize-autoloader

# 4. Carica via FTP la cartella vendor/ sul server
# Carica in: wp-content/plugins/747disco-crm/vendor/
```

### **Opzione 3: Download Manuale (Solo se necessario)**

Se non puoi usare Composer, scarica manualmente:

#### **PhpSpreadsheet:**
1. Vai su: https://github.com/PHPOffice/PhpSpreadsheet/releases
2. Scarica l'ultima versione (es. 1.29.0)
3. Estrai e carica in: `wp-content/plugins/747disco-crm/vendor/phpoffice/phpspreadsheet/`

#### **Dompdf:**
1. Vai su: https://github.com/dompdf/dompdf/releases
2. Scarica l'ultima versione (es. 2.0.4)
3. Estrai e carica in: `wp-content/plugins/747disco-crm/vendor/dompdf/dompdf/`

---

## 📁 Struttura Finale Attesa

Dopo l'installazione, dovresti avere:

```
/workspace/
  ├── composer.json
  ├── composer.lock (generato da composer)
  └── vendor/
      ├── autoload.php
      ├── composer/
      ├── phpoffice/
      │   └── phpspreadsheet/
      ├── dompdf/
      │   └── dompdf/
      └── [altre dipendenze automatiche]
```

## ✅ Verifica Installazione

Dopo l'installazione, verifica che funzioni:

### **1. Via Codice PHP:**

Crea un file `test-vendor.php` nella root del plugin:

```php
<?php
require_once __DIR__ . '/vendor/autoload.php';

echo "Test librerie:\n";

// Test PhpSpreadsheet
if (class_exists('PhpOffice\\PhpSpreadsheet\\Spreadsheet')) {
    echo "✅ PhpSpreadsheet installato\n";
} else {
    echo "❌ PhpSpreadsheet NON trovato\n";
}

// Test Dompdf
if (class_exists('Dompdf\\Dompdf')) {
    echo "✅ Dompdf installato\n";
} else {
    echo "❌ Dompdf NON trovato\n";
}
```

Poi eseguilo:
```bash
php test-vendor.php
```

### **2. Via WordPress:**

Dopo l'installazione, vai su:
- **PreventiviParty → Nuovo Preventivo**
- Compila e salva
- Se non ci sono errori, le librerie funzionano! ✅

---

## 🐛 Problemi Comuni

### **Errore: "Composer command not found"**

Installa Composer:
```bash
# Ubuntu/Debian
sudo apt update
sudo apt install composer

# CentOS/RHEL
sudo yum install composer

# Mac
brew install composer
```

### **Errore: "memory limit" durante install**

Aumenta il limite di memoria PHP temporaneamente:
```bash
php -d memory_limit=512M /usr/local/bin/composer install
```

### **Errore: "vendor/autoload.php not found"**

Il codice cerca:
```php
require_once DISCO747_CRM_PLUGIN_DIR . 'vendor/autoload.php';
```

Assicurati che la cartella `vendor/` sia nella root del plugin, non in una sottocartella.

---

## 🔒 Permessi File

Dopo l'installazione, verifica i permessi:

```bash
# Imposta proprietario corretto
chown -R www-data:www-data vendor/

# Imposta permessi corretti
chmod -R 755 vendor/
```

---

## 📊 Dimensioni Librerie

Le librerie occuperanno circa:
- **PhpSpreadsheet:** ~15 MB
- **Dompdf:** ~8 MB
- **Dipendenze:** ~5 MB
- **Totale:** ~30 MB

Assicurati di avere spazio sufficiente sul server.

---

## 🎯 Prossimi Passi

Una volta installate le dipendenze:

1. ✅ Vai su **PreventiviParty → Nuovo Preventivo**
2. ✅ Compila un preventivo di test
3. ✅ Salva e verifica che:
   - Excel venga generato
   - PDF venga generato
   - File vengano caricati su Google Drive

---

## 🆘 Alternative Se Non Puoi Installare

Se non puoi installare Composer o non hai accesso SSH:

1. **Contatta il tuo hosting provider** e chiedi di installare Composer
2. Oppure chiedi di eseguire per te: `composer install` nella cartella del plugin
3. Oppure passa a un hosting che supporta Composer (es. SiteGround, WP Engine, Kinsta)

---

**Data:** 2025-11-04  
**Plugin:** 747 Disco CRM v11.8.0
