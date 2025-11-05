<?php
/**
 * AJAX Handlers per 747 Disco CRM
 * Gestisce tutte le chiamate AJAX del plugin
 * 
 * @package    Disco747_CRM
 * @subpackage Admin
 * @since      11.8.9-RESET-AND-SCAN
 */

namespace Disco747_CRM\Admin;

if (!defined('ABSPATH')) {
    exit;
}

class Disco747_AJAX_Handlers {

    /**
     * Inizializza gli handler AJAX
     */
    public static function init() {
        // Batch scan Excel
        add_action('wp_ajax_batch_scan_excel', array(__CLASS__, 'handle_batch_scan'));
        add_action('wp_ajax_disco747_scan_drive_batch', array(__CLASS__, 'handle_batch_scan')); // Alias
        
        // Reset e Scan
        add_action('wp_ajax_reset_and_scan_excel', array(__CLASS__, 'handle_reset_and_scan'));
        
        // Altri handler
        add_action('wp_ajax_analyze_excel_file', array(__CLASS__, 'handle_analyze_file'));
        
        // ✅ NUOVO: Diagnostica date Excel
        add_action('wp_ajax_disco747_diagnostic_excel_dates', array(__CLASS__, 'handle_diagnostic_excel_dates'));
        
        // ✅ NUOVO: Debug struttura Excel
        add_action('wp_ajax_disco747_get_excel_files_list', array(__CLASS__, 'handle_get_excel_files_list'));
        add_action('wp_ajax_disco747_analyze_excel_structure', array(__CLASS__, 'handle_analyze_excel_structure'));
        
        error_log('[Excel-Scan-AJAX] Hook AJAX registrati: batch_scan_excel, reset_and_scan_excel, analyze_excel_file, diagnostic_excel_dates, debug_structure');
    }

