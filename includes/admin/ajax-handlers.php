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
        
        error_log('[Excel-Scan-AJAX] Hook AJAX registrati: batch_scan_excel, reset_and_scan_excel, analyze_excel_file');
    }

    /**
     * Handler principale per batch scan
     * ✅ REINDIRIZZA al nuovo handler progressivo in class-disco747-excel-scan-handler.php
     */
    public static function handle_batch_scan() {
        error_log('[Batch-Scan-AJAX-v1.4] ========== INIZIO HANDLER ==========');
        error_log('[Batch-Scan-AJAX-v1.4] POST data: ' . print_r(array_keys($_POST), true));
        error_log('[Batch-Scan-AJAX-v1.4] action ricevuta: ' . ($_POST['action'] ?? 'N/D'));
        
        // ✅ Reindirizza al nuovo handler ottimizzato (usa singleton per evitare istanziazioni multiple)
        if (class_exists('Disco747_CRM\\Handlers\\Disco747_Excel_Scan_Handler')) {
            error_log('[Batch-Scan-AJAX-v1.4] ✅ Classe Disco747_Excel_Scan_Handler TROVATA!');
            
            try {
                // ✅ Usa get_instance() per ottenere istanza singleton (evita hook duplicati)
                error_log('[Batch-Scan-AJAX-v1.4] Chiamata get_instance()...');
                $handler = \Disco747_CRM\Handlers\Disco747_Excel_Scan_Handler::get_instance();
                
                if (!$handler) {
                    error_log('[Batch-Scan-AJAX-v1.4] ERRORE: get_instance() ha ritornato null!');
                    wp_send_json_error(array('message' => 'Handler non disponibile'));
                    return;
                }
                
                error_log('[Batch-Scan-AJAX-v1.4] ✅ Handler istanziato: ' . get_class($handler));
                
                // Assicura compatibilità nonce
                if (!isset($_POST['nonce']) && isset($_POST['_wpnonce'])) {
                    $_POST['nonce'] = $_POST['_wpnonce'];
                }
                
                // Chiama il nuovo handler
                error_log('[Batch-Scan-AJAX-v1.4] ✅ Invoco handle_batch_scan_ajax...');
                $handler->handle_batch_scan_ajax();
                
                error_log('[Batch-Scan-AJAX-v1.4] ✅ Handler completato, return');
                return; // Importante: stoppa esecuzione
                
            } catch (\Exception $e) {
                error_log('[Batch-Scan-AJAX-v1.4] EXCEPTION: ' . $e->getMessage());
                wp_send_json_error(array('message' => 'Errore handler: ' . $e->getMessage()));
                return;
            }
        }
        
        // ⚠️ FALLBACK: vecchio handler (se il nuovo non è disponibile)
        error_log('[Batch-Scan-AJAX-v1.4] ⚠️ FALLBACK: Classe non trovata, uso vecchio handler');
        
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
}

// Inizializza gli handler AJAX
Disco747_AJAX_Handlers::init();