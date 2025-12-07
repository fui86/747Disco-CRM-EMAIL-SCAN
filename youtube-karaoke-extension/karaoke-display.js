// Karaoke Display - Finestra sul secondo monitor per visualizzare i video
console.log('Karaoke Display: Script caricato');

const videoContainer = document.getElementById('videoContainer');
const loadingMessage = document.getElementById('loadingMessage');

let currentVideoId = null;
let isPlaying = false;

// Ascolta i comandi dal player principale
chrome.runtime.onMessage.addListener((request, sender, sendResponse) => {
  console.log('Karaoke Display: Messaggio ricevuto', request.action);

  if (request.action === 'playKaraokeVideo') {
    playVideo(request.videoId);
    sendResponse({ success: true });
  } else if (request.action === 'pauseKaraokeVideo') {
    // Non possiamo controllare pause/play su YouTube diretto
    sendResponse({ success: false, message: 'Controllo play/pause non disponibile su YouTube diretto' });
  } else if (request.action === 'resumeKaraokeVideo') {
    sendResponse({ success: false, message: 'Controllo play/pause non disponibile su YouTube diretto' });
  } else if (request.action === 'setKaraokeVolume') {
    sendResponse({ success: false, message: 'Controllo volume non disponibile su YouTube diretto' });
  }

  return true;
});

function playVideo(videoId) {
  console.log('Karaoke Display: Riproduzione video', videoId);
  
  currentVideoId = videoId;
  
  // Nascondi messaggio di caricamento
  loadingMessage.style.display = 'none';
  
  // Reindirizza a YouTube per evitare Error 153
  // Questo apre il video direttamente su YouTube
  window.location.href = `https://www.youtube.com/watch?v=${videoId}&autoplay=1`;
  
  isPlaying = true;
}

// Inizializzazione
console.log('Karaoke Display: Pronto a ricevere video');

// Nota: Usando YouTube diretto invece di iframe evita l'errore 153
// I controlli play/pause/volume devono essere gestiti direttamente dall'utente
// nella finestra del secondo monitor o tramite la pagina YouTube originale
