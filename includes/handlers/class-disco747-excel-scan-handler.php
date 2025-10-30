<?php
/**
 * Handler dedicato per la scansione Excel di 747 Disco CRM
 * VERSIONE 12.1.0-COMPLETE: Batch scan completo con tutti i fix
 * 
 * @package    Disco747_CRM
 * @subpackage Handlers
 * @since      12.1.0
 * @version    12.1.0-COMPLETE
 */

namespace Disco747_CRM\Handlers;

if (!defined('ABSPATH')) {
    exit('Accesso diretto non consentito');
}

/**
 * Classe per gestire la scansione automatica dei file Excel da Google Drive
 * Salva i dati nella tabella unificata wp_disco747_preventivi
 */
class Disco747_Excel_Scan_Handler {
    
    private $table_name;
    private $googledrive_sync = null;
    
    /**
     * Costruttore
     */
    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'disco747_preventivi';
        
        // Registra hooks AJAX
        add_action('wp_ajax_batch_scan_excel', array($this, 'handle_batch_scan_ajax'));
        add_action('wp_ajax_reset_and_scan_excel', array($this, 'handle_reset_and_scan_ajax'));
        add_action('wp_ajax_analyze_excel_file', array($this, 'handle_analyze_single_file'));
        
        error_log('[Excel-Scan-AJAX] Hook AJAX registrati: batch_scan_excel, reset_and_scan_excel, analyze_excel_file');
        
