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

// Quando siamo su YouTube, attiva automaticamente il fullscreen
if (window.location.hostname === 'www.youtube.com') {
  console.log('Karaoke Display: Siamo su YouTube, attivo fullscreen automatico');
  
  // Aspetta che il video sia caricato
  const waitForVideo = setInterval(() => {
    const video = document.querySelector('video');
    if (video) {
      clearInterval(waitForVideo);
      console.log('Karaoke Display: Video trovato, attivo fullscreen');
      
      // Aspetta che il video inizi a riprodursi
      setTimeout(() => {
        // Prova a cliccare il pulsante fullscreen
        const fullscreenButton = document.querySelector('.ytp-fullscreen-button');
        if (fullscreenButton) {
          console.log('Karaoke Display: Clic su pulsante fullscreen');
          fullscreenButton.click();
        } else {
          // Se il pulsante non esiste, usa l'API fullscreen del video
          console.log('Karaoke Display: Uso API fullscreen del video');
          if (video.requestFullscreen) {
            video.requestFullscreen();
          } else if (video.webkitRequestFullscreen) {
            video.webkitRequestFullscreen();
          } else if (video.mozRequestFullScreen) {
            video.mozRequestFullScreen();
          } else if (video.msRequestFullscreen) {
            video.msRequestFullscreen();
          }
        }
      }, 2000); // Aspetta 2 secondi per l'autoplay
    }
  }, 500);
  
  // Timeout di sicurezza
  setTimeout(() => clearInterval(waitForVideo), 10000);
  
  // Aggiungi anche listener per F key come fallback
  document.addEventListener('DOMContentLoaded', () => {
    setTimeout(() => {
      // Simula la pressione del tasto F per fullscreen
      const video = document.querySelector('video');
      if (video && !document.fullscreenElement) {
        console.log('Karaoke Display: Tentativo pressione F per fullscreen');
        const event = new KeyboardEvent('keydown', {
          key: 'f',
          code: 'KeyF',
          keyCode: 70,
          which: 70,
          bubbles: true
        });
        document.dispatchEvent(event);
      }
    }, 3000);
  });
}

// Inizializzazione
console.log('Karaoke Display: Pronto a ricevere video');

// Nota: Usando YouTube diretto invece di iframe evita l'errore 153
// I controlli play/pause/volume devono essere gestiti direttamente dall'utente
// nella finestra del secondo monitor o tramite la pagina YouTube originale
