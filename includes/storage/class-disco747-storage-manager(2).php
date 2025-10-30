<?php
/**
 * Storage Manager - Gestione Multi-Storage
 * Versione 11.8.8 - Aggiunta metodi pubblici get_active_handler(), get_googledrive_handler(), get_dropbox_handler()
 *
 * @package    Disco747_CRM
 * @subpackage Storage
 */

namespace Disco747_CRM\Storage;

if (!defined('ABSPATH')) {
    exit;
}

class Disco747_Storage_Manager {
    
    private $googledrive_handler;
    private $dropbox_handler;
    private $storage_type;
    
    public function __construct() {
        $this->storage_type = get_option('disco747_storage_type', 'googledrive');
        $this->init_storage_handlers();
    }
    
    private function init_storage_handlers() {
        try {
            // Inizializza Google Drive
            if (class_exists('Disco747_CRM\\Storage\\Disco747_GoogleDrive')) {
                $this->googledrive_handler = new Disco747_GoogleDrive();
                $this->log('[StorageManager] Google Drive handler inizializzato');
            }
            
            // Inizializza Dropbox
            if (class_exists('Disco747_CRM\\Storage\\Disco747_Dropbox')) {
                $this->dropbox_handler = new Disco747_Dropbox();
                $this->log('[StorageManager] Dropbox handler inizializzato');
            }
            
            $this->log('[StorageManager] Inizializzato con tipo: ' . $this->storage_type);
            
        } catch (\Exception $e) {
            $this->log('[StorageManager] Errore inizializzazione: ' . $e->getMessage(), 'error');
        }
    }
    
    /**
     * ✅ NUOVO: Ottiene l'handler attivo (GoogleDrive o Dropbox)
     * 
     * @return object|null Handler attivo o null
     */
    public function get_active_handler() {
        if ($this->storage_type === 'googledrive' && $this->googledrive_handler) {
            return $this->googledrive_handler;
        } elseif ($this->storage_type === 'dropbox' && $this->dropbox_handler) {
            return $this->dropbox_handler;
        }
        return null;
    }
    
    /**
     * ✅ NUOVO: Ottiene handler Google Drive
     * 
     * @return object|null Google Drive handler o null
     */
    public function get_googledrive_handler() {
        return $this->googledrive_handler;
    }
    
    /**
     * ✅ NUOVO: Ottiene handler Dropbox
     * 
     * @return object|null Dropbox handler o null
     */
    public function get_dropbox_handler() {
        return $this->dropbox_handler;
    }
    
    /**
     * Ottiene il tipo di storage attivo
     * 
     * @return string 'googledrive' o 'dropbox'
     */
    public function get_storage_type() {
        return $this->storage_type;
    }
    
    /**
     * Imposta il tipo di storage
     * 
     * @param string $type 'googledrive' o 'dropbox'
     */
    public function set_storage_type($type) {
        if (in_array($type, array('googledrive', 'dropbox'))) {
            $this->storage_type = $type;
            update_option('disco747_storage_type', $type);
            $this->log('[StorageManager] Tipo storage cambiato in: ' . $type);
        }
    }
    
    /**
     * Verifica se lo storage è connesso
     * 
     * @return bool
     */
    public function is_connected() {
        $handler = $this->get_active_handler();
        
        if (!$handler) {
            return false;
        }
        
        if (method_exists($handler, 'is_connected')) {
            return $handler->is_connected();
        }
        
        return false;
    }
    
    /**
     * Upload file su storage attivo
     * 
     * @param string $local_path Path locale del file
     * @param string $remote_path Path remoto di destinazione
     * @return array Risultato upload
     */
    public function upload_file($local_path, $remote_path) {
        $handler = $this->get_active_handler();
        
        if (!$handler) {
            return array(
                'success' => false,
                'error' => 'Nessun handler storage disponibile'
            );
        }
        
        if (!method_exists($handler, 'upload_file')) {
            return array(
                'success' => false,
                'error' => 'Metodo upload_file non disponibile'
            );
        }
        
        try {
            return $handler->upload_file($local_path, $remote_path);
        } catch (\Exception $e) {
            return array(
                'success' => false,
                'error' => $e->getMessage()
            );
        }
    }
    
    /**
     * Download file da storage attivo
     * 
     * @param string $remote_path Path remoto
     * @param string $local_path Path locale di destinazione
     * @return array Risultato download
     */
    public function download_file($remote_path, $local_path) {
        $handler = $this->get_active_handler();
        
        if (!$handler) {
            return array(
                'success' => false,
                'error' => 'Nessun handler storage disponibile'
            );
        }
        
        if (!method_exists($handler, 'download_file')) {
            return array(
                'success' => false,
                'error' => 'Metodo download_file non disponibile'
            );
        }
        
        try {
            return $handler->download_file($remote_path, $local_path);
        } catch (\Exception $e) {
            return array(
                'success' => false,
                'error' => $e->getMessage()
            );
        }
    }
    
    /**
     * Lista file da storage attivo
     * 
     * @param string $path Path da listare
     * @return array Lista file
     */
    public function list_files($path = '') {
        $handler = $this->get_active_handler();
        
        if (!$handler) {
            return array();
        }
        
        if (!method_exists($handler, 'list_files')) {
            return array();
        }
        
        try {
            return $handler->list_files($path);
        } catch (\Exception $e) {
            $this->log('[StorageManager] Errore list_files: ' . $e->getMessage(), 'error');
            return array();
        }
    }
    
    /**
     * Elimina file da storage attivo
     * 
     * @param string $remote_path Path remoto del file
     * @return array Risultato eliminazione
     */
    public function delete_file($remote_path) {
        $handler = $this->get_active_handler();
        
        if (!$handler) {
            return array(
                'success' => false,
                'error' => 'Nessun handler storage disponibile'
            );
        }
        
        if (!method_exists($handler, 'delete_file')) {
            return array(
                'success' => false,
                'error' => 'Metodo delete_file non disponibile'
            );
        }
        
        try {
            return $handler->delete_file($remote_path);
        } catch (\Exception $e) {
            return array(
                'success' => false,
                'error' => $e->getMessage()
            );
        }
    }
    
    /**
     * Crea cartella su storage attivo
     * 
     * @param string $path Path della cartella da creare
     * @return array Risultato creazione
     */
    public function create_folder($path) {
        $handler = $this->get_active_handler();
        
        if (!$handler) {
            return array(
                'success' => false,
                'error' => 'Nessun handler storage disponibile'
            );
        }
        
        if (!method_exists($handler, 'create_folder')) {
            return array(
                'success' => false,
                'error' => 'Metodo create_folder non disponibile'
            );
        }
        
        try {
            return $handler->create_folder($path);
        } catch (\Exception $e) {
            return array(
                'success' => false,
                'error' => $e->getMessage()
            );
        }
    }
    
    /**
     * Ottiene informazioni su un file
     * 
     * @param string $remote_path Path remoto
     * @return array|false Info file o false
     */
    public function get_file_info($remote_path) {
        $handler = $this->get_active_handler();
        
        if (!$handler) {
            return false;
        }
        
        if (!method_exists($handler, 'get_file_info')) {
            return false;
        }
        
        try {
            return $handler->get_file_info($remote_path);
        } catch (\Exception $e) {
            $this->log('[StorageManager] Errore get_file_info: ' . $e->getMessage(), 'error');
            return false;
        }
    }
    
    /**
     * Logging
     */
    private function log($message, $level = 'info') {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[747Disco-CRM] ' . $message);
        }
    }
}