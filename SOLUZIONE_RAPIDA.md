# ⚡ SOLUZIONE RAPIDA - FIX EMAIL FUNNEL

## 🔴 IL PROBLEMA

Il CSS nel tag `<style>` viene mostrato come testo nell'email perché:
1. `wp_kses()` rimuoveva il tag `<style>` ma lasciava il contenuto
2. Gmail rimuove SEMPRE i tag `<style>` per sicurezza

## ✅ LA SOLUZIONE (2 STEP)

---

### **STEP 1: Esegui questo SQL nel database**

Vai su **phpMyAdmin** (o WP Dashboard → plugin Database) ed esegui:

```sql
-- Cancella template corrotti
DELETE FROM wp_disco747_funnel_sequences WHERE funnel_type = 'pre_conferma';
```

Poi **esegui tutto il contenuto** del file: **`fix-funnel-templates.sql`**

Questo inserisce 3 template Gmail-safe **senza tag `<style>`** (solo CSS inline).

---

### **STEP 2: Test immediato**

**Opzione A - Via Database:**

```sql
-- Forza invio immediato di un tracking esistente
UPDATE wp_disco747_funnel_tracking 
SET next_send_at = NOW() 
WHERE status = 'active' 
LIMIT 1;
```

Poi vai su **WP Admin → 747Disco-CRM → Configurazioni Funnel** e clicca **"Test Cron"**.

**Opzione B - Via PHP:**

Crea un file `test-email.php` nella root WordPress:

```php
<?php
require_once('wp-load.php');

// Simula preventivo
$preventivo = (object) [
    'id' => 999,
    'preventivo_id' => 'TEST-001',
    'nome_referente' => 'Mario',
    'cognome_referente' => 'Rossi',
    'nome_cliente' => 'Mario Rossi',
    'email' => 'TUA-EMAIL@test.com', // ← CAMBIA QUI
    'telefono' => '+39 347 1811119',
    'tipo_evento' => 'Compleanno 18 anni',
    'data_evento' => '2025-06-15',
    'numero_invitati' => 80,
    'tipo_menu' => 'Menu 74',
    'importo_totale' => 2500.00,
    'acconto' => 500.00
];

// Carica template dal database
global $wpdb;
$step = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}disco747_funnel_sequences WHERE funnel_type = 'pre_conferma' AND step_number = 1");

// Invia email
$fm = new \Disco747_CRM\Funnel\Disco747_Funnel_Manager();
$sent = $fm->send_email_to_customer($preventivo, $step);

echo $sent ? '✅ Email inviata!' : '❌ Errore';
?>
```

Esegui: Vai su `https://tuosito.it/test-email.php`

---

## ✅ RISULTATO ATTESO

L'email ricevuta dovrebbe avere:
- ✅ **Layout nero/oro corretto**
- ✅ **Nessun CSS visibile** come testo all'inizio
- ✅ **Tabelle formattate** con bordi arrotondati
- ✅ **Omaggi ben visibili** in box giallo
- ✅ **Link WhatsApp funzionanti**
- ✅ **Placeholder sostituiti** ({{nome}}, {{data_evento}})

---

## 🐛 SE IL PROBLEMA PERSISTE

### **Verifica 1: Controlla database**

```sql
SELECT id, step_name, LENGTH(email_body) AS lunghezza, 
       LEFT(email_body, 200) AS preview
FROM wp_disco747_funnel_sequences 
WHERE funnel_type = 'pre_conferma';
```

Se la `lunghezza` è < 500 caratteri, i template NON sono stati caricati correttamente.

### **Verifica 2: Controlla log**

File: `/wp-content/debug.log`

Cerca:
```
[747Disco-Funnel] ✉️ Email inviata a cliente@email.com
```

Se vedi `❌ Errore invio email`, problema SMTP.

### **Verifica 3: Testa con Litmus/Email on Acid**

Invia a: `test@litmus.com` per vedere come l'email viene renderizzata su tutti i client.

---

## 📁 FILE MODIFICATI

1. **`includes/funnel/class-disco747-funnel-manager.php`**
   - Rimosso `nl2br()` (riga 261)
   - Aggiunti placeholder `{{nome}}`, `{{cognome}}`, `{{preventivo_id}}` (riga 374-402)

2. **`includes/admin/views/funnel-automation-page.php`**
   - Rimosso `wp_kses()` distruttivo (riga 47-78)
   - HTML salvato completo per admin fidati

3. **`fix-funnel-templates.sql`**
   - SQL pronto per inserire 3 template Gmail-safe
   - Nessun tag `<style>`, solo CSS inline

---

## 📞 SUPPORTO

**Se dopo questi 2 step il problema persiste:**
1. Screenshot dell'email ricevuta
2. Codice HTML sorgente (Gmail → Mostra originale)
3. Output del SQL di verifica sopra

---

**🎯 Con questi fix, le email funzioneranno perfettamente su Gmail, Outlook e Apple Mail!**

**Tempo stimato:** 5 minuti ⏱️
