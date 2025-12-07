// YouTube Karaoke Player - Controllo completo della playlist e casting
console.log('YouTube Karaoke Player: Script caricato');

// Stato del player
let playlist = [];
let currentIndex = -1;
let isPlaying = false;
let currentVolume = 100;
let karaokeWindowId = null;
let karaokeTabId = null;

// Elementi DOM
const videoUrlInput = document.getElementById('videoUrl');
const addVideoBtn = document.getElementById('addVideoBtn');
const playlistList = document.getElementById('playlistList');
const clearPlaylistBtn = document.getElementById('clearPlaylistBtn');
const currentVideoInfo = document.getElementById('currentVideoInfo');
const prevBtn = document.getElementById('prevBtn');
const playPauseBtn = document.getElementById('playPauseBtn');
const nextBtn = document.getElementById('nextBtn');
const volumeSlider = document.getElementById('volumeSlider');
const volumeValue = document.getElementById('volumeValue');
const castToSecondaryBtn = document.getElementById('castToSecondaryBtn');
const castStatus = document.getElementById('castStatus');
const statusMessage = document.getElementById('statusMessage');

// Carica playlist salvata
chrome.storage.local.get(['karaokePlaylist'], (result) => {
  if (result.karaokePlaylist && Array.isArray(result.karaokePlaylist)) {
    playlist = result.karaokePlaylist;
    renderPlaylist();
  }
});

// Event listeners
addVideoBtn.addEventListener('click', addVideo);
videoUrlInput.addEventListener('keypress', (e) => {
  if (e.key === 'Enter') addVideo();
});
clearPlaylistBtn.addEventListener('click', clearPlaylist);
prevBtn.addEventListener('click', playPrevious);
playPauseBtn.addEventListener('click', togglePlayPause);
nextBtn.addEventListener('click', playNext);
volumeSlider.addEventListener('input', updateVolume);
castToSecondaryBtn.addEventListener('click', toggleCastToSecondary);

// Funzioni
function addVideo() {
  const url = videoUrlInput.value.trim();
  if (!url) {
    showStatus('Inserisci un URL YouTube valido', 'error');
    return;
  }

  // Estrai l'ID del video da vari formati di URL YouTube
  const videoId = extractYouTubeId(url);
  if (!videoId) {
    showStatus('URL YouTube non valido', 'error');
    return;
  }

  const video = {
    id: Date.now(),
    videoId: videoId,
    url: `https://www.youtube.com/watch?v=${videoId}`,
    title: `Video ${videoId}`
  };

  playlist.push(video);
  savePlaylist();
  renderPlaylist();
  videoUrlInput.value = '';
  showStatus(`Video aggiunto alla playlist (${playlist.length} video)`, 'success');
}

function extractYouTubeId(url) {
  // Supporta vari formati di URL YouTube
  const patterns = [
    /(?:youtube\.com\/watch\?v=|youtu\.be\/|youtube\.com\/embed\/)([a-zA-Z0-9_-]{11})/,
    /^([a-zA-Z0-9_-]{11})$/ // Solo l'ID
  ];

  for (const pattern of patterns) {
    const match = url.match(pattern);
    if (match && match[1]) {
      return match[1];
    }
  }
  return null;
}

function removeVideo(id) {
  playlist = playlist.filter(v => v.id !== id);
  if (currentIndex >= playlist.length) {
    currentIndex = playlist.length - 1;
  }
  savePlaylist();
  renderPlaylist();
  showStatus('Video rimosso dalla playlist', 'success');
}

function clearPlaylist() {
  if (confirm('Sei sicuro di voler svuotare la playlist?')) {
    playlist = [];
    currentIndex = -1;
    savePlaylist();
    renderPlaylist();
    updateCurrentVideoInfo();
    showStatus('Playlist svuotata', 'success');
  }
}

function playVideo(index) {
  if (index < 0 || index >= playlist.length) {
    showStatus('Nessun video disponibile', 'error');
    return;
  }

  currentIndex = index;
  isPlaying = true;
  updatePlayPauseButton();
  updateCurrentVideoInfo();
  renderPlaylist();

  // Se c'è una finestra karaoke attiva, invia il comando di riproduzione
  if (karaokeTabId) {
    chrome.tabs.sendMessage(karaokeTabId, {
      action: 'playKaraokeVideo',
      videoId: playlist[currentIndex].videoId,
      volume: currentVolume
    }, (response) => {
      if (chrome.runtime.lastError) {
        console.error('Errore invio messaggio:', chrome.runtime.lastError);
        showStatus('Errore comunicazione con finestra karaoke', 'error');
      } else {
        showStatus(`Riproduzione: ${playlist[currentIndex].title}`, 'success');
      }
    });
  } else {
    showStatus('Apri la finestra karaoke sul secondo monitor per riprodurre', 'error');
  }
}

function playPrevious() {
  if (playlist.length === 0) return;
  const newIndex = currentIndex > 0 ? currentIndex - 1 : playlist.length - 1;
  playVideo(newIndex);
}

function togglePlayPause() {
  if (playlist.length === 0) {
    showStatus('Aggiungi video alla playlist prima di riprodurre', 'error');
    return;
  }

  if (!karaokeTabId) {
    showStatus('Apri la finestra karaoke sul secondo monitor', 'error');
    return;
  }

  if (currentIndex < 0) {
    // Inizia dal primo video
    playVideo(0);
  } else {
    // Toggle play/pause
    isPlaying = !isPlaying;
    updatePlayPauseButton();
    
    chrome.tabs.sendMessage(karaokeTabId, {
      action: isPlaying ? 'resumeKaraokeVideo' : 'pauseKaraokeVideo'
    }, (response) => {
      if (chrome.runtime.lastError) {
        console.error('Errore:', chrome.runtime.lastError);
      }
    });
    
    showStatus(isPlaying ? 'Riproduzione' : 'Pausa', 'success');
  }
}

