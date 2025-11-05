<?php
/**
 * Handler dedicato per la scansione Excel di 747 Disco CRM
 * VERSIONE AGGIORNATA - Scansione REALE da Google Drive
 * 
 * @package    Disco747_CRM
 * @subpackage Handlers
 * @since      11.4.2
 * @version    11.9.0 - IMPLEMENTAZIONE REALE
 */

namespace Disco747_CRM\Handlers;

// Sicurezza: impedisce l'accesso diretto al file
if (!defined('ABSPATH')) {
    exit('Accesso diretto non consentito');
}

/**
 * Classe per gestire la scansione automatica dei file Excel da Google Drive
 * VERSIONE AGGIORNATA: Implementa scansione reale e salvataggio unificato
 */
class Disco747_Excel_Scan_Handler {
    
    /**
     * Nome della tabella unificata per i preventivi
     */
    private $table_name;
    
    /**
     * Istanza Google Drive
     */
    private $googledrive = null;
    
    /**
     * Costruttore
     */
    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'disco747_preventivi'; // ✅ TABELLA UNIFICATA
        
        // Registra hooks AJAX
        add_action('wp_ajax_batch_scan_excel', array($this, 'handle_batch_scan_ajax'));
        add_action('wp_ajax_reset_and_scan_excel', array($this, 'handle_reset_and_scan_ajax'));
        
