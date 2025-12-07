// Playlist Manager for YouTube Karaoke Extension

let playlist = [];
let currentIndex = -1;

// DOM Elements
const videoUrlInput = document.getElementById('videoUrlInput');
const addVideoBtn = document.getElementById('addVideoBtn');
const playlistItems = document.getElementById('playlistItems');
const playlistCount = document.getElementById('playlistCount');
const playerInfo = document.getElementById('playerInfo');
const prevBtn = document.getElementById('prevBtn');
const playBtn = document.getElementById('playBtn');
const nextBtn = document.getElementById('nextBtn');
const clearPlaylistBtn = document.getElementById('clearPlaylistBtn');
const backToMainBtn = document.getElementById('backToMainBtn');
const status = document.getElementById('status');

// Helper function to validate YouTube URL
function isYouTubeUrl(url) {
  try {
    const urlObj = new URL(url);
    return urlObj.hostname === 'www.youtube.com' || 
           urlObj.hostname === 'youtube.com' || 
           urlObj.hostname === 'm.youtube.com' ||
           urlObj.hostname === 'youtu.be' ||
           urlObj.hostname.endsWith('.youtube.com');
  } catch (e) {
    return false;
  }
}

// Helper function to extract video ID from YouTube URL
function extractVideoId(url) {
  try {
    const urlObj = new URL(url);
    if (urlObj.hostname === 'youtu.be') {
      return urlObj.pathname.slice(1);
    }
    return urlObj.searchParams.get('v');
  } catch (e) {
    return null;
  }
}

// Show status message
function showStatus(message, type = 'success') {
  status.textContent = message;
  status.className = `status show ${type}`;
  
  setTimeout(() => {
    status.classList.remove('show');
  }, 3000);
}

// Load playlist from storage
function loadPlaylist() {
  chrome.storage.sync.get(['karaoke_playlist', 'karaoke_current_index'], (result) => {
    playlist = result.karaoke_playlist || [];
    currentIndex = result.karaoke_current_index ?? -1;
    renderPlaylist();
    updatePlayerInfo();
    updateControls();
  });
}

// Save playlist to storage
function savePlaylist() {
  chrome.storage.sync.set({
    karaoke_playlist: playlist,
    karaoke_current_index: currentIndex
  });
}

// Add video to playlist
function addVideo() {
  const url = videoUrlInput.value.trim();
  
  if (!url) {
    showStatus('Inserisci un URL YouTube', 'warning');
    return;
  }
  
  if (!isYouTubeUrl(url)) {
    showStatus('URL non valido. Usa un link YouTube', 'error');
    return;
  }
  
  const videoId = extractVideoId(url);
  if (!videoId) {
    showStatus('Impossibile estrarre ID video', 'error');
    return;
  }
  
  // Add to playlist
  playlist.push({
    url: url,
    videoId: videoId,
    title: `Video ${playlist.length + 1}`,
    addedAt: Date.now()
  });
  
  savePlaylist();
  renderPlaylist();
  updateControls();
  
  videoUrlInput.value = '';
  showStatus('Video aggiunto alla playlist!', 'success');
}

// Remove video from playlist
function removeVideo(index) {
  playlist.splice(index, 1);
  
  // Adjust current index if necessary
  if (currentIndex >= index && currentIndex > 0) {
    currentIndex--;
  } else if (currentIndex >= playlist.length) {
    currentIndex = playlist.length - 1;
  }
  
  savePlaylist();
  renderPlaylist();
  updatePlayerInfo();
  updateControls();
  
  showStatus('Video rimosso dalla playlist', 'success');
}

// Play video at index
async function playVideo(index) {
  if (index < 0 || index >= playlist.length) {
    showStatus('Video non trovato', 'error');
    return;
  }
  
  currentIndex = index;
  savePlaylist();
  
  const video = playlist[index];
  
  try {
    // Open video in new tab
    const tab = await chrome.tabs.create({
      url: video.url,
      active: true
    });
    
    // Wait a bit for the tab to load
    setTimeout(async () => {
      // Send message to activate fullscreen
      chrome.tabs.sendMessage(tab.id, { action: 'toggleFullscreen' }, (response) => {
        if (chrome.runtime.lastError) {
          console.log('Tab not ready yet:', chrome.runtime.lastError.message);
        }
      });
    }, 2000);
    
    updatePlayerInfo();
    updateControls();
    renderPlaylist();
    
    showStatus('Riproduzione avviata!', 'success');
  } catch (error) {
    showStatus('Errore durante l\'apertura del video', 'error');
    console.error(error);
  }
}

