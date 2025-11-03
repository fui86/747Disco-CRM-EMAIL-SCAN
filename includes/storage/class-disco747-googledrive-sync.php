<?php
/**
 * Classe per sincronizzazione preventivi da Google Drive
 * VERSIONE 11.8.1-FIXED: Con metodo is_available() e preventivo_id autogenerato
 * 
 * @package    Disco747_CRM
 * @subpackage Storage
 * @since      11.8.1
 * @author     747 Disco Team
 */

namespace Disco747_CRM\Storage;

if (!defined('ABSPATH')) {
    exit('Accesso diretto non consentito');
}

class Disco747_GoogleDrive_Sync {

    private $googledrive;
    private $database;
    private $preventivi_cache = null;
    private $cache_duration = 300;
    private $debug_mode = true;
    private $sync_available = false;
    private $last_error = '';

    /**
     * Costruttore
     */
    public function __construct($googledrive_instance = null) {
        $session_id = 'INIT_' . date('His') . '_' . wp_rand(100, 999);
        
        try {
            $this->log("=== DEBUG SESSION {$session_id} CONSTRUCTOR START ===");
            
            if ($googledrive_instance) {
                $this->googledrive = $googledrive_instance;
                $this->sync_available = true;
                $this->log("DEBUG: GoogleDrive instance fornita esternamente");
            } else {
                $this->log("DEBUG: Cerco di caricare classe GoogleDrive autonomamente...");
                if (class_exists('Disco747_CRM\\Storage\\Disco747_GoogleDrive')) {
                    $this->googledrive = new \Disco747_CRM\Storage\Disco747_GoogleDrive();
                    $this->sync_available = true;
                    $this->log("DEBUG: Classe GoogleDrive trovata e istanziata");
                } else {
                    $this->log("DEBUG: Classe GoogleDrive NON trovata", 'WARNING');
                    $this->sync_available = false;
                }
            }
            
            // Carica database handler
            if (function_exists('disco747_crm')) {
                $this->database = disco747_crm()->get_database();
                $this->log("DEBUG: Database handler caricato");
            }
            
            $this->log("DEBUG: GoogleDrive Sync Handler inizializzato (disponibile: " . ($this->sync_available ? 'SI' : 'NO') . ")");
            $this->log("=== DEBUG SESSION {$session_id} CONSTRUCTOR END ===");
            
        } catch (\Exception $e) {
            $this->log("DEBUG: Errore inizializzazione GoogleDrive Sync: " . $e->getMessage(), 'ERROR');
            $this->sync_available = false;
        }
    }

    /**
     * âœ… METODO PUBBLICO: Verifica se Google Drive Sync Ã¨ disponibile
     * 
     * @return bool
     */
    public function is_available() {
        return $this->sync_available;
    }

