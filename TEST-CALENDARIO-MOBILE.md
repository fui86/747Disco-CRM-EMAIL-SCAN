# 📱 TEST CALENDARIO MOBILE v3.0

## ✅ COSA È CAMBIATO

Ho **riscritto completamente** il CSS mobile con un approccio totalmente diverso:

### **PRIMA (Non Funzionava)**
```css
/* Usava selettori di attributo che non funzionano bene con inline styles */
#calendario-eventi [style*="padding: 25px 30px"] {
    padding: 12px 10px !important;
}
```

### **DOPO (Dovrebbe Funzionare)**
```css
/* Usa selettori DOM diretti specifici che hanno priorità massima */
#calendario-eventi > div:first-child {
    padding: 12px 10px !important;
}
```

---

## 🔧 MODIFICHE TECNICHE

### **1. Selettori Più Specifici**
- ✅ Da `[style*="..."]` a selettori DOM diretti `> div:first-child`
- ✅ Massima specificità CSS
- ✅ `!important` su OGNI regola

### **2. Dimensioni Mobile Ottimizzate**
- **Tablet (768px):** Giorni **36px**
- **Mobile (576px):** Giorni **32px**
- **Extra small (400px):** Giorni **30px**

### **3. Script Debug Integrato**
Il nuovo script nel footer stampa nella **Console del browser**:
```
[Calendario] Device width: 375 - Mobile: true
[Calendario] Applicazione stili mobile forzata
[Calendario] Altezza cella giorno: 32px (Target: 32-36px su mobile)
[Calendario] ✅ CSS mobile applicato correttamente!
```

---

## 📲 COME TESTARE (IMPORTANTE!)

### **STEP 1: Carica il file sul server**
Ricarica `main-page.php` aggiornato in:
```
/wp-content/plugins/747disco-crm/includes/admin/views/
```

### **STEP 2: Svuota TUTTE le cache**

#### **A) Cache Browser Smartphone**

**iPhone / Safari:**
1. **Impostazioni** → **Safari**
2. **"Avanzate"** → **"Dati dei siti web"**
3. **"Rimuovi tutti i dati"**
4. Conferma

**Android / Chrome:**
1. **Chrome** → **⋮** (3 puntini)
2. **Cronologia** → **Cancella dati**
3. Seleziona **"Immagini e file memorizzati nella cache"**
4. **Cancella**

#### **B) Cache WordPress**
Se hai plugin di cache:
- WP Rocket: **Svuota cache**
- W3 Total Cache: **Purge All**
- LiteSpeed: **Purge All**

#### **C) Cache Server/CDN**
- **Cloudflare:** Dashboard → Caching → **Purge Everything**
- **SiteGround:** Tools → **Flush Cache**

### **STEP 3: Test con Console Aperta**

**Su Desktop (per test rapido):**
1. Apri il CRM
2. Premi **F12** (DevTools)
3. Tab **Console**
4. Premi **CTRL+SHIFT+M** (Toggle Device Toolbar)
5. Seleziona **iPhone 12 Pro** o **Pixel 5**
6. **Ricarica** la pagina (CTRL+R)
7. Guarda i log nella console:

✅ **SUCCESSO:**
```
[Calendario] Device width: 390 - Mobile: true
[Calendario] Altezza cella giorno: 32px
[Calendario] ✅ CSS mobile applicato correttamente!
```

❌ **FALLIMENTO:**
```
[Calendario] Device width: 390 - Mobile: true
[Calendario] Altezza cella giorno: 56px
[Calendario] ⚠️ CSS mobile NON applicato! Altezza troppo grande.
[Calendario] Svuota la cache del browser!
```

### **STEP 4: Test su Smartphone Reale**

1. **Connetti il telefono** alla stessa rete Wi-Fi
2. **Apri Safari/Chrome** sul telefono
3. Vai su: `https://gestionale.747disco.it/wp-admin/admin.php?page=disco747-crm`
4. **Ispeziona** se il calendario è compatto

**Come aprire Console su Mobile:**

**iPhone (Safari):**
1. **Impostazioni** → **Safari** → **Avanzate**
2. Attiva **"Web Inspector"**
3. Collega iPhone al Mac via cavo
4. Su Mac: **Safari** → **Sviluppo** → **[Nome iPhone]** → **[Pagina]**
5. Guarda la console

**Android (Chrome):**
1. **Chrome** sul PC
2. Vai su `chrome://inspect`
3. Collega Android via USB
4. Attiva **"Debug USB"** su Android
5. Click **"Inspect"** sulla pagina
6. Guarda la console

---

## 🎯 RISULTATO ATTESO

### **Desktop (> 768px)**
```
┌────────────────────────────────────┐
│  [Novembre ▾] [2025 ▾] [Oggi]     │
│  ‹      Novembre 2025       ›      │
├────────────────────────────────────┤
│ Lun  Mar  Mer  Gio  Ven  Sab  Dom │
│                                    │
│  1    2    3    4    5    6    7   │  ← Grandi (auto)
│  8    9   10   11   12   13   14   │
└────────────────────────────────────┘
```