// Play previous video
function playPrevious() {
  if (currentIndex > 0) {
    playVideo(currentIndex - 1);
  }
}

// Play current/next video
function playNext() {
  if (currentIndex < playlist.length - 1) {
    playVideo(currentIndex + 1);
  } else if (playlist.length > 0) {
    playVideo(0); // Restart from beginning
  }
}

// Clear entire playlist
function clearPlaylist() {
  if (confirm('Sei sicuro di voler svuotare la playlist?')) {
    playlist = [];
    currentIndex = -1;
    savePlaylist();
    renderPlaylist();
    updatePlayerInfo();
    updateControls();
    showStatus('Playlist svuotata', 'success');
  }
}

// Update player info display
function updatePlayerInfo() {
  if (currentIndex >= 0 && currentIndex < playlist.length) {
    const video = playlist[currentIndex];
    playerInfo.innerHTML = `
      <p class="video-title">🎵 ${video.title}</p>
      <p class="video-url">${video.url}</p>
    `;
  } else {
    playerInfo.innerHTML = '<p class="no-video">Nessun video in riproduzione</p>';
  }
}

// Update control buttons state
function updateControls() {
  const hasVideos = playlist.length > 0;
  const hasCurrent = currentIndex >= 0 && currentIndex < playlist.length;
  
  prevBtn.disabled = !hasCurrent || currentIndex === 0;
  playBtn.disabled = !hasVideos;
  nextBtn.disabled = !hasVideos;
  
  // Update play button text
  if (hasCurrent && currentIndex < playlist.length - 1) {
    nextBtn.textContent = '⏭️ Successivo';
  } else if (hasVideos) {
    nextBtn.textContent = '⏭️ Riprendi';
  }
}

// Render playlist items
function renderPlaylist() {
  playlistCount.textContent = playlist.length;
  
  if (playlist.length === 0) {
    playlistItems.innerHTML = '<p class="empty-playlist">La playlist è vuota. Aggiungi video sopra!</p>';
    return;
  }
  
  playlistItems.innerHTML = playlist.map((video, index) => `
    <div class="playlist-item ${index === currentIndex ? 'active' : ''}" data-index="${index}">
      <div class="playlist-item-index">${index + 1}.</div>
      <div class="playlist-item-info">
        <p class="playlist-item-title">${video.title}</p>
        <p class="playlist-item-url">${video.url}</p>
      </div>
      <div class="playlist-item-actions">
        <button class="playlist-item-btn btn-play-item" data-index="${index}">▶️</button>
        <button class="playlist-item-btn btn-remove-item" data-index="${index}">🗑️</button>
      </div>
    </div>
  `).join('');
  
  // Add event listeners to playlist items
  document.querySelectorAll('.btn-play-item').forEach(btn => {
    btn.addEventListener('click', (e) => {
      const index = parseInt(e.target.dataset.index);
      playVideo(index);
    });
  });
  
  document.querySelectorAll('.btn-remove-item').forEach(btn => {
    btn.addEventListener('click', (e) => {
      const index = parseInt(e.target.dataset.index);
      removeVideo(index);
    });
  });
}

// Event Listeners
addVideoBtn.addEventListener('click', addVideo);

videoUrlInput.addEventListener('keypress', (e) => {
  if (e.key === 'Enter') {
    addVideo();
  }
});

prevBtn.addEventListener('click', playPrevious);
nextBtn.addEventListener('click', playNext);

playBtn.addEventListener('click', () => {
  if (currentIndex >= 0 && currentIndex < playlist.length) {
    playVideo(currentIndex);
  } else if (playlist.length > 0) {
    playVideo(0);
  }
});

clearPlaylistBtn.addEventListener('click', clearPlaylist);

backToMainBtn.addEventListener('click', () => {
  window.location.href = 'popup.html';
});

// Initialize
loadPlaylist();