    /**
     * ✅ METODO BATCH SCAN OTTIMIZZATO - Elaborazione a lotti per evitare timeout
     * 
     * @param int $offset Offset di partenza (per paginazione)
     * @param int $batch_size Numero di file per batch (default: 10)
     * @param string $year Anno da filtrare (opzionale)
     * @param string $month Mese da filtrare (opzionale)
     * @return array Risultati elaborazione con informazioni next_offset
     */
    public function scan_excel_files_batch($offset = 0, $batch_size = 10, $year = '', $month = '') {
        // ✅ TIMEOUT ESTESO per batch processing
        set_time_limit(120); // 2 minuti per batch
        ini_set('max_execution_time', '120');
        
        $this->log('[BATCH-SCAN] ========== INIZIO BATCH SCAN OTTIMIZZATO ==========');
        $this->log('[BATCH-SCAN] Offset: ' . $offset . ', Batch size: ' . $batch_size);
        $this->log('[BATCH-SCAN] Filtri: Anno=' . ($year ?: 'tutti') . ', Mese=' . ($month ?: 'tutti'));
        
        $messages = array();
        $new_count = 0;
        $updated_count = 0;
        $error_count = 0;
        $total_files = 0;
        $has_more = false;
        $next_offset = 0;
        
        try {
            // Step 1: Verifica token
            $token = $this->googledrive->get_valid_access_token();
            if (!$token) {
                throw new \Exception('Token Google Drive non valido');
            }
            $this->log('[Token] Token ottenuto con successo');
            
            // Step 2: Trova cartella principale
            $main_folder_id = $this->find_main_folder_safe($token);
            if (!$main_folder_id) {
                throw new \Exception('Cartella /747-Preventivi/ non trovata');
            }
            $this->log("Cartella 747-Preventivi trovata: {$main_folder_id}");
            
            // Step 3: Trova TUTTI i file Excel (solo al primo batch)
            if ($offset === 0) {
                // Cache lista file per i batch successivi
                $all_files = $this->scan_all_excel_files_recursive($main_folder_id, $token, $year, $month);
                set_transient('disco747_scan_files_cache', $all_files, 300); // 5 minuti cache
            } else {
                // Recupera da cache
                $all_files = get_transient('disco747_scan_files_cache');
                if (!$all_files) {
                    throw new \Exception('Cache file scaduta. Riavvia scansione.');
                }
            }
            
            $total_files = count($all_files);
            $this->log("[BATCH] Totale file Excel: {$total_files}, Offset: {$offset}");
            
            // âœ… Estrai solo il batch corrente
            $batch_files = array_slice($all_files, $offset, $batch_size);
            $batch_count = count($batch_files);
            
            $this->log("[BATCH] Processando batch di {$batch_count} file (da {$offset} a " . ($offset + $batch_count) . ")");
            
            // Step 4: Processa batch corrente con gestione errori robusta
            foreach ($batch_files as $index => $file) {
                $file_number = $offset + $index + 1;
                $file_name = $file['filename'] ?? 'Unknown';
                
                $this->log("[BATCH] [{$file_number}/{$total_files}] Processamento: {$file_name}");
                
                try {
                    // âœ… TRY-CATCH ROBUSTO per evitare che un file corrotto blocchi tutto
                    $result = $this->process_single_excel_file($file, $token);
                    
                    if ($result && isset($result['success']) && $result['success']) {
                        if (isset($result['action']) && $result['action'] === 'inserted') {
                            $new_count++;
                        } else {
                            $updated_count++;
                        }
                        $this->log("[BATCH] âœ… Processato: {$file_name}");
                    } else {
                        $error_count++;
                        $error_msg = isset($result['error']) ? $result['error'] : 'Errore sconosciuto';
                        $this->log("[BATCH] âŒ Errore: {$file_name} - {$error_msg}", 'ERROR');
                    }
                    
                } catch (\PhpOffice\PhpSpreadsheet\Reader\Exception $e) {
                    // Errore specifico PhpSpreadsheet (file corrotto/vuoto)
                    $error_count++;
                    $this->log("[BATCH] âŒ File Excel corrotto/vuoto: {$file_name} - " . $e->getMessage(), 'ERROR');
                } catch (\Exception $e) {
                    // Qualsiasi altro errore
                    $error_count++;
                    $this->log("[BATCH] âŒ Errore generico: {$file_name} - " . $e->getMessage(), 'ERROR');
                } catch (\Throwable $e) {
                    // Catch anche errori fatali PHP 7+
                    $error_count++;
                    $this->log("[BATCH] âŒ Errore fatale: {$file_name} - " . $e->getMessage(), 'ERROR');
                }
            }
            
            // âœ… Calcola se ci sono altri file
            $next_offset = $offset + $batch_size;
            $has_more = $next_offset < $total_files;
            
            if (!$has_more) {
                // Pulizia cache alla fine
                delete_transient('disco747_scan_files_cache');
                $messages[] = 'âœ… Scansione completata!';
            } else {
                $messages[] = "â³ Elaborati {$next_offset}/{$total_files} file...";
            }
            
            
        } catch (\Exception $e) {
            $messages[] = 'âŒ Errore batch scan: ' . $e->getMessage();
            $this->log('[BATCH-SCAN] Errore: ' . $e->getMessage(), 'ERROR');
        }
        
        $this->log('[BATCH-SCAN] ========== FINE BATCH (Offset: ' . $offset . ') ==========');
        
        return array(
            'success' => true,
            'total_files' => $total_files,
            'offset' => $offset,
            'batch_size' => $batch_size,
            'processed_in_batch' => $new_count + $updated_count,
            'new' => $new_count,
            'updated' => $updated_count,
            'errors' => $error_count,
            'has_more' => $has_more,
            'next_offset' => $next_offset,
            'progress_percent' => $total_files > 0 ? round(($offset + $batch_count) / $total_files * 100) : 100,
            'messages' => $messages
        );
    }

    /**
     * Processa singolo file Excel
     */
    private function process_single_excel_file($file, $token) {
        $this->log('[PROCESS] Inizio processamento: ' . $file['filename']);
        
        $temp_file_path = null;
        
        try {
            // Download file temporaneo
            $file_id = $file['googledrive_id'];
            $temp_result = $this->download_file_to_temp($file_id, $token);
            
            if (!$temp_result['success']) {
                throw new \Exception('Errore download: ' . $temp_result['error']);
            }
            
            $temp_file_path = $temp_result['path'];
            $this->log('[PROCESS] File scaricato: ' . $temp_file_path);
            
            // Estrai dati dal file Excel
            $extracted_data = $this->extract_data_from_excel($temp_file_path);
            
            if (!$extracted_data) {
                throw new \Exception('Impossibile estrarre dati dal file Excel');
            }
            
            $this->log('[PROCESS] Dati estratti: cliente=' . ($extracted_data['nome_cliente'] ?? 'N/A'));
            
            // Aggiungi metadati Google Drive
            $extracted_data['googledrive_file_id'] = $file_id;
            $extracted_data['googledrive_url'] = 'https://drive.google.com/file/d/' . $file_id . '/view';
            $extracted_data['excel_url'] = '';
            $extracted_data['pdf_url'] = '';
            
            // Salva nel database
            $save_result = $this->save_to_database($extracted_data);
            
            // Cleanup file temporaneo
            if (file_exists($temp_file_path)) {
                unlink($temp_file_path);
                $this->log('[PROCESS] âœ… File temp cancellato: ' . $temp_file_path);
            }
            
            return $save_result;
            
        } catch (\Exception $e) {
            // Cleanup in caso di errore
            if ($temp_file_path && file_exists($temp_file_path)) {
                unlink($temp_file_path);
            }
            
            $this->log('[PROCESS] Errore: ' . $e->getMessage(), 'ERROR');
            return array(
                'success' => false,
                'error' => $e->getMessage()
            );
        }
    }

