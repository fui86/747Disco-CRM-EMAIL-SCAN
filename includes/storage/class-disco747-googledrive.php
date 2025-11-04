<?php
/**
 * Google Drive Handler - 747 Disco CRM
 * VERSIONE CORRETTA v12.0.0
 * 
 * FIX APPLICATI:
 * 1. ✅ Usa data_evento per determinare cartella (Anno/Mese italiano)
 * 2. ✅ Metodo upload_to_googledrive() corretto
 * 3. ✅ Metodo get_or_create_folder() per gestione gerarchia cartelle
 * 
 * @package    Disco747_CRM
 * @subpackage Storage
 * @version    12.0.0
 */

namespace Disco747_CRM\Storage;

if (!defined('ABSPATH')) {
    exit('Accesso diretto non consentito');
}

class Disco747_GoogleDrive {
    
    private $credentials_cache = null;
    private $access_token_cache = null;
    private $debug_mode = true;
    
    /**
     * Mesi italiani per naming cartelle
     */
    private $mesi_italiani = array(
        '01' => 'Gennaio', '02' => 'Febbraio', '03' => 'Marzo',
        '04' => 'Aprile', '05' => 'Maggio', '06' => 'Giugno',
        '07' => 'Luglio', '08' => 'Agosto', '09' => 'Settembre',
        '10' => 'Ottobre', '11' => 'Novembre', '12' => 'Dicembre'
    );
    
    public function __construct() {
        $this->log('[GoogleDrive] Handler v12.0.0 inizializzato');
        
        // ✅ Sincronizza credenziali all'avvio (in caso ci siano credenziali salvate in chiavi diverse)
        add_action('init', array($this, 'sync_credentials'), 5);
    }
    
    /**
     * ========================================================================
     * METODO PRINCIPALE: Upload file su Google Drive
     * ✅ FIX: Usa data_evento per determinare la cartella corretta
     * ========================================================================
     */
    public function upload_to_googledrive($local_file_path, $remote_filename, $data_evento) {
        try {
            $this->log('========== INIZIO UPLOAD GOOGLE DRIVE ==========');
            $this->log('File locale: ' . $local_file_path);
            $this->log('Nome remoto: ' . $remote_filename);
            $this->log('📅 Data evento: ' . $data_evento);
            
            if (!file_exists($local_file_path)) {
                throw new \Exception('File non trovato: ' . $local_file_path);
            }
            
            // ✅ FIX CRITICO: Usa data_evento per calcolare anno e mese
            $date_parts = explode('-', $data_evento);
            $year = $date_parts[0] ?? date('Y');
            $month_num = $date_parts[1] ?? date('m');
            
            $month_name = $this->mesi_italiani[$month_num] ?? 'Sconosciuto';
            
            $this->log("📅 Anno: {$year}, Mese: {$month_name} (estratti da data_evento)");
            
            // ✅ Struttura cartelle: /747-Preventivi/ANNO/MESE/
            $base_folder = $this->get_or_create_folder('747-Preventivi');
            $year_folder = $this->get_or_create_folder($year, $base_folder);
            $month_folder = $this->get_or_create_folder($month_name, $year_folder);
            
            $this->log('📁 Struttura cartelle creata correttamente');
            $this->log('   Base: 747-Preventivi');
            $this->log('   Anno: ' . $year);
            $this->log('   Mese: ' . $month_name);
            
            // Upload file
            $token = $this->get_valid_access_token();
            
            $boundary = wp_generate_uuid4();
            $file_content = file_get_contents($local_file_path);
            $mime_type = wp_check_filetype($local_file_path)['type'] ?: 'application/octet-stream';
            
            $metadata = json_encode(array(
                'name' => $remote_filename,
                'parents' => array($month_folder)
            ));
            
            $body = "--{$boundary}\r\n" .
                    "Content-Type: application/json; charset=UTF-8\r\n\r\n" .
                    "{$metadata}\r\n" .
                    "--{$boundary}\r\n" .
                    "Content-Type: {$mime_type}\r\n\r\n" .
                    "{$file_content}\r\n" .
                    "--{$boundary}--";
            
            $response = wp_remote_post('https://www.googleapis.com/upload/drive/v3/files?uploadType=multipart', array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $token,
                    'Content-Type' => "multipart/related; boundary=\"{$boundary}\""
                ),
                'body' => $body,
                'timeout' => 60
            ));
            
