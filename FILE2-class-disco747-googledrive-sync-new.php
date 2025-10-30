<?php
/**
 * Classe per sincronizzazione preventivi da Google Drive
 * VERSIONE 12.0.0-COMPLETE: Batch scan completo
 * 
 * @package    Disco747_CRM
 * @subpackage Storage
 * @since      12.0.0
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

    public function __construct($googledrive_instance = null) {
        $session_id = 'INIT_' . date('His') . '_' . wp_rand(100, 999);
        
        try {
            $this->log("=== DEBUG SESSION {$session_id} CONSTRUCTOR START ===");
            
            if ($googledrive_instance) {
                $this->googledrive = $googledrive_instance;
                $this->sync_available = true;
                $this->log("DEBUG: GoogleDrive instance fornita");
            } else {
                $this->log("DEBUG: Carico GoogleDrive autonomamente");
                if (class_exists('Disco747_CRM\\Storage\\Disco747_GoogleDrive')) {
                    $this->googledrive = new \Disco747_CRM\Storage\Disco747_GoogleDrive();
                    $this->sync_available = true;
                    $this->log("DEBUG: GoogleDrive caricato");
                }
            }
            
            if (function_exists('disco747_crm')) {
                $this->database = disco747_crm()->get_database();
                $this->log("DEBUG: Database caricato");
            }
            
            $this->log("=== DEBUG SESSION {$session_id} END ===");
            
        } catch (\Exception $e) {
            $this->log("ERROR: " . $e->getMessage(), 'ERROR');
            $this->sync_available = false;
        }
    }

    public function is_available() {
        return $this->sync_available;
    }

    public function batch_scan_excel_files($year = '', $month = '', $reset = false) {
        $this->log('[747Disco-Scan] Inizio batch scan');
        
        $start_time = microtime(true);
        $messages = array();
        $processed = 0;
        $inserted = 0;
        $updated = 0;
        $errors = 0;
        
        try {
            if (!$this->sync_available || !$this->googledrive) {
                throw new \Exception('Google Drive non disponibile');
            }
            
            $messages[] = '✅ Google Drive OK';
            
            $token = $this->googledrive->get_valid_access_token();
            if (!$token) {
                throw new \Exception('Token non valido');
            }
            
            $messages[] = '✅ Token ottenuto';
            
            // Trova cartella principale
            $main_folder_id = $this->find_main_folder($token);
            if (!$main_folder_id) {
                throw new \Exception('Cartella /747-Preventivi/ non trovata');
            }
            
            $messages[] = '✅ Cartella trovata';
            
            // Trova file Excel
            $all_files = $this->find_excel_files($main_folder_id, $token, $year, $month);
            $total_files = count($all_files);
            
            $messages[] = "📊 Trovati {$total_files} file";
            
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
                    'messages' => array_merge($messages, array('⚠️ Nessun file'))
                );
            }
            
            // Processa file 2 alla volta
            $batch_size = 2;
            $batches = array_chunk($all_files, $batch_size);
            
            foreach ($batches as $batch) {
                foreach ($batch as $file) {
                    try {
                        $result = $this->process_file($file, $token);
                        if ($result['success']) {
                            $processed++;
                            if ($result['action'] === 'inserted') $inserted++;
                            if ($result['action'] === 'updated') $updated++;
                            $messages[] = "✅ " . $file['filename'];
                        } else {
                            $errors++;
                            $messages[] = "❌ " . $file['filename'];
                        }
                    } catch (\Exception $e) {
                        $errors++;
                        $messages[] = "❌ " . $file['filename'] . ": " . $e->getMessage();
                    }
                }
                usleep(500000); // 500ms pausa
            }
            
            $duration_ms = round((microtime(true) - $start_time) * 1000);
            $messages[] = "✅ Completato in {$duration_ms}ms";
            
            return array(
                'success' => true,
                'total_files' => $total_files,
                'processed' => $processed,
                'inserted' => $inserted,
                'updated' => $updated,
                'errors' => $errors,
                'skipped' => 0,
                'duration_ms' => $duration_ms,
                'messages' => $messages
            );
            
        } catch (\Exception $e) {
            $duration_ms = round((microtime(true) - $start_time) * 1000);
            
            return array(
                'success' => false,
                'total_files' => 0,
                'processed' => $processed,
                'inserted' => $inserted,
                'updated' => $updated,
                'errors' => $errors + 1,
                'skipped' => 0,
                'duration_ms' => $duration_ms,
                'messages' => array_merge($messages, array('❌ ' . $e->getMessage()))
            );
        }
    }

    private function find_main_folder($token) {
        try {
            $query = "mimeType='application/vnd.google-apps.folder' and name='747-Preventivi' and trashed=false";
            $url = 'https://www.googleapis.com/drive/v3/files?' . http_build_query(array('q' => $query, 'fields' => 'files(id,name)', 'pageSize' => 10));
            
            $response = wp_remote_get($url, array('headers' => array('Authorization' => 'Bearer ' . $token), 'timeout' => 30));
            
            if (is_wp_error($response)) return null;
            
            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);
            
            if (isset($data['files']) && !empty($data['files'])) {
                return $data['files'][0]['id'];
            }
            
            return null;
        } catch (\Exception $e) {
            return null;
        }
    }

    private function find_excel_files($folder_id, $token, $year, $month) {
        $all_files = array();
        
        try {
            if (empty($year)) $year = date('Y');
            
            // Trova cartelle anno
            $year_folders = $this->get_subfolders($folder_id, $token);
            
            foreach ($year_folders as $yf) {
                if (!empty($year) && $yf['name'] !== $year) continue;
                
                // Trova cartelle mesi
                $month_folders = $this->get_subfolders($yf['id'], $token);
                
                foreach ($month_folders as $mf) {
                    if (!empty($month) && strtoupper($mf['name']) !== strtoupper($month)) continue;
                    
                    // Trova file Excel
                    $files = $this->get_excel_in_folder($mf['id'], $token);
                    
                    foreach ($files as $f) {
                        $f['year'] = $yf['name'];
                        $f['month'] = $mf['name'];
                        $all_files[] = $f;
                    }
                }
            }
            
            return $all_files;
        } catch (\Exception $e) {
            return array();
        }
    }

    private function get_subfolders($parent_id, $token) {
        try {
            $query = "'{$parent_id}' in parents and mimeType='application/vnd.google-apps.folder' and trashed=false";
            $url = 'https://www.googleapis.com/drive/v3/files?' . http_build_query(array('q' => $query, 'fields' => 'files(id,name)', 'pageSize' => 100));
            
            $response = wp_remote_get($url, array('headers' => array('Authorization' => 'Bearer ' . $token), 'timeout' => 30));
            
            if (is_wp_error($response)) return array();
            
            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);
            
            return $data['files'] ?? array();
        } catch (\Exception $e) {
            return array();
        }
    }

    private function get_excel_in_folder($folder_id, $token) {
        try {
            $query = "'{$folder_id}' in parents and (mimeType='application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' or name contains '.xlsx') and trashed=false";
            $url = 'https://www.googleapis.com/drive/v3/files?' . http_build_query(array('q' => $query, 'fields' => 'files(id,name,size)', 'pageSize' => 100));
            
            $response = wp_remote_get($url, array('headers' => array('Authorization' => 'Bearer ' . $token), 'timeout' => 30));
            
            if (is_wp_error($response)) return array();
            
            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);
            
            $files = array();
            if (isset($data['files'])) {
                foreach ($data['files'] as $f) {
                    $files[] = array('googledrive_id' => $f['id'], 'filename' => $f['name'], 'file_size' => $f['size'] ?? 0);
                }
            }
            
            return $files;
        } catch (\Exception $e) {
            return array();
        }
    }

    private function process_file($file, $token) {
        try {
            $temp_path = $this->download_temp($file['googledrive_id'], $token);
            if (!$temp_path) throw new \Exception('Download fallito');
            
            $data = $this->parse_excel($temp_path, $file['filename']);
            if (!$data) throw new \Exception('Parsing fallito');
            
            $data['googledrive_file_id'] = $file['googledrive_id'];
            $data['googledrive_url'] = 'https://drive.google.com/file/d/' . $file['googledrive_id'] . '/view';
            
            $result = $this->save_db($data);
            
            if (file_exists($temp_path)) @unlink($temp_path);
            
            return $result;
        } catch (\Exception $e) {
            return array('success' => false, 'error' => $e->getMessage());
        }
    }

    private function download_temp($file_id, $token) {
        try {
            $upload_dir = wp_upload_dir();
            $temp_dir = $upload_dir['basedir'] . '/preventivi/temp/';
            if (!is_dir($temp_dir)) wp_mkdir_p($temp_dir);
            
            $temp_path = $temp_dir . 'temp_' . time() . '_' . wp_rand(1000, 9999) . '.xlsx';
            
            $url = 'https://www.googleapis.com/drive/v3/files/' . $file_id . '?alt=media';
            $response = wp_remote_get($url, array('headers' => array('Authorization' => 'Bearer ' . $token), 'timeout' => 60));
            
            if (is_wp_error($response)) return null;
            
            $body = wp_remote_retrieve_body($response);
            if (empty($body)) return null;
            
            file_put_contents($temp_path, $body);
            return $temp_path;
        } catch (\Exception $e) {
            return null;
        }
    }

    private function parse_excel($path, $filename) {
        try {
            $autoload = DISCO747_CRM_PLUGIN_DIR . 'vendor/autoload.php';
            if (file_exists($autoload)) require_once $autoload;
            
            if (!class_exists('PhpOffice\\PhpSpreadsheet\\IOFactory')) return null;
            
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($path);
            $sheet = $spreadsheet->getActiveSheet();
            
            $tipo_menu_raw = trim($sheet->getCell('B1')->getValue() ?? '');
            $tipo_menu = $this->extract_menu($tipo_menu_raw, $filename);
            
            $data = array(
                'data_evento' => $this->parse_date($sheet->getCell('C6')->getValue()),
                'tipo_evento' => trim($sheet->getCell('C7')->getValue() ?? ''),
                'tipo_menu' => $tipo_menu,
                'orario_evento' => trim($sheet->getCell('C8')->getValue() ?? ''),
                'numero_invitati' => intval($sheet->getCell('C9')->getValue() ?? 0),
                'nome_referente' => trim($sheet->getCell('C11')->getValue() ?? ''),
                'cognome_referente' => trim($sheet->getCell('C12')->getValue() ?? ''),
                'telefono' => trim($sheet->getCell('C14')->getValue() ?? ''),
                'email' => trim($sheet->getCell('C15')->getValue() ?? ''),
                'omaggio1' => trim($sheet->getCell('C17')->getValue() ?? ''),
                'omaggio2' => trim($sheet->getCell('C18')->getValue() ?? ''),
                'omaggio3' => trim($sheet->getCell('C19')->getValue() ?? ''),
                'importo_totale' => $this->parse_currency($sheet->getCell('F27')->getValue()),
                'acconto' => $this->parse_currency($sheet->getCell('F28')->getValue()),
                'saldo' => $this->parse_currency($sheet->getCell('F30')->getValue()),
                'extra1' => trim($sheet->getCell('C33')->getValue() ?? ''),
                'extra1_importo' => $this->parse_currency($sheet->getCell('F33')->getValue()),
                'extra2' => trim($sheet->getCell('C34')->getValue() ?? ''),
                'extra2_importo' => $this->parse_currency($sheet->getCell('F34')->getValue()),
                'extra3' => trim($sheet->getCell('C35')->getValue() ?? ''),
                'extra3_importo' => $this->parse_currency($sheet->getCell('F35')->getValue())
            );
            
            $data['nome_cliente'] = trim($data['nome_referente'] . ' ' . $data['cognome_referente']);
            $extra_total = $data['extra1_importo'] + $data['extra2_importo'] + $data['extra3_importo'];
            $data['importo_preventivo'] = $data['importo_totale'] + $extra_total;
            $data['stato'] = $this->get_stato($filename, $data['acconto']);
            
            if (!empty($data['orario_evento']) && strpos($data['orario_evento'], '-') !== false) {
                $parts = explode('-', $data['orario_evento']);
                $data['orario_inizio'] = trim($parts[0]);
                $data['orario_fine'] = trim($parts[1]);
            } else {
                $data['orario_inizio'] = $data['orario_evento'] ?: '20:30';
                $data['orario_fine'] = '01:30';
            }
            
            return $data;
        } catch (\Exception $e) {
            return null;
        }
    }

    private function extract_menu($cell, $filename) {
        if (!empty($cell)) {
            if (preg_match('/(Menu\s*7-4-7|7-4-7|747)/i', $cell)) return 'Menu 7-4-7';
            if (preg_match('/(Menu\s*7-4|7-4|74)/i', $cell)) return 'Menu 7-4';
            if (preg_match('/(Menu\s*7|^7$)/i', $cell)) return 'Menu 7';
        }
        if (preg_match('/\(Menu\s*(7-4-7|747)\)/i', $filename)) return 'Menu 7-4-7';
        if (preg_match('/\(Menu\s*(7-4|74)\)/i', $filename)) return 'Menu 7-4';
        if (preg_match('/\(Menu\s*7\)/i', $filename)) return 'Menu 7';
        return 'Menu 7';
    }

    private function get_stato($filename, $acconto) {
        if (stripos($filename, 'CONF ') === 0 || $acconto > 0) return 'confermato';
        if (stripos($filename, 'NO ') === 0) return 'annullato';
        return 'attivo';
    }

    private function parse_date($value) {
        if (empty($value)) return date('Y-m-d');
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) return $value;
        if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/', $value, $m)) {
            return $m[3] . '-' . str_pad($m[2], 2, '0', STR_PAD_LEFT) . '-' . str_pad($m[1], 2, '0', STR_PAD_LEFT);
        }
        if (is_numeric($value) && $value > 25569) {
            return date('Y-m-d', ($value - 25569) * 86400);
        }
        $ts = strtotime($value);
        if ($ts) return date('Y-m-d', $ts);
        return date('Y-m-d');
    }

    private function parse_currency($value) {
        if (empty($value)) return 0.00;
        $cleaned = preg_replace('/[€$£,\s]/', '', strval($value));
        return floatval($cleaned);
    }

    private function save_db($data) {
        global $wpdb;
        
        try {
            $table = $wpdb->prefix . 'disco747_preventivi';
            
            $existing = null;
            if (!empty($data['googledrive_file_id'])) {
                $existing = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE googledrive_file_id = %s", $data['googledrive_file_id']));
            }
            
            if ($existing) {
                $wpdb->update($table, array(
                    'data_evento' => $data['data_evento'],
                    'tipo_evento' => $data['tipo_evento'],
                    'tipo_menu' => $data['tipo_menu'],
                    'numero_invitati' => $data['numero_invitati'],
                    'nome_cliente' => $data['nome_cliente'],
                    'telefono' => $data['telefono'],
                    'email' => $data['email'],
                    'importo_totale' => $data['importo_totale'],
                    'acconto' => $data['acconto'],
                    'stato' => $data['stato'],
                    'updated_at' => current_time('mysql')
                ), array('id' => $existing->id));
                
                return array('success' => true, 'id' => $existing->id, 'action' => 'updated');
            } else {
                $max = $wpdb->get_var("SELECT MAX(CAST(SUBSTRING(preventivo_id, 2) AS UNSIGNED)) FROM {$table} WHERE preventivo_id LIKE '#%'");
                $next = intval($max) + 1;
                $preventivo_id = '#' . str_pad($next, 3, '0', STR_PAD_LEFT);
                
                $wpdb->insert($table, array(
                    'preventivo_id' => $preventivo_id,
                    'data_evento' => $data['data_evento'],
                    'tipo_evento' => $data['tipo_evento'],
                    'tipo_menu' => $data['tipo_menu'],
                    'numero_invitati' => $data['numero_invitati'],
                    'nome_cliente' => $data['nome_cliente'],
                    'telefono' => $data['telefono'],
                    'email' => $data['email'],
                    'importo_totale' => $data['importo_totale'],
                    'acconto' => $data['acconto'],
                    'stato' => $data['stato'],
                    'googledrive_file_id' => $data['googledrive_file_id'],
                    'googledrive_url' => $data['googledrive_url'],
                    'created_at' => current_time('mysql'),
                    'created_by' => get_current_user_id(),
                    'updated_at' => current_time('mysql')
                ));
                
                return array('success' => true, 'id' => $wpdb->insert_id, 'action' => 'inserted');
            }
        } catch (\Exception $e) {
            return array('success' => false, 'error' => $e->getMessage());
        }
    }

    private function log($message, $level = 'INFO') {
        if (!$this->debug_mode) return;
        error_log("[747Disco-GDriveSync] {$message}");
    }
}