    /**
     * âœ… METODO CORRETTO: Salva o aggiorna preventivo nel database con preventivo_id autogenerato
     */
    private function save_to_database($data) {
        global $wpdb;
        
        try {
            $table_name = $wpdb->prefix . 'disco747_preventivi';
            
            // Controlla se esiste giÃ  tramite googledrive_file_id
            $existing = null;
            if (!empty($data['googledrive_file_id'])) {
                $existing = $wpdb->get_row($wpdb->prepare(
                    "SELECT * FROM {$table_name} WHERE googledrive_file_id = %s",
                    $data['googledrive_file_id']
                ));
            }
            
            if ($existing) {
                // UPDATE preventivo esistente
                $this->log('[DB] Preventivo esistente trovato (ID: ' . $existing->id . '), UPDATE');
                
                $update_data = array(
                    'data_evento' => $data['data_evento'] ?? $existing->data_evento,
                    'tipo_evento' => $data['tipo_evento'] ?? $existing->tipo_evento,
                    'tipo_menu' => $data['tipo_menu'] ?? $existing->tipo_menu,
                    'numero_invitati' => intval($data['numero_invitati'] ?? $existing->numero_invitati),
                    'orario_evento' => $data['orario_evento'] ?? $existing->orario_evento,
                    'nome_cliente' => $data['nome_cliente'] ?? $existing->nome_cliente,
                    'nome_referente' => $data['nome_referente'] ?? $existing->nome_referente ?? '',
                    'cognome_referente' => $data['cognome_referente'] ?? $existing->cognome_referente ?? '',
                    'telefono' => $data['telefono'] ?? $existing->telefono,
                    'email' => $data['email'] ?? $existing->email,
                    'importo_totale' => floatval($data['importo_totale'] ?? $existing->importo_totale),
                    'acconto' => floatval($data['acconto'] ?? $existing->acconto),
                    'saldo' => floatval($data['saldo'] ?? $existing->saldo ?? 0),
                    'omaggio1' => $data['omaggio1'] ?? $existing->omaggio1,
                    'omaggio2' => $data['omaggio2'] ?? $existing->omaggio2,
                    'omaggio3' => $data['omaggio3'] ?? $existing->omaggio3,
                    'extra1' => $data['extra1'] ?? $existing->extra1,
                    'extra1_importo' => floatval($data['extra1_importo'] ?? $existing->extra1_importo),
                    'extra2' => $data['extra2'] ?? $existing->extra2,
                    'extra2_importo' => floatval($data['extra2_importo'] ?? $existing->extra2_importo),
                    'extra3' => $data['extra3'] ?? $existing->extra3,
                    'extra3_importo' => floatval($data['extra3_importo'] ?? $existing->extra3_importo),
                    'stato' => $data['stato'] ?? $existing->stato,
                    'googledrive_url' => $data['googledrive_url'] ?? $existing->googledrive_url,
                    'updated_at' => current_time('mysql')
                );
                
                $result = $wpdb->update(
                    $table_name,
                    $update_data,
                    array('id' => $existing->id),
                    // 25 campi: data, tipo_evento, tipo_menu, numero_invitati, orario, nome, nome_ref, cognome_ref, telefono, email, importo, acconto, saldo, 3 omaggi, 3 extra+importi, stato, url, updated
                    array('%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%f', '%f', '%f', '%s', '%s', '%s', '%s', '%f', '%s', '%f', '%s', '%f', '%s', '%s', '%s'),
                    array('%d')
                );
                
                if ($result === false) {
                    $this->log('[DB] âŒ ERRORE UPDATE: ' . $wpdb->last_error, 'ERROR');
                    $this->log('[DB] SQL Query: ' . $wpdb->last_query, 'ERROR');
                    return array('success' => false, 'error' => $wpdb->last_error);
                }
                
                // âœ… VERIFICA DATI SALVATI LEGGENDO DAL DB
                $saved = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table_name} WHERE id = %d", $existing->id));
                
                $this->log('[DB] âœ… UPDATE eseguito - ID: ' . $existing->id);
                $this->log('[DB] === VERIFICA DATI SALVATI NEL DB ===');
                $this->log('[DB] â†’ Nome DB: "' . $saved->nome_cliente . '" (ref: "' . $saved->nome_referente . ' ' . $saved->cognome_referente . '")');
                $this->log('[DB] â†’ Telefono DB: "' . $saved->telefono . '"');
                $this->log('[DB] â†’ Email DB: "' . $saved->email . '"');
                $this->log('[DB] â†’ Data DB: "' . $saved->data_evento . '"');
                $this->log('[DB] â†’ Tipo Evento DB: "' . $saved->tipo_evento . '"');
                $this->log('[DB] â†’ Importo DB: ' . $saved->importo_totale . ' (inviato: ' . $update_data['importo_totale'] . ')');
                $this->log('[DB] â†’ Acconto DB: ' . $saved->acconto . ' (inviato: ' . $update_data['acconto'] . ')');
                $this->log('[DB] â†’ Saldo DB: ' . $saved->saldo . ' (inviato: ' . $update_data['saldo'] . ')');
                $this->log('[DB] â†’ Extra1 DB: "' . $saved->extra1 . '" (â‚¬' . $saved->extra1_importo . ')');
                $this->log('[DB] â†’ Omaggio1 DB: "' . $saved->omaggio1 . '"');
                $this->log('[DB] =======================================');
                
                return array(
                    'success' => true,
                    'id' => $existing->id,
                    'action' => 'updated'
                );
                
            } else {
                // âœ… INSERT nuovo preventivo con preventivo_id AUTOGENERATO
                $this->log('[DB] Preventivo nuovo, INSERT');
                
                // âœ… GENERA preventivo_id PROGRESSIVO
                $max_id = $wpdb->get_var("
                    SELECT MAX(CAST(preventivo_id AS UNSIGNED)) 
                    FROM {$table_name}
                    WHERE preventivo_id != '' AND preventivo_id IS NOT NULL
                ");
                
                $next_id = intval($max_id) + 1;
                $preventivo_id = str_pad($next_id, 3, '0', STR_PAD_LEFT); // 001, 002, 003...
                
                $this->log("[DB] âœ… Generato preventivo_id: {$preventivo_id}");
                
                $insert_data = array(
                    'preventivo_id' => $preventivo_id, // âœ… CAMPO OBBLIGATORIO
                    'data_evento' => $data['data_evento'] ?? date('Y-m-d'),
                    'tipo_evento' => $data['tipo_evento'] ?? '',
                    'tipo_menu' => $data['tipo_menu'] ?? '',
                    'numero_invitati' => intval($data['numero_invitati'] ?? 0),
                    'orario_evento' => $data['orario_evento'] ?? '',
                    'nome_cliente' => $data['nome_cliente'] ?? '',
                    'nome_referente' => $data['nome_referente'] ?? '',
                    'cognome_referente' => $data['cognome_referente'] ?? '',
                    'telefono' => $data['telefono'] ?? '',
                    'email' => $data['email'] ?? '',
                    'importo_totale' => floatval($data['importo_totale'] ?? 0),
                    'acconto' => floatval($data['acconto'] ?? 0),
                    'saldo' => floatval($data['saldo'] ?? 0),
                    'omaggio1' => $data['omaggio1'] ?? '',
                    'omaggio2' => $data['omaggio2'] ?? '',
                    'omaggio3' => $data['omaggio3'] ?? '',
                    'extra1' => $data['extra1'] ?? '',
                    'extra1_importo' => floatval($data['extra1_importo'] ?? 0),
                    'extra2' => $data['extra2'] ?? '',
                    'extra2_importo' => floatval($data['extra2_importo'] ?? 0),
                    'extra3' => $data['extra3'] ?? '',
                    'extra3_importo' => floatval($data['extra3_importo'] ?? 0),
                    'stato' => $data['stato'] ?? 'attivo',
                    'excel_url' => '',
                    'pdf_url' => '',
                    'googledrive_url' => $data['googledrive_url'] ?? '',
                    'googledrive_file_id' => $data['googledrive_file_id'] ?? '',
                    'created_at' => current_time('mysql'),
                    'created_by' => get_current_user_id(),
                    'updated_at' => current_time('mysql')
                );
                
                $result = $wpdb->insert(
                    $table_name,
                    $insert_data,
                    // 31 campi: preventivo_id, data, tipo_evento, tipo_menu, numero_invitati, orario, nome, nome_ref, cognome_ref, telefono, email, importo, acconto, saldo, 3 omaggi, 3 extra+importi, stato, excel_url, pdf_url, gdrive_url, gdrive_id, created_at, created_by, updated_at
                    array('%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%f', '%f', '%f', '%s', '%s', '%s', '%s', '%f', '%s', '%f', '%s', '%f', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s')
                );
                
                if ($result === false) {
                    $this->log('[DB] âŒ ERRORE INSERT: ' . $wpdb->last_error, 'ERROR');
                    $this->log('[DB] SQL Query: ' . $wpdb->last_query, 'ERROR');
                    return array('success' => false, 'error' => $wpdb->last_error);
                }
                
                $insert_id = $wpdb->insert_id;
                
                // âœ… VERIFICA DATI SALVATI LEGGENDO DAL DB
                $saved = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table_name} WHERE id = %d", $insert_id));
                
                $this->log("[DB] âœ… Preventivo inserito con ID: {$insert_id}");
                $this->log('[DB] === VERIFICA DATI SALVATI NEL DB ===');
                $this->log('[DB] â†’ preventivo_id DB: "' . $saved->preventivo_id . '"');
                $this->log('[DB] â†’ Nome DB: "' . $saved->nome_cliente . '" (ref: "' . $saved->nome_referente . ' ' . $saved->cognome_referente . '")');
                $this->log('[DB] â†’ Telefono DB: "' . $saved->telefono . '"');
                $this->log('[DB] â†’ Email DB: "' . $saved->email . '"');
                $this->log('[DB] â†’ Data DB: "' . $saved->data_evento . '"');
                $this->log('[DB] â†’ Tipo Evento DB: "' . $saved->tipo_evento . '"');
                $this->log('[DB] â†’ Tipo Menu DB: "' . $saved->tipo_menu . '"');
                $this->log('[DB] â†’ N. Invitati DB: ' . $saved->numero_invitati);
                $this->log('[DB] â†’ Orario DB: "' . $saved->orario_evento . '"');
                $this->log('[DB] â†’ Importo DB: ' . $saved->importo_totale . ' (inviato: ' . $insert_data['importo_totale'] . ')');
                $this->log('[DB] â†’ Acconto DB: ' . $saved->acconto . ' (inviato: ' . $insert_data['acconto'] . ')');
                $this->log('[DB] â†’ Saldo DB: ' . $saved->saldo . ' (inviato: ' . $insert_data['saldo'] . ')');
                $this->log('[DB] â†’ Omaggio1 DB: "' . $saved->omaggio1 . '"');
                $this->log('[DB] â†’ Omaggio2 DB: "' . $saved->omaggio2 . '"');
                $this->log('[DB] â†’ Omaggio3 DB: "' . $saved->omaggio3 . '"');
                $this->log('[DB] â†’ Extra1 DB: "' . $saved->extra1 . '" (â‚¬' . $saved->extra1_importo . ')');
                $this->log('[DB] â†’ Extra2 DB: "' . $saved->extra2 . '" (â‚¬' . $saved->extra2_importo . ')');
                $this->log('[DB] â†’ Extra3 DB: "' . $saved->extra3 . '" (â‚¬' . $saved->extra3_importo . ')');
                $this->log('[DB] =======================================');
                
                return array(
                    'success' => true,
                    'id' => $insert_id,
                    'action' => 'inserted'
                );
            }
            
        } catch (\Exception $e) {
            $this->log('[DB] Exception: ' . $e->getMessage(), 'ERROR');
            return array('success' => false, 'error' => $e->getMessage());
        }
    }

