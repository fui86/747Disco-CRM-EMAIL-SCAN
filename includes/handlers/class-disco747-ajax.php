<?php
/**
 * Classe per gestione richieste AJAX - 747 Disco CRM
 * VERSIONE 11.6.2 con Batch Scan endpoint
 *
 * @package    Disco747_CRM
 * @subpackage Handlers
 * @version    11.6.2-BATCH-SCAN
 * @author     747 Disco Team
 */

namespace Disco747_CRM\Handlers;

if (!defined('ABSPATH')) {
    exit('Accesso diretto non consentito');
}

class Disco747_Ajax {

    /**
     * Componenti core
     */
    private $config;
    private $database;
    private $auth;
    private $storage_manager;
    private $pdf_generator;
    private $excel_generator;
    private $googledrive_sync;
    
    /**
     * Flag debug
     */
    private $debug_mode = true;

    /**
     * Costruttore
     */
    public function __construct() {
        $this->load_dependencies();
        $this->register_ajax_hooks();
        $this->log('AJAX Handler inizializzato con endpoint batch scan');
    }

    /**
     * Carica dipendenze
     */
    private function load_dependencies() {
        $disco747_crm = disco747_crm();
        
        $this->config = $disco747_crm->get_config();
        $this->database = $disco747_crm->get_database();
        $this->auth = $disco747_crm->get_auth();
        $this->storage_manager = $disco747_crm->get_storage_manager();
        $this->pdf_generator = $disco747_crm->get_pdf();
        $this->excel_generator = $disco747_crm->get_excel();
        
        // Carica GoogleDrive Sync se disponibile
        if ($disco747_crm && method_exists($disco747_crm, 'get_googledrive_sync')) {
            $this->googledrive_sync = $disco747_crm->get_googledrive_sync();
        }
        
        // Crea tabella log email se non esiste
        $this->create_email_log_table();
    }
    