    /**
     * Handler principale per batch scan
     */
    public static function handle_batch_scan() {
        // ✅ Aumenta timeout PHP per scansioni lunghe (usa config centralizzata)
        if (function_exists('disco747_set_scan_timeout')) {
            disco747_set_scan_timeout();
        } else {
            @set_time_limit(900);
            @ini_set('max_execution_time', 900);
        }
        
        error_log('[Batch-Scan-AJAX] ========== INIZIO BATCH SCAN (timeout: 15min) ==========');
        
        // Verifica nonce
        if (!isset($_POST['nonce']) && !isset($_POST['_wpnonce'])) {
            error_log('[Batch-Scan-AJAX] ERRORE: Nonce mancante');
            wp_send_json_error(array('message' => 'Nonce mancante'));
            return;
        }

        $nonce = isset($_POST['nonce']) ? $_POST['nonce'] : $_POST['_wpnonce'];
        
        if (!wp_verify_nonce($nonce, 'disco747_batch_scan')) {
            error_log('[Batch-Scan-AJAX] ERRORE: Nonce non valido');
            wp_send_json_error(array('message' => 'Verifica nonce fallita'));
            return;
        }

        error_log('[Batch-Scan-AJAX] ✅ Nonce verificato');

        // Verifica permessi
        if (!current_user_can('manage_options')) {
            error_log('[Batch-Scan-AJAX] ERRORE: Permessi insufficienti');
            wp_send_json_error(array('message' => 'Permessi insufficienti'));
            return;
        }

        error_log('[Batch-Scan-AJAX] ✅ Permessi verificati');

        // Parametri
        $year = isset($_POST['year']) ? sanitize_text_field($_POST['year']) : date('Y');
        $month = isset($_POST['month']) ? sanitize_text_field($_POST['month']) : '';

        error_log("[Batch-Scan-AJAX] Parametri: anno={$year}, mese={$month}");

        try {
            // Carica le classi necessarie
            $plugin = disco747_crm();
            
            if (!$plugin || !$plugin->is_initialized()) {
                error_log('[Batch-Scan-AJAX] ERRORE: Plugin non inizializzato');
                wp_send_json_error(array('message' => 'Plugin non inizializzato'));
                return;
            }

            $storage_manager = $plugin->get_storage_manager();
            $database = $plugin->get_database();

            if (!$storage_manager || !$database) {
                error_log('[Batch-Scan-AJAX] ERRORE: Componenti mancanti');
                wp_send_json_error(array('message' => 'Componenti sistema mancanti'));
                return;
            }

            error_log('[Batch-Scan-AJAX] ✅ Componenti caricati');

            // Ottieni handler storage attivo
            $handler = $storage_manager->get_active_handler();
            
            if (!$handler) {
                error_log('[Batch-Scan-AJAX] ERRORE: Handler storage non disponibile');
                wp_send_json_error(array('message' => 'Storage non configurato'));
                return;
            }

            error_log('[Batch-Scan-AJAX] ✅ Handler storage attivo: ' . get_class($handler));

            // Determina i mesi da scansionare
            $months_to_scan = array();
            if (empty($month)) {
                // Tutti i mesi
                $months_to_scan = array(
                    'GENNAIO', 'FEBBRAIO', 'MARZO', 'APRILE', 
                    'MAGGIO', 'GIUGNO', 'LUGLIO', 'AGOSTO',
                    'SETTEMBRE', 'OTTOBRE', 'NOVEMBRE', 'DICEMBRE'
                );
            } else {
                $months_to_scan = array($month);
            }

            error_log('[Batch-Scan-AJAX] Mesi da scansionare: ' . implode(', ', $months_to_scan));

            // Raccogli tutti i file
            $all_files = array();
            
            foreach ($months_to_scan as $current_month) {
                $folder_path = "747-Preventivi/{$year}/{$current_month}/";
                error_log("[Batch-Scan-AJAX] Scansione cartella: {$folder_path}");
                
                try {
                    $files = $handler->list_files($folder_path, '*.xlsx');
                    
                    if (!empty($files)) {
                        error_log("[Batch-Scan-AJAX] Trovati " . count($files) . " file in {$current_month}");
                        $all_files = array_merge($all_files, $files);
                    } else {
                        error_log("[Batch-Scan-AJAX] Nessun file in {$current_month}");
                    }
                } catch (\Exception $e) {
                    error_log("[Batch-Scan-AJAX] Errore scansione {$current_month}: " . $e->getMessage());
                }
            }

            $total_files = count($all_files);
            error_log("[Batch-Scan-AJAX] ⭐ TOTALE FILE TROVATI: {$total_files}");

            if ($total_files === 0) {
                wp_send_json_success(array(
                    'complete' => true,
                    'total_files' => 0,
                    'processed' => 0,
                    'new_records' => 0,
                    'updated_records' => 0,
                    'errors' => 0,
                    'new_files_list' => array(),
                    'message' => 'Nessun file trovato nelle cartelle specificate'
                ));
                return;
            }

            // Processa tutti i file
            $new_records = 0;
            $updated_records = 0;
            $errors = 0;
            $error_details = array();

            foreach ($all_files as $file) {
                try {
                    $file_name = $file['name'];
                    error_log("[Batch-Scan-AJAX] Processamento file: {$file_name}");
                    
                    // Parse filename
                    $parsed = self::parse_filename($file_name);
                    
                    if (!$parsed) {
                        error_log("[Batch-Scan-AJAX] ⚠️ Impossibile parsare: {$file_name}");
                        $errors++;
                        $error_details[] = array(
                            'file' => $file_name,
                            'error' => 'Formato filename non valido'
                        );
                        continue;
                    }

                    // Verifica se esiste già
                    global $wpdb;
                    $table = $wpdb->prefix . 'disco747_preventivi';
                    
                    $existing = $wpdb->get_var($wpdb->prepare(
                        "SELECT id FROM {$table} WHERE data_evento = %s AND tipo_evento = %s AND tipo_menu = %s",
                        $parsed['data_evento'],
                        $parsed['tipo_evento'],
                        $parsed['tipo_menu']
                    ));

                    if ($existing) {
                        // Aggiorna esistente
                        $wpdb->update(
                            $table,
                            array(
                                'stato' => $parsed['stato'],
                                'file_path' => $file['path'],
                                'updated_at' => current_time('mysql')
                            ),
                            array('id' => $existing),
                            array('%s', '%s', '%s'),
                            array('%d')
                        );
                        
                        $updated_records++;
                        error_log("[Batch-Scan-AJAX] ✅ Record aggiornato: ID {$existing}");
                    } else {
                        // Inserisci nuovo
                        $inserted = $wpdb->insert(
                            $table,
                            array(
                                'data_evento' => $parsed['data_evento'],
                                'tipo_evento' => $parsed['tipo_evento'],
                                'tipo_menu' => $parsed['tipo_menu'],
                                'stato' => $parsed['stato'],
                                'file_path' => $file['path'],
                                'created_at' => current_time('mysql'),
                                'updated_at' => current_time('mysql')
                            ),
                            array('%s', '%s', '%s', '%s', '%s', '%s', '%s')
                        );

                        if ($inserted) {
                            $new_records++;
                            error_log("[Batch-Scan-AJAX] ✅ Nuovo record inserito: ID {$wpdb->insert_id}");
                        } else {
                            $errors++;
                            error_log("[Batch-Scan-AJAX] ❌ Errore inserimento: " . $wpdb->last_error);
                        }
                    }

                } catch (\Exception $e) {
                    $errors++;
                    $error_details[] = array(
                        'file' => $file['name'],
                        'error' => $e->getMessage()
                    );
                    error_log("[Batch-Scan-AJAX] ❌ Errore file {$file['name']}: " . $e->getMessage());
                }
            }

            error_log("[Batch-Scan-AJAX] ========== RIEPILOGO ==========");
            error_log("[Batch-Scan-AJAX] Totale file: {$total_files}");
            error_log("[Batch-Scan-AJAX] Nuovi: {$new_records}");
            error_log("[Batch-Scan-AJAX] Aggiornati: {$updated_records}");
            error_log("[Batch-Scan-AJAX] Errori: {$errors}");

            // ⭐ PREPARA LISTA FILE PROCESSATI (nuovi + aggiornati)
            $new_files_list = array();
            $total_to_show = $new_records + $updated_records;

            if ($total_to_show > 0) {
                global $wpdb;
                $table = $wpdb->prefix . 'disco747_preventivi';
                
                // Prendi gli ultimi N record modificati (updated_at DESC)
                $new_files = $wpdb->get_results(
                    $wpdb->prepare(
                        "SELECT data_evento, tipo_evento, tipo_menu, stato, file_path, updated_at 
                         FROM {$table} 
                         ORDER BY updated_at DESC 
                         LIMIT %d",
                        $total_to_show
                    ),
                    ARRAY_A
                );
                
                error_log("[Batch-Scan-AJAX] Query file processati restituisce: " . count($new_files) . " record");
                
                if ($new_files) {
                    foreach ($new_files as $file) {
                        // Formatta data
                        $data_formattata = '-';
                        if (!empty($file['data_evento']) && $file['data_evento'] !== '0000-00-00') {
                            try {
                                $data_formattata = date('d/m/Y', strtotime($file['data_evento']));
                            } catch (\Exception $e) {
                                $data_formattata = $file['data_evento'];
                            }
                        }
                        
                        // Estrai nome file dal path
                        $filename = '-';
                        if (!empty($file['file_path'])) {
                            $filename = basename($file['file_path']);
                        }
                        
                        $new_files_list[] = array(
                            'data_evento' => $data_formattata,
                            'tipo_evento' => $file['tipo_evento'] ?: '-',
                            'tipo_menu' => $file['tipo_menu'] ?: '-',
                            'stato' => ucfirst($file['stato'] ?: 'attivo'),
                            'filename' => $filename
                        );
                    }
                    
                    error_log("[Batch-Scan-AJAX] ✅ Preparati " . count($new_files_list) . " file per la tabella");
                }
            }

            // Risposta JSON
            wp_send_json_success(array(
                'complete' => true,
                'total_files' => $total_files,
                'processed' => $total_files,
                'new_records' => $new_records,
                'updated_records' => $updated_records,
                'errors' => $errors,
                'error_details' => array_slice($error_details, 0, 10),
                'new_files_list' => $new_files_list,
                'progress_percent' => 100,
                'message' => "Scansione completata: {$new_records} nuovi, {$updated_records} aggiornati, {$errors} errori"
            ));

        } catch (\Exception $e) {
            error_log('[Batch-Scan-AJAX] ❌ ERRORE FATALE: ' . $e->getMessage());
            wp_send_json_error(array(
                'message' => 'Errore durante la scansione: ' . $e->getMessage()
            ));
        }
    }