    /**
     * Download file temporaneo
     */
    private function download_file_to_temp($file_id, $token) {
        try {
            $upload_dir = wp_upload_dir();
            $temp_dir = $upload_dir['basedir'] . '/disco747-temp';
            
            if (!is_dir($temp_dir)) {
                wp_mkdir_p($temp_dir);
            }
            
            $temp_filename = 'temp_' . time() . '_' . sanitize_file_name(basename($file_id)) . '.xlsx';
            $temp_path = $temp_dir . '/' . $temp_filename;
            
            $download_url = 'https://www.googleapis.com/drive/v3/files/' . $file_id . '?alt=media';
            
            $response = wp_remote_get($download_url, array(
                'headers' => array('Authorization' => 'Bearer ' . $token),
                'timeout' => 60
            ));
            
            if (is_wp_error($response)) {
                return array('success' => false, 'error' => $response->get_error_message());
            }
            
            $body = wp_remote_retrieve_body($response);
            
            // âœ… VALIDAZIONE CRITICA: Verifica contenuto non vuoto
            if (empty($body)) {
                $this->log('[DOWNLOAD] ERRORE: Contenuto vuoto per file_id: ' . $file_id, 'ERROR');
                return array('success' => false, 'error' => 'File vuoto scaricato da Google Drive');
            }
            
            $content_size = strlen($body);
            if ($content_size < 1024) { // File Excel deve essere minimo 1KB
                $this->log('[DOWNLOAD] WARNING: File molto piccolo (' . $content_size . ' bytes) per file_id: ' . $file_id, 'WARNING');
            }
            
            // Salva file
            $bytes_written = file_put_contents($temp_path, $body);
            
            if ($bytes_written === false || $bytes_written === 0) {
                $this->log('[DOWNLOAD] ERRORE: Impossibile scrivere file: ' . $temp_path, 'ERROR');
                return array('success' => false, 'error' => 'Impossibile salvare file su disco');
            }
            
            // âœ… VERIFICA FINALE: File salvato correttamente
            if (!file_exists($temp_path) || filesize($temp_path) === 0) {
                $this->log('[DOWNLOAD] ERRORE: File salvato ma vuoto o inesistente: ' . $temp_path, 'ERROR');
                if (file_exists($temp_path)) {
                    unlink($temp_path);
                }
                return array('success' => false, 'error' => 'File salvato ma risulta vuoto');
            }
            
            $this->log('[DOWNLOAD] âœ… File salvato: ' . $temp_path . ' (' . number_format($bytes_written) . ' bytes)');
            
            return array(
                'success' => true, 
                'path' => $temp_path,
                'size' => $bytes_written
            );
            
        } catch (\Exception $e) {
            return array('success' => false, 'error' => $e->getMessage());
        }
    }

