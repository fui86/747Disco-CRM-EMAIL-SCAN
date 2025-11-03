<?php
/**
 * Admin Manager per 747 Disco CRM
 * Gestisce menu, pagine admin e interfaccia backend
 * 
 * @package    Disco747_CRM
 * @subpackage Admin
 * @since      11.8.0
 */

namespace Disco747_CRM\Admin;

// Sicurezza: impedisce l'accesso diretto
if (!defined('ABSPATH')) {
    exit('Accesso diretto non consentito');
}

/**
 * Classe Admin Manager
 */
class Disco747_Admin {
    
    /**
     * Istanza singleton
     */
    private static $instance = null;
    
    /**
     * Costruttore privato per singleton
     */
    private function __construct() {
        $this->init_hooks();
    }
    
    /**
     * Ottieni istanza singleton
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Inizializza hooks WordPress
     */
    private function init_hooks() {
        // Registra menu admin
        add_action('admin_menu', array($this, 'register_admin_menu'));
        
        // Registra assets admin
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        
        // AJAX handlers
        add_action('wp_ajax_disco747_get_preventivi', array($this, 'ajax_get_preventivi'));
        add_action('wp_ajax_disco747_save_preventivo', array($this, 'ajax_save_preventivo'));
    }
    
    /**
     * Registra menu admin di WordPress
     */
    public function register_admin_menu() {
        // Menu principale
        add_menu_page(
            '747 Disco CRM',                    // Page title
            '?? 747 Disco CRM',                 // Menu title (emoji musica)
            'manage_options',                   // Capability
            'disco747-crm',                     // Menu slug
            array($this, 'render_main_page'),   // Callback
            'dashicons-admin-multisite',        // Icon
            30                                  // Position
        );
        
        // Sottomenu: Dashboard
        add_submenu_page(
            'disco747-crm',
            'Dashboard 747 Disco',
            '?? Dashboard',                     // Emoji casa
            'manage_options',
            'disco747-crm',
            array($this, 'render_main_page')
        );
        
        // Sottomenu: View Database
        add_submenu_page(
            'disco747-crm',
            'View Database - 747 Disco',
            '??? View Database',                // Emoji database (simbolo corretto!)
            'manage_options',
            'disco747-database',
            array($this, 'render_database_page')
        );
        
        // Sottomenu: Preventivi
        add_submenu_page(
            'disco747-crm',
            'Gestione Preventivi',
            '?? Preventivi',                    // Emoji clipboard
            'manage_options',
            'disco747-preventivi',
            array($this, 'render_preventivi_page')
        );
        
        // Sottomenu: Excel Scan
        add_submenu_page(
            'disco747-crm',
            'Excel Scanner',
            '?? Excel Scan',                    // Emoji grafico
            'manage_options',
            'disco747-excel-scan',
            array($this, 'render_excel_scan_page')
        );
        
        // Sottomenu: Storage Cloud
        add_submenu_page(
            'disco747-crm',
            'Storage Cloud',
            '?? Storage',                       // Emoji cloud
            'manage_options',
            'disco747-storage',
            array($this, 'render_storage_page')
        );
        
        // Sottomenu: Messaggi
        add_submenu_page(
            'disco747-crm',
            'Template Messaggi',
            '?? Messaggi',                      // Emoji chat
            'manage_options',
            'disco747-messages',
            array($this, 'render_messages_page')
        );
        
        // Sottomenu: Statistiche
        add_submenu_page(
            'disco747-crm',
            'Statistiche',
            '?? Stats',                         // Emoji grafico crescita
            'manage_options',
            'disco747-stats',
            array($this, 'render_stats_page')
        );
        
        // Sottomenu: Tools
        add_submenu_page(
            'disco747-crm',
            'Strumenti',
            '?? Tools',                         // Emoji chiave inglese
            'manage_options',
            'disco747-tools',
            array($this, 'render_tools_page')
        );
        
        // Sottomenu: Impostazioni
        add_submenu_page(
            'disco747-crm',
            'Impostazioni',
            '?? Impostazioni',                  // Emoji ingranaggio
            'manage_options',
            'disco747-settings',
            array($this, 'render_settings_page')
        );
        
        // Sottomenu: System Info (nascosto)
        add_submenu_page(
            'disco747-crm',
            'System Info',
            '?? System',                        // Emoji lente
            'manage_options',
            'disco747-system',
            array($this, 'render_system_page')
        );
        
        // Sottomenu: Debug (solo per sviluppatori)
        if (defined('DISCO747_CRM_DEBUG') && DISCO747_CRM_DEBUG) {
            add_submenu_page(
                'disco747-crm',
                'Debug Info',
                '?? Debug',                     // Emoji bug
                'manage_options',
                'disco747-debug',
                array($this, 'render_debug_page')
            );
        }
    }
    
