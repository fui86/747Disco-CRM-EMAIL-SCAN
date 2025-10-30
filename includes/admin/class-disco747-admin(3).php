<?php
/**
 * Classe Admin Manager - 747 Disco CRM
 * Versione 11.8.3-FIXED - Correzione path e costanti
 *
 * @package    Disco747_CRM
 * @subpackage Admin
 * @version    11.8.3-FIXED
 */

namespace Disco747_CRM\Admin;

if (!defined('ABSPATH')) {
    exit('Accesso diretto non consentito');
}

class Disco747_Admin {
    
    private $config;
    private $database;
    private $auth;
    private $storage_manager;
    private $pdf_excel_handler;
    private $excel_handler;
    
    private $min_capability = 'manage_options';
    private $asset_version;
    private $admin_notices = array();
    private $hooks_registered = false;
    private $debug_mode = true;

    public function __construct() {
        $this->asset_version = defined('DISCO747_CRM_VERSION') ? DISCO747_CRM_VERSION : '11.8.3';
        add_action('init', array($this, 'delayed_init'), 10);
    }

    public function delayed_init() {
        try {
            $this->load_dependencies();
            $this->register_admin_hooks();
            $this->log('Admin Manager inizializzato');
        } catch (\Exception $e) {
            $this->log('Errore inizializzazione Admin: ' . $e->getMessage(), 'error');
            $this->add_admin_notice('Errore inizializzazione 747 Disco CRM.', 'error');
        }
    }

    private function load_dependencies() {
        $disco747_crm = disco747_crm();
        if (!$disco747_crm || !$disco747_crm->is_initialized()) {
            throw new \Exception('Plugin principale non ancora inizializzato');
        }
        $this->config = $disco747_crm->get_config();
        $this->database = $disco747_crm->get_database();
        $this->auth = $disco747_crm->get_auth();
        $this->storage_manager = $disco747_crm->get_storage_manager();
        $this->pdf_excel_handler = $disco747_crm->get_pdf();
        $this->excel_handler = $disco747_crm->get_excel();
    }

    private function register_admin_hooks() {
        if ($this->hooks_registered) return;
        try {
            add_action('admin_menu', array($this, 'add_admin_menu'));
            add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
            add_action('admin_notices', array($this, 'show_admin_notices'));
            add_filter('plugin_action_links_' . plugin_basename(DISCO747_CRM_PLUGIN_FILE), array($this, 'add_plugin_action_links'));
            
            // AJAX handlers
            add_action('wp_ajax_disco747_batch_scan_excel', array($this, 'handle_batch_scan'));
            add_action('wp_ajax_disco747_get_preventivo', array($this, 'handle_get_preventivo'));
            add_action('wp_ajax_disco747_delete_preventivo', array($this, 'handle_delete_preventivo'));
            add_action('wp_ajax_disco747_export_preventivi_csv', array($this, 'handle_export_csv'));
            
            $this->hooks_registered = true;
            $this->log('Hook WordPress registrati (incluso batch scan)');
        } catch (\Exception $e) {
            $this->log('Errore registrazione hook: ' . $e->getMessage(), 'error');
        }
    }

    public function add_admin_menu() {
        try {
            add_menu_page(
                __('PreventiviParty', 'disco747'),
                __('PreventiviParty', 'disco747'),
                $this->min_capability,
                'disco747-crm',
                array($this, 'render_main_dashboard'),
                'dashicons-clipboard',
                30
            );
            add_submenu_page(
                'disco747-crm',
                __('Impostazioni', 'disco747'),
                __('Impostazioni', 'disco747'),
                $this->min_capability,
                'disco747-settings',
                array($this, 'render_settings_page')
            );
            add_submenu_page(
                'disco747-crm',
                __('Messaggi Automatici', 'disco747'),
                __('Messaggi Automatici', 'disco747'),
                $this->min_capability,
                'disco747-messages',
                array($this, 'render_messages_page')
            );
            add_submenu_page(
                'disco747-crm',
                __('Scansione Excel Auto', 'disco747'),
                __('Scansione Excel Auto', 'disco747'),
                $this->min_capability,
                'disco747-scan-excel',
                array($this, 'render_scan_excel_page')
            );
            add_submenu_page(
                'disco747-crm',
                __('View Database', 'disco747'),
                __('📊 View Database', 'disco747'),
                $this->min_capability,
                'disco747-view-preventivi',
                array($this, 'render_view_preventivi_page')
            );
            if (get_option('disco747_debug_mode', false)) {
                add_submenu_page(
                    'disco747-crm',
                    __('Debug & Test', 'disco747'),
                    __('Debug & Test', 'disco747'),
                    $this->min_capability,
                    'disco747-debug',
                    array($this, 'render_debug_page')
                );
            }
            $this->log('Menu amministrazione aggiunto');
        } catch (\Exception $e) {
            $this->log('Errore aggiunta menu: ' . $e->getMessage(), 'error');
        }
    }

