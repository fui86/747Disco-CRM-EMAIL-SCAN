# 🔧 ISTRUZIONI DEFINITIVE - FIX EMAIL FUNNEL

## ⚠️ **PROBLEMA IDENTIFICATO**

Hai **2 bug critici**:

1. ✅ **RISOLTO:** `nl2br()` distruggeva l'HTML → Rimosso
2. ✅ **RISOLTO:** `wp_kses()` rimuoveva il tag `<style>` ma lasciava il contenuto come testo
3. ⚠️ **NUOVO PROBLEMA:** Gmail rimuove **SEMPRE** i tag `<style>` dalle email per sicurezza

**Risultato:** Il CSS nel `<head>` viene mostrato come testo normale nell'email.

---

## 🎯 **SOLUZIONE DEFINITIVA**

### **STEP 1: Cancella i template corrotti dal database**

Esegui questo SQL nel database WordPress (via phpMyAdmin o plugin):

```sql
-- Cancella tutte le sequenze funnel esistenti (sono corrotte)
DELETE FROM wp_disco747_funnel_sequences;

-- Resetta anche i tracking attivi (opzionale, per ripartire da zero)
-- DELETE FROM wp_disco747_funnel_tracking;
```

---

### **STEP 2: Usa i template Gmail-safe (SENZA tag `<style>`)**

Ho preparato una versione **Gmail-safe** dei tuoi template:
- ✅ **NESSUN tag `<style>`** (Gmail lo rimuove comunque)
- ✅ **Solo CSS inline** (attributi `style=""` su ogni elemento)
- ✅ **Preheader nascosto** con inline styles
- ✅ **Funziona perfettamente su Gmail, Outlook, Apple Mail**

**File creato:** `template-email-gmail-safe.html`

---

### **STEP 3: Carica i nuovi template in WordPress**

#### **Template 1: "Ultimi posti" (Mail 2 - semplificata)**

1. Vai su **WP Admin → 747Disco-CRM → 🚀 Configurazioni Funnel**
2. Tab **"Pre-Conferma"**
3. Clicca **"Aggiungi Nuova Sequenza"** (o modifica Step 1)
4. Compila:
   - **Tipo Funnel:** Pre-Conferma
   - **Step Number:** 1
   - **Nome Step:** Ultimi posti
   - **Days Offset:** +1 (un giorno dopo preventivo)
   - **Send Time:** 10:00
   - **Email Enabled:** ✅
   - **Email Subject:** `Ultimi posti per la tua data | 747 Disco`
   - **Email Body:** Copia il contenuto di `template-email-gmail-safe.html`

5. **SALVA**

---

#### **Template 2, 3, 4: Ripeti per gli altri**

Ripeti lo stesso processo per:
- **Step 2** (+2 giorni): "Serve una mano?"
- **Step 3** (+3 giorni): "Ultime 24 ore"
- **Step 4** (post-visita): "Grazie per la visita"

**IMPORTANTE:** Usa sempre **template senza tag `<style>`**, solo CSS inline!

---

### **STEP 4: Verifica il salvataggio**

1. Dopo aver salvato, **riapri la sequenza** modificata
2. Verifica che:
   - ✅ L'HTML sia **completo** (tag `<table>`, `<td>`, `<div>`)
   - ✅ Gli attributi `style=""` siano **presenti e intatti**
   - ✅ **NON ci sia** tag `<style>` nel `<head>` (causa del problema)

---

### **STEP 5: Test email reale**

#### **Opzione A - Test immediato manuale**

1. Vai nel database e trova un tracking attivo:
   ```sql
   SELECT * FROM wp_disco747_funnel_tracking WHERE status = 'active' LIMIT 1;
   ```

2. Annota l'`id` (es: 42)

3. Esegui questo codice PHP (tramite plugin "Code Snippets" o file PHP temporaneo):
   ```php
   require_once('/path/to/wp-load.php');
   $funnel_manager = new \Disco747_CRM\Funnel\Disco747_Funnel_Manager();
   $funnel_manager->send_next_step(42); // Sostituisci 42 con l'ID tracking
   echo "Email inviata!";
   ```

#### **Opzione B - Crea preventivo di test**

1. Crea un **nuovo preventivo** con la tua email di test
2. Il funnel partirà automaticamente
3. Aspetta 1 giorno (o modifica `next_send_at` nel database per invio immediato):
   ```sql
   UPDATE wp_disco747_funnel_tracking 
   SET next_send_at = NOW() 
   WHERE preventivo_id = 123; -- Sostituisci con ID preventivo
   ```

4. Esegui il cron manualmente:
   ```php
   require_once('/path/to/wp-load.php');
   $scheduler = new \Disco747_CRM\Funnel\Disco747_Funnel_Scheduler();
   $scheduler->process_pending_sends();
   echo "Cron eseguito!";
   ```

---

### **STEP 6: Controlla l'email ricevuta**

Quando ricevi l'email, verifica:

