// Karaoke Display - Finestra sul secondo monitor per visualizzare i video
console.log('Karaoke Display: Script caricato');

const videoContainer = document.getElementById('videoContainer');
const loadingMessage = document.getElementById('loadingMessage');

let currentPlayer = null;
let fadeInterval = null;
let originalVolume = 100;

// Ascolta i comandi dal player principale
chrome.runtime.onMessage.addListener((request, sender, sendResponse) => {
  console.log('Karaoke Display: Messaggio ricevuto', request.action);

  if (request.action === 'playKaraokeVideo') {
    playVideo(request.videoId, request.volume || 100);
    sendResponse({ success: true });
  } else if (request.action === 'pauseKaraokeVideo') {
    pauseVideo();
    sendResponse({ success: true });
  } else if (request.action === 'resumeKaraokeVideo') {
    resumeVideo();
    sendResponse({ success: true });
  } else if (request.action === 'setKaraokeVolume') {
    setVolume(request.volume);
    sendResponse({ success: true });
  }

  return true;
});

function playVideo(videoId, volume) {
  console.log('Karaoke Display: Riproduzione video', videoId);
  
  // Rimuovi il messaggio di caricamento
  loadingMessage.style.display = 'none';
  
  // Rimuovi il player precedente se esiste
  if (currentPlayer) {
    currentPlayer.remove();
  }
  
  // Crea nuovo iframe con YouTube Player
  const iframe = document.createElement('iframe');
  iframe.src = `https://www.youtube.com/embed/${videoId}?autoplay=1&controls=0&modestbranding=1&rel=0&showinfo=0&fs=1&loop=0`;
  iframe.allow = 'autoplay; fullscreen';
  iframe.allowFullscreen = true;
  
  videoContainer.appendChild(iframe);
  currentPlayer = iframe;
  
  originalVolume = volume;
  
  // Applica fade-in audio (simulato)
  startFadeIn();
}

function pauseVideo() {
  console.log('Karaoke Display: Pausa video');
  if (currentPlayer && currentPlayer.contentWindow) {
    // Invia comando pausa tramite postMessage API di YouTube
    currentPlayer.contentWindow.postMessage('{"event":"command","func":"pauseVideo","args":""}', '*');
    
    // Applica fade-out audio
    startFadeOut();
  }
}

function resumeVideo() {
  console.log('Karaoke Display: Riprendi video');
  if (currentPlayer && currentPlayer.contentWindow) {
    // Invia comando play tramite postMessage API di YouTube
    currentPlayer.contentWindow.postMessage('{"event":"command","func":"playVideo","args":""}', '*');
    
    // Applica fade-in audio
    startFadeIn();
  }
}

function setVolume(volume) {
  console.log('Karaoke Display: Imposta volume', volume);
  originalVolume = volume;
  
  if (currentPlayer && currentPlayer.contentWindow) {
    // Invia comando volume tramite postMessage API di YouTube
    currentPlayer.contentWindow.postMessage(`{"event":"command","func":"setVolume","args":[${volume}]}`, '*');
  }
}

function startFadeIn() {
  // Cancella fade precedente
  if (fadeInterval) {
    clearInterval(fadeInterval);
  }
  
  let currentVol = 0;
  const steps = 40;
  const increment = originalVolume / steps;
  const delay = 2000 / steps; // 2 secondi totali
  
  if (currentPlayer && currentPlayer.contentWindow) {
    currentPlayer.contentWindow.postMessage(`{"event":"command","func":"setVolume","args":[0]}`, '*');
    
    fadeInterval = setInterval(() => {
      currentVol += increment;
      if (currentVol >= originalVolume) {
        currentVol = originalVolume;
        clearInterval(fadeInterval);
        fadeInterval = null;
      }
      
      if (currentPlayer && currentPlayer.contentWindow) {
        currentPlayer.contentWindow.postMessage(`{"event":"command","func":"setVolume","args":[${Math.round(currentVol)}]}`, '*');
      }
    }, delay);
  }
}

function startFadeOut() {
  // Cancella fade precedente
  if (fadeInterval) {
    clearInterval(fadeInterval);
  }
  
  let currentVol = originalVolume;
  const steps = 40;
  const decrement = originalVolume / steps;
  const delay = 2000 / steps; // 2 secondi totali
  
  if (currentPlayer && currentPlayer.contentWindow) {
    fadeInterval = setInterval(() => {
      currentVol -= decrement;
      if (currentVol <= 0) {
        currentVol = 0;
        clearInterval(fadeInterval);
        fadeInterval = null;
      }
      
      if (currentPlayer && currentPlayer.contentWindow) {
        currentPlayer.contentWindow.postMessage(`{"event":"command","func":"setVolume","args":[${Math.round(currentVol)}]}`, '*');
      }
    }, delay);
  }
}

// Inizializzazione
console.log('Karaoke Display: Pronto a ricevere video');