            if (is_wp_error($response)) {
                throw new \Exception('Errore upload: ' . $response->get_error_message());
            }
            
            $http_code = wp_remote_retrieve_response_code($response);
            $upload_data = json_decode(wp_remote_retrieve_body($response), true);
            
            if ($http_code !== 200) {
                $error_msg = $upload_data['error']['message'] ?? 'Errore sconosciuto';
                throw new \Exception("Upload fallito (HTTP {$http_code}): {$error_msg}");
            }
            
            $file_id = $upload_data['id'];
            $this->log('✅ File caricato con ID: ' . $file_id);
            
            // Genera link condivisione
            $share_url = $this->create_shareable_link($file_id);
            
            if ($share_url) {
                $this->log('✅ Link condivisione: ' . $share_url);
                $this->log('========== UPLOAD COMPLETATO CON SUCCESSO ==========');
                return $share_url;
            }
            
            $this->log('⚠️ Upload riuscito ma link condivisione non creato');
            return "https://drive.google.com/file/d/{$file_id}/view";
            
        } catch (\Exception $e) {
            $this->log('❌ ERRORE UPLOAD: ' . $e->getMessage(), 'ERROR');
            return false;
        }
    }
    
    /**
     * ========================================================================
     * GESTIONE CARTELLE: Trova o crea cartella
     * ✅ Supporta gerarchia con parent_id
     * ========================================================================
     */
    private function get_or_create_folder($folder_name, $parent_id = null) {
        try {
            $token = $this->get_valid_access_token();
            
            // Cerca cartella esistente
            $query = "name='{$folder_name}' and mimeType='application/vnd.google-apps.folder' and trashed=false";
            
            if ($parent_id) {
                $query .= " and '{$parent_id}' in parents";
            }
            
            $search_url = 'https://www.googleapis.com/drive/v3/files?' . http_build_query(array(
                'q' => $query,
                'fields' => 'files(id, name)',
                'pageSize' => 1
            ));
            
            $response = wp_remote_get($search_url, array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $token
                ),
                'timeout' => 30
            ));
            
            if (!is_wp_error($response)) {
                $data = json_decode(wp_remote_retrieve_body($response), true);
                
                if (!empty($data['files'][0]['id'])) {
                    $this->log("✅ Cartella '{$folder_name}' trovata (ID: {$data['files'][0]['id']})");
                    return $data['files'][0]['id'];
                }
            }
            
            // Crea nuova cartella
            $this->log("📁 Creazione cartella '{$folder_name}'...");
            
            $metadata = array(
                'name' => $folder_name,
                'mimeType' => 'application/vnd.google-apps.folder'
            );
            
            if ($parent_id) {
                $metadata['parents'] = array($parent_id);
            }
            
            $create_response = wp_remote_post('https://www.googleapis.com/drive/v3/files', array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $token,
                    'Content-Type' => 'application/json'
                ),
                'body' => json_encode($metadata),
                'timeout' => 30
            ));
            
            if (is_wp_error($create_response)) {
                throw new \Exception('Errore creazione cartella: ' . $create_response->get_error_message());
            }
            
            $create_data = json_decode(wp_remote_retrieve_body($create_response), true);
            
            if (empty($create_data['id'])) {
                throw new \Exception('ID cartella non ricevuto');
            }
            
            $this->log("✅ Cartella '{$folder_name}' creata (ID: {$create_data['id']})");
            return $create_data['id'];
            
        } catch (\Exception $e) {
            $this->log('❌ Errore gestione cartella: ' . $e->getMessage(), 'ERROR');
            throw $e;
        }
    }
    
    /**
     * ========================================================================
     * LINK CONDIVISIONE: Crea link pubblico per file
     * ========================================================================
     */
    private function create_shareable_link($file_id) {
        try {
            $token = $this->get_valid_access_token();
            
            // Imposta permessi pubblici
            $response = wp_remote_post("https://www.googleapis.com/drive/v3/files/{$file_id}/permissions", array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $token,
                    'Content-Type' => 'application/json'
                ),
                'body' => json_encode(array(
                    'role' => 'reader',
                    'type' => 'anyone'
                )),
                'timeout' => 30
            ));
            
            if (is_wp_error($response)) {
                $this->log('Errore impostazione permessi: ' . $response->get_error_message(), 'WARNING');
                return null;
            }
            
            return "https://drive.google.com/file/d/{$file_id}/view";
            
        } catch (\Exception $e) {
            $this->log('Errore creazione link: ' . $e->getMessage(), 'WARNING');
            return null;
        }
    }
    
    /**
     * ========================================================================
     * OAUTH 2.0: Gestione Access Token
     * ========================================================================
     */
    private function get_valid_access_token() {
        // Check cache
        if ($this->access_token_cache) {
            $expires = get_option('disco747_googledrive_token_expires', 0);
            if (time() < $expires - 300) { // 5 minuti margine
                return $this->access_token_cache;
            }
        }
        
        // Check opzione
        $access_token = get_option('disco747_googledrive_access_token', '');
        $expires = get_option('disco747_googledrive_token_expires', 0);
        
        if ($access_token && time() < $expires - 300) {
            $this->access_token_cache = $access_token;
            return $access_token;
        }
        
        // Refresh token
        $this->log('Access token scaduto, refresh in corso...');
        return $this->refresh_access_token();
    }
    
    /**
     * Refresh access token usando refresh_token
     */
    private function refresh_access_token() {
        $credentials = $this->get_oauth_credentials();
        
        if (empty($credentials['refresh_token'])) {
            throw new \Exception('Refresh token mancante. Riautenticare Google Drive.');
        }
        
        $this->log('Refresh access token...');
        
        $response = wp_remote_post('https://oauth2.googleapis.com/token', array(
            'body' => array(
                'client_id' => $credentials['client_id'],
                'client_secret' => $credentials['client_secret'],
                'refresh_token' => $credentials['refresh_token'],
                'grant_type' => 'refresh_token'
            ),
            'timeout' => 30
        ));
        
        if (is_wp_error($response)) {
            throw new \Exception('Errore refresh token: ' . $response->get_error_message());
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        $http_code = wp_remote_retrieve_response_code($response);
        
        if ($http_code !== 200 || !isset($body['access_token'])) {
            $error = $body['error_description'] ?? 'Errore sconosciuto';
            throw new \Exception("Errore refresh token: {$error}");
        }
        
        $this->save_access_token($body);
        $this->log('✅ Access token refreshed');
        
        return $body['access_token'];
    }
    
    /**
     * Salva access token
     */
    private function save_access_token($token_data) {
        $access_token = $token_data['access_token'];
        $expires_in = $token_data['expires_in'] ?? 3600;
        
        update_option('disco747_googledrive_access_token', $access_token);
        update_option('disco747_googledrive_token_expires', time() + $expires_in);
        
        $this->access_token_cache = $access_token;
        $this->log("Access token salvato (scade in {$expires_in}s)");
    }
    
    /**
     * ========================================================================
     * CREDENZIALI: Ottiene configurazione OAuth
     * ✅ FIX: Cerca credenziali in tutte le possibili chiavi (supporta migrazione)
     * ========================================================================
     */
    public function get_oauth_credentials() {
        if ($this->credentials_cache !== null) {
            return $this->credentials_cache;
        }
        
        // Prova prima l'array unificato disco747_gd_credentials
        $credentials = get_option('disco747_gd_credentials', array());
        
        if (empty($credentials['client_id'])) {
            // ✅ FIX: Prova le chiavi disco747_googledrive_* (nuovo sistema)
            $credentials = array(
                'client_id' => get_option('disco747_googledrive_client_id', ''),
                'client_secret' => get_option('disco747_googledrive_client_secret', ''),
                'redirect_uri' => get_option('disco747_googledrive_redirect_uri', ''),
                'refresh_token' => get_option('disco747_googledrive_refresh_token', ''),
                'folder_id' => get_option('disco747_googledrive_folder_id', '')
            );
            
            // Se non trovate, prova fallback a vecchia struttura preventivi_*
            if (empty($credentials['client_id'])) {
                $credentials = array(
                    'client_id' => get_option('preventivi_googledrive_client_id', ''),
                    'client_secret' => get_option('preventivi_googledrive_client_secret', ''),
                    'redirect_uri' => get_option('preventivi_googledrive_redirect_uri', ''),
                    'refresh_token' => get_option('preventivi_googledrive_refresh_token', ''),
                    'folder_id' => get_option('preventivi_googledrive_folder_id', '')
                );
                
                if (!empty($credentials['client_id'])) {
                    $this->log('✅ Credenziali trovate in preventivi_googledrive_* (vecchio sistema)');
                }
            } else {
                $this->log('✅ Credenziali trovate in disco747_googledrive_* (nuovo sistema)');
            }
            
            // Migra a struttura unificata se trovate credenziali valide
            if (!empty($credentials['client_id'])) {
                update_option('disco747_gd_credentials', $credentials);
                $this->log('✅ Credenziali migrate a disco747_gd_credentials');
            }
        } else {
            $this->log('✅ Credenziali caricate da disco747_gd_credentials (array unificato)');
        }
        
        $this->credentials_cache = $credentials;
        return $credentials;
    }
    
    /**
     * Verifica se OAuth è configurato
     * ✅ FIX: Usa get_oauth_credentials() che ora cerca in tutte le chiavi possibili
     */
    public function is_oauth_configured() {
        $credentials = $this->get_oauth_credentials();
        
        $has_client_id = !empty($credentials['client_id']);
        $has_client_secret = !empty($credentials['client_secret']);
        $has_refresh_token = !empty($credentials['refresh_token']);
        
        $is_configured = $has_client_id && $has_client_secret && $has_refresh_token;
        
        // Log dettagliato per debug
        if ($this->debug_mode) {
            $this->log('🔍 Verifica configurazione OAuth:');
            $this->log('   - Client ID: ' . ($has_client_id ? 'presente' : 'MANCANTE'));
            $this->log('   - Client Secret: ' . ($has_client_secret ? 'presente' : 'MANCANTE'));
            $this->log('   - Refresh Token: ' . ($has_refresh_token ? 'presente' : 'MANCANTE'));
            $this->log('   - Risultato: ' . ($is_configured ? '✅ CONFIGURATO' : '❌ NON CONFIGURATO'));
        }
        
        return $is_configured;
    }
    
    /**
     * ========================================================================
     * OAUTH FLOW: Genera URL autorizzazione
     * ✅ FIX: Usa il redirect_uri corretto dalla pagina storage
     * ========================================================================
     */
    public function generate_auth_url() {
        $credentials = $this->get_oauth_credentials();
        
        if (empty($credentials['client_id'])) {
            return array(
                'success' => false, 
                'message' => 'Client ID non configurato'
            );
        }
        
        $state = wp_create_nonce('googledrive_oauth_' . time());
        update_option('disco747_googledrive_oauth_state', $state);
        
        // ✅ FIX: Usa redirect_uri dalla pagina storage (corretto)
        $redirect_uri = !empty($credentials['redirect_uri']) 
            ? $credentials['redirect_uri'] 
            : admin_url('admin.php?page=disco747-storage&oauth_callback=googledrive');
        
        $params = array(
            'client_id' => $credentials['client_id'],
            'redirect_uri' => $redirect_uri,
            'scope' => 'https://www.googleapis.com/auth/drive.file',
            'response_type' => 'code',
            'access_type' => 'offline',
            'prompt' => 'consent',
            'state' => $state
        );
        
        $auth_url = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params);
        
        $this->log('✅ URL autorizzazione generato');
        $this->log('   - Redirect URI: ' . $redirect_uri);
        
        return array(
            'success' => true,
            'auth_url' => $auth_url,
            'state' => $state
        );
    }
    
    /**
     * ========================================================================
     * OAUTH FLOW: Scambia code per tokens
     * ✅ FIX: Salva refresh_token in tutte le chiavi per compatibilità
     * ========================================================================
     */
    public function exchange_code_for_tokens($auth_code, $state) {
        $stored_state = get_option('disco747_googledrive_oauth_state');
        
        if (empty($state) || $state !== $stored_state) {
            return array(
                'success' => false, 
                'message' => 'Stato OAuth non valido'
            );
        }
        
        delete_option('disco747_googledrive_oauth_state');
        
        $credentials = $this->get_oauth_credentials();
        
        if (empty($credentials['client_id']) || empty($credentials['client_secret'])) {
            return array(
                'success' => false, 
                'message' => 'Credenziali OAuth incomplete'
            );
        }
        
        $this->log('Scambio code per tokens...');
        
        $redirect_uri = !empty($credentials['redirect_uri']) 
            ? $credentials['redirect_uri'] 
            : admin_url('admin.php?page=disco747-storage&oauth_callback=googledrive');
        
        $response = wp_remote_post('https://oauth2.googleapis.com/token', array(
            'body' => array(
                'grant_type' => 'authorization_code',
                'code' => trim($auth_code),
                'client_id' => trim($credentials['client_id']),
                'client_secret' => trim($credentials['client_secret']),
                'redirect_uri' => $redirect_uri
            ),
            'timeout' => 30
        ));
        
        if (is_wp_error($response)) {
            return array(
                'success' => false, 
                'message' => 'Errore connessione: ' . $response->get_error_message()
            );
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        $http_code = wp_remote_retrieve_response_code($response);
        
        if ($http_code !== 200) {
            $error = $body['error_description'] ?? 'Errore sconosciuto';
            return array(
                'success' => false, 
                'message' => "Errore Google: {$error}"
            );
        }
        
        if (isset($body['refresh_token'])) {
            // ✅ FIX: Salva refresh token in TUTTE le chiavi per compatibilità
            $credentials['refresh_token'] = $body['refresh_token'];
            
            // Salva nell'array unificato
            update_option('disco747_gd_credentials', $credentials);
            
            // Salva anche nelle chiavi separate per compatibilità con altri componenti
            update_option('disco747_googledrive_refresh_token', $body['refresh_token']);
            update_option('preventivi_googledrive_refresh_token', $body['refresh_token']); // Vecchio sistema
            
            // Salva access token
            $this->save_access_token($body);
            
            // Invalida cache credenziali
            $this->credentials_cache = null;
            
            $this->log('✅ Refresh token salvato in tutte le chiavi');
            $this->log('   - disco747_gd_credentials (array)');
            $this->log('   - disco747_googledrive_refresh_token');
            $this->log('   - preventivi_googledrive_refresh_token (compatibilità)');
            
            return array(
                'success' => true,
                'message' => 'Google Drive configurato con successo!',
                'refresh_token' => substr($body['refresh_token'], 0, 30) . '...'
            );
        } else {
            return array(
                'success' => false,
                'message' => 'Refresh token non ricevuto. Riprova con prompt=consent'
            );
        }
    }
    
    /**
     * ========================================================================
     * TEST CONNESSIONE
     * ========================================================================
     */
    public function test_connection() {
        try {
            $token = $this->get_valid_access_token();
            
            $response = wp_remote_get('https://www.googleapis.com/drive/v3/about?fields=user', array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $token
                ),
                'timeout' => 15
            ));
            
            if (is_wp_error($response)) {
                return array(
                    'success' => false,
                    'message' => 'Errore connessione: ' . $response->get_error_message()
                );
            }
            
            $body = json_decode(wp_remote_retrieve_body($response), true);
            $http_code = wp_remote_retrieve_response_code($response);
            
            if ($http_code === 200 && isset($body['user'])) {
                return array(
                    'success' => true,
                    'message' => 'Connessione Google Drive OK',
                    'user_name' => $body['user']['displayName'] ?? 'N/D',
                    'user_email' => $body['user']['emailAddress'] ?? 'N/D'
                );
            } else {
                $error = $body['error']['message'] ?? 'Errore sconosciuto';
                return array(
                    'success' => false,
                    'message' => "Errore API: {$error}"
                );
            }
            
        } catch (\Exception $e) {
            return array(
                'success' => false,
                'message' => 'Errore test: ' . $e->getMessage()
            );
        }
    }
    
    /**
     * ========================================================================
     * VERIFICA CONNESSIONE
     * ========================================================================
     */
    public function is_connected() {
        try {
            $credentials = $this->get_oauth_credentials();
            
            if (empty($credentials['refresh_token'])) {
                return false;
            }
            
            // Prova a ottenere access token
            $token = $this->get_valid_access_token();
            
            return !empty($token);
            
        } catch (\Exception $e) {
            return false;
        }
    }
    
    /**
     * ========================================================================
     * METODI UTILITY
     * ========================================================================
     */
    
    /**
     * Lista file in una cartella
     */
    public function list_files($folder_id = null, $query = '') {
        try {
            $token = $this->get_valid_access_token();
            
            $q = "trashed=false";
            
            if ($folder_id) {
                $q .= " and '{$folder_id}' in parents";
            }
            
            if ($query) {
                $q .= " and name contains '{$query}'";
            }
            
            $url = 'https://www.googleapis.com/drive/v3/files?' . http_build_query(array(
                'q' => $q,
                'fields' => 'files(id, name, mimeType, modifiedTime, size)',
                'pageSize' => 100
            ));
            
            $response = wp_remote_get($url, array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $token
                ),
                'timeout' => 30
            ));
            
            if (is_wp_error($response)) {
                return array();
            }
            
            $body = json_decode(wp_remote_retrieve_body($response), true);
            
            return $body['files'] ?? array();
            
        } catch (\Exception $e) {
            $this->log('Errore list files: ' . $e->getMessage(), 'ERROR');
            return array();
        }
    }
    
    /**
     * Elimina file
     */
    public function delete_file($file_id) {
        try {
            $token = $this->get_valid_access_token();
            
            $response = wp_remote_request(
                "https://www.googleapis.com/drive/v3/files/{$file_id}",
                array(
                    'method' => 'DELETE',
                    'headers' => array(
                        'Authorization' => 'Bearer ' . $token
                    ),
                    'timeout' => 30
                )
            );
            
            if (is_wp_error($response)) {
                return array(
                    'success' => false,
                    'error' => $response->get_error_message()
                );
            }
            
            $http_code = wp_remote_retrieve_response_code($response);
            
            if ($http_code === 204) {
                return array('success' => true);
            }
            
            return array(
                'success' => false,
                'error' => "HTTP {$http_code}"
            );
            
        } catch (\Exception $e) {
            return array(
                'success' => false,
                'error' => $e->getMessage()
            );
        }
    }
    
    /**
     * Download file
     */
    public function download_file($file_id, $local_path) {
        try {
            $token = $this->get_valid_access_token();
            
            $url = "https://www.googleapis.com/drive/v3/files/{$file_id}?alt=media";
            
            $response = wp_remote_get($url, array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $token
                ),
                'timeout' => 60
            ));
            
            if (is_wp_error($response)) {
                return array(
                    'success' => false,
                    'error' => $response->get_error_message()
                );
            }
            
            $http_code = wp_remote_retrieve_response_code($response);
            
            if ($http_code !== 200) {
                return array(
                    'success' => false,
                    'error' => "HTTP {$http_code}"
                );
            }
            
            $content = wp_remote_retrieve_body($response);
            
            // Crea directory se non esiste
            $dir = dirname($local_path);
            if (!is_dir($dir)) {
                wp_mkdir_p($dir);
            }
            
            // Salva file
            $result = file_put_contents($local_path, $content);
            
            if ($result === false) {
                return array(
                    'success' => false,
                    'error' => 'Impossibile salvare file'
                );
            }
            
            return array(
                'success' => true,
                'path' => $local_path,
                'size' => $result
            );
            
        } catch (\Exception $e) {
            return array(
                'success' => false,
                'error' => $e->getMessage()
            );
        }
    }
    
    /**
     * Ottiene info file
     */
    public function get_file_info($file_id) {
        try {
            $token = $this->get_valid_access_token();
            
            $url = "https://www.googleapis.com/drive/v3/files/{$file_id}?fields=id,name,mimeType,size,modifiedTime,webViewLink";
            
            $response = wp_remote_get($url, array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $token
                ),
                'timeout' => 30
            ));
            
            if (is_wp_error($response)) {
                return false;
            }
            
            $body = json_decode(wp_remote_retrieve_body($response), true);
            
            return $body;
            
        } catch (\Exception $e) {
            return false;
        }
    }
    
    /**
     * Crea cartella
     */
    public function create_folder($folder_name, $parent_id = null) {
        try {
            return $this->get_or_create_folder($folder_name, $parent_id);
        } catch (\Exception $e) {
            $this->log('Errore creazione cartella: ' . $e->getMessage(), 'ERROR');
            return false;
        }
    }
    
    /**
     * ========================================================================
     * SINCRONIZZAZIONE CREDENZIALI
     * ✅ NUOVO: Sincronizza credenziali tra tutte le chiavi
     * ========================================================================
     */
    public function sync_credentials() {
        $this->log('🔄 Sincronizzazione credenziali...');
        
        // Ottieni credenziali (questo già cerca in tutte le chiavi)
        $credentials = $this->get_oauth_credentials();
        
        if (empty($credentials['client_id'])) {
            $this->log('⚠️ Nessuna credenziale da sincronizzare');
            return false;
        }
        
        // Salva nell'array unificato
        update_option('disco747_gd_credentials', $credentials);
        
        // Salva anche nelle chiavi separate per compatibilità
        if (!empty($credentials['client_id'])) {
            update_option('disco747_googledrive_client_id', $credentials['client_id']);
            update_option('preventivi_googledrive_client_id', $credentials['client_id']);
        }
        
        if (!empty($credentials['client_secret'])) {
            update_option('disco747_googledrive_client_secret', $credentials['client_secret']);
            update_option('preventivi_googledrive_client_secret', $credentials['client_secret']);
        }
        
        if (!empty($credentials['redirect_uri'])) {
            update_option('disco747_googledrive_redirect_uri', $credentials['redirect_uri']);
            update_option('preventivi_googledrive_redirect_uri', $credentials['redirect_uri']);
        }
        
        if (!empty($credentials['refresh_token'])) {
            update_option('disco747_googledrive_refresh_token', $credentials['refresh_token']);
            update_option('preventivi_googledrive_refresh_token', $credentials['refresh_token']);
        }
        
        if (!empty($credentials['folder_id'])) {
            update_option('disco747_googledrive_folder_id', $credentials['folder_id']);
            update_option('preventivi_googledrive_folder_id', $credentials['folder_id']);
        }
        
        // Invalida cache
        $this->credentials_cache = null;
        
        $this->log('✅ Credenziali sincronizzate in tutte le chiavi');
        return true;
    }
    
    /**
     * ========================================================================
     * LOGGING
     * ========================================================================
     */
    private function log($message, $level = 'INFO') {
        if ($this->debug_mode) {
            error_log("[747Disco-CRM] [GoogleDrive] [{$level}] {$message}");
        }
    }
}