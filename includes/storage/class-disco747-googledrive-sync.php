<?php
/**
 * Classe per sincronizzazione preventivi da Google Drive
 * VERSIONE 12.0.0-COMPLETE: Batch scan completo con mapping corretto
 * 
 * @package    Disco747_CRM
 * @subpackage Storage
 * @since      12.0.0
 * @author     747 Disco Team
 */

namespace Disco747_CRM\Storage;

if (!defined('ABSPATH')) {
    exit('Accesso diretto non consentito');
}

class Disco747_GoogleDrive_Sync {

    private $googledrive;
    private $database;
    private $debug_mode = true;
    private $sync_available = false;

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
     * ✅ METODO PUBBLICO: Verifica se Google Drive Sync è disponibile
     */
    public function is_available() {
        return $this->sync_available;
    }

    /**
     * ✅ METODO PRINCIPALE: Batch scan Excel files con limite di 2 file per volta
     * 
     * @param string $year Anno da scansionare
     * @param string $month Mese da scansionare (opzionale)
     * @param bool $reset Se true, è una scansione dopo reset
     * @return array Risultati scansione
     */
    public function batch_scan_excel_files($year = '', $month = '', $reset = false) {
        $this->log('[747Disco-Scan] ========== INIZIO BATCH SCAN ==========');
        
        $start_time = microtime(true);
        $messages = array();
        $processed = 0;
        $inserted = 0;
        $updated = 0;
        $errors = 0;
        $skipped = 0;
        $total_files = 0;
        
        try {
            // Verifica disponibilità
            if (!$this->sync_available || !$this->googledrive) {
                throw new \Exception('Google Drive non disponibile');
            }
            
            $messages[] = '✅ Google Drive disponibile';
            $this->log('[747Disco-Scan] Google Drive handler OK');
            
            // Ottieni token valido
            $token = $this->googledrive->get_valid_access_token();
            if (!$token) {
                throw new \Exception('Token Google Drive non valido. Riautenticare.');
            }
            
            $messages[] = '✅ Token Google Drive ottenuto';
            $this->log('[747Disco-Scan] Token ottenuto');
            
            // Trova cartella principale /747-Preventivi/
            $main_folder_id = $this->find_main_folder($token);
            if (!$main_folder_id) {
                throw new \Exception('Cartella /747-Preventivi/ non trovata su Google Drive');
            }
            
            $messages[] = '✅ Cartella /747-Preventivi/ trovata';
            $this->log('[747Disco-Scan] Cartella principale ID: ' . $main_folder_id);
            
            // Trova tutti i file Excel nella struttura Anno/Mese
            $all_files = $this->find_excel_files_in_structure($main_folder_id, $token, $year, $month);
            $total_files = count($all_files);
            
            $messages[] = "📊 Trovati {$total_files} file Excel";
            $this->log("[747Disco-Scan] Trovati {$total_files} file totali");
            
            if ($total_files === 0) {
                return array(
                    'success' => true,
                    'total_files' => 0,
                    'processed' => 0,
                    'inserted' => 0,
                    'updated' => 0,
                    'errors' => 0,
                    'skipped' => 0,
                    'duration_ms' => round((microtime(true) - $start_time) * 1000),
                    'messages' => array_merge($messages, array('⚠️ Nessun file da processare'))
                );
            }
            
            // Processa file 2 alla volta per evitare timeout
            $batch_size = 2;
            $batches = array_chunk($all_files, $batch_size);
            
            foreach ($batches as $batch_index => $batch) {
                $this->log("[747Disco-Scan] Batch " . ($batch_index + 1) . "/" . count($batches) . " con " . count($batch) . " file");
                
                foreach ($batch as $file) {
                    try {
                        $this->log('[747Disco-Scan] Processando file: ' . $file['filename']);
                        
                        $result = $this->process_single_file($file, $token);
                        
                        if ($result['success']) {
                            $processed++;
                            if ($result['action'] === 'inserted') {
                                $inserted++;
                            } elseif ($result['action'] === 'updated') {
                                $updated++;
                            }
                            $messages[] = "✅ " . $file['filename'];
                        } else {
                            $errors++;
                            $messages[] = "❌ " . $file['filename'] . ": " . $result['error'];
                            $this->log('[747Disco-Scan] Errore: ' . $result['error'], 'ERROR');
                        }
                        
                    } catch (\Exception $e) {
                        $errors++;
                        $messages[] = "❌ " . $file['filename'] . ": " . $e->getMessage();
                        $this->log('[747Disco-Scan] Exception: ' . $e->getMessage(), 'ERROR');
                    }
                }
                
                // Pausa tra batch per evitare rate limiting
                if ($batch_index < count($batches) - 1) {
                    usleep(500000); // 500ms
                }
            }
            
            $duration_ms = round((microtime(true) - $start_time) * 1000);
            
            $messages[] = "✅ Scansione completata in {$duration_ms}ms";
            $messages[] = "📊 Processati: {$processed}, Nuovi: {$inserted}, Aggiornati: {$updated}, Errori: {$errors}";
            
            $this->log('[747Disco-Scan] ========== BATCH SCAN COMPLETATO ==========');
            $this->log("[747Disco-Scan] Totale: {$total_files}, Processati: {$processed}, Nuovi: {$inserted}, Aggiornati: {$updated}, Errori: {$errors}");
            
            return array(
                'success' => $errors < $total_files, // Success se almeno qualche file è stato processato
                'total_files' => $total_files,
                'processed' => $processed,
                'inserted' => $inserted,
                'updated' => $updated,
                'errors' => $errors,
                'skipped' => $skipped,
                'duration_ms' => $duration_ms,
                'messages' => $messages
            );
            
        } catch (\Exception $e) {
            $duration_ms = round((microtime(true) - $start_time) * 1000);
            
            $this->log('[747Disco-Scan] ERRORE CRITICO: ' . $e->getMessage(), 'ERROR');
            
            return array(
                'success' => false,
                'total_files' => $total_files,
                'processed' => $processed,
                'inserted' => $inserted,
                'updated' => $updated,
                'errors' => $errors + 1,
                'skipped' => $skipped,
                'duration_ms' => $duration_ms,
                'messages' => array_merge($messages, array('❌ Errore: ' . $e->getMessage()))
            );
        }
    }

