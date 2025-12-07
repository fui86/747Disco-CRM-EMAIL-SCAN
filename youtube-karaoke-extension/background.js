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
      // Se c'è solo un display, massimizza la finestra
      await chrome.windows.update(window.id, {
        state: 'fullscreen'
      });
      return;
    }
    
    // Trova il secondo display (display secondario)
    const secondaryDisplay = displays[1];
    
    console.log('YouTube Karaoke: Spostamento sul display secondario:', secondaryDisplay.name);
    
    // Crea una nuova finestra sul secondo display in modalità fullscreen
    const newWindow = await chrome.windows.create({
      url: tab.url,
      type: 'popup',
      state: 'fullscreen',
      left: secondaryDisplay.bounds.left,
      top: secondaryDisplay.bounds.top,
      width: secondaryDisplay.bounds.width,
      height: secondaryDisplay.bounds.height
    });
    
    // Chiudi la scheda originale (opzionale, dipende dalle preferenze)
    // await chrome.tabs.remove(tab.id);
    
    console.log('YouTube Karaoke: Finestra creata sul display secondario');
    
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

// Listener per i comandi da tastiera (shortcuts)
chrome.commands?.onCommand.addListener((command) => {
  if (command === 'open-karaoke-fullscreen') {
    chrome.tabs.query({active: true, currentWindow: true}, (tabs) => {
      if (tabs[0] && tabs[0].url.includes('youtube.com')) {
        chrome.tabs.sendMessage(tabs[0].id, {action: 'openFullscreen'});
      }
    });
  }
});

console.log('YouTube Karaoke Extension: Background script inizializzato');
