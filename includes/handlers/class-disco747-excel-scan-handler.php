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
            $file_id = isset($_POST['file_id']) ? sanitize_text_field($_POST['file_id']) : '';
            
            error_log("Disco747 Excel Scan - Avvio scansione REALE - dry_run: {$dry_run}, file_id: {$file_id}");
            
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
            
            error_log("Disco747 Excel Scan - Trovati {$counters['listed']} file Excel REALI da Google Drive");
            
            if (empty($excel_files)) {
                wp_send_json_error(array('message' => 'Nessun file Excel trovato su Google Drive'));
                return;
            }
            
            // ✅ REALE: Processa ogni file Excel
            foreach ($excel_files as $i => $file) {
                try {
                    error_log("Disco747 Excel Scan - Processando file REALE: {$file['name']}");
                    
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
                    
                    // Rate limiting per non sovraccaricare Google Drive
                    if ($i < count($excel_files) - 1) {
                        usleep(200000); // 200ms
                    }
                    
                } catch (Exception $e) {
                    $error_msg = "Errore processando {$file['name']}: " . $e->getMessage();
                    $errors[] = $error_msg;
                    error_log("Disco747 Excel Scan - {$error_msg}");
                    $counters['errors']++;
                }
            }
            
            error_log("Disco747 Excel Scan - Completata REALE - Parsed: {$counters['parsed_ok']}, Saved: {$counters['saved_ok']}, Errors: {$counters['errors']}");
            
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
                'updated_records' => 0, // Non implementato in questo handler
                'errors' => $counters['errors'],
                'messages' => $messages
            ));
            
        } catch (Exception $e) {
            error_log('Disco747 Excel Scan - Errore scansione batch REALE: ' . $e->getMessage());
            wp_send_json_error(array('message' => 'Errore interno: ' . $e->getMessage()));
        }
    }
    
    /**
     * Handler AJAX per reset e scan completo
     */
    public function handle_reset_and_scan_ajax() {
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
            error_log('[747Disco-Scan] Svuotamento database...');
            
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
     * Sostituisce find_excel_files_simulation()
     */
    private function get_excel_files_from_googledrive() {
        if (!$this->googledrive) {
            error_log('Disco747 Excel Scan - Google Drive non inizializzato');
            return array();
        }
        
        try {
            $excel_files = array();
            
            // Ottieni parametri dal POST
            $year = sanitize_text_field($_POST['year'] ?? date('Y'));
            $month = sanitize_text_field($_POST['month'] ?? '');
            
            error_log("[747Disco-Scan] Parametri: anno={$year}, mese={$month}");
            
            // Trova cartella principale 747-Preventivi
            $main_folder_id = $this->googledrive->get_or_create_folder('747-Preventivi');
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
     * Trova cartella anno
     */
    private function find_year_folder($parent_id, $year) {
        $files = $this->googledrive->list_files($parent_id, "mimeType='application/vnd.google-apps.folder' and name='{$year}'");
        return !empty($files) ? $files[0]['id'] : null;
    }
    
    /**
     * Trova cartella mese
     */
    private function find_month_folder($parent_id, $month) {
        $files = $this->googledrive->list_files($parent_id, "mimeType='application/vnd.google-apps.folder' and name='{$month}'");
        return !empty($files) ? $files[0]['id'] : null;
    }
    
    /**
     * Scansiona file Excel in una cartella specifica
     */
    private function scan_excel_files_in_folder($folder_id) {
        $files = $this->googledrive->list_files($folder_id, "mimeType='application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' or mimeType='application/vnd.ms-excel'");
        
        $excel_files = array();
        foreach ($files as $file) {
            $excel_files[] = array(
                'id' => $file['id'],
                'name' => $file['name'],
                'modifiedTime' => $file['modifiedTime'] ?? '',
                'size' => $file['size'] ?? 0,
                'webViewLink' => $file['webViewLink'] ?? ''
            );
        }
        
        return $excel_files;
    }
    
    /**
     * Scansiona ricorsivamente tutte le cartelle
     */
    private function scan_all_excel_files_recursive($folder_id) {
        $all_files = array();
        
        // Scansiona file Excel nella cartella corrente
        $files = $this->scan_excel_files_in_folder($folder_id);
        $all_files = array_merge($all_files, $files);
        
        // Scansiona sottocartelle
        $subfolders = $this->googledrive->list_files($folder_id, "mimeType='application/vnd.google-apps.folder'");
        foreach ($subfolders as $subfolder) {
            $subfolder_files = $this->scan_all_excel_files_recursive($subfolder['id']);
            $all_files = array_merge($all_files, $subfolder_files);
        }
        
        return $all_files;
    }
    
    /**
     * ✅ REALE: Download e parsing file Excel
     * Sostituisce simulate_excel_parsing()
     */
    private function download_and_parse_excel($file_info) {
        if (!$this->googledrive) {
            error_log('Disco747 Excel Scan - Google Drive non disponibile per download');
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
                error_log("Disco747 Excel Scan - Errore download: " . $download_result['error']);
                return false;
            }
            
            error_log("Disco747 Excel Scan - File scaricato: {$temp_file}");
            
            // ✅ Parsing reale con PhpSpreadsheet
            $parsed_data = $this->parse_excel_with_phpspreadsheet($temp_file, $file_info);
            
            // Pulizia file temporaneo
            if (file_exists($temp_file)) {
                unlink($temp_file);
            }
            
            return $parsed_data;
            
        } catch (Exception $e) {
            error_log('Disco747 Excel Scan - Errore download/parsing: ' . $e->getMessage());
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
                    error_log('Disco747 Excel Scan - PhpSpreadsheet non disponibile');
                    return false;
                }
            }
            
            // Carica il file Excel
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($file_path);
            $worksheet = $spreadsheet->getActiveSheet();
            
            // ✅ Mapping secondo il template specificato
            $data = array();
            
            // Nome file per estrarre info base
            $filename = $file_info['name'];
            
            // Parsing celle specifiche
            $data['tipo_menu'] = $this->clean_cell_value($worksheet->getCell('B1')->getValue());
            $data['data_evento'] = $this->parse_date_from_cell($worksheet->getCell('C6')->getValue());
            $data['tipo_evento'] = $this->clean_cell_value($worksheet->getCell('C7')->getValue());
            $data['orario_evento'] = $this->clean_cell_value($worksheet->getCell('C8')->getValue());
            $data['numero_invitati'] = $this->parse_number_from_cell($worksheet->getCell('C9')->getValue());
            
            // Cliente/Referente
            $data['nome_referente'] = $this->clean_cell_value($worksheet->getCell('C11')->getValue());
            $data['cognome_referente'] = $this->clean_cell_value($worksheet->getCell('C12')->getValue());
            $data['telefono'] = $this->clean_cell_value($worksheet->getCell('C14')->getValue());
            $data['email'] = $this->clean_cell_value($worksheet->getCell('C15')->getValue());
            
            // Omaggi
            $data['omaggio1'] = $this->clean_cell_value($worksheet->getCell('C17')->getValue());
            $data['omaggio2'] = $this->clean_cell_value($worksheet->getCell('C18')->getValue());
            $data['omaggio3'] = $this->clean_cell_value($worksheet->getCell('C19')->getValue());
            
            // Importi
            $data['importo_totale'] = $this->parse_currency_from_cell($worksheet->getCell('C21')->getValue());
            $data['acconto'] = $this->parse_currency_from_cell($worksheet->getCell('C23')->getValue());
            
            // Extra a pagamento
            $data['extra1'] = $this->clean_cell_value($worksheet->getCell('C33')->getValue());
            $data['extra1_importo'] = $this->parse_currency_from_cell($worksheet->getCell('F33')->getValue());
            $data['extra2'] = $this->clean_cell_value($worksheet->getCell('C34')->getValue());
            $data['extra2_importo'] = $this->parse_currency_from_cell($worksheet->getCell('F34')->getValue());
            $data['extra3'] = $this->clean_cell_value($worksheet->getCell('C35')->getValue());
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
            
            // Calcola saldo
            $importo_totale = floatval($data['importo_totale']);
            $acconto = floatval($data['acconto']);
            $extra_totale = floatval($data['extra1_importo']) + floatval($data['extra2_importo']) + floatval($data['extra3_importo']);
            
            $data['importo_preventivo'] = $importo_totale + $extra_totale;
            $data['saldo'] = $data['importo_preventivo'] - $acconto;
            
            // Stato basato su acconto
            $data['stato'] = $acconto > 0 ? 'confermato' : 'attivo';
            
            // Determina prefisso dal filename per stato
            if (strpos($filename, 'CONF ') === 0) {
                $data['stato'] = 'confermato';
            } elseif (strpos($filename, 'NO ') === 0) {
                $data['stato'] = 'annullato';
            }
            
            error_log("Disco747 Excel Scan - Parsing completato: {$filename} - Evento: {$data['tipo_evento']}, Importo: €" . number_format($data['importo_totale'], 2));
            
            return $data;
            
        } catch (Exception $e) {
            error_log('Disco747 Excel Scan - Errore parsing PhpSpreadsheet: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * ✅ REALE: Salva nella tabella preventivi unificata
     * Sostituisce save_excel_analysis()
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
                'importo_preventivo' => $data['importo_preventivo'],
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
                        error_log("Disco747 Excel Scan - Preventivo aggiornato ID: {$existing}");
                        return $existing;
                    } else {
                        error_log("Disco747 Excel Scan - Errore UPDATE: " . $wpdb->last_error);
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
                error_log("Disco747 Excel Scan - Errore INSERT: " . $wpdb->last_error);
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
            
            error_log("Disco747 Excel Scan - Preventivo salvato con ID: {$insert_id}");
            
            return $insert_id;
            
        } catch (Exception $e) {
            error_log('Disco747 Excel Scan - Errore salvataggio preventivo: ' . $e->getMessage());
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
     */
    private function parse_date_from_cell($value) {
        if (empty($value)) return null;
        
        try {
            // Prova parsing diretto
            if (is_numeric($value)) {
                // Excel date serial
                $unix_date = ($value - 25569) * 86400;
                return date('Y-m-d', $unix_date);
            } else {
                // Stringa data
                $timestamp = strtotime($value);
                return $timestamp ? date('Y-m-d', $timestamp) : null;
            }
        } catch (Exception $e) {
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
     * Ottiene statistiche per la dashboard
     * (Compatibilità con interfaccia esistente)
     */
    public function get_excel_analysis($args = array()) {
        global $wpdb;
        
        $defaults = array(
            'limit' => 100,
            'offset' => 0,
            'orderby' => 'created_at',
            'order' => 'DESC',
            'search' => '',
            'menu_filter' => '',
            'status_filter' => ''
        );
        
        $args = wp_parse_args($args, $defaults);
        
        $where_conditions = array('1=1');
        $where_values = array();
        
        // ✅ Filtra solo record da Excel (hanno googledrive_file_id)
        $where_conditions[] = "googledrive_file_id IS NOT NULL AND googledrive_file_id != ''";
        
        // Filtro ricerca
        if (!empty($args['search'])) {
            $search = '%' . $wpdb->esc_like($args['search']) . '%';
            $where_conditions[] = "(nome_referente LIKE %s OR cognome_referente LIKE %s OR email LIKE %s OR telefono LIKE %s OR tipo_evento LIKE %s)";
            $where_values = array_merge($where_values, array($search, $search, $search, $search, $search));
        }
        
        // Filtro menu
        if (!empty($args['menu_filter'])) {
            $where_conditions[] = "tipo_menu LIKE %s";
            $where_values[] = '%' . $args['menu_filter'] . '%';
        }
        
        // Filtro stato
        if (!empty($args['status_filter'])) {
            if ($args['status_filter'] === 'confirmed') {
                $where_conditions[] = "acconto > 0";
            } elseif ($args['status_filter'] === 'pending') {
                $where_conditions[] = "(acconto IS NULL OR acconto <= 0) AND stato != 'annullato'";
            } elseif ($args['status_filter'] === 'error') {
                $where_conditions[] = "stato = 'errore'";
            }
        }
        
        $where_clause = implode(' AND ', $where_conditions);
        $order_clause = sprintf('ORDER BY %s %s', $args['orderby'], $args['order']);
        $limit_clause = sprintf('LIMIT %d OFFSET %d', $args['limit'], $args['offset']);
        
        $query = "SELECT * FROM {$this->table_name} WHERE {$where_clause} {$order_clause} {$limit_clause}";
        
        if (!empty($where_values)) {
            $prepared_query = $wpdb->prepare($query, $where_values);
        } else {
            $prepared_query = $query;
        }
        
        return $wpdb->get_results($prepared_query, OBJECT);
    }
    
    /**
     * Ottiene singolo preventivo per ID
     * (Compatibilità con interfaccia esistente)
     */
    public function get_excel_analysis_by_id($id) {
        global $wpdb;
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE id = %d AND googledrive_file_id IS NOT NULL",
            $id
        ), OBJECT);
    }
    
    /**
     * Log delle attività
     */
    private function log($message, $level = 'info') {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("Disco747 Excel Scan [{$level}]: {$message}");
        }
    }
}

// Inizializza l'handler
new Disco747_Excel_Scan_Handler();