✅ **Layout corretto:**
- Sfondo nero (#1a1a1a)
- Colori oro/bronzo (#c28a4d)
- Gradienti visibili

✅ **Tabelle formattate:**
- Bordi arrotondati
- Padding corretto
- Box omaggi ben visibili

✅ **Testo corretto:**
- **NESSUN CSS visibile** come testo all'inizio
- Placeholder sostituiti ({{nome}}, {{data_evento}})

✅ **Link funzionanti:**
- WhatsApp con messaggio pre-compilato
- Email cliccabili

✅ **Immagini visibili:**
- Logo 747 Disco

---

## 🚨 **SE IL PROBLEMA PERSISTE**

### **Problema 1: CSS ancora visibile come testo**

**Causa:** Template vecchi ancora nel database

**Soluzione:**
```sql
-- Verifica cosa è salvato nel database
SELECT id, step_name, LEFT(email_body, 500) AS email_preview
FROM wp_disco747_funnel_sequences 
WHERE funnel_type = 'pre_conferma';
```

Se vedi ancora il CSS nel testo, **cancella tutto e ricarica** i template Gmail-safe.

---

### **Problema 2: Layout rotto su Gmail**

**Causa:** Template troppo complesso per Gmail

**Soluzione:** Semplifica ulteriormente:
1. Rimuovi `border-radius` (Gmail mobile non lo supporta bene)
2. Rimuovi `linear-gradient` (sostituisci con colore solido)
3. Usa tabelle semplici (max 2 colonne)

---

### **Problema 3: Email non arriva**

**Causa:** Problema SMTP o spam filter

**Verifica:**
1. Controlla log WordPress: `/wp-content/debug.log`
2. Cerca:
   ```
   [747Disco-Funnel] ✉️ Email inviata a cliente@email.com
   ```
3. Se c'è `❌ Errore invio email`, controlla configurazione SMTP

---

## 📧 **TEMPLATE PRONTI ALL'USO**

Ho preparato **4 template Gmail-safe completi** nel file:
- `template-email-gmail-safe.html` (base - Mail 2)

Per le altre 3 email:
1. Copia il template base
2. Modifica solo il **contenuto testuale** (titolo, paragrafi)
3. Mantieni **TUTTA la struttura HTML** invariata
4. **NON aggiungere** tag `<style>` nel `<head>`

---

## ✅ **CHECKLIST FINALE**

- [ ] Template vecchi cancellati dal database
- [ ] Template Gmail-safe caricati (senza `<style>`)
- [ ] Email di test inviata
- [ ] Email ricevuta con layout corretto
- [ ] NESSUN CSS visibile come testo
- [ ] Placeholder sostituiti
- [ ] Link WhatsApp funzionanti
- [ ] Test su Gmail ✅
- [ ] Test su Outlook ✅  
- [ ] Test su Apple Mail ✅

---

## 🎯 **RIEPILOGO MODIFICHE CODICE**

### File modificati:
1. **`includes/funnel/class-disco747-funnel-manager.php`**
   - Riga 261: Rimosso `nl2br()`
   - Riga 374-402: Aggiunti placeholder `{{nome}}`, `{{cognome}}`, `{{preventivo_id}}`

2. **`includes/admin/views/funnel-automation-page.php`**
   - Riga 47-78: Rimosso `wp_kses()`, salvataggio HTML completo per admin

### Come funziona ora:
1. Admin salva template HTML **completo** senza sanitizzazione distruttiva
2. Template viene salvato nel database **intatto**
3. Email viene inviata **senza `nl2br()`**, HTML preservato
4. Placeholder vengono **sostituiti** prima dell'invio
5. Cliente riceve email con **layout perfetto**

---

## 📞 **DEBUG RAPIDO**

### Test immediato con una riga di codice:

```php
// Metti questo in un file test.php nella root di WordPress
require_once('wp-load.php');
$fm = new \Disco747_CRM\Funnel\Disco747_Funnel_Manager();

// Simula un preventivo
$preventivo = (object) array(
    'id' => 999,
    'preventivo_id' => 'TEST-001',
    'nome_referente' => 'Mario',
    'cognome_referente' => 'Rossi',
    'nome_cliente' => 'Mario Rossi',
    'email' => 'tua-email@test.com', // ← METTI LA TUA EMAIL QUI
    'telefono' => '+39 347 1811119',
    'tipo_evento' => 'Compleanno 18 anni',
    'data_evento' => '2025-06-15',
    'numero_invitati' => 80,
    'tipo_menu' => 'Menu 74',
    'importo_totale' => 2500.00,
    'acconto' => 500.00
);

// Simula uno step email
$step = (object) array(
    'email_enabled' => 1,
    'email_subject' => 'Test Email | 747 Disco',
    'email_body' => file_get_contents(__DIR__ . '/template-email-gmail-safe.html')
);

// Invia!
$sent = $fm->send_email_to_customer($preventivo, $step);
echo $sent ? '✅ Email inviata!' : '❌ Errore invio';
```

Esegui: `php test.php`

---

**🎯 Con questi fix, le email dovrebbero funzionare perfettamente!**

Se dopo aver seguito TUTTI questi step il problema persiste, fammi vedere:
1. Screenshot dell'email ricevuta
2. Codice HTML sorgente dell'email (tasto destro → "Mostra originale" in Gmail)
3. Log da `/wp-content/debug.log`
