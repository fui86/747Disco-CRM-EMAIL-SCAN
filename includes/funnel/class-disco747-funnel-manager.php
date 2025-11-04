<?php
/**
 * Funnel Manager - 747 Disco CRM
 * Gestisce la logica del funnel marketing (pre-conferma e pre-evento)
 * 
 * @package    Disco747_CRM
 * @subpackage Funnel
 * @version    1.0.0
 */

namespace Disco747_CRM\Funnel;

if (!defined('ABSPATH')) {
    exit('Accesso diretto non consentito');
}

class Disco747_Funnel_Manager {
    
    private $sequences_table;
    private $tracking_table;
    private $preventivi_table;
    
    public function __construct() {
        global $wpdb;
        
        $this->sequences_table = $wpdb->prefix . 'disco747_funnel_sequences';
        $this->tracking_table = $wpdb->prefix . 'disco747_funnel_tracking';
        $this->preventivi_table = $wpdb->prefix . 'disco747_preventivi';
    }
    
    /**
     * Avvia un funnel per un preventivo
     * 
     * @param int $preventivo_id ID del preventivo
     * @param string $funnel_type Tipo (pre_conferma | pre_evento)
     * @return bool|int Tracking ID o false
     */
    public function start_funnel($preventivo_id, $funnel_type = 'pre_conferma') {
        global $wpdb;
        
        // Verifica se esiste già un tracking attivo
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->tracking_table} 
             WHERE preventivo_id = %d AND funnel_type = %s AND status = 'active'",
            $preventivo_id,
            $funnel_type
        ));
        
        if ($existing) {
            error_log("[747Disco-Funnel] Funnel già attivo per preventivo #{$preventivo_id}");
            return false;
        }
        
        // Calcola quando inviare il primo step (giorni_offset del primo step)
        $first_step = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->sequences_table} 
             WHERE funnel_type = %s AND active = 1 
             ORDER BY step_number ASC LIMIT 1",
            $funnel_type
        ));
        
        if (!$first_step) {
            error_log("[747Disco-Funnel] Nessuna sequenza attiva trovata per {$funnel_type}");
            return false;
        }
        
        // Calcola data+orario invio
        $send_time = $first_step->send_time ?? '09:00:00';
        
        // Per funnel pre-evento usa la data evento del preventivo
        if ($funnel_type === 'pre_evento') {
            $preventivo = $wpdb->get_row($wpdb->prepare(
                "SELECT data_evento FROM {$this->preventivi_table} WHERE id = %d",
                $preventivo_id
            ));
            
            if ($preventivo && $preventivo->data_evento) {
                // Calcola giorni prima dell'evento (es: -10 giorni)
                $next_send_at = date('Y-m-d', strtotime($preventivo->data_evento . ' ' . $first_step->days_offset . ' days')) . ' ' . $send_time;
            } else {
                $next_send_at = date('Y-m-d', strtotime("+{$first_step->days_offset} days")) . ' ' . $send_time;
            }
        } else {
            // Per funnel pre-conferma usa data corrente + offset
            $next_send_at = date('Y-m-d', strtotime("+{$first_step->days_offset} days")) . ' ' . $send_time;
        }
        
        // Crea tracking
        $inserted = $wpdb->insert(
            $this->tracking_table,
            array(
                'preventivo_id' => $preventivo_id,
                'funnel_type' => $funnel_type,
                'current_step' => 0,
                'status' => 'active',
                'started_at' => current_time('mysql'),
                'next_send_at' => $next_send_at,
                'emails_log' => json_encode(array()),
                'whatsapp_log' => json_encode(array())
            ),
            array('%d', '%s', '%d', '%s', '%s', '%s', '%s', '%s')
        );
        
        if ($inserted) {
            $tracking_id = $wpdb->insert_id;
            error_log("[747Disco-Funnel] ✅ Funnel {$funnel_type} avviato per preventivo #{$preventivo_id} (Tracking ID: {$tracking_id})");
            
            // Se il primo step è +0 giorni, invialo subito
            if ($first_step->days_offset == 0) {
                $this->send_next_step($tracking_id);
            }
            
            return $tracking_id;
        }
        
        return false;
    }
    
    /**
     * Invia il prossimo step del funnel
     * 
     * @param int $tracking_id ID tracking
     * @return bool Success
     */
    public function send_next_step($tracking_id) {
        global $wpdb;
        
        // Carica tracking
        $tracking = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->tracking_table} WHERE id = %d",
            $tracking_id
        ));
        
        if (!$tracking || $tracking->status !== 'active') {
            return false;
        }
        
        // Carica preventivo
        $preventivo = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->preventivi_table} WHERE id = %d",
            $tracking->preventivo_id
        ));
        
        if (!$preventivo) {
            error_log("[747Disco-Funnel] Preventivo #{$tracking->preventivo_id} non trovato");
            return false;
        }
        
        // Carica prossimo step
        $next_step_number = $tracking->current_step + 1;
        $step = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->sequences_table} 
             WHERE funnel_type = %s AND step_number = %d AND active = 1",
            $tracking->funnel_type,
            $next_step_number
        ));
        
        if (!$step) {
            // Nessuno step successivo = funnel completato
            $this->complete_funnel($tracking_id);
            return true;
        }
        
        // INVIO EMAIL AL CLIENTE
        $email_sent = false;
        if ($step->email_enabled && !empty($step->email_body)) {
            $email_sent = $this->send_email_to_customer($preventivo, $step);
        }
        
        // INVIO EMAIL NOTIFICA WHATSAPP A TE
        $whatsapp_notif_sent = false;
        if ($step->whatsapp_enabled && !empty($step->whatsapp_text)) {
            $whatsapp_notif_sent = $this->send_whatsapp_notification($preventivo, $step, $tracking_id);
        }
        
        // Aggiorna log
        $emails_log = json_decode($tracking->emails_log, true) ?: array();
        $whatsapp_log = json_decode($tracking->whatsapp_log, true) ?: array();
        
        $emails_log[] = array(
            'step' => $next_step_number,
            'sent_at' => current_time('mysql'),
            'success' => $email_sent,
            'subject' => $step->email_subject
        );
        
        if ($step->whatsapp_enabled) {
            $whatsapp_log[] = array(
                'step' => $next_step_number,
                'notification_sent_at' => current_time('mysql'),
                'success' => $whatsapp_notif_sent,
                'status' => 'pending_manual_send'
            );
        }
        
        // Calcola quando inviare il prossimo step
        $next_step_data = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->sequences_table} 
             WHERE funnel_type = %s AND step_number = %d AND active = 1",
            $tracking->funnel_type,
            $next_step_number + 1
        ));
        
        $next_send_at = null;
        if ($next_step_data) {
            $send_time = $next_step_data->send_time ?? '09:00:00';
            
            if ($tracking->funnel_type === 'pre_evento') {
                // Per pre-evento calcola dalla data evento
                $next_send_at = date('Y-m-d', strtotime($preventivo->data_evento . ' ' . $next_step_data->days_offset . ' days')) . ' ' . $send_time;
            } else {
                // Per pre-conferma calcola differenza giorni
                $days_diff = $next_step_data->days_offset - $step->days_offset;
                $next_send_at = date('Y-m-d', strtotime("+{$days_diff} days")) . ' ' . $send_time;
            }
        }
        
        // Aggiorna tracking
        $wpdb->update(
            $this->tracking_table,
            array(
                'current_step' => $next_step_number,
                'last_sent_at' => current_time('mysql'),
                'next_send_at' => $next_send_at,
                'emails_log' => json_encode($emails_log),
                'whatsapp_log' => json_encode($whatsapp_log)
            ),
            array('id' => $tracking_id),
            array('%d', '%s', '%s', '%s', '%s'),
            array('%d')
        );
        
        error_log("[747Disco-Funnel] ✅ Step {$next_step_number} inviato per tracking #{$tracking_id}");
        
        return true;
    }
    
    /**
     * Invia email al cliente
     */
    private function send_email_to_customer($preventivo, $step) {
        $to = $preventivo->email;
        
        if (empty($to)) {
            error_log("[747Disco-Funnel] Email cliente mancante per preventivo #{$preventivo->id}");
            return false;
        }
        
        $subject = $this->replace_variables($step->email_subject, $preventivo);
        $body = $this->replace_variables($step->email_body, $preventivo);
        
        // Headers
        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: 747 Disco <info@747disco.it>',
            'Reply-To: info@747disco.it'
        );
        
        // Converti newline in <br>
        $body_html = nl2br($body);
        
        $sent = wp_mail($to, $subject, $body_html, $headers);
        
        if ($sent) {
            error_log("[747Disco-Funnel] ✉️ Email inviata a {$to}");
        } else {
            error_log("[747Disco-Funnel] ❌ Errore invio email a {$to}");
        }
        
        return $sent;
    }
    
    /**
     * Invia email notifica WhatsApp a info@747disco.it
     */
    private function send_whatsapp_notification($preventivo, $step, $tracking_id) {
        $to = 'info@747disco.it';
        
        $telefono = $preventivo->telefono;
        
        if (empty($telefono)) {
            error_log("[747Disco-Funnel] Telefono mancante per preventivo #{$preventivo->id}");
            return false;
        }
        
        // Formatta numero WhatsApp (rimuovi spazi, trattini)
        $whatsapp_number = preg_replace('/[^0-9+]/', '', $telefono);
        if (substr($whatsapp_number, 0, 1) !== '+') {
            $whatsapp_number = '+39' . $whatsapp_number;
        }
        
        // Messaggio WhatsApp
        $whatsapp_message = $this->replace_variables($step->whatsapp_text, $preventivo);
        $whatsapp_url = 'https://wa.me/' . $whatsapp_number . '?text=' . urlencode($whatsapp_message);
        
        // Link per segnare come inviato
        $mark_sent_url = admin_url('admin.php?page=disco747-funnel&action=mark_whatsapp_sent&tracking_id=' . $tracking_id);
        
        $subject = "💬 Invia WhatsApp a {$preventivo->nome_cliente} - Preventivo #{$preventivo->id}";
        
        $body = "
        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
            <div style='background: linear-gradient(135deg, #25D366 0%, #128C7E 100%); padding: 20px; color: white; border-radius: 10px 10px 0 0;'>
                <h2 style='margin: 0;'>⚠️ È il momento di inviare il WhatsApp!</h2>
            </div>
            
            <div style='background: white; padding: 30px; border: 1px solid #e9ecef; border-top: none;'>
                <div style='background: #f8f9fa; padding: 15px; border-radius: 8px; margin-bottom: 20px;'>
                    <strong>Cliente:</strong> {$preventivo->nome_cliente}<br>
                    <strong>Preventivo:</strong> #{$preventivo->id}<br>
                    <strong>Telefono:</strong> {$telefono}<br>
                    <strong>Tipo Evento:</strong> {$preventivo->tipo_evento}<br>
                    <strong>Data Evento:</strong> " . date('d/m/Y', strtotime($preventivo->data_evento)) . "
                </div>
                
                <div style='text-align: center; margin: 30px 0;'>
                    <a href='{$whatsapp_url}' 
                       style='background: linear-gradient(135deg, #25D366 0%, #128C7E 100%); 
                              color: white; 
                              padding: 18px 40px; 
                              text-decoration: none; 
                              border-radius: 30px; 
                              font-weight: bold; 
                              font-size: 18px;
                              display: inline-block;
                              box-shadow: 0 4px 15px rgba(37, 211, 102, 0.3);'>
                        📱 INVIA WHATSAPP ORA
                    </a>
                </div>
                
                <div style='background: #e7f3ff; padding: 15px; border-radius: 8px; border-left: 4px solid #007bff; margin: 20px 0;'>
                    <strong style='color: #0056b3;'>📝 Preview messaggio:</strong><br><br>
                    <em style='color: #495057;'>" . nl2br(htmlspecialchars($whatsapp_message)) . "</em>
                </div>
                
                <hr style='border: none; border-top: 1px solid #e9ecef; margin: 30px 0;'>
                
                <div style='text-align: center;'>
                    <p style='color: #6c757d; font-size: 14px;'>✅ Dopo aver inviato il WhatsApp, clicca qui per segnarlo:</p>
                    <a href='{$mark_sent_url}' 
                       style='color: #28a745; text-decoration: none; font-weight: bold;'>
                        ✓ Segna come Inviato
                    </a>
                </div>
            </div>
            
            <div style='background: #f8f9fa; padding: 15px; text-align: center; font-size: 12px; color: #6c757d; border-radius: 0 0 10px 10px;'>
                <p style='margin: 0;'>Funnel Step: {$step->step_number} - {$step->step_name}</p>
            </div>
        </div>
        ";
        
        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: 747 Disco Funnel <noreply@747disco.it>'
        );
        
        $sent = wp_mail($to, $subject, $body, $headers);
        
        if ($sent) {
            error_log("[747Disco-Funnel] 📧 Notifica WhatsApp inviata a {$to}");
        } else {
            error_log("[747Disco-Funnel] ❌ Errore invio notifica WhatsApp");
        }
        
        return $sent;
    }
    
    /**
     * Sostituisce variabili nel testo
     */
    private function replace_variables($text, $preventivo) {
        $variables = array(
            '{{nome_referente}}' => $preventivo->nome_referente ?: $preventivo->nome_cliente,
            '{{cognome_referente}}' => $preventivo->cognome_referente ?: '',
            '{{nome_cliente}}' => $preventivo->nome_cliente,
            '{{tipo_evento}}' => $preventivo->tipo_evento,
            '{{data_evento}}' => date('d/m/Y', strtotime($preventivo->data_evento)),
            '{{numero_invitati}}' => $preventivo->numero_invitati,
            '{{tipo_menu}}' => $preventivo->tipo_menu,
            '{{importo_totale}}' => number_format($preventivo->importo_totale, 2, ',', '.'),
            '{{acconto}}' => number_format($preventivo->acconto, 2, ',', '.'),
            '{{telefono_sede}}' => '06 123456789', // Sostituisci con numero reale
            '{{email_sede}}' => 'info@747disco.it'
        );
        
        return str_replace(array_keys($variables), array_values($variables), $text);
    }
    
    /**
     * Completa un funnel
     */
    public function complete_funnel($tracking_id) {
        global $wpdb;
        
        $wpdb->update(
            $this->tracking_table,
            array(
                'status' => 'completed',
                'completed_at' => current_time('mysql'),
                'next_send_at' => null
            ),
            array('id' => $tracking_id),
            array('%s', '%s', '%s'),
            array('%d')
        );
        
        error_log("[747Disco-Funnel] ✅ Funnel completato (Tracking ID: {$tracking_id})");
        
        return true;
    }
    
    /**
     * Pausa un funnel
     */
    public function pause_funnel($tracking_id) {
        global $wpdb;
        
        $wpdb->update(
            $this->tracking_table,
            array('status' => 'paused'),
            array('id' => $tracking_id),
            array('%s'),
            array('%d')
        );
        
        error_log("[747Disco-Funnel] ⏸️ Funnel in pausa (Tracking ID: {$tracking_id})");
        
        return true;
    }
    
    /**
     * Riprendi un funnel in pausa
     */
    public function resume_funnel($tracking_id) {
        global $wpdb;
        
        $wpdb->update(
            $this->tracking_table,
            array('status' => 'active'),
            array('id' => $tracking_id),
            array('%s'),
            array('%d')
        );
        
        error_log("[747Disco-Funnel] ▶️ Funnel ripreso (Tracking ID: {$tracking_id})");
        
        return true;
    }
    
    /**
     * Stoppa un funnel definitivamente
     */
    public function stop_funnel($preventivo_id, $funnel_type) {
        global $wpdb;
        
        $wpdb->update(
            $this->tracking_table,
            array(
                'status' => 'stopped',
                'completed_at' => current_time('mysql'),
                'next_send_at' => null
            ),
            array(
                'preventivo_id' => $preventivo_id,
                'funnel_type' => $funnel_type
            ),
            array('%s', '%s', '%s'),
            array('%d', '%s')
        );
        
        error_log("[747Disco-Funnel] 🛑 Funnel stoppato per preventivo #{$preventivo_id}");
        
        return true;
    }
    
    /**
     * Ottieni tracking per preventivo
     */
    public function get_tracking($preventivo_id, $funnel_type) {
        global $wpdb;
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->tracking_table} 
             WHERE preventivo_id = %d AND funnel_type = %s 
             ORDER BY id DESC LIMIT 1",
            $preventivo_id,
            $funnel_type
        ));
    }
    
    /**
     * Ottieni tutti i tracking attivi
     */
    public function get_active_trackings($funnel_type = null) {
        global $wpdb;
        
        $sql = "SELECT t.*, p.nome_cliente, p.email, p.telefono, p.tipo_evento, p.data_evento
                FROM {$this->tracking_table} t
                LEFT JOIN {$this->preventivi_table} p ON t.preventivo_id = p.id
                WHERE t.status = 'active'";
        
        if ($funnel_type) {
            $sql .= $wpdb->prepare(" AND t.funnel_type = %s", $funnel_type);
        }
        
        $sql .= " ORDER BY t.next_send_at ASC";
        
        return $wpdb->get_results($sql);
    }
    
    /**
     * Ottieni tracking da inviare ORA
     */
    public function get_pending_sends() {
        global $wpdb;
        
        return $wpdb->get_results("
            SELECT t.*, p.nome_cliente, p.email, p.telefono
            FROM {$this->tracking_table} t
            LEFT JOIN {$this->preventivi_table} p ON t.preventivo_id = p.id
            WHERE t.status = 'active' 
              AND t.next_send_at IS NOT NULL
              AND t.next_send_at <= NOW()
            ORDER BY t.next_send_at ASC
        ");
    }
}
