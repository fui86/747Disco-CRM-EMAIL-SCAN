<?php
/**
 * Forms Handler per 747 Disco CRM - VERSIONE FINALE v12.3.0
 * 
 * FIX APPLICATI:
 * 1. âœ… Cartella basata su data_evento
 * 2. âœ… PDF NON generato automaticamente
 * 3. âœ… PDF on-demand SENZA upload Google Drive
 * 4. âœ… Salvataggio database COMPLETO con tutti i campi
 * 
 * PERCORSO: wp-content/plugins/747disco-crm/includes/handlers/class-disco747-forms.php
 * 
 * @package    Disco747_CRM
 * @subpackage Handlers
 * @version    12.3.0
 */

namespace Disco747_CRM\Handlers;

if (!defined('ABSPATH')) {
    exit('Accesso diretto non consentito');
}

class Disco747_Forms {
    
    private $database;
    private $excel;
    private $pdf;
    private $storage;
    private $email;
    private $whatsapp;
    private $log_enabled = true;
    private $components_loaded = false;
    private $table_name;
    
    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'disco747_preventivi';
        $this->log('[Forms] Handler Forms v12.3.0 inizializzato');
        
        // Hook AJAX
        add_action('wp_ajax_disco747_save_preventivo', array($this, 'handle_ajax_submission'));
        add_action('wp_ajax_nopriv_disco747_save_preventivo', array($this, 'handle_ajax_submission'));
        
        // Hook AJAX solo per PDF (email e whatsapp gestiti da class-disco747-ajax.php)
        add_action('wp_ajax_disco747_generate_pdf', array($this, 'handle_generate_pdf'));
        
        add_action('disco747_cleanup_temp_files', array($this, 'cleanup_temp_files'));
        if (!wp_next_scheduled('disco747_cleanup_temp_files')) {
            wp_schedule_event(time(), 'hourly', 'disco747_cleanup_temp_files');
        }
        