    /**
     * Handler per svuotare database e rianalizzare
     */
    public static function handle_reset_and_scan() {
        error_log('[Reset-Scan-AJAX] ========== INIZIO RESET & SCAN ==========');
        
        // Verifica nonce
        if (!isset($_POST['nonce']) && !isset($_POST['_wpnonce'])) {
            wp_send_json_error(array('message' => 'Nonce mancante'));
            return;
        }

        $nonce = isset($_POST['nonce']) ? $_POST['nonce'] : $_POST['_wpnonce'];
        
        if (!wp_verify_nonce($nonce, 'disco747_batch_scan')) {
            wp_send_json_error(array('message' => 'Verifica nonce fallita'));
            return;
        }

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permessi insufficienti'));
            return;
        }

        error_log('[Reset-Scan-AJAX] ✅ Nonce e permessi verificati');

        try {
            // SVUOTA TABELLA
            global $wpdb;
            $table = $wpdb->prefix . 'disco747_preventivi';
            $deleted = $wpdb->query("TRUNCATE TABLE {$table}");
            
            error_log('[Reset-Scan-AJAX] ✅ Tabella svuotata (record eliminati: ' . ($deleted !== false ? 'OK' : 'ERRORE') . ')');

            // Ora esegui la scansione normale (riutilizza la logica esistente)
            self::handle_batch_scan();

        } catch (\Exception $e) {
            error_log('[Reset-Scan-AJAX] ❌ ERRORE: ' . $e->getMessage());
            wp_send_json_error(array(
                'message' => 'Errore durante reset: ' . $e->getMessage()
            ));
        }
    }

    /**
     * Parse filename Excel per estrarre informazioni
     * 
     * Esempi supportati:
     * - "Conf 03_09 18 Anni di Tommaso (Menu 7).xlsx"
     * - "No 02_09 18 anni di Luca (Menu 7).xlsx"
     * - "22_09 Evento (Menu 7).xlsx"
     */
    private static function parse_filename($filename) {
        // Rimuovi estensione
        $name = pathinfo($filename, PATHINFO_FILENAME);
        
        error_log("[Parse-Filename] Parsing: {$name}");
        
        // Pattern regex migliorato
        $pattern = '/^(?:(Conf|No)\s+)?(\d{1,2})_(\d{1,2})\s+(.+?)\s+\(Menu\s+([\d\-]+)\)$/i';
        
        if (preg_match($pattern, $name, $matches)) {
            $stato_prefix = strtolower($matches[1] ?: '');
            $giorno = str_pad($matches[2], 2, '0', STR_PAD_LEFT);
            $mese = str_pad($matches[3], 2, '0', STR_PAD_LEFT);
            $tipo_evento = trim($matches[4]);
            $tipo_menu = 'Menu ' . $matches[5];
            
            // Determina stato
            $stato = 'attivo';
            if ($stato_prefix === 'conf') {
                $stato = 'confermato';
            } elseif ($stato_prefix === 'no') {
                $stato = 'annullato';
            }
            
            // Determina anno (usa anno corrente + 1 per date future)
            $anno_corrente = (int) date('Y');
            $mese_corrente = (int) date('m');
            
            // Se il mese è passato, usa anno prossimo
            if ((int)$mese < $mese_corrente) {
                $anno = $anno_corrente + 1;
            } else {
                $anno = $anno_corrente;
            }
            
            $data_evento = "{$anno}-{$mese}-{$giorno}";
            
            $result = array(
                'data_evento' => $data_evento,
                'tipo_evento' => $tipo_evento,
                'tipo_menu' => $tipo_menu,
                'stato' => $stato
            );
            
            error_log("[Parse-Filename] ✅ Parsed: " . json_encode($result));
            
            return $result;
        }
        
        error_log("[Parse-Filename] ❌ Pattern non riconosciuto");
        return false;
    }

    /**
     * Handler per analisi singolo file
     */
    public static function handle_analyze_file() {
        // Verifica nonce
        check_ajax_referer('disco747_excel_scan', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permessi insufficienti'));
            return;
        }

        $file_id = isset($_POST['file_id']) ? sanitize_text_field($_POST['file_id']) : '';

        if (empty($file_id)) {
            wp_send_json_error(array('message' => 'File ID mancante'));
            return;
        }

        try {
            // Implementa logica analisi singolo file
            wp_send_json_success(array(
                'message' => 'Analisi file non ancora implementata',
                'file_id' => $file_id
            ));

        } catch (\Exception $e) {
            wp_send_json_error(array(
                'message' => 'Errore analisi file: ' . $e->getMessage()
            ));
        }
    }
    
    /**
     * ✅ NUOVO: Handler diagnostica date Excel
     * Analizza tutti i file Excel e verifica quali hanno la cella C6 (data_evento) vuota
     */
    public static function handle_diagnostic_excel_dates() {
        error_log('[Diagnostic-AJAX] ========== AVVIO DIAGNOSTICA DATE EXCEL ==========');
        
        // Verifica nonce
        if (!isset($_POST['nonce'])) {
            wp_send_json_error(array('message' => 'Nonce mancante'));
            return;
        }
        
        if (!wp_verify_nonce($_POST['nonce'], 'disco747_diagnostic')) {
            wp_send_json_error(array('message' => 'Nonce non valido'));
            return;
        }
        
        // Verifica permessi
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permessi insufficienti'));
            return;
        }
        
        try {
            // Usa Excel Scan Handler per trovare file
            $scan_handler = new \Disco747_CRM\Handlers\Disco747_Excel_Scan_Handler();
            
            // Ottieni lista file da Google Drive (riutilizza logica esistente)
            $plugin = disco747_crm();
            $storage_manager = $plugin->get_storage_manager();
            $googledrive = $storage_manager->get_active_handler();
            
            if (!$googledrive) {
                wp_send_json_error(array('message' => 'Google Drive non disponibile'));
                return;
            }
            
            // Trova cartella principale e file
            $main_folder_id = self::find_main_folder_diagnostic($googledrive);
            if (!$main_folder_id) {
                wp_send_json_error(array('message' => 'Cartella 747-Preventivi non trovata'));
                return;
            }
            
            // Scansiona file Excel (anno 2025, tutti i mesi)
            $excel_files = self::scan_files_with_metadata($googledrive, $main_folder_id, '2025');
            
            error_log('[Diagnostic-AJAX] Trovati ' . count($excel_files) . ' file Excel');
            
            // Analizza ogni file
            $results = array();
            $limit = 10; // Limite per test iniziale
            $count = 0;
            
            foreach ($excel_files as $file) {
                if ($count >= $limit) break; // Limite per performance
                
                $result = self::analyze_single_file_date($googledrive, $file);
                $results[] = $result;
                $count++;
                
                // Rate limiting
                usleep(300000); // 300ms
            }
            
            error_log('[Diagnostic-AJAX] Analizzati ' . count($results) . ' file');
            
            wp_send_json_success(array(
                'files' => $results,
                'total' => count($excel_files),
                'analyzed' => count($results)
            ));
            
        } catch (\Exception $e) {
            error_log('[Diagnostic-AJAX] ERRORE: ' . $e->getMessage());
            wp_send_json_error(array('message' => 'Errore: ' . $e->getMessage()));
        }
    }
    
    /**
     * Trova cartella principale per diagnostica
     */
    private static function find_main_folder_diagnostic($googledrive) {
        // ✅ USA il metodo pubblico di GoogleDrive per ottenere token refreshato
        $token = self::get_refreshed_token($googledrive);
        if (empty($token)) {
            error_log('[Debug-Tool] Token non disponibile');
            return null;
        }
        
        $query = "name='747-Preventivi' and mimeType='application/vnd.google-apps.folder' and trashed=false";
        $url = 'https://www.googleapis.com/drive/v3/files?' . http_build_query(array(
            'q' => $query,
            'fields' => 'files(id, name)',
            'pageSize' => 1
        ));
        
        $response = wp_remote_get($url, array(
            'headers' => array('Authorization' => 'Bearer ' . $token),
            'timeout' => 120 // ✅ 2 minuti per richieste Google Drive API
        ));
        
        if (is_wp_error($response)) {
            error_log('[Debug-Tool] Errore API: ' . $response->get_error_message());
            return null;
        }
        
        $http_code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        error_log('[Debug-Tool] Risposta API (HTTP ' . $http_code . '): ' . json_encode($body));
        
        return !empty($body['files']) ? $body['files'][0]['id'] : null;
    }
    
    /**
     * ✅ NUOVO: Ottiene token refreshato usando GoogleDrive handler
     */
    private static function get_refreshed_token($googledrive) {
        try {
            // Verifica se token è scaduto
            $access_token = get_option('disco747_googledrive_access_token', '');
            $expires = get_option('disco747_googledrive_token_expires', 0);
            
            // Se valido, usa quello
            if (!empty($access_token) && time() < ($expires - 300)) {
                error_log('[Debug-Tool] Token valido, scade in ' . ($expires - time()) . 's');
                return $access_token;
            }
            
            error_log('[Debug-Tool] Token scaduto o mancante, refresh necessario');
            
            // Altrimenti refresha
            $credentials = $googledrive->get_oauth_credentials();
            
            if (empty($credentials['refresh_token'])) {
                error_log('[Debug-Tool] Refresh token mancante');
                return null;
            }
            
            $response = wp_remote_post('https://oauth2.googleapis.com/token', array(
                'body' => array(
                    'client_id' => $credentials['client_id'],
                    'client_secret' => $credentials['client_secret'],
                    'refresh_token' => $credentials['refresh_token'],
                    'grant_type' => 'refresh_token'
                ),
                'timeout' => 120 // ✅ 2 minuti per richieste Google Drive API
            ));
            
            if (is_wp_error($response)) {
                error_log('[Debug-Tool] Errore refresh: ' . $response->get_error_message());
                return null;
            }
            
            $body = json_decode(wp_remote_retrieve_body($response), true);
            $http_code = wp_remote_retrieve_response_code($response);
            
            if ($http_code !== 200 || !isset($body['access_token'])) {
                error_log('[Debug-Tool] Refresh fallito (HTTP ' . $http_code . ')');
                return null;
            }
            
            // Salva nuovo token
            $new_token = $body['access_token'];
            $expires_in = $body['expires_in'] ?? 3600;
            
            update_option('disco747_googledrive_access_token', $new_token);
            update_option('disco747_googledrive_token_expires', time() + $expires_in);
            
            error_log('[Debug-Tool] ✅ Token refreshato con successo');
            
            return $new_token;
            
        } catch (\Exception $e) {
            error_log('[Debug-Tool] Errore get_refreshed_token: ' . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Scansiona file con metadati cartelle
     */
    private static function scan_files_with_metadata($googledrive, $folder_id, $year) {
        $files = array();
        $token = self::get_refreshed_token($googledrive); // ✅ USA token refreshato
        
        // Trova cartella anno
        $year_folder_id = self::find_subfolder($token, $folder_id, $year);
        if (!$year_folder_id) return $files;
        
        // Trova cartelle mese
        $month_folders = self::get_subfolders($token, $year_folder_id);
        
        foreach ($month_folders as $month_folder) {
            // Trova file Excel nella cartella mese
            $query = "(mimeType='application/vnd.openxmlformats-officedocument.spreadsheetml.sheet') and trashed=false and '{$month_folder['id']}' in parents";
            $url = 'https://www.googleapis.com/drive/v3/files?' . http_build_query(array(
                'q' => $query,
                'fields' => 'files(id, name)',
                'pageSize' => 100
            ));
            
            $response = wp_remote_get($url, array(
                'headers' => array('Authorization' => 'Bearer ' . $token),
                'timeout' => 120 // ✅ 2 minuti per richieste Google Drive API
            ));
            
            if (!is_wp_error($response)) {
                $body = json_decode(wp_remote_retrieve_body($response), true);
                foreach ($body['files'] ?? [] as $file) {
                    $files[] = array(
                        'id' => $file['id'],
                        'name' => $file['name'],
                        'year_folder' => $year,
                        'month_folder' => $month_folder['name']
                    );
                }
            }
        }
        
        return $files;
    }
    
    /**
     * Trova sottocartella
     */
    private static function find_subfolder($token, $parent_id, $name) {
        $query = "name='{$name}' and mimeType='application/vnd.google-apps.folder' and trashed=false and '{$parent_id}' in parents";
        $url = 'https://www.googleapis.com/drive/v3/files?' . http_build_query(array(
            'q' => $query,
            'fields' => 'files(id)',
            'pageSize' => 1
        ));
        
        $response = wp_remote_get($url, array(
            'headers' => array('Authorization' => 'Bearer ' . $token),
            'timeout' => 120 // ✅ 2 minuti per richieste Google Drive API
        ));
        
        if (is_wp_error($response)) return null;
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        return !empty($body['files']) ? $body['files'][0]['id'] : null;
    }
    
    /**
     * Ottieni sottocartelle
     */
    private static function get_subfolders($token, $parent_id) {
        $query = "mimeType='application/vnd.google-apps.folder' and trashed=false and '{$parent_id}' in parents";
        $url = 'https://www.googleapis.com/drive/v3/files?' . http_build_query(array(
            'q' => $query,
            'fields' => 'files(id, name)',
            'pageSize' => 50
        ));
        
        $response = wp_remote_get($url, array(
            'headers' => array('Authorization' => 'Bearer ' . $token),
            'timeout' => 120 // ✅ 2 minuti per richieste Google Drive API
        ));
        
        if (is_wp_error($response)) return array();
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        return $body['files'] ?? array();
    }
    
    /**
     * Analizza singolo file per data evento
     */
    private static function analyze_single_file_date($googledrive, $file_info) {
        $result = array(
            'name' => $file_info['name'],
            'year_folder' => $file_info['year_folder'],
            'month_folder' => $file_info['month_folder'],
            'date_value' => null,
            'status' => 'error'
        );
        
        try {
            // Download temporaneo
            $upload_dir = wp_upload_dir();
            $temp_dir = $upload_dir['basedir'] . '/preventivi/temp/';
            
            if (!is_dir($temp_dir)) {
                wp_mkdir_p($temp_dir);
            }
            
            $temp_file = $temp_dir . 'diagnostic_' . $file_info['id'] . '.xlsx';
            
            // Download file
            $download_result = $googledrive->download_file($file_info['id'], $temp_file);
            
            if (!$download_result['success'] || !file_exists($temp_file)) {
                $result['status'] = 'error';
                $result['date_value'] = 'Download fallito';
                return $result;
            }
            
            // Carica con PhpSpreadsheet
            if (!class_exists('PhpOffice\\PhpSpreadsheet\\IOFactory')) {
                $composer_autoload = DISCO747_CRM_PLUGIN_DIR . 'vendor/autoload.php';
                if (file_exists($composer_autoload)) {
                    require_once $composer_autoload;
                }
            }
            
            try {
                $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($temp_file);
                $worksheet = $spreadsheet->getActiveSheet();
                
                // Leggi cella C6 (data evento)
                $cell_value = $worksheet->getCell('C6')->getValue();
                
                if (empty($cell_value)) {
                    $result['date_value'] = 'NULL';
                    $result['status'] = 'empty';
                } else {
                    // Parsing data
                    if (is_numeric($cell_value)) {
                        $unix_date = ($cell_value - 25569) * 86400;
                        $result['date_value'] = date('Y-m-d', $unix_date);
                    } else {
                        $result['date_value'] = $cell_value;
                    }
                    $result['status'] = 'ok';
                }
                
            } catch (\Exception $e) {
                $result['date_value'] = 'Errore lettura: ' . $e->getMessage();
                $result['status'] = 'error';
            }
            
            // Pulizia
            if (file_exists($temp_file)) {
                unlink($temp_file);
            }
            
        } catch (\Exception $e) {
            $result['date_value'] = 'Errore: ' . $e->getMessage();
            $result['status'] = 'error';
        }
        
        return $result;
    }
    
    /**
     * ✅ NUOVO: Ottieni lista file Excel per debug
     */
    public static function handle_get_excel_files_list() {
        // Verifica nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'disco747_debug')) {
            wp_send_json_error(array('message' => 'Nonce non valido'));
            return;
        }
        
        // Verifica permessi
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permessi insufficienti'));
            return;
        }
        
        try {
            $plugin = disco747_crm();
            $storage_manager = $plugin->get_storage_manager();
            $googledrive = $storage_manager->get_active_handler();
            
            if (!$googledrive) {
                wp_send_json_error(array('message' => 'Google Drive non disponibile'));
                return;
            }
            
            // Trova cartella principale
            $main_folder_id = self::find_main_folder_diagnostic($googledrive);
            if (!$main_folder_id) {
                wp_send_json_error(array('message' => 'Cartella 747-Preventivi non trovata'));
                return;
            }
            
            // Scansiona file Excel
            $files = self::scan_files_with_metadata($googledrive, $main_folder_id, '2025');
            
            wp_send_json_success(array(
                'files' => $files,
                'total' => count($files)
            ));
            
        } catch (\Exception $e) {
            wp_send_json_error(array('message' => 'Errore: ' . $e->getMessage()));
        }
    }
    
    /**
     * ✅ NUOVO: Analizza struttura Excel completa
     */
    public static function handle_analyze_excel_structure() {
        error_log('[Debug-Structure] ========== ANALISI STRUTTURA EXCEL ==========');
        
        // Verifica nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'disco747_debug')) {
            wp_send_json_error(array('message' => 'Nonce non valido'));
            return;
        }
        
        // Verifica permessi
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permessi insufficienti'));
            return;
        }
        
        $file_id = isset($_POST['file_id']) ? sanitize_text_field($_POST['file_id']) : '';
        
        if (empty($file_id)) {
            wp_send_json_error(array('message' => 'File ID mancante'));
            return;
        }
        
        try {
            $plugin = disco747_crm();
            $storage_manager = $plugin->get_storage_manager();
            $googledrive = $storage_manager->get_active_handler();
            
            if (!$googledrive) {
                wp_send_json_error(array('message' => 'Google Drive non disponibile'));
                return;
            }
            
            // Download file temporaneo
            $upload_dir = wp_upload_dir();
            $temp_dir = $upload_dir['basedir'] . '/preventivi/temp/';
            
            if (!is_dir($temp_dir)) {
                wp_mkdir_p($temp_dir);
            }
            
            $temp_file = $temp_dir . 'debug_structure_' . $file_id . '.xlsx';
            
            error_log('[Debug-Structure] Download file: ' . $file_id);
            
            $download_result = $googledrive->download_file($file_id, $temp_file);
            
            if (!$download_result['success'] || !file_exists($temp_file)) {
                wp_send_json_error(array('message' => 'Download fallito'));
                return;
            }
            
            error_log('[Debug-Structure] File scaricato: ' . $temp_file);
            
            // Analizza con PhpSpreadsheet
            if (!class_exists('PhpOffice\\PhpSpreadsheet\\IOFactory')) {
                $composer_autoload = DISCO747_CRM_PLUGIN_DIR . 'vendor/autoload.php';
                if (file_exists($composer_autoload)) {
                    require_once $composer_autoload;
                }
            }
            
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($temp_file);
            
            // Info fogli
            $sheets = array();
            foreach ($spreadsheet->getAllSheets() as $sheet) {
                $sheets[] = $sheet->getTitle();
            }
            
            $active_sheet = $spreadsheet->getActiveSheet();
            $active_sheet_name = $active_sheet->getTitle();
            
            error_log('[Debug-Structure] Foglio attivo: ' . $active_sheet_name);
            
            // Estrai tutte le celle (prime 30 righe, colonne A-J)
            $cells = array();
            $cols = range('A', 'J');
            
            for ($row = 1; $row <= 30; $row++) {
                foreach ($cols as $col) {
                    $cellRef = $col . $row;
                    
                    try {
                        $cell = $active_sheet->getCell($cellRef);
                        $value = $cell->getValue();
                        
                        if ($value !== null && $value !== '') {
                            $cells[$cellRef] = array(
                                'raw' => $value,
                                'display' => (string) $cell->getFormattedValue(),
                                'type' => self::detect_cell_type($cell),
                                'formula' => $cell->isFormula() ? $cell->getValue() : null
                            );
                            
                            // Se è data, parsala
                            if ($cells[$cellRef]['type'] === 'date' && is_numeric($value)) {
                                $unix_date = ($value - 25569) * 86400;
                                $cells[$cellRef]['parsed_date'] = date('Y-m-d', $unix_date);
                            }
                        }
                    } catch (\Exception $e) {
                        // Ignora errori su celle singole
                    }
                }
            }
            
            // Pulizia
            if (file_exists($temp_file)) {
                unlink($temp_file);
            }
            
            error_log('[Debug-Structure] Celle estratte: ' . count($cells));
            
            wp_send_json_success(array(
                'sheets' => $sheets,
                'active_sheet' => $active_sheet_name,
                'cells' => $cells,
                'total_cells' => count($cells)
            ));
            
        } catch (\Exception $e) {
            error_log('[Debug-Structure] ERRORE: ' . $e->getMessage());
            wp_send_json_error(array('message' => 'Errore analisi: ' . $e->getMessage()));
        }
    }
    
    /**
     * Rileva tipo di cella
     */
    private static function detect_cell_type($cell) {
        if ($cell->isFormula()) {
            return 'formula';
        }
        
        $dataType = $cell->getDataType();
        
        if ($dataType === \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC) {
            // Verifica se è data
            $value = $cell->getValue();
            if (is_numeric($value) && $value > 25569 && $value < 50000) {
                return 'date';
            }
            return 'number';
        }
        
        if ($dataType === \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING) {
            return 'string';
        }
        
        if ($dataType === \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_BOOL) {
            return 'boolean';
        }
        
        return 'unknown';
    }
}

// Inizializza gli handler AJAX
Disco747_AJAX_Handlers::init();