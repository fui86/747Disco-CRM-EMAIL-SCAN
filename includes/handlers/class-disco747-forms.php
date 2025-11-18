<?php
/**
 * Forms Handler per 747 Disco CRM - VERSIONE FINALE v12.3.0
 * 
 * FIX APPLICATI:
 * 1. ÃƒÂ¢Ã…â€œÃ¢â‚¬Â¦ Cartella basata su data_evento
 * 2. ÃƒÂ¢Ã…â€œÃ¢â‚¬Â¦ PDF NON generato automaticamente
 * 3. ÃƒÂ¢Ã…â€œÃ¢â‚¬Â¦ PDF on-demand SENZA upload Google Drive
 * 4. ÃƒÂ¢Ã…â€œÃ¢â‚¬Â¦ Salvataggio database COMPLETO con tutti i campi
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
            
            $this->log('[Forms] ÃƒÂ¢Ã…â€œÃ¢â‚¬Â¦ Dati validati correttamente');
            
            // Controlla se ÃƒÆ’Ã‚Â¨ modalitÃƒÆ’Ã‚Â  modifica
            $is_edit_mode = !empty($_POST['is_edit_mode']);
            $edit_id = $is_edit_mode ? intval($_POST['edit_id']) : 0;
            
            if ($is_edit_mode && $edit_id > 0) {
                $this->update_preventivo($edit_id, $data);
                return;
            }
            
            // Creazione nuovo preventivo
            $this->create_new_preventivo($data);
            
        } catch (\Exception $e) {
            $this->log('[Forms] ÃƒÂ¢Ã‚ÂÃ…â€™ ERRORE FATALE: ' . $e->getMessage(), 'ERROR');
            wp_send_json_error('Errore: ' . $e->getMessage());
        }
    }
    
    /**
     * ========================================================================
     * CREAZIONE NUOVO PREVENTIVO
     * ========================================================================
     */
    private function create_new_preventivo($data) {
        $this->log('[Forms] MODALITÃƒÆ’Ã¢â€šÂ¬ CREAZIONE NUOVO PREVENTIVO');
        
        // Genera ID preventivo progressivo
        $data['preventivo_id'] = $this->generate_preventivo_id();
        $this->log('[Forms] ID Preventivo generato: ' . $data['preventivo_id']);
        
        // ÃƒÂ¢Ã…â€œÃ¢â‚¬Â¦ GENERA SOLO EXCEL (NO PDF)
        $this->log('[Forms] ÃƒÂ°Ã…Â¸Ã¢â‚¬Å“Ã¢â‚¬Å¾ Generazione Excel...');
        $excel_path = $this->create_excel_safe($data);
        
        if (!$excel_path) {
            throw new \Exception('Errore generazione Excel');
        }
        
        $this->log('[Forms] ÃƒÂ¢Ã…â€œÃ¢â‚¬Â¦ Excel generato: ' . basename($excel_path));
        
        // ÃƒÂ¢Ã¢â‚¬ÂºÃ¢â‚¬Â PDF NON generato automaticamente
        $pdf_path = null;
        
        // ÃƒÂ¢Ã…â€œÃ¢â‚¬Â¦ UPLOAD SU GOOGLE DRIVE nella cartella corretta (basata su data_evento)
        $cloud_url = '';
        if ($this->storage) {
            $this->log('[Forms] ÃƒÂ¢Ã‹Å“Ã‚ÂÃƒÂ¯Ã‚Â¸Ã‚Â Upload su Google Drive...');
            
            try {
                // ÃƒÂ¢Ã…â€œÃ¢â‚¬Â¦ FIX: Usa data_evento per determinare il percorso corretto
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
                
                $this->log('[Forms] ÃƒÂ°Ã…Â¸Ã¢â‚¬Å“Ã‚Â Percorso Google Drive: ' . $drive_folder);
                $this->log('[Forms] ÃƒÂ°Ã…Â¸Ã¢â‚¬Å“Ã¢â‚¬Â¦ Data evento usata: ' . $data['data_evento']);
                
                // Upload Excel
                if ($excel_path && file_exists($excel_path)) {
                    $excel_url = $this->storage->upload_file($excel_path, $drive_folder);
                    if ($excel_url) {
                        // Se è un array con file_id, salvalo
                        if (is_array($excel_url) && isset($excel_url['file_id'])) {
                            $cloud_url = $excel_url['url'];
                            $data['googledrive_url'] = $excel_url['url'];
                            $data['googledrive_file_id'] = $excel_url['file_id'];
                            $this->log('[Forms] ✅ Excel caricato su Drive con file_id: ' . $excel_url['file_id']);
                        } else {
                            // Formato vecchio (solo URL)
                            $cloud_url = $excel_url;
                            $data['googledrive_url'] = $excel_url;
                            $this->log('[Forms] ✅ Excel caricato su Drive (formato vecchio): ' . basename($excel_path));
                        }
                    }
                }
                
            } catch (\Exception $e) {
                $this->log('[Forms] ÃƒÂ¢Ã…Â¡Ã‚Â ÃƒÂ¯Ã‚Â¸Ã‚Â Errore upload Drive: ' . $e->getMessage(), 'WARNING');
            }
        }
        
        // Salva database
        $this->log('[Forms] ÃƒÂ°Ã…Â¸Ã¢â‚¬â„¢Ã‚Â¾ Salvataggio database...');
        $db_id = $this->save_preventivo($data);
        
        if (!$db_id) {
            throw new \Exception('Errore salvataggio database');
        }
        
        $this->log('[Forms] ÃƒÂ¢Ã…â€œÃ¢â‚¬Â¦ Preventivo salvato con ID database: ' . $db_id);
        
        // 🚀 HOOK: Lancia evento preventivo creato/confermato (per funnel automation)
        if ($data['stato'] === 'confermato' && floatval($data['acconto']) > 0) {
            // Se è confermato, lancia hook conferma
            do_action('disco747_preventivo_confirmed', $db_id);
            $this->log('[Forms] 🎯 Hook disco747_preventivo_confirmed lanciato (ID: ' . $db_id . ')');
        } else {
            // Se NON è confermato, lancia hook creazione (avvia funnel pre-conferma)
            do_action('disco747_preventivo_created', $db_id);
            $this->log('[Forms] 🎯 Hook disco747_preventivo_created lanciato (ID: ' . $db_id . ')');
        }
        
        $this->log('[Forms] ========== ÃƒÂ¢Ã…â€œÃ¢â‚¬Â¦ÃƒÂ¢Ã…â€œÃ¢â‚¬Â¦ÃƒÂ¢Ã…â€œÃ¢â‚¬Â¦ PREVENTIVO COMPLETATO ==========');
        
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
        
        // ðŸ” LEGGI STATO PRECEDENTE (per determinare se Ã¨ stata una conferma)
        $old_preventivo = $wpdb->get_row($wpdb->prepare(
            "SELECT stato, acconto, googledrive_file_id, data_evento, tipo_evento, tipo_menu FROM {$this->table_name} WHERE id = %d",
            $edit_id
        ));
        $old_stato = $old_preventivo ? $old_preventivo->stato : '';
        $old_acconto = $old_preventivo ? floatval($old_preventivo->acconto) : 0;
        
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
                'updated_at' => current_time('mysql')
            ),
            array('id' => $edit_id),
            array(
                '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d',
                '%s', '%s', '%s', '%s', '%s', '%s', '%f', '%s', '%f',
                '%s', '%f', '%f', '%f', '%f', '%f', '%s', '%s', '%s', '%s'
            ),
            array('%d')
        );
        
        if ($updated === false) {
            $this->log('[Forms] Ã¢Å’ Errore aggiornamento: ' . $wpdb->last_error, 'ERROR');
            wp_send_json_error('Errore aggiornamento database');
            return;
        }
        
        $this->log('[Forms] Ã¢Å“â€¦ Preventivo aggiornato con successo');
        
        // ðŸŽ‰ HOOK: Controlla se il preventivo Ã¨ stato appena confermato
        $new_stato = $data['stato'];
        $new_acconto = floatval($data['acconto']);
        $just_confirmed = ($old_stato !== 'confermato' && $new_stato === 'confermato' && $new_acconto > 0);
        
        if ($just_confirmed) {
            // Il preventivo Ã¨ stato APPENA confermato â†’ lancia hook
            do_action('disco747_preventivo_confirmed', $edit_id);
            $this->log('[Forms] ðŸŽ¯ Hook disco747_preventivo_confirmed lanciato (da ' . $old_stato . ' a confermato, ID: ' . $edit_id . ')');
        }

        
        // Rileggi preventivo aggiornato dal database per avere tutti i dati
        $preventivo = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE id = %d",
            $edit_id
        ), ARRAY_A);
        
        // GESTIONE RINOMINA FILE SU GOOGLE DRIVE
        $rename_success = $this->handle_google_drive_rename($edit_id, $old_preventivo, $data);
        
        // Se la rinomina è riuscita, NON serve rigenerare e ricaricare il file
        if ($rename_success) {
            $this->log('[Forms] ✅ Rinomina completata, salto rigenerazione Excel');
            
            // Invia risposta senza rigenerare
            wp_send_json_success(array(
                'message' => 'Preventivo aggiornato con successo!',
                'preventivo_id' => $preventivo['preventivo_id'] ?? $data['preventivo_id'],
                'id' => $edit_id,
                'db_id' => $edit_id,
                'is_edit_mode' => true,
                'file_renamed' => true,
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
                'acconto' => $preventivo['acconto'] ?? $data['acconto'] ?? 0,
                'stato' => $preventivo['stato'] ?? $data['stato'] ?? 'attivo'
            ));
            return;
        }
        
        // Se la rinomina fallisce o non serve, ALLORA rigenera + upload
        $this->log('[Forms] Rinomina fallita o non necessaria, procedo con rigenera + upload...');
        
        // ÃƒÂ¢Ã…â€œÃ¢â‚¬Â¦ RIGENERA EXCEL E RICARICA SU GOOGLE DRIVE
        $this->log('[Forms] ÃƒÂ°Ã…Â¸"Ã¢â‚¬Å¾ Rigenerazione Excel per preventivo aggiornato...');
        
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
            $this->log('[Forms] ÃƒÂ¢Ã…â€œÃ¢â‚¬Â¦ Excel rigenerato: ' . basename($excel_path));
            
            // Upload su Google Drive (sovrascrive il vecchio)
            if ($this->storage) {
                $this->log('[Forms] ÃƒÂ¢Ã‹Å“ÃƒÂ¯Ã‚Â¸ Upload Excel aggiornato su Google Drive...');
                
                try {
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
                    
                    $this->log('[Forms] ÃƒÂ°Ã…Â¸" Percorso Google Drive: ' . $drive_folder);
                    
                    // Upload Excel
                    $excel_url = $this->storage->upload_file($excel_path, $drive_folder);
                    if ($excel_url) {
                        // Aggiorna URL nel database
                        $wpdb->update(
                            $this->table_name,
                            array('googledrive_url' => $excel_url),
                            array('id' => $edit_id),
                            array('%s'),
                            array('%d')
                        );
                        $this->log('[Forms] ÃƒÂ¢Ã…â€œÃ¢â‚¬Â¦ Excel aggiornato su Drive: ' . basename($excel_path));
                    }
                    
                } catch (\Exception $e) {
                    $this->log('[Forms] ÃƒÂ¢Ã…Â¡ ÃƒÂ¯Ã‚Â¸ Errore upload Drive: ' . $e->getMessage(), 'WARNING');
                }
            }
        } else {
            $this->log('[Forms] ÃƒÂ¢Ã…Â¡ ÃƒÂ¯Ã‚Â¸ Impossibile rigenerare Excel', 'WARNING');
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
            
            $this->log('[PDF] ÃƒÂ¢Ã…â€œÃ¢â‚¬Â¦ PDF generato: ' . basename($pdf_path));
            
            // ÃƒÂ¢Ã¢â‚¬ÂºÃ¢â‚¬Â RIMOSSO: Upload su Google Drive
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
            
            $this->log('[PDF] ========== ÃƒÂ¢Ã…â€œÃ¢â‚¬Â¦ COMPLETATO (NO UPLOAD DRIVE) ==========');
            
            wp_send_json_success(array(
                'message' => 'PDF generato con successo!',
                'pdf_path' => $pdf_path,
                'pdf_url' => $pdf_url,
                'download_url' => $pdf_url,
                'filename' => basename($pdf_path),
                'note' => 'PDF salvato solo localmente, NON caricato su Google Drive'
            ));
            
        } catch (\Exception $e) {
            $this->log('[PDF] ÃƒÂ¢Ã‚ÂÃ…â€™ ERRORE: ' . $e->getMessage(), 'ERROR');
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
            $this->log('[Email] ÃƒÂ¢Ã‚ÂÃ…â€™ ERRORE: ' . $e->getMessage(), 'ERROR');
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
                '1' => "Ciao {{nome}}! ÃƒÂ°Ã…Â¸Ã…Â½Ã¢â‚¬Â°\n\nIl tuo preventivo per {{tipo_evento}} del {{data_evento}} ÃƒÆ’Ã‚Â¨ pronto!\n\nÃƒÂ°Ã…Â¸Ã¢â‚¬â„¢Ã‚Â° Importo: {{importo}}\n\n747 Disco - La tua festa indimenticabile! ÃƒÂ°Ã…Â¸Ã…Â½Ã…Â ",
                '2' => "Ciao {{nome}}! ÃƒÂ°Ã…Â¸Ã…Â½Ã‹â€ \n\nTi ricordiamo il tuo evento del {{data_evento}}.\n\nHai confermato? Rispondi per finalizzare! ÃƒÂ°Ã…Â¸Ã¢â‚¬Å“Ã…Â¾",
                '3' => "Ciao {{nome}}! ÃƒÂ¢Ã…â€œÃ¢â‚¬Â¦\n\nGrazie per aver confermato!\n\nÃƒÂ°Ã…Â¸Ã¢â‚¬Å“Ã¢â‚¬Â¦ {{data_evento}}\nÃƒÂ°Ã…Â¸Ã¢â‚¬â„¢Ã‚Â° Acconto: {{acconto}}\n\nCi vediamo presto! ÃƒÂ°Ã…Â¸Ã…Â½Ã¢â‚¬Â°"
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
            
            $this->log('[WhatsApp] ÃƒÂ¢Ã…â€œÃ¢â‚¬Â¦ Link generato per: ' . $phone);
            
            wp_send_json_success(array(
                'message' => 'Link WhatsApp generato!',
                'whatsapp_url' => $whatsapp_url,
                'phone' => $phone,
                'template_used' => $template_id
            ));
            
        } catch (\Exception $e) {
            $this->log('[WhatsApp] ÃƒÂ¢Ã‚ÂÃ…â€™ ERRORE: ' . $e->getMessage(), 'ERROR');
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
            '{{importo}}' => number_format($data['importo_totale'] ?? 0, 2, ',', '.') . ' ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬',
            '{{acconto}}' => number_format($data['acconto'] ?? 0, 2, ',', '.') . ' ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬',
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
                $this->log('[Forms] Ã¢Å“â€¦ Nonce verificato correttamente (disco747_preventivo)');
                return true;
            }
        }
        
        // Fallback: altri possibili nonce
        if (isset($_POST['disco747_nonce'])) {
            if (wp_verify_nonce($_POST['disco747_nonce'], 'disco747_action')) {
                $this->log('[Forms] Ã¢Å“â€¦ Nonce verificato correttamente (disco747_nonce)');
                return true;
            }
        }
        
        if (isset($_POST['_wpnonce'])) {
            if (wp_verify_nonce($_POST['_wpnonce'], 'disco747_preventivo')) {
                $this->log('[Forms] Ã¢Å“â€¦ Nonce verificato correttamente (_wpnonce)');
                return true;
            }
        }
        
        // Log per debug
        $this->log('[Forms] Ã¢ÂÅ’ Nessun nonce valido trovato. Campi POST ricevuti: ' . implode(', ', array_keys($_POST)), 'WARNING');
        
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
        
        // CONTATTI - Duplica per compatibilitÃƒÆ’Ã‚Â 
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
        $this->log('  - Extra1: "' . $data['extra1'] . '" = Ã¢â€šÂ¬' . $data['extra1_importo']);
        $this->log('  - Extra2: "' . $data['extra2'] . '" = Ã¢â€šÂ¬' . $data['extra2_importo']);
        $this->log('  - Extra3: "' . $data['extra3'] . '" = Ã¢â€šÂ¬' . $data['extra3_importo']);
        
        // IMPORTI
        $data['importo_preventivo'] = floatval($post_data['importo_preventivo'] ?? 0);
        $data['importo_totale'] = $data['importo_preventivo'];
        $data['acconto'] = floatval($post_data['acconto'] ?? 0);
        
        // NOTE
        $data['note_aggiuntive'] = sanitize_textarea_field($post_data['note_aggiuntive'] ?? '');
        $data['note_interne'] = sanitize_textarea_field($post_data['note_interne'] ?? '');
        
        // METADATA
        $data['stato'] = 'attivo';
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
        
        // ÃƒÂ¢Ã…â€œÃ¢â‚¬Â¦ MAPPATURA CORRETTA: Mantieni TUTTI i campi necessari
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
            'googledrive_file_id' => $data['googledrive_file_id'] ?? '',
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
            $this->log('[DB] ÃƒÂ¢Ã‚ÂÃ…â€™ ERRORE INSERIMENTO: ' . $wpdb->last_error, 'ERROR');
            $this->log('[DB] Query: ' . $wpdb->last_query, 'ERROR');
            return false;
        }
        
        $insert_id = $wpdb->insert_id;
        $this->log('[DB] ÃƒÂ¢Ã…â€œÃ¢â‚¬Â¦ Preventivo inserito con ID: ' . $insert_id);
        
        // Verifica immediata che i dati siano salvati
        $check = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE id = %d",
            $insert_id
        ), ARRAY_A);
        
        if ($check) {
            $this->log('[DB] ÃƒÂ¢Ã…â€œÃ¢â‚¬Â¦ VERIFICA: Dati salvati correttamente');
            $this->log('[DB]   - Nome: ' . ($check['nome_referente'] ?? 'VUOTO'));
            $this->log('[DB]   - Cognome: ' . ($check['cognome_referente'] ?? 'VUOTO'));
            $this->log('[DB]   - Telefono: ' . ($check['telefono'] ?? 'VUOTO'));
            $this->log('[DB]   - Email: ' . ($check['email'] ?? 'VUOTO'));
        } else {
            $this->log('[DB] ÃƒÂ¢Ã‚ÂÃ…â€™ VERIFICA FALLITA: Record non trovato!', 'ERROR');
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
     * HELPER: Gestisce rinomina file su Google Drive quando cambia stato
     * ========================================================================
     */
    private function handle_google_drive_rename($edit_id, $old_preventivo, $new_data) {
        global $wpdb;
        
        $old_file_id = $old_preventivo->googledrive_file_id ?? '';
        
        // Calcola vecchio e nuovo nome file
        $old_filename_data = array(
            'data_evento' => $old_preventivo->data_evento,
            'tipo_evento' => $old_preventivo->tipo_evento,
            'tipo_menu' => $old_preventivo->tipo_menu,
            'stato' => $old_preventivo->stato,
            'acconto' => floatval($old_preventivo->acconto)
        );
        $old_filename = $this->generate_filename($old_filename_data) . '.xlsx';
        
        $new_filename_data = array(
            'data_evento' => $new_data['data_evento'],
            'tipo_evento' => $new_data['tipo_evento'],
            'tipo_menu' => $new_data['tipo_menu'],
            'stato' => $new_data['stato'],
            'acconto' => floatval($new_data['acconto'])
        );
        $new_filename = $this->generate_filename($new_filename_data) . '.xlsx';
        
        $filename_changed = ($old_filename !== $new_filename);
        
        if (!$filename_changed) {
            $this->log("[Forms] Nome file non cambiato, nessuna rinomina necessaria");
            return false;
        }
        
        $this->log("[Forms] Nome file cambiato:");
        $this->log("   Vecchio: {$old_filename}");
        $this->log("   Nuovo: {$new_filename}");
        
        if (empty($old_file_id)) {
            $this->log("[Forms] googledrive_file_id mancante, impossibile rinominare", 'WARNING');
            return false;
        }
        
        // Ottieni istanza GoogleDrive
        $googledrive = $this->get_googledrive_instance();
        
        if (!$googledrive || !method_exists($googledrive, 'rename_file')) {
            $this->log('[Forms] Metodo rename_file non disponibile', 'WARNING');
            return false;
        }
        
        // Rinomina file su Google Drive
        $this->log("[Forms] Rinomina file su Google Drive (ID: {$old_file_id})...");
        $rename_result = $googledrive->rename_file($old_file_id, $new_filename);
        
        if ($rename_result['success']) {
            $this->log('[Forms] File rinominato su Google Drive con successo!');
            return true;
        } else {
            $error = $rename_result['error'] ?? 'Sconosciuto';
            $this->log("[Forms] Errore rinomina su Google Drive: {$error}", 'ERROR');
            return false;
        }
    }
    
    /**
     * ========================================================================
     * HELPER: Ottiene istanza GoogleDrive
     * ========================================================================
     */
    private function get_googledrive_instance() {
        // Prova tramite storage manager
        if ($this->storage && method_exists($this->storage, 'get_googledrive')) {
            return $this->storage->get_googledrive();
        }
        
        // Prova tramite plugin principale
        $disco747_crm = disco747_crm();
        if ($disco747_crm && method_exists($disco747_crm, 'get_googledrive')) {
            return $disco747_crm->get_googledrive();
        }
        
        // Prova a istanziare direttamente
        if (class_exists('\Disco747_CRM\Storage\Disco747_GoogleDrive')) {
            return new \Disco747_CRM\Storage\Disco747_GoogleDrive();
        }
        
        return null;
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