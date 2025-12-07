// Background service worker per gestire i display multipli e le finestre

console.log('YouTube Karaoke Extension: Background script loaded');

// Ascolta i messaggi dal content script
chrome.runtime.onMessage.addListener((request, sender, sendResponse) => {
  if (request.action === 'moveToSecondaryDisplay') {
    moveWindowToSecondaryDisplay(sender.tab);
    sendResponse({success: true});
  }
  return true;
});

// Funzione per spostare la finestra sul secondo monitor
async function moveWindowToSecondaryDisplay(tab) {
  try {
    // Ottieni la finestra corrente
    const window = await chrome.windows.get(tab.windowId);
    
    // Ottieni informazioni sui display disponibili
    const displays = await chrome.system.display.getInfo();
    
    console.log('Displays disponibili:', displays.length);
    
    if (displays.length < 2) {
      console.log('YouTube Karaoke: Solo un display disponibile');
      // Se c'è solo un display, non serve spostare la finestra
      return;
    }
    
    // Trova il secondo display (display secondario)
    const secondaryDisplay = displays[1];
    
    console.log('YouTube Karaoke: Spostamento sul display secondario:', secondaryDisplay.name);
    console.log('Secondary display bounds:', secondaryDisplay.bounds);
    
    // Prima riporta la finestra allo stato normale (se è maximized/fullscreen)
    await chrome.windows.update(window.id, {
      state: 'normal'
    });
    
    // Aspetta un momento per assicurarsi che la finestra sia tornata normale
    await new Promise(resolve => setTimeout(resolve, 100));
    
    // Sposta la finestra sul secondo display
    await chrome.windows.update(window.id, {
      left: secondaryDisplay.bounds.left + 50,  // +50 per essere sicuri di essere nel secondo monitor
      top: secondaryDisplay.bounds.top + 50,
      width: secondaryDisplay.bounds.width - 100,
      height: secondaryDisplay.bounds.height - 100,
      state: 'normal'
    });
    
    console.log('YouTube Karaoke: Finestra spostata sul display secondario');
    
  } catch (error) {
    console.error('YouTube Karaoke: Errore nello spostamento sul display secondario:', error);
  }
}

// Listener per l'installazione dell'estensione
chrome.runtime.onInstalled.addListener((details) => {
  if (details.reason === 'install') {
    console.log('YouTube Karaoke Extension: Prima installazione');
    
    // Imposta i valori predefiniti
    chrome.storage.sync.set({
      adSkipperEnabled: true,
      autoFullscreenEnabled: false
    });
    
    // Apri la pagina di benvenuto (opzionale)
    // chrome.tabs.create({ url: 'welcome.html' });
  } else if (details.reason === 'update') {
    console.log('YouTube Karaoke Extension: Aggiornamento');
  }
});

// Helper function to validate YouTube URL
function isYouTubeUrl(url) {
  try {
    const urlObj = new URL(url);
    return urlObj.hostname === 'www.youtube.com' || 
           urlObj.hostname === 'youtube.com' || 
           urlObj.hostname === 'm.youtube.com' ||
           urlObj.hostname.endsWith('.youtube.com');
  } catch (e) {
    return false;
  }
}

// Listener per i comandi da tastiera (shortcuts)
chrome.commands?.onCommand.addListener((command) => {
  if (command === 'open-karaoke-fullscreen') {
    chrome.tabs.query({active: true, currentWindow: true}, (tabs) => {
      if (tabs[0] && isYouTubeUrl(tabs[0].url)) {
        chrome.tabs.sendMessage(tabs[0].id, {action: 'openFullscreen'});
      }
    });
  }
});

console.log('YouTube Karaoke Extension: Background script inizializzato');
