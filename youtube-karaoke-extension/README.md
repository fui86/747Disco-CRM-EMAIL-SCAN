# 🎤 YouTube Karaoke - Ad Skipper & Fullscreen Extension

Estensione per Google Chrome che salta automaticamente le pubblicità di YouTube e trasmette i video a pieno schermo sul secondo monitor, perfetta per karaoke!

## ✨ Caratteristiche

- **🚫 Salta Pubblicità Automaticamente**: Salta istantaneamente tutti gli annunci di YouTube
- **📺 Fullscreen su Secondo Monitor**: Apri i video a pieno schermo sul monitor secondario con un clic
- **⌨️ Scorciatoia da Tastiera**: Usa `Ctrl+Shift+K` per avviare rapidamente la modalità karaoke
- **🎛️ Controlli Intuitivi**: Interfaccia popup semplice per gestire tutte le funzionalità
- **💾 Impostazioni Persistenti**: Le tue preferenze vengono salvate automaticamente

## 📦 Installazione

### Metodo 1: Da File (Sviluppo)

1. Scarica o clona questa repository
2. Apri Google Chrome e vai su `chrome://extensions/`
3. Attiva la "Modalità sviluppatore" in alto a destra
4. Clicca su "Carica estensione non pacchettizzata"
5. Seleziona la cartella `youtube-karaoke-extension`
6. L'estensione è ora installata! 🎉

### Metodo 2: Da Chrome Web Store (Quando disponibile)

*In attesa di pubblicazione sul Chrome Web Store*

## 🎯 Come Usare

### Per il Karaoke:

1. **Apri YouTube** e naviga al video che vuoi usare per il karaoke
2. **Clicca sull'icona dell'estensione** nella barra degli strumenti di Chrome
3. **Clicca su "Apri su Secondo Monitor"** oppure usa la scorciatoia `Ctrl+Shift+K`
4. Il video verrà aperto a pieno schermo sul tuo secondo monitor
5. **Controlla la riproduzione dal monitor principale** mentre il pubblico vede il video sul secondo schermo

### Funzionalità Automatiche:

- **Salta Pubblicità**: L'estensione salterà automaticamente tutte le pubblicità su YouTube
- Puoi disattivare questa funzione dal popup dell'estensione se necessario

## ⚙️ Impostazioni

### Pannello di Controllo

L'estensione offre un pannello popup con le seguenti opzioni:

- **Salta Pubblicità** (ON/OFF): Attiva o disattiva il salto automatico delle pubblicità
- **Fullscreen Automatico** (ON/OFF): Avvia automaticamente in fullscreen quando possibile
- **Pulsante Fullscreen**: Apri manualmente il video sul secondo monitor

### Scorciatoie da Tastiera

- `Ctrl+Shift+K`: Apri il video corrente a pieno schermo sul secondo monitor

## 🖥️ Requisiti di Sistema

- Google Chrome (versione 88 o superiore)
- Due monitor collegati al computer (per la funzionalità fullscreen su secondo monitor)
- Funziona su: Windows, macOS, Linux

## 🔧 Sviluppo

### Struttura del Progetto

```
youtube-karaoke-extension/
├── manifest.json          # Configurazione dell'estensione
├── content.js            # Script per saltare pubblicità e gestire video
├── background.js         # Service worker per gestione monitor
├── popup.html            # Interfaccia popup
├── popup.css             # Stili per il popup
├── popup.js              # Logica del popup
├── icons/                # Icone dell'estensione
│   ├── icon16.png
│   ├── icon32.png
│   ├── icon48.png
│   └── icon128.png
└── README.md             # Questo file
```

### Tecnologie Utilizzate

- **Manifest V3**: Ultima versione delle estensioni Chrome
- **Content Scripts**: Per interagire con le pagine YouTube
- **Service Worker**: Per gestione background e monitor multipli
- **Chrome Storage API**: Per salvare le impostazioni
- **Chrome System Display API**: Per gestire monitor multipli

## 🐛 Risoluzione Problemi

### L'estensione non salta le pubblicità

1. Assicurati che l'opzione "Salta Pubblicità" sia attiva nel popup
2. Ricarica la pagina YouTube
3. Verifica che l'estensione sia attiva in `chrome://extensions/`

### Il video non si apre sul secondo monitor

1. Assicurati di avere un secondo monitor collegato e configurato
2. Verifica che Chrome abbia i permessi necessari
3. Prova a usare la scorciatoia `Ctrl+Shift+K` invece del pulsante

### Il popup non si apre

1. Ricarica l'estensione in `chrome://extensions/`
2. Verifica che non ci siano errori nella console dell'estensione

## 📝 Note Importanti

- L'estensione funziona solo su YouTube.com e suoi sottodomini
- Per una migliore esperienza, assicurati che il secondo monitor sia configurato come "Estendi display" nelle impostazioni del sistema
- Le pubblicità vengono saltate automaticamente quando disponibile il pulsante "Salta annuncio"

## 🤝 Contribuire

Contributi, segnalazioni di bug e richieste di funzionalità sono benvenuti!

## 📄 Licenza

Questo progetto è rilasciato sotto licenza GPL v2 o successiva.

## 👥 Autori

Sviluppato per 747 Disco Team

## 🎉 Divertiti con il Karaoke!

Questa estensione è stata creata specificamente per rendere le serate karaoke più semplici e professionali. Buon divertimento! 🎤🎵