    /**
     * Estrai dati da file Excel con validazione robusta
     */
    private function extract_data_from_excel($file_path) {
        try {
            $this->log('[EXCEL] Apertura file: ' . $file_path);
            
            // âœ… VALIDAZIONE PRE-LOAD
            if (!file_exists($file_path)) {
                $this->log('[EXCEL] ERRORE: File non esiste: ' . $file_path, 'ERROR');
                return null;
            }
            
            $file_size = filesize($file_path);
            if ($file_size === 0 || $file_size === false) {
                $this->log('[EXCEL] ERRORE: File vuoto o illeggibile (size: ' . $file_size . ')', 'ERROR');
                return null;
            }
            
            $this->log('[EXCEL] Dimensione file: ' . number_format($file_size) . ' bytes');
            
            require_once DISCO747_CRM_PLUGIN_DIR . 'vendor/autoload.php';
            
            // âœ… LOAD CON TRY-CATCH SPECIFICO
            try {
                $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($file_path);
            } catch (\PhpOffice\PhpSpreadsheet\Reader\Exception $e) {
                $this->log('[EXCEL] ERRORE PhpSpreadsheet load: ' . $e->getMessage(), 'ERROR');
                return null;
            } catch (\Exception $e) {
                $this->log('[EXCEL] ERRORE generico load: ' . $e->getMessage(), 'ERROR');
                return null;
            }
            
            // âœ… VALIDAZIONE FOGLIO
            if ($spreadsheet->getSheetCount() === 0) {
                $this->log('[EXCEL] ERRORE: Nessun foglio trovato nel file', 'ERROR');
                return null;
            }
            
            $sheet = $spreadsheet->getActiveSheet();
            
            // âœ… LOGGING DEBUG: Leggi valori raw dalle celle per debugging
            $this->log('[EXCEL-DEBUG] === LETTURA CELLE CRITICHE ===');
            $this->log('[EXCEL-DEBUG] C11 (Nome): "' . ($sheet->getCell('C11')->getValue() ?? 'VUOTO') . '"');
            $this->log('[EXCEL-DEBUG] C12 (Cognome): "' . ($sheet->getCell('C12')->getValue() ?? 'VUOTO') . '"');
            $this->log('[EXCEL-DEBUG] C14 (Telefono): "' . ($sheet->getCell('C14')->getValue() ?? 'VUOTO') . '" (tipo: ' . gettype($sheet->getCell('C14')->getValue()) . ')');
            $this->log('[EXCEL-DEBUG] C15 (Email): "' . ($sheet->getCell('C15')->getValue() ?? 'VUOTO') . '" (tipo: ' . gettype($sheet->getCell('C15')->getValue()) . ')');
            
            // âœ… LOGGING IMPORTI: Celle corrette F27, F28, F30
            $this->log('[EXCEL-DEBUG] === IMPORTI ===');
            $this->log('[EXCEL-DEBUG] F27 (Importo Totale): "' . ($sheet->getCell('F27')->getValue() ?? 'VUOTO') . '" (tipo: ' . gettype($sheet->getCell('F27')->getValue()) . ')');
            $this->log('[EXCEL-DEBUG] F28 (Acconto): "' . ($sheet->getCell('F28')->getValue() ?? 'VUOTO') . '" (tipo: ' . gettype($sheet->getCell('F28')->getValue()) . ')');
            $this->log('[EXCEL-DEBUG] F30 (Saldo): "' . ($sheet->getCell('F30')->getValue() ?? 'VUOTO') . '" (tipo: ' . gettype($sheet->getCell('F30')->getValue()) . ')');
            $this->log('[EXCEL-DEBUG] ================================');
            
            // âœ… MAPPATURA CELLE CORRETTA (Template 747 Disco)
            $nome = trim($sheet->getCell('C11')->getValue() ?? '');  // C11 = Nome
            $cognome = trim($sheet->getCell('C12')->getValue() ?? '');  // C12 = Cognome
            
            $data = array(
                // ðŸ‘¤ Dati Referente
                'nome_cliente' => trim($nome . ' ' . $cognome),  // Nome completo
                'nome_referente' => $nome,  // Nome separato
                'cognome_referente' => $cognome,  // Cognome separato
                'telefono' => trim($sheet->getCell('C14')->getValue() ?? ''),  // C14 = Telefono
                'cellulare' => trim($sheet->getCell('C14')->getValue() ?? ''),  // Alias per compatibilitÃ 
                'email' => trim($sheet->getCell('C15')->getValue() ?? ''),  // C15 = Email
                'mail' => trim($sheet->getCell('C15')->getValue() ?? ''),  // Alias per compatibilitÃ 
                
                // ðŸŽ‰ Dati Evento
                'data_evento' => $this->normalize_date($sheet->getCell('C6')->getValue()),  // C6 = Data Evento
                'tipo_evento' => trim($sheet->getCell('C7')->getValue() ?? ''),  // C7 = Tipo Evento
                'orario_evento' => trim($sheet->getCell('C8')->getValue() ?? ''),  // C8 = Orario
                'numero_invitati' => intval($sheet->getCell('C9')->getValue() ?? 0),  // C9 = N. Invitati
                
                // ðŸ½ï¸ Menu
                'tipo_menu' => trim($sheet->getCell('B1')->getValue() ?? ''),  // B1 = Tipo Menu
                
                // ðŸ’° Importi (CORRETTI - con getCalculatedValue per formule!)
                'importo_totale' => $this->parse_currency_value($sheet->getCell('F27')->getCalculatedValue()),  // F27 = Importo Totale âœ…
                'acconto' => $this->parse_currency_value($sheet->getCell('F28')->getCalculatedValue()),  // F28 = Acconto âœ…
                'saldo' => $this->parse_currency_value($sheet->getCell('F30')->getCalculatedValue()),  // F30 = Saldo (FORMULA!) âœ…
                
                // ðŸŽ Omaggi
                'omaggio1' => trim($sheet->getCell('C17')->getValue() ?? ''),  // C17 = Omaggio 1
                'omaggio2' => trim($sheet->getCell('C18')->getValue() ?? ''),  // C18 = Omaggio 2
                'omaggio3' => trim($sheet->getCell('C19')->getValue() ?? ''),  // C19 = Omaggio 3
                
                // ðŸ’° Extra a Pagamento (CORRETTI - Colonna B per nomi, F con formule!)
                'extra1' => trim($sheet->getCell('B33')->getValue() ?? ''),  // B33 = Extra 1 Nome âœ…
                'extra1_importo' => $this->parse_currency_value($sheet->getCell('F33')->getCalculatedValue()),  // F33 = Extra 1 Importo
                'extra2' => trim($sheet->getCell('B34')->getValue() ?? ''),  // B34 = Extra 2 Nome âœ…
                'extra2_importo' => $this->parse_currency_value($sheet->getCell('F34')->getCalculatedValue()),  // F34 = Extra 2 Importo
                'extra3' => trim($sheet->getCell('B35')->getValue() ?? ''),  // B35 = Extra 3 Nome âœ…
                'extra3_importo' => $this->parse_currency_value($sheet->getCell('F35')->getCalculatedValue()),  // F35 = Extra 3 Importo
                
                'stato' => 'attivo'
            );
            
            // âœ… LOGGING FINALE DATI ESTRATTI
            $this->log('[EXCEL] === DATI ESTRATTI FINALI ===');
            $this->log('[EXCEL] Nome: "' . $data['nome_cliente'] . '" (nome_ref: "' . ($data['nome_referente'] ?? '') . '", cognome_ref: "' . ($data['cognome_referente'] ?? '') . '")');
            $this->log('[EXCEL] Telefono: "' . $data['telefono'] . '"');
            $this->log('[EXCEL] Email: "' . $data['email'] . '"');
            $this->log('[EXCEL] Data Evento: "' . $data['data_evento'] . '"');
            $this->log('[EXCEL] Tipo Evento: "' . $data['tipo_evento'] . '"');
            $this->log('[EXCEL] Tipo Menu: "' . ($data['tipo_menu'] ?? '') . '" (da B1)');
            $this->log('[EXCEL] Orario Evento: "' . ($data['orario_evento'] ?? '') . '" (da C8)');
            $this->log('[EXCEL] Numero Invitati: ' . ($data['numero_invitati'] ?? 0) . ' (da C9)');
            $this->log('[EXCEL] Importo Totale: ' . $data['importo_totale'] . ' (da F27)');
            $this->log('[EXCEL] Acconto: ' . $data['acconto'] . ' (da F28)');
            $this->log('[EXCEL] Saldo: ' . ($data['saldo'] ?? 0) . ' (da F30)');
            $this->log('[EXCEL] Omaggio1: "' . ($data['omaggio1'] ?? '') . '"');
            $this->log('[EXCEL] Omaggio2: "' . ($data['omaggio2'] ?? '') . '"');
            $this->log('[EXCEL] Omaggio3: "' . ($data['omaggio3'] ?? '') . '"');
            $this->log('[EXCEL] Extra1: "' . ($data['extra1'] ?? '') . '" (â‚¬' . ($data['extra1_importo'] ?? 0) . ')');
            $this->log('[EXCEL] Extra2: "' . ($data['extra2'] ?? '') . '" (â‚¬' . ($data['extra2_importo'] ?? 0) . ')');
            $this->log('[EXCEL] Extra3: "' . ($data['extra3'] ?? '') . '" (â‚¬' . ($data['extra3_importo'] ?? 0) . ')');
            $this->log('[EXCEL] ==============================');
            
            return $data;
            
        } catch (\Exception $e) {
            $this->log('[EXCEL] Errore: ' . $e->getMessage(), 'ERROR');
            return null;
        }
    }

