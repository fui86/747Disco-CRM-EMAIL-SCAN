# 📋 GUIDA PLAYLIST - YouTube Karaoke Extension

## Panoramica

La funzionalità Playlist ti permette di creare e gestire una coda di video YouTube per le tue serate karaoke. Puoi aggiungere video, navigare tra di essi con controlli player, e ogni video si apre automaticamente a pieno schermo sul secondo monitor.

---

## Accesso alla Playlist

### Da Menu Principale

1. Clicca l'icona dell'estensione 🎤 nella toolbar di Chrome
2. Clicca il pulsante **"🎵 Apri Playlist"**
3. Si apre l'interfaccia di gestione playlist

---

## Aggiungere Video alla Playlist

### Procedura

1. **Trova il video su YouTube** che vuoi aggiungere
2. **Copia l'URL** del video (es: `https://www.youtube.com/watch?v=dQw4w9WgXcQ`)
3. **Incolla l'URL** nel campo "Incolla link YouTube qui..."
4. **Clicca "➕ Aggiungi"** o premi **Enter**
5. Il video viene aggiunto alla playlist

### URL Supportati

✅ `https://www.youtube.com/watch?v=VIDEO_ID`  
✅ `https://youtu.be/VIDEO_ID`  
✅ `https://m.youtube.com/watch?v=VIDEO_ID`  
✅ Qualsiasi variante di URL YouTube

### Validazione

- L'estensione controlla automaticamente che l'URL sia valido
- Se l'URL non è valido, ricevi un messaggio di errore
- Solo link YouTube sono accettati

---

## Gestire la Playlist

### Visualizzazione

- **Numero totale video**: Mostrato in alto (es: "Playlist (5 video)")
- **Video corrente**: Evidenziato con sfondo colorato
- **Indice numerico**: Ogni video è numerato (1, 2, 3...)
- **URL completo**: Visibile sotto ogni video

### Azioni per Video Singolo

#### ▶️ Play Video
- Clicca il pulsante **▶️** accanto al video
- Il video si apre in una nuova tab
- Fullscreen attivato automaticamente dopo 2 secondi
- Diventa il video corrente

#### 🗑️ Rimuovi Video
- Clicca il pulsante **🗑️** accanto al video
- Il video viene rimosso dalla playlist
- Gli indici vengono riorganizzati automaticamente

### Azioni Globali

#### 🗑️ Svuota Playlist
- Clicca "🗑️ Svuota Playlist" in basso
- Conferma l'azione
- Tutti i video vengono rimossi
- La playlist torna vuota

#### ← Torna al Menu
- Clicca "← Torna al Menu"
- Torni all'interfaccia principale dell'estensione
- La playlist rimane salvata

---

## Controlli Player

### Player Corrente

**Sezione "In Riproduzione"**
- Mostra il titolo del video corrente
- Mostra l'URL completo
- Controlli di navigazione

### Pulsanti di Controllo

#### ⏮️ Precedente
- Vai al video precedente nella playlist
- Apre il video automaticamente fullscreen
- Disabilitato se sei al primo video

#### ▶️ Play
- Riproduci il video corrente
- Se nessun video è corrente, riproduce il primo
- Apre in nuova tab fullscreen

#### ⏭️ Successivo
- Vai al video successivo nella playlist
- Apre il video automaticamente fullscreen
- Se sei all'ultimo, ricomincia dal primo

---

## Funzionamento Fullscreen

### Comportamento Automatico

Quando riproduci un video dalla playlist:

1. **Nuova Tab**: Video aperto in nuova tab Chrome
2. **Attesa 2 secondi**: Tempo per caricare il video
3. **Fullscreen attivato**: Automaticamente
4. **Secondo monitor**: Se disponibile, video mostrato lì
5. **Fade-in audio**: Volume sale gradualmente (2 sec)

### Multi-Monitor

- Se hai **2+ monitor**: video va sul secondario
- Se hai **1 monitor**: fullscreen normale
- Rilevamento automatico

---

## Storage e Persistenza

### Salvataggio Automatico

- La playlist viene salvata automaticamente
- Ogni modifica è persistita immediatamente
- Usa Chrome Storage Sync

### Cosa Viene Salvato

✅ Lista completa dei video  
✅ URL di ogni video  
✅ Video ID estratto  
✅ Indice video corrente  
✅ Timestamp aggiunta video

### Persistenza

- La playlist **sopravvive alla chiusura del browser**
- La playlist **rimane tra sessioni**
- La playlist **si sincronizza tra dispositivi** (Chrome Sync)

---

## Workflow Karaoke Completo

### Preparazione (Prima dell'Evento)

1. Apri la Playlist
2. Aggiungi tutti i video karaoke per la serata
3. Controlla l'ordine
4. Chiudi il browser (playlist salvata)

### Durante l'Evento

1. Apri Chrome ed estensione
2. Vai alla Playlist
3. Clicca "▶️ Play" per il primo video
4. Video si apre fullscreen su monitor 2
5. Quando finisce, clicca "⏭️ Successivo"
6. Video successivo si apre automaticamente
7. Ripeti fino alla fine

### Vantaggi

✅ **Preparazione anticipata**: Lista pronta prima  
✅ **Navigazione rapida**: Un clic per cambiare video  
✅ **Fullscreen automatico**: Nessuna azione manuale  
✅ **Ordine definito**: Niente confusione  
✅ **Professionale**: Workflow fluido

---

## Casi d'Uso

### Serata Karaoke (10 canzoni)

