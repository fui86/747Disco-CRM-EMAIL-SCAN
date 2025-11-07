# 📅 Calendario Eventi Stile iPhone - 747 Disco CRM

## ✅ Implementato

Il calendario eventi in stile iPhone è stato aggiunto alla dashboard principale del CRM.

---

## 🎯 Posizione

Il calendario si trova nella pagina principale:

```
https://gestionale.747disco.it/wp-admin/admin.php?page=disco747-crm
```

**Posizionato:**
- Subito dopo l'header con il pulsante "NUOVO PREVENTIVO"
- Prima dei blocchi "Statistiche & Azioni" e "Eventi Imminenti"
- Larghezza: 100% della pagina
- Altezza: Dinamica in base al contenuto

---

## 🎨 Design Stile iPhone

### **Header Nero Elegante**
- Background: Gradiente nero (#1d1d1f → #000000)
- Pulsanti ‹ › per navigare tra i mesi
- Titolo centrale: "Mese Anno" (es. "Novembre 2025")
- Contatore eventi: "X eventi questo mese"

### **Griglia Calendario**
- 7 colonne (Lun-Dom)
- Giorni in cerchi (come iOS)
- Giorno corrente: Cerchio blu (#007aff) con testo bianco
- Giorni con eventi: Background grigio chiaro (#e5e5ea)
- Giorni liberi: Trasparente

### **Indicatori Eventi**
- **Pallino verde** (●) = Preventivo confermato
- **Pallino blu** (●) = Preventivo attivo
- I pallini appaiono sotto il numero del giorno

---

## 📊 Funzionalità

### **1. Vista Mensile**
- Mostra il mese corrente all'apertura
- **Menu a tendina Mese**: Selezione diretta (Gennaio-Dicembre)
- **Menu a tendina Anno**: Selezione diretta (range 2024-2027)
- **Pulsante "📍 Oggi"**: Torna istantaneamente al mese corrente
- Naviga tra i mesi con i pulsanti ‹ › (navigazione rapida)
- Visualizza solo preventivi **attivi** e **confermati** (NO annullati)

### **2. Click su un Giorno**
- Click su un giorno con eventi → Appare lista eventi sotto il calendario
- Scroll automatico smooth verso la lista
- Data formattata in italiano: "Lunedì 15 novembre 2025"

### **3. Dettaglio Eventi**
Per ogni evento del giorno mostra:
- **Tipo evento** (es. "18 Anni", "Festa Aziendale")
- **Nome cliente**
- **Badge stato**:
  - ✅ CONFERMATO (verde #34c759)
  - ⏳ ATTIVO (blu #007aff)
- **Pulsanti azione**:
  - 📱 WhatsApp → Apre chat WhatsApp
  - ✉️ Email → Apre client email

### **4. Hover Effects**
- Giorni con eventi si ingrandiscono (scale 1.1) al passaggio mouse
- Ombra dinamica sui pulsanti
- Transizioni smooth su tutti gli elementi

---

## 💾 Query Database

```sql
-- Carica eventi del mese
SELECT * FROM wp_disco747_preventivi 
WHERE data_evento BETWEEN '{primo_giorno_mese}' AND '{ultimo_giorno_mese}' 
AND stato IN ('attivo', 'confermato')
ORDER BY data_evento ASC
```

**Campi utilizzati:**
- `data_evento` → Per posizionare nel calendario
- `tipo_evento` → Titolo evento
- `nome_cliente` → Nome cliente
- `stato` → Badge confermato/attivo
- `acconto` → Se > 0 conta come confermato
- `telefono` → Link WhatsApp
- `email` → Link Email

---

## 🔍 Come Usare il Calendario

### **Caso 1: Verificare Data Libera/Occupata**

**Scenario:** Cliente chiede disponibilità per sabato 15 novembre

**Passi:**
1. Apri dashboard principale
2. Guarda il calendario
3. Trova il giorno 15
   - **Giorno trasparente** → Data libera ✅
   - **Giorno grigio** → Data occupata ⚠️
4. Se occupato, click sul giorno per vedere:
   - Chi ha prenotato
   - Se è confermato (●) o attivo (●)

---

### **Caso 2: Contattare Cliente per Evento Imminente**

**Scenario:** Evento tra 3 giorni, devi confermare dettagli

**Passi:**
1. Apri dashboard
2. Click sul giorno dell'evento nel calendario
3. Appare lista eventi sotto
4. Click su "📱 WhatsApp" → Si apre chat
5. Messaggio rapido al cliente

---

### **Caso 3: Navigare tra Mesi**

**Scenario:** Verificare disponibilità dicembre/gennaio

**Metodo 1 - Menu a Tendina (VELOCE):**
1. Click sul menu a tendina "Mese"
2. Scegli "Dicembre" dal menu
3. La pagina ricarica istantaneamente a dicembre
4. Per anno successivo, cambia anche il menu "Anno"

**Metodo 2 - Frecce (RAPIDO):**
1. Click su › (freccia destra) per mese successivo
2. Click su ‹ (freccia sinistra) per mese precedente
3. La pagina ricarica con il nuovo mese

**Metodo 3 - Pulsante Oggi:**
1. Click su "📍 Oggi"
2. Torna immediatamente al mese corrente

**URL:** Cambia automaticamente: `?cal_month=12&cal_year=2025`

---

## 📱 Responsive Design

### **Desktop (> 1400px)**
- Giorni grandi e spaziosi
- Pallini eventi più visibili (6px)
- Font size 1rem

### **Desktop Standard (992px - 1400px)**
- Design ottimale standard
- Pallini eventi 5px
- Font size 0.9rem

### **Tablet (768px - 992px)**
- Header più compatto
- Padding ridotto
- Giorni leggermente più piccoli
- Gap ridotto tra giorni

### **Mobile (576px - 768px)**
- Header stacked verticale
- Pulsanti mese centrati
- Giorni molto compatti
- Intestazioni giorni abbreviate
- Font size 0.75rem
- Pallini eventi 4px
- Min-height giorni: 40px

### **Mobile Piccolo (< 576px)**
- Intestazioni giorni 0.6rem
- Min-height giorni: 35px
- Margini ridotti al minimo
- Lista eventi con font più piccoli

---

## 🎨 Colori Utilizzati

| Elemento | Colore | Hex | Uso |
|----------|--------|-----|-----|
| Header | Nero | #1d1d1f → #000000 | Gradient header |
| Oggi | Blu iOS | #007aff | Giorno corrente |
| Giorno occupato | Grigio chiaro | #e5e5ea | Giorni con eventi |
| Confermato | Verde iOS | #34c759 | Badge/pallino confermato |
| Attivo | Blu iOS | #007aff | Badge/pallino attivo |
| WhatsApp | Verde WA | #25D366 | Pulsante WhatsApp |
| Email | Blu iOS | #007aff | Pulsante Email |
| Testo giorni liberi | Grigio | #8e8e93 | Giorni senza eventi |
| Background card | Grigio chiaro | #f5f5f7 | Card eventi |

---

## 🔧 Personalizzazioni Possibili

### **Aggiungere più colonne alle card evento**

Modifica la funzione `mostraEventi()` in JavaScript:

```javascript
// Aggiungi numero invitati
<div>
    👥 ${evento.numero_invitati || 0} invitati
</div>
```

### **Mostrare anche preventivi annullati (con indicatore rosso)**

Modifica la query PHP:

```php
// Rimuovi filtro stato
$eventi_calendario = $wpdb->get_results($wpdb->prepare(
    "SELECT * FROM {$table} 
     WHERE data_evento BETWEEN %s AND %s 
     ORDER BY data_evento ASC",
    $primo_giorno,
    $ultimo_giorno
), ARRAY_A);

// Aggiungi pallino rosso per annullati
<?php if ($evt['stato'] === 'annullato'): ?>
    <div style="width: 5px; height: 5px; background: #ff3b30; border-radius: 50%;" title="Annullato"></div>
<?php endif; ?>
```

### **Aggiungere link "Modifica Preventivo"**

Nella funzione `mostraEventi()`:

```javascript
<a href="<?php echo admin_url('admin.php?page=disco747-crm&action=edit_preventivo&id='); ?>${evento.id}" 
   style="background: #8e8e93; color: white; padding: 8px 15px; border-radius: 20px; text-decoration: none; font-size: 0.85rem; font-weight: 600;">
    ✏️ Modifica
</a>
```

---

## 🐛 Risoluzione Problemi

### **Problema: Calendario non mostra eventi**

**Causa:** Query database non trova preventivi con stato corretto

**Soluzione:**
1. Verifica che i preventivi abbiano `stato = 'attivo'` o `stato = 'confermato'`
2. Controlla che `data_evento` sia nel range del mese visualizzato
3. Debug con questo codice PHP:

```php
echo '<pre>';
print_r($eventi_calendario);
echo '</pre>';
```

---

### **Problema: Click su giorno non mostra eventi**

**Causa:** JavaScript non trova dati eventi

**Soluzione:**
1. Apri console browser (F12)
2. Verifica che `eventiPerData` contenga dati:

```javascript
console.log('Eventi per data:', eventiPerData);
```

3. Se è vuoto, controlla che PHP stia popolando correttamente l'array

---

### **Problema: Giorni disallineati**

**Causa:** Primo giorno del mese non parte da lunedì

**Soluzione:**
- Il codice calcola automaticamente le celle vuote
- Se persiste, verifica che `date('N')` ritorni 1-7 (Lun-Dom)

---

### **Problema: WhatsApp link non funziona**

**Causa:** Numero telefono non formattato correttamente

**Soluzione:**
```javascript
// Debug numero telefono
console.log('Numero originale:', evento.telefono);
console.log('Numero formattato:', whatsappLink);
```

Il formato corretto è: `+39XXXXXXXXXX` (prefisso + numero senza spazi)

---

### **Problema: Date in inglese invece che italiano**

**Causa:** Locale server non configurato

**Soluzione:**
- Abbiamo usato array manuali dei mesi italiani
- Se vedi ancora inglese, verifica che `$mesi_nomi` sia definito correttamente

---

## ✅ Checklist Funzionalità

- [x] Vista mensile con griglia 7 giorni
- [x] Navigazione mesi (‹ ›)
- [x] Indicatori eventi (pallini colorati)
- [x] Click su giorno mostra eventi
- [x] Badge stato (Confermato/Attivo)
- [x] Link WhatsApp funzionante
- [x] Link Email funzionante
- [x] Responsive mobile/tablet/desktop
- [x] Giorno corrente evidenziato (blu)
- [x] Hover effects
- [x] Scroll smooth verso eventi
- [x] Solo attivi e confermati (NO annullati)
- [x] Conta eventi nel mese (header)

---

## 📊 Performance

- **Query DB:** 1 query per mese (cache-friendly)
- **JavaScript:** Nessuna richiesta AJAX (tutto in-page)
- **Rendering:** Lato server (SEO-friendly)
- **Navigazione:** Reload pagina (mantiene stato WordPress)

---

## 🚀 Prossimi Miglioramenti (Opzionali)

1. **Vista settimanale** → Click su "Settimana" per vedere 7 giorni dettagliati
2. **Filtri stato** → Toggle per mostrare/nascondere annullati
3. **Legenda colori** → Box che spiega significato pallini
4. **Export calendario** → Download ICS per importare in Google Calendar
5. **Notifiche eventi** → Badge rosso su giorni con eventi in scadenza
6. **Vista giorno singolo** → Dettaglio completo evento con tutte le info
7. **Drag & Drop** → Spostare eventi tra giorni (richiede backend)

---

**Calendario eventi implementato e funzionante! Perfetto per gestione appuntamenti rapida.** 📅✨
