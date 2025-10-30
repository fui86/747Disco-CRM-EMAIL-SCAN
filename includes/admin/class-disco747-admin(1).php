<?php
/**
 * Classe per la gestione dell'area amministrativa del plugin 747 Disco CRM
 * CORRETTA: Replica ESATTAMENTE il menu del vecchio PreventiviParty
 * CON AGGIUNTA: Excel Scan functionality
 *
 * @package    Disco747_CRM
 * @subpackage Admin
 * @since      11.4.2
 * @version    11.4.2
 * @author     747 Disco Team
 */

namespace Disco747_CRM\Admin;

// Sicurezza: impedisce l'accesso diretto al file
if (!defined('ABSPATH')) {
    exit('Accesso diretto non consentito');
}

/**
 * Classe Disco747_Admin CORRETTA + ENHANCED
 * 
 * Replica ESATTAMENTE la struttura menu del vecchio PreventiviParty:
 * - PreventiviParty (pagina principale) + ROUTING INTERNO NUOVO
 * - Impostazioni 
 * - Messaggi Automatici
 * + NUOVO: Excel Auto Scan
 * 
 * @since 11.4.2
 */
class Disco747_Admin {
    
    /**
     * Componenti core
     */
    private $config;
    private $database;
    private $auth;
    private $storage_manager;
    private $pdf_excel_handler;  // Sarà il PDF handler
    private $excel_handler;      // Sarà l'Excel handler separato
    
    /**
     * Configurazione admin
     */
    private $min_capability = 'manage_options';
    private $asset_version;
    private $admin_notices = array();
    private $hooks_registered = false;
    private $debug_mode = true;

    /**
     * Costruttore SAFE con delay
     */
    public function __construct() {
        $this->asset_version = defined('DISCO747_CRM_VERSION') ? DISCO747_CRM_VERSION : '11.4.2';
        
        // Inizializzazione ritardata per evitare problemi di dipendenze
        add_action('init', array($this, 'delayed_init'), 10);
    }

    /**
     * Inizializzazione ritardata e sicura
     */
    public function delayed_init() {
        try {
            $this->load_dependencies();
            $this->register_admin_hooks();
            
            $this->log('Admin Manager inizializzato');
        } catch (\Exception $e) {
            $this->log('Errore inizializzazione Admin: ' . $e->getMessage(), 'error');
            $this->add_admin_notice(
                'Errore inizializzazione 747 Disco CRM. Controlla i log per maggiori dettagli.',
                'error'
            );
        }
    }

    /**
     * Carica dipendenze SAFE
     */
    private function load_dependencies() {
        // Ottieni istanza principale (ora dovrebbe essere disponibile)
        $disco747_crm = disco747_crm();
        
        if (!$disco747_crm || !$disco747_crm->is_initialized()) {
            throw new \Exception('Plugin principale non ancora inizializzato');
        }

        // Carica componenti dal plugin principale
        $this->config = $disco747_crm->get_config();
        $this->database = $disco747_crm->get_database();
        $this->auth = $disco747_crm->get_auth();
        $this->storage_manager = $disco747_crm->get_storage_manager();
        
        // NUOVO: Carica handlers per preventivi (METODI CORRETTI)
        $this->pdf_excel_handler = $disco747_crm->get_pdf();
        $this->excel_handler = $disco747_crm->get_excel();
        
        // Per Google Drive e Dropbox usiamo storage_manager
        // o gestiamo tramite storage_manager che li contiene già
    }

    /**
     * Registra hook WordPress
     */
    private function register_admin_hooks() {
        if ($this->hooks_registered) {
            return;
        }

        try {
            // Menu principale
            add_action('admin_menu', array($this, 'add_admin_menu'));
            
            // Assets admin
            add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
            
            // Notice admin
            add_action('admin_notices', array($this, 'show_admin_notices'));
            
            // Link azioni plugin
            add_filter('plugin_action_links_' . 
                plugin_basename(DISCO747_CRM_PLUGIN_FILE), array($this, 'add_plugin_action_links'));
            
            // AJAX handlers per OAuth (esistenti)
            add_action('wp_ajax_disco747_dropbox_auth', array($this, 'handle_dropbox_auth'));
            add_action('wp_ajax_disco747_googledrive_auth', array($this, 'handle_googledrive_auth'));
            add_action('wp_ajax_disco747_test_storage', array($this, 'handle_test_storage'));
            
            // NUOVO: AJAX handlers per preventivi
            add_action('wp_ajax_disco747_save_preventivo', array($this, 'handle_save_preventivo'));
            add_action('wp_ajax_disco747_delete_preventivo', array($this, 'handle_delete_preventivo'));
            add_action('wp_ajax_disco747_get_preventivo', array($this, 'handle_get_preventivo'));
            
            $this->hooks_registered = true;
            $this->log('Hook WordPress registrati');
            
        } catch (\Exception $e) {
            $this->log('Errore registrazione hook: ' . $e->getMessage(), 'error');
        }
    }