    public function enqueue_admin_assets($hook_suffix) {
        $this->log('Hook suffix: ' . $hook_suffix);
        
        if (strpos($hook_suffix, 'disco747') === false) return;
        
        try {
            // ✅ CORRETTO: Usa DISCO747_CRM_PLUGIN_URL invece di DISCO747_CRM_ASSETS_URL
            wp_enqueue_style('disco747-admin-style', DISCO747_CRM_PLUGIN_URL . 'assets/css/admin.css', array(), $this->asset_version);
            wp_enqueue_script('disco747-admin-script', DISCO747_CRM_PLUGIN_URL . 'assets/js/admin.js', array('jquery'), $this->asset_version, true);
            
            // Assets specifici Excel Scan
            if (strpos($hook_suffix, 'disco747-scan-excel') !== false) {
                $this->log('EXCEL SCAN RILEVATO!');
                
                wp_enqueue_script(
                    'disco747-excel-scan-js',
                    DISCO747_CRM_PLUGIN_URL . 'assets/js/excel-scan.js',
                    array('jquery'),
                    $this->asset_version,
                    true
                );
                
                wp_localize_script('disco747-excel-scan-js', 'disco747ExcelScan', array(
                    'ajaxurl' => admin_url('admin-ajax.php'),
                    'nonce' => wp_create_nonce('disco747_batch_scan'),
                    'i18n' => array(
                        'error' => __('Errore', 'disco747'),
                        'success' => __('Successo', 'disco747'),
                        'processing' => __('Elaborazione in corso...', 'disco747')
                    )
                ));
                
                $this->log('Assets Excel Scan caricati');
            }
            
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
            
            $this->log('Assets amministrazione caricati per: ' . $hook_suffix);
        } catch (\Exception $e) {
            $this->log('Errore caricamento assets: ' . $e->getMessage(), 'error');
        }
    }

    // ========================================================================
    // RENDER METHODS
    // ========================================================================