    /**
     * Crea tabella per log invio email
     */
    private function create_email_log_table() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'disco747_email_log';
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            preventivo_id bigint(20) NOT NULL,
            email_to varchar(255) NOT NULL,
            subject varchar(500) DEFAULT NULL,
            template_id varchar(50) DEFAULT NULL,
            sent_at datetime NOT NULL,
            status varchar(20) NOT NULL,
            error_message text DEFAULT NULL,
            PRIMARY KEY (id),
            KEY preventivo_id (preventivo_id),
            KEY sent_at (sent_at)
        ) {$charset_collate};";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        
        $this->log('Tabella email_log verificata/creata');
    }

    /**
     * Registra hook AJAX
     */
    private function register_ajax_hooks() {
        
        // Storage e OAuth
        add_action('wp_ajax_disco747_test_storage', array($this, 'handle_storage_test'));
        add_action('wp_ajax_disco747_update_setting', array($this, 'handle_update_setting'));
        
        // ❌ DISABILITATO: Batch scan Excel ora gestito da ajax-handlers.php con sistema ottimizzato
        // add_action('wp_ajax_disco747_scan_drive_batch', array($this, 'handle_batch_scan'));
        
        // Template messaggi
        add_action('wp_ajax_disco747_get_templates', array($this, 'handle_get_templates'));
        add_action('wp_ajax_disco747_compile_template', array($this, 'handle_compile_template'));
        add_action('wp_ajax_disco747_send_email_template', array($this, 'handle_send_email_template'));
        add_action('wp_ajax_disco747_send_whatsapp_template', array($this, 'handle_send_whatsapp_template'));
        
        $this->log('Hook AJAX registrati (templates + send email + whatsapp) - batch scan disabilitato, usa ajax-handlers.php');
    }

    /**
     * Ã¢Å“â€¦ NUOVO: Handler per batch scan di file Excel su Google Drive
     */
    /**
     * Handler batch scan deprecato - usa ajax-handlers.php
     */
    public function handle_batch_scan() {
        error_log('[747Disco-AJAX] Handler batch_scan LEGACY chiamato - usa ajax-handlers.php invece');
        wp_send_json_error(array(
            'message' => 'Handler deprecato. Usa action: batch_scan_excel'
        ));
    }


    /**
     * Test storage connection
     */
    public function handle_storage_test() {
        check_ajax_referer('disco747_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permessi insufficienti');
        }

        try {
            $storage_type = get_option('disco747_storage_type', 'googledrive');
            
            if ($storage_type === 'googledrive') {
                $result = $this->test_googledrive_connection();
            } elseif ($storage_type === 'dropbox') {
                $result = $this->test_dropbox_connection();
            } else {
                $result = array(
                    'success' => false,
                    'message' => 'Tipo storage non riconosciuto'
                );
            }
            
            wp_send_json($result);
            
        } catch (\Exception $e) {
            wp_send_json_error('Errore test: ' . $e->getMessage());
        }
    }

    /**
     * Test connessione Google Drive
     */
    private function test_googledrive_connection() {
        if (!$this->storage_manager) {
            return array(
                'success' => false,
                'message' => 'Storage manager non disponibile'
            );
        }

        try {
            $credentials = get_option('disco747_gd_credentials', array());
            
            if (empty($credentials['client_id']) || empty($credentials['refresh_token'])) {
                return array(
                    'success' => false,
                    'message' => 'Credenziali Google Drive incomplete'
                );
            }
            
            return array(
                'success' => true,
                'message' => 'Connessione Google Drive OK',
                'details' => array(
                    'type' => 'Google Drive',
                    'configured' => true
                )
            );
            
        } catch (\Exception $e) {
            return array(
                'success' => false,
                'message' => 'Errore: ' . $e->getMessage()
            );
        }
    }

    /**
     * Test connessione Dropbox
     */
    private function test_dropbox_connection() {
        try {
            $access_token = get_option('disco747_dropbox_access_token', '');
            
            if (empty($access_token)) {
                return array(
                    'success' => false,
                    'message' => 'Token Dropbox mancante'
                );
            }
            
            return array(
                'success' => true,
                'message' => 'Connessione Dropbox OK',
                'details' => array(
                    'type' => 'Dropbox',
                    'configured' => true
                )
            );
            
        } catch (\Exception $e) {
            return array(
                'success' => false,
                'message' => 'Errore: ' . $e->getMessage()
            );
        }
    }

    /**
     * Aggiorna impostazione singola
     */
    public function handle_update_setting() {
        check_ajax_referer('disco747_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permessi insufficienti');
        }

        try {
            $setting_key = sanitize_text_field($_POST['setting_key'] ?? '');
            $setting_value = sanitize_text_field($_POST['setting_value'] ?? '');
            
            if (empty($setting_key)) {
                wp_send_json_error('Chiave impostazione mancante');
            }
            
            update_option($setting_key, $setting_value);
            
            wp_send_json_success(array(
                'message' => 'Impostazione aggiornata',
                'key' => $setting_key,
                'value' => $setting_value
            ));
            
        } catch (\Exception $e) {
            wp_send_json_error('Errore: ' . $e->getMessage());
        }
    }

    /**
     * Handler: Recupera tutti i template attivi
     */
    public function handle_get_templates() {
        try {
            // Verifica nonce
            if (!check_ajax_referer('disco747_admin_nonce', 'nonce', false)) {
                wp_send_json_error('Nonce non valido');
                return;
            }
            
            // Carica tutti i template (vecchi + nuovi)
            $templates = array(
                'email' => array(),
                'whatsapp' => array()
            );
            
            // Numero massimo template
            $max_templates = get_option('disco747_max_templates', 5);
            
            // Template vecchi (1,2,3...)
            for ($i = 1; $i <= $max_templates; $i++) {
                $email_enabled = get_option('disco747_email_enabled_' . $i, 1);
                $whatsapp_enabled = get_option('disco747_whatsapp_enabled_' . $i, 1);
                
                $email_name = get_option('disco747_email_name_' . $i, 'Template Email ' . $i);
                $whatsapp_name = get_option('disco747_whatsapp_name_' . $i, 'Template WhatsApp ' . $i);
                
                if ($email_enabled) {
                    $templates['email'][] = array(
                        'id' => 'email_' . $i,
                        'name' => $email_name,
                        'enabled' => true,
                        'trigger' => 'manual'
                    );
                }
                
                if ($whatsapp_enabled) {
                    $templates['whatsapp'][] = array(
                        'id' => 'whatsapp_' . $i,
                        'name' => $whatsapp_name,
                        'enabled' => true,
                        'trigger' => 'manual'
                    );
                }
            }
            
            $this->log('Template caricati: ' . count($templates['email']) . ' email, ' . count($templates['whatsapp']) . ' whatsapp');
            
            wp_send_json_success($templates);
            
        } catch (\Exception $e) {
            $this->log('Errore get_templates: ' . $e->getMessage(), 'ERROR');
            wp_send_json_error('Errore: ' . $e->getMessage());
        }
    }

    /**
     * Handler: Compila un template con i dati del preventivo
     */
    public function handle_compile_template() {
        try {
            // Verifica nonce
            if (!check_ajax_referer('disco747_admin_nonce', 'nonce', false)) {
                wp_send_json_error('Nonce non valido');
                return;
            }
            
            $template_id = sanitize_text_field($_POST['template_id'] ?? '');
            $preventivo_id = intval($_POST['preventivo_id'] ?? 0);
            
            if (empty($template_id) || empty($preventivo_id)) {
                wp_send_json_error('Parametri mancanti');
                return;
            }
            
            // Carica dati preventivo
            global $wpdb;
            $table = $wpdb->prefix . 'disco747_preventivi';
            
            $preventivo = $wpdb->get_row(
                $wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $preventivo_id),
                ARRAY_A
            );
            
            if (!$preventivo) {
                wp_send_json_error('Preventivo non trovato');
                return;
            }
            
            // Determina tipo template e carica contenuto
            if (strpos($template_id, 'email_') === 0) {
                $num = str_replace('email_', '', $template_id);
                $subject = get_option('disco747_email_subject_' . $num, '');
                $body = get_option('disco747_email_template_' . $num, '');
                $type = 'email';
            } elseif (strpos($template_id, 'whatsapp_') === 0) {
                $num = str_replace('whatsapp_', '', $template_id);
                $subject = '';
                $body = get_option('disco747_whatsapp_template_' . $num, '');
                $type = 'whatsapp';
            } else {
                wp_send_json_error('Template non riconosciuto');
                return;
            }
            
            // Prepara dati per sostituzione
            $data = array(
                'nome' => $preventivo['nome_referente'] ?? explode(' ', $preventivo['nome_cliente'])[0] ?? '',
                'cognome' => $preventivo['cognome_referente'] ?? '',
                'nome_completo' => $preventivo['nome_cliente'],
                'email' => $preventivo['email'] ?? '',
                'telefono' => $preventivo['telefono'] ?? '',
                'data_evento' => date('d/m/Y', strtotime($preventivo['data_evento'])),
                'tipo_evento' => $preventivo['tipo_evento'],
                'numero_invitati' => $preventivo['numero_invitati'],
                'orario_inizio' => $preventivo['orario_inizio'] ?? '20:30',
                'orario_fine' => $preventivo['orario_fine'] ?? '01:30',
                'menu' => $preventivo['tipo_menu'],
                'tipo_menu' => $preventivo['tipo_menu'],
                'importo_totale' => number_format($preventivo['importo_totale'], 2, ',', '.') . 'â‚¬',
                'importo_preventivo' => number_format($preventivo['importo_preventivo'] ?? $preventivo['importo_totale'], 2, ',', '.') . 'â‚¬',
                'totale' => number_format($preventivo['importo_preventivo'] ?? $preventivo['importo_totale'], 2, ',', '.') . 'â‚¬',
                'acconto' => number_format($preventivo['acconto'], 2, ',', '.') . 'â‚¬',
                'saldo' => number_format($preventivo['saldo'] ?? 0, 2, ',', '.') . 'â‚¬',
                'extra1' => $preventivo['extra1'] ?? '',
                'extra2' => $preventivo['extra2'] ?? '',
                'extra3' => $preventivo['extra3'] ?? '',
                'omaggio1' => $preventivo['omaggio1'] ?? '',
                'omaggio2' => $preventivo['omaggio2'] ?? '',
                'omaggio3' => $preventivo['omaggio3'] ?? '',
                'preventivo_id' => $preventivo['preventivo_id'] ?? 'PREV' . str_pad($preventivo['id'], 3, '0', STR_PAD_LEFT),
                'stato' => $preventivo['stato']
            );
            
            // Sostituisci placeholder
            foreach ($data as $key => $value) {
                $subject = str_replace('{{' . $key . '}}', $value, $subject);
                $body = str_replace('{{' . $key . '}}', $value, $body);
            }
            
            $this->log('Template compilato: ' . $template_id . ' per preventivo #' . $preventivo_id);
            
            wp_send_json_success(array(
                'subject' => $subject,
                'body' => $body,
                'type' => $type
            ));
            
        } catch (\Exception $e) {
            $this->log('Errore compile_template: ' . $e->getMessage(), 'ERROR');
            wp_send_json_error('Errore: ' . $e->getMessage());
        }
    }

    /**
     * Handler: Invia email con template
     */
    public function handle_send_email_template() {
        try {
            // Verifica nonce
            if (!check_ajax_referer('disco747_send_email', 'nonce', false)) {
                wp_send_json_error('Nonce non valido');
                return;
            }
            
            $preventivo_id = intval($_POST['preventivo_id'] ?? 0);
            $template_id = sanitize_text_field($_POST['template_id'] ?? '');
            $attach_pdf = $_POST['attach_pdf'] === '1';
            
            if (empty($preventivo_id) || empty($template_id)) {
                wp_send_json_error('Parametri mancanti');
                return;
            }
            
            $this->log("Invio email - Preventivo: $preventivo_id, Template: $template_id, PDF: " . ($attach_pdf ? 'Si' : 'No'));
            
            // Carica dati preventivo
            global $wpdb;
            $table = $wpdb->prefix . 'disco747_preventivi';
            
            $preventivo = $wpdb->get_row(
                $wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $preventivo_id),
                ARRAY_A
            );
            
            if (!$preventivo) {
                wp_send_json_error('Preventivo non trovato');
                return;
            }
            
            // Verifica che ci sia un'email
            $to_email = $preventivo['email'];
            if (empty($to_email) || !is_email($to_email)) {
                wp_send_json_error('Email cliente non valida');
                return;
            }
            
            // Carica template
            if (strpos($template_id, 'email_') === 0) {
                $num = str_replace('email_', '', $template_id);
                $subject = get_option('disco747_email_subject_' . $num, '');
                $body = get_option('disco747_email_template_' . $num, '');
            } else {
                wp_send_json_error('Template email non valido');
                return;
            }
            
            if (empty($subject) || empty($body)) {
                wp_send_json_error('Template vuoto');
                return;
            }
            
            // Prepara dati per sostituzione
            $data = array(
                'nome' => $preventivo['nome_referente'] ?? explode(' ', $preventivo['nome_cliente'])[0] ?? '',
                'cognome' => $preventivo['cognome_referente'] ?? '',
                'nome_completo' => $preventivo['nome_cliente'],
                'email' => $preventivo['email'],
                'telefono' => $preventivo['telefono'] ?? '',
                'data_evento' => date('d/m/Y', strtotime($preventivo['data_evento'])),
                'tipo_evento' => $preventivo['tipo_evento'],
                'numero_invitati' => $preventivo['numero_invitati'],
                'orario_inizio' => $preventivo['orario_inizio'] ?? '20:30',
                'orario_fine' => $preventivo['orario_fine'] ?? '01:30',
                'menu' => $preventivo['tipo_menu'],
                'tipo_menu' => $preventivo['tipo_menu'],
                'importo_totale' => number_format($preventivo['importo_totale'], 2, ',', '.') . 'â‚¬',
                'importo_preventivo' => number_format($preventivo['importo_preventivo'] ?? $preventivo['importo_totale'], 2, ',', '.') . 'â‚¬',
                'totale' => number_format($preventivo['importo_preventivo'] ?? $preventivo['importo_totale'], 2, ',', '.') . 'â‚¬',
                'acconto' => number_format($preventivo['acconto'], 2, ',', '.') . 'â‚¬',
                'saldo' => number_format($preventivo['saldo'] ?? 0, 2, ',', '.') . 'â‚¬',
                'extra1' => $preventivo['extra1'] ?? '',
                'extra2' => $preventivo['extra2'] ?? '',
                'extra3' => $preventivo['extra3'] ?? '',
                'omaggio1' => $preventivo['omaggio1'] ?? '',
                'omaggio2' => $preventivo['omaggio2'] ?? '',
                'omaggio3' => $preventivo['omaggio3'] ?? '',
                'preventivo_id' => $preventivo['preventivo_id'] ?? 'PREV' . str_pad($preventivo['id'], 3, '0', STR_PAD_LEFT),
                'stato' => $preventivo['stato']
            );
            
            // Sostituisci placeholder
            foreach ($data as $key => $value) {
                $subject = str_replace('{{' . $key . '}}', $value, $subject);
                $body = str_replace('{{' . $key . '}}', $value, $body);
            }
            
            // Headers email - Compatibile con WP Mail SMTP
            $headers = array(
                'Content-Type: text/html; charset=UTF-8'
            );
            
            // From name e email (WP Mail SMTP li gestisce automaticamente se configurati)
            // Ma possiamo forzarli se necessario
            $from_email = get_option('disco747_from_email', 'eventi@747disco.it');
            $from_name = get_option('disco747_from_name', '747 Disco');
            
            // Solo se WP Mail SMTP non Ã¨ attivo, aggiungi From manualmente
            if (!class_exists('WPMailSMTP\Options')) {
                $headers[] = 'From: ' . $from_name . ' <' . $from_email . '>';
            }
            
            $headers[] = 'Reply-To: ' . $from_email;
            
            // Cc a info@747disco.it (se configurato)
            $cc_email = get_option('disco747_cc_email', 'info@747disco.it');
            if (!empty($cc_email)) {
                $headers[] = 'Cc: ' . $cc_email;
            }
            
            // Log configurazione
            $this->log("Headers preparati: From=$from_name <$from_email>, Cc=$cc_email");
            
            // Verifica WP Mail SMTP
            $smtp_active = false;
            if (class_exists('WPMailSMTP\Options')) {
                $smtp_active = true;
                $mailer = \WPMailSMTP\Options::init()->get('mail', 'mailer');
                $this->log("âœ… WP Mail SMTP attivo - Mailer: $mailer");
            } elseif (function_exists('wp_mail_smtp')) {
                $smtp_active = true;
                $this->log("âœ… WP Mail SMTP attivo (versione legacy)");
            } else {
                $this->log("âš ï¸  WP Mail SMTP non rilevato, uso wp_mail() standard", 'WARNING');
            }
            
            // Allegati
            $attachments = array();
            if ($attach_pdf) {
                $pdf_path = WP_CONTENT_DIR . '/uploads/preventivi/preventivo_' . $preventivo['id'] . '.pdf';
                if (file_exists($pdf_path)) {
                    $attachments[] = $pdf_path;
                    $this->log("PDF allegato: $pdf_path");
                } else {
                    $this->log("PDF non trovato: $pdf_path", 'WARNING');
                }
            }
            
            // Log dettagli invio
            $this->log("Invio a: $to_email | Oggetto: $subject | Allegati: " . count($attachments));
            
            // Hook per debug SMTP (opzionale)
            add_action('wp_mail_failed', function($error) {
                $this->log("wp_mail fallito: " . $error->get_error_message(), 'ERROR');
            });
            
            // Invia email
            $sent = wp_mail($to_email, $subject, $body, $headers, $attachments);
            
            if ($sent) {
                $this->log("âœ… Email inviata con successo a $to_email");
                
                // Salva log invio nel database (se la tabella esiste)
                global $wpdb;
                $log_table = $wpdb->prefix . 'disco747_email_log';
                
                // Verifica se la tabella esiste
                if ($wpdb->get_var("SHOW TABLES LIKE '$log_table'") == $log_table) {
                    $wpdb->insert(
                        $log_table,
                        array(
                            'preventivo_id' => $preventivo['id'],
                            'email_to' => $to_email,
                            'subject' => $subject,
                            'template_id' => $template_id,
                            'sent_at' => current_time('mysql'),
                            'status' => 'success'
                        ),
                        array('%d', '%s', '%s', '%s', '%s', '%s')
                    );
                } else {
                    $this->log("Tabella email_log non trovata, skip logging DB", 'WARNING');
                }
                
                wp_send_json_success(array(
                    'message' => 'âœ… Email inviata con successo!',
                    'to' => $to_email,
                    'subject' => $subject
                ));
            } else {
                $this->log("âŒ Errore invio email a $to_email", 'ERROR');
                
                // Salva errore nel log
                global $wpdb;
                $wpdb->insert(
                    $wpdb->prefix . 'disco747_email_log',
                    array(
                        'preventivo_id' => $preventivo['id'],
                        'email_to' => $to_email,
                        'subject' => $subject,
                        'template_id' => $template_id,
                        'sent_at' => current_time('mysql'),
                        'status' => 'failed'
                    ),
                    array('%d', '%s', '%s', '%s', '%s', '%s')
                );
                
                wp_send_json_error('Impossibile inviare email. Verifica configurazione WP Mail SMTP in Impostazioni â†’ Email.');
            }
            
        } catch (\Exception $e) {
            $this->log('Errore send_email: ' . $e->getMessage(), 'ERROR');
            wp_send_json_error('Errore: ' . $e->getMessage());
        }
    }

    /**
     * Handler: Invia WhatsApp con template
     */
    public function handle_send_whatsapp_template() {
        try {
            // Verifica nonce
            if (!check_ajax_referer('disco747_send_whatsapp', 'nonce', false)) {
                wp_send_json_error('Nonce non valido');
                return;
            }
            
            $preventivo_id = intval($_POST['preventivo_id'] ?? 0);
            $template_id = sanitize_text_field($_POST['template_id'] ?? '');
            
            if (empty($preventivo_id) || empty($template_id)) {
                wp_send_json_error('Parametri mancanti');
                return;
            }
            
            $this->log("Invio WhatsApp - Preventivo: $preventivo_id, Template: $template_id");
            
            // Carica dati preventivo
            global $wpdb;
            $table = $wpdb->prefix . 'disco747_preventivi';
            
            $preventivo = $wpdb->get_row(
                $wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $preventivo_id),
                ARRAY_A
            );
            
            if (!$preventivo) {
                wp_send_json_error('Preventivo non trovato');
                return;
            }
            
            // Verifica che ci sia un telefono
            $telefono = $preventivo['telefono'];
            if (empty($telefono)) {
                wp_send_json_error('Numero di telefono non disponibile');
                return;
            }
            
            // Carica template WhatsApp
            if (strpos($template_id, 'whatsapp_') === 0) {
                $num = str_replace('whatsapp_', '', $template_id);
                $body = get_option('disco747_whatsapp_template_' . $num, '');
            } else {
                wp_send_json_error('Template WhatsApp non valido');
                return;
            }
            
            if (empty($body)) {
                wp_send_json_error('Template vuoto');
                return;
            }
            
            // Prepara dati per sostituzione
            $data = array(
                'nome' => $preventivo['nome_referente'] ?? explode(' ', $preventivo['nome_cliente'])[0] ?? '',
                'cognome' => $preventivo['cognome_referente'] ?? '',
                'nome_completo' => $preventivo['nome_cliente'],
                'email' => $preventivo['email'] ?? '',
                'telefono' => $telefono,
                'data_evento' => date('d/m/Y', strtotime($preventivo['data_evento'])),
                'tipo_evento' => $preventivo['tipo_evento'],
                'numero_invitati' => $preventivo['numero_invitati'],
                'orario_inizio' => $preventivo['orario_inizio'] ?? '20:30',
                'orario_fine' => $preventivo['orario_fine'] ?? '01:30',
                'menu' => $preventivo['tipo_menu'],
                'tipo_menu' => $preventivo['tipo_menu'],
                'importo_totale' => number_format($preventivo['importo_totale'], 2, ',', '.') . '€',
                'importo_preventivo' => number_format($preventivo['importo_preventivo'] ?? $preventivo['importo_totale'], 2, ',', '.') . '€',
                'totale' => number_format($preventivo['importo_preventivo'] ?? $preventivo['importo_totale'], 2, ',', '.') . '€',
                'acconto' => number_format($preventivo['acconto'], 2, ',', '.') . '€',
                'saldo' => number_format($preventivo['saldo'] ?? 0, 2, ',', '.') . '€',
                'extra1' => $preventivo['extra1'] ?? '',
                'extra2' => $preventivo['extra2'] ?? '',
                'extra3' => $preventivo['extra3'] ?? '',
                'omaggio1' => $preventivo['omaggio1'] ?? '',
                'omaggio2' => $preventivo['omaggio2'] ?? '',
                'omaggio3' => $preventivo['omaggio3'] ?? '',
                'preventivo_id' => $preventivo['preventivo_id'] ?? 'PREV' . str_pad($preventivo['id'], 3, '0', STR_PAD_LEFT),
                'stato' => $preventivo['stato']
            );
            
            // Sostituisci placeholder
            foreach ($data as $key => $value) {
                $body = str_replace('{{' . $key . '}}', $value, $body);
            }
            
            // Normalizza numero di telefono per WhatsApp (rimuovi spazi, trattini, parentesi)
            $telefono_clean = preg_replace('/[^0-9+]/', '', $telefono);
            
            // Se non inizia con +, aggiungi +39 (Italia)
            if (substr($telefono_clean, 0, 1) !== '+') {
                $telefono_clean = '+39' . $telefono_clean;
            }
            
            // Codifica il messaggio per URL
            $message_encoded = urlencode($body);
            
            // Genera URL WhatsApp
            $whatsapp_url = "https://wa.me/{$telefono_clean}?text={$message_encoded}";
            
            $this->log("✅ URL WhatsApp generato per: $telefono_clean");
            
            wp_send_json_success(array(
                'message' => '✅ Link WhatsApp generato!',
                'whatsapp_url' => $whatsapp_url,
                'telefono' => $telefono_clean
            ));
            
        } catch (\Exception $e) {
            $this->log('Errore send_whatsapp: ' . $e->getMessage(), 'ERROR');
            wp_send_json_error('Errore: ' . $e->getMessage());
        }
    }

    /**
     * Log interno
     */
    private function log($message, $level = 'INFO') {
        if ($this->debug_mode && function_exists('error_log')) {
            $timestamp = date('Y-m-d H:i:s');
            error_log("[{$timestamp}] [747Disco-AJAX] [{$level}] {$message}");
        }
    }
}