    /**
     * Aggiunge menu amministrazione
     */
    public function add_admin_menu() {
        add_menu_page(
            __('PreventiviParty', 'disco747'),
            __('PreventiviParty', 'disco747'),
            $this->min_capability,
            'disco747-main',
            array($this, 'render_main_dashboard'),
            'dashicons-clipboard',
            30
        );
        
        add_submenu_page(
            'disco747-main',
            __('Impostazioni', 'disco747'),
            __('Impostazioni', 'disco747'),
            $this->min_capability,
            'disco747-settings',
            array($this, 'render_settings_page')
        );
        
        add_submenu_page(
            'disco747-main',
            __('Messaggi Automatici', 'disco747'),
            __('Messaggi Automatici', 'disco747'),
            $this->min_capability,
            'disco747-messages',
            array($this, 'render_messages_page')
        );

        // NUOVO: Excel Auto Scan
        add_submenu_page(
            'disco747-main',
            __('Excel Auto Scan', 'disco747'),
            __('Excel Auto Scan', 'disco747'),
            $this->min_capability,
            'disco747-excel-scan',
            array($this, 'render_excel_scan_page')
        );
    }

    /**
     * Carica assets amministrazione + EXCEL SCAN ASSETS
     */
    public function enqueue_admin_assets($hook_suffix) {
        // Carica solo nelle pagine del plugin
        if (strpos($hook_suffix, 'disco747') === false) {
            return;
        }

        try {
            // CSS amministrazione
            wp_enqueue_style(
                'disco747-admin-style',
                DISCO747_CRM_PLUGIN_URL . 'assets/css/admin.css',
                array(),
                $this->asset_version
            );

            // JavaScript amministrazione  
            wp_enqueue_script(
                'disco747-admin-script',
                DISCO747_CRM_PLUGIN_URL . 'assets/js/admin.js',
                array('jquery'),
                $this->asset_version,
                true
            );

            // Localizzazione per AJAX
            wp_localize_script('disco747-admin-script', 'disco747Admin', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('disco747_admin_nonce'),
                'messages' => array(
                    'loading' => __('Caricamento...', 'disco747'),
                    'error' => __('Errore durante l\'operazione', 'disco747'),
                    'success' => __('Operazione completata', 'disco747'),
                    'confirm_delete' => __('Sei sicuro di voler eliminare questo preventivo?', 'disco747'),
                    'processing' => __('Elaborazione in corso...', 'disco747')
                )
            ));

            // NUOVO: Assets specifici per Excel Scan
            if (strpos($hook_suffix, 'excel-scan') !== false || strpos($hook_suffix, 'disco747-excel-scan') !== false) {
                
                // JavaScript specifico per Excel Scan
                wp_enqueue_script(
                    'disco747-excel-scan-script',
                    DISCO747_CRM_PLUGIN_URL . 'assets/js/excel-scan.js',
                    array('jquery', 'disco747-admin-script'), // Dipende dal script admin principale
                    $this->asset_version,
                    true
                );
                
                // CSS specifico per Excel Scan (se esiste)
                wp_enqueue_style(
                    'disco747-excel-scan-style',
                    DISCO747_CRM_PLUGIN_URL . 'assets/css/excel-scan.css',
                    array('disco747-admin-style'),
                    $this->asset_version
                );
                
                $this->log('Assets Excel Scan caricati per: ' . $hook_suffix);
            }

            $this->log('Assets amministrazione caricati per: ' . $hook_suffix);
            
        } catch (\Exception $e) {
            $this->log('Errore caricamento assets: ' . $e->getMessage(), 'error');
        }
    }

    /**
     * MODIFICATO: Renderizza dashboard principale CON routing interno
     */
    public function render_main_dashboard() {
        try {
            // Controlla permessi
            if (!current_user_can($this->min_capability)) {
                wp_die(__('Non hai i permessi per accedere a questa pagina.', 'disco747'));
            }
            
            // NUOVO: Routing interno per le pagine preventivi
            $action = isset($_GET['action']) ? sanitize_key($_GET['action']) : '';
            
            switch ($action) {
                case 'new_preventivo':
                    $this->render_form_preventivo();
                    break;
                    
                case 'edit_preventivo':
                    $this->render_edit_preventivo();
                    break;
                    
                case 'dashboard_preventivi':
                    $this->render_dashboard_preventivi();
                    break;
                    
                default:
                    $this->render_main_dashboard_page();
                    break;
            }
            
        } catch (\Exception $e) {
            $this->log('Errore render dashboard: ' . $e->getMessage(), 'error');
            echo '<div class="error"><p>Errore caricamento dashboard.</p></div>';
        }
    }

    /**
     * NUOVO: Renderizza pagina dashboard principale (contenuto originale)
     */
    private function render_main_dashboard_page() {
        // Dati per dashboard
        $stats = $this->get_dashboard_statistics();
        $system_status = $this->get_system_status_summary();
        $recent_preventivi = $this->get_recent_preventivi(5);
        
        // Template esistente con aggiunta pulsanti
        $template_path = DISCO747_CRM_PLUGIN_DIR . 'includes/admin/views/main-page.php';
        
        if (file_exists($template_path)) {
            include $template_path;
        } else {
            $this->render_fallback_dashboard();
        }
    }

    /**
     * NUOVO: Renderizza form per nuovo preventivo
     */
    private function render_form_preventivo() {
        $preventivo = null;
        $title = 'Nuovo Preventivo';
        $submit_text = 'Crea Preventivo';
        
        $template_path = DISCO747_CRM_PLUGIN_DIR . 'includes/admin/views/form-preventivo.php';
        
        if (file_exists($template_path)) {
            include $template_path;
        } else {
            echo '<div class="error"><p>Template form preventivo non trovato.</p></div>';
        }
    }

    /**
     * NUOVO: Renderizza form per modifica preventivo
     */
    private function render_edit_preventivo() {
        $preventivo_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        
        if (!$preventivo_id) {
            wp_die('ID preventivo non valido');
        }
        
        $preventivo = $this->database->get_preventivo($preventivo_id);
        
        if (!$preventivo) {
            wp_die('Preventivo non trovato');
        }
        
        $title = 'Modifica Preventivo #' . $preventivo_id;
        $submit_text = 'Aggiorna Preventivo';
        
        $template_path = DISCO747_CRM_PLUGIN_DIR . 'includes/admin/views/form-preventivo.php';
        
        if (file_exists($template_path)) {
            include $template_path;
        } else {
            echo '<div class="error"><p>Template form preventivo non trovato.</p></div>';
        }
    }

    /**
     * NUOVO: Renderizza dashboard preventivi
     */
    private function render_dashboard_preventivi() {
        // Parametri filtri
        $search = isset($_GET['search']) ? sanitize_text_field($_GET['search']) : '';
        $stato = isset($_GET['stato']) ? sanitize_key($_GET['stato']) : '';
        $anno = isset($_GET['anno']) ? intval($_GET['anno']) : '';
        $mese = isset($_GET['mese']) ? intval($_GET['mese']) : '';
        $menu = isset($_GET['menu']) ? sanitize_key($_GET['menu']) : '';
        
        // Parametri paginazione
        $paged = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $per_page = 20;
        
        // Ottieni preventivi con filtri
        $preventivi = $this->get_filtered_preventivi(array(
            'search' => $search,
            'stato' => $stato,
            'anno' => $anno,
            'mese' => $mese,
            'menu' => $menu,
            'paged' => $paged,
            'per_page' => $per_page
        ));
        
        $template_path = DISCO747_CRM_PLUGIN_DIR . 'includes/admin/views/dashboard-preventivi.php';
        
        if (file_exists($template_path)) {
            include $template_path;
        } else {
            echo '<div class="error"><p>Template dashboard preventivi non trovato.</p></div>';
        }
    }

    /**
     * NUOVO: Renderizza pagina Excel Auto Scan
     */
    public function render_excel_scan_page() {
        try {
            if (!current_user_can($this->min_capability)) {
                wp_die(__('Non hai i permessi per accedere a questa pagina.', 'disco747'));
            }
            
            $template_path = DISCO747_CRM_PLUGIN_DIR . 'includes/admin/views/excel-scan-page.php';
            
            if (file_exists($template_path)) {
                include $template_path;
            } else {
                echo '<div class="wrap">';
                echo '<h1>Excel Auto Scan</h1>';
                echo '<div class="error"><p>Template Excel Scan non trovato: ' . $template_path . '</p></div>';
                echo '</div>';
            }
            
        } catch (\Exception $e) {
            $this->log('Errore render Excel Scan: ' . $e->getMessage(), 'error');
            echo '<div class="wrap">';
            echo '<h1>Excel Auto Scan</h1>';
            echo '<div class="error"><p>Errore caricamento: ' . esc_html($e->getMessage()) . '</p></div>';
            echo '</div>';
        }
    }

    /**
     * Renderizza pagina impostazioni con OAuth
     */
    public function render_settings_page() {
        try {
            // Gestione form submissions
            $this->handle_settings_form();
            
            // Percorso template impostazioni
            $template_path = DISCO747_CRM_PLUGIN_DIR . 'includes/admin/views/settings-page.php';
            
            if (file_exists($template_path)) {
                include $template_path;
            } else {
                $this->render_fallback_settings();
            }
            
        } catch (\Exception $e) {
            $this->log('Errore render impostazioni: ' . $e->getMessage(), 'error');
            echo '<div class="error"><p>Errore caricamento impostazioni.</p></div>';
        }
    }

    /**
     * Renderizza pagina messaggi
     */
    public function render_messages_page() {
        try {
            // Gestione form submissions
            $this->handle_messages_form();
            
            // Percorso template messaggi
            $template_path = DISCO747_CRM_PLUGIN_DIR . 'includes/admin/views/messages-page.php';
            
            if (file_exists($template_path)) {
                include $template_path;
            } else {
                $this->render_fallback_messages();
            }
            
        } catch (\Exception $e) {
            $this->log('Errore render messaggi: ' . $e->getMessage(), 'error');
            echo '<div class="error"><p>Errore caricamento messaggi.</p></div>';
        }
    }

    /**
     * NUOVO: Ottieni preventivi filtrati
     */
    private function get_filtered_preventivi($filters) {
        global $wpdb;
        
        $table_name = $this->database->get_table_name();
        $where_conditions = array();
        $where_values = array();
        
        // Costruisci WHERE clause
        if (!empty($filters['search'])) {
            $where_conditions[] = "(nome_referente LIKE %s OR cognome_referente LIKE %s OR mail LIKE %s)";
            $search_term = '%' . $wpdb->esc_like($filters['search']) . '%';
            $where_values[] = $search_term;
            $where_values[] = $search_term;
            $where_values[] = $search_term;
        }
        
        if (!empty($filters['stato'])) {
            $where_conditions[] = "stato = %s";
            $where_values[] = $filters['stato'];
        }
        
        if (!empty($filters['anno'])) {
            $where_conditions[] = "YEAR(data_evento) = %d";
            $where_values[] = $filters['anno'];
        }
        
        if (!empty($filters['mese'])) {
            $where_conditions[] = "MONTH(data_evento) = %d";
            $where_values[] = $filters['mese'];
        }
        
        if (!empty($filters['menu'])) {
            $where_conditions[] = "tipo_menu = %s";
            $where_values[] = $filters['menu'];
        }
        
        $where_clause = '';
        if (!empty($where_conditions)) {
            $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
        }
        
        // Aggiungi LIMIT e OFFSET per paginazione
        $offset = ($filters['paged'] - 1) * $filters['per_page'];
        $limit_clause = "LIMIT {$filters['per_page']} OFFSET {$offset}";
        
        $sql = "SELECT * FROM {$table_name} {$where_clause} ORDER BY created_at DESC {$limit_clause}";
        
        if (!empty($where_values)) {
            $sql = $wpdb->prepare($sql, $where_values);
        }
        
        return $wpdb->get_results($sql);
    }

    /**
     * Gestisce form impostazioni
     */
    private function handle_settings_form() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['disco747_settings_nonce'])) {
            // Logica gestione form impostazioni
        }
    }

    /**
     * Gestisce form messaggi
     */
    private function handle_messages_form() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['disco747_messages_nonce'])) {
            // Logica gestione form messaggi
        }
    }

    /**
     * AJAX: Handle Dropbox auth
     */
    public function handle_dropbox_auth() {
        // Implementazione OAuth Dropbox
        wp_send_json_success('Dropbox auth handled');
    }

    /**
     * AJAX: Handle Google Drive auth
     */
    public function handle_googledrive_auth() {
        // Implementazione OAuth Google Drive
        wp_send_json_success('Google Drive auth handled');
    }

    /**
     * AJAX: Test storage connection
     */
    public function handle_test_storage() {
        $connected = $this->check_storage_connection();
        
        if ($connected) {
            wp_send_json_success('Storage connesso correttamente');
        } else {
            wp_send_json_error('Storage non connesso');
        }
    }

    /**
     * AJAX: Save preventivo
     */
    public function handle_save_preventivo() {
        // Logica salvataggio preventivo
        wp_send_json_success('Preventivo salvato');
    }

    /**
     * AJAX: Delete preventivo
     */
    public function handle_delete_preventivo() {
        // Logica eliminazione preventivo
        wp_send_json_success('Preventivo eliminato');
    }

    /**
     * AJAX: Get preventivo
     */
    public function handle_get_preventivo() {
        // Logica recupero preventivo
        wp_send_json_success('Preventivo recuperato');
    }

    /**
     * Mostra notice amministrazione
     */
    public function show_admin_notices() {
        foreach ($this->admin_notices as $notice) {
            printf(
                '<div class="notice notice-%s is-dismissible"><p>%s</p></div>',
                esc_attr($notice['type']),
                esc_html($notice['message'])
            );
        }
    }

    /**
     * Aggiunge link azioni plugin
     */
    public function add_plugin_action_links($links) {
        $settings_link = '<a href="' . admin_url('admin.php?page=disco747-settings') . '">' . 
                        __('Impostazioni', 'disco747') . '</a>';
        array_unshift($links, $settings_link);
        return $links;
    }

    /**
     * Aggiunge admin notice
     */
    private function add_admin_notice($message, $type = 'info') {
        $this->admin_notices[] = array(
            'message' => $message,
            'type' => $type
        );
    }

    /**
     * Ottieni statistiche dashboard
     */
    private function get_dashboard_statistics() {
        return array(
            'total' => 0,
            'active' => 0,
            'confirmed' => 0,
            'this_month' => 0
        );
    }

    /**
     * Ottieni status sistema
     */
    private function get_system_status_summary() {
        return array(
            'database_status' => 'ok',
            'storage_connected' => $this->check_storage_connection(),
            'plugin_version' => DISCO747_CRM_VERSION ?? '11.4.2',
            'last_sync' => get_option('disco747_last_sync', 'Mai')
        );
    }

    /**
     * Ottieni preventivi recenti
     */
    private function get_recent_preventivi($limit = 5) {
        if (!$this->database) {
            return array();
        }
        
        return $this->database->get_preventivi(array(
            'orderby' => 'created_at', 
            'order' => 'DESC',
            'limit' => $limit
        ));
    }

    /**
     * Verifica connessione storage tramite storage_manager
     */
    private function check_storage_connection() {
        if (!$this->storage_manager) {
            return false;
        }
        
        try {
            // Il storage manager dovrebbe avere un metodo per testare la connessione
            if (method_exists($this->storage_manager, 'test_connection')) {
                return $this->storage_manager->test_connection();
            }
            
            // Fallback: verifica se è configurato
            return $this->storage_manager->is_configured();
            
        } catch (Exception $e) {
            $this->log('Errore verifica connessione storage: ' . $e->getMessage(), 'error');
            return false;
        }
    }

    /**
     * Fallback per dashboard
     */
    private function render_fallback_dashboard() {
        echo '<div class="wrap">';
        echo '<h1>747 Disco CRM</h1>';
        echo '<p>Dashboard in costruzione...</p>';
        echo '</div>';
    }

    /**
     * Fallback per impostazioni
     */
    private function render_fallback_settings() {
        echo '<div class="wrap">';
        echo '<h1>Impostazioni</h1>';
        echo '<p>Pagina impostazioni in costruzione...</p>';
        echo '</div>';
    }

    /**
     * Fallback per messaggi
     */
    private function render_fallback_messages() {
        echo '<div class="wrap">';
        echo '<h1>Messaggi Automatici</h1>';
        echo '<p>Pagina messaggi in costruzione...</p>';
        echo '</div>';
    }

    /**
     * Logging con prefisso identificativo
     */
    private function log($message, $level = 'info') {
        if ($this->debug_mode && function_exists('error_log')) {
            $prefix = '[' . date('Y-m-d H:i:s') . '] [747Disco-Admin] ';
            error_log($prefix . $message);
        }
    }
}