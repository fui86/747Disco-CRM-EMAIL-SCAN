# 🗑️ Come Svuotare la Cache - 747 Disco CRM

## 📱 CACHE BROWSER MOBILE

### iPhone / Safari iOS
1. **Impostazioni** → **Safari**
2. Scorri giù → **"Cancella dati siti web e cronologia"**
3. Conferma
4. Riapri Safari e vai al CRM

**Alternativa rapida:**
- Tieni premuto il tasto **Reload** in Safari
- Seleziona **"Ricarica senza contenuti memorizzati"**

---

### Android / Chrome
1. Apri **Chrome**
2. Tap **⋮** (3 puntini) in alto a destra
3. **Impostazioni** → **Privacy e sicurezza**
4. **Cancella dati di navigazione**
5. Seleziona solo **"Immagini e file memorizzati nella cache"**
6. **Cancella dati**

**Alternativa rapida:**
- Nella pagina CRM, tap su **🔄** per reload
- Aggiungi `?v=123` alla URL

---

## 💻 CACHE BROWSER DESKTOP

### Chrome / Edge
```
Windows: CTRL + SHIFT + R
Mac: CMD + SHIFT + R
```

### Firefox
```
Windows: CTRL + SHIFT + DEL → Seleziona Cache → Cancella
Mac: CMD + SHIFT + DEL → Seleziona Cache → Cancella
```

### Safari (Mac)
```
CMD + OPTION + E (Svuota cache)
Poi: CMD + R (Ricarica)
```

---

## 🔧 CACHE SERVER WORDPRESS

Se il CSS non si aggiorna nemmeno dopo aver svuotato la cache del browser, potrebbe esserci una cache lato server.

### **Plugin Cache WordPress**

Se hai installato plugin di cache tipo:
- **WP Rocket**
- **W3 Total Cache**
- **WP Super Cache**
- **LiteSpeed Cache**

**SOLUZIONE:**
1. Vai in **Dashboard WordPress**
2. Cerca il plugin di cache nel menu
3. Clicca **"Svuota Cache"** o **"Purge All"**

---

### **Cache Server Hosting**

Alcuni hosting hanno cache integrate:

#### **SiteGround**
1. **Site Tools** → **Speed** → **Caching**
2. Clicca **"Flush All Caches"**

#### **Cloudflare**
1. Login su **Cloudflare**
2. Seleziona il dominio **747disco.it**
3. **Caching** → **Purge Everything**

#### **Kinsta**
1. **Dashboard Kinsta**
2. Seleziona il sito
3. **Tools** → **Clear Cache**

---

## 🆘 METODO UNIVERSALE (Sempre Funziona)

Aggiungi un parametro di versione alla URL:

```
PRIMA:
https://gestionale.747disco.it/wp-admin/admin.php?page=disco747-crm

DOPO:
https://gestionale.747disco.it/wp-admin/admin.php?page=disco747-crm&v=12345
```

Cambia il numero `12345` ogni volta per forzare il reload.

---

## 🔍 COME VERIFICARE SE LA CACHE È STATA SVUOTATA

### **Metodo 1: Ispeziona Sorgente**

1. Sulla pagina CRM, apri il **codice sorgente**:
   - **Mobile Safari**: Non disponibile, usa Desktop
   - **Chrome Desktop**: CTRL+U (Win) o CMD+OPTION+U (Mac)

2. Cerca questa riga:
```html
<!-- Cache Buster: 1730970123 -->
```

3. Ricarica la pagina e **controlla che il numero sia diverso**
   - ✅ **Numero cambia** = Cache funziona
   - ❌ **Numero uguale** = Cache ancora attiva

---

### **Metodo 2: Controlla CSS**

1. Apri **DevTools** (F12)
2. Vai su **Elements** (o **Inspector**)
3. Cerca `#calendario-eventi`
4. Controlla se vedi le media queries:

```css
@media (max-width: 768px) {
    #calendario-eventi {
        margin: 0 0 20px 0;
        border-radius: 12px !important;
    }
}
```

5. Guarda il valore `min-height` dei giorni:
   - ✅ **36px** = CSS nuovo caricato
   - ❌ **50px+** = CSS vecchio ancora in cache

---

## 🎯 CHECKLIST COMPLETA RISOLUZIONE CACHE

Segui questi step **IN ORDINE**:

- [ ] 1. **Svuota cache browser** (CTRL+SHIFT+R su PC, istruzioni sopra su mobile)
- [ ] 2. **Ricarica pagina** (F5 o tap reload)
- [ ] 3. **Verifica timestamp** nel sorgente HTML
- [ ] 4. Se non funziona: **Svuota cache WordPress** (plugin cache)
- [ ] 5. Se non funziona: **Svuota cache hosting** (Cloudflare, etc.)
- [ ] 6. Se non funziona: **Aggiungi ?v=123** alla URL
- [ ] 7. Se non funziona: **Ricarica file PHP** sul server via FTP
- [ ] 8. **Ultimo tentativo**: Cambia nome del file (es. `main-page-v2.php`)

---

## ⚡ SOLUZIONE RAPIDA PER SVILUPPO

Per **disabilitare completamente la cache** durante lo sviluppo:

### **Aggiungi questo al wp-config.php:**

```php
// Disabilita cache durante sviluppo
define('WP_CACHE', false);
define('CONCATENATE_SCRIPTS', false);
```

⚠️ **Attenzione:** Rimuovi questa modifica quando vai in produzione!

---

## 🧪 TEST FINALE

Dopo aver svuotato tutte le cache:

1. Apri la dashboard CRM su **smartphone**
2. Ruota lo schermo in **verticale**
3. Verifica che il **calendario sia visibile tutto intero** senza scroll
4. I giorni devono essere **piccoli ma cliccabili** (~32-36px)
5. I pallini eventi devono essere **visibili ma piccoli** (~2-3px)

**Screenshot atteso (mobile 375px):**
```
┌─────────────────────────┐
│ [Nov▾][2025▾][📍Oggi]  │  ← Compatto
│  ‹  Novembre 2025   ›   │
├─────────────────────────┤
│ L M M G V S D           │  ← Piccolo
│ 1 2 3 4 5 6 7           │  ← 32px
│ 8 9 10 11 12 13 14      │
│ 15 16 17 18 19 20 21    │  ← Tutto visibile!
│ 22 23 24 25 26 27 28    │
│ 29 30                   │
└─────────────────────────┘
```

---

## 📞 SUPPORTO

Se dopo **tutti questi step** il CSS non si aggiorna:

1. **Verifica che il file PHP sia stato caricato**:
   ```bash
   ls -lah /path/to/includes/admin/views/main-page.php
   ```
   Controlla la data di modifica

2. **Verifica permessi file**:
   ```bash
   chmod 644 main-page.php
   ```

3. **Forza WordPress a ricaricare i file**:
   - Vai in **Plugins** → Disattiva e Riattiva **747 Disco CRM**

4. **Riavvia server** (se hai accesso):
   ```bash
   sudo service apache2 restart
   # oppure
   sudo service nginx restart
   ```

---

**Fine troubleshooting cache.** 🎉