    /**
     * Trova cartella principale /747-Preventivi/
     */
    private function find_main_folder($token) {
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
            
            if (is_wp_error($response)) {
                throw new \Exception($response->get_error_message());
            }
            
            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);
            
            if (isset($data['files']) && !empty($data['files'])) {
                return $data['files'][0]['id'];
            }
            
            return null;
            
        } catch (\Exception $e) {
            $this->log("[747Disco-Scan] Errore ricerca cartella: " . $e->getMessage(), 'ERROR');
            return null;
        }
    }

    /**
     * Trova file Excel nella struttura Anno/Mese
     */
    private function find_excel_files_in_structure($main_folder_id, $token, $year = '', $month = '') {
        $all_files = array();
        
        try {
            // Se non specificato anno, usa anno corrente
            if (empty($year)) {
                $year = date('Y');
            }
            
            // Trova cartella anno
            $year_folders = $this->find_subfolders($main_folder_id, $token);
            
            foreach ($year_folders as $year_folder) {
                // Filtra per anno se specificato
                if (!empty($year) && $year_folder['name'] !== $year) {
                    continue;
                }
                
                $this->log("[747Disco-Scan] Scansione anno: " . $year_folder['name']);
                
                // Trova cartelle mesi
                $month_folders = $this->find_subfolders($year_folder['id'], $token);
                
                foreach ($month_folders as $month_folder) {
                    // Filtra per mese se specificato
                    if (!empty($month) && strtoupper($month_folder['name']) !== strtoupper($month)) {
                        continue;
                    }
                    
                    $this->log("[747Disco-Scan] Scansione mese: " . $month_folder['name']);
                    
                    // Trova file Excel in questa cartella
                    $excel_files = $this->find_excel_files_in_folder($month_folder['id'], $token);
                    
                    // Aggiungi metadati path
                    foreach ($excel_files as $file) {
                        $file['year'] = $year_folder['name'];
                        $file['month'] = $month_folder['name'];
                        $all_files[] = $file;
                    }
                }
            }
            
            return $all_files;
            
        } catch (\Exception $e) {
            $this->log("[747Disco-Scan] Errore scansione struttura: " . $e->getMessage(), 'ERROR');
            return array();
        }
    }

    /**
     * Trova sottocartelle
     */
    private function find_subfolders($parent_id, $token) {
        try {
            $query = "'{$parent_id}' in parents and mimeType='application/vnd.google-apps.folder' and trashed=false";
            
            $url = 'https://www.googleapis.com/drive/v3/files?' . http_build_query(array(
                'q' => $query,
                'fields' => 'files(id,name)',
                'pageSize' => 100
            ));
            
            $response = wp_remote_get($url, array(
                'headers' => array('Authorization' => 'Bearer ' . $token),
                'timeout' => 30
            ));
            
            if (is_wp_error($response)) {
                return array();
            }
            
            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);
            
            return $data['files'] ?? array();
            
        } catch (\Exception $e) {
            $this->log("[747Disco-Scan] Errore ricerca sottocartelle: " . $e->getMessage(), 'ERROR');
            return array();
        }
    }

    /**
     * Trova file Excel in una cartella specifica
     */
    private function find_excel_files_in_folder($folder_id, $token) {
        try {
            $query = "'{$folder_id}' in parents and (mimeType='application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' or name contains '.xlsx') and trashed=false";
            
            $url = 'https://www.googleapis.com/drive/v3/files?' . http_build_query(array(
                'q' => $query,
                'fields' => 'files(id,name,size,modifiedTime)',
                'pageSize' => 100
            ));
            
            $response = wp_remote_get($url, array(
                'headers' => array('Authorization' => 'Bearer ' . $token),
                'timeout' => 30
            ));
            
            if (is_wp_error($response)) {
                return array();
            }
            
            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);
            
            $files = array();
            if (isset($data['files'])) {
                foreach ($data['files'] as $file) {
                    $files[] = array(
                        'googledrive_id' => $file['id'],
                        'filename' => $file['name'],
                        'file_size' => $file['size'] ?? 0,
                        'modified_time' => $file['modifiedTime'] ?? ''
                    );
                }
            }
            
            return $files;
            
        } catch (\Exception $e) {
            $this->log("[747Disco-Scan] Errore ricerca file Excel: " . $e->getMessage(), 'ERROR');
            return array();
        }
    }

    /**
     * Processa singolo file Excel
     */
    private function process_single_file($file, $token) {
        $this->log('[747Disco-Scan] Processando file: ' . $file['filename']);
        
        $temp_file_path = null;
        
        try {
            // Download file temporaneo
            $temp_result = $this->download_file_to_temp($file['googledrive_id'], $token);
            
            if (!$temp_result['success']) {
                throw new \Exception('Errore download: ' . $temp_result['error']);
            }
            
            $temp_file_path = $temp_result['path'];
            $this->log('[747Disco-Scan] File scaricato: ' . $temp_file_path);
            
            // Parsing Excel
            $extracted_data = $this->parse_excel_file($temp_file_path, $file['filename']);
            
            if (!$extracted_data) {
                throw new \Exception('Parsing fallito');
            }
            
            $this->log('[747Disco-Scan] Dati estratti da: ' . $file['filename']);
            
            // Aggiungi metadati
            $extracted_data['googledrive_file_id'] = $file['googledrive_id'];
            $extracted_data['googledrive_url'] = 'https://drive.google.com/file/d/' . $file['googledrive_id'] . '/view';
            
            // Salva nel database
            $save_result = $this->save_to_database($extracted_data);
            
            // Cleanup
            if (file_exists($temp_file_path)) {
                @unlink($temp_file_path);
            }
            
            return $save_result;
            
        } catch (\Exception $e) {
            // Cleanup in caso di errore
            if ($temp_file_path && file_exists($temp_file_path)) {
                @unlink($temp_file_path);
            }
            
            return array(
                'success' => false,
                'error' => $e->getMessage()
            );
        }
    }

    /**
     * Download file temporaneo
     */
    private function download_file_to_temp($file_id, $token) {
        try {
            $upload_dir = wp_upload_dir();
            $temp_dir = $upload_dir['basedir'] . '/preventivi/temp/';
            
            if (!is_dir($temp_dir)) {
                wp_mkdir_p($temp_dir);
            }
            
            $temp_filename = 'temp_' . time() . '_' . wp_rand(1000, 9999) . '.xlsx';
            $temp_path = $temp_dir . $temp_filename;
            
            $download_url = 'https://www.googleapis.com/drive/v3/files/' . $file_id . '?alt=media';
            
            $response = wp_remote_get($download_url, array(
                'headers' => array('Authorization' => 'Bearer ' . $token),
                'timeout' => 60
            ));
            
            if (is_wp_error($response)) {
                return array('success' => false, 'error' => $response->get_error_message());
            }
            
            $body = wp_remote_retrieve_body($response);
            
            if (empty($body)) {
                return array('success' => false, 'error' => 'File vuoto');
            }
            
            file_put_contents($temp_path, $body);
            
            $this->log('[747Disco-Scan] File salvato temporaneamente: ' . $temp_filename);
            
            return array('success' => true, 'path' => $temp_path);
            
        } catch (\Exception $e) {
            return array('success' => false, 'error' => $e->getMessage());
        }
    }

    /**
     * ✅ PARSING COMPLETO: Estrae dati da file Excel secondo mapping richiesto
     */
    private function parse_excel_file($file_path, $filename) {
        try {
            $this->log('[747Disco-Scan] Apertura Excel con PhpSpreadsheet');
            
            // Carica PhpSpreadsheet
            $autoload = DISCO747_CRM_PLUGIN_DIR . 'vendor/autoload.php';
            if (file_exists($autoload)) {
                require_once $autoload;
            }
            
            if (!class_exists('PhpOffice\\PhpSpreadsheet\\IOFactory')) {
                throw new \Exception('PhpSpreadsheet non disponibile');
            }
            
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($file_path);
            $sheet = $spreadsheet->getActiveSheet();
            
            // Leggi tipo_menu da B1 per determinare il mapping corretto
            $tipo_menu_raw = trim($sheet->getCell('B1')->getValue() ?? '');
            $tipo_menu = $this->extract_menu_type($tipo_menu_raw, $filename);
            
            $this->log('[747Disco-Scan] Tipo menu rilevato: ' . $tipo_menu);
            
            // ✅ MAPPING CELLE secondo specifiche
            $data = array(
                // Dati evento
                'data_evento' => $this->parse_date($sheet->getCell('C6')->getValue()),
                'tipo_evento' => $this->clean_value($sheet->getCell('C7')->getValue()),
                'tipo_menu' => $tipo_menu,
                'orario_evento' => $this->clean_value($sheet->getCell('C8')->getValue()),
                'numero_invitati' => $this->parse_number($sheet->getCell('C9')->getValue()),
                
                // Dati cliente/referente
                'nome_referente' => $this->clean_value($sheet->getCell('C11')->getValue()),
                'cognome_referente' => $this->clean_value($sheet->getCell('C12')->getValue()),
                'telefono' => $this->clean_value($sheet->getCell('C14')->getValue()),
                'email' => $this->clean_value($sheet->getCell('C15')->getValue()),
                
                // Omaggi
                'omaggio1' => $this->clean_value($sheet->getCell('C17')->getValue()),
                'omaggio2' => $this->clean_value($sheet->getCell('C18')->getValue()),
                'omaggio3' => $this->clean_value($sheet->getCell('C19')->getValue()),
                
                // Importi
                'importo_totale' => $this->parse_currency($sheet->getCell('F27')->getValue()),
                'acconto' => $this->parse_currency($sheet->getCell('F28')->getValue()),
                'saldo' => $this->parse_currency($sheet->getCell('F30')->getValue()),
                
                // Extra a pagamento
                'extra1' => $this->clean_value($sheet->getCell('C33')->getValue()),
                'extra1_importo' => $this->parse_currency($sheet->getCell('F33')->getValue()),
                'extra2' => $this->clean_value($sheet->getCell('C34')->getValue()),
                'extra2_importo' => $this->parse_currency($sheet->getCell('F34')->getValue()),
                'extra3' => $this->clean_value($sheet->getCell('C35')->getValue()),
                'extra3_importo' => $this->parse_currency($sheet->getCell('F35')->getValue()),
            );
            
            // Costruisci nome_cliente da nome e cognome
            $data['nome_cliente'] = trim($data['nome_referente'] . ' ' . $data['cognome_referente']);
            
            // Calcola importo_preventivo e saldo se necessario
            $extra_totale = $data['extra1_importo'] + $data['extra2_importo'] + $data['extra3_importo'];
            $data['importo_preventivo'] = $data['importo_totale'] + $extra_totale;
            
            // Determina stato dal filename
            $data['stato'] = $this->determine_stato($filename, $data['acconto']);
            
            // Split orario in inizio/fine se possibile
            $this->parse_orario($data);
            
            $this->log('[747Disco-Scan] Parsing completato: ' . $filename . ' - Evento: ' . $data['tipo_evento'] . ', Importo: €' . number_format($data['importo_totale'], 2));
            
            return $data;
            
        } catch (\Exception $e) {
            $this->log('[747Disco-Scan] Errore parsing: ' . $e->getMessage(), 'ERROR');
            return null;
        }
    }

    /**
     * Estrae tipo menu
     */
    private function extract_menu_type($tipo_menu_cell, $filename) {
        // Prova da cella B1
        if (!empty($tipo_menu_cell)) {
            if (preg_match('/(Menu\s*7-4-7|Menu\s*747|7-4-7|747)/i', $tipo_menu_cell)) {
                return 'Menu 7-4-7';
            }
            if (preg_match('/(Menu\s*7-4|Menu\s*74|7-4|74)/i', $tipo_menu_cell)) {
                return 'Menu 7-4';
            }
            if (preg_match('/(Menu\s*7|^7$)/i', $tipo_menu_cell)) {
                return 'Menu 7';
            }
        }
        
        // Prova da filename
        if (preg_match('/\(Menu\s*(7-4-7|747)\)/i', $filename)) {
            return 'Menu 7-4-7';
        }
        if (preg_match('/\(Menu\s*(7-4|74)\)/i', $filename)) {
            return 'Menu 7-4';
        }
        if (preg_match('/\(Menu\s*7\)/i', $filename)) {
            return 'Menu 7';
        }
        
        // Default
        return 'Menu 7';
    }

    /**
     * Determina stato dal filename e acconto
     */
    private function determine_stato($filename, $acconto) {
        if (stripos($filename, 'CONF ') === 0 || $acconto > 0) {
            return 'confermato';
        }
        if (stripos($filename, 'NO ') === 0) {
            return 'annullato';
        }
        return 'attivo';
    }

    /**
     * Parsing data
     */
    private function parse_date($value) {
        if (empty($value)) {
            return date('Y-m-d');
        }
        
        // Se è già Y-m-d
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return $value;
        }
        
        // Se è d/m/Y
        if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/', $value, $matches)) {
            return $matches[3] . '-' . str_pad($matches[2], 2, '0', STR_PAD_LEFT) . '-' . str_pad($matches[1], 2, '0', STR_PAD_LEFT);
        }
        
        // Se è numero seriale Excel
        if (is_numeric($value) && $value > 25569) {
            $unix_date = ($value - 25569) * 86400;
            return date('Y-m-d', $unix_date);
        }
        
        // Prova strtotime
        $timestamp = strtotime($value);
        if ($timestamp) {
            return date('Y-m-d', $timestamp);
        }
        
        return date('Y-m-d');
    }

    /**
     * Parsing numero
     */
    private function parse_number($value) {
        if (empty($value)) {
            return 0;
        }
        return intval($value);
    }

    /**
     * Parsing valuta
     */
    private function parse_currency($value) {
        if (empty($value)) {
            return 0.00;
        }
        
        $cleaned = preg_replace('/[€$£,\s]/', '', strval($value));
        return floatval($cleaned);
    }

    /**
     * Pulisce valore
     */
    private function clean_value($value) {
        if ($value === null) {
            return '';
        }
        return trim(strval($value));
    }

    /**
     * Parsing orario
     */
    private function parse_orario(&$data) {
        if (!empty($data['orario_evento']) && strpos($data['orario_evento'], '-') !== false) {
            $parts = explode('-', $data['orario_evento']);
            $data['orario_inizio'] = trim($parts[0]);
            $data['orario_fine'] = trim($parts[1]);
        } else {
            $data['orario_inizio'] = $data['orario_evento'] ?: '20:30';
            $data['orario_fine'] = '01:30';
        }
    }

    /**
     * ✅ Salva nel database con upsert
     */
    private function save_to_database($data) {
        global $wpdb;
        
        try {
            $table_name = $wpdb->prefix . 'disco747_preventivi';
            
            // Controlla se esiste già tramite googledrive_file_id
            $existing = null;
            if (!empty($data['googledrive_file_id'])) {
                $existing = $wpdb->get_row($wpdb->prepare(
                    "SELECT * FROM {$table_name} WHERE googledrive_file_id = %s",
                    $data['googledrive_file_id']
                ));
            }
            
            if ($existing) {
                // UPDATE
                $this->log('[747Disco-Scan] Preventivo esistente trovato (ID: ' . $existing->id . '), UPDATE');
                
                $update_data = array(
                    'data_evento' => $data['data_evento'],
                    'tipo_evento' => $data['tipo_evento'],
                    'tipo_menu' => $data['tipo_menu'],
                    'numero_invitati' => $data['numero_invitati'],
                    'orario_evento' => $data['orario_evento'] ?? '',
                    'orario_inizio' => $data['orario_inizio'] ?? '20:30',
                    'orario_fine' => $data['orario_fine'] ?? '01:30',
                    'nome_cliente' => $data['nome_cliente'],
                    'nome_referente' => $data['nome_referente'],
                    'cognome_referente' => $data['cognome_referente'],
                    'telefono' => $data['telefono'],
                    'email' => $data['email'],
                    'importo_totale' => $data['importo_totale'],
                    'importo_preventivo' => $data['importo_preventivo'],
                    'acconto' => $data['acconto'],
                    'saldo' => $data['saldo'],
                    'omaggio1' => $data['omaggio1'],
                    'omaggio2' => $data['omaggio2'],
                    'omaggio3' => $data['omaggio3'],
                    'extra1' => $data['extra1'],
                    'extra1_importo' => $data['extra1_importo'],
                    'extra2' => $data['extra2'],
                    'extra2_importo' => $data['extra2_importo'],
                    'extra3' => $data['extra3'],
                    'extra3_importo' => $data['extra3_importo'],
                    'stato' => $data['stato'],
                    'googledrive_url' => $data['googledrive_url'],
                    'updated_at' => current_time('mysql')
                );
                
                $result = $wpdb->update(
                    $table_name,
                    $update_data,
                    array('id' => $existing->id)
                );
                
                if ($result === false) {
                    $this->log('[747Disco-Scan] Errore UPDATE: ' . $wpdb->last_error, 'ERROR');
                    return array('success' => false, 'error' => $wpdb->last_error);
                }
                
                $this->log('[747Disco-Scan] ✅ Preventivo aggiornato ID: ' . $existing->id);
                
                return array(
                    'success' => true,
                    'id' => $existing->id,
                    'action' => 'updated'
                );
                
            } else {
                // INSERT nuovo preventivo
                $this->log('[747Disco-Scan] Preventivo nuovo, INSERT');
                
                // Genera preventivo_id progressivo
                $max_id = $wpdb->get_var("
                    SELECT MAX(CAST(SUBSTRING(preventivo_id, 2) AS UNSIGNED)) 
                    FROM {$table_name}
                    WHERE preventivo_id LIKE '#%'
                ");
                
                $next_id = intval($max_id) + 1;
                $preventivo_id = '#' . str_pad($next_id, 3, '0', STR_PAD_LEFT);
                
                $this->log("[747Disco-Scan] Generato preventivo_id: {$preventivo_id}");
                
                $insert_data = array(
                    'preventivo_id' => $preventivo_id,
                    'data_evento' => $data['data_evento'],
                    'tipo_evento' => $data['tipo_evento'],
                    'tipo_menu' => $data['tipo_menu'],
                    'numero_invitati' => $data['numero_invitati'],
                    'orario_evento' => $data['orario_evento'] ?? '',
                    'orario_inizio' => $data['orario_inizio'] ?? '20:30',
                    'orario_fine' => $data['orario_fine'] ?? '01:30',
                    'nome_cliente' => $data['nome_cliente'],
                    'nome_referente' => $data['nome_referente'],
                    'cognome_referente' => $data['cognome_referente'],
                    'telefono' => $data['telefono'],
                    'email' => $data['email'],
                    'importo_totale' => $data['importo_totale'],
                    'importo_preventivo' => $data['importo_preventivo'],
                    'acconto' => $data['acconto'],
                    'saldo' => $data['saldo'],
                    'omaggio1' => $data['omaggio1'],
                    'omaggio2' => $data['omaggio2'],
                    'omaggio3' => $data['omaggio3'],
                    'extra1' => $data['extra1'],
                    'extra1_importo' => $data['extra1_importo'],
                    'extra2' => $data['extra2'],
                    'extra2_importo' => $data['extra2_importo'],
                    'extra3' => $data['extra3'],
                    'extra3_importo' => $data['extra3_importo'],
                    'stato' => $data['stato'],
                    'excel_url' => '',
                    'pdf_url' => '',
                    'googledrive_url' => $data['googledrive_url'],
                    'googledrive_file_id' => $data['googledrive_file_id'],
                    'created_at' => current_time('mysql'),
                    'created_by' => get_current_user_id(),
                    'updated_at' => current_time('mysql')
                );
                
                $result = $wpdb->insert($table_name, $insert_data);
                
                if ($result === false) {
                    $this->log('[747Disco-Scan] Errore INSERT: ' . $wpdb->last_error, 'ERROR');
                    return array('success' => false, 'error' => $wpdb->last_error);
                }
                
                $insert_id = $wpdb->insert_id;
                $this->log("[747Disco-Scan] ✅ Preventivo inserito con ID: {$insert_id}");
                
                return array(
                    'success' => true,
                    'id' => $insert_id,
                    'action' => 'inserted'
                );
            }
            
        } catch (\Exception $e) {
            $this->log('[747Disco-Scan] Exception DB: ' . $e->getMessage(), 'ERROR');
            return array('success' => false, 'error' => $e->getMessage());
        }
    }

    /**
     * Logging
     */
    private function log($message, $level = 'INFO') {
        if (!$this->debug_mode) return;
        
        error_log("[747Disco-GDriveSync] {$message}");
    }
}
