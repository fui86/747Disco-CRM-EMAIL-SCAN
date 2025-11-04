<?php
/**
 * Email Handler Class - 747 Disco CRM
 * VERSIONE COMPLETA AGGIORNATA con template HTML professionale
 * 
 * @package    Disco747_CRM
 * @subpackage Communication
 * @since      11.7.3
 * @author     747 Disco Team
 */

namespace Disco747_CRM\Communication;

use Disco747_CRM\Core\Disco747_Config;
use Disco747_CRM\Generators\Disco747_Templates;
use Exception;

defined('ABSPATH') || exit;

class Disco747_Email {

    private $config;
    private $templates;
    private $smtp_config;
    private $debug_mode;
    private $delivery_log = array();

    public function __construct() {
        $this->config = Disco747_Config::get_instance();
        $this->debug_mode = $this->config->get('debug_mode', false);
        
        // Carica configurazione SMTP
        $this->smtp_config = array(
            'enabled' => $this->config->get('smtp_enabled', false),
            'host' => $this->config->get('smtp_host', ''),
            'port' => $this->config->get('smtp_port', 587),
            'username' => $this->config->get('smtp_username', ''),
            'password' => $this->config->get('smtp_password', ''),
            'encryption' => $this->config->get('smtp_encryption', 'tls'),
            'from_name' => $this->config->get('email_from_name', '747 Disco'),
            'from_email' => $this->config->get('email_from_address', 'info@747disco.it')
        );
        
        $this->setup_hooks();
        $this->log('Email Handler inizializzato');
    }

    /**
     * Setup hooks WordPress
     */
    private function setup_hooks() {
        if ($this->smtp_config['enabled']) {
            add_action('phpmailer_init', array($this, 'configure_smtp'));
        }
        add_action('wp_mail_failed', array($this, 'on_mail_failure'));
    }

    /**
     * ✅ METODO PRINCIPALE: Invia email preventivo con PDF allegato
     * 
     * @param array $preventivo_data Dati del preventivo
     * @param string $pdf_path Path del PDF da allegare (opzionale)
     * @param array $options Opzioni aggiuntive
     * @return bool Success
     */
    public function send_preventivo_email($preventivo_data, $pdf_path = null, $options = array()) {
        $this->log('Invio email preventivo per: ' . ($preventivo_data['nome_referente'] ?? 'N/A'));

        try {
            // Prepara dati email
            $email_data = $this->prepare_email_data($preventivo_data, $options);
            
            // Genera contenuto HTML dell'email
            $email_content = $this->generate_email_content($email_data);
            
            // Prepara allegati
            $attachments = array();
            if ($pdf_path && file_exists($pdf_path)) {
                $attachments[] = $pdf_path;
                $this->log('PDF allegato: ' . basename($pdf_path));
                $this->log('PDF path completo: ' . $pdf_path);
                $this->log('PDF dimensione: ' . filesize($pdf_path) . ' bytes');
            } else {
                if ($pdf_path) {
                    $this->log('ATTENZIONE: PDF path fornito ma file non esiste: ' . $pdf_path, 'WARNING');
                } else {
                    $this->log('Nessun PDF da allegare');
                }
            }
            
            // Invia email tramite wp_mail
            $this->log('Invio wp_mail con ' . count($attachments) . ' allegati');
            $sent = wp_mail(
                $email_data['recipient_email'],
                $email_data['subject'],
                $email_content,
                $this->get_email_headers(),
                $attachments
            );
            
            // Log risultato
            $this->log_delivery($email_data, $sent, $attachments);
            
            if ($sent) {
                $this->log('✅ Email inviata con successo a: ' . $email_data['recipient_email']);
            } else {
                throw new Exception('Errore invio email tramite wp_mail');
            }
            
            return $sent;
            
        } catch (Exception $e) {
            $this->log('❌ Errore invio email: ' . $e->getMessage(), 'ERROR');
            return false;
        }
    }

    /**
     * ✅ Prepara i dati per l'email con TUTTE le variabili necessarie
     * 
     * @param array $preventivo_data Dati preventivo
     * @param array $options Opzioni
     * @return array Dati email preparati
     */
    private function prepare_email_data($preventivo_data, $options = array()) {
        // Email destinatario
        $recipient_email = $preventivo_data['mail'] ?? '';
        if (!is_email($recipient_email)) {
            throw new Exception('Email destinatario non valida: ' . $recipient_email);
        }
        
        // ✅ Prepara TUTTE le variabili per il template
        $template_vars = array(
            // Dati cliente
            'nome_referente' => $preventivo_data['nome_referente'] ?? '',
            'cognome_referente' => $preventivo_data['cognome_referente'] ?? '',
            
            // Dati evento
            'data_evento' => $this->format_date($preventivo_data['data_evento'] ?? ''),
            'tipo_evento' => $preventivo_data['tipo_evento'] ?? '',
            'numero_invitati' => $preventivo_data['numero_invitati'] ?? 0,
            'tipo_menu' => $preventivo_data['tipo_menu'] ?? '',
            
            // ✅ ORARI (fondamentali per il template)
            'orario_inizio' => $this->format_time($preventivo_data['orario_inizio'] ?? '20:30'),
            'orario_fine' => $this->format_time($preventivo_data['orario_fine'] ?? '01:30'),
            
            // Importi formattati
            'importo' => $this->format_currency($preventivo_data['importo_preventivo'] ?? 0),
            'acconto' => $this->format_currency($preventivo_data['acconto'] ?? 0),
            
            // ✅ PREZZO EXTRA PERSONA DINAMICO (Menu 7=20€, Menu 74=25€, Menu 747=30€)
            'prezzo_extra_persona' => $this->get_prezzo_extra_persona($preventivo_data['tipo_menu'] ?? 'Menu 74')
        );
        
        // Oggetto email
        $subject = 'Il tuo preventivo 747 Disco è pronto! 🎉';
        
        return array(
            'recipient_email' => $recipient_email,
            'subject' => $subject,
            'template_vars' => $template_vars,
            'options' => $options
        );
    }