function playNext() {
  if (playlist.length === 0) return;
  const newIndex = (currentIndex + 1) % playlist.length;
  playVideo(newIndex);
}

function updateVolume() {
  currentVolume = parseInt(volumeSlider.value);
  volumeValue.textContent = `${currentVolume}%`;
  
  if (karaokeTabId) {
    chrome.tabs.sendMessage(karaokeTabId, {
      action: 'setKaraokeVolume',
      volume: currentVolume
    });
  }
}

function toggleCastToSecondary() {
  if (karaokeWindowId) {
    // Chiudi la finestra karaoke
    closeKaraokeWindow();
  } else {
    // Apri la finestra karaoke sul secondo monitor
    openKaraokeWindow();
  }
}

async function openKaraokeWindow() {
  try {
    // Ottieni informazioni sui display
    const displays = await chrome.system.display.getInfo();
    
    if (displays.length < 2) {
      showStatus('Secondo monitor non trovato. Usando monitor primario.', 'error');
      // Apri comunque su monitor primario
    }
    
    const secondaryDisplay = displays.length >= 2 ? displays[1] : displays[0];
    
    // Crea finestra karaoke
    const window = await chrome.windows.create({
      url: chrome.runtime.getURL('karaoke-display.html'),
      type: 'popup',
      state: 'normal',
      left: secondaryDisplay.bounds.left,
      top: secondaryDisplay.bounds.top,
      width: secondaryDisplay.bounds.width,
      height: secondaryDisplay.bounds.height
    });
    
    karaokeWindowId = window.id;
    karaokeTabId = window.tabs[0].id;
    
    castToSecondaryBtn.textContent = '🔲 Chiudi Finestra Karaoke';
    castStatus.textContent = `Finestra karaoke aperta su ${displays.length >= 2 ? 'secondo monitor' : 'monitor primario'}`;
    showStatus('Finestra karaoke aperta', 'success');
    
    // Attendi che la finestra si carichi, poi attiva fullscreen
    setTimeout(async () => {
      try {
        await chrome.windows.update(karaokeWindowId, { state: 'fullscreen' });
        castStatus.textContent += ' (Fullscreen)';
      } catch (error) {
        console.error('Errore attivazione fullscreen:', error);
      }
    }, 1000);
    
    // Ascolta la chiusura della finestra
    chrome.windows.onRemoved.addListener(onKaraokeWindowClosed);
    
  } catch (error) {
    console.error('Errore apertura finestra karaoke:', error);
    showStatus('Errore apertura finestra karaoke', 'error');
  }
}

function onKaraokeWindowClosed(windowId) {
  if (windowId === karaokeWindowId) {
    karaokeWindowId = null;
    karaokeTabId = null;
    castToSecondaryBtn.textContent = '📺 Trasmetti su Secondo Monitor';
    castStatus.textContent = '';
    showStatus('Finestra karaoke chiusa', 'success');
  }
}

function closeKaraokeWindow() {
  if (karaokeWindowId) {
    chrome.windows.remove(karaokeWindowId, () => {
      karaokeWindowId = null;
      karaokeTabId = null;
      castToSecondaryBtn.textContent = '📺 Trasmetti su Secondo Monitor';
      castStatus.textContent = '';
      showStatus('Finestra karaoke chiusa', 'success');
    });
  }
}

function updatePlayPauseButton() {
  playPauseBtn.textContent = isPlaying ? '⏸️ Pausa' : '▶️ Play';
}

function updateCurrentVideoInfo() {
  if (currentIndex >= 0 && currentIndex < playlist.length) {
    const video = playlist[currentIndex];
    currentVideoInfo.textContent = `${video.title} (${currentIndex + 1}/${playlist.length})`;
  } else {
    currentVideoInfo.textContent = 'Nessun video';
  }
}

function renderPlaylist() {
  if (playlist.length === 0) {
    playlistList.innerHTML = '<p style="text-align: center; color: #999; padding: 20px;">Playlist vuota</p>';
    return;
  }

  playlistList.innerHTML = playlist.map((video, index) => `
    <div class="playlist-item ${index === currentIndex ? 'active' : ''}" data-id="${video.id}">
      <div class="playlist-item-info">
        <strong>${index + 1}.</strong> ${video.title}
        <br><small style="color: #999;">${video.videoId}</small>
      </div>
      <div class="playlist-item-actions">
        <button onclick="window.playVideoFromList(${index})" style="background: #667eea; color: white;">▶️</button>
        <button onclick="window.removeVideoFromList(${video.id})" style="background: #dc3545; color: white;">🗑️</button>
      </div>
    </div>
  `).join('');
}

function savePlaylist() {
  chrome.storage.local.set({ karaokePlaylist: playlist });
}

function showStatus(message, type = '') {
  statusMessage.textContent = message;
  statusMessage.parentElement.className = 'status-bar' + (type ? ` ${type}` : '');
  
  setTimeout(() => {
    statusMessage.textContent = 'Pronto';
    statusMessage.parentElement.className = 'status-bar';
  }, 3000);
}

// Esporta funzioni per onclick HTML
window.playVideoFromList = playVideo;
window.removeVideoFromList = removeVideo;

// Inizializzazione
updatePlayPauseButton();
updateCurrentVideoInfo();
renderPlaylist();
