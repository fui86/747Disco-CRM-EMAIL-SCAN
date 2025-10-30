<?php
/**
 * Forms Handler per 747 Disco CRM
 * VERSIONE COMPLETA con implementazione pulsanti PDF, Email, WhatsApp
 * 
 * @package    Disco747_CRM
 * @subpackage Handlers
 * @version    12.1.0-COMPLETE
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
    private $messaging;
    private $log_enabled = true;
    private $components_loaded = false;
    private $table_name;
    
    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'disco747_preventivi';
        $this->log('[Forms] Handler Forms v12.1.0-COMPLETE inizializzato - Tabella: ' . $this->table_name);
        
        // Hook AJAX esistenti
        add_action('wp_ajax_disco747_save_preventivo', array($this, 'handle_ajax_submission'));
        add_action('wp_ajax_nopriv_disco747_save_preventivo', array($this, 'handle_ajax_submission'));
        
        // NUOVI Hook AJAX per i pulsanti
        add_action('wp_ajax_disco747_generate_pdf', array($this, 'handle_generate_pdf'));
        add_action('wp_ajax_disco747_download_pdf', array($this, 'handle_download_pdf'));
        add_action('wp_ajax_disco747_send_email_template', array($this, 'handle_send_email_template'));
        add_action('wp_ajax_disco747_send_whatsapp_template', array($this, 'handle_send_whatsapp_template'));
        
        // Cleanup schedulato
        add_action('disco747_cleanup_temp_files', array($this, 'cleanup_temp_files'));
        if (!wp_next_scheduled('disco747_cleanup_temp_files')) {
            wp_schedule_event(time(), 'hourly', 'disco747_cleanup_temp_files');
        }
        
        $this->log('[Forms] Hook AJAX registrati correttamente');
    }
    
    /**
     * ============================================================================
     * METODO ESISTENTE: Handle AJAX Submission (NON MODIFICATO)
     * ============================================================================
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
            
            $this->log('[Forms] ✅ Dati validati correttamente');
            
            // Genera ID progressivo
            $data['preventivo_id'] = $this->generate_progressive_id();
            $this->log('[Forms] ID Progressivo generato: ' . $data['preventivo_id']);
            
            // Genera filename
            $filename_base = $this->generate_filename($data);
            $this->log('[Forms] Filename base: ' . $filename_base);
            
            // Percorsi file
            $upload_dir = wp_upload_dir();
            $preventivi_dir = $upload_dir['basedir'] . '/preventivi/';
            
            if (!file_exists($preventivi_dir)) {
                wp_mkdir_p($preventivi_dir);
            }
            
            $excel_path = $preventivi_dir . $filename_base . '.xlsx';
            $pdf_path = $preventivi_dir . $filename_base . '.pdf';
            
            // Genera Excel
            $this->log('[Forms] Generazione Excel...');
            if ($this->excel && method_exists($this->excel, 'generate_excel')) {
                $result = $this->excel->generate_excel($data);
                if ($result && file_exists($result)) {
                    if ($result !== $excel_path) {
                        copy($result, $excel_path);
                    }
                    $this->log('[Forms] ✅ Excel generato: ' . basename($excel_path));
                }
            }
            
            // Genera PDF
            $this->log('[Forms] Generazione PDF...');
            if ($this->pdf && method_exists($this->pdf, 'generate_pdf')) {
                $result = $this->pdf->generate_pdf($data);
                if ($result && file_exists($result)) {
                    if ($result !== $pdf_path) {
                        copy($result, $pdf_path);
                    }
                    $this->log('[Forms] ✅ PDF generato: ' . basename($pdf_path));
                }
            }
            
            // Upload su Google Drive
            $cloud_url = '';
            if ($this->storage && file_exists($excel_path)) {
                $this->log('[Forms] Upload su storage...');
                
                $date_parts = explode('-', $data['data_evento']);
                $year = $date_parts[0];
                $month = $date_parts[1];
                
                try {
                    $uploaded = $this->storage->upload_file($excel_path, $data['data_evento']);
                    if ($uploaded) {
                        $cloud_url = $uploaded;
                        $this->log('[Forms] ✅ File caricato su cloud');
                    }
                    
                    if (file_exists($pdf_path)) {
                        $this->storage->upload_file($pdf_path, $data['data_evento']);
                    }
                } catch (\Exception $e) {
                    $this->log('[Forms] ⚠️ Errore upload: ' . $e->getMessage(), 'WARNING');
                }
            }
            
            // Salva nel database
            $data['excel_path'] = basename($excel_path);
            $data['pdf_path'] = basename($pdf_path);
            $data['cloud_url'] = $cloud_url;
            $data['created_by'] = get_current_user_id();
            
            $db_id = $this->save_to_database($data);
            
            $this->log('[Forms] ========== ✅✅✅ PREVENTIVO COMPLETATO CON SUCCESSO ==========');
            
            wp_send_json_success(array(
                'message' => 'Preventivo creato con successo!',
                'preventivo_id' => $data['preventivo_id'],
                'db_id' => $db_id,
                'keep_form_open' => true,
                'data' => $data,
                'files' => array(
                    'excel' => basename($excel_path),
                    'pdf' => basename($pdf_path)
                ),
                'paths' => array(
                    'excel_path' => $excel_path,
                    'pdf_path' => $pdf_path
                ),
                'cloud_url' => $cloud_url
            ));
            
        } catch (\Exception $e) {
            $this->log('[Forms] ❌ ERRORE FATALE: ' . $e->getMessage(), 'ERROR');
            wp_send_json_error('Errore: ' . $e->getMessage());
        }
    }
    
    /**
     * ============================================================================
     * NUOVO METODO: Genera PDF su richiesta
     * ============================================================================
     */
    public function handle_generate_pdf() {
        try {
            $this->log('[GeneratePDF] ========== INIZIO GENERAZIONE PDF ==========');
            
            // Verifica nonce
            if (!check_ajax_referer('disco747_preventivo_nonce', 'nonce', false)) {
                wp_send_json_error('Nonce non valido');
                return;
            }
            
            // Carica componenti
            $this->load_components();
            
            // Ottieni ID preventivo
            $preventivo_id = intval($_POST['preventivo_id'] ?? 0);
            
            if (!$preventivo_id) {
                wp_send_json_error('ID preventivo mancante');
                return;
            }
            
            // Carica dati dal database
            $preventivo_data = $this->get_preventivo_from_db($preventivo_id);
            
            if (!$preventivo_data) {
                wp_send_json_error('Preventivo non trovato nel database');
                return;
            }
            
            $this->log('[GeneratePDF] Dati preventivo caricati per ID: ' . $preventivo_id);
            
            // Genera filename
            $filename = $this->generate_filename($preventivo_data);
            
            // Percorso PDF
            $upload_dir = wp_upload_dir();
            $preventivi_dir = $upload_dir['basedir'] . '/preventivi/';
            $pdf_path = $preventivi_dir . $filename . '.pdf';
            
            // Genera PDF
            if (!$this->pdf || !method_exists($this->pdf, 'generate_pdf')) {
                wp_send_json_error('Generatore PDF non disponibile');
                return;
            }
            
            $this->log('[GeneratePDF] Generazione PDF in corso...');
            $result = $this->pdf->generate_pdf($preventivo_data);
            
            if (!$result || !file_exists($result)) {
                wp_send_json_error('Errore nella generazione del PDF');
                return;
            }
            
            // Sposta in posizione corretta se necessario
            if ($result !== $pdf_path) {
                copy($result, $pdf_path);
                @unlink($result);
            }
            
            $this->log('[GeneratePDF] ✅ PDF generato con successo: ' . basename($pdf_path));
            
            // Upload su Google Drive (opzionale)
            if ($this->storage) {
                try {
                    $this->storage->upload_file($pdf_path, $preventivo_data['data_evento']);
                    $this->log('[GeneratePDF] ✅ PDF caricato su Google Drive');
                } catch (\Exception $e) {
                    $this->log('[GeneratePDF] ⚠️ Upload cloud fallito: ' . $e->getMessage(), 'WARNING');
                }
            }
            
            // Aggiorna database con path PDF
            global $wpdb;
            $wpdb->update(
                $this->table_name,
                array('pdf_path' => basename($pdf_path)),
                array('preventivo_id' => $preventivo_id),
                array('%s'),
                array('%s')
            );
            
            wp_send_json_success(array(
                'message' => 'PDF generato con successo!',
                'filename' => basename($pdf_path),
                'pdf_path' => $pdf_path,
                'download_url' => admin_url('admin-ajax.php?action=disco747_download_pdf&preventivo_id=' . $preventivo_id . '&nonce=' . wp_create_nonce('disco747_download_pdf'))
            ));
            
        } catch (\Exception $e) {
            $this->log('[GeneratePDF] ❌ ERRORE: ' . $e->getMessage(), 'ERROR');
            wp_send_json_error('Errore generazione PDF: ' . $e->getMessage());
        }
    }
    
    /**
     * ============================================================================
     * NUOVO METODO: Download PDF
     * ============================================================================
     */
    public function handle_download_pdf() {
        // Verifica nonce
        if (!isset($_GET['nonce']) || !wp_verify_nonce($_GET['nonce'], 'disco747_download_pdf')) {
            wp_die('Nonce non valido');
        }
        
        $preventivo_id = intval($_GET['preventivo_id'] ?? 0);
        
        if (!$preventivo_id) {
            wp_die('ID preventivo mancante');
        }
        
        // Carica dati
        $preventivo_data = $this->get_preventivo_from_db($preventivo_id);
        
        if (!$preventivo_data || empty($preventivo_data['pdf_path'])) {
            wp_die('PDF non trovato');
        }
        
        $upload_dir = wp_upload_dir();
        $pdf_path = $upload_dir['basedir'] . '/preventivi/' . $preventivo_data['pdf_path'];
        
        if (!file_exists($pdf_path)) {
            wp_die('File PDF non esiste sul server');
        }
        
        // Download
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . basename($pdf_path) . '"');
        header('Content-Length: ' . filesize($pdf_path));
        readfile($pdf_path);
        exit;
    }
    
    /**
     * ============================================================================
     * NUOVO METODO: Invia Email con Template
     * ============================================================================
     */
    public function handle_send_email_template() {
        try {
            $this->log('[SendEmail] ========== INIZIO INVIO EMAIL ==========');
            
            // Verifica nonce
            if (!check_ajax_referer('disco747_preventivo_nonce', 'nonce', false)) {
                wp_send_json_error('Nonce non valido');
                return;
            }
            
            // Carica componenti
            $this->load_components();
            
            // Parametri
            $preventivo_id = intval($_POST['preventivo_id'] ?? 0);
            $template_id = intval($_POST['template_id'] ?? 1);
            $attach_pdf = !empty($_POST['attach_pdf']);
            $pdf_path = sanitize_text_field($_POST['pdf_path'] ?? '');
            
            if (!$preventivo_id) {
                wp_send_json_error('ID preventivo mancante');
                return;
            }
            
            // Carica dati preventivo
            $preventivo_data = $this->get_preventivo_from_db($preventivo_id);
            
            if (!$preventivo_data) {
                wp_send_json_error('Preventivo non trovato');
                return;
            }
            
            $this->log('[SendEmail] Template: ' . $template_id . ', Allegato: ' . ($attach_pdf ? 'SI' : 'NO'));
            
            // Carica template email
            $email_subject = get_option('disco747_email_subject_' . $template_id, 'Preventivo 747 Disco');
            $email_body = get_option('disco747_email_template_' . $template_id, '');
            
            if (empty($email_body)) {
                wp_send_json_error('Template email non configurato');
                return;
            }
            
            // Sostituisci placeholder
            $email_body = $this->replace_placeholders($email_body, $preventivo_data);
            $email_subject = $this->replace_placeholders($email_subject, $preventivo_data);
            
            // Destinatario
            $to = $preventivo_data['mail'];
            
            if (empty($to) || !is_email($to)) {
                wp_send_json_error('Email destinatario non valida');
                return;
            }
            
            // Headers
            $headers = array(
                'Content-Type: text/html; charset=UTF-8',
                'From: 747 Disco <eventi@747disco.it>',
                'Cc: info@747disco.it'
            );
            
            // Allegato PDF
            $attachments = array();
            if ($attach_pdf && !empty($pdf_path)) {
                $upload_dir = wp_upload_dir();
                $full_pdf_path = $upload_dir['basedir'] . '/preventivi/' . basename($pdf_path);
                
                if (file_exists($full_pdf_path)) {
                    $attachments[] = $full_pdf_path;
                    $this->log('[SendEmail] PDF allegato: ' . basename($full_pdf_path));
                }
            }
            
            // Invia email
            $sent = wp_mail($to, $email_subject, $email_body, $headers, $attachments);
            
            if ($sent) {
                $this->log('[SendEmail] ✅ Email inviata con successo a: ' . $to);
                
                wp_send_json_success(array(
                    'message' => 'Email inviata con successo!',
                    'recipient' => $to,
                    'template_used' => $template_id
                ));
            } else {
                $this->log('[SendEmail] ❌ Errore invio email', 'ERROR');
                wp_send_json_error('Errore nell\'invio dell\'email');
            }
            
        } catch (\Exception $e) {
            $this->log('[SendEmail] ❌ ERRORE: ' . $e->getMessage(), 'ERROR');
            wp_send_json_error('Errore: ' . $e->getMessage());
        }
    }
    
    /**
     * ============================================================================
     * NUOVO METODO: Prepara messaggio WhatsApp con Template
     * ============================================================================
     */
    public function handle_send_whatsapp_template() {
        try {
            $this->log('[SendWhatsApp] ========== INIZIO PREPARAZIONE WHATSAPP ==========');
            
            // Verifica nonce
            if (!check_ajax_referer('disco747_preventivo_nonce', 'nonce', false)) {
                wp_send_json_error('Nonce non valido');
                return;
            }
            
            // Parametri
            $preventivo_id = intval($_POST['preventivo_id'] ?? 0);
            $template_id = intval($_POST['template_id'] ?? 1);
            
            if (!$preventivo_id) {
                wp_send_json_error('ID preventivo mancante');
                return;
            }
            
            // Carica dati preventivo
            $preventivo_data = $this->get_preventivo_from_db($preventivo_id);
            
            if (!$preventivo_data) {
                wp_send_json_error('Preventivo non trovato');
                return;
            }
            
            $this->log('[SendWhatsApp] Template: ' . $template_id);
            
            // Carica template WhatsApp
            $whatsapp_message = get_option('disco747_whatsapp_template_' . $template_id, '');
            
            if (empty($whatsapp_message)) {
                wp_send_json_error('Template WhatsApp non configurato');
                return;
            }
            
            // Sostituisci placeholder
            $whatsapp_message = $this->replace_placeholders($whatsapp_message, $preventivo_data);
            
            // Numero telefono
            $phone = $preventivo_data['cellulare'] ?? $preventivo_data['telefono'] ?? '';
            $phone = preg_replace('/[^0-9+]/', '', $phone);
            
            if (empty($phone)) {
                wp_send_json_error('Numero telefono non disponibile');
                return;
            }
            
            // Se il numero non inizia con +, aggiungi +39 (Italia)
            if (substr($phone, 0, 1) !== '+') {
                if (substr($phone, 0, 2) === '39') {
                    $phone = '+' . $phone;
                } else {
                    $phone = '+39' . $phone;
                }
            }
            
            // Genera URL WhatsApp
            $whatsapp_url = 'https://wa.me/' . $phone . '?text=' . urlencode($whatsapp_message);
            
            $this->log('[SendWhatsApp] ✅ Link WhatsApp generato per: ' . $phone);
            
            wp_send_json_success(array(
                'message' => 'Link WhatsApp generato!',
                'whatsapp_url' => $whatsapp_url,
                'phone' => $phone,
                'template_used' => $template_id
            ));
            
        } catch (\Exception $e) {
            $this->log('[SendWhatsApp] ❌ ERRORE: ' . $e->getMessage(), 'ERROR');
            wp_send_json_error('Errore: ' . $e->getMessage());
        }
    }
    
    /**
     * ============================================================================
     * HELPER: Carica preventivo dal database
     * ============================================================================
     */
    private function get_preventivo_from_db($preventivo_id) {
        global $wpdb;
        
        $preventivo = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE preventivo_id = %s",
            $preventivo_id
        ), ARRAY_A);
        
        return $preventivo;
    }
    
    /**
     * ============================================================================
     * HELPER: Sostituisce placeholder nei template
     * ============================================================================
     */
    private function replace_placeholders($text, $data) {
        $replacements = array(
            '{{nome}}' => $data['nome_referente'] ?? '',
            '{{cognome}}' => $data['cognome_referente'] ?? '',
            '{{nome_completo}}' => trim(($data['nome_referente'] ?? '') . ' ' . ($data['cognome_referente'] ?? '')),
            '{{email}}' => $data['mail'] ?? $data['email'] ?? '',
            '{{telefono}}' => $data['cellulare'] ?? $data['telefono'] ?? '',
            '{{data_evento}}' => date('d/m/Y', strtotime($data['data_evento'] ?? '')),
            '{{tipo_evento}}' => $data['tipo_evento'] ?? '',
            '{{menu}}' => $data['tipo_menu'] ?? '',
            '{{numero_invitati}}' => $data['numero_invitati'] ?? '',
            '{{importo}}' => number_format($data['importo_preventivo'] ?? 0, 2, ',', '.') . ' €',
            '{{acconto}}' => number_format($data['acconto'] ?? 0, 2, ',', '.') . ' €',
            '{{preventivo_id}}' => $data['preventivo_id'] ?? ''
        );
        
        return str_replace(array_keys($replacements), array_values($replacements), $text);
    }
    
    /**
     * ============================================================================
     * METODI ESISTENTI (NON MODIFICATI)
     * ============================================================================
     */
    
    private function verify_nonce() {
        $nonce_fields = array('disco747_nonce', 'disco747_preventivo_nonce', '_wpnonce');
        
        foreach ($nonce_fields as $field) {
            if (isset($_POST[$field])) {
                if (wp_verify_nonce($_POST[$field], 'disco747_preventivo_nonce')) {
                    return true;
                }
                if (wp_verify_nonce($_POST[$field], 'disco747_nonce')) {
                    return true;
                }
            }
        }
        
        return false;
    }
    
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
    
    private function validate_form_data($post_data) {
        $data = array();
        
        $nome = sanitize_text_field($post_data['nome_referente'] ?? '');
        $cognome = sanitize_text_field($post_data['cognome_referente'] ?? '');
        $data['nome_cliente'] = trim($nome . ' ' . $cognome);
        $data['nome_referente'] = $nome;
        $data['cognome_referente'] = $cognome;
        
        $data['telefono'] = sanitize_text_field($post_data['cellulare'] ?? '');
        $data['email'] = sanitize_email($post_data['mail'] ?? '');
        $data['cellulare'] = $data['telefono'];
        $data['mail'] = $data['email'];
        
        $data['data_evento'] = sanitize_text_field($post_data['data_evento'] ?? '');
        $data['tipo_evento'] = sanitize_text_field($post_data['tipo_evento'] ?? '');
        $data['tipo_menu'] = sanitize_text_field($post_data['tipo_menu'] ?? 'Menu 7');
        $data['numero_invitati'] = intval($post_data['numero_invitati'] ?? 50);
        
        $data['orario_inizio'] = sanitize_text_field($post_data['orario_inizio'] ?? '20:30');
        $data['orario_fine'] = sanitize_text_field($post_data['orario_fine'] ?? '01:30');
        
        $data['omaggio1'] = sanitize_text_field($post_data['omaggio1'] ?? '');
        $data['omaggio2'] = sanitize_text_field($post_data['omaggio2'] ?? '');
        $data['omaggio3'] = sanitize_text_field($post_data['omaggio3'] ?? '');
        
        $data['extra1'] = sanitize_text_field($post_data['extra1'] ?? '');
        $data['extra1_importo'] = floatval($post_data['extra1_importo'] ?? 0);
        $data['extra2'] = sanitize_text_field($post_data['extra2'] ?? '');
        $data['extra2_importo'] = floatval($post_data['extra2_importo'] ?? 0);
        $data['extra3'] = sanitize_text_field($post_data['extra3'] ?? '');
        $data['extra3_importo'] = floatval($post_data['extra3_importo'] ?? 0);
        
        $data['importo_base'] = floatval($post_data['importo_base'] ?? 0);
        $data['importo_totale'] = floatval($post_data['importo_totale'] ?? 0);
        $data['importo_preventivo'] = $data['importo_totale'];
        $data['acconto'] = floatval($post_data['acconto'] ?? 0);
        
        $data['note_aggiuntive'] = sanitize_textarea_field($post_data['note_aggiuntive'] ?? '');
        $data['note_interne'] = sanitize_textarea_field($post_data['note_interne'] ?? '');
        
        $data['stato'] = 'attivo';
        $data['send_mode'] = 'none';
        
        return $data;
    }
    
    private function generate_progressive_id() {
        global $wpdb;
        $max_id = $wpdb->get_var("SELECT MAX(CAST(preventivo_id AS UNSIGNED)) FROM {$this->table_name}");
        return str_pad(intval($max_id) + 1, 3, '0', STR_PAD_LEFT);
    }
    
    private function generate_filename($data) {
        $date = date_create($data['data_evento']);
        $day = $date->format('d');
        $month = $date->format('m');
        
        $tipo_evento = substr(preg_replace('/[^a-zA-Z0-9\s]/', '', $data['tipo_evento']), 0, 30);
        
        $prefix = '';
        if (isset($data['acconto']) && floatval($data['acconto']) > 0) {
            $prefix = 'CONF ';
        }
        
        $menu_number = str_replace('Menu ', '', $data['tipo_menu']);
        
        return $prefix . $day . '_' . $month . ' ' . $tipo_evento . ' (Menu ' . $menu_number . ')';
    }
    
    private function save_to_database($data) {
        global $wpdb;
        
        $insert_data = array(
            'preventivo_id' => $data['preventivo_id'],
            'nome_cliente' => $data['nome_cliente'],
            'email' => $data['email'],
            'telefono' => $data['telefono'],
            'data_evento' => $data['data_evento'],
            'tipo_evento' => $data['tipo_evento'],
            'tipo_menu' => $data['tipo_menu'],
            'numero_invitati' => $data['numero_invitati'],
            'importo_preventivo' => $data['importo_preventivo'],
            'acconto' => $data['acconto'],
            'stato' => $data['stato'],
            'excel_path' => $data['excel_path'] ?? '',
            'pdf_path' => $data['pdf_path'] ?? '',
            'cloud_url' => $data['cloud_url'] ?? '',
            'created_by' => $data['created_by'] ?? get_current_user_id(),
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql')
        );
        
        $wpdb->insert($this->table_name, $insert_data);
        
        return $wpdb->insert_id;
    }
    
    public function cleanup_temp_files() {
        $upload_dir = wp_upload_dir();
        $preventivi_dir = $upload_dir['basedir'] . '/preventivi/';
        
        if (!is_dir($preventivi_dir)) {
            return;
        }
        
        $files = glob($preventivi_dir . '*');
        $count = 0;
        
        foreach ($files as $file) {
            if (is_file($file) && (time() - filemtime($file)) > 86400) {
                if (unlink($file)) {
                    $count++;
                }
            }
        }
        
        if ($count > 0) {
            $this->log('[Forms] Cleanup: ' . $count . ' file temporanei eliminati');
        }
    }
    
    private function log($message, $level = 'INFO') {
        if ($this->log_enabled && function_exists('error_log')) {
            $timestamp = date('Y-m-d H:i:s');
            error_log("[{$timestamp}] [{$level}] {$message}");
        }
    }
}