    /**
     * ✅ NUOVO METODO: Calcola prezzo extra persona in base al menu
     * 
     * @param string $tipo_menu
     * @return string Prezzo formattato (es: "20,00")
     */
    private function get_prezzo_extra_persona($tipo_menu) {
        $prezzi = array(
            'Menu 7' => '20,00',
            'Menu 74' => '25,00',
            'Menu 7-4' => '25,00',  // Alias
            'Menu 747' => '30,00',
            'Menu 7-4-7' => '30,00'  // Alias
        );
        
        return $prezzi[$tipo_menu] ?? '25,00'; // Default 25,00
    }

    /**
     * ✅ Genera contenuto HTML dell'email con il TEMPLATE BELLISSIMO
     * 
     * @param array $email_data Dati email
     * @return string HTML content
     */
    private function generate_email_content($email_data) {
        $vars = $email_data['template_vars'];
        
        // ✅ TEMPLATE HTML PROFESSIONALE CON GRAFICA 747 DISCO
        $html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Il tuo preventivo 747 Disco è pronto!</title>
</head>
<body style="margin: 0; padding: 0; font-family: Arial, sans-serif; background-color: #f9f9f9;">
    <div style="max-width: 600px; margin: 0 auto; background: #f9f9f9; padding: 20px;">
        
        <!-- Logo -->
        <div style="text-align: center; margin-bottom: 20px;">
            <img src="https://747disco.it/wp-content/uploads/2025/06/images.png" alt="747 Disco" style="max-width: 160px;">
        </div>
        
        <!-- Header con gradiente -->
        <div style="background: linear-gradient(135deg, #c28a4d 0%, #b8b1b3 100%); padding: 30px; border-radius: 15px; text-align: center; color: white;">
            <h1 style="margin: 0; font-size: 28px;">🎉 IL TUO EVENTO DA SOGNO</h1>
            <p style="margin: 10px 0 0; font-size: 16px;">Preventivo personalizzato pronto!</p>
        </div>
        
        <!-- Contenuto principale -->
        <div style="background: white; padding: 30px; margin: 20px 0; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
            <h2 style="color: #c28a4d; margin-top: 0;">Buonasera ' . esc_html($vars['nome_referente']) . ',</h2>
            
            <p style="color: #333; line-height: 1.6; font-size: 16px;">
                Siamo entusiasti di presentarle il preventivo per il suo <strong>' . esc_html($vars['tipo_evento']) . '</strong> che si terrà il <strong>' . esc_html($vars['data_evento']) . '</strong>!
            </p>
            
            <!-- Box REGALO SPECIALE -->
            <div style="background: linear-gradient(135deg, #28a745 0%, #20c997 100%); padding: 25px; border-radius: 12px; margin: 25px 0; text-align: center; border: 3px dashed rgba(255,255,255,0.5);">
                <p style="margin: 0; color: white; font-size: 18px; font-weight: bold;">🎁 REGALO SPECIALE SOLO PER LEI</p>
                <p style="margin: 15px 0 10px; color: white; font-size: 32px; font-weight: bold;">VALORE €450</p>
                <p style="margin: 0; color: rgba(255,255,255,0.95); font-size: 15px;">
                    ✨ Servizio Fotografico Professionale (€250)<br>
                    🍫 Crepes Nutella Express per tutti gli ospiti (€200)
                </p>
                <p style="margin: 15px 0 0; color: white; font-size: 13px; font-style: italic;">
                    Omaggi riservati esclusivamente a chi conferma entro 7 giorni!
                </p>
            </div>
            
            <!-- Dettagli evento -->
            <div style="background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #c28a4d;">
                <h3 style="color: #2b1e1a; margin-top: 0;">📋 Riepilogo del Suo Evento</h3>
                <table style="width: 100%; border-collapse: collapse;">
                    <tr>
                        <td style="padding: 8px 0; color: #666; font-size: 15px;"><strong>📅 Data:</strong></td>
                        <td style="padding: 8px 0; color: #333; font-size: 15px; text-align: right;">' . esc_html($vars['data_evento']) . '</td>
                    </tr>
                    <tr>
                        <td style="padding: 8px 0; color: #666; font-size: 15px;"><strong>🎉 Evento:</strong></td>
                        <td style="padding: 8px 0; color: #333; font-size: 15px; text-align: right;">' . esc_html($vars['tipo_evento']) . '</td>
                    </tr>
                    <tr>
                        <td style="padding: 8px 0; color: #666; font-size: 15px;"><strong>👥 Ospiti:</strong></td>
                        <td style="padding: 8px 0; color: #333; font-size: 15px; text-align: right;">' . esc_html($vars['numero_invitati']) . ' persone</td>
                    </tr>
                    <tr>
                        <td style="padding: 8px 0; color: #666; font-size: 15px;"><strong>🍽️ Menu:</strong></td>
                        <td style="padding: 8px 0; color: #333; font-size: 15px; text-align: right;">' . esc_html($vars['tipo_menu']) . '</td>
                    </tr>
                    <tr>
                        <td style="padding: 8px 0; color: #666; font-size: 15px;"><strong>🕐 Orario:</strong></td>
                        <td style="padding: 8px 0; color: #333; font-size: 15px; text-align: right;">' . esc_html($vars['orario_inizio']) . ' - ' . esc_html($vars['orario_fine']) . '</td>
                    </tr>
                </table>
            </div>
            
            <!-- TUTTO INCLUSO -->
            <div style="background: #fff; padding: 20px; border-radius: 8px; margin: 25px 0; border: 2px solid #c28a4d;">
                <h3 style="color: #c28a4d; margin-top: 0; text-align: center;">✨ TUTTO INCLUSO NEL PACCHETTO</h3>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                    <div style="padding: 8px; background: #f8f9fa; border-radius: 5px;">
                        <span style="color: #28a745; font-size: 18px;">✓</span> 
                        <span style="color: #333; font-size: 14px;">Allestimenti sala personalizzati</span>
                    </div>
                    <div style="padding: 8px; background: #f8f9fa; border-radius: 5px;">
                        <span style="color: #28a745; font-size: 18px;">✓</span> 
                        <span style="color: #333; font-size: 14px;">DJ & Animazione</span>
                    </div>
                    <div style="padding: 8px; background: #f8f9fa; border-radius: 5px;">
                        <span style="color: #28a745; font-size: 18px;">✓</span> 
                        <span style="color: #333; font-size: 14px;">SIAE inclusa</span>
                    </div>
                    <div style="padding: 8px; background: #f8f9fa; border-radius: 5px;">
                        <span style="color: #28a745; font-size: 18px;">✓</span> 
                        <span style="color: #333; font-size: 14px;">18 luminoso</span>
                    </div>
                    <div style="padding: 8px; background: #f8f9fa; border-radius: 5px;">
                        <span style="color: #28a745; font-size: 18px;">✓</span> 
                        <span style="color: #333; font-size: 14px;">Locandina virtuale invito</span>
                    </div>
                    <div style="padding: 8px; background: #f8f9fa; border-radius: 5px;">
                        <span style="color: #28a745; font-size: 18px;">✓</span> 
                        <span style="color: #333; font-size: 14px;">Menu selezionato</span>
                    </div>
                </div>
                <p style="margin: 15px 0 0; text-align: center; color: #666; font-size: 13px;">
                    <em>Persone extra oltre le ' . esc_html($vars['numero_invitati']) . ' quotate: €' . esc_html($vars['prezzo_extra_persona']) . ' cad. (da definire 10 giorni prima)</em>
                </p>
            </div>
            
            <!-- EXTRA DISPONIBILI -->
            <div style="background: #fff8e6; padding: 20px; border-radius: 8px; margin: 25px 0; border-left: 4px solid #ffc107;">
                <h3 style="color: #e67e22; margin-top: 0;">⭐ Vuole rendere la festa ancora più speciale?</h3>
                <p style="color: #666; margin: 10px 0; font-size: 14px;">Aggiunga uno di questi extra esclusivi:</p>
                <ul style="list-style: none; padding: 0; margin: 15px 0;">
                    <li style="padding: 8px 0; color: #333; font-size: 14px;">🥂 <strong>Aperol Spritz in aperitivo</strong> - €80,00</li>
                    <li style="padding: 8px 0; color: #333; font-size: 14px;">🍺 <strong>Birra per tutta la cena</strong> - €3,00/persona</li>
                    <li style="padding: 8px 0; color: #333; font-size: 14px;">🍷 <strong>Vino per tutta la cena</strong> - €2,50/persona</li>
                    <li style="padding: 8px 0; color: #333; font-size: 14px;">🍤 <strong>Frittini misti</strong> - €4,50/persona</li>
                    <li style="padding: 8px 0; color: #333; font-size: 14px;">🍉 <strong>Tagliata di frutta</strong> - €120,00</li>
                    <li style="padding: 8px 0; color: #333; font-size: 14px;">🍫 <strong>Fontana di cioccolato</strong> - €260,00</li>
                    <li style="padding: 8px 0; color: #333; font-size: 14px;">🍾 <strong>Consumazioni alcoliche</strong> - €4,00 cad.</li>
                </ul>
            </div>
            
            <!-- Box investimento -->
            <div style="background: linear-gradient(135deg, #2b1e1a 0%, #3c3c3c 100%); padding: 25px; border-radius: 12px; margin: 25px 0; text-align: center;">
                <p style="margin: 0; color: rgba(255,255,255,0.8); font-size: 14px; text-transform: uppercase; letter-spacing: 1px;">Investimento Totale</p>
                <p style="margin: 10px 0; color: #c28a4d; font-size: 42px; font-weight: bold;">' . esc_html($vars['importo']) . '</p>
                <p style="margin: 5px 0 0; color: rgba(255,255,255,0.7); font-size: 13px;">
                    <em>Include tutto quanto descritto + omaggi del valore di €450</em>
                </p>
            </div>
            
            <!-- CTA PRINCIPALE -->
            <div style="background: #f0f9ff; padding: 25px; border-radius: 12px; margin: 25px 0; text-align: center; border: 2px solid #3b82f6;">
                <p style="color: #1e40af; font-size: 18px; font-weight: bold; margin: 0 0 15px;">
                    ⏰ Confermi entro 7 giorni e si assicuri:<br>
                    <span style="font-size: 24px; color: #c28a4d;">€450 di omaggi GRATIS!</span>
                </p>
                <a href="https://wa.me/393331234567?text=Salve%2C%20vorrei%20confermare%20il%20preventivo%20per%20' . urlencode($vars['tipo_evento']) . '" 
                   style="display: inline-block; background: #25d366; color: white; padding: 18px 45px; text-decoration: none; border-radius: 30px; font-weight: bold; font-size: 17px; margin: 10px 0; box-shadow: 0 4px 15px rgba(37, 211, 102, 0.4);">
                    💬 Conferma Subito su WhatsApp
                </a>
                <p style="margin: 15px 0 0; color: #666; font-size: 13px;">
                    Oppure risponda a questa email o ci chiami al numero in calce
                </p>
            </div>
            
            <p style="color: #333; line-height: 1.6; font-size: 15px; margin-top: 25px;">
                Il preventivo completo è allegato in PDF. Siamo a disposizione per qualsiasi chiarimento o personalizzazione.
            </p>
            
            <p style="color: #333; line-height: 1.6; font-size: 15px; margin-top: 20px;">
                <strong>Non vediamo l\'ora di rendere il suo ' . esc_html($vars['tipo_evento']) . ' indimenticabile!</strong>
            </p>
        </div>
        
        <!-- Footer -->
        <div style="text-align: center; color: #666; font-size: 12px; margin-top: 30px; padding: 20px;">
            <p style="margin: 5px 0;">
                <strong style="color: #c28a4d; font-size: 16px;">747 DISCO</strong><br>
                <em style="color: #999;">La tua festa, la nostra passione</em>
            </p>
            <p style="margin: 15px 0 5px;">
                📧 info@747disco.it | 📞 +39 333 123 4567<br>
                🌐 <a href="https://www.747disco.it" style="color: #c28a4d; text-decoration: none;">www.747disco.it</a>
            </p>
            <div style="margin: 20px 0; padding: 15px; background: #f8f9fa; border-radius: 8px;">
                <p style="margin: 0; font-size: 11px; color: #999; line-height: 1.4;">
                    Hai ricevuto questa email perché hai richiesto un preventivo a 747 Disco.<br>
                    Se non sei interessato, puoi ignorare questa comunicazione.
                </p>
            </div>
        </div>
        
    </div>
</body>
</html>';

        return $html;
    }

