<?php
/**
 * AJAX Handlers per 747 Disco CRM
 * Gestisce tutte le chiamate AJAX del plugin
 * 
 * @package    Disco747_CRM
 * @subpackage Admin
 * @since      11.8.9-RESET-AND-SCAN
 */

namespace Disco747_CRM\Admin;

if (!defined('ABSPATH')) {
    exit;
}

class Disco747_AJAX_Handlers {

    /**
     * Inizializza gli handler AJAX
     */
    public static function init() {
        // Batch scan Excel
        add_action('wp_ajax_batch_scan_excel', array(__CLASS__, 'handle_batch_scan'));
        add_action('wp_ajax_disco747_scan_drive_batch', array(__CLASS__, 'handle_batch_scan')); // Alias
        
        // Reset e Scan
        add_action('wp_ajax_reset_and_scan_excel', array(__CLASS__, 'handle_reset_and_scan'));
        
        // Altri handler
        add_action('wp_ajax_analyze_excel_file', array(__CLASS__, 'handle_analyze_file'));
        
        error_log('[Excel-Scan-AJAX] Hook AJAX registrati: batch_scan_excel, reset_and_scan_excel, analyze_excel_file');
    }

    /**
     * âœ… Handler principale per batch scan - OTTIMIZZATO con offset/batch_size
     */
    public static function handle_batch_scan() {
        error_log('[Batch-Scan-AJAX] ========== INIZIO BATCH SCAN OTTIMIZZATO ==========');
        
        // âœ… TIMEOUT ESTESO
        set_time_limit(120); // 2 minuti
        ini_set('max_execution_time', '120');
        ini_set('memory_limit', '512M');
        
        // Verifica nonce
        if (!isset($_POST['nonce']) && !isset($_POST['_wpnonce'])) {
            error_log('[Batch-Scan-AJAX] ERRORE: Nonce mancante');
            wp_send_json_error(array('message' => 'Nonce mancante'));
            return;
        }

        $nonce = isset($_POST['nonce']) ? $_POST['nonce'] : $_POST['_wpnonce'];
        
        if (!wp_verify_nonce($nonce, 'disco747_batch_scan')) {
            error_log('[Batch-Scan-AJAX] ERRORE: Nonce non valido');
            wp_send_json_error(array('message' => 'Verifica nonce fallita'));
            return;
        }

        error_log('[Batch-Scan-AJAX] âœ… Nonce verificato');

        // Verifica permessi
        if (!current_user_can('manage_options')) {
            error_log('[Batch-Scan-AJAX] ERRORE: Permessi insufficienti');
            wp_send_json_error(array('message' => 'Permessi insufficienti'));
            return;
        }

        error_log('[Batch-Scan-AJAX] âœ… Permessi verificati');

        // âœ… PARAMETRI con offset e batch_size
        $year = isset($_POST['year']) ? sanitize_text_field($_POST['year']) : date('Y');
        $month = isset($_POST['month']) ? sanitize_text_field($_POST['month']) : '';
        $offset = isset($_POST['offset']) ? intval($_POST['offset']) : 0;
        $batch_size = isset($_POST['batch_size']) ? intval($_POST['batch_size']) : 10;

        error_log("[Batch-Scan-AJAX] Parametri: anno={$year}, mese={$month}, offset={$offset}, batch_size={$batch_size}");

        try {
            // âœ… Usa GoogleDrive_Sync per elaborazione ottimizzata
            if (!class_exists('Disco747_CRM\\Storage\\Disco747_GoogleDrive_Sync')) {
                error_log('[Batch-Scan-AJAX] ERRORE: GoogleDrive_Sync non disponibile');
                wp_send_json_error(array('message' => 'GoogleDrive_Sync non disponibile'));
                return;
            }

            if (!class_exists('Disco747_CRM\\Storage\\Disco747_GoogleDrive')) {
                error_log('[Batch-Scan-AJAX] ERRORE: GoogleDrive handler non disponibile');
                wp_send_json_error(array('message' => 'GoogleDrive handler non disponibile'));
                return;
            }

            // Crea istanza GoogleDrive e GoogleDrive_Sync
            $googledrive_handler = new \Disco747_CRM\Storage\Disco747_GoogleDrive();
            $gdrive_sync = new \Disco747_CRM\Storage\Disco747_GoogleDrive_Sync($googledrive_handler);

            if (!$gdrive_sync->is_available()) {
                error_log('[Batch-Scan-AJAX] ERRORE: GoogleDrive Sync non disponibile');
                wp_send_json_error(array('message' => 'GoogleDrive Sync non disponibile'));
                return;
            }

            error_log('[Batch-Scan-AJAX] ✅ GoogleDrive_Sync inizializzato');

            // ✅ CHIAMA il metodo ottimizzato con offset, batch_size, year e month
            $result = $gdrive_sync->scan_excel_files_batch($offset, $batch_size, $year, $month);

            error_log('[Batch-Scan-AJAX] ========== RISULTATO BATCH ==========');
            error_log('[Batch-Scan-AJAX] Total: ' . $result['total_files']);
            error_log('[Batch-Scan-AJAX] Processed in batch: ' . $result['processed_in_batch']);
            error_log('[Batch-Scan-AJAX] Has more: ' . ($result['has_more'] ? 'SI' : 'NO'));
            error_log('[Batch-Scan-AJAX] Next offset: ' . $result['next_offset']);

            // âœ… RISPOSTA OTTIMIZZATA con info batch
            wp_send_json_success(array(
                'complete' => !$result['has_more'], // true se Ã¨ l'ultimo batch
                'total_files' => $result['total_files'],
                'current_offset' => $offset,
                'batch_size' => $batch_size,
                'processed_in_batch' => $result['processed_in_batch'],
                'new_records' => $result['new'],
                'updated_records' => $result['updated'],
                'errors' => $result['errors'],
                'has_more' => $result['has_more'],
                'next_offset' => $result['next_offset'],
                'progress_percent' => $result['progress_percent'],
                'message' => $result['has_more'] 
                    ? "Batch {$offset}-" . ($offset + $batch_size) . " completato. Continuando..."
                    : "Scansione completata: {$result['new']} nuovi, {$result['updated']} aggiornati"
            ));

        } catch (\Exception $e) {
            error_log('[Batch-Scan-AJAX] âŒ ERRORE FATALE: ' . $e->getMessage());
            wp_send_json_error(array(
                'message' => 'Errore durante la scansione: ' . $e->getMessage()
            ));
        }
    }