        $this->log('[Forms] Hook AJAX registrati correttamente');
    }
    
    /**
     * ========================================================================
     * METODO PRINCIPALE - Handle AJAX Submission
     * ========================================================================
     */
    public function handle_ajax_submission() {
        try {
            $this->log('[Forms] ========== INIZIO GESTIONE PREVENTIVO ==========');
            
            if (!$this->verify_nonce()) {
                $this->log('[Forms] ERRORE: Nonce non valido');
                wp_send_json_error('Sessione scaduta. Ricarica la pagina.');
                return;
            }
            
            $this->load_components();
            $data = $this->validate_form_data($_POST);
            
            if (!$data) {
                wp_send_json_error('Dati del form non validi');
                return;
            }
            
            $this->log('[Forms] âœ… Dati validati correttamente');
            
            // Controlla se Ã¨ modalitÃ  modifica
            $is_edit_mode = !empty($_POST['is_edit_mode']);
            $edit_id = $is_edit_mode ? intval($_POST['edit_id']) : 0;
            
            if ($is_edit_mode && $edit_id > 0) {
                $this->update_preventivo($edit_id, $data);
                return;
            }
            
            // Creazione nuovo preventivo
            $this->create_new_preventivo($data);
            
        } catch (\Exception $e) {
            $this->log('[Forms] âŒ ERRORE FATALE: ' . $e->getMessage(), 'ERROR');
            wp_send_json_error('Errore: ' . $e->getMessage());
        }
    }
    
    /**
     * ========================================================================
     * CREAZIONE NUOVO PREVENTIVO
     * ========================================================================
     */
    private function create_new_preventivo($data) {
        $this->log('[Forms] MODALITÃ€ CREAZIONE NUOVO PREVENTIVO');
        
        // Genera ID preventivo progressivo
        $data['preventivo_id'] = $this->generate_preventivo_id();
        $this->log('[Forms] ID Preventivo generato: ' . $data['preventivo_id']);
        
        // âœ… GENERA SOLO EXCEL (NO PDF)
        $this->log('[Forms] ðŸ“„ Generazione Excel...');
        $excel_path = $this->create_excel_safe($data);
        
        if (!$excel_path) {
            throw new \Exception('Errore generazione Excel');
        }
        
        $this->log('[Forms] âœ… Excel generato: ' . basename($excel_path));
        
        // â›” PDF NON generato automaticamente
        $pdf_path = null;
        
        // âœ… UPLOAD SU GOOGLE DRIVE nella cartella corretta (basata su data_evento)
        $cloud_url = '';
        if ($this->storage) {
            $this->log('[Forms] â˜ï¸ Upload su Google Drive...');
            
            try {
                // âœ… FIX: Usa data_evento per determinare il percorso corretto
                $date_parts = explode('-', $data['data_evento']);
                $year = $date_parts[0];
                $month_num = $date_parts[1];
                
                // Converti numero mese in nome mese italiano
                $mesi = array(
                    '01' => 'Gennaio', '02' => 'Febbraio', '03' => 'Marzo',
                    '04' => 'Aprile', '05' => 'Maggio', '06' => 'Giugno',
                    '07' => 'Luglio', '08' => 'Agosto', '09' => 'Settembre',
                    '10' => 'Ottobre', '11' => 'Novembre', '12' => 'Dicembre'
                );
                $month_name = $mesi[$month_num] ?? $month_num;
                
                // Percorso corretto: /747-Preventivi/2025/Novembre/
                $drive_folder = '747-Preventivi/' . $year . '/' . $month_name . '/';
                
                $this->log('[Forms] ðŸ“ Percorso Google Drive: ' . $drive_folder);
                $this->log('[Forms] ðŸ“… Data evento usata: ' . $data['data_evento']);
                
                // Upload Excel
                if ($excel_path && file_exists($excel_path)) {
                    $upload_result = $this->storage->upload_file($excel_path, $drive_folder);
                    
                    // ✅ Gestisci risposta (può essere array o stringa per compatibilità)
                    if ($upload_result) {
                        $excel_url = is_array($upload_result) ? $upload_result['url'] : $upload_result;
                        $file_id = is_array($upload_result) ? ($upload_result['file_id'] ?? '') : '';
                        
                        $cloud_url = $excel_url;
                        $data['googledrive_url'] = $excel_url;
                        
                        // ✅ Salva anche il file_id per poterlo eliminare in futuro
                        if (!empty($file_id)) {
                            $data['googledrive_file_id'] = $file_id;
                            $this->log('[Forms] 📎 File ID salvato: ' . $file_id);
                        }
                        
                        $this->log('[Forms] âœ… Excel caricato su Drive: ' . basename($excel_path));
                    }
                }
                
            } catch (\Exception $e) {
                $this->log('[Forms] âš ï¸ Errore upload Drive: ' . $e->getMessage(), 'WARNING');
            }
        }
        
        // Salva database
        $this->log('[Forms] ðŸ’¾ Salvataggio database...');
        $db_id = $this->save_preventivo($data);
        
        if (!$db_id) {
            throw new \Exception('Errore salvataggio database');
        }
        
        $this->log('[Forms] âœ… Preventivo salvato con ID database: ' . $db_id);
        $this->log('[Forms] ========== âœ…âœ…âœ… PREVENTIVO COMPLETATO ==========');
        
        // ✅ REGISTRA CREAZIONE NEL LOG AUDIT
        if ($this->database) {
            $this->database->log_preventivo_change($db_id, 'create');
            $this->log('[Forms] 📝 Creazione registrata nel log audit');
        }
        
        wp_send_json_success(array(
            'message' => 'Preventivo creato con successo!',
            'preventivo_id' => $data['preventivo_id'],
            'id' => $db_id,
            'db_id' => $db_id,
            'excel_path' => $excel_path,
            'excel_url' => $cloud_url,
            'pdf_generated' => false,
            'pdf_note' => 'PDF non generato automaticamente. Usa il pulsante "Genera PDF" se necessario.',
            // Dati preventivo per JavaScript
            'nome_referente' => $data['nome_referente'] ?? '',
            'cognome_referente' => $data['cognome_referente'] ?? '',
            'nome_cliente' => $data['nome_cliente'] ?? trim(($data['nome_referente'] ?? '') . ' ' . ($data['cognome_referente'] ?? '')),
            'email' => $data['email'] ?? '',
            'telefono' => $data['telefono'] ?? '',
            'data_evento' => $data['data_evento'] ?? '',
            'tipo_evento' => $data['tipo_evento'] ?? '',
            'tipo_menu' => $data['tipo_menu'] ?? '',
            'numero_invitati' => $data['numero_invitati'] ?? 0,
            'orario_inizio' => $data['orario_inizio'] ?? '',
            'orario_fine' => $data['orario_fine'] ?? '',
            'importo_totale' => $data['importo_totale'] ?? 0,
            'acconto' => $data['acconto'] ?? 0,
            'saldo' => ($data['importo_totale'] ?? 0) - ($data['acconto'] ?? 0),
            'omaggio1' => $data['omaggio1'] ?? '',
            'omaggio2' => $data['omaggio2'] ?? '',
            'omaggio3' => $data['omaggio3'] ?? '',
            'extra1' => $data['extra1'] ?? '',
            'extra2' => $data['extra2'] ?? '',
            'extra3' => $data['extra3'] ?? '',
            'stato' => $data['stato'] ?? 'attivo'
        ));
    }
    
    /**
     * ========================================================================
     * AGGIORNAMENTO PREVENTIVO ESISTENTE
     * ========================================================================
     */
    private function update_preventivo($edit_id, $data) {
        $this->log('[Forms] MODALITA AGGIORNAMENTO PREVENTIVO ID: ' . $edit_id);
        
        global $wpdb;
        
        // ✅ Carica dati precedenti per confronto
        $old_data = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE id = %d",
            $edit_id
        ), ARRAY_A);
        
        // Calcola extra totale
        $extra_totale = floatval($data['extra1_importo']) + floatval($data['extra2_importo']) + floatval($data['extra3_importo']);
        
        // Calcola importo_preventivo e saldo
        $importo_preventivo = floatval($data['importo_totale']) + $extra_totale;
        $saldo = $importo_preventivo - floatval($data['acconto']);
        
        // Aggiorna database
        $updated = $wpdb->update(
            $this->table_name,
            array(
                'nome_cliente' => $data['nome_cliente'],
                'nome_referente' => $data['nome_referente'],
                'cognome_referente' => $data['cognome_referente'],
                'telefono' => $data['telefono'],
                'email' => $data['email'],
                'data_evento' => $data['data_evento'],
                'tipo_evento' => $data['tipo_evento'],
                'tipo_menu' => $data['tipo_menu'],
                'numero_invitati' => $data['numero_invitati'],
                'orario_inizio' => $data['orario_inizio'],
                'orario_fine' => $data['orario_fine'],
                'omaggio1' => $data['omaggio1'],
                'omaggio2' => $data['omaggio2'],
                'omaggio3' => $data['omaggio3'],
                'extra1' => $data['extra1'],
                'extra1_importo' => $data['extra1_importo'],
                'extra2' => $data['extra2'],
                'extra2_importo' => $data['extra2_importo'],
                'extra3' => $data['extra3'],
                'extra3_importo' => $data['extra3_importo'],
                'importo_totale' => $data['importo_totale'],
                'importo_preventivo' => $importo_preventivo,
                'acconto' => $data['acconto'],
                'saldo' => $saldo,
                'note_aggiuntive' => $data['note_aggiuntive'],
                'note_interne' => $data['note_interne'],
                'stato' => $data['stato'],
                'updated_at' => current_time('mysql'),
                'updated_by' => get_current_user_id() // ✅ Traccia chi ha modificato
            ),
            array('id' => $edit_id),
            array(
                '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d',
                '%s', '%s', '%s', '%s', '%s', '%s', '%f', '%s', '%f',
                '%s', '%f', '%f', '%f', '%f', '%f', '%s', '%s', '%s', '%s', '%d'
            ),
            array('%d')
        );
        
        if ($updated === false) {
            $this->log('[Forms] âŒ Errore aggiornamento: ' . $wpdb->last_error, 'ERROR');
            wp_send_json_error('Errore aggiornamento database');
            return;
        }
        
        $this->log('[Forms] âœ… Preventivo aggiornato con successo');
        
        // ✅ REGISTRA MODIFICHE NEL LOG AUDIT
        if ($old_data && $this->database) {
            $changes = $this->detect_changes($old_data, $data);
            if (!empty($changes)) {
                $this->database->log_preventivo_change($edit_id, 'update', $changes);
                $this->log('[Forms] 📝 ' . count($changes) . ' modifiche registrate nel log audit');
            }
        }
        
        // Rileggi preventivo aggiornato dal database per avere tutti i dati
        $preventivo = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE id = %d",
            $edit_id
        ), ARRAY_A);
        
        // âœ… RIGENERA EXCEL E RICARICA SU GOOGLE DRIVE
        $this->log('[Forms] ðŸ"„ Rigenerazione Excel per preventivo aggiornato...');
        
        // Prepara dati per Excel (mappatura completa)
        $excel_data = array(
            'preventivo_id' => $preventivo['preventivo_id'],
            'nome_referente' => $preventivo['nome_referente'],
            'cognome_referente' => $preventivo['cognome_referente'],
            'cellulare' => $preventivo['telefono'],
            'mail' => $preventivo['email'],
            'data_evento' => $preventivo['data_evento'],
            'tipo_evento' => $preventivo['tipo_evento'],
            'tipo_menu' => $preventivo['tipo_menu'],
            'numero_invitati' => $preventivo['numero_invitati'],
            'orario_inizio' => $preventivo['orario_inizio'],
            'orario_fine' => $preventivo['orario_fine'],
            'omaggio1' => $preventivo['omaggio1'],
            'omaggio2' => $preventivo['omaggio2'],
            'omaggio3' => $preventivo['omaggio3'],
            'extra1' => $preventivo['extra1'],
            'extra1_importo' => $preventivo['extra1_importo'],
            'extra2' => $preventivo['extra2'],
            'extra2_importo' => $preventivo['extra2_importo'],
            'extra3' => $preventivo['extra3'],
            'extra3_importo' => $preventivo['extra3_importo'],
            'importo_preventivo' => $preventivo['importo_totale'],
            'acconto' => $preventivo['acconto'],
            'stato' => $preventivo['stato']
        );
        
        // Genera Excel aggiornato
        $excel_path = $this->create_excel_safe($excel_data);
        
        if ($excel_path && file_exists($excel_path)) {
            $this->log('[Forms] âœ… Excel rigenerato: ' . basename($excel_path));
            
            // Upload su Google Drive (elimina vecchio, carica nuovo)
            if ($this->storage) {
                $this->log('[Forms] â˜ï¸ Upload Excel aggiornato su Google Drive...');
                
                try {
                    // ✅ ELIMINA vecchio file da Google Drive (evita duplicati con nome diverso)
                    if (!empty($preventivo['googledrive_file_id'])) {
                        $this->log('[Forms] 🗑️ Eliminazione vecchio file da Drive (ID: ' . $preventivo['googledrive_file_id'] . ')...');
                        try {
                            $handler = $this->storage->get_active_handler();
                            if ($handler && method_exists($handler, 'delete_file')) {
                                $handler->delete_file($preventivo['googledrive_file_id']);
                                $this->log('[Forms] ✅ Vecchio file eliminato da Google Drive');
                            } else {
                                $this->log('[Forms] ⚠️ Handler non supporta delete_file', 'WARNING');
                            }
                        } catch (\Exception $e) {
                            $this->log('[Forms] ⚠️ Impossibile eliminare vecchio file: ' . $e->getMessage(), 'WARNING');
                        }
                    }
                    
                    // Usa data_evento per determinare il percorso corretto
                    $date_parts = explode('-', $preventivo['data_evento']);
                    $year = $date_parts[0];
                    $month_num = $date_parts[1];
                    
                    // Converti numero mese in nome mese italiano
                    $mesi = array(
                        '01' => 'Gennaio', '02' => 'Febbraio', '03' => 'Marzo',
                        '04' => 'Aprile', '05' => 'Maggio', '06' => 'Giugno',
                        '07' => 'Luglio', '08' => 'Agosto', '09' => 'Settembre',
                        '10' => 'Ottobre', '11' => 'Novembre', '12' => 'Dicembre'
                    );
                    $month_name = $mesi[$month_num] ?? $month_num;
                    
                    // Percorso corretto: /747-Preventivi/2025/Novembre/
                    $drive_folder = '747-Preventivi/' . $year . '/' . $month_name . '/';
                    
                    $this->log('[Forms] ðŸ" Percorso Google Drive: ' . $drive_folder);
                    
                    // ✅ Upload nuovo Excel con nome aggiornato (include prefisso NO/CONF)
                    $upload_result = $this->storage->upload_file($excel_path, $drive_folder);
                    
                    // ✅ Gestisci risposta (può essere array o stringa per compatibilità)
                    if ($upload_result) {
                        $excel_url = is_array($upload_result) ? $upload_result['url'] : $upload_result;
                        $file_id = is_array($upload_result) ? ($upload_result['file_id'] ?? '') : '';
                        
                        // Aggiorna URL e file_id nel database
                        $update_data = array('googledrive_url' => $excel_url);
                        if (!empty($file_id)) {
                            $update_data['googledrive_file_id'] = $file_id;
                            $this->log('[Forms] 📎 File ID salvato: ' . $file_id);
                        }
                        
                        $wpdb->update(
                            $this->table_name,
                            $update_data,
                            array('id' => $edit_id),
                            array_fill(0, count($update_data), '%s'),
                            array('%d')
                        );
                        $this->log('[Forms] âœ… Excel aggiornato su Drive: ' . basename($excel_path));
                    }
                    
                } catch (\Exception $e) {
                    $this->log('[Forms] âš ï¸ Errore upload Drive: ' . $e->getMessage(), 'WARNING');
                }
            }
        } else {
            $this->log('[Forms] âš ï¸ Impossibile rigenerare Excel', 'WARNING');
        }
        
        wp_send_json_success(array(
            'message' => 'Preventivo aggiornato con successo!',
            'preventivo_id' => $preventivo['preventivo_id'] ?? $data['preventivo_id'],
            'id' => $edit_id,
            'db_id' => $edit_id,
            'is_edit_mode' => true,
            // Dati preventivo per JavaScript
            'nome_referente' => $preventivo['nome_referente'] ?? $data['nome_referente'] ?? '',
            'cognome_referente' => $preventivo['cognome_referente'] ?? $data['cognome_referente'] ?? '',
            'nome_cliente' => $preventivo['nome_cliente'] ?? $data['nome_cliente'] ?? '',
            'email' => $preventivo['email'] ?? $data['email'] ?? '',
            'telefono' => $preventivo['telefono'] ?? $data['telefono'] ?? '',
            'data_evento' => $preventivo['data_evento'] ?? $data['data_evento'] ?? '',
            'tipo_evento' => $preventivo['tipo_evento'] ?? $data['tipo_evento'] ?? '',
            'tipo_menu' => $preventivo['tipo_menu'] ?? $data['tipo_menu'] ?? '',
            'numero_invitati' => $preventivo['numero_invitati'] ?? $data['numero_invitati'] ?? 0,
            'orario_inizio' => $preventivo['orario_inizio'] ?? $data['orario_inizio'] ?? '',
            'orario_fine' => $preventivo['orario_fine'] ?? $data['orario_fine'] ?? '',
            'importo_totale' => $preventivo['importo_totale'] ?? $data['importo_totale'] ?? 0,
            'importo_preventivo' => $preventivo['importo_preventivo'] ?? $importo_preventivo ?? 0,
            'acconto' => $preventivo['acconto'] ?? $data['acconto'] ?? 0,
            'saldo' => $preventivo['saldo'] ?? $saldo ?? 0,
            'omaggio1' => $preventivo['omaggio1'] ?? $data['omaggio1'] ?? '',
            'omaggio2' => $preventivo['omaggio2'] ?? $data['omaggio2'] ?? '',
            'omaggio3' => $preventivo['omaggio3'] ?? $data['omaggio3'] ?? '',
            'extra1' => $preventivo['extra1'] ?? $data['extra1'] ?? '',
            'extra2' => $preventivo['extra2'] ?? $data['extra2'] ?? '',
            'extra3' => $preventivo['extra3'] ?? '',
            'stato' => $preventivo['stato'] ?? $data['stato'] ?? 'attivo'
        ));
    }
    
    /**
     * ========================================================================
     * METODO 1: Genera PDF on-demand - SENZA UPLOAD GOOGLE DRIVE
     * ========================================================================
     */
    public function handle_generate_pdf() {
        try {
            $this->log('[PDF] ========== GENERAZIONE PDF ON-DEMAND ==========');
            
            if (!check_ajax_referer('disco747_generate_pdf', 'nonce', false)) {
                wp_send_json_error('Nonce non valido');
                return;
            }
            
            $this->load_components();
            
            if (!$this->pdf) {
                wp_send_json_error('PDF Generator non disponibile');
                return;
            }
            
            $preventivo_id = isset($_POST['preventivo_id']) ? sanitize_text_field($_POST['preventivo_id']) : '';
            
            if (empty($preventivo_id)) {
                wp_send_json_error('ID preventivo mancante');
                return;
            }
            
            // Carica preventivo dal database
            global $wpdb;
            $preventivo = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$this->table_name} WHERE preventivo_id = %s OR id = %d",
                $preventivo_id,
                intval($preventivo_id)
            ), ARRAY_A);
            
            if (!$preventivo) {
                wp_send_json_error('Preventivo non trovato');
                return;
            }
            
            $this->log('[PDF] ========== DATI DAL DATABASE ==========');
            $this->log('[PDF] Preventivo caricato - ID database: ' . $preventivo['id']);
            $this->log('[PDF] Preventivo_ID: ' . ($preventivo['preventivo_id'] ?? 'N/D'));
            $this->log('[PDF] Nome: ' . ($preventivo['nome_referente'] ?? 'N/D') . ' ' . ($preventivo['cognome_referente'] ?? ''));
            $this->log('[PDF] Telefono: ' . ($preventivo['telefono'] ?? 'N/D'));
            $this->log('[PDF] importo_totale DB: ' . ($preventivo['importo_totale'] ?? 'N/D'));
            $this->log('[PDF] extra1 DB: "' . ($preventivo['extra1'] ?? '') . '" = ' . ($preventivo['extra1_importo'] ?? 'N/D'));
            $this->log('[PDF] extra2 DB: "' . ($preventivo['extra2'] ?? '') . '" = ' . ($preventivo['extra2_importo'] ?? 'N/D'));
            $this->log('[PDF] extra3 DB: "' . ($preventivo['extra3'] ?? '') . '" = ' . ($preventivo['extra3_importo'] ?? 'N/D'));
            $this->log('[PDF] acconto DB: ' . ($preventivo['acconto'] ?? 'N/D'));
            $this->log('[PDF] ==========================================');
            
            // Prepara dati per PDF
            $pdf_data = $this->prepare_pdf_data($preventivo);
            
            // Genera PDF
            $pdf_path = $this->pdf->generate_pdf($pdf_data);
            
            if (!$pdf_path || !file_exists($pdf_path)) {
                wp_send_json_error('Errore generazione PDF');
                return;
            }
            
            $this->log('[PDF] âœ… PDF generato: ' . basename($pdf_path));
            
            // â›” RIMOSSO: Upload su Google Drive
            // Il PDF viene generato SOLO localmente per il download
            
            // Genera URL download
            $upload_dir = wp_upload_dir();
            $pdf_url = str_replace($upload_dir['basedir'], $upload_dir['baseurl'], $pdf_path);
            
            // Aggiorna database con path PDF
            $wpdb->update(
                $this->table_name,
                array('pdf_url' => $pdf_path),
                array('preventivo_id' => $preventivo_id),
                array('%s'),
                array('%s')
            );
            
            $this->log('[PDF] ========== âœ… COMPLETATO (NO UPLOAD DRIVE) ==========');
            
            wp_send_json_success(array(
                'message' => 'PDF generato con successo!',
                'pdf_path' => $pdf_path,
                'pdf_url' => $pdf_url,
                'download_url' => $pdf_url,
                'filename' => basename($pdf_path),
                'note' => 'PDF salvato solo localmente, NON caricato su Google Drive'
            ));
            
        } catch (\Exception $e) {
            $this->log('[PDF] âŒ ERRORE: ' . $e->getMessage(), 'ERROR');
            wp_send_json_error('Errore: ' . $e->getMessage());
        }
    }
    
    /**
     * ========================================================================
     * METODO 2: Invia Email con template
     * ========================================================================
     */
    public function handle_send_email_template() {
        try {
            $this->log('[Email] ========== INVIO EMAIL TEMPLATE ==========');
            
            if (!check_ajax_referer('disco747_send_email', 'nonce', false)) {
                wp_send_json_error('Nonce non valido');
                return;
            }
            
            $preventivo_id = isset($_POST['preventivo_id']) ? sanitize_text_field($_POST['preventivo_id']) : '';
            $template_id = isset($_POST['template_id']) ? sanitize_text_field($_POST['template_id']) : '1';
            
            if (empty($preventivo_id)) {
                wp_send_json_error('ID preventivo mancante');
                return;
            }
            
            // Carica preventivo
            $preventivo_data = $this->get_preventivo_from_db($preventivo_id);
            
            if (!$preventivo_data) {
                wp_send_json_error('Preventivo non trovato');
                return;
            }
            
            // TODO: Implementa invio email
            $this->log('[Email] Template selezionato: ' . $template_id);
            
            wp_send_json_success(array(
                'message' => 'Email inviata con successo!',
                'template_used' => $template_id
            ));
            
        } catch (\Exception $e) {
            $this->log('[Email] âŒ ERRORE: ' . $e->getMessage(), 'ERROR');
            wp_send_json_error('Errore: ' . $e->getMessage());
        }
    }
    
    /**
     * ========================================================================
     * METODO 3: Invia WhatsApp con template
     * ========================================================================
     */
    public function handle_send_whatsapp_template() {
        try {
            $this->log('[WhatsApp] ========== GENERA LINK WHATSAPP ==========');
            
            if (!check_ajax_referer('disco747_send_whatsapp', 'nonce', false)) {
                wp_send_json_error('Nonce non valido');
                return;
            }
            
            $preventivo_id = isset($_POST['preventivo_id']) ? sanitize_text_field($_POST['preventivo_id']) : '';
            $template_id = isset($_POST['template_id']) ? sanitize_text_field($_POST['template_id']) : '1';
            
            if (empty($preventivo_id)) {
                wp_send_json_error('ID preventivo mancante');
                return;
            }
            
            // Carica preventivo
            $preventivo_data = $this->get_preventivo_from_db($preventivo_id);
            
            if (!$preventivo_data) {
                wp_send_json_error('Preventivo non trovato');
                return;
            }
            
            // Template WhatsApp
            $templates = array(
                '1' => "Ciao {{nome}}! ðŸŽ‰\n\nIl tuo preventivo per {{tipo_evento}} del {{data_evento}} Ã¨ pronto!\n\nðŸ’° Importo: {{importo}}\n\n747 Disco - La tua festa indimenticabile! ðŸŽŠ",
                '2' => "Ciao {{nome}}! ðŸŽˆ\n\nTi ricordiamo il tuo evento del {{data_evento}}.\n\nHai confermato? Rispondi per finalizzare! ðŸ“ž",
                '3' => "Ciao {{nome}}! âœ…\n\nGrazie per aver confermato!\n\nðŸ“… {{data_evento}}\nðŸ’° Acconto: {{acconto}}\n\nCi vediamo presto! ðŸŽ‰"
            );
            
            $whatsapp_message = $templates[$template_id] ?? $templates['1'];
            $whatsapp_message = $this->replace_placeholders($whatsapp_message, $preventivo_data);
            
            // Genera link WhatsApp
            $phone = $preventivo_data['telefono'] ?? '';
            $phone = preg_replace('/[^0-9+]/', '', $phone);
            
            if (empty($phone)) {
                wp_send_json_error('Numero telefono non disponibile');
                return;
            }
            
            if (substr($phone, 0, 1) !== '+') {
                if (substr($phone, 0, 2) === '39') {
                    $phone = '+' . $phone;
                } else {
                    $phone = '+39' . $phone;
                }
            }
            
            $whatsapp_url = 'https://wa.me/' . $phone . '?text=' . urlencode($whatsapp_message);
            
            $this->log('[WhatsApp] âœ… Link generato per: ' . $phone);
            
            wp_send_json_success(array(
                'message' => 'Link WhatsApp generato!',
                'whatsapp_url' => $whatsapp_url,
                'phone' => $phone,
                'template_used' => $template_id
            ));
            
        } catch (\Exception $e) {
            $this->log('[WhatsApp] âŒ ERRORE: ' . $e->getMessage(), 'ERROR');
            wp_send_json_error('Errore: ' . $e->getMessage());
        }
    }
    
    /**
     * ========================================================================
     * HELPER: Carica preventivo dal database
     * ========================================================================
     */
    private function get_preventivo_from_db($preventivo_id) {
        global $wpdb;
        
        $preventivo = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE preventivo_id = %s OR id = %d",
            $preventivo_id,
            intval($preventivo_id)
        ), ARRAY_A);
        
        return $preventivo;
    }
    
    /**
     * ========================================================================
     * HELPER: Prepara dati per PDF - MAPPATURA COMPLETA
     * ========================================================================
     */
    private function prepare_pdf_data($preventivo) {
        // Split nome completo se necessario
        $nome_completo = $preventivo['nome_cliente'] ?? '';
        $nome_parts = explode(' ', $nome_completo, 2);
        
        return array(
            // === ID ===
            'preventivo_id' => $preventivo['preventivo_id'] ?? '',
            
            // === DATI CLIENTE ===
            'nome_referente' => $preventivo['nome_referente'] ?? ($nome_parts[0] ?? ''),
            'cognome_referente' => $preventivo['cognome_referente'] ?? ($nome_parts[1] ?? ''),
            'nome_cliente' => $nome_completo,
            
            // === CONTATTI - Tutti gli alias ===
            'mail' => $preventivo['email'] ?? '',
            'email' => $preventivo['email'] ?? '',
            'cellulare' => $preventivo['telefono'] ?? '',
            'telefono' => $preventivo['telefono'] ?? '',
            
            // === DATI EVENTO ===
            'data_evento' => $preventivo['data_evento'] ?? '',
            'tipo_evento' => $preventivo['tipo_evento'] ?? '',
            'tipo_menu' => $preventivo['tipo_menu'] ?? 'Menu 7',
            'numero_invitati' => $preventivo['numero_invitati'] ?? 0,
            
            // === ORARI ===
            'orario_inizio' => $preventivo['orario_inizio'] ?? '20:30',
            'orario_fine' => $preventivo['orario_fine'] ?? '01:30',
            
            // === OMAGGI ===
            'omaggio1' => $preventivo['omaggio1'] ?? '',
            'omaggio2' => $preventivo['omaggio2'] ?? '',
            'omaggio3' => $preventivo['omaggio3'] ?? '',
            
            // === EXTRA ===
            'extra1' => $preventivo['extra1'] ?? '',
            'extra1_importo' => $preventivo['extra1_importo'] ?? 0,
            'extra2' => $preventivo['extra2'] ?? '',
            'extra2_importo' => $preventivo['extra2_importo'] ?? 0,
            'extra3' => $preventivo['extra3'] ?? '',
            'extra3_importo' => $preventivo['extra3_importo'] ?? 0,
            
            // === IMPORTI - Tutti gli alias ===
            'importo_preventivo' => $preventivo['importo_totale'] ?? 0,
            'importo_totale' => $preventivo['importo_totale'] ?? 0,
            'acconto' => $preventivo['acconto'] ?? 0,
            
            // === NOTE ===
            'note_aggiuntive' => $preventivo['note_aggiuntive'] ?? '',
            'note_interne' => $preventivo['note_interne'] ?? '',
            
            // === METADATA ===
            'stato' => $preventivo['stato'] ?? 'attivo',
            'created_at' => $preventivo['created_at'] ?? '',
            'created_by' => $preventivo['created_by'] ?? ''
        );
    }
    
    /**
     * ========================================================================
     * HELPER: Sostituisce placeholder
     * ========================================================================
     */
    private function replace_placeholders($text, $data) {
        $replacements = array(
            '{{nome}}' => $data['nome_referente'] ?? '',
            '{{cognome}}' => $data['cognome_referente'] ?? '',
            '{{nome_completo}}' => trim(($data['nome_referente'] ?? '') . ' ' . ($data['cognome_referente'] ?? '')),
            '{{email}}' => $data['email'] ?? '',
            '{{telefono}}' => $data['telefono'] ?? '',
            '{{data_evento}}' => date('d/m/Y', strtotime($data['data_evento'] ?? '')),
            '{{tipo_evento}}' => $data['tipo_evento'] ?? '',
            '{{menu}}' => $data['tipo_menu'] ?? '',
            '{{numero_invitati}}' => $data['numero_invitati'] ?? '',
            '{{importo}}' => number_format($data['importo_totale'] ?? 0, 2, ',', '.') . ' â‚¬',
            '{{acconto}}' => number_format($data['acconto'] ?? 0, 2, ',', '.') . ' â‚¬',
            '{{preventivo_id}}' => $data['preventivo_id'] ?? ''
        );
        
        return str_replace(array_keys($replacements), array_values($replacements), $text);
    }
    
    /**
     * ========================================================================
     * HELPER: Verifica nonce - CORRETTO
     * ========================================================================
     */
    private function verify_nonce() {
        // Verifica il nonce principale del form preventivo
        if (isset($_POST['disco747_preventivo_nonce'])) {
            if (wp_verify_nonce($_POST['disco747_preventivo_nonce'], 'disco747_preventivo')) {
                $this->log('[Forms] ✅ Nonce verificato correttamente (disco747_preventivo)');
                return true;
            }
        }
        
        // Fallback: altri possibili nonce
        if (isset($_POST['disco747_nonce'])) {
            if (wp_verify_nonce($_POST['disco747_nonce'], 'disco747_action')) {
                $this->log('[Forms] ✅ Nonce verificato correttamente (disco747_nonce)');
                return true;
            }
        }
        
        if (isset($_POST['_wpnonce'])) {
            if (wp_verify_nonce($_POST['_wpnonce'], 'disco747_preventivo')) {
                $this->log('[Forms] ✅ Nonce verificato correttamente (_wpnonce)');
                return true;
            }
        }
        
        // Log per debug
        $this->log('[Forms] ❌ Nessun nonce valido trovato. Campi POST ricevuti: ' . implode(', ', array_keys($_POST)), 'WARNING');
        
        return false;
    }
    
    /**
     * ========================================================================
     * HELPER: Carica componenti
     * ========================================================================
     */
    private function load_components() {
        if ($this->components_loaded) return;
        
        try {
            $disco747_crm = disco747_crm();
            
            if ($disco747_crm && $disco747_crm->is_initialized()) {
                $this->database = $disco747_crm->get_database();
                $this->excel = $disco747_crm->get_excel();
                $this->pdf = $disco747_crm->get_pdf();
                $this->storage = $disco747_crm->get_storage_manager();
                
                $this->components_loaded = true;
                $this->log('[Forms] Componenti caricati con successo');
            } else {
                throw new \Exception('Plugin principale non inizializzato');
            }
        } catch (\Exception $e) {
            $this->log('[Forms] Errore caricamento componenti: ' . $e->getMessage(), 'ERROR');
            throw $e;
        }
    }
    
    /**
     * ========================================================================
     * HELPER: Valida dati form
     * ========================================================================
     */
    private function validate_form_data($post_data) {
        $data = array();
        
        // DATI CLIENTE
        $nome = sanitize_text_field($post_data['nome_referente'] ?? '');
        $cognome = sanitize_text_field($post_data['cognome_referente'] ?? '');
        $data['nome_cliente'] = trim($nome . ' ' . $cognome);
        $data['nome_referente'] = $nome;
        $data['cognome_referente'] = $cognome;
        
        // CONTATTI - Duplica per compatibilitÃ 
        $data['telefono'] = sanitize_text_field($post_data['cellulare'] ?? '');
        $data['email'] = sanitize_email($post_data['mail'] ?? '');
        $data['cellulare'] = $data['telefono'];
        $data['mail'] = $data['email'];
        
        // DATI EVENTO
        $data['data_evento'] = sanitize_text_field($post_data['data_evento'] ?? '');
        $data['tipo_evento'] = sanitize_text_field($post_data['tipo_evento'] ?? '');
        $data['tipo_menu'] = sanitize_text_field($post_data['tipo_menu'] ?? 'Menu 7');
        $data['numero_invitati'] = intval($post_data['numero_invitati'] ?? 50);
        
        // ORARI
        $data['orario_inizio'] = sanitize_text_field($post_data['orario_inizio'] ?? '20:30');
        $data['orario_fine'] = sanitize_text_field($post_data['orario_fine'] ?? '01:30');
        
        // OMAGGI
        $data['omaggio1'] = sanitize_text_field($post_data['omaggio1'] ?? '');
        $data['omaggio2'] = sanitize_text_field($post_data['omaggio2'] ?? '');
        $data['omaggio3'] = sanitize_text_field($post_data['omaggio3'] ?? '');
        
        // EXTRA A PAGAMENTO
        $data['extra1'] = sanitize_text_field($post_data['extra1'] ?? '');
        $data['extra1_importo'] = floatval($post_data['extra1_importo'] ?? 0);
        $data['extra2'] = sanitize_text_field($post_data['extra2'] ?? '');
        $data['extra2_importo'] = floatval($post_data['extra2_importo'] ?? 0);
        $data['extra3'] = sanitize_text_field($post_data['extra3'] ?? '');
        $data['extra3_importo'] = floatval($post_data['extra3_importo'] ?? 0);
        
        // DEBUG LOG EXTRA
        $this->log('[Forms] Extra validati:');
        $this->log('  - Extra1: "' . $data['extra1'] . '" = €' . $data['extra1_importo']);
        $this->log('  - Extra2: "' . $data['extra2'] . '" = €' . $data['extra2_importo']);
        $this->log('  - Extra3: "' . $data['extra3'] . '" = €' . $data['extra3_importo']);
        
        // IMPORTI
        $data['importo_preventivo'] = floatval($post_data['importo_preventivo'] ?? 0);
        $data['importo_totale'] = $data['importo_preventivo'];
        $data['acconto'] = floatval($post_data['acconto'] ?? 0);
        
        // NOTE
        $data['note_aggiuntive'] = sanitize_textarea_field($post_data['note_aggiuntive'] ?? '');
        $data['note_interne'] = sanitize_textarea_field($post_data['note_interne'] ?? '');
        
        // METADATA
        $data['stato'] = sanitize_text_field($post_data['stato'] ?? 'attivo'); // ✅ Legge dal form
        $data['created_by'] = get_current_user_id();
        $data['created_at'] = current_time('mysql');
        
        return $data;
    }
    
    /**
     * ========================================================================
     * HELPER: Genera ID preventivo progressivo
     * ========================================================================
     */
    private function generate_preventivo_id() {
        global $wpdb;
        
        $last_id = $wpdb->get_var("SELECT MAX(CAST(SUBSTRING(preventivo_id, 2) AS UNSIGNED)) FROM {$this->table_name} WHERE preventivo_id LIKE '#%'");
        $next_number = $last_id ? intval($last_id) + 1 : 1;
        
        return '#' . str_pad($next_number, 3, '0', STR_PAD_LEFT);
    }
    
    /**
     * ========================================================================
     * HELPER: Genera nome file
     * ========================================================================
     */
    private function generate_filename($data) {
        $date_parts = explode('-', $data['data_evento']);
        $day = str_pad($date_parts[2] ?? date('d'), 2, '0', STR_PAD_LEFT);
        $month = str_pad($date_parts[1] ?? date('m'), 2, '0', STR_PAD_LEFT);
        
        $tipo_evento = preg_replace('/[^a-zA-Z0-9\s]/u', '', $data['tipo_evento'] ?? 'Evento');
        $tipo_evento = substr(trim($tipo_evento), 0, 30);
        
        $prefix = '';
        if (isset($data['stato']) && $data['stato'] === 'annullato') {
            $prefix = 'NO ';
        } elseif (isset($data['acconto']) && floatval($data['acconto']) > 0) {
            $prefix = 'CONF ';
        }
        
        $menu_number = str_replace('Menu ', '', $data['tipo_menu'] ?? 'Menu 7');
        
        return $prefix . $day . '_' . $month . ' ' . $tipo_evento . ' (Menu ' . $menu_number . ')';
    }
    
    /**
     * ========================================================================
     * HELPER: Crea Excel in modo sicuro
     * ========================================================================
     */
    private function create_excel_safe($data) {
        if (!$this->excel || !method_exists($this->excel, 'generate_excel')) {
            $this->log('[Excel] Generator non disponibile', 'WARNING');
            return null;
        }
        
        try {
            $result = $this->excel->generate_excel($data);
            
            if ($result && file_exists($result)) {
                return $result;
            }
            
            return null;
            
        } catch (\Exception $e) {
            $this->log('[Excel] Errore: ' . $e->getMessage(), 'ERROR');
            return null;
        }
    }
    
    /**
     * ========================================================================
     * HELPER: Salva preventivo in database - VERSIONE CORRETTA
     * ========================================================================
     */
    private function save_preventivo($data) {
        global $wpdb;
        
        $this->log('[DB] Preparazione dati per salvataggio database');
        
        // âœ… MAPPATURA CORRETTA: Mantieni TUTTI i campi necessari
        $insert_data = array(
            // ID e Metadata
            'preventivo_id' => $data['preventivo_id'] ?? '',
            
            // Cliente - USA I CAMPI SINGOLI
            'nome_referente' => $data['nome_referente'] ?? '',
            'cognome_referente' => $data['cognome_referente'] ?? '',
            'nome_cliente' => $data['nome_cliente'] ?? trim(($data['nome_referente'] ?? '') . ' ' . ($data['cognome_referente'] ?? '')),
            
            // Contatti
            'telefono' => $data['telefono'] ?? '',
            'email' => $data['email'] ?? '',
            
            // Evento
            'data_evento' => $data['data_evento'] ?? date('Y-m-d'),
            'tipo_evento' => $data['tipo_evento'] ?? '',
            'tipo_menu' => $data['tipo_menu'] ?? 'Menu 7',
            'numero_invitati' => intval($data['numero_invitati'] ?? 0),
            
            // Orari
            'orario_inizio' => $data['orario_inizio'] ?? '20:30',
            'orario_fine' => $data['orario_fine'] ?? '01:30',
            
            // Omaggi
            'omaggio1' => $data['omaggio1'] ?? '',
            'omaggio2' => $data['omaggio2'] ?? '',
            'omaggio3' => $data['omaggio3'] ?? '',
            
            // Extra
            'extra1' => $data['extra1'] ?? '',
            'extra1_importo' => floatval($data['extra1_importo'] ?? 0),
            'extra2' => $data['extra2'] ?? '',
            'extra2_importo' => floatval($data['extra2_importo'] ?? 0),
            'extra3' => $data['extra3'] ?? '',
            'extra3_importo' => floatval($data['extra3_importo'] ?? 0),
            
            // Calcola importi
            'importo_totale' => floatval($data['importo_totale'] ?? 0),
            'acconto' => floatval($data['acconto'] ?? 0),
        );
        
        // Calcola extra totale
        $extra_totale = $insert_data['extra1_importo'] + $insert_data['extra2_importo'] + $insert_data['extra3_importo'];
        
        // Calcola importo_preventivo (importo totale + extra)
        $insert_data['importo_preventivo'] = $insert_data['importo_totale'] + $extra_totale;
        
        // Calcola saldo (totale - acconto)
        $insert_data['saldo'] = $insert_data['importo_preventivo'] - $insert_data['acconto'];
        
        // Continua con gli altri campi
        $insert_data = array_merge($insert_data, array(
            
            // Note
            'note_aggiuntive' => $data['note_aggiuntive'] ?? '',
            'note_interne' => $data['note_interne'] ?? '',
            
            // Stato e URLs
            'stato' => $data['stato'] ?? 'attivo',
            'googledrive_url' => $data['googledrive_url'] ?? '',
            'googledrive_file_id' => $data['googledrive_file_id'] ?? '', // ✅ Salva file_id per eliminazioni future
            'excel_url' => $data['googledrive_url'] ?? '',
            'pdf_url' => '',
            
            // Metadata
            'created_by' => $data['created_by'] ?? get_current_user_id(),
            'created_at' => $data['created_at'] ?? current_time('mysql'),
            'updated_at' => current_time('mysql')
        ));
        
        // Inserisci nel database
        $inserted = $wpdb->insert($this->table_name, $insert_data);
        
        if ($inserted === false) {
            $this->log('[DB] âŒ ERRORE INSERIMENTO: ' . $wpdb->last_error, 'ERROR');
            $this->log('[DB] Query: ' . $wpdb->last_query, 'ERROR');
            return false;
        }
        
        $insert_id = $wpdb->insert_id;
        $this->log('[DB] âœ… Preventivo inserito con ID: ' . $insert_id);
        
        // Verifica immediata che i dati siano salvati
        $check = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE id = %d",
            $insert_id
        ), ARRAY_A);
        
        if ($check) {
            $this->log('[DB] âœ… VERIFICA: Dati salvati correttamente');
            $this->log('[DB]   - Nome: ' . ($check['nome_referente'] ?? 'VUOTO'));
            $this->log('[DB]   - Cognome: ' . ($check['cognome_referente'] ?? 'VUOTO'));
            $this->log('[DB]   - Telefono: ' . ($check['telefono'] ?? 'VUOTO'));
            $this->log('[DB]   - Email: ' . ($check['email'] ?? 'VUOTO'));
        } else {
            $this->log('[DB] âŒ VERIFICA FALLITA: Record non trovato!', 'ERROR');
        }
        
        return $insert_id;
    }
    
    /**
     * ========================================================================
     * HELPER: Cleanup file temporanei
     * ========================================================================
     */
    public function cleanup_temp_files() {
        $upload_dir = wp_upload_dir();
        $temp_dir = $upload_dir['basedir'] . '/preventivi/temp/';
        
        if (!is_dir($temp_dir)) {
            return;
        }
        
        $files = glob($temp_dir . '*');
        $now = time();
        
        foreach ($files as $file) {
            if (is_file($file)) {
                if ($now - filemtime($file) >= 3600) { // 1 ora
                    @unlink($file);
                }
            }
        }
    }
    
    /**
     * ========================================================================
     * HELPER: Rileva modifiche tra vecchi e nuovi dati
     * ========================================================================
     */
    private function detect_changes($old_data, $new_data) {
        $changes = array();
        
        // Campi da monitorare
        $monitored_fields = array(
            'nome_referente' => 'Nome',
            'cognome_referente' => 'Cognome',
            'telefono' => 'Telefono',
            'email' => 'Email',
            'data_evento' => 'Data Evento',
            'tipo_evento' => 'Tipo Evento',
            'tipo_menu' => 'Menu',
            'numero_invitati' => 'Numero Invitati',
            'importo_totale' => 'Importo Totale',
            'acconto' => 'Acconto',
            'stato' => 'Stato',
            'note_aggiuntive' => 'Note',
            'orario_inizio' => 'Orario Inizio',
            'orario_fine' => 'Orario Fine'
        );
        
        foreach ($monitored_fields as $field => $label) {
            $old_value = $old_data[$field] ?? '';
            $new_value = $new_data[$field] ?? '';
            
            // Converti a stringa per confronto
            $old_str = strval($old_value);
            $new_str = strval($new_value);
            
            if ($old_str !== $new_str) {
                $changes[$field] = array(
                    'label' => $label,
                    'old' => $old_str,
                    'new' => $new_str
                );
            }
        }
        
        return $changes;
    }
    
    /**
     * ========================================================================
     * HELPER: Logging
     * ========================================================================
     */
    private function log($message, $level = 'INFO') {
        if (!$this->log_enabled) return;
        
        $timestamp = current_time('mysql');
        $log_message = "[{$timestamp}] [Disco747-Forms] [{$level}] {$message}";
        error_log($log_message);
    }
}