    /**
     * Invia email con allegato (wrapper pubblico)
     */
    public function send_email_with_attachment($preventivo_data, $pdf_path, $is_update = false) {
        $options = array(
            'is_update' => $is_update,
            'template_type' => $is_update ? 'preventivo_aggiornato' : 'preventivo_nuovo'
        );
        
        $success = $this->send_preventivo_email($preventivo_data, $pdf_path, $options);
        
        return array(
            'success' => $success,
            'message' => $success ? 'Email inviata con successo' : 'Errore invio email'
        );
    }

    /**
     * ✅ NUOVO: Invia email di PRE-VENDITA dopo preventivo
     * Template accattivante con colori del locale (Nero, Oro, Grigio, Bianco)
     * 
     * @param array $preventivo_data Dati del preventivo
     * @param array $options Opzioni aggiuntive
     * @return bool Success
     */
    public function send_presale_email($preventivo_data, $options = array()) {
        $this->log('Invio email pre-vendita per: ' . ($preventivo_data['nome_referente'] ?? 'N/A'));

        try {
            // Prepara dati email
            $email_data = $this->prepare_presale_email_data($preventivo_data, $options);
            
            // Genera contenuto HTML dell'email di pre-vendita
            $email_content = $this->generate_presale_email_content($email_data);
            
            // Invia email tramite wp_mail
            $this->log('Invio email pre-vendita wp_mail');
            $sent = wp_mail(
                $email_data['recipient_email'],
                $email_data['subject'],
                $email_content,
                $this->get_email_headers()
            );
            
            // Log risultato
            $this->log_delivery($email_data, $sent, array());
            
            if ($sent) {
                $this->log('✅ Email pre-vendita inviata con successo a: ' . $email_data['recipient_email']);
            } else {
                throw new Exception('Errore invio email pre-vendita tramite wp_mail');
            }
            
            return $sent;
            
        } catch (Exception $e) {
            $this->log('❌ Errore invio email pre-vendita: ' . $e->getMessage(), 'ERROR');
            return false;
        }
    }