    /**
     * Parse currency value - gestisce numeri, stringhe formattate, e valute
     */
    private function parse_currency_value($value) {
        if (empty($value) || $value === null) {
            return 0.00;
        }
        
        // Se Ã¨ giÃ  un numero (PhpSpreadsheet restituisce float per celle numeriche)
        if (is_numeric($value) && !is_string($value)) {
            $this->log('[CURRENCY] Valore giÃ  numerico: ' . $value);
            return floatval($value);
        }
        
        // Se Ã¨ una stringa, puliscila
        $str_value = strval($value);
        $this->log('[CURRENCY] Parsing stringa: "' . $str_value . '"');
        
        // Rimuovi simboli valuta comuni: â‚¬ $ Â£ Â¥ e spazi
        $str_value = str_replace(['â‚¬', '$', 'Â£', 'Â¥', ' ', chr(194).chr(160)], '', $str_value); // Include non-breaking space
        
        // Gestisci formato italiano (1.590,00 â†’ 1590.00)
        if (strpos($str_value, ',') !== false && strpos($str_value, '.') !== false) {
            // Entrambi presenti: rimuovi punti (migliaia) e sostituisci virgola con punto
            $str_value = str_replace('.', '', $str_value);
            $str_value = str_replace(',', '.', $str_value);
        } elseif (strpos($str_value, ',') !== false) {
            // Solo virgola: sostituisci con punto (decimali italiani)
            $str_value = str_replace(',', '.', $str_value);
        }
        // Se solo punto, lascia cosÃ¬ (formato US)
        
        $result = floatval($str_value);
        $this->log('[CURRENCY] Risultato: ' . $result);
        
        return $result;
    }