    /**
     * Carica assets admin (CSS e JS)
     */
    public function enqueue_admin_assets($hook) {
        // Carica solo nelle pagine del plugin
        if (strpos($hook, 'disco747') === false) {
            return;
        }
        
        // CSS Admin
        wp_enqueue_style(
            'disco747-admin-css',
            DISCO747_CRM_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            DISCO747_CRM_VERSION
        );
        
        // CSS Excel Scan (solo per quella pagina)
        if (strpos($hook, 'excel-scan') !== false) {
            wp_enqueue_style(
                'disco747-excel-scan-css',
                DISCO747_CRM_PLUGIN_URL . 'assets/css/excel-scan.css',
                array(),
                DISCO747_CRM_VERSION
            );
        }
        
        // JS Admin
        wp_enqueue_script(
            'disco747-admin-js',
            DISCO747_CRM_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery'),
            DISCO747_CRM_VERSION,
            true
        );
        
        // JS Excel Scan (solo per quella pagina)
        if (strpos($hook, 'excel-scan') !== false) {
            wp_enqueue_script(
                'disco747-excel-scan-js',
                DISCO747_CRM_PLUGIN_URL . 'assets/js/excel-scan.js',
                array('jquery'),
                DISCO747_CRM_VERSION,
                true
            );
        }
        
        // Localizza script per AJAX
        wp_localize_script('disco747-admin-js', 'disco747Admin', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('disco747_admin_nonce'),
            'messages' => array(
                'confirm_delete' => __('Sei sicuro di voler eliminare questo preventivo?', 'disco747'),
                'confirm_cancel' => __('Sei sicuro di voler annullare questo preventivo?', 'disco747'),
                'error_generic' => __('Si ? verificato un errore. Riprova.', 'disco747'),
                'success_saved' => __('Dati salvati con successo!', 'disco747'),
            )
        ));
    }
    
    // ============================================================================
    // RENDER METHODS - Pagine Admin
    // ============================================================================
    
    /**
     * Render pagina principale / Dashboard
     */
    public function render_main_page() {
        $view_file = DISCO747_CRM_PLUGIN_DIR . 'includes/admin/views/main-page.php';
        if (file_exists($view_file)) {
            include $view_file;
        } else {
            echo '<div class="wrap">';
            echo '<h1>?? 747 Disco CRM - Dashboard</h1>';
            echo '<p>File view non trovato: ' . esc_html($view_file) . '</p>';
            echo '</div>';
        }
    }
    
    /**
     * Render pagina Database
     */
    public function render_database_page() {
        $view_file = DISCO747_CRM_PLUGIN_DIR . 'includes/admin/views/dashboard-preventivi.php';
        if (file_exists($view_file)) {
            include $view_file;
        } else {
            echo '<div class="wrap">';
            echo '<h1>??? View Database - 747 Disco CRM</h1>';
            echo '<p>Pagina database in costruzione...</p>';
            echo '</div>';
        }
    }
    
    /**
     * Render pagina Preventivi
     */
    public function render_preventivi_page() {
        $view_file = DISCO747_CRM_PLUGIN_DIR . 'includes/admin/views/dashboard-preventivi.php';
        if (file_exists($view_file)) {
            include $view_file;
        } else {
            echo '<div class="wrap">';
            echo '<h1>?? Gestione Preventivi</h1>';
            echo '<p>Pagina preventivi in costruzione...</p>';
            echo '</div>';
        }
    }
    
    /**
     * Render pagina Excel Scan
     */
    public function render_excel_scan_page() {
        $view_file = DISCO747_CRM_PLUGIN_DIR . 'includes/admin/views/excel-scan-page.php';
        if (file_exists($view_file)) {
            include $view_file;
        } else {
            echo '<div class="wrap">';
            echo '<h1>?? Excel Scanner</h1>';
            echo '<p>Pagina Excel Scan in costruzione...</p>';
            echo '</div>';
        }
    }
    
    /**
     * Render pagina Storage
     */
    public function render_storage_page() {
        $view_file = DISCO747_CRM_PLUGIN_DIR . 'includes/admin/views/storage-page.php';
        if (file_exists($view_file)) {
            include $view_file;
        } else {
            echo '<div class="wrap">';
            echo '<h1>?? Storage Cloud</h1>';
            echo '<p>Pagina storage in costruzione...</p>';
            echo '</div>';
        }
    }
    
    /**
     * Render pagina Messaggi
     */
    public function render_messages_page() {
        $view_file = DISCO747_CRM_PLUGIN_DIR . 'includes/admin/views/messages-page.php';
        if (file_exists($view_file)) {
            include $view_file;
        } else {
            echo '<div class="wrap">';
            echo '<h1>?? Template Messaggi</h1>';
            echo '<p>Pagina messaggi in costruzione...</p>';
            echo '</div>';
        }
    }
    
    /**
     * Render pagina Statistiche
     */
    public function render_stats_page() {
        $view_file = DISCO747_CRM_PLUGIN_DIR . 'includes/admin/views/stats-page.php';
        if (file_exists($view_file)) {
            include $view_file;
        } else {
            echo '<div class="wrap">';
            echo '<h1>?? Statistiche</h1>';
            echo '<p>Pagina statistiche in costruzione...</p>';
            echo '</div>';
        }
    }
    
    /**
     * Render pagina Tools
     */
    public function render_tools_page() {
        $view_file = DISCO747_CRM_PLUGIN_DIR . 'includes/admin/views/tools-page.php';
        if (file_exists($view_file)) {
            include $view_file;
        } else {
            echo '<div class="wrap">';
            echo '<h1>?? Strumenti</h1>';
            echo '<p>Pagina tools in costruzione...</p>';
            echo '</div>';
        }
    }
    
    /**
     * Render pagina Impostazioni
     */
    public function render_settings_page() {
        $view_file = DISCO747_CRM_PLUGIN_DIR . 'includes/admin/views/settings-page.php';
        if (file_exists($view_file)) {
            include $view_file;
        } else {
            echo '<div class="wrap">';
            echo '<h1>?? Impostazioni</h1>';
            echo '<p>Pagina impostazioni in costruzione...</p>';
            echo '</div>';
        }
    }
    
    /**
     * Render pagina System Info
     */
    public function render_system_page() {
        $view_file = DISCO747_CRM_PLUGIN_DIR . 'includes/admin/views/system-page.php';
        if (file_exists($view_file)) {
            include $view_file;
        } else {
            echo '<div class="wrap">';
            echo '<h1>?? System Info</h1>';
            echo '<p>Pagina system info in costruzione...</p>';
            echo '</div>';
        }
    }
    
    /**
     * Render pagina Debug
     */
    public function render_debug_page() {
        $view_file = DISCO747_CRM_PLUGIN_DIR . 'includes/admin/views/debug-page.php';
        if (file_exists($view_file)) {
            include $view_file;
        } else {
            echo '<div class="wrap">';
            echo '<h1>?? Debug Info</h1>';
            echo '<p>Pagina debug in costruzione...</p>';
            echo '</div>';
        }
    }
    
    // ============================================================================
    // AJAX HANDLERS
    // ============================================================================
    
    /**
     * AJAX: Ottieni lista preventivi
     */
    public function ajax_get_preventivi() {
        // Verifica nonce
        check_ajax_referer('disco747_admin_nonce', 'nonce');
        
        // Verifica permessi
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permessi insufficienti');
        }
        
        // TODO: Implementare logica recupero preventivi dal database
        
        wp_send_json_success(array(
            'preventivi' => array(),
            'total' => 0
        ));
    }
    
    /**
     * AJAX: Salva preventivo
     */
    public function ajax_save_preventivo() {
        // Verifica nonce
        check_ajax_referer('disco747_admin_nonce', 'nonce');
        
        // Verifica permessi
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permessi insufficienti');
        }
        
        // TODO: Implementare logica salvataggio preventivo
        
        wp_send_json_success(array(
            'message' => 'Preventivo salvato con successo!'
        ));
    }
}