**Setup:**
```
1. Bohemian Rhapsody - Queen
2. Wonderwall - Oasis
3. Sweet Child O' Mine - Guns N' Roses
4. Hotel California - Eagles
5. Livin' on a Prayer - Bon Jovi
6. Don't Stop Believin' - Journey
7. Mr. Brightside - The Killers
8. I Want It That Way - Backstreet Boys
9. Africa - Toto
10. We Are the Champions - Queen
```

**Workflow:**
- Aggiungi tutti i 10 video in anticipo
- Durante l'evento, solo "⏭️ Successivo"
- Ogni cambio: ~5 secondi
- Zero ricerche manuali
- Zero copia/incolla in diretta

### DJ Set con Video

**Setup:**
- Mix di video musicali
- Ordine definito per atmosfera
- Transizioni tra generi

**Workflow:**
- Playlist preparata per DJ set
- Fade-in/fade-out automatico
- Skip rapido tra video
- Fullscreen sempre attivo

### Presentazione Aziendale

**Setup:**
- Video promozionali
- Tutorial
- Demo prodotti

**Workflow:**
- Ordine presentazione definito
- Navigazione semplice
- Fullscreen professionale
- Niente interruzioni

---

## Tips & Tricks

### Organizzazione

💡 **Nomina i video**: Puoi modificare il titolo dopo l'aggiunta (feature futura)  
💡 **Ordina per evento**: Crea playlist diverse per eventi diversi  
💡 **Backup URL**: Tieni una copia testuale degli URL

### Performance

💡 **Precarica video**: Apri YouTube in anticipo per caching  
💡 **Controlla connessione**: Assicurati di avere buona connessione  
💡 **Test prima**: Prova la playlist prima dell'evento

### Best Practices

✅ Aggiungi video il giorno prima  
✅ Controlla che tutti i video siano disponibili  
✅ Testa il passaggio tra video  
✅ Verifica funzionamento secondo monitor  
✅ Tieni un piano B (lista cartacea)

---

## Troubleshooting

### Problema: Video non si aggiunge

**Cause:**
- URL non valido
- Non è un link YouTube
- Problema di rete

**Soluzione:**
1. Controlla che l'URL sia corretto
2. Prova a copiare di nuovo da YouTube
3. Verifica connessione internet

### Problema: Fullscreen non si attiva

**Cause:**
- Tab non ancora caricata
- Popup bloccato dal browser
- Permessi mancanti

**Soluzione:**
1. Attendi qualche secondo
2. Attiva manualmente con F11
3. Controlla permessi estensione

### Problema: Playlist scomparsa

**Cause:**
- Storage cancellato
- Sync disabilitato
- Browser reinstallato

**Soluzione:**
1. Controlla Chrome Sync sia attivo
2. Verifica storage non sia pieno
3. Re-aggiungi i video

### Problema: Skip non funziona

**Cause:**
- Fine playlist raggiunta
- Nessun video successivo
- Errore caricamento

**Soluzione:**
1. Controlla indice corrente
2. Prova "▶️ Play" invece di skip
3. Ricarica interfaccia playlist

---

## Domande Frequenti (FAQ)

### Quanti video posso aggiungere?

**Risposta**: Limite teorico ~100 video. Limite pratico dipende da Chrome Storage (max ~100KB). Per uso normale (10-50 video) non ci sono problemi.

### La playlist si sincronizza su altri computer?

**Risposta**: Sì, se hai Chrome Sync attivo, la playlist si sincronizza automaticamente tra tutti i tuoi dispositivi Chrome.

### Posso modificare l'ordine dei video?

**Risposta**: Attualmente no. Devi rimuovere e ri-aggiungere nell'ordine desiderato. Feature futura: drag & drop.

### Cosa succede se chiudo il browser?

**Risposta**: La playlist viene salvata automaticamente. Quando riapri, trovi tutto come lo hai lasciato, incluso il video corrente.

### Posso esportare la playlist?

**Risposta**: Non direttamente. Puoi copiare manualmente gli URL. Feature futura: export/import JSON.

---

## Keyboard Shortcuts

### Nell'Interfaccia Playlist

- **Enter**: Aggiungi video (quando il campo URL è selezionato)
- **Esc**: Chiudi popup (browser default)

### Durante Riproduzione Video

- **Spazio**: Play/Pause (YouTube default)
- **F**: Fullscreen (YouTube default)
- **M**: Mute (YouTube default)
- **←/→**: Avanti/Indietro 5 sec (YouTube default)

---

## Limitazioni Note

⚠️ **Nessun drag & drop**: Ordine non modificabile dopo aggiunta  
⚠️ **Titoli generici**: "Video 1", "Video 2", ecc.  
⚠️ **Nessun preview**: Nessuna thumbnail del video  
⚠️ **Solo YouTube**: Non supporta altri siti video  
⚠️ **Limite storage**: ~100 video max

---

## Prossimi Miglioramenti (Future Features)

🔮 **Drag & drop** per riordinare video  
🔮 **Titoli personalizzati** per ogni video  
🔮 **Thumbnail** dei video nella lista  
🔮 **Export/Import** playlist in JSON  
🔮 **Playlist multiple** (diverse per eventi)  
🔮 **Ricerca** nella playlist  
🔮 **Filtri** per durata/artista  
🔮 **Auto-play** prossimo video

---

## Conclusione

La funzionalità Playlist trasforma l'estensione da semplice skip-ads a completo sistema di gestione karaoke. Perfetta per eventi professionali, DJ set, e presentazioni.

**Caratteristiche Chiave:**
- 🎵 Gestione completa playlist
- ⏭️ Skip rapido tra video
- 📺 Fullscreen automatico
- 💾 Storage persistente
- 🎤 Perfetto per karaoke

**Pronto per l'uso professionale!** 🎉

---

_Ultima modifica: Dicembre 2025_  
_Versione: 1.0.0_