    public function render_main_dashboard() {
        if (!current_user_can($this->min_capability)) {
            wp_die(__('Non hai i permessi per accedere a questa pagina.', 'disco747'));
        }
        
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
                // ✅ CORRETTO: Verifica che il file esista prima di includerlo
                $main_dashboard_file = DISCO747_CRM_PLUGIN_DIR . 'includes/admin/views/dashboard-preventivi.php';
                if (file_exists($main_dashboard_file)) {
                    require_once $main_dashboard_file;
                } else {
                    // Fallback temporaneo
                    echo '<div class="wrap"><h1>Dashboard Preventivi</h1>';
                    echo '<p><a href="?page=disco747-crm&action=new_preventivo" class="button button-primary">Nuovo Preventivo</a></p>';
                    echo '<p><a href="?page=disco747-crm&action=dashboard_preventivi" class="button">Gestisci Preventivi</a></p>';
                    echo '</div>';
                }
                break;
        }
    }

    private function render_form_preventivo() {
        $form_file = DISCO747_CRM_PLUGIN_DIR . 'includes/admin/views/form-preventivo.php';
        if (file_exists($form_file)) {
            require_once $form_file;
        } else {
            echo '<div class="wrap"><h1>Form Preventivo</h1><p>Form in caricamento...</p></div>';
        }
    }

    private function render_edit_preventivo() {
        $edit_file = DISCO747_CRM_PLUGIN_DIR . 'includes/admin/views/edit-preventivo.php';
        if (file_exists($edit_file)) {
            require_once $edit_file;
        } else {
            echo '<div class="wrap"><h1>Modifica Preventivo</h1><p>Editor in caricamento...</p></div>';
        }
    }

    private function render_dashboard_preventivi() {
        $dashboard_file = DISCO747_CRM_PLUGIN_DIR . 'includes/admin/views/dashboard-preventivi.php';
        if (file_exists($dashboard_file)) {
            require_once $dashboard_file;
        } else {
            echo '<div class="wrap"><h1>Dashboard Preventivi</h1><p>Dashboard in caricamento...</p></div>';
        }
    }

    public function render_settings_page() {
        if (!current_user_can($this->min_capability)) {
            wp_die(__('Non hai i permessi.', 'disco747'));
        }
        $settings_file = DISCO747_CRM_PLUGIN_DIR . 'includes/admin/views/settings-page.php';
        if (file_exists($settings_file)) {
            require_once $settings_file;
        }
    }

    public function render_messages_page() {
        if (!current_user_can($this->min_capability)) {
            wp_die(__('Non hai i permessi.', 'disco747'));
        }
        $messages_file = DISCO747_CRM_PLUGIN_DIR . 'includes/admin/views/messages-page.php';
        if (file_exists($messages_file)) {
            require_once $messages_file;
        }
    }

    public function render_scan_excel_page() {
        if (!current_user_can($this->min_capability)) {
            wp_die(__('Non hai i permessi.', 'disco747'));
        }

        $is_googledrive_configured = false;
        $excel_files_list = array();

        try {
            $gd_handler = $this->storage_manager->get_handler();
            if ($gd_handler && method_exists($gd_handler, 'is_connected')) {
                $is_googledrive_configured = $gd_handler->is_connected();
            }
        } catch (\Exception $e) {
            $this->log('Errore verifica GoogleDrive: ' . $e->getMessage(), 'error');
        }

        require_once DISCO747_CRM_PLUGIN_DIR . 'includes/admin/views/excel-scan-page.php';
    }

    public function render_view_preventivi_page() {
        if (!current_user_can($this->min_capability)) {
            wp_die('Non hai i permessi per accedere a questa pagina.');
        }
        
        require_once DISCO747_CRM_PLUGIN_DIR . 'includes/admin/views/view-preventivi-page.php';
    }

    public function render_debug_page() {
        if (!current_user_can($this->min_capability)) {
            wp_die(__('Non hai i permessi.', 'disco747'));
        }
        $debug_file = DISCO747_CRM_PLUGIN_DIR . 'includes/admin/views/debug-page.php';
        if (file_exists($debug_file)) {
            require_once $debug_file;
        }
    }

    // ========================================================================
    // AJAX HANDLERS
    // ========================================================================

    public function handle_batch_scan() {
        $this->log('handle_batch_scan chiamato!');

        try {
            if (!current_user_can($this->min_capability)) {
                throw new \Exception('Permessi insufficienti');
            }

            $this->log('Permessi OK - avvio batch scan reale');

            if (!class_exists('Disco747_CRM\\Storage\\Disco747_GoogleDrive_Sync')) {
                throw new \Exception('Classe GoogleDrive_Sync non trovata');
            }

            if (!class_exists('Disco747_CRM\\Storage\\Disco747_GoogleDrive')) {
                throw new \Exception('Classe GoogleDrive non trovata');
            }

            $googledrive_handler = new \Disco747_CRM\Storage\Disco747_GoogleDrive();
            $gdrive_sync = new \Disco747_CRM\Storage\Disco747_GoogleDrive_Sync($googledrive_handler);

            if (!$gdrive_sync->is_available()) {
                throw new \Exception('GoogleDrive Sync non disponibile');
            }

            $result = $gdrive_sync->scan_excel_files_batch(true, 5);
            wp_send_json_success($result);

        } catch (\Exception $e) {
            $this->log('Errore handle_batch_scan: ' . $e->getMessage(), 'error');
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }

    public function handle_get_preventivo() {
        try {
            if (!wp_verify_nonce($_POST['nonce'], 'disco747_get_preventivo')) {
                throw new \Exception('Nonce non valido');
            }

            if (!current_user_can($this->min_capability)) {
                throw new \Exception('Permessi insufficienti');
            }

            $preventivo_id = intval($_POST['preventivo_id'] ?? 0);
            if ($preventivo_id <= 0) {
                throw new \Exception('ID preventivo non valido');
            }

            global $wpdb;
            $table_name = $wpdb->prefix . 'disco747_preventivi';
            
            $preventivo = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$table_name} WHERE id = %d",
                $preventivo_id
            ), ARRAY_A);

            if (!$preventivo) {
                throw new \Exception('Preventivo non trovato');
            }

            wp_send_json_success($preventivo);

        } catch (\Exception $e) {
            $this->log('Errore get_preventivo: ' . $e->getMessage(), 'error');
            wp_send_json_error($e->getMessage());
        }
    }

    public function handle_delete_preventivo() {
        try {
            if (!wp_verify_nonce($_POST['nonce'], 'disco747_delete_preventivo')) {
                throw new \Exception('Nonce non valido');
            }

            if (!current_user_can($this->min_capability)) {
                throw new \Exception('Permessi insufficienti');
            }

            $preventivo_id = intval($_POST['preventivo_id'] ?? 0);
            if ($preventivo_id <= 0) {
                throw new \Exception('ID preventivo non valido');
            }

            global $wpdb;
            $table_name = $wpdb->prefix . 'disco747_preventivi';
            
            $result = $wpdb->delete($table_name, array('id' => $preventivo_id), array('%d'));

            if ($result === false) {
                throw new \Exception('Errore durante l\'eliminazione');
            }

            $this->log("Preventivo #{$preventivo_id} eliminato con successo");
            wp_send_json_success(array('message' => 'Preventivo eliminato con successo'));

        } catch (\Exception $e) {
            $this->log('Errore delete_preventivo: ' . $e->getMessage(), 'error');
            wp_send_json_error($e->getMessage());
        }
    }

    public function handle_export_csv() {
        try {
            if (!wp_verify_nonce($_GET['nonce'], 'disco747_export_csv')) {
                wp_die('Nonce non valido');
            }

            if (!current_user_can($this->min_capability)) {
                wp_die('Permessi insufficienti');
            }

            global $wpdb;
            $table_name = $wpdb->prefix . 'disco747_preventivi';

            $where = array('1=1');
            $where_values = array();

            if (!empty($_GET['search'])) {
                $where[] = "(nome_cliente LIKE %s OR email LIKE %s)";
                $search = '%' . $wpdb->esc_like($_GET['search']) . '%';
                $where_values[] = $search;
                $where_values[] = $search;
            }

            if (!empty($_GET['stato'])) {
                $where[] = "stato = %s";
                $where_values[] = sanitize_key($_GET['stato']);
            }

            if (!empty($_GET['menu'])) {
                $where[] = "tipo_menu LIKE %s";
                $where_values[] = '%' . $wpdb->esc_like($_GET['menu']) . '%';
            }

            if (!empty($_GET['anno'])) {
                $where[] = "YEAR(data_evento) = %d";
                $where_values[] = intval($_GET['anno']);
            }

            if (!empty($_GET['mese'])) {
                $where[] = "MONTH(data_evento) = %d";
                $where_values[] = intval($_GET['mese']);
            }

            $where_clause = implode(' AND ', $where);

            if (!empty($where_values)) {
                $query = $wpdb->prepare("SELECT * FROM {$table_name} WHERE {$where_clause} ORDER BY created_at DESC", $where_values);
            } else {
                $query = "SELECT * FROM {$table_name} WHERE {$where_clause} ORDER BY created_at DESC";
            }

            $preventivi = $wpdb->get_results($query, ARRAY_A);

            $filename = 'preventivi_' . date('Y-m-d_His') . '.csv';
            
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            
            $output = fopen('php://output', 'w');
            
            fputcsv($output, array(
                'ID', 'Preventivo ID', 'Data Evento', 'Nome Cliente', 'Email', 
                'Telefono', 'Tipo Evento', 'Menu', 'Invitati', 'Importo', 
                'Acconto', 'Stato', 'Creato il'
            ));

            foreach ($preventivi as $prev) {
                fputcsv($output, array(
                    $prev['id'],
                    $prev['preventivo_id'],
                    $prev['data_evento'],
                    $prev['nome_cliente'],
                    $prev['email'],
                    $prev['telefono'],
                    $prev['tipo_evento'],
                    $prev['tipo_menu'],
                    $prev['numero_invitati'],
                    $prev['importo_totale'],
                    $prev['acconto'],
                    $prev['stato'],
                    $prev['created_at']
                ));
            }

            fclose($output);
            exit;

        } catch (\Exception $e) {
            wp_die('Errore export: ' . $e->getMessage());
        }
    }

    // ========================================================================
    // UTILITY METHODS
    // ========================================================================

    public function show_admin_notices() {
        foreach ($this->admin_notices as $notice) {
            printf(
                '<div class="notice notice-%s is-dismissible"><p>%s</p></div>',
                esc_attr($notice['type']),
                esc_html($notice['message'])
            );
        }
    }

    private function add_admin_notice($message, $type = 'info') {
        $this->admin_notices[] = array(
            'message' => $message,
            'type' => $type
        );
    }

    public function add_plugin_action_links($links) {
        $settings_link = '<a href="' . admin_url('admin.php?page=disco747-settings') . '">' . __('Impostazioni', 'disco747') . '</a>';
        array_unshift($links, $settings_link);
        return $links;
    }

    private function log($message, $level = 'INFO') {
        if (!$this->debug_mode) return;
        $prefix = '[747Disco-Admin]';
        $timestamp = current_time('mysql');
        $log_message = "[{$timestamp}] {$prefix} {$message}";
        error_log($log_message);
    }
}