    /**
     * Prepara i dati per l'email di pre-vendita
     * 
     * @param array $preventivo_data Dati preventivo
     * @param array $options Opzioni
     * @return array Dati email preparati
     */
    private function prepare_presale_email_data($preventivo_data, $options = array()) {
        // Email destinatario
        $recipient_email = $preventivo_data['mail'] ?? '';
        if (!is_email($recipient_email)) {
            throw new Exception('Email destinatario non valida: ' . $recipient_email);
        }
        
        // Prepara TUTTE le variabili per il template
        $template_vars = array(
            // Dati cliente
            'nome_referente' => $preventivo_data['nome_referente'] ?? '',
            'cognome_referente' => $preventivo_data['cognome_referente'] ?? '',
            'nome_cliente' => trim(($preventivo_data['nome_referente'] ?? '') . ' ' . ($preventivo_data['cognome_referente'] ?? '')),
            
            // Dati evento
            'data_evento' => $this->format_date($preventivo_data['data_evento'] ?? ''),
            'tipo_evento' => $preventivo_data['tipo_evento'] ?? '',
            'numero_invitati' => $preventivo_data['numero_invitati'] ?? 0,
            'tipo_menu' => $preventivo_data['tipo_menu'] ?? '',
            
            // Importi formattati
            'importo_totale' => $this->format_currency($preventivo_data['importo_preventivo'] ?? 0),
            'acconto' => $this->format_currency($preventivo_data['acconto'] ?? 0),
            
            // Contatti
            'telefono_sede' => $this->get_company_phone(),
            'email_sede' => $this->config->get('email_from_address', 'info@747disco.it')
        );
        
        // Oggetto email accattivante
        $subject = 'Il tuo evento da sogno ti aspetta, ' . $template_vars['nome_referente'] . '! ✨';
        
        return array(
            'recipient_email' => $recipient_email,
            'subject' => $subject,
            'template_vars' => $template_vars,
            'options' => $options
        );
    }