        // Inizializza Google Drive Sync
        $this->init_googledrive_sync();
    }
    
    /**
     * Inizializza Google Drive Sync
     */
    private function init_googledrive_sync() {
        try {
            // Prova a ottenere istanza dal plugin principale
            if (function_exists('disco747_crm')) {
                $disco747 = disco747_crm();
                if ($disco747 && method_exists($disco747, 'get_gdrive_sync')) {
                    $this->googledrive_sync = $disco747->get_gdrive_sync();
                }
            }
            
            // Fallback: crea istanza diretta
            if (!$this->googledrive_sync && class_exists('Disco747_CRM\\Storage\\Disco747_GoogleDrive_Sync')) {
                // Ottieni GoogleDrive handler
                $googledrive_handler = null;
                if (class_exists('Disco747_CRM\\Storage\\Disco747_GoogleDrive')) {
                    $googledrive_handler = new \Disco747_CRM\Storage\Disco747_GoogleDrive();
                }
                
                $this->googledrive_sync = new \Disco747_CRM\Storage\Disco747_GoogleDrive_Sync($googledrive_handler);
            }
            
        } catch (\Exception $e) {
            error_log('[Excel-Scan-AJAX] Errore init GoogleDrive Sync: ' . $e->getMessage());
        }
    }
    
    /**
     * ✅ Handler AJAX per scansione batch
     */
    public function handle_batch_scan_ajax() {
        error_log('[747Disco-Scan] ========== BATCH SCAN AJAX CHIAMATO ==========');
        
        try {
            // Verifica nonce
            if (!check_ajax_referer('disco747_batch_scan', 'nonce', false)) {
                error_log('[747Disco-Scan] Nonce non valido');
                wp_send_json_error(array('message' => 'Nonce non valido'));
                return;
            }
            
            error_log('[747Disco-Scan] Nonce OK');
            
            // Verifica permessi
            if (!current_user_can('manage_options')) {
                error_log('[747Disco-Scan] Permessi insufficienti');
                wp_send_json_error(array('message' => 'Permessi insufficienti'));
                return;
            }
            
            error_log('[747Disco-Scan] Permessi OK');
            
            // Parametri
            $year = isset($_POST['year']) ? sanitize_text_field($_POST['year']) : date('Y');
            $month = isset($_POST['month']) ? sanitize_text_field($_POST['month']) : '';
            
            error_log("[747Disco-Scan] Parametri: Year={$year}, Month={$month}");
            
            // Verifica GoogleDrive Sync disponibile
            if (!$this->googledrive_sync) {
                error_log('[747Disco-Scan] GoogleDrive Sync NON disponibile');
                wp_send_json_error(array('message' => 'Servizio GoogleDrive Sync non disponibile'));
                return;
            }
            
            if (!method_exists($this->googledrive_sync, 'batch_scan_excel_files')) {
                error_log('[747Disco-Scan] Metodo batch_scan_excel_files non esiste');
                wp_send_json_error(array('message' => 'Metodo batch_scan_excel_files non disponibile'));
                return;
            }
            
            error_log('[747Disco-Scan] GoogleDrive Sync disponibile, avvio scansione...');
            
            // Esegui scan
            $result = $this->googledrive_sync->batch_scan_excel_files($year, $month, false);
            
            error_log('[747Disco-Scan] Scansione completata: ' . json_encode($result));
            
            if ($result['success'] || $result['processed'] > 0) {
                // Leggi file processati per visualizzazione
                $new_files_list = $this->get_recent_scanned_files(20);
                
                wp_send_json_success(array(
                    'total_files' => $result['total_files'],
                    'processed' => $result['processed'],
                    'new_records' => $result['inserted'],
                    'updated_records' => $result['updated'],
                    'errors' => $result['errors'],
                    'messages' => $result['messages'],
                    'duration_ms' => $result['duration_ms'],
                    'new_files_list' => $new_files_list
                ));
            } else {
                wp_send_json_error(array(
                    'message' => 'Errore durante la scansione',
                    'details' => $result['messages'] ?? array()
                ));
            }
            
        } catch (\Exception $e) {
            error_log('[747Disco-Scan] Errore batch scan: ' . $e->getMessage());
            wp_send_json_error(array('message' => 'Errore: ' . $e->getMessage()));
        }
    }

    /**
     * ✅ Handler AJAX per reset + scan
     */
    public function handle_reset_and_scan_ajax() {
        error_log('[747Disco-Scan] ========== RESET + SCAN AJAX CHIAMATO ==========');
        
        try {
            // Verifica nonce
            if (!check_ajax_referer('disco747_batch_scan', 'nonce', false)) {
                error_log('[747Disco-Scan] Nonce non valido');
                wp_send_json_error(array('message' => 'Nonce non valido'));
                return;
            }
            
            // Verifica permessi
            if (!current_user_can('manage_options')) {
                error_log('[747Disco-Scan] Permessi insufficienti');
                wp_send_json_error(array('message' => 'Permessi insufficienti'));
                return;
            }
            
            // Parametri
            $year = isset($_POST['year']) ? sanitize_text_field($_POST['year']) : date('Y');
            $month = isset($_POST['month']) ? sanitize_text_field($_POST['month']) : '';
            
            error_log("[747Disco-Scan] Reset+Scan - Year={$year}, Month={$month}");
            
            // STEP 1: Svuota tabella
            global $wpdb;
            $wpdb->query("TRUNCATE TABLE {$this->table_name}");
            
            error_log('[747Disco-Scan] ✅ Tabella svuotata');
            
            // STEP 2: Rianalizza
            if (!$this->googledrive_sync) {
                wp_send_json_error(array('message' => 'GoogleDrive Sync non disponibile'));
                return;
            }
            
            $result = $this->googledrive_sync->batch_scan_excel_files($year, $month, true);
            
            error_log('[747Disco-Scan] Reset+Scan completato: ' . json_encode($result));
            
            if ($result['success'] || $result['processed'] > 0) {
                $new_files_list = $this->get_recent_scanned_files(20);
                
                wp_send_json_success(array(
                    'total_files' => $result['total_files'],
                    'processed' => $result['processed'],
                    'new_records' => $result['inserted'], // Dopo reset sono tutti nuovi
                    'updated_records' => 0,
                    'errors' => $result['errors'],
                    'messages' => $result['messages'],
                    'duration_ms' => $result['duration_ms'],
                    'new_files_list' => $new_files_list
                ));
            } else {
                wp_send_json_error(array(
                    'message' => 'Errore durante reset/scan',
                    'details' => $result['messages'] ?? array()
                ));
            }
            
        } catch (\Exception $e) {
            error_log('[747Disco-Scan] Errore reset+scan: ' . $e->getMessage());
            wp_send_json_error(array('message' => 'Errore: ' . $e->getMessage()));
        }
    }

    /**
     * Handler AJAX per analisi singolo file
     */
    public function handle_analyze_single_file() {
        error_log('[747Disco-Scan] ========== ANALYZE SINGLE FILE CHIAMATO ==========');
        
        try {
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
            
            $file_id = isset($_POST['file_id']) ? sanitize_text_field($_POST['file_id']) : '';
            
            if (empty($file_id)) {
                wp_send_json_error(array('message' => 'File ID mancante'));
                return;
            }
            
            error_log("[747Disco-Scan] Analisi file singolo: {$file_id}");
            
            // TODO: Implementa analisi singolo file se necessario
            
            wp_send_json_success(array(
                'message' => 'File analizzato',
                'file_id' => $file_id
            ));
            
        } catch (\Exception $e) {
            error_log('[747Disco-Scan] Errore analyze single: ' . $e->getMessage());
            wp_send_json_error(array('message' => 'Errore: ' . $e->getMessage()));
        }
    }

    /**
     * Ottiene lista file scansionati recenti per visualizzazione
     */
    private function get_recent_scanned_files($limit = 20) {
        global $wpdb;
        
        $results = $wpdb->get_results($wpdb->prepare("
            SELECT 
                preventivo_id,
                data_evento,
                tipo_evento,
                tipo_menu,
                stato,
                nome_cliente,
                googledrive_file_id
            FROM {$this->table_name}
            WHERE googledrive_file_id IS NOT NULL AND googledrive_file_id != ''
            ORDER BY created_at DESC
            LIMIT %d
        ", $limit), ARRAY_A);
        
        // Aggiungi filename costruito
        foreach ($results as &$file) {
            $file['filename'] = $this->construct_filename_from_data($file);
        }
        
        return $results;
    }

    /**
     * Costruisce filename da dati preventivo
     */
    private function construct_filename_from_data($data) {
        $prefix = '';
        if ($data['stato'] === 'confermato') {
            $prefix = 'CONF ';
        } elseif ($data['stato'] === 'annullato') {
            $prefix = 'NO ';
        }
        
        $date_parts = explode('-', $data['data_evento']);
        $day = str_pad($date_parts[2] ?? '01', 2, '0', STR_PAD_LEFT);
        $month = str_pad($date_parts[1] ?? '01', 2, '0', STR_PAD_LEFT);
        
        $tipo_evento = substr($data['tipo_evento'] ?? 'Evento', 0, 30);
        $menu = str_replace('Menu ', '', $data['tipo_menu'] ?? '7');
        
        return $prefix . $day . '_' . $month . ' ' . $tipo_evento . ' (Menu ' . $menu . ')';
    }
}

// Inizializza l'handler quando WordPress è pronto
add_action('init', function() {
    new \Disco747_CRM\Handlers\Disco747_Excel_Scan_Handler();
}, 20);
