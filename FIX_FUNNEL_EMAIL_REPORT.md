# 🔧 FIX FUNNEL EMAIL - REPORT COMPLETO

## 📋 **PROBLEMI IDENTIFICATI E RISOLTI**

### ❌ **PROBLEMA 1: `nl2br()` distruggeva l'HTML**
**File:** `includes/funnel/class-disco747-funnel-manager.php` (riga 260)

**Causa:**
```php
// VECCHIO CODICE (SBAGLIATO)
$body_html = nl2br($body);
```

La funzione `nl2br()` convertiva **tutti** i newline (`\n`) in tag `<br>`, ma dato che i template email sono **HTML completo** (con `<table>`, `<style>`, `<!doctype>`, etc.), aggiungeva `<br>` tag ovunque, **distruggendo completamente** la struttura HTML.

**Soluzione applicata:**
```php
// NUOVO CODICE (CORRETTO) ✅
$body_html = $body; // Nessuna conversione, l'HTML è già completo
```

---

### ❌ **PROBLEMA 2: `wp_kses_post()` rimuoveva CSS e tag avanzati**
**File:** `includes/admin/views/funnel-automation-page.php` (riga 58)

**Causa:**
```php
// VECCHIO CODICE (SBAGLIATO)
'email_body' => wp_kses_post($_POST['email_body'])
```

La funzione `wp_kses_post()` è troppo **restrittiva** e rimuove:
- Tag `<style>` (tutto il CSS inline!)
- Alcuni attributi avanzati delle tabelle (`role`, `cellpadding`, `cellspacing`)
- Attributi `style` complessi

**Soluzione applicata:**
```php
// NUOVO CODICE (CORRETTO) ✅
$allowed_html = array(
    'style' => array('type' => true),
    'table' => array('role' => true, 'width' => true, 'cellpadding' => true, 'cellspacing' => true, 'border' => true, 'style' => true),
    'td' => array('style' => true, 'align' => true, 'valign' => true, 'width' => true, 'height' => true),
    // ... altri tag permessi
);
'email_body' => wp_kses($_POST['email_body'], $allowed_html)
```

---

### ✅ **MIGLIORAMENTI AGGIUNTIVI**

**Placeholder aggiunti** nella funzione `replace_variables()`:
- `{{nome}}` → Alias per `{{nome_referente}}`
- `{{cognome}}` → Alias per `{{cognome_referente}}`
- `{{preventivo_id}}` → ID preventivo
- `{{telefono_sede}}` → +39 347 181 1119 (aggiornato)
- `{{email_sede}}` → eventi@747disco.it (aggiornato)

---

## 🧪 **COME TESTARE IL FIX**

### **STEP 1: Ricaricare i template HTML**

1. Vai su **WP Admin → 747Disco-CRM → 🚀 Configurazioni Funnel**
2. Seleziona il tab **"Pre-Conferma"**
3. Clicca su **"Modifica"** per ogni step
4. **Copia e incolla** l'HTML completo dei tuoi template email (quelli che mi hai fornito)
5. Salva la sequenza

### **STEP 2: Verificare il salvataggio**

Dopo aver salvato, riapri la sequenza modificata e verifica che:
- ✅ Il tag `<style>` con tutto il CSS sia presente
- ✅ I tag `<table>` con attributi `role`, `cellpadding`, `cellspacing` siano intatti
- ✅ Gli attributi `style` inline siano preservati

### **STEP 3: Test email reale**

**Opzione A - Test manuale da WordPress Admin:**
1. Vai su **WP Admin → 747Disco-CRM → 🚀 Configurazioni Funnel**
2. In alto a destra clicca **"Test Cron"** (se disponibile)
3. Oppure crea un preventivo di test e attendi l'invio schedulato

**Opzione B - Test immediato con preventivo esistente:**
```php
// Esegui questo codice PHP una volta via admin o debug
$funnel_manager = new \Disco747_CRM\Funnel\Disco747_Funnel_Manager();
$funnel_manager->send_next_step(TRACKING_ID); // Sostituisci con ID tracking reale
```

### **STEP 4: Controllo email ricevuta**

Quando ricevi l'email, verifica:
- ✅ **Layout corretto** (sfondo nero, colori oro)
- ✅ **CSS applicato** (gradienti, bordi arrotondati)
- ✅ **Tabelle formattate** correttamente
- ✅ **Immagini visibili**
- ✅ **Link WhatsApp funzionanti**
- ✅ **Placeholder sostituiti** ({{nome}}, {{data_evento}}, etc.)

---

## 📧 **COME CARICARE I 4 TEMPLATE EMAIL**