    /**
     * Handler per svuotare database e rianalizzare
     */
    public static function handle_reset_and_scan() {
        error_log('[Reset-Scan-AJAX] ========== INIZIO RESET & SCAN ==========');
        
        // Verifica nonce
        if (!isset($_POST['nonce']) && !isset($_POST['_wpnonce'])) {
            wp_send_json_error(array('message' => 'Nonce mancante'));
            return;
        }

        $nonce = isset($_POST['nonce']) ? $_POST['nonce'] : $_POST['_wpnonce'];
        
        if (!wp_verify_nonce($nonce, 'disco747_batch_scan')) {
            wp_send_json_error(array('message' => 'Verifica nonce fallita'));
            return;
        }

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permessi insufficienti'));
            return;
        }

        error_log('[Reset-Scan-AJAX] âœ… Nonce e permessi verificati');

        try {
            // SVUOTA TABELLA
            global $wpdb;
            $table = $wpdb->prefix . 'disco747_preventivi';
            $deleted = $wpdb->query("TRUNCATE TABLE {$table}");
            
            error_log('[Reset-Scan-AJAX] âœ… Tabella svuotata (record eliminati: ' . ($deleted !== false ? 'OK' : 'ERRORE') . ')');

            // Ora esegui la scansione normale (riutilizza la logica esistente)
            self::handle_batch_scan();

        } catch (\Exception $e) {
            error_log('[Reset-Scan-AJAX] âŒ ERRORE: ' . $e->getMessage());
            wp_send_json_error(array(
                'message' => 'Errore durante reset: ' . $e->getMessage()
            ));
        }
    }

    /**
     * Parse filename Excel per estrarre informazioni
     * 
     * Esempi supportati:
     * - "Conf 03_09 18 Anni di Tommaso (Menu 7).xlsx"
     * - "No 02_09 18 anni di Luca (Menu 7).xlsx"
     * - "22_09 Evento (Menu 7).xlsx"
     */
    private static function parse_filename($filename) {
        // Rimuovi estensione
        $name = pathinfo($filename, PATHINFO_FILENAME);
        
        error_log("[Parse-Filename] Parsing: {$name}");
        
        // Pattern regex migliorato
        $pattern = '/^(?:(Conf|No)\s+)?(\d{1,2})_(\d{1,2})\s+(.+?)\s+\(Menu\s+([\d\-]+)\)$/i';
        
        if (preg_match($pattern, $name, $matches)) {
            $stato_prefix = strtolower($matches[1] ?: '');
            $giorno = str_pad($matches[2], 2, '0', STR_PAD_LEFT);
            $mese = str_pad($matches[3], 2, '0', STR_PAD_LEFT);
            $tipo_evento = trim($matches[4]);
            $tipo_menu = 'Menu ' . $matches[5];
            
            // Determina stato
            $stato = 'attivo';
            if ($stato_prefix === 'conf') {
                $stato = 'confermato';
            } elseif ($stato_prefix === 'no') {
                $stato = 'annullato';
            }
            
            // Determina anno (usa anno corrente + 1 per date future)
            $anno_corrente = (int) date('Y');
            $mese_corrente = (int) date('m');
            
            // Se il mese Ã¨ passato, usa anno prossimo
            if ((int)$mese < $mese_corrente) {
                $anno = $anno_corrente + 1;
            } else {
                $anno = $anno_corrente;
            }
            
            $data_evento = "{$anno}-{$mese}-{$giorno}";
            
            $result = array(
                'data_evento' => $data_evento,
                'tipo_evento' => $tipo_evento,
                'tipo_menu' => $tipo_menu,
                'stato' => $stato
            );
            
            error_log("[Parse-Filename] âœ… Parsed: " . json_encode($result));
            
            return $result;
        }
        
        error_log("[Parse-Filename] âŒ Pattern non riconosciuto");
        return false;
    }

    /**
     * Handler per analisi singolo file
     */
    public static function handle_analyze_file() {
        // Verifica nonce
        check_ajax_referer('disco747_excel_scan', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permessi insufficienti'));
            return;
        }

        $file_id = isset($_POST['file_id']) ? sanitize_text_field($_POST['file_id']) : '';

        if (empty($file_id)) {
            wp_send_json_error(array('message' => 'File ID mancante'));
            return;
        }

        try {
            // Implementa logica analisi singolo file
            wp_send_json_success(array(
                'message' => 'Analisi file non ancora implementata',
                'file_id' => $file_id
            ));

        } catch (\Exception $e) {
            wp_send_json_error(array(
                'message' => 'Errore analisi file: ' . $e->getMessage()
            ));
        }
    }
}

// Inizializza gli handler AJAX
Disco747_AJAX_Handlers::init();