// Content script per saltare automaticamente le pubblicità di YouTube
// e gestire la funzionalità karaoke

console.log('YouTube Karaoke Extension: Content script loaded');

// Configurazione
let adSkipperEnabled = true;
let autoFullscreenEnabled = false;
let isFullscreenActive = false;

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
  } else if (request.action === 'toggleFullscreen') {
    toggleFullscreen();
    sendResponse({success: true, isFullscreen: isFullscreenActive});
  } else if (request.action === 'getFullscreenState') {
    sendResponse({isFullscreen: isFullscreenActive});
  }
  return true;
});

// Funzione per saltare le pubblicità
function skipAd() {
  if (!adSkipperEnabled) return;

  // Cerca il pulsante "Salta annuncio" / "Skip ad" usando selettori specifici
  const skipButton = document.querySelector('.ytp-ad-skip-button, .ytp-skip-ad-button, button.ytp-ad-skip-button-modern, .ytp-ad-skip-button-container button');
  
  if (skipButton && skipButton.offsetParent !== null) {
    console.log('YouTube Karaoke: Saltando pubblicità...');
    skipButton.click();
    return true;
  }

  // Cerca pulsanti con attributi specifici per gli annunci
  const adButtons = document.querySelectorAll('button[class*="skip"], button[class*="ytp-ad"]');
  for (let button of adButtons) {
    if (button.offsetParent !== null) {
      const text = button.textContent.toLowerCase();
      if (text.includes('skip') || text.includes('salta')) {
        console.log('YouTube Karaoke: Saltando pubblicità (alternativo)...');
        button.click();
        return true;
      }
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

// Funzione per toggle fullscreen on/off
function toggleFullscreen() {
  const video = document.querySelector('video');
  
  if (!video) {
    console.error('YouTube Karaoke: Video non trovato');
    return;
  }

  // Se è già in fullscreen, esci
  if (isFullscreenActive || document.fullscreenElement || document.webkitFullscreenElement || 
      document.mozFullScreenElement || document.msFullscreenElement) {
    exitFullscreen();
  } else {
    // Altrimenti entra in fullscreen
    openVideoInFullscreen();
  }
}

// Funzione per uscire dal fullscreen
function exitFullscreen() {
  if (document.exitFullscreen) {
    document.exitFullscreen();
  } else if (document.webkitExitFullscreen) {
    document.webkitExitFullscreen();
  } else if (document.mozCancelFullScreen) {
    document.mozCancelFullScreen();
  } else if (document.msExitFullscreen) {
    document.msExitFullscreen();
  }
  
  isFullscreenActive = false;
  console.log('YouTube Karaoke: Uscita da fullscreen');
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

  isFullscreenActive = true;

  // Invia messaggio al background per spostare la finestra sul secondo monitor
  chrome.runtime.sendMessage({
    action: 'moveToSecondaryDisplay'
  });

  console.log('YouTube Karaoke: Video a pieno schermo richiesto');
}

// Listener per cambiamenti dello stato fullscreen
document.addEventListener('fullscreenchange', () => {
  isFullscreenActive = !!document.fullscreenElement;
});
document.addEventListener('webkitfullscreenchange', () => {
  isFullscreenActive = !!document.webkitFullscreenElement;
});
document.addEventListener('mozfullscreenchange', () => {
  isFullscreenActive = !!document.mozFullScreenElement;
});
document.addEventListener('msfullscreenchange', () => {
  isFullscreenActive = !!document.msFullscreenElement;
});

// Variabili per gestire il fade audio
let fadeInterval = null;
let originalVolume = 1.0;

// Funzione per applicare fade-IN all'audio (quando si preme play)
function applyAudioFadeIn(video) {
  if (!video) return;
  
  // Cancella eventuale fade in corso
  if (fadeInterval) {
    clearInterval(fadeInterval);
    fadeInterval = null;
  }
  
  // Durata fade-in: 2 secondi
  const fadeDuration = 2000; // millisecondi
  const steps = 40; // numero di step per un fade smooth
  const stepDuration = fadeDuration / steps;
  const volumeIncrement = originalVolume / steps;
  
  let currentStep = 0;
  
  // Inizia da volume 0
  video.volume = 0;
  
  fadeInterval = setInterval(() => {
    currentStep++;
    
    if (currentStep >= steps) {
      video.volume = originalVolume;
      clearInterval(fadeInterval);
      fadeInterval = null;
      console.log('YouTube Karaoke: Fade-in completato');
    } else {
      video.volume = Math.min(originalVolume, volumeIncrement * currentStep);
    }
  }, stepDuration);
  
  console.log('YouTube Karaoke: Fade-in audio avviato (2 secondi)');
}

// Funzione per applicare fade-OUT all'audio (quando si preme pause/stop)
function applyAudioFadeOut(video) {
  if (!video) return;
  
  // Cancella eventuale fade in corso
  if (fadeInterval) {
    clearInterval(fadeInterval);
    fadeInterval = null;
  }
  
  // Salva il volume corrente come punto di partenza
  const startVolume = video.volume;
  
  // Durata fade-out: 2 secondi
  const fadeDuration = 2000; // millisecondi
  const steps = 40; // numero di step per un fade smooth
  const stepDuration = fadeDuration / steps;
  const volumeDecrement = startVolume / steps;
  
  let currentStep = 0;
  
  fadeInterval = setInterval(() => {
    currentStep++;
    
    if (currentStep >= steps) {
      video.volume = 0;
      clearInterval(fadeInterval);
      fadeInterval = null;
      console.log('YouTube Karaoke: Fade-out completato');
    } else {
      video.volume = Math.max(0, startVolume - (volumeDecrement * currentStep));
    }
  }, stepDuration);
  
  console.log('YouTube Karaoke: Fade-out audio avviato (2 secondi)');
}

// Osserva i cambiamenti dello stato del video (play/pause)
function monitorVideoState() {
  const video = document.querySelector('video');
  
  if (video) {
    // Salva il volume originale all'inizio
    if (video.volume > 0) {
      originalVolume = video.volume;
    }
    
    // Listener per quando il video viene messo in pausa
    video.addEventListener('pause', () => {
      console.log('YouTube Karaoke: Video in pausa, avvio fade-out');
      applyAudioFadeOut(video);
    });
    
    // Listener per quando viene fermato (ended)
    video.addEventListener('ended', () => {
      console.log('YouTube Karaoke: Video terminato, avvio fade-out');
      applyAudioFadeOut(video);
    });
    
    // Listener per quando il video riprende/inizia
    video.addEventListener('play', () => {
      console.log('YouTube Karaoke: Video in play, avvio fade-in');
      applyAudioFadeIn(video);
    });
    
    // Listener per quando il video è effettivamente in riproduzione
    video.addEventListener('playing', () => {
      // Solo se non c'è già un fade in corso
      if (!fadeInterval) {
        console.log('YouTube Karaoke: Video playing, avvio fade-in');
        applyAudioFadeIn(video);
      }
    });
    
    console.log('YouTube Karaoke: Monitoraggio stato video attivato');
  }
}

// Avvia il monitoraggio quando troviamo il video
function initVideoMonitoring() {
  const video = document.querySelector('video');
  if (video) {
    monitorVideoState();
  } else {
    // Riprova dopo un breve delay se il video non è ancora caricato
    setTimeout(initVideoMonitoring, 1000);
  }
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

// Intervallo per controllare periodicamente le pubblicità (backup per casi che l'observer potrebbe non catturare)
setInterval(() => {
  if (adSkipperEnabled) {
    skipAd();
  }
}, 2000); // Ridotto a 2 secondi per ridurre l'impatto sulle performance

// Avvia l'observer quando il DOM è caricato
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', () => {
    startObserver();
    initVideoMonitoring();
  });
} else {
  startObserver();
  initVideoMonitoring();
}

console.log('YouTube Karaoke Extension: Inizializzato completamente');