    /**
     * ✅ Genera contenuto HTML dell'email di PRE-VENDITA
     * Template accattivante con colori del locale: Nero, Oro, Grigio, Bianco
     * 
     * @param array $email_data Dati email
     * @return string HTML content
     */
    private function generate_presale_email_content($email_data) {
        $vars = $email_data['template_vars'];
        
        // Colori del locale
        $colore_nero = '#000000';
        $colore_oro = '#D4AF37';
        $colore_grigio = '#808080';
        $colore_bianco = '#FFFFFF';
        $colore_grigio_chiaro = '#F5F5F5';
        $colore_oro_scuro = '#B8941F';
        
        // ✅ TEMPLATE HTML PRE-VENDITA CON COLORI DEL LOCALE
        $html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Il tuo evento da sogno ti aspetta!</title>
</head>
<body style="margin: 0; padding: 0; font-family: \'Arial\', \'Helvetica Neue\', Helvetica, sans-serif; background-color: ' . $colore_grigio_chiaro . ';">
    <div style="max-width: 650px; margin: 0 auto; background: ' . $colore_grigio_chiaro . '; padding: 0;">
        
        <!-- Header Nero con Logo Oro -->
        <div style="background: ' . $colore_nero . '; padding: 30px 20px; text-align: center;">
            <img src="https://747disco.it/wp-content/uploads/2025/06/images.png" alt="747 Disco" style="max-width: 200px; height: auto;">
        </div>
        
        <!-- Hero Section con Gradiente Oro -->
        <div style="background: linear-gradient(135deg, ' . $colore_oro . ' 0%, ' . $colore_oro_scuro . ' 100%); padding: 50px 30px; text-align: center; color: ' . $colore_bianco . ';">
            <h1 style="margin: 0; font-size: 32px; font-weight: bold; text-shadow: 2px 2px 4px rgba(0,0,0,0.3);">
                🎉 Ciao ' . esc_html($vars['nome_referente']) . '!
            </h1>
            <p style="margin: 20px 0 0; font-size: 20px; font-weight: 300;">
                Il tuo evento da sogno ti aspetta!
            </p>
        </div>
        
        <!-- Contenuto principale Bianco -->
        <div style="background: ' . $colore_bianco . '; padding: 40px 30px; margin: 0;">
            
            <!-- Messaggio personale -->
            <div style="margin-bottom: 30px;">
                <p style="color: ' . $colore_nero . '; line-height: 1.8; font-size: 17px; margin: 0 0 20px;">
                    Siamo <strong style="color: ' . $colore_oro . ';">felicissimi</strong> di averti preparato un preventivo unico per il tuo <strong style="color: ' . $colore_oro . ';">' . esc_html($vars['tipo_evento']) . '</strong> del <strong>' . esc_html($vars['data_evento']) . '</strong>!
                </p>
                <p style="color: ' . $colore_grigio . '; line-height: 1.8; font-size: 16px; margin: 0;">
                    A <strong style="color: ' . $colore_nero . ';">747 Disco</strong> non facciamo solo feste... <em>creiamo ricordi indimenticabili!</em> ✨
                </p>
            </div>
            
            <!-- Box URGENZA con sfondo Oro -->
            <div style="background: linear-gradient(135deg, ' . $colore_oro . ' 0%, ' . $colore_oro_scuro . ' 100%); padding: 30px; border-radius: 15px; margin: 30px 0; text-align: center; border: 3px solid ' . $colore_nero . '; box-shadow: 0 8px 20px rgba(0,0,0,0.2);">
                <p style="margin: 0; color: ' . $colore_bianco . '; font-size: 22px; font-weight: bold; text-transform: uppercase; letter-spacing: 1px;">
                    ⏰ OFFERTA LIMITATA!
                </p>
                <p style="margin: 15px 0 10px; color: ' . $colore_bianco . '; font-size: 36px; font-weight: bold; text-shadow: 2px 2px 4px rgba(0,0,0,0.3);">
                    €450 di OMAGGI GRATIS
                </p>
                <p style="margin: 0; color: ' . $colore_bianco . '; font-size: 16px; line-height: 1.6;">
                    ✨ Servizio Fotografico Professionale (valore €250)<br>
                    🍫 Crepes Nutella Express per tutti gli ospiti (valore €200)
                </p>
                <p style="margin: 15px 0 0; color: ' . $colore_bianco . '; font-size: 14px; font-weight: bold; border-top: 2px dashed rgba(255,255,255,0.5); padding-top: 15px;">
                    ⚡ Riservato solo a chi conferma entro 7 giorni!
                </p>
            </div>
            
            <!-- Riepilogo Evento con stile elegante -->
            <div style="background: ' . $colore_grigio_chiaro . '; padding: 25px; border-radius: 10px; margin: 30px 0; border-left: 5px solid ' . $colore_oro . ';">
                <h2 style="color: ' . $colore_nero . '; margin: 0 0 20px; font-size: 24px; font-weight: bold;">
                    📋 Il Tuo Evento
                </h2>
                <table style="width: 100%; border-collapse: collapse;">
                    <tr>
                        <td style="padding: 12px 0; color: ' . $colore_grigio . '; font-size: 16px; border-bottom: 1px solid rgba(0,0,0,0.1);"><strong>📅 Data:</strong></td>
                        <td style="padding: 12px 0; color: ' . $colore_nero . '; font-size: 16px; text-align: right; border-bottom: 1px solid rgba(0,0,0,0.1); font-weight: bold;">' . esc_html($vars['data_evento']) . '</td>
                    </tr>
                    <tr>
                        <td style="padding: 12px 0; color: ' . $colore_grigio . '; font-size: 16px; border-bottom: 1px solid rgba(0,0,0,0.1);"><strong>🎉 Tipo Evento:</strong></td>
                        <td style="padding: 12px 0; color: ' . $colore_nero . '; font-size: 16px; text-align: right; border-bottom: 1px solid rgba(0,0,0,0.1); font-weight: bold;">' . esc_html($vars['tipo_evento']) . '</td>
                    </tr>
                    <tr>
                        <td style="padding: 12px 0; color: ' . $colore_grigio . '; font-size: 16px; border-bottom: 1px solid rgba(0,0,0,0.1);"><strong>👥 Numero Invitati:</strong></td>
                        <td style="padding: 12px 0; color: ' . $colore_nero . '; font-size: 16px; text-align: right; border-bottom: 1px solid rgba(0,0,0,0.1); font-weight: bold;">' . esc_html($vars['numero_invitati']) . ' persone</td>
                    </tr>
                    <tr>
                        <td style="padding: 12px 0; color: ' . $colore_grigio . '; font-size: 16px; border-bottom: 1px solid rgba(0,0,0,0.1);"><strong>🍽️ Menu Selezionato:</strong></td>
                        <td style="padding: 12px 0; color: ' . $colore_oro . '; font-size: 16px; text-align: right; border-bottom: 1px solid rgba(0,0,0,0.1); font-weight: bold;">' . esc_html($vars['tipo_menu']) . '</td>
                    </tr>
                    <tr>
                        <td style="padding: 12px 0; color: ' . $colore_grigio . '; font-size: 16px;"><strong>💰 Investimento Totale:</strong></td>
                        <td style="padding: 12px 0; color: ' . $colore_oro . '; font-size: 20px; text-align: right; font-weight: bold;">' . esc_html($vars['importo_totale']) . '</td>
                    </tr>
                </table>
            </div>
            
            <!-- Cosa è incluso -->
            <div style="background: ' . $colore_bianco . '; padding: 25px; border-radius: 10px; margin: 30px 0; border: 2px solid ' . $colore_oro . ';">
                <h3 style="color: ' . $colore_nero . '; margin: 0 0 20px; font-size: 22px; text-align: center; font-weight: bold;">
                    ✨ TUTTO INCLUSO NEL PACCHETTO
                </h3>
                <div style="display: block;">
                    <div style="padding: 10px 0; border-bottom: 1px solid ' . $colore_grigio_chiaro . ';">
                        <span style="color: ' . $colore_oro . '; font-size: 20px; margin-right: 10px;">✓</span>
                        <span style="color: ' . $colore_nero . '; font-size: 15px;">Allestimenti sala personalizzati</span>
                    </div>
                    <div style="padding: 10px 0; border-bottom: 1px solid ' . $colore_grigio_chiaro . ';">
                        <span style="color: ' . $colore_oro . '; font-size: 20px; margin-right: 10px;">✓</span>
                        <span style="color: ' . $colore_nero . '; font-size: 15px;">DJ & Animazione professionale</span>
                    </div>
                    <div style="padding: 10px 0; border-bottom: 1px solid ' . $colore_grigio_chiaro . ';">
                        <span style="color: ' . $colore_oro . '; font-size: 20px; margin-right: 10px;">✓</span>
                        <span style="color: ' . $colore_nero . '; font-size: 15px;">SIAE inclusa</span>
                    </div>
                    <div style="padding: 10px 0; border-bottom: 1px solid ' . $colore_grigio_chiaro . ';">
                        <span style="color: ' . $colore_oro . '; font-size: 20px; margin-right: 10px;">✓</span>
                        <span style="color: ' . $colore_nero . '; font-size: 15px;">Impianto luci professionale (18 luminoso)</span>
                    </div>
                    <div style="padding: 10px 0; border-bottom: 1px solid ' . $colore_grigio_chiaro . ';">
                        <span style="color: ' . $colore_oro . '; font-size: 20px; margin-right: 10px;">✓</span>
                        <span style="color: ' . $colore_nero . '; font-size: 15px;">Locandina virtuale invito personalizzata</span>
                    </div>
                    <div style="padding: 10px 0;">
                        <span style="color: ' . $colore_oro . '; font-size: 20px; margin-right: 10px;">✓</span>
                        <span style="color: ' . $colore_nero . '; font-size: 15px;">Menu selezionato (' . esc_html($vars['tipo_menu']) . ')</span>
                    </div>
                </div>
            </div>
            
            <!-- Immagine Gallery (se disponibile) -->
            <div style="text-align: center; margin: 30px 0;">
                <img src="https://747disco.it/wp-content/uploads/2025/11/Marco-74-1024x683.jpg" alt="747 Disco - Location eventi" style="max-width: 100%; height: auto; border-radius: 10px; box-shadow: 0 4px 15px rgba(0,0,0,0.2);">
            </div>
            
            <!-- Box Investimento Nero con Accento Oro -->
            <div style="background: ' . $colore_nero . '; padding: 35px; border-radius: 15px; margin: 30px 0; text-align: center; border: 3px solid ' . $colore_oro . ';">
                <p style="margin: 0; color: ' . $colore_grigio . '; font-size: 14px; text-transform: uppercase; letter-spacing: 2px;">
                    Investimento Totale
                </p>
                <p style="margin: 15px 0; color: ' . $colore_oro . '; font-size: 48px; font-weight: bold; text-shadow: 0 0 20px rgba(212,175,55,0.5);">
                    ' . esc_html($vars['importo_totale']) . '
                </p>
                <p style="margin: 10px 0 0; color: ' . $colore_grigio . '; font-size: 14px; line-height: 1.6;">
                    Include tutto quanto descritto<br>
                    <span style="color: ' . $colore_oro . '; font-weight: bold;">+ €450 di omaggi GRATIS se confermi entro 7 giorni!</span>
                </p>
                ' . (!empty($vars['acconto']) && $vars['acconto'] !== '€0,00' ? '<p style="margin: 15px 0 0; color: ' . $colore_grigio . '; font-size: 13px;">Acconto richiesto: <span style="color: ' . $colore_oro . '; font-weight: bold;">' . esc_html($vars['acconto']) . '</span></p>' : '') . '
            </div>
            
            <!-- CTA Principale Oro -->
            <div style="background: linear-gradient(135deg, ' . $colore_oro . ' 0%, ' . $colore_oro_scuro . ' 100%); padding: 35px; border-radius: 15px; margin: 30px 0; text-align: center; box-shadow: 0 8px 25px rgba(212,175,55,0.4);">
                <p style="color: ' . $colore_bianco . '; font-size: 20px; font-weight: bold; margin: 0 0 20px; text-shadow: 1px 1px 3px rgba(0,0,0,0.3);">
                    🎊 Pronto a rendere questo evento indimenticabile?
                </p>
                <p style="color: ' . $colore_bianco . '; font-size: 16px; margin: 0 0 25px; line-height: 1.6;">
                    Conferma entro 7 giorni e ricevi <strong>€450 di omaggi GRATIS</strong>!<br>
                    <span style="font-size: 14px; opacity: 0.9;">Non perdere questa opportunità unica!</span>
                </p>
                <a href="https://wa.me/39' . preg_replace('/[^0-9]/', '', $vars['telefono_sede']) . '?text=Ciao%2C%20vorrei%20confermare%20il%20preventivo%20per%20il%20mio%20' . urlencode($vars['tipo_evento']) . '%20del%20' . urlencode($vars['data_evento']) . '" 
                   style="display: inline-block; background: ' . $colore_nero . '; color: ' . $colore_oro . '; padding: 18px 50px; text-decoration: none; border-radius: 50px; font-weight: bold; font-size: 18px; margin: 10px 5px; border: 3px solid ' . $colore_oro . '; box-shadow: 0 4px 15px rgba(0,0,0,0.3); transition: all 0.3s;">
                    💬 Conferma su WhatsApp
                </a>
                <p style="margin: 20px 0 0; color: ' . $colore_bianco . '; font-size: 14px; opacity: 0.9;">
                    Oppure chiamaci al <strong>' . esc_html($vars['telefono_sede']) . '</strong><br>
                    o rispondi a questa email
                </p>
            </div>
            
            <!-- Social Proof -->
            <div style="background: ' . $colore_grigio_chiaro . '; padding: 25px; border-radius: 10px; margin: 30px 0; text-align: center;">
                <p style="color: ' . $colore_nero . '; font-size: 16px; margin: 0 0 15px; font-weight: bold;">
                    ⭐ Cosa dicono di noi:
                </p>
                <p style="color: ' . $colore_grigio . '; font-size: 15px; margin: 0; font-style: italic; line-height: 1.8;">
                    "La festa più bella che abbiamo mai fatto! Location spettacolare, servizio impeccabile e attenzione ai dettagli incredibile. Consigliatissimo!" - <strong style="color: ' . $colore_oro . ';">Marco, Festa 18 Anni</strong>
                </p>
            </div>
            
            <!-- Chiusura personale -->
            <div style="margin-top: 40px; padding-top: 30px; border-top: 2px solid ' . $colore_grigio_chiaro . ';">
                <p style="color: ' . $colore_nero . '; line-height: 1.8; font-size: 16px; margin: 0 0 15px;">
                    Non vediamo l\'ora di rendere il tuo <strong style="color: ' . $colore_oro . ';">' . esc_html($vars['tipo_evento']) . '</strong> un\'esperienza da ricordare per sempre!
                </p>
                <p style="color: ' . $colore_nero . '; line-height: 1.8; font-size: 16px; margin: 0;">
                    Se hai domande, dubbi o vuoi personalizzare qualcosa, siamo sempre a tua disposizione. <strong style="color: ' . $colore_oro . ';">Il tuo evento è la nostra priorità!</strong>
                </p>
            </div>
        </div>
        
        <!-- Footer Nero con Testo Grigio -->
        <div style="background: ' . $colore_nero . '; padding: 40px 30px; text-align: center;">
            <p style="margin: 0 0 15px;">
                <img src="https://747disco.it/wp-content/uploads/2025/06/images.png" alt="747 Disco" style="max-width: 150px; height: auto; opacity: 0.9;">
            </p>
            <p style="margin: 15px 0 5px; color: ' . $colore_oro . '; font-size: 20px; font-weight: bold;">
                747 DISCO
            </p>
            <p style="margin: 5px 0 20px; color: ' . $colore_grigio . '; font-size: 14px; font-style: italic;">
                La tua festa, la nostra passione
            </p>
            <div style="border-top: 1px solid rgba(255,255,255,0.1); padding-top: 20px; margin-top: 20px;">
                <p style="margin: 5px 0; color: ' . $colore_grigio . '; font-size: 14px;">
                    📧 <a href="mailto:' . esc_attr($vars['email_sede']) . '" style="color: ' . $colore_oro . '; text-decoration: none;">' . esc_html($vars['email_sede']) . '</a>
                </p>
                <p style="margin: 5px 0; color: ' . $colore_grigio . '; font-size: 14px;">
                    📞 <a href="tel:' . esc_attr(preg_replace('/[^0-9]/', '', $vars['telefono_sede'])) . '" style="color: ' . $colore_oro . '; text-decoration: none;">' . esc_html($vars['telefono_sede']) . '</a>
                </p>
                <p style="margin: 15px 0 5px; color: ' . $colore_grigio . '; font-size: 14px;">
                    🌐 <a href="https://www.747disco.it" style="color: ' . $colore_oro . '; text-decoration: none;">www.747disco.it</a>
                </p>
            </div>
            <div style="margin-top: 30px; padding: 20px; background: rgba(255,255,255,0.05); border-radius: 8px;">
                <p style="margin: 0; font-size: 11px; color: ' . $colore_grigio . '; line-height: 1.6;">
                    Hai ricevuto questa email perché hai richiesto un preventivo a 747 Disco.<br>
                    Se non desideri più ricevere comunicazioni, puoi rispondere con "RIMUOVIMI".
                </p>
            </div>
        </div>
        
    </div>
</body>
</html>';

        return $html;
    }

