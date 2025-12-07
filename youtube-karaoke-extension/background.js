// Background service worker per gestire i display multipli e le finestre

console.log('YouTube Karaoke Extension: Background script loaded');

// Ascolta i messaggi dal content script
chrome.runtime.onMessage.addListener((request, sender, sendResponse) => {
  if (request.action === 'moveToSecondaryDisplay') {
    moveWindowToSecondaryDisplay(sender.tab).then(result => {
      sendResponse(result);
    });
    return true; // Mantieni il canale aperto per la risposta asincrona
  } else if (request.action === 'closeKaraokeWindow') {
    closeKaraokeWindow(sender.tab.id).then(result => {
      sendResponse(result);
    });
    return true;
  } else if (request.action === 'checkKaraokeWindow') {
    const hasWindow = karaokeWindows.has(sender.tab.id);
    sendResponse({ hasKaraokeWindow: hasWindow });
  }
  return true;
});

// Mappa per tenere traccia delle finestre karaoke create
const karaokeWindows = new Map();

// Funzione per creare una nuova finestra sul secondo monitor con il video
async function moveWindowToSecondaryDisplay(tab) {
  try {
    // Ottieni informazioni sui display disponibili
    const displays = await chrome.system.display.getInfo();
    
    console.log('Displays disponibili:', displays.length);
    
    if (displays.length < 2) {
      console.log('YouTube Karaoke: Solo un display disponibile, uso fullscreen normale');
      // Se c'è solo un display, ritorna e il content script gestirà il fullscreen normale
      return { secondaryDisplayAvailable: false };
    }
    
    // Trova il secondo display (display secondario)
    const secondaryDisplay = displays[1];
    
    console.log('YouTube Karaoke: Creazione finestra sul display secondario:', secondaryDisplay.name);
    console.log('Secondary display bounds:', secondaryDisplay.bounds);
    
    // Se esiste già una finestra karaoke per questo tab, chiudila
    if (karaokeWindows.has(tab.id)) {
      const existingWindowId = karaokeWindows.get(tab.id);
      try {
        await chrome.windows.remove(existingWindowId);
      } catch (e) {
        console.log('Finestra karaoke precedente già chiusa');
      }
      karaokeWindows.delete(tab.id);
    }
    
    // Crea una nuova finestra sul secondo monitor con l'URL del video
    // NON crearla in fullscreen subito, altrimenti ignora left/top
    const newWindow = await chrome.windows.create({
      url: tab.url,
      type: 'popup',  // Tipo popup per non mostrare barra degli indirizzi
      state: 'normal',  // Prima crea come normale, poi fullscreen
      left: secondaryDisplay.bounds.left,
      top: secondaryDisplay.bounds.top,
      width: secondaryDisplay.bounds.width,
      height: secondaryDisplay.bounds.height
    });
    
    // Salva l'ID della finestra creata
    karaokeWindows.set(tab.id, newWindow.id);
    
    // Listener per rimuovere dalla mappa quando la finestra viene chiusa
    chrome.windows.onRemoved.addListener((windowId) => {
      for (const [tabId, karaokWindowId] of karaokeWindows.entries()) {
        if (karaokWindowId === windowId) {
          karaokeWindows.delete(tabId);
          console.log('YouTube Karaoke: Finestra karaoke chiusa');
          break;
        }
      }
    });
    
    console.log('YouTube Karaoke: Nuova finestra creata sul display secondario con ID:', newWindow.id);
    
    return { 
      secondaryDisplayAvailable: true, 
      windowId: newWindow.id,
      tabId: newWindow.tabs[0].id 
    };
    
  } catch (error) {
    console.error('YouTube Karaoke: Errore nella creazione della finestra sul display secondario:', error);
    return { secondaryDisplayAvailable: false, error: error.message };
  }
}

// Funzione per chiudere la finestra karaoke
async function closeKaraokeWindow(tabId) {
  if (karaokeWindows.has(tabId)) {
    const windowId = karaokeWindows.get(tabId);
    try {
      await chrome.windows.remove(windowId);
      karaokeWindows.delete(tabId);
      console.log('YouTube Karaoke: Finestra karaoke chiusa manualmente');
      return { success: true };
    } catch (error) {
      console.error('Errore nella chiusura della finestra karaoke:', error);
      karaokeWindows.delete(tabId);
      return { success: false, error: error.message };
    }
  }
  return { success: true, message: 'Nessuna finestra karaoke attiva' };
}

// Funzione per mettere la finestra in fullscreen dopo che è stata posizionata
async function setWindowFullscreen(windowId) {
  try {
    await chrome.windows.update(windowId, {
      state: 'fullscreen'
    });
    console.log('YouTube Karaoke: Finestra impostata a fullscreen');
    return { success: true };
  } catch (error) {
    console.error('Errore nell\'impostare fullscreen:', error);
    return { success: false, error: error.message };
  }
}

// Listener per i messaggi che richiedono di mettere la finestra in fullscreen
chrome.runtime.onMessage.addListener((request, sender, sendResponse) => {
  if (request.action === 'setWindowFullscreen' && request.windowId) {
    setWindowFullscreen(request.windowId).then(result => {
      sendResponse(result);
    });
    return true;
  }
});

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
