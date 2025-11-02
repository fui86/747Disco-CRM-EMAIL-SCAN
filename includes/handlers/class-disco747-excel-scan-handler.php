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
     * ✅ SINGLETON: Istanza unica
     */
    private static $instance = null;
    
    /**
     * Nome della tabella unificata per i preventivi
     */
    private $table_name;
    
    /**
     * Istanza Google Drive
     */
    private $googledrive = null;
    
    /**
     * Cache file Excel (per evitare scansioni multiple)
     */
    private $cached_excel_files = null;
    
    /**
     * ✅ SINGLETON: Get instance
     */
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Costruttore privato (SINGLETON)
     */
    private function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'disco747_preventivi'; // ✅ TABELLA UNIFICATA
        
        // Registra hooks AJAX
        add_action('wp_ajax_disco747_batch_scan_excel', array($this, 'handle_batch_scan_ajax'));
        add_action('wp_ajax_disco747_single_scan_excel', array($this, 'handle_single_scan_ajax'));
        
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
     * Handler AJAX per scansione batch PROGRESSIVA
     * ✅ FIX 503: Supporta batch_size e offset per processamento incrementale
     */
    public function handle_batch_scan_ajax() {
        // ✅ Verifica nonce (accetta sia disco747_excel_scan che disco747_batch_scan per compatibilità)
        $nonce_valid = false;
        
        if (isset($_POST['nonce']) || isset($_POST['_wpnonce'])) {
            $nonce = isset($_POST['nonce']) ? $_POST['nonce'] : $_POST['_wpnonce'];
            
            // Prova entrambi i nonce names per compatibilità
            $nonce_valid = wp_verify_nonce($nonce, 'disco747_excel_scan') || 
                          wp_verify_nonce($nonce, 'disco747_batch_scan');
        }
        
        if (!$nonce_valid) {
            error_log('[Excel-Scan] Nonce non valido o mancante');
            wp_send_json_error(array('message' => 'Nonce non valido'));
            return;
        }
        
        // Verifica permessi
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permessi insufficienti'));
            return;
        }
        
        // ✅ Aumenta timeout PHP per batch
        @set_time_limit(120); // 2 minuti max per batch
        @ini_set('memory_limit', '256M'); // Aumenta memoria disponibile
        
        try {
            // ✅ Parametri batch progressivo
            $batch_size = isset($_POST['batch_size']) ? intval($_POST['batch_size']) : 10; // Default 10 file
            $offset = isset($_POST['offset']) ? intval($_POST['offset']) : 0;
            $dry_run = isset($_POST['dry_run']) ? intval($_POST['dry_run']) === 1 : false;
            
            error_log("[Excel-Scan] Batch progressivo - Batch size: {$batch_size}, Offset: {$offset}");
            
            // Inizializza contatori
            $counters = array(
                'found' => 0,      // Totale file trovati
                'processed' => 0,  // File processati in questo batch
                'new' => 0,        // Nuovi inserimenti
                'updated' => 0,    // Aggiornamenti
                'errors' => 0,     // Errori
                'has_more' => false // Ci sono altri file da processare?
            );
            
            $messages = array();
            $errors_detail = array();
            
            // ✅ STEP 1: Trova TUTTI i file Excel da Google Drive (solo la prima volta)
            if ($offset === 0) {
                $this->cached_excel_files = null; // Reset cache
            }
            
            $all_files = $this->get_excel_files_from_googledrive_cached();
            $counters['found'] = count($all_files);
            
            if (empty($all_files)) {
                wp_send_json_error(array('message' => 'Nessun file Excel trovato su Google Drive'));
                return;
            }
            
            // ✅ STEP 2: Processa solo un subset (batch_size file a partire da offset)
            $files_to_process = array_slice($all_files, $offset, $batch_size);
            $counters['has_more'] = (($offset + $batch_size) < $counters['found']);
            
            error_log("[Excel-Scan] Processando " . count($files_to_process) . " file (offset {$offset} di {$counters['found']} totali)");
            $messages[] = "📂 Processando " . count($files_to_process) . " file (totale: {$counters['found']})...";
            
            // ✅ STEP 3: Processa ogni file del batch corrente
            foreach ($files_to_process as $file) {
                try {
                    error_log("[Excel-Scan] Processando: {$file['name']}");
                    
                    // Download e parsing
                    $parsed_data = $this->download_and_parse_excel($file);
                    
                    if (!$parsed_data) {
                        $errors_detail[] = "Impossibile parsare: {$file['name']}";
                        $counters['errors']++;
                        $messages[] = "❌ Errore parsing: {$file['name']}";
                        continue;
                    }
                    
                    // Salva nel database se non è dry run
                    if (!$dry_run) {
                        $result = $this->save_to_preventivi_table($parsed_data);
                        
                        if ($result['success']) {
                            $counters['processed']++;
                            
                            if ($result['action'] === 'inserted') {
                                $counters['new']++;
                                $messages[] = "✅ Nuovo: {$file['name']}";
                            } else {
                                $counters['updated']++;
                                $messages[] = "🔄 Aggiornato: {$file['name']}";
                            }
                        } else {
                            $counters['errors']++;
                            $errors_detail[] = "Errore salvataggio: {$file['name']}";
                            $messages[] = "❌ Errore DB: {$file['name']}";
                        }
                    } else {
                        $counters['processed']++;
                    }
                    
                    // Rate limiting
                    usleep(100000); // 100ms tra file
                    
                } catch (Exception $e) {
                    $error_msg = "Errore {$file['name']}: " . $e->getMessage();
                    $errors_detail[] = $error_msg;
                    $counters['errors']++;
                    $messages[] = "❌ " . $error_msg;
                    error_log("[Excel-Scan] {$error_msg}");
                }
            }
            
            // ✅ Messaggio riepilogo batch
            $summary = "Batch completato: {$counters['processed']} file processati";
            if ($counters['new'] > 0) $summary .= ", {$counters['new']} nuovi";
            if ($counters['updated'] > 0) $summary .= ", {$counters['updated']} aggiornati";
            if ($counters['errors'] > 0) $summary .= ", {$counters['errors']} errori";
            
            error_log("[Excel-Scan] {$summary}");
            $messages[] = "📊 " . $summary;
            
            wp_send_json_success(array(
                'found' => $counters['found'],
                'processed' => $counters['processed'],
                'new' => $counters['new'],
                'updated' => $counters['updated'],
                'errors' => $counters['errors'],
                'has_more' => $counters['has_more'],
                'next_offset' => $offset + $batch_size,
                'messages' => $messages,
                'errors_detail' => array_slice($errors_detail, 0, 5)
            ));
            
        } catch (Exception $e) {
            error_log('[Excel-Scan] Errore batch: ' . $e->getMessage());
            wp_send_json_error(array('message' => 'Errore: ' . $e->getMessage()));
        }
    }
    
    /**
     * Handler AJAX per scansione singolo file
     */
    public function handle_single_scan_ajax() {
        // Reindirizza alla scansione batch con singolo file
        $this->handle_batch_scan_ajax();
    }
    
    /**
     * ✅ Versione cached per evitare scansioni multiple nella stessa sessione
     */
    private function get_excel_files_from_googledrive_cached() {
        // Usa cache se disponibile (dura per la sessione corrente)
        if ($this->cached_excel_files !== null) {
            error_log("[Excel-Scan] Uso cache file (count: " . count($this->cached_excel_files) . ")");
            return $this->cached_excel_files;
        }
        
        // Prima scansione: carica e cача
        $this->cached_excel_files = $this->get_excel_files_from_googledrive();
        return $this->cached_excel_files;
    }
    
    /**
     * ✅ REALE: Trova file Excel da Google Drive usando GoogleDrive_Sync
     * Usa la scansione ricorsiva già funzionante
     */
    private function get_excel_files_from_googledrive() {
        try {
            error_log('[Excel-Scan] Inizializzazione GoogleDrive_Sync per scansione ricorsiva...');
            
            // ✅ Usa la classe GoogleDrive_Sync che già funziona perfettamente
            $sync_file = DISCO747_CRM_PLUGIN_DIR . 'includes/storage/class-disco747-googledrive-sync.php';
            if (!file_exists($sync_file)) {
                error_log('[Excel-Scan] ERRORE: class-disco747-googledrive-sync.php non trovato');
                return array();
            }
            
            require_once $sync_file;
            
            // Istanzia GoogleDrive_Sync
            $sync = new \Disco747_CRM\Storage\Disco747_GoogleDrive_Sync();
            
            if (!$sync || !method_exists($sync, 'scan_excel_files_batch')) {
                error_log('[Excel-Scan] ERRORE: metodo scan_excel_files_batch non disponibile');
                return array();
            }
            
            // ✅ Usa scan_excel_files_batch in modalità test per ottenere solo la lista
            error_log('[Excel-Scan] Avvio scan_excel_files_batch...');
            $result = $sync->scan_excel_files_batch(
                true,  // test_mode = true (non salva, solo scansiona)
                999    // limit alto per prendere tutti i file
            );
            
            if (!$result['success'] || empty($result['files'])) {
                error_log('[Excel-Scan] Nessun file trovato da GoogleDrive_Sync');
                return array();
            }
            
            $files = $result['files'];
            error_log('[Excel-Scan] ✅ Trovati ' . count($files) . ' file Excel da GoogleDrive_Sync');
            
            // Converti formato per compatibilità
            $excel_files = array();
            foreach ($files as $file) {
                $excel_files[] = array(
                    'id' => $file['id'],
                    'name' => $file['name'],
                    'modifiedTime' => $file['modifiedTime'] ?? date('Y-m-d H:i:s'),
                    'size' => $file['size'] ?? 0,
                    'folder_path' => $file['folder_path'] ?? ''
                );
            }
            
            return $excel_files;
            
        } catch (\Exception $e) {
            error_log('[Excel-Scan] ERRORE get_excel_files_from_googledrive: ' . $e->getMessage());
            return array();
        }
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
     * Ritorna array con 'success' e 'action' per tracking
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
                        return array('success' => true, 'action' => 'updated', 'id' => $existing);
                    } else {
                        error_log("Disco747 Excel Scan - Errore UPDATE: " . $wpdb->last_error);
                        return array('success' => false, 'error' => $wpdb->last_error);
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
                return array('success' => false, 'error' => $wpdb->last_error);
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
            
            return array('success' => true, 'action' => 'inserted', 'id' => $insert_id);
            
        } catch (Exception $e) {
            error_log('Disco747 Excel Scan - Errore salvataggio preventivo: ' . $e->getMessage());
            return array('success' => false, 'error' => $e->getMessage());
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

// ✅ SINGLETON: Inizializza l'handler unico
Disco747_Excel_Scan_Handler::get_instance();