    /**
     * Normalizza data Excel
     */
    private function normalize_date($value) {
        if (empty($value)) {
            return date('Y-m-d');
        }
        
        // Se Ã¨ giÃ  formato Y-m-d
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return $value;
        }
        
        // Se Ã¨ formato d/m/Y
        if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/', $value, $matches)) {
            return $matches[3] . '-' . str_pad($matches[2], 2, '0', STR_PAD_LEFT) . '-' . str_pad($matches[1], 2, '0', STR_PAD_LEFT);
        }
        
        // Se Ã¨ numero seriale Excel
        if (is_numeric($value) && $value > 25569) {
            $unix_date = ($value - 25569) * 86400;
            return date('Y-m-d', $unix_date);
        }
        
        return date('Y-m-d');
    }

    /**
     * Trova cartella principale
     */
    private function find_main_folder_safe($token) {
        try {
            $query = "mimeType='application/vnd.google-apps.folder' and name='747-Preventivi' and trashed=false";
            
            $url = 'https://www.googleapis.com/drive/v3/files?' . http_build_query(array(
                'q' => $query,
                'fields' => 'files(id,name)',
                'pageSize' => 10
            ));
            
            $response = wp_remote_get($url, array(
                'headers' => array('Authorization' => 'Bearer ' . $token),
                'timeout' => 30
            ));
            
            if (!is_wp_error($response)) {
                $body = wp_remote_retrieve_body($response);
                $data = json_decode($body, true);
                
                if (isset($data['files']) && !empty($data['files'])) {
                    return $data['files'][0]['id'];
                }
            }
            
            return null;
            
        } catch (\Exception $e) {
            $this->log("Errore ricerca cartella: " . $e->getMessage(), 'ERROR');
            return null;
        }
    }

    /**
     * Scansione ricorsiva file Excel
     */
    private function scan_all_excel_files_recursive($folder_id, $token, $year = '', $month = '', $current_path = '') {
        $all_files = array();
        
        try {
            // 🔍 Ottieni informazioni sulla cartella corrente
            $folder_info = $this->get_folder_info($folder_id, $token);
            $folder_name = $folder_info['name'] ?? '';
            $new_path = $current_path ? $current_path . '/' . $folder_name : $folder_name;
            
            $this->log("[RECURSIVE-SCAN] Scansiono: {$new_path}");
            
            // ✅ FILTRO CARTELLE ANNO: Se year è specificato, salta cartelle di altri anni
            if (!empty($year) && preg_match('/^(20\d{2})$/', $folder_name, $matches)) {
                if ($matches[1] !== $year) {
                    $this->log("[FILTER] ⏭️ Salto anno {$folder_name} (cerco solo {$year})");
                    return array(); // Salta questa cartella e tutte le sue sottocartelle
                }
            }
            
            // ✅ FILTRO CARTELLE MESE: Se month è specificato, salta cartelle di altri mesi
            if (!empty($month)) {
                $month_names = array(
                    'GENNAIO' => '01', 'FEBBRAIO' => '02', 'MARZO' => '03', 'APRILE' => '04',
                    'MAGGIO' => '05', 'GIUGNO' => '06', 'LUGLIO' => '07', 'AGOSTO' => '08',
                    'SETTEMBRE' => '09', 'OTTOBRE' => '10', 'NOVEMBRE' => '11', 'DICEMBRE' => '12'
                );
                
                $folder_upper = strtoupper($folder_name);
                
                // Controlla se la cartella corrente è una cartella mese
                if (isset($month_names[$folder_upper])) {
                    $folder_month_num = $month_names[$folder_upper];
                    
                    // Normalizza $month: se è un nome (NOVEMBRE), converti in numero (11)
                    $month_upper = strtoupper($month);
                    $target_month = isset($month_names[$month_upper]) ? $month_names[$month_upper] : $month;
                    
                    // Salta solo se il numero del mese NON corrisponde
                    if ($folder_month_num !== $target_month) {
                        $this->log("[FILTER] ⏭️ Salto mese {$folder_name} (cerco solo mese {$month})");
                        return array(); // Salta questa cartella
                    } else {
                        $this->log("[FILTER] ✅ Entro nel mese {$folder_name} (corrisponde a {$month})");
                    }
                }
            }
            
            // Cerca file Excel nella cartella corrente
            $query = "'{$folder_id}' in parents and (mimeType='application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' or mimeType='application/vnd.ms-excel') and trashed=false";
            
            $url = 'https://www.googleapis.com/drive/v3/files?' . http_build_query(array(
                'q' => $query,
                'fields' => 'files(id,name,size,modifiedTime)',
                'pageSize' => 100
            ));
            
            $response = wp_remote_get($url, array(
                'headers' => array('Authorization' => 'Bearer ' . $token),
                'timeout' => 30
            ));
            
            if (!is_wp_error($response)) {
                $body = wp_remote_retrieve_body($response);
                $data = json_decode($body, true);
                
                if (isset($data['files'])) {
                    foreach ($data['files'] as $file) {
                        $all_files[] = array(
                            'googledrive_id' => $file['id'],
                            'filename' => $file['name'],
                            'file_size' => $file['size'] ?? 0,
                            'modified_time' => $file['modifiedTime'] ?? '',
                            'path' => $new_path
                        );
                    }
                    $this->log("[RECURSIVE-SCAN] ✅ Trovati " . count($data['files']) . " file Excel in {$new_path}");
                }
            }
            
            // Cerca nelle sottocartelle
            $query_folders = "'{$folder_id}' in parents and mimeType='application/vnd.google-apps.folder' and trashed=false";
            
            $url_folders = 'https://www.googleapis.com/drive/v3/files?' . http_build_query(array(
                'q' => $query_folders,
                'fields' => 'files(id,name)',
                'pageSize' => 50
            ));
            
            $response_folders = wp_remote_get($url_folders, array(
                'headers' => array('Authorization' => 'Bearer ' . $token),
                'timeout' => 30
            ));
            
            if (!is_wp_error($response_folders)) {
                $body_folders = wp_remote_retrieve_body($response_folders);
                $data_folders = json_decode($body_folders, true);
                
                if (isset($data_folders['files'])) {
                    foreach ($data_folders['files'] as $subfolder) {
                        // Ricorsione con filtri
                        $subfiles = $this->scan_all_excel_files_recursive($subfolder['id'], $token, $year, $month, $new_path);
                        $all_files = array_merge($all_files, $subfiles);
                    }
                }
            }
            
        } catch (\Exception $e) {
            $this->log("Errore scansione: " . $e->getMessage(), 'ERROR');
        }
        
        return $all_files;
    }
    
    /**
     * Ottiene informazioni su una cartella
     */
    private function get_folder_info($folder_id, $token) {
        try {
            $url = "https://www.googleapis.com/drive/v3/files/{$folder_id}?fields=id,name";
            
            $response = wp_remote_get($url, array(
                'headers' => array('Authorization' => 'Bearer ' . $token),
                'timeout' => 10
            ));
            
            if (!is_wp_error($response)) {
                $body = wp_remote_retrieve_body($response);
                return json_decode($body, true);
            }
        } catch (\Exception $e) {
            $this->log("Errore get_folder_info: " . $e->getMessage(), 'ERROR');
        }
        
        return array('name' => 'Unknown');
    }

    /**
     * Logging
     */
    private function log($message, $level = 'INFO') {
        if (!$this->debug_mode) return;
        
        $prefix = '[747Disco-GDriveSync]';
        error_log("{$prefix} {$message}");
    }
}