<?php
/**
 * Helper per rinomina file su Google Drive - 747 Disco CRM
 * 
 * Questo file contiene logica per rinominare file Excel su Google Drive
 * quando lo stato del preventivo cambia (es: da attivo ad annullato).
 * 
 * @package    Disco747_CRM
 * @subpackage Handlers
 * @version    1.0.0
 */

namespace Disco747_CRM\Handlers;

if (!defined('ABSPATH')) {
    exit('Accesso diretto non consentito');
}

trait Disco747_Forms_Rename_Helper {
    
    /**
     * ========================================================================
     * GESTISCE RINOMINA FILE QUANDO CAMBIA STATO
     * ========================================================================
     */
    protected function handle_google_drive_rename($edit_id, $old_preventivo, $new_data) {
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
            return true;
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
     * OTTIENE ISTANZA GOOGLEDRIVE
     * ========================================================================
     */
    protected function get_googledrive_instance() {
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
     * RIGENERA E CARICA EXCEL (FALLBACK)
     * ========================================================================
     */
    protected function regenerate_and_upload_excel($edit_id, $preventivo) {
        global $wpdb;
        
        $this->log('[Forms] Rigenerazione Excel...');
        
        // Prepara dati per Excel
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
        
        // Genera Excel
        $excel_path = $this->create_excel_safe($excel_data);
        
        if (!$excel_path || !file_exists($excel_path)) {
            $this->log('[Forms] Errore generazione Excel', 'ERROR');
            return false;
        }
        
        $this->log('[Forms] Excel generato: ' . basename($excel_path));
        
        // Upload su Google Drive
        if (!$this->storage) {
            $this->log('[Forms] Storage manager non disponibile', 'WARNING');
            return false;
        }
        
        try {
            // Calcola percorso Drive
            $date_parts = explode('-', $preventivo['data_evento']);
            $year = $date_parts[0];
            $month_num = $date_parts[1];
            
            $mesi = array(
                '01' => 'Gennaio', '02' => 'Febbraio', '03' => 'Marzo',
                '04' => 'Aprile', '05' => 'Maggio', '06' => 'Giugno',
                '07' => 'Luglio', '08' => 'Agosto', '09' => 'Settembre',
                '10' => 'Ottobre', '11' => 'Novembre', '12' => 'Dicembre'
            );
            $month_name = $mesi[$month_num] ?? $month_num;
            
            $drive_folder = '747-Preventivi/' . $year . '/' . $month_name . '/';
            
            $this->log('[Forms] Upload su: ' . $drive_folder);
            
            // Upload
            $upload_result = $this->storage->upload_file($excel_path, $drive_folder);
            
            if ($upload_result) {
                // Se upload_result è un array con url e file_id
                if (is_array($upload_result) && isset($upload_result['file_id'])) {
                    $wpdb->update(
                        $this->table_name,
                        array(
                            'googledrive_url' => $upload_result['url'],
                            'googledrive_file_id' => $upload_result['file_id']
                        ),
                        array('id' => $edit_id),
                        array('%s', '%s'),
                        array('%d')
                    );
                } else {
                    // Formato vecchio (solo URL)
                    $wpdb->update(
                        $this->table_name,
                        array('googledrive_url' => $upload_result),
                        array('id' => $edit_id),
                        array('%s'),
                        array('%d')
                    );
                }
                
                $this->log('[Forms] Excel caricato su Google Drive');
                return true;
            }
            
        } catch (\Exception $e) {
            $this->log('[Forms] Errore upload: ' . $e->getMessage(), 'ERROR');
        }
        
        return false;
    }
}
