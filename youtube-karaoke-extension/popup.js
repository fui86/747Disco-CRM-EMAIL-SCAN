// Script per il popup dell'estensione

document.addEventListener('DOMContentLoaded', () => {
  const adSkipperToggle = document.getElementById('adSkipperToggle');
  const autoFullscreenToggle = document.getElementById('autoFullscreenToggle');
  const playerBtn = document.getElementById('playerBtn');
  const playlistBtn = document.getElementById('playlistBtn');
  const status = document.getElementById('status');

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

  // Carica le impostazioni salvate
  chrome.storage.sync.get(['adSkipperEnabled', 'autoFullscreenEnabled'], (result) => {
    adSkipperToggle.checked = result.adSkipperEnabled !== false;
    autoFullscreenToggle.checked = result.autoFullscreenEnabled || false;
  });

  // Salva le impostazioni quando cambiano
  adSkipperToggle.addEventListener('change', () => {
    const enabled = adSkipperToggle.checked;
    chrome.storage.sync.set({ adSkipperEnabled: enabled }, () => {
      showStatus(`Salta pubblicità ${enabled ? 'attivato' : 'disattivato'}`, 'success');
      updateContentScript();
    });
  });

  autoFullscreenToggle.addEventListener('change', () => {
    const enabled = autoFullscreenToggle.checked;
    chrome.storage.sync.set({ autoFullscreenEnabled: enabled }, () => {
      showStatus(`Fullscreen automatico ${enabled ? 'attivato' : 'disattivato'}`, 'success');
      updateContentScript();
    });
  });

  // Pulsante per aprire il player karaoke
  playerBtn.addEventListener('click', () => {
    chrome.tabs.create({ url: chrome.runtime.getURL('player.html') });
    window.close();
  });

  // Pulsante per aprire la playlist
  playlistBtn.addEventListener('click', () => {
    window.location.href = 'playlist.html';
  });



  // Funzione per aggiornare le impostazioni nel content script
  function updateContentScript() {
    chrome.tabs.query({ active: true, currentWindow: true }, (tabs) => {
      if (tabs[0]) {
        chrome.tabs.sendMessage(tabs[0].id, {
          action: 'updateSettings',
          adSkipperEnabled: adSkipperToggle.checked,
          autoFullscreenEnabled: autoFullscreenToggle.checked
        }, (response) => {
          if (chrome.runtime.lastError) {
            console.log('Tab non pronta per ricevere messaggi:', chrome.runtime.lastError.message);
          }
        });
      }
    });
  }

  // Funzione per mostrare i messaggi di stato
  function showStatus(message, type = 'success') {
    status.textContent = message;
    status.className = `status show ${type}`;
    
    setTimeout(() => {
      status.classList.remove('show');
    }, 3000);
  }
});