        // Inizializza Google Drive
        $this->init_googledrive();
    }
    
    /**
     * Inizializza connessione Google Drive
     */
    private function init_googledrive() {
        try {
            $disco747_crm = disco747_crm();
            if ($disco747_crm && $disco747_crm->is_initialized()) {
                $storage_manager = $disco747_crm->get_storage_manager();
                if ($storage_manager && method_exists($storage_manager, 'get_googledrive')) {
                    $this->googledrive = $storage_manager->get_googledrive();
                }
            }
            
            // ✅ Fallback: carica direttamente se disponibile
            if (!$this->googledrive && class_exists('Disco747_CRM\\Storage\\Disco747_GoogleDrive')) {
                $this->googledrive = new \Disco747_CRM\Storage\Disco747_GoogleDrive();
            }
            
        } catch (Exception $e) {
            error_log('Disco747 Excel Scan - Errore init Google Drive: ' . $e->getMessage());
        }
    }
    
    /**
     * Handler AJAX per scansione batch
     */
    public function handle_batch_scan_ajax() {
        // ✅ Aumenta timeout PHP per scansioni lunghe (usa config centralizzata)
        if (function_exists('disco747_set_scan_timeout')) {
            disco747_set_scan_timeout();
        } else {
            @set_time_limit(900);
            @ini_set('max_execution_time', 900);
        }
        
        // Verifica nonce
        if (!check_ajax_referer('disco747_batch_scan', 'nonce', false)) {
            wp_send_json_error(array('message' => 'Nonce non valido'));
            return;
        }
        
        // Verifica permessi
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permessi insufficienti'));
            return;
        }
        
        try {
            $dry_run = isset($_POST['dry_run']) ? intval($_POST['dry_run']) === 1 : false;
            
            error_log("[747Disco-Scan] Avvio scansione batch (timeout: 15min)");
            
            // Inizializza contatori
            $counters = array(
                'listed' => 0,
                'downloaded' => 0,
                'parsed_ok' => 0,
                'saved_ok' => 0,
                'errors' => 0
            );
            
            $errors = array();
            $results = array();
            
            // ✅ REALE: Trova file Excel da Google Drive
            $excel_files = $this->get_excel_files_from_googledrive();
            $counters['listed'] = count($excel_files);
            
            error_log("[747Disco-Scan] Trovati {$counters['listed']} file Excel da Google Drive");
            
            if (empty($excel_files)) {
                wp_send_json_success(array(
                    'total_files' => 0,
                    'processed' => 0,
                    'new_records' => 0,
                    'updated_records' => 0,
                    'errors' => 0,
                    'messages' => array('Nessun file Excel trovato con i filtri specificati')
                ));
                return;
            }
            
            // ✅ REALE: Processa ogni file Excel
            foreach ($excel_files as $i => $file) {
                try {
                    error_log("[747Disco-Scan] Processando file: {$file['name']}");
                    
                    // ✅ REALE: Download e parsing
                    $parsed_data = $this->download_and_parse_excel($file);
                    $counters['downloaded']++;
                    
                    if (!$parsed_data) {
                        $errors[] = "Impossibile parsare file: {$file['name']}";
                        $counters['errors']++;
                        continue;
                    }
                    
                    $counters['parsed_ok']++;
                    
                    // ✅ REALE: Salva nella tabella unificata se non è dry run
                    if (!$dry_run) {
                        $preventivo_id = $this->save_to_preventivi_table($parsed_data);
                        if ($preventivo_id) {
                            $counters['saved_ok']++;
                            $results[] = array(
                                'preventivo_id' => $preventivo_id,
                                'filename' => $file['name'],
                                'data' => $parsed_data
                            );
                        } else {
                            $errors[] = "Impossibile salvare preventivo per: {$file['name']}";
                            $counters['errors']++;
                        }
                    } else {
                        $counters['saved_ok']++;
                    }
                    
                    // ✅ Rate limiting ridotto per velocizzare (100ms invece di 200ms)
                    if ($i < count($excel_files) - 1) {
                        usleep(100000); // 100ms
                    }
                    
                } catch (\Exception $e) {
                    $error_msg = "Errore processando {$file['name']}: " . $e->getMessage();
                    $errors[] = $error_msg;
                    error_log("[747Disco-Scan] {$error_msg}");
                    $counters['errors']++;
                    
                    // ✅ NON bloccare l'intera scansione, continua con file successivo
                    continue;
                }
            }
            
            error_log("[747Disco-Scan] Completata - Parsed: {$counters['parsed_ok']}, Saved: {$counters['saved_ok']}, Errors: {$counters['errors']}");
            
            // Prepara messaggi per il frontend
            $messages = array();
            foreach ($results as $result) {
                $messages[] = "✅ Processato: {$result['filename']} - {$result['data']['nome_cliente']}";
            }
            foreach (array_slice($errors, 0, 5) as $error) {
                $messages[] = "❌ Errore: {$error}";
            }
            
            wp_send_json_success(array(
                'total_files' => $counters['listed'],
                'processed' => $counters['parsed_ok'],
                'new_records' => $counters['saved_ok'],
                'updated_records' => 0,
                'errors' => $counters['errors'],
                'messages' => $messages
            ));
            
        } catch (Exception $e) {
            error_log('[747Disco-Scan] Errore scansione batch: ' . $e->getMessage());
            wp_send_json_error(array('message' => 'Errore interno: ' . $e->getMessage()));
        }
    }
    
    /**
     * Handler AJAX per reset e scan completo
     */
    public function handle_reset_and_scan_ajax() {
        // ✅ Aumenta timeout PHP per scansioni lunghe (usa config centralizzata)
        if (function_exists('disco747_set_scan_timeout')) {
            disco747_set_scan_timeout();
        } else {
            @set_time_limit(900);
            @ini_set('max_execution_time', 900);
        }
        
        // Verifica nonce
        if (!check_ajax_referer('disco747_batch_scan', 'nonce', false)) {
            wp_send_json_error(array('message' => 'Nonce non valido'));
            return;
        }
        
        // Verifica permessi
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permessi insufficienti'));
            return;
        }
        
        try {
            error_log('[747Disco-Scan] Svuotamento database (timeout: 15min)...');
            
            // Svuota tabella preventivi
            global $wpdb;
            $deleted = $wpdb->query("DELETE FROM {$this->table_name}");
            
            error_log("[747Disco-Scan] Eliminati {$deleted} record dal database");
            
            // Esegui batch scan normale
            $this->handle_batch_scan_ajax();
            
        } catch (Exception $e) {
            error_log('[747Disco-Scan] Errore reset and scan: ' . $e->getMessage());
            wp_send_json_error(array('message' => 'Errore: ' . $e->getMessage()));
        }
    }
    
    /**
     * ✅ REALE: Trova file Excel da Google Drive
     */
    private function get_excel_files_from_googledrive() {
        if (!$this->googledrive) {
            error_log('[747Disco-Scan] Google Drive non inizializzato');
            return array();
        }
        
        try {
            $excel_files = array();
            
            // Ottieni parametri dal POST
            $year = sanitize_text_field($_POST['year'] ?? date('Y'));
            $month = sanitize_text_field($_POST['month'] ?? '');
            
            error_log("[747Disco-Scan] Parametri: anno={$year}, mese={$month}");
            
            // ✅ CORRETTO: Usa metodo pubblico per trovare cartella principale
            $main_folder_id = $this->find_main_folder();
            if (!$main_folder_id) {
                error_log('[747Disco-Scan] Cartella principale 747-Preventivi non trovata');
                return array();
            }
            
            // Scansiona file con filtri
            $all_files = $this->scan_excel_files_with_filters($main_folder_id, $year, $month);
            
            error_log("[747Disco-Scan] Trovati " . count($all_files) . " file Excel totali");
            
            return $all_files;
            
        } catch (Exception $e) {
            error_log('Disco747 Excel Scan - Errore ricerca file: ' . $e->getMessage());
            return array();
        }
    }
    
    /**
     * Trova cartella principale 747-Preventivi usando API diretta
     */
    private function find_main_folder() {
        try {
            error_log('[747Disco-Scan] Ricerca cartella principale 747-Preventivi...');
            
            // ✅ USA METODO PUBBLICO per ottenere token
            if (!$this->googledrive->is_connected()) {
                error_log('[747Disco-Scan] Google Drive non connesso');
                return null;
            }
            
            // Ottieni token usando metodo pubblico
            $token = $this->get_access_token_public();
            if (!$token) {
                error_log('[747Disco-Scan] Token di accesso non disponibile');
                return null;
            }
            
            // Cerca cartella 747-Preventivi nella root
            $query = "name='747-Preventivi' and mimeType='application/vnd.google-apps.folder' and trashed=false";
            $url = 'https://www.googleapis.com/drive/v3/files?' . http_build_query(array(
                'q' => $query,
                'fields' => 'files(id, name)',
                'pageSize' => 1
            ));
            
            $response = wp_remote_get($url, array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $token
                ),
                'timeout' => 120 // ✅ 2 minuti per richieste API Google Drive
            ));
            
            if (is_wp_error($response)) {
                error_log('[747Disco-Scan] Errore API Google Drive: ' . $response->get_error_message());
                return null;
            }
            
            $body = json_decode(wp_remote_retrieve_body($response), true);
            $http_code = wp_remote_retrieve_response_code($response);
            
            error_log('[747Disco-Scan] Risultato ricerca root (HTTP ' . $http_code . '): ' . json_encode($body));
            
            if ($http_code === 200 && !empty($body['files'])) {
                error_log('[747Disco-Scan] Cartella 747-Preventivi trovata nella root (ID: ' . $body['files'][0]['id'] . ')');
                return $body['files'][0]['id'];
            }
            
            // Se non trovata, cerca in tutte le cartelle
            error_log('[747Disco-Scan] Cartella non trovata nella root, cerco in tutte le cartelle...');
            $all_query = "mimeType='application/vnd.google-apps.folder' and trashed=false";
            $all_url = 'https://www.googleapis.com/drive/v3/files?' . http_build_query(array(
                'q' => $all_query,
                'fields' => 'files(id, name)',
                'pageSize' => 100
            ));
            
            $all_response = wp_remote_get($all_url, array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $token
                ),
                'timeout' => 30
            ));
            
            if (!is_wp_error($all_response)) {
                $all_body = json_decode(wp_remote_retrieve_body($all_response), true);
                error_log('[747Disco-Scan] Cartelle trovate nella root: ' . count($all_body['files'] ?? []));
                
                foreach ($all_body['files'] ?? [] as $folder) {
                    error_log('[747Disco-Scan] Cartella: ' . $folder['name'] . ' (ID: ' . $folder['id'] . ')');
                    if ($folder['name'] === '747-Preventivi') {
                        error_log('[747Disco-Scan] Cartella 747-Preventivi trovata!');
                        return $folder['id'];
                    }
                }
            }
            
            error_log('[747Disco-Scan] Cartella 747-Preventivi non trovata');
            return null;
            
        } catch (Exception $e) {
            error_log('[747Disco-Scan] Errore ricerca cartella principale: ' . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Ottiene access token usando metodi pubblici
     */
    private function get_access_token_public() {
        try {
            // Prova a ottenere token dalle opzioni WordPress
            $access_token = get_option('disco747_googledrive_access_token', '');
            $expires = get_option('disco747_googledrive_token_expires', 0);
            
            if ($access_token && time() < $expires - 300) { // 5 minuti margine
                return $access_token;
            }
            
            // Se scaduto, prova refresh
            $credentials = $this->googledrive->get_oauth_credentials();
            if (empty($credentials['refresh_token'])) {
                error_log('[747Disco-Scan] Refresh token mancante');
                return null;
            }
            
            // Refresh token
            $response = wp_remote_post('https://oauth2.googleapis.com/token', array(
                'body' => array(
                    'client_id' => $credentials['client_id'],
                    'client_secret' => $credentials['client_secret'],
                    'refresh_token' => $credentials['refresh_token'],
                    'grant_type' => 'refresh_token'
                ),
                'timeout' => 120 // ✅ 2 minuti per refresh token
            ));
            
            if (is_wp_error($response)) {
                error_log('[747Disco-Scan] Errore refresh token: ' . $response->get_error_message());
                return null;
            }
            
            $body = json_decode(wp_remote_retrieve_body($response), true);
            $http_code = wp_remote_retrieve_response_code($response);
            
            if ($http_code !== 200 || !isset($body['access_token'])) {
                $error = $body['error_description'] ?? 'Errore sconosciuto';
                error_log("[747Disco-Scan] Errore refresh token: {$error}");
                return null;
            }
            
            // Salva nuovo token
            $access_token = $body['access_token'];
            $expires_in = $body['expires_in'] ?? 3600;
            
            update_option('disco747_googledrive_access_token', $access_token);
            update_option('disco747_googledrive_token_expires', time() + $expires_in);
            
            error_log('[747Disco-Scan] Token refreshed con successo');
            return $access_token;
            
        } catch (Exception $e) {
            error_log('[747Disco-Scan] Errore ottenimento token: ' . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Scansiona file Excel con filtri anno/mese
     */
    private function scan_excel_files_with_filters($main_folder_id, $year = null, $month = null) {
        $all_files = array();
        
        try {
            if ($year) {
                $year_folder_id = $this->find_year_folder($main_folder_id, $year);
                if (!$year_folder_id) {
                    error_log("[747Disco-Scan] Cartella anno {$year} non trovata");
                    return array();
                }
                
                if ($month) {
                    $month_folder_id = $this->find_month_folder($year_folder_id, $month);
                    if (!$month_folder_id) {
                        error_log("[747Disco-Scan] Cartella mese {$month} non trovata");
                        return array();
                    }
                    $all_files = $this->scan_excel_files_in_folder($month_folder_id);
                } else {
                    $all_files = $this->scan_all_excel_files_recursive($year_folder_id);
                }
            } else {
                $all_files = $this->scan_all_excel_files_recursive($main_folder_id);
            }
            
            error_log("[747Disco-Scan] Filtri applicati - Anno: " . ($year ?: 'tutti') . ", Mese: " . ($month ?: 'tutti'));
            
        } catch (Exception $e) {
            error_log("[747Disco-Scan] Errore scansione con filtri: " . $e->getMessage());
        }
        
        return $all_files;
    }
    
    /**
     * Trova cartella anno usando API diretta
     */
    private function find_year_folder($parent_id, $year) {
        try {
            error_log("[747Disco-Scan] Cerco cartella anno: {$year} in parent: {$parent_id}");
            
            $token = $this->get_access_token_public();
            if (!$token) return null;
            
            $query = "name='{$year}' and mimeType='application/vnd.google-apps.folder' and trashed=false and '{$parent_id}' in parents";
            $url = 'https://www.googleapis.com/drive/v3/files?' . http_build_query(array(
                'q' => $query,
                'fields' => 'files(id, name)',
                'pageSize' => 1
            ));
            
            $response = wp_remote_get($url, array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $token
                ),
                'timeout' => 120 // ✅ 2 minuti per richieste API Google Drive
            ));
            
            if (is_wp_error($response)) {
                error_log("[747Disco-Scan] Errore ricerca anno: " . $response->get_error_message());
                return null;
            }
            
            $body = json_decode(wp_remote_retrieve_body($response), true);
            error_log("[747Disco-Scan] Risultato ricerca anno: " . json_encode($body));
            
            return !empty($body['files']) ? $body['files'][0]['id'] : null;
            
        } catch (Exception $e) {
            error_log("[747Disco-Scan] Errore ricerca anno: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Trova cartella mese usando API diretta
     */
    private function find_month_folder($parent_id, $month) {
        try {
            error_log("[747Disco-Scan] Cerco cartella mese: {$month} in parent: {$parent_id}");
            
            $token = $this->get_access_token_public();
            if (!$token) return null;
            
            $query = "name='{$month}' and mimeType='application/vnd.google-apps.folder' and trashed=false and '{$parent_id}' in parents";
            $url = 'https://www.googleapis.com/drive/v3/files?' . http_build_query(array(
                'q' => $query,
                'fields' => 'files(id, name)',
                'pageSize' => 1
            ));
            
            $response = wp_remote_get($url, array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $token
                ),
                'timeout' => 120 // ✅ 2 minuti per richieste API Google Drive
            ));
            
            if (is_wp_error($response)) {
                error_log("[747Disco-Scan] Errore ricerca mese: " . $response->get_error_message());
                return null;
            }
            
            $body = json_decode(wp_remote_retrieve_body($response), true);
            error_log("[747Disco-Scan] Risultato ricerca mese: " . json_encode($body));
            
            return !empty($body['files']) ? $body['files'][0]['id'] : null;
            
        } catch (Exception $e) {
            error_log("[747Disco-Scan] Errore ricerca mese: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Scansiona file Excel in una cartella specifica usando API diretta
     */
    private function scan_excel_files_in_folder($folder_id) {
        try {
            error_log("[747Disco-Scan] Scansiono cartella: {$folder_id}");
            
            $token = $this->get_access_token_public();
            if (!$token) return array();
            
            $query = "(mimeType='application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' or mimeType='application/vnd.ms-excel') and trashed=false and '{$folder_id}' in parents";
            $url = 'https://www.googleapis.com/drive/v3/files?' . http_build_query(array(
                'q' => $query,
                'fields' => 'files(id, name, mimeType, modifiedTime, size, webViewLink)',
                'pageSize' => 100
            ));
            
            $response = wp_remote_get($url, array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $token
                ),
                'timeout' => 120 // ✅ 2 minuti per richieste API Google Drive
            ));
            
            if (is_wp_error($response)) {
                error_log("[747Disco-Scan] Errore scansione cartella: " . $response->get_error_message());
                return array();
            }
            
            $body = json_decode(wp_remote_retrieve_body($response), true);
            $files = $body['files'] ?? [];
            
            error_log("[747Disco-Scan] File Excel trovati: " . count($files));
            
            $excel_files = array();
            foreach ($files as $file) {
                $excel_files[] = array(
                    'id' => $file['id'],
                    'name' => $file['name'],
                    'modifiedTime' => $file['modifiedTime'] ?? '',
                    'size' => $file['size'] ?? 0,
                    'webViewLink' => $file['webViewLink'] ?? ''
                );
                error_log("[747Disco-Scan] File: {$file['name']} (ID: {$file['id']})");
            }
            
            return $excel_files;
            
        } catch (Exception $e) {
            error_log("[747Disco-Scan] Errore scansione cartella: " . $e->getMessage());
            return array();
        }
    }
    
    /**
     * Scansiona ricorsivamente tutte le cartelle usando API diretta
     */
    private function scan_all_excel_files_recursive($folder_id) {
        $all_files = array();
        
        // Scansiona file Excel nella cartella corrente
        $files = $this->scan_excel_files_in_folder($folder_id);
        $all_files = array_merge($all_files, $files);
        
        // Scansiona sottocartelle
        try {
            $token = $this->get_access_token_public();
            if (!$token) return $all_files;
            
            $query = "mimeType='application/vnd.google-apps.folder' and trashed=false and '{$folder_id}' in parents";
            $url = 'https://www.googleapis.com/drive/v3/files?' . http_build_query(array(
                'q' => $query,
                'fields' => 'files(id, name)',
                'pageSize' => 100
            ));
            
            $response = wp_remote_get($url, array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $token
                ),
                'timeout' => 120 // ✅ 2 minuti per richieste API Google Drive
            ));
            
            if (!is_wp_error($response)) {
                $body = json_decode(wp_remote_retrieve_body($response), true);
                $subfolders = $body['files'] ?? [];
                
                error_log("[747Disco-Scan] Sottocartelle trovate: " . count($subfolders));
                
                foreach ($subfolders as $subfolder) {
                    error_log("[747Disco-Scan] Scansiono sottocartella: {$subfolder['name']} (ID: {$subfolder['id']})");
                    $subfolder_files = $this->scan_all_excel_files_recursive($subfolder['id']);
                    $all_files = array_merge($all_files, $subfolder_files);
                }
            }
        } catch (Exception $e) {
            error_log("[747Disco-Scan] Errore scansione sottocartelle: " . $e->getMessage());
        }
        
        return $all_files;
    }
    
    /**
     * ✅ REALE: Download e parsing file Excel
     */
    private function download_and_parse_excel($file_info) {
        if (!$this->googledrive) {
            error_log('[747Disco-Scan] Google Drive non disponibile per download');
            return false;
        }
        
        try {
            // Download temporaneo
            $upload_dir = wp_upload_dir();
            $temp_dir = $upload_dir['basedir'] . '/preventivi/temp/';
            
            if (!is_dir($temp_dir)) {
                wp_mkdir_p($temp_dir);
            }
            
            $temp_file = $temp_dir . 'excel_' . $file_info['id'] . '.xlsx';
            
            // ✅ Download reale da Google Drive
            $download_result = $this->googledrive->download_file($file_info['id'], $temp_file);
            
            if (!$download_result['success']) {
                error_log("[747Disco-Scan] Errore download: " . $download_result['error']);
                return false;
            }
            
            error_log("[747Disco-Scan] File scaricato: {$temp_file}");
            
            // ✅ Parsing reale con PhpSpreadsheet
            $parsed_data = $this->parse_excel_with_phpspreadsheet($temp_file, $file_info);
            
            // Pulizia file temporaneo
            if (file_exists($temp_file)) {
                unlink($temp_file);
            }
            
            return $parsed_data;
            
        } catch (Exception $e) {
            error_log('[747Disco-Scan] Errore download/parsing: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * ✅ REALE: Parsing Excel con PhpSpreadsheet
     */
    private function parse_excel_with_phpspreadsheet($file_path, $file_info) {
        try {
            // Carica PhpSpreadsheet se disponibile
            if (!class_exists('PhpOffice\\PhpSpreadsheet\\IOFactory')) {
                // Prova a caricare da Composer autoload se presente
                $composer_autoload = DISCO747_CRM_PLUGIN_DIR . 'vendor/autoload.php';
                if (file_exists($composer_autoload)) {
                    require_once $composer_autoload;
                } else {
                    error_log('[747Disco-Scan] PhpSpreadsheet non disponibile');
                    return false;
                }
            }
            
            // ✅ FIX: Carica il file Excel con gestione errori per file corrotti
            try {
                $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($file_path);
                $worksheet = $spreadsheet->getActiveSheet();
            } catch (\PhpOffice\PhpSpreadsheet\Exception $e) {
                error_log('[747Disco-Scan] File Excel corrotto o con hyperlink nulli: ' . $e->getMessage());
                throw new \Exception('File Excel corrotto: ' . $e->getMessage());
            }
            
            // ✅ Mapping secondo le specifiche richieste
            $data = array();
            
            // Nome file per estrarre info base
            $filename = $file_info['name'];
            
            // Parsing celle specifiche (specifiche richieste)
            $data['tipo_menu'] = $this->clean_cell_value($worksheet->getCell('B1')->getValue());
            $data['data_evento'] = $this->parse_date_from_cell($worksheet->getCell('C6')->getValue());
            
            // ✅ FIX CRITICO: Se data_evento è NULL, prova a estrarre dal filename
            if (empty($data['data_evento'])) {
                $data['data_evento'] = $this->extract_date_from_filename($filename);
                error_log('[747Disco-Scan] ⚠️ Data evento estratta da filename: ' . $data['data_evento']);
            }
            
            // ✅ ULTIMO FALLBACK: Se ancora NULL, usa data corrente
            if (empty($data['data_evento'])) {
                $data['data_evento'] = date('Y-m-d');
                error_log('[747Disco-Scan] ⚠️ Data evento fallback: ' . $data['data_evento']);
            }
            
            $data['tipo_evento'] = $this->clean_cell_value($worksheet->getCell('C7')->getValue());
            $data['orario_evento'] = $this->clean_cell_value($worksheet->getCell('C8')->getValue());
            $data['numero_invitati'] = $this->parse_number_from_cell($worksheet->getCell('C9')->getValue());
            
            // Cliente/Referente (specifiche richieste)
            $data['nome_referente'] = $this->clean_cell_value($worksheet->getCell('C11')->getValue());
            $data['cognome_referente'] = $this->clean_cell_value($worksheet->getCell('C12')->getValue());
            $data['telefono'] = $this->clean_cell_value($worksheet->getCell('C14')->getValue());
            $data['email'] = $this->clean_cell_value($worksheet->getCell('C15')->getValue());
            
            // Omaggi (specifiche richieste)
            $data['omaggio1'] = $this->clean_cell_value($worksheet->getCell('C17')->getValue());
            $data['omaggio2'] = $this->clean_cell_value($worksheet->getCell('C18')->getValue());
            $data['omaggio3'] = $this->clean_cell_value($worksheet->getCell('C19')->getValue());
            
            // Importi (specifiche richieste)
            $data['importo_totale'] = $this->parse_currency_from_cell($worksheet->getCell('F27')->getValue());
            $data['acconto'] = $this->parse_currency_from_cell($worksheet->getCell('F28')->getValue());
            $data['saldo'] = $this->parse_currency_from_cell($worksheet->getCell('F30')->getValue());
            
            // Extra a pagamento (specifiche richieste)
            $data['extra1'] = $this->clean_cell_value($worksheet->getCell('B33')->getValue());
            $data['extra1_importo'] = $this->parse_currency_from_cell($worksheet->getCell('F33')->getValue());
            $data['extra2'] = $this->clean_cell_value($worksheet->getCell('B34')->getValue());
            $data['extra2_importo'] = $this->parse_currency_from_cell($worksheet->getCell('F34')->getValue());
            $data['extra3'] = $this->clean_cell_value($worksheet->getCell('B35')->getValue());
            $data['extra3_importo'] = $this->parse_currency_from_cell($worksheet->getCell('F35')->getValue());
            
            // Metadati file
            $data['googledrive_file_id'] = $file_info['id'];
            $data['filename'] = $filename;
            $data['excel_url'] = "https://drive.google.com/file/d/{$file_info['id']}/view";
            $data['modified_time'] = $file_info['modifiedTime'];
            
            // Nome cliente combinato se mancante
            if (empty($data['nome_cliente'])) {
                $data['nome_cliente'] = trim($data['nome_referente'] . ' ' . $data['cognome_referente']);
            }
            
            // Calcola saldo se mancante
            if (empty($data['saldo'])) {
                $importo_totale = floatval($data['importo_totale']);
                $acconto = floatval($data['acconto']);
                $extra_totale = floatval($data['extra1_importo']) + floatval($data['extra2_importo']) + floatval($data['extra3_importo']);
                
                $data['importo_preventivo'] = $importo_totale + $extra_totale;
                $data['saldo'] = $data['importo_preventivo'] - $acconto;
            }
            
            // Stato basato su acconto
            $data['stato'] = floatval($data['acconto']) > 0 ? 'confermato' : 'attivo';
            
            // Determina prefisso dal filename per stato
            if (strpos($filename, 'CONF ') === 0) {
                $data['stato'] = 'confermato';
            } elseif (strpos($filename, 'NO ') === 0) {
                $data['stato'] = 'annullato';
            }
            
            error_log("[747Disco-Scan] Parsing completato: {$filename} - Evento: {$data['tipo_evento']}, Importo: €" . number_format($data['importo_totale'], 2));
            
            return $data;
            
        } catch (Exception $e) {
            error_log('[747Disco-Scan] Errore parsing PhpSpreadsheet: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * ✅ REALE: Salva nella tabella preventivi unificata
     */
    private function save_to_preventivi_table($data) {
        global $wpdb;
        
        try {
            // Prepara dati per inserimento nella tabella preventivi
            $table_data = array(
                'preventivo_id' => '', // Verrà generato automaticamente se necessario
                'data_evento' => $data['data_evento'],
                'tipo_evento' => $data['tipo_evento'],
                'tipo_menu' => $data['tipo_menu'],
                'numero_invitati' => $data['numero_invitati'],
                'orario_evento' => $data['orario_evento'],
                'nome_cliente' => $data['nome_cliente'],
                'nome_referente' => $data['nome_referente'],
                'cognome_referente' => $data['cognome_referente'],
                'telefono' => $data['telefono'],
                'email' => $data['email'],
                'importo_totale' => $data['importo_totale'],
                'importo_preventivo' => $data['importo_preventivo'] ?? $data['importo_totale'],
                'acconto' => $data['acconto'],
                'saldo' => $data['saldo'],
                'omaggio1' => $data['omaggio1'],
                'omaggio2' => $data['omaggio2'],
                'omaggio3' => $data['omaggio3'],
                'extra1' => $data['extra1'],
                'extra1_importo' => $data['extra1_importo'],
                'extra2' => $data['extra2'],
                'extra2_importo' => $data['extra2_importo'],
                'extra3' => $data['extra3'],
                'extra3_importo' => $data['extra3_importo'],
                'stato' => $data['stato'],
                'excel_url' => $data['excel_url'],
                'googledrive_file_id' => $data['googledrive_file_id'],
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql'),
                'created_by' => get_current_user_id()
            );
            
            // ✅ Check duplicati via googledrive_file_id
            if (!empty($data['googledrive_file_id'])) {
                $existing = $wpdb->get_var($wpdb->prepare(
                    "SELECT id FROM {$this->table_name} WHERE googledrive_file_id = %s",
                    $data['googledrive_file_id']
                ));
                
                if ($existing) {
                    // UPDATE record esistente
                    $result = $wpdb->update(
                        $this->table_name,
                        $table_data,
                        array('googledrive_file_id' => $data['googledrive_file_id']),
                        array('%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%f', '%f', '%f', '%f', '%s', '%s', '%s', '%s', '%f', '%s', '%f', '%s', '%f', '%s', '%s', '%s', '%s', '%s', '%d'),
                        array('%s')
                    );
                    
                    if ($result !== false) {
                        error_log("[747Disco-Scan] Preventivo aggiornato ID: {$existing}");
                        return $existing;
                    } else {
                        error_log("[747Disco-Scan] Errore UPDATE: " . $wpdb->last_error);
                        return false;
                    }
                }
            }
            
            // ✅ INSERT nuovo preventivo
            $result = $wpdb->insert(
                $this->table_name,
                $table_data,
                array('%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%f', '%f', '%f', '%f', '%s', '%s', '%s', '%s', '%f', '%s', '%f', '%s', '%f', '%s', '%s', '%s', '%s', '%s', '%d')
            );
            
            if ($result === false) {
                error_log("[747Disco-Scan] Errore INSERT: " . $wpdb->last_error);
                return false;
            }
            
            $insert_id = $wpdb->insert_id;
            
            // Genera preventivo_id se mancante
            if (empty($table_data['preventivo_id'])) {
                $preventivo_id = sprintf('#%03d', $insert_id);
                $wpdb->update(
                    $this->table_name,
                    array('preventivo_id' => $preventivo_id),
                    array('id' => $insert_id),
                    array('%s'),
                    array('%d')
                );
            }
            
            error_log("[747Disco-Scan] Preventivo salvato con ID: {$insert_id}");
            
            return $insert_id;
            
        } catch (Exception $e) {
            error_log('[747Disco-Scan] Errore salvataggio preventivo: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Helper: Pulisce valore cella
     */
    private function clean_cell_value($value) {
        if ($value === null) return '';
        return trim(strval($value));
    }
    
    /**
     * Helper: Parsing data da cella
     * ✅ AGGIORNATO: Supporta formato italiano dd/mm/yyyy
     */
    private function parse_date_from_cell($value) {
        if (empty($value)) return null;
        
        try {
            // Se è numerico = Excel date serial
            if (is_numeric($value)) {
                $unix_date = ($value - 25569) * 86400;
                return date('Y-m-d', $unix_date);
            }
            
            // Se è stringa, gestisci formati italiani
            $value = trim($value);
            
            // ✅ Pattern formato italiano: dd/mm/yyyy o dd-mm-yyyy o dd.mm.yyyy
            if (preg_match('/^(\d{1,2})[\/\-\.](\d{1,2})[\/\-\.](\d{4})$/', $value, $matches)) {
                $day = intval($matches[1]);
                $month = intval($matches[2]);
                $year = intval($matches[3]);
                
                // Valida la data
                if (checkdate($month, $day, $year)) {
                    return sprintf('%04d-%02d-%02d', $year, $month, $day);
                } else {
                    error_log("[747Disco-Scan] ⚠️ Data non valida: {$value} (giorno={$day}, mese={$month}, anno={$year})");
                    return null;
                }
            }
            
            // ✅ Pattern formato italiano testuale: "sabato 13 dicembre 2025"
            if (preg_match('/(\d{1,2})\s+(gennaio|febbraio|marzo|aprile|maggio|giugno|luglio|agosto|settembre|ottobre|novembre|dicembre)\s+(\d{4})/i', $value, $matches)) {
                $day = intval($matches[1]);
                $month_name = strtolower($matches[2]);
                $year = intval($matches[3]);
                
                // Mappa mesi italiani
                $mesi = array(
                    'gennaio' => 1, 'febbraio' => 2, 'marzo' => 3, 'aprile' => 4,
                    'maggio' => 5, 'giugno' => 6, 'luglio' => 7, 'agosto' => 8,
                    'settembre' => 9, 'ottobre' => 10, 'novembre' => 11, 'dicembre' => 12
                );
                
                if (isset($mesi[$month_name])) {
                    $month = $mesi[$month_name];
                    if (checkdate($month, $day, $year)) {
                        return sprintf('%04d-%02d-%02d', $year, $month, $day);
                    }
                }
            }
            
            // ✅ Fallback: prova strtotime() per altri formati
            $timestamp = strtotime($value);
            if ($timestamp) {
                return date('Y-m-d', $timestamp);
            }
            
            error_log("[747Disco-Scan] ⚠️ Impossibile parsare data: {$value}");
            return null;
            
        } catch (\Exception $e) {
            error_log("[747Disco-Scan] ❌ Errore parsing data: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Helper: Parsing numero da cella
     */
    private function parse_number_from_cell($value) {
        if (empty($value)) return 0;
        return intval($value);
    }
    
    /**
     * Helper: Parsing valuta da cella
     */
    private function parse_currency_from_cell($value) {
        if (empty($value)) return 0.00;
        
        // Rimuovi simboli valuta e converti
        $cleaned = preg_replace('/[€$£,]/', '', strval($value));
        return floatval($cleaned);
    }
    
    /**
     * ✅ NUOVO: Estrae data evento dal nome file
     * Formati supportati:
     * - "13_11 18 Anni di..." -> "2025-11-13"
     * - "CONF 13_11 18 Anni..." -> "2025-11-13"
     * - "14_12 Festa..." -> "2025-12-14"
     * 
     * @param string $filename Nome file
     * @return string|null Data in formato Y-m-d
     */
    private function extract_date_from_filename($filename) {
        try {
            // Pattern: cattura DD_MM all'inizio o dopo prefisso CONF/NO
            if (preg_match('/(CONF |NO )?(\d{1,2})_(\d{1,2})/', $filename, $matches)) {
                $day = intval($matches[2]);
                $month = intval($matches[3]);
                
                // Anno corrente o prossimo anno se mese già passato
                $year = date('Y');
                $current_month = intval(date('m'));
                
                // Se il mese è minore del mese corrente, probabile che sia anno prossimo
                if ($month < $current_month) {
                    $year++;
                }
                
                // Valida data
                if (checkdate($month, $day, $year)) {
                    return sprintf('%04d-%02d-%02d', $year, $month, $day);
                }
            }
            
            return null;
            
        } catch (\Exception $e) {
            error_log('[747Disco-Scan] Errore estrazione data da filename: ' . $e->getMessage());
            return null;
        }
    }
}

// Inizializza l'handler
new Disco747_Excel_Scan_Handler();