    /**
     * Ottiene il telefono della sede dalla configurazione
     * 
     * @return string Telefono
     */
    private function get_company_phone() {
        // Prova a ottenere dalla configurazione
        $phone = $this->config->get('company_phone', '');
        
        // Se non trovato, prova dalle opzioni WordPress
        if (empty($phone)) {
            $phone = get_option('disco747_company_phone', '');
        }
        
        // Default se ancora vuoto
        if (empty($phone)) {
            $phone = '06 123456789';
        }
        
        return $phone;
    }

    /**
     * Headers email
     */
    private function get_email_headers() {
        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $this->smtp_config['from_name'] . ' <' . $this->smtp_config['from_email'] . '>'
        );
        
        return $headers;
    }

    /**
     * Configura SMTP per PHPMailer
     */
    public function configure_smtp($phpmailer) {
        if (!$this->smtp_config['enabled']) {
            return;
        }
        
        $phpmailer->isSMTP();
        $phpmailer->Host = $this->smtp_config['host'];
        $phpmailer->Port = $this->smtp_config['port'];
        $phpmailer->SMTPAuth = true;
        $phpmailer->Username = $this->smtp_config['username'];
        $phpmailer->Password = $this->smtp_config['password'];
        $phpmailer->SMTPSecure = $this->smtp_config['encryption'];
        
        if ($this->debug_mode) {
            $phpmailer->SMTPDebug = 2;
        }
    }

    /**
     * Callback errore invio
     */
    public function on_mail_failure($wp_error) {
        $this->log("Errore invio email: " . $wp_error->get_error_message(), 'ERROR');
    }

    /**
     * Formatta data in italiano
     */
    private function format_date($date_string) {
        if (empty($date_string)) return '';
        
        $timestamp = strtotime($date_string);
        if (!$timestamp) return $date_string;
        
        $mesi = array(
            1 => 'Gennaio', 2 => 'Febbraio', 3 => 'Marzo', 4 => 'Aprile',
            5 => 'Maggio', 6 => 'Giugno', 7 => 'Luglio', 8 => 'Agosto',
            9 => 'Settembre', 10 => 'Ottobre', 11 => 'Novembre', 12 => 'Dicembre'
        );
        
        $giorno = date('d', $timestamp);
        $mese = $mesi[intval(date('m', $timestamp))];
        $anno = date('Y', $timestamp);
        
        return "$giorno $mese $anno";
    }

    /**
     * Formatta orario (rimuove secondi)
     */
    private function format_time($time_string) {
        if (empty($time_string)) return '';
        
        // Rimuove secondi se presenti (20:30:00 -> 20:30)
        return substr($time_string, 0, 5);
    }

    /**
     * Formatta valuta
     */
    private function format_currency($amount) {
        return '€' . number_format(floatval($amount), 2, ',', '.');
    }

    /**
     * Log delivery
     */
    private function log_delivery($email_data, $success, $attachments = array()) {
        $delivery_record = array(
            'timestamp' => current_time('mysql'),
            'recipient' => $email_data['recipient_email'],
            'subject' => $email_data['subject'],
            'success' => $success,
            'attachments_count' => count($attachments),
            'method' => $this->smtp_config['enabled'] ? 'SMTP' : 'PHP_MAIL'
        );
        
        $this->delivery_log[] = $delivery_record;
    }

    /**
     * Log centralizzato
     */
    private function log($message, $level = 'INFO') {
        if ($this->debug_mode || $level === 'ERROR') {
            error_log("[747Disco-Email] [{$level}] {$message}");
        }
    }
}