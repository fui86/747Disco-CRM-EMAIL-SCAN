<?php
/**
 * Classe per gestione richieste AJAX - 747 Disco CRM
 * VERSIONE 11.8.9 con Batch Scan endpoint
 *
 * @package    Disco747_CRM
 * @subpackage Handlers
 * @version    11.8.9-BATCH-SCAN
 * @author     747 Disco Team
 */

namespace Disco747_CRM\Handlers;

if (!defined('ABSPATH')) {
    exit('Accesso diretto non consentito');
}

class Disco747_Ajax {

    /**
     * Componenti core
     */
    private $config;
    private $database;
    private $auth;
    private $storage_manager;
    private $pdf_generator;
    private $excel_generator;
    private $googledrive_sync;
    
    /**
     * Flag debug
     */
    private $debug_mode = true;

    /**
     * Costruttore
     */
    public function __construct() {
        $this->load_dependencies();
        $this->register_ajax_hooks();
        $this->log('AJAX Handler inizializzato con endpoint batch scan');
    }

    /**
     * Carica dipendenze
     */
    private function load_dependencies() {
        $disco747_crm = disco747_crm();
        
        $this->config = $disco747_crm->get_config();
        $this->database = $disco747_crm->get_database();
        $this->auth = $disco747_crm->get_auth();
        $this->storage_manager = $disco747_crm->get_storage_manager();
        $this->pdf_generator = $disco747_crm->get_pdf();
        $this->excel_generator = $disco747_crm->get_excel();
        
        // Carica GoogleDrive Sync se disponibile
        if ($disco747_crm && method_exists($disco747_crm, 'get_googledrive_sync')) {
            $this->googledrive_sync = $disco747_crm->get_googledrive_sync();
        }
        
        // Crea tabella log email se non esiste
        $this->create_email_log_table();
    }
    
    /**
     * Crea tabella per log invio email
     */
    private function create_email_log_table() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'disco747_email_log';
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            preventivo_id bigint(20) NOT NULL,
            email_to varchar(255) NOT NULL,
            subject varchar(500) DEFAULT NULL,
            template_id varchar(50) DEFAULT NULL,
            sent_at datetime NOT NULL,
            status varchar(20) NOT NULL,
            error_message text DEFAULT NULL,
            PRIMARY KEY (id),
            KEY preventivo_id (preventivo_id),
            KEY sent_at (sent_at)
        ) {$charset_collate};";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        
        $this->log('Tabella email_log verificata/creata');
    }

    /**
     * Registra hook AJAX
     */
    private function register_ajax_hooks() {
        
        // Storage e OAuth
        add_action('wp_ajax_disco747_test_storage', array($this, 'handle_storage_test'));
        add_action('wp_ajax_disco747_update_setting', array($this, 'handle_update_setting'));
        
        // Batch scan Excel da Google Drive
        add_action('wp_ajax_batch_scan_excel', array($this, 'handle_batch_scan'));
        add_action('wp_ajax_reset_and_scan_excel', array($this, 'handle_reset_and_scan'));
        
        // Template messaggi
        add_action('wp_ajax_disco747_get_templates', array($this, 'handle_get_templates'));
        add_action('wp_ajax_disco747_compile_template', array($this, 'handle_compile_template'));
        add_action('wp_ajax_disco747_send_email_template', array($this, 'handle_send_email_template'));
        add_action('wp_ajax_disco747_send_whatsapp_template', array($this, 'handle_send_whatsapp_template'));
        
        $this->log('Hook AJAX registrati (incluso batch scan + templates + send email + whatsapp)');
    }

    /**
     * 🎯 Handler per batch scan di file Excel su Google Drive
     */
    public function handle_batch_scan() {
        error_log('[747Disco-Scan] handle_batch_scan chiamato');
        
        try {
            // Verifica nonce
            if (!check_ajax_referer('disco747_batch_scan', 'nonce', false)) {
                error_log('[747Disco-Scan] Nonce non valido');
                wp_send_json_error('Nonce non valido');
                return;
            }
            
            error_log('[747Disco-Scan] Nonce OK');
            
            // Verifica permessi
            if (!current_user_can('manage_options')) {
                error_log('[747Disco-Scan] Permessi insufficienti');
                wp_send_json_error('Permessi insufficienti');
                return;
            }
            
            error_log('[747Disco-Scan] Permessi OK');
            
            // Verifica che GoogleDrive Sync sia disponibile
            if (!$this->googledrive_sync) {
                error_log('[747Disco-Scan] GoogleDrive Sync non disponibile');
                wp_send_json_error('Servizio GoogleDrive Sync non disponibile');
                return;
            }
            
            error_log('[747Disco-Scan] GoogleDrive Sync disponibile, avvio scansione...');
            
            // Ottieni parametri
            $year = sanitize_text_field($_POST['year'] ?? date('Y'));
            $month = sanitize_text_field($_POST['month'] ?? '');
            
            error_log("[747Disco-Scan] Parametri: anno={$year}, mese={$month}");
            
            // Esegui batch scan
            $result = $this->googledrive_sync->scan_excel_files_batch($year, $month);
            
            error_log('[747Disco-Scan] Risultato batch scan: ' . json_encode($result));
            
            wp_send_json_success($result);
            
        } catch (\Exception $e) {
            error_log('[747Disco-Scan] Errore batch scan: ' . $e->getMessage());
            wp_send_json_error('Errore: ' . $e->getMessage());
        }
    }

    /**
     * 🗑️ Handler per reset e scan completo
     */
    public function handle_reset_and_scan() {
        error_log('[747Disco-Scan] handle_reset_and_scan chiamato');
        
        try {
            // Verifica nonce
            if (!check_ajax_referer('disco747_batch_scan', 'nonce', false)) {
                error_log('[747Disco-Scan] Nonce non valido');
                wp_send_json_error('Nonce non valido');
                return;
            }
            
            // Verifica permessi
            if (!current_user_can('manage_options')) {
                error_log('[747Disco-Scan] Permessi insufficienti');
                wp_send_json_error('Permessi insufficienti');
                return;
            }
            
            error_log('[747Disco-Scan] Svuotamento database...');
            
            // Svuota tabella preventivi
            global $wpdb;
            $table_name = $wpdb->prefix . 'disco747_preventivi';
            $deleted = $wpdb->query("DELETE FROM {$table_name}");
            
            error_log("[747Disco-Scan] Eliminati {$deleted} record dal database");
            
            // Esegui batch scan normale
            $this->handle_batch_scan();
            
        } catch (\Exception $e) {
            error_log('[747Disco-Scan] Errore reset and scan: ' . $e->getMessage());
            wp_send_json_error('Errore: ' . $e->getMessage());
        }
    }

    // ... resto dei metodi esistenti per compatibilità ...
    
    /**
     * Log interno
     */
    private function log($message, $level = 'INFO') {
        if ($this->debug_mode && function_exists('error_log')) {
            $timestamp = date('Y-m-d H:i:s');
            error_log("[{$timestamp}] [747Disco-AJAX] [{$level}] {$message}");
        }
    }
}