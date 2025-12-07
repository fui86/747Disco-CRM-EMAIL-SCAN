// Content script per saltare automaticamente le pubblicità di YouTube
// e gestire la funzionalità karaoke

console.log('YouTube Karaoke Extension: Content script loaded');

// Configurazione
let adSkipperEnabled = true;
let autoFullscreenEnabled = false;

// Carica le impostazioni dal storage
chrome.storage.sync.get(['adSkipperEnabled', 'autoFullscreenEnabled'], (result) => {
  adSkipperEnabled = result.adSkipperEnabled !== false;
  autoFullscreenEnabled = result.autoFullscreenEnabled || false;
});

// Ascolta i messaggi dal popup o background
chrome.runtime.onMessage.addListener((request, sender, sendResponse) => {
  if (request.action === 'updateSettings') {
    adSkipperEnabled = request.adSkipperEnabled;
    autoFullscreenEnabled = request.autoFullscreenEnabled;
    sendResponse({success: true});
  } else if (request.action === 'openFullscreen') {
    openVideoInFullscreen();
    sendResponse({success: true});
  }
  return true;
});

// Funzione per saltare le pubblicità
function skipAd() {
  if (!adSkipperEnabled) return;

  // Cerca il pulsante "Salta annuncio" / "Skip ad"
  const skipButton = document.querySelector('.ytp-ad-skip-button, .ytp-skip-ad-button, button.ytp-ad-skip-button-modern');
  
  if (skipButton && skipButton.offsetParent !== null) {
    console.log('YouTube Karaoke: Saltando pubblicità...');
    skipButton.click();
    return true;
  }

  // Cerca altri pulsanti di skip
  const skipButtons = document.querySelectorAll('button');
  for (let button of skipButtons) {
    const text = button.textContent.toLowerCase();
    if (text.includes('skip') || text.includes('salta')) {
      console.log('YouTube Karaoke: Saltando pubblicità (alternativo)...');
      button.click();
      return true;
    }
  }

  // Prova a saltare l'annuncio accelerando il video
  const video = document.querySelector('video');
  if (video && video.duration && video.currentTime < video.duration) {
    const adContainer = document.querySelector('.ad-showing, .ad-interrupting');
    if (adContainer) {
      console.log('YouTube Karaoke: Accelerando pubblicità...');
      video.currentTime = video.duration;
      return true;
    }
  }

  return false;
}

// Funzione per aprire il video a pieno schermo
function openVideoInFullscreen() {
  const video = document.querySelector('video');
  
  if (!video) {
    console.error('YouTube Karaoke: Video non trovato');
    return;
  }

  // Richiedi il fullscreen
  if (video.requestFullscreen) {
    video.requestFullscreen();
  } else if (video.webkitRequestFullscreen) {
    video.webkitRequestFullscreen();
  } else if (video.mozRequestFullScreen) {
    video.mozRequestFullScreen();
  } else if (video.msRequestFullscreen) {
    video.msRequestFullscreen();
  }

  // Invia messaggio al background per spostare la finestra sul secondo monitor
  chrome.runtime.sendMessage({
    action: 'moveToSecondaryDisplay'
  });

  console.log('YouTube Karaoke: Video a pieno schermo richiesto');
}

// Observer per monitorare i cambiamenti nel DOM (pubblicità che appaiono)
const observer = new MutationObserver((mutations) => {
  if (adSkipperEnabled) {
    skipAd();
  }
});

// Avvia l'observer quando il DOM è pronto
function startObserver() {
  const target = document.body;
  if (target) {
    observer.observe(target, {
      childList: true,
      subtree: true,
      attributes: true,
      attributeFilter: ['class']
    });
    console.log('YouTube Karaoke: Observer avviato');
  }
}

// Intervallo per controllare periodicamente le pubblicità
setInterval(() => {
  if (adSkipperEnabled) {
    skipAd();
  }
}, 500);

// Avvia l'observer quando il DOM è caricato
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', startObserver);
} else {
  startObserver();
}

// Aggiungi un listener per la shortcut da tastiera (Ctrl+Shift+K per karaoke mode)
document.addEventListener('keydown', (event) => {
  if (event.ctrlKey && event.shiftKey && event.key === 'K') {
    event.preventDefault();
    openVideoInFullscreen();
  }
});

console.log('YouTube Karaoke Extension: Inizializzato completamente');
