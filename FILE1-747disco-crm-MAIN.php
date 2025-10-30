<?php
/**
 * ISTRUZIONI: Questo file deve sostituire 747disco-crm.php
 * 
 * ⚠️ MODIFICHE MINIME: Solo le righe 335 e 353 sono cambiate per caricare i nuovi file
 * Tutto il resto è identico al file originale - NON tocca il form o altre funzionalità
 */

/**
 * Plugin Name: 747 Disco CRM - PreventiviParty Enhanced
 * Plugin URI: https://747disco.it
 * Description: Sistema CRM completo per la gestione dei preventivi della location 747 Disco
 * Version: 11.8.0
 * Author: 747 Disco Team
 * Author URI: https://747disco.it
 * Text Domain: disco747
 * Domain Path: /languages/
 * Requires at least: 5.8
 * Tested up to: 6.4.2
 * Requires PHP: 7.4
 * Network: false
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

if (!defined('ABSPATH')) {
    exit('Accesso diretto non consentito');
}

// Costanti
define('DISCO747_CRM_VERSION', '11.8.0');
define('DISCO747_CRM_PLUGIN_FILE', __FILE__);
define('DISCO747_CRM_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('DISCO747_CRM_PLUGIN_URL', plugin_dir_url(__FILE__));
define('DISCO747_CRM_PLUGIN_BASENAME', plugin_basename(__FILE__));
define('DISCO747_CRM_DB_PREFIX', 'disco747_');
define('DISCO747_CRM_DEBUG', true);

final class Disco747_CRM_Plugin {
    
    private static $instance = null;
    private $config = null;
    private $database = null;
    private $auth = null;
    private $admin = null;
    private $frontend = null;
    private $storage_manager = null;
    private $email_manager = null;
    private $pdf_generator = null;
    private $excel_generator = null;
    private $gdrive_sync = null;
    private $forms_handler = null;
    private $initialized = false;
    private $debug_mode = DISCO747_CRM_DEBUG;
    
    private function __construct() {
        register_activation_hook(__FILE__, array($this, 'activate_plugin'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate_plugin'));
        add_action('plugins_loaded', array($this, 'init_plugin'), 10);
        add_action('wp_scheduled_delete', array($this, 'cleanup_old_files'));
    }

    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function init_plugin() {
        if ($this->initialized) {
            return;
        }

        try {
            $this->public_log('🚀 Inizializzazione 747 Disco CRM v' . DISCO747_CRM_VERSION);
            $this->load_core_classes();
            $this->init_core_components();
            $this->load_additional_components();
            $this->init_wordpress_hooks();
            $this->initialized = true;
            $this->public_log('✅ Plugin inizializzato correttamente');
        } catch (Exception $e) {
            $this->public_log('❌ Errore inizializzazione: ' . $e->getMessage(), 'ERROR');
        }
    }

    private function load_core_classes() {
        $core_files = array(
            'includes/core/class-disco747-config.php',
            'includes/core/class-disco747-database.php',
            'includes/core/class-disco747-auth.php',
            'includes/admin/class-disco747-admin.php',
            'includes/storage/class-disco747-googledrive.php',
            'includes/storage/class-disco747-dropbox.php',
            'includes/storage/class-disco747-storage-manager.php',
            'includes/handlers/class-disco747-excel-scan-handler.php',
            'includes/admin/ajax-handlers.php'
        );

        foreach ($core_files as $file) {
            $file_path = DISCO747_CRM_PLUGIN_DIR . $file;
            if (file_exists($file_path)) {
                require_once $file_path;
                $this->public_log('✅ Core caricato: ' . $file);
            }
        }

        spl_autoload_register(function($class) {
            if (strpos($class, 'Disco747_CRM') !== 0) {
                return;
            }
            $class_file = str_replace('\\', '/', $class);
            $class_file = str_replace('Disco747_CRM/', '', $class_file);
            $file = DISCO747_CRM_PLUGIN_DIR . 'includes/' . strtolower($class_file) . '.php';
            if (file_exists($file)) {
                require_once $file;
            }
        });

        $this->public_log('✅ Autoloader caricato (' . count($core_files) . ' file core)');
    }

    private function init_core_components() {
        if (class_exists('Disco747_CRM\\Core\\Disco747_Config')) {
            $this->config = new Disco747_CRM\Core\Disco747_Config();
        }
        
        if (class_exists('Disco747_CRM\\Core\\Disco747_Database')) {
            $this->database = new Disco747_CRM\Core\Disco747_Database();
        }
        
        if (class_exists('Disco747_CRM\\Core\\Disco747_Auth')) {
            $this->auth = new Disco747_CRM\Core\Disco747_Auth();
        }
        
        if (class_exists('Disco747_CRM\\Storage\\Disco747_GoogleDrive')) {
            $googledrive_handler = new Disco747_CRM\Storage\Disco747_GoogleDrive();
        }
        
        if (class_exists('Disco747_CRM\\Storage\\Disco747_Dropbox')) {
            $dropbox_handler = new Disco747_CRM\Storage\Disco747_Dropbox();
        }
        
        if (class_exists('Disco747_CRM\\Storage\\Disco747_Storage_Manager')) {
            $this->storage_manager = new Disco747_CRM\Storage\Disco747_Storage_Manager();
        }
        
        $this->public_log('✅ Componenti core inizializzati');
        
        if (is_admin()) {
            if (class_exists('Disco747_CRM\\Admin\\Disco747_Admin')) {
                $this->admin = new Disco747_CRM\Admin\Disco747_Admin();
                $this->public_log('✅ Admin Manager caricato');
            }
        }
    }

    private function load_additional_components() {
        $components = array(
            'includes/generators/class-disco747-pdf.php' => 'pdf_generator',
            'includes/generators/class-disco747-excel.php' => 'excel_generator',
            'includes/communication/class-disco747-email.php' => 'email_manager',
            'includes/communication/class-disco747-messaging.php' => 'messaging'
        );

        foreach ($components as $file => $component) {
            $file_path = DISCO747_CRM_PLUGIN_DIR . $file;
            if (file_exists($file_path)) {
                require_once $file_path;
                $this->public_log('✅ Caricato: ' . $file);
                
                if ($component === 'pdf_generator' && class_exists('Disco747_CRM\\Generators\\Disco747_PDF')) {
                    $this->pdf_generator = new Disco747_CRM\Generators\Disco747_PDF();
                    $this->public_log('✅ PDF Generator inizializzato');
                }
                
                if ($component === 'excel_generator' && class_exists('Disco747_CRM\\Generators\\Disco747_Excel')) {
                    $this->excel_generator = new Disco747_CRM\Generators\Disco747_Excel();
                    $this->public_log('✅ Excel Generator inizializzato');
                }
                
                if ($component === 'email_manager' && class_exists('Disco747_CRM\\Communication\\Disco747_Email')) {
                    $this->email_manager = new Disco747_CRM\Communication\Disco747_Email();
                }
            }
        }
    }

    private function init_wordpress_hooks() {
        // ⚠️ MODIFICA 1: Carica il file -new per Google Drive Sync
        try {
            add_action('init', function() {
                $gdrive_sync_path = DISCO747_CRM_PLUGIN_DIR . 'includes/storage/class-disco747-googledrive-sync-new.php';
                if (file_exists($gdrive_sync_path)) {
                    require_once $gdrive_sync_path;
                    $this->public_log('✅ Caricato: includes/storage/class-disco747-googledrive-sync-new.php');
                    
                    if (class_exists('Disco747_CRM\\Storage\\Disco747_GoogleDrive_Sync')) {
                        $this->gdrive_sync = new Disco747_CRM\Storage\Disco747_GoogleDrive_Sync();
                        $this->public_log('✅ Google Drive Sync inizializzato');
                    }
                }
            });
        } catch (Exception $e) {
            $this->public_log('❌ Errore caricamento Google Drive Sync: ' . $e->getMessage(), 'ERROR');
        }
        
        // ⚠️ MODIFICA 2: Carica il file -new per AJAX Handlers
        try {
            add_action('init', function() {
                $ajax_path = DISCO747_CRM_PLUGIN_DIR . 'includes/handlers/class-disco747-ajax-new.php';
                if (file_exists($ajax_path)) {
                    require_once $ajax_path;
                    $this->public_log('✅ Caricato: includes/handlers/class-disco747-ajax-new.php');
                    
                    if (class_exists('Disco747_CRM\\Handlers\\Disco747_Ajax')) {
                        new Disco747_CRM\Handlers\Disco747_Ajax();
                        $this->public_log('✅ AJAX Handlers inizializzato');
                    }
                }
            });
        } catch (Exception $e) {
            $this->public_log('❌ Errore caricamento AJAX Handlers: ' . $e->getMessage(), 'ERROR');
        }
        
        $this->public_log('✅ Init WordPress completato');
        
        // Forms Handler (NON MODIFICATO - resta identico)
        $forms_path = DISCO747_CRM_PLUGIN_DIR . 'includes/handlers/class-disco747-forms.php';
        if (file_exists($forms_path)) {
            require_once $forms_path;
            $this->public_log('✅ Caricato: includes/handlers/class-disco747-forms.php');
            
            if (class_exists('Disco747_CRM\\Handlers\\Disco747_Forms')) {
                $this->forms_handler = new Disco747_CRM\Handlers\Disco747_Forms();
                $this->public_log('✅ Forms Handler caricato');
            }
        }
    }

    // Getters
    public function is_initialized() { return $this->initialized; }
    public function get_config() { return $this->config; }
    public function get_database() { return $this->database; }
    public function get_auth() { return $this->auth; }
    public function get_admin() { return $this->admin; }
    public function get_storage_manager() { return $this->storage_manager; }
    public function get_email() { return $this->email_manager; }
    public function get_pdf() { return $this->pdf_generator; }
    public function get_excel() { return $this->excel_generator; }
    public function get_googledrive_sync() { return $this->gdrive_sync; }
    public function get_forms() { return $this->forms_handler; }

    public function activate_plugin() {
        if ($this->database) {
            $this->database->create_tables();
        }
        flush_rewrite_rules();
    }

    public function deactivate_plugin() {
        flush_rewrite_rules();
    }

    public function cleanup_old_files() {
        $upload_dir = wp_upload_dir();
        $temp_dir = $upload_dir['basedir'] . '/preventivi/temp/';
        
        if (is_dir($temp_dir)) {
            $files = glob($temp_dir . '*');
            $now = time();
            
            foreach ($files as $file) {
                if (is_file($file)) {
                    if ($now - filemtime($file) >= 86400) {
                        @unlink($file);
                    }
                }
            }
        }
    }

    public function public_log($message, $level = 'INFO') {
        if (!$this->debug_mode) {
            return;
        }
        
        $timestamp = current_time('mysql');
        $formatted_message = "[{$timestamp}] [747Disco-CRM] [{$level}] {$message}";
        error_log($formatted_message);
    }

    private function auto_migrate_if_needed() {
        // Compatibilità con vecchio plugin (NON MODIFICATO)
    }
}

function disco747_crm() {
    return Disco747_CRM_Plugin::instance();
}

disco747_crm();
