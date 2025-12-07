// Script per il popup dell'estensione

document.addEventListener('DOMContentLoaded', () => {
  const adSkipperToggle = document.getElementById('adSkipperToggle');
  const autoFullscreenToggle = document.getElementById('autoFullscreenToggle');
  const fullscreenBtn = document.getElementById('fullscreenBtn');
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

  // Pulsante per toggle fullscreen
  fullscreenBtn.addEventListener('click', async () => {
    try {
      // Ottieni la scheda attiva
      const [tab] = await chrome.tabs.query({ active: true, currentWindow: true });
      
      if (!tab) {
        showStatus('Nessuna scheda attiva trovata', 'error');
        return;
      }

      // Verifica che sia una pagina YouTube
      if (!tab.url || !isYouTubeUrl(tab.url)) {
        showStatus('Apri prima un video di YouTube!', 'warning');
        return;
      }

      // Prima ottieni lo stato corrente del fullscreen
      chrome.tabs.sendMessage(tab.id, { action: 'getFullscreenState' }, (stateResponse) => {
        if (chrome.runtime.lastError) {
          showStatus('Errore: ricarica la pagina YouTube', 'error');
          console.error(chrome.runtime.lastError);
          return;
        }

        // Ora invia il comando di toggle
        chrome.tabs.sendMessage(tab.id, { action: 'toggleFullscreen' }, (response) => {
          if (chrome.runtime.lastError) {
            showStatus('Errore: ricarica la pagina YouTube', 'error');
            console.error(chrome.runtime.lastError);
          } else {
            const isNowFullscreen = response.isFullscreen;
            if (isNowFullscreen) {
              showStatus('Fullscreen attivato!', 'success');
              fullscreenBtn.textContent = '🔲 Disattiva Fullscreen';
            } else {
              showStatus('Fullscreen disattivato', 'success');
              fullscreenBtn.textContent = '📺 Attiva Fullscreen su Secondo Monitor';
            }
            // Non chiudere il popup per permettere toggle rapido
          }
        });
      });
    } catch (error) {
      showStatus('Errore: ' + error.message, 'error');
      console.error(error);
    }
  });

  // Aggiorna il testo del pulsante all'apertura del popup
  chrome.tabs.query({ active: true, currentWindow: true }, (tabs) => {
    if (tabs[0] && isYouTubeUrl(tabs[0].url)) {
      chrome.tabs.sendMessage(tabs[0].id, { action: 'getFullscreenState' }, (response) => {
        if (!chrome.runtime.lastError && response) {
          if (response.isFullscreen) {
            fullscreenBtn.textContent = '🔲 Disattiva Fullscreen';
          } else {
            fullscreenBtn.textContent = '📺 Attiva Fullscreen su Secondo Monitor';
          }
        }
      });
    }
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