### **Template 1: "Serve una mano?" (Step 1)**
- **Tipo Funnel:** Pre-Conferma
- **Step Number:** 1
- **Days Offset:** +1 (un giorno dopo preventivo)
- **Send Time:** 14:00
- **Email Subject:** `Tutto chiaro? | 747 Disco`
- **Email Body:** [Copia tutto l'HTML della Mail 1]

### **Template 2: "Ultimi posti" (Step 2)**
- **Tipo Funnel:** Pre-Conferma
- **Step Number:** 2
- **Days Offset:** +2
- **Send Time:** 10:00
- **Email Subject:** `Ultimi posti per la tua data | 747 Disco`
- **Email Body:** [Copia tutto l'HTML della Mail 2]

### **Template 3: "Ultime 24 ore" (Step 3)**
- **Tipo Funnel:** Pre-Conferma
- **Step Number:** 3
- **Days Offset:** +3
- **Send Time:** 09:00
- **Email Subject:** `Ultime 24 Ore | 747 Disco`
- **Email Body:** [Copia tutto l'HTML della Mail 3]

### **Template 4: "Grazie per la visita" (Step 0)**
Questo template sembra essere un follow-up **dopo visita fisica**:
- **Tipo Funnel:** Pre-Conferma (o crea nuovo tipo "post_visita")
- **Step Number:** 0 (invio immediato)
- **Days Offset:** 0
- **Send Time:** 17:00
- **Email Subject:** `Grazie per la visita | 747 Disco`
- **Email Body:** [Copia tutto l'HTML della Mail 4]

---

## ⚠️ **NOTE IMPORTANTI**

### **1. Commenti condizionali MSO**
I tuoi template hanno commenti condizionali per Outlook:
```html
<!--[if mso]>
<table>...</table>
<![endif]-->
```

Questi commenti **potrebbero essere rimossi** da WordPress durante il salvataggio per sicurezza. Se Outlook mostra problemi di rendering, potrebbero dover essere rimossi o gestiti diversamente.

**Soluzione alternativa:**
- Usa versioni semplificate senza commenti condizionali MSO
- Oppure testa con `wp_kses` disabilitato temporaneamente per admin fidati

### **2. Placeholder disponibili**
I template possono usare questi placeholder:

| Placeholder | Descrizione | Esempio Output |
|-------------|-------------|----------------|
| `{{nome}}` | Nome referente | Mario |
| `{{cognome}}` | Cognome referente | Rossi |
| `{{nome_cliente}}` | Nome completo | Mario Rossi |
| `{{tipo_evento}}` | Tipo | Compleanno 18 anni |
| `{{data_evento}}` | Data formattata | 15/06/2025 |
| `{{numero_invitati}}` | Ospiti | 80 |
| `{{tipo_menu}}` | Menu | Menu 74 |
| `{{importo_totale}}` | Prezzo | €2.500,00 |
| `{{acconto}}` | Caparra | €500,00 |
| `{{preventivo_id}}` | ID preventivo | 042 |
| `{{telefono_sede}}` | Tel. sede | +39 347 181 1119 |
| `{{email_sede}}` | Email sede | eventi@747disco.it |

### **3. Test su client email diversi**
Testa le email su:
- ✅ Gmail (web + mobile)
- ✅ Outlook (desktop + web)
- ✅ Apple Mail (Mac + iPhone)
- ✅ Thunderbird

Le tabelle HTML per email sono complesse e potrebbero renderizzare diversamente su client diversi.

---

## 🔍 **DEBUG - Se le email ancora non funzionano**

### **Verifica 1: Log WordPress**
Controlla i log PHP in `/wp-content/debug.log` (se `WP_DEBUG_LOG` è attivo):
```
[747Disco-Funnel] ✉️ Email inviata a cliente@email.com
```

### **Verifica 2: Plugin SMTP**
Se usi SMTP (tipo WP Mail SMTP), verifica:
- Configurazione corretta
- Log SMTP per errori
- Bounce/reject del server email

### **Verifica 3: Controlla database**
Verifica che i template siano salvati correttamente:
```sql
SELECT email_subject, LEFT(email_body, 200) 
FROM wp_disco747_funnel_sequences 
WHERE funnel_type = 'pre_conferma';
```

Se l'HTML è stato strippato, vedrai solo testo senza tag.

### **Verifica 4: Forza re-invio**
Per testare subito senza aspettare il cron:
```php
// Da eseguire in WP Admin → Tools → PHP Code Executor (o plugin Query Monitor)
$funnel_manager = new \Disco747_CRM\Funnel\Disco747_Funnel_Manager();
$scheduler = new \Disco747_CRM\Funnel\Disco747_Funnel_Scheduler();
$scheduler->process_pending_sends();
```

---

## ✅ **CHECKLIST POST-FIX**

- [ ] File modificati committati in git
- [ ] Template HTML ricaricati in WP Admin
- [ ] Test email inviata e ricevuta
- [ ] Layout email verificato (CSS, tabelle, colori)
- [ ] Placeholder testati e funzionanti
- [ ] Link WhatsApp testati
- [ ] Immagini caricate e visibili
- [ ] Test su Gmail, Outlook, Apple Mail
- [ ] Cron WordPress attivo e funzionante
- [ ] Log verificati per errori

---

## 📞 **SUPPORTO**

Se dopo questi fix le email continuano ad avere problemi:

1. **Controlla i log** PHP/WordPress
2. **Invia un'email di test** manualmente via `wp_mail()`
3. **Verifica HTML** copiando il codice sorgente dell'email ricevuta
4. **Testa con template più semplice** (senza CSS avanzato)

---

**✅ FIX COMPLETATI IL:** 2025-01-13  
**🔧 FILES MODIFICATI:**
- `includes/funnel/class-disco747-funnel-manager.php` (riga 260 + 374-402)
- `includes/admin/views/funnel-automation-page.php` (riga 50-99)

**🎯 PROBLEMA RISOLTO:** Email funnel arrivavano con HTML rotto, ora dovrebbero renderizzare correttamente con tutti gli stili e il layout originale.
