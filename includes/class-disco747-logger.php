<?php
/**
 * Logger centralizzato - 747 Disco CRM
 * Scrive tutti i log in wp-content/debug.log
 * 
 * @package    Disco747_CRM
 * @subpackage Core
 * @version    1.0.0
 */

defined('ABSPATH') || exit;

/**
 * Classe per gestire il logging centralizzato
 */
class Disco747_Logger {
    
    /**
     * Percorso del file di log
     */
    private static $log_file = null;
    
    /**
     * Flag debug mode
     */
    private static $debug_enabled = null;
    
    /**
     * Inizializza il logger
     */
    public static function init() {
        if (self::$log_file === null) {
            self::$log_file = WP_CONTENT_DIR . '/debug.log';
            self::$debug_enabled = get_option('disco747_debug_mode', false) || (defined('WP_DEBUG') && WP_DEBUG);
        }
    }
    
    /**
     * Scrive un messaggio di log
     * 
     * @param string $message Messaggio da loggare
     * @param string $level Livello: INFO, WARNING, ERROR, DEBUG
     * @param string $component Componente che scrive (es. PDF, GOOGLEDRIVE, EXCEL)
     */
    public static function log($message, $level = 'INFO', $component = 'CORE') {
        self::init();
        
        // Scrivi solo se debug è abilitato (tranne per gli errori)
        if (!self::$debug_enabled && $level !== 'ERROR') {
            return;
        }
        
        // Formatta messaggio
        $timestamp = current_time('mysql');
        $log_entry = sprintf(
            "[%s] [747Disco-CRM] [%s] [%s] %s\n",
            $timestamp,
            $component,
            $level,
            $message
        );
        
        // Scrivi nel file
        self::write_to_file($log_entry);
        
        // Scrivi anche nel log PHP standard
        error_log($log_entry);
    }
    
    /**
     * Scrive nel file di log
     */
    private static function write_to_file($message) {
        if (!self::$log_file) {
            return;
        }
        
        // Crea il file se non esiste
        if (!file_exists(self::$log_file)) {
            @touch(self::$log_file);
            @chmod(self::$log_file, 0644);
        }
        
        // Verifica se il file è scrivibile
        if (!is_writable(self::$log_file)) {
            error_log("[747Disco-CRM] ERRORE: debug.log non è scrivibile!");
            return;
        }
        
        // Scrivi nel file
        @file_put_contents(self::$log_file, $message, FILE_APPEND | LOCK_EX);
    }
    
    /**
     * Helper methods per diversi livelli di log
     */
    public static function info($message, $component = 'CORE') {
        self::log($message, 'INFO', $component);
    }
    
    public static function warning($message, $component = 'CORE') {
        self::log($message, 'WARNING', $component);
    }
    
    public static function error($message, $component = 'CORE') {
        self::log($message, 'ERROR', $component);
    }
    
    public static function debug($message, $component = 'CORE') {
        self::log($message, 'DEBUG', $component);
    }
    
    /**
     * Pulisce il file di log (se troppo grande)
     */
    public static function cleanup() {
        self::init();
        
        if (!file_exists(self::$log_file)) {
            return;
        }
        
        // Se il log è > 10MB, lo svuota
        $max_size = 10 * 1024 * 1024; // 10 MB
        if (filesize(self::$log_file) > $max_size) {
            self::info('Log file troppo grande, svuotamento in corso...', 'LOGGER');
            @file_put_contents(self::$log_file, '');
            self::info('Log file svuotato', 'LOGGER');
        }
    }
    
    /**
     * Verifica se il debug è abilitato
     */
    public static function is_debug_enabled() {
        self::init();
        return self::$debug_enabled;
    }
    
    /**
     * Log di avvio del plugin
     */
    public static function log_startup() {
        self::init();
        self::info('========================================', 'CORE');
        self::info('747 Disco CRM v' . DISCO747_CRM_VERSION . ' - AVVIO', 'CORE');
        self::info('Debug Mode: ' . (self::$debug_enabled ? 'ABILITATO' : 'DISABILITATO'), 'CORE');
        self::info('WordPress: ' . get_bloginfo('version'), 'CORE');
        self::info('PHP: ' . PHP_VERSION, 'CORE');
        self::info('========================================', 'CORE');
    }
}