### **Mobile (< 576px)**
```
┌─────────────────────────┐
│ [Nov▾][2025▾][Oggi]    │  ← Compatto
│  ‹  Novembre 2025  ›    │
├─────────────────────────┤
│ L M M G V S D           │  ← Mini
│ 1 2 3 4 5 6 7           │  ← 32px
│ 8 9 10 11 12 13 14      │
│ 15 16 17 18 19 20 21    │  ← Tutto visibile!
│ 22 23 24 25 26 27 28    │
│ 29 30                   │
└─────────────────────────┘
```

---

## 🔍 DEBUG: Se Ancora Non Funziona

### **1. Verifica che il file sia stato caricato**

**Metodo A: Timestamp nel sorgente**

1. Sul telefono, apri la pagina CRM
2. View Source (se possibile) o usa Desktop
3. Cerca nel codice HTML:
```html
<!-- MOBILE CALENDAR v3.0 - CACHE BUSTER: 1730971234 -->
```

4. Ricarica e **verifica che il numero cambi**
   - ✅ **Cambia:** File aggiornato sul server
   - ❌ **Uguale:** File NON caricato o cache attiva

**Metodo B: Cerca stringa specifica**

Nel sorgente HTML, cerca:
```css
/* CALENDARIO RESPONSIVE - MOBILE COMPATTO v3.0 ULTRA AGGRESSIVE */
```

- ✅ **Presente:** CSS nuovo caricato
- ❌ **Assente:** File vecchio ancora attivo

### **2. Verifica CSS applicato**

**DevTools Mobile (F12):**

1. Tab **Elements** (o **Inspector**)
2. Seleziona un giorno del calendario
3. Guarda **Computed Styles**
4. Cerca `min-height`:
   - ✅ **30-36px:** CSS mobile applicato
   - ❌ **50px+:** CSS mobile NON applicato (cache!)

### **3. Test Bypass Cache Forzato**

Aggiungi `?nocache=12345` alla URL:
```
https://gestionale.747disco.it/wp-admin/admin.php?page=disco747-crm&nocache=12345
```

Cambia il numero ogni volta per forzare il bypass.

---

## 📊 CHECKLIST COMPLETA

Segui questi step **IN ORDINE**:

- [ ] 1. ✅ File `main-page.php` caricato sul server
- [ ] 2. 🗑️ Cache browser smartphone svuotata
- [ ] 3. 🗑️ Cache WordPress svuotata (se presente)
- [ ] 4. 🗑️ Cache CDN/hosting svuotata (se presente)
- [ ] 5. 🔄 Pagina ricaricata sul telefono
- [ ] 6. 👀 Console verificata (log presenti?)
- [ ] 7. 📏 Altezza giorni verificata (32-36px su mobile?)
- [ ] 8. ✅ Calendario compatto visibile senza scroll?

---

## 💡 DIFFERENZE TECNICHE v3.0

| Aspetto | v2.0 (Vecchio) | v3.0 (Nuovo) |
|---------|----------------|--------------|
| **Selettori** | `[style*="..."]` | `> div:first-child` |
| **Specificità** | Media | Massima |
| **!important** | Pochi | Tutti |
| **Debug** | Assente | Console log |
| **Viewport** | Eredita | Forzato |
| **Cache Buster** | Timestamp statico | Timestamp dinamico |
| **Target Celle** | `[style*="aspect-ratio"]` | `div[onclick]` |

---

## 🆘 ULTIMO TENTATIVO

Se **PROPRIO** non funziona:

### **Opzione Nucleare: Disabilita Inline Styles**

Aggiungi questo JavaScript **in fondo al file**:

```javascript
<script>
// OPZIONE NUCLEARE: Rimuovi inline styles e applica classi
(function() {
    if (window.innerWidth <= 576) {
        const calendario = document.getElementById('calendario-eventi');
        if (calendario) {
            // Trova tutti i giorni
            const giorni = calendario.querySelectorAll('div[onclick]');
            giorni.forEach(function(giorno) {
                giorno.style.minHeight = '32px';
                giorno.style.maxHeight = '32px';
                giorno.style.fontSize = '0.65rem';
            });
        }
    }
})();
</script>
```

Questo **forza** JavaScript a sovrascrivere gli inline styles a runtime.

---

## 📸 SCREENSHOT PER VERIFICA

Quando testi su smartphone, fai **screenshot** e confronta:

### **PRIMA (Grande, scroll necessario):**
- Giorni: ~50-60px
- Mese non tutto visibile
- Scroll verticale presente
- Header grande

### **DOPO (Compatto, tutto visibile):**
- Giorni: 32-36px
- Tutto il mese visibile senza scroll
- Gap 1-2px tra giorni
- Header compatto

---

**Fine guida test.** 🎯

Segui TUTTI gli step e fammi sapere cosa dice la Console del browser!
