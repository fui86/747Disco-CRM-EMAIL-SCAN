<?php
/**
 * 747 Disco CRM - WhatsApp Handler
 * 
 * Gestisce l'integrazione e l'invio di messaggi WhatsApp automatici per i preventivi.
 * 
 * @package    747Disco_CRM
 * @subpackage Communication
 * @since      1.0.0
 */

// Previeni accesso diretto
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Classe Disco747_WhatsApp
 * 
 * NOTA: Questa classe NON usa namespace per compatibilità
 */
class Disco747_WhatsApp {

    /**
     * Istanza di configurazione
     * @var object
     */
    private $config;

    /**
     * Modalità debug
     * @var bool
     */
    private $debug_mode;

    /**
     * Sistema template
     * @var object
     */
    private $templates;

    /**
     * Configurazione WhatsApp
     * @var array
     */
    private $whatsapp_config;

    /**
     * Log consegne
     * @var array
     */
    private $delivery_log = [];

    /**
     * Costruttore
     */
    public function __construct() {
        // Usa la classe con namespace completo o alias se disponibile
        if (class_exists('Disco747_Config')) {
            $this->config = Disco747_Config::get_instance();
        } elseif (class_exists('Disco747_CRM\\Core\\Disco747_Config')) {
            $this->config = Disco747_CRM\Core\Disco747_Config::get_instance();
        } else {
            throw new Exception('Classe Config non trovata');
        }
        
        $this->debug_mode = $this->config->get('debug_mode', false);
        
        // Carica template system
        if (class_exists('Disco747_Templates')) {
            $this->templates = Disco747_Templates::get_instance();
        } elseif (class_exists('Disco747_CRM\\Generators\\Disco747_Templates')) {
            $this->templates = Disco747_CRM\Generators\Disco747_Templates::get_instance();
        }
        
        $this->load_whatsapp_config();
        
        $this->log('WhatsApp Handler inizializzato');
    }

    /**
     * Carica la configurazione WhatsApp dal sistema
     */
    private function load_whatsapp_config() {
        $this->whatsapp_config = array(
            'enabled' => $this->config->get('whatsapp_enabled', false),
            'token' => $this->config->get('whatsapp_token', ''),
            'phone' => $this->config->get('whatsapp_phone', ''),
            'template' => $this->config->get('whatsapp_template', '')
        );
    }

    /**
     * Genera link WhatsApp per un preventivo
     * 
     * @param array $preventivo_data Dati preventivo
     * @param string $template_type Tipo di template da usare
     * @param array $options Opzioni aggiuntive
     * @return string URL generato WhatsApp
     */
    public function generate_preventivo_link($preventivo_data, $template_type = 'default', $options = array()) {
        $this->log("Generazione link WhatsApp per: " . ($preventivo_data['nome_referente'] ?? 'N/A'));

        // Prepara dati messaggio
        $message_data = $this->prepare_message_data($preventivo_data, $options);

        // Renderizza messaggio con template
        $message_content = $this->render_message_template($message_data, $template_type);

        // Ottieni numero telefono pulito
        $phone_number = $this->extract_phone_number($preventivo_data['cellulare'] ?? '');

        // Genera URL WhatsApp
        $whatsapp_url = $this->build_whatsapp_url($phone_number, $message_content);

        $this->log("Link WhatsApp generato: " . substr($whatsapp_url, 0, 50) . '...');

        return $whatsapp_url;
    }

    /**
     * Prepara messaggio WhatsApp per preventivo
     * 
     * @param array $preventivo_data Dati preventivo
     * @param bool $is_update Se è un aggiornamento
     * @return array Risultato con URL
     */
    public function prepare_whatsapp_message($preventivo_data, $is_update = false) {
        $template_type = $is_update ? 'preventivo_aggiornato' : 'preventivo_nuovo';
        
        try {
            $url = $this->generate_preventivo_link($preventivo_data, $template_type);
            
            return array(
                'success' => true,
                'url' => $url,
                'message' => 'Link WhatsApp generato con successo'
            );
        } catch (Exception $e) {
            $this->log('Errore generazione WhatsApp: ' . $e->getMessage(), 'ERROR');
            
            return array(
                'success' => false,
                'url' => null,
                'message' => 'Errore generazione link WhatsApp: ' . $e->getMessage()
            );
        }
    }

    /**
     * Prepara i dati del messaggio
     * 
     * @param array $preventivo_data Dati preventivo
     * @param array $options Opzioni
     * @return array Dati preparati
     */
    private function prepare_message_data($preventivo_data, $options = array()) {
        return array(
            'nome_referente' => $preventivo_data['nome_referente'] ?? '',
            'cognome_referente' => $preventivo_data['cognome_referente'] ?? '',
            'data_evento' => $this->format_date($preventivo_data['data_evento'] ?? ''),
            'tipo_evento' => $preventivo_data['tipo_evento'] ?? '',
            'numero_invitati' => $preventivo_data['numero_invitati'] ?? 0,
            'tipo_menu' => $preventivo_data['tipo_menu'] ?? '',
            'importo' => $this->format_currency($preventivo_data['importo_preventivo'] ?? 0),
            'acconto' => $this->format_currency($preventivo_data['acconto'] ?? 0),
            'orario_inizio' => $preventivo_data['orario_inizio'] ?? '',
            'orario_fine' => $preventivo_data['orario_fine'] ?? ''
        );
    }

    /**
     * Renderizza il messaggio con template
     * 
     * @param array $message_data Dati messaggio
     * @param string $template_type Tipo template
     * @return string Messaggio renderizzato
     */
    private function render_message_template($message_data, $template_type) {
        // Carica template configurato
        $template = $this->whatsapp_config['template'];
        
        if (empty($template)) {
            // Template predefinito
            $template = "Ciao {{nome_referente}}! 🎉\n\n";
            $template .= "Il tuo preventivo per {{tipo_evento}} del {{data_evento}} è pronto!\n\n";
            $template .= "📅 Data: {{data_evento}}\n";
            $template .= "🕐 Orario: {{orario_inizio}} - {{orario_fine}}\n";
            $template .= "👥 Invitati: {{numero_invitati}}\n";
            $template .= "🍽️ Menu: {{tipo_menu}}\n";
            $template .= "💰 Importo: {{importo}}\n";
            
            if (!empty($message_data['acconto']) && $message_data['acconto'] !== '€0,00') {
                $template .= "✅ Acconto: {{acconto}}\n";
            }
            
            $template .= "\nPer qualsiasi informazione contattaci!\n";
            $template .= "📞 06 123456789\n";
            $template .= "📧 info@747disco.it";
        }
        
        // Sostituisci placeholder
        foreach ($message_data as $key => $value) {
            $template = str_replace('{{' . $key . '}}', $value, $template);
        }
        
        return $template;
    }

    /**
     * Estrae e pulisce numero di telefono
     * 
     * @param string $phone Numero telefono
     * @return string Numero pulito
     */
    private function extract_phone_number($phone) {
        // Rimuovi spazi e caratteri speciali
        $phone = preg_replace('/[^0-9+]/', '', $phone);
        
        // Se inizia con 0, sostituisci con +39
        if (strpos($phone, '0') === 0) {
            $phone = '+39' . substr($phone, 1);
        }
        
        // Se non inizia con +, aggiungi +39
        if (strpos($phone, '+') !== 0) {
            $phone = '+39' . $phone;
        }
        
        return $phone;
    }

    /**
     * Costruisce URL WhatsApp
     * 
     * @param string $phone_number Numero telefono
     * @param string $message Messaggio
     * @return string URL WhatsApp
     */
    private function build_whatsapp_url($phone_number, $message) {
        // Base URL WhatsApp Web
        $base_url = 'https://wa.me/';
        
        // Rimuovi il + dal numero per l'URL
        $phone_for_url = str_replace('+', '', $phone_number);
        
        // Encode messaggio per URL
        $encoded_message = urlencode($message);
        
        // Costruisci URL completo
        $whatsapp_url = $base_url . $phone_for_url . '?text=' . $encoded_message;
        
        return $whatsapp_url;
    }

    /**
     * Formatta data in italiano
     * 
     * @param string $date Data
     * @return string Data formattata
     */
    private function format_date($date) {
        if (empty($date)) {
            return '';
        }
        
        $timestamp = strtotime($date);
        if (!$timestamp) {
            return $date;
        }
        
        // Array mesi in italiano
        $mesi = array(
            'January' => 'Gennaio',
            'February' => 'Febbraio',
            'March' => 'Marzo',
            'April' => 'Aprile',
            'May' => 'Maggio',
            'June' => 'Giugno',
            'July' => 'Luglio',
            'August' => 'Agosto',
            'September' => 'Settembre',
            'October' => 'Ottobre',
            'November' => 'Novembre',
            'December' => 'Dicembre'
        );
        
        $formatted = date('d F Y', $timestamp);
        
        // Sostituisci mese in italiano
        foreach ($mesi as $eng => $ita) {
            $formatted = str_replace($eng, $ita, $formatted);
        }
        
        return $formatted;
    }

    /**
     * Formatta valuta
     * 
     * @param float $amount Importo
     * @return string Importo formattato
     */
    private function format_currency($amount) {
        return '€' . number_format((float)$amount, 2, ',', '.');
    }

    /**
     * Invia messaggio WhatsApp via API (per future implementazioni)
     * 
     * @param string $phone_number Numero destinatario
     * @param string $message Messaggio
     * @param array $media Media allegati (opzionale)
     * @return bool Success
     */
    public function send_whatsapp_api($phone_number, $message, $media = array()) {
        if (!$this->whatsapp_config['enabled'] || empty($this->whatsapp_config['token'])) {
            $this->log('WhatsApp API non configurata', 'WARNING');
            return false;
        }
        
        // Implementazione futura per API WhatsApp Business
        // Per ora restituisce false
        
        $this->log('Invio WhatsApp API non ancora implementato', 'INFO');
        return false;
    }

    /**
     * Salva log consegna
     * 
     * @param array $log_data Dati log
     */
    private function save_delivery_log($log_data) {
        $existing_log = get_option('disco747_whatsapp_delivery_log', array());
        $existing_log[] = array_merge($log_data, array(
            'timestamp' => current_time('mysql')
        ));
        
        // Mantieni solo ultimi 100 record
        if (count($existing_log) > 100) {
            $existing_log = array_slice($existing_log, -100);
        }
        
        update_option('disco747_whatsapp_delivery_log', $existing_log);
    }

    /**
     * Ottieni template predefiniti
     * 
     * @return array Template disponibili
     */
    public function get_default_templates() {
        return array(
            'preventivo_nuovo' => array(
                'name' => 'Nuovo Preventivo',
                'template' => "Ciao {{nome_referente}}! 🎉\n\nIl tuo preventivo per {{tipo_evento}} è pronto!\n\n📅 {{data_evento}}\n💰 {{importo}}\n\nContattaci per confermare!"
            ),
            'preventivo_aggiornato' => array(
                'name' => 'Preventivo Aggiornato',
                'template' => "Ciao {{nome_referente}}! 📝\n\nIl tuo preventivo è stato aggiornato.\n\n📅 {{data_evento}}\n💰 Nuovo importo: {{importo}}\n\nVerifica le modifiche!"
            ),
            'reminder' => array(
                'name' => 'Promemoria Evento',
                'template' => "Ciao {{nome_referente}}! ⏰\n\nTi ricordiamo il tuo evento:\n\n📅 {{data_evento}}\n🕐 {{orario_inizio}}\n👥 {{numero_invitati}} invitati\n\nA presto!"
            ),
            'conferma' => array(
                'name' => 'Conferma Preventivo',
                'template' => "Grazie {{nome_referente}}! ✅\n\nIl tuo evento è confermato:\n\n📅 {{data_evento}}\n💰 Totale: {{importo}}\n✅ Acconto: {{acconto}}\n\nCi vediamo presto!"
            )
        );
    }

    /**
     * Test invio WhatsApp
     * 
     * @param string $phone Numero test
     * @return array Risultato test
     */
    public function test_whatsapp($phone = null) {
        $test_data = array(
            'nome_referente' => 'Test',
            'cognome_referente' => 'Cliente',
            'cellulare' => $phone ?: '+391234567890',
            'data_evento' => date('Y-m-d', strtotime('+7 days')),
            'tipo_evento' => 'Test Evento',
            'numero_invitati' => 50,
            'tipo_menu' => 'Menu Test',
            'importo_preventivo' => 1000,
            'orario_inizio' => '20:00',
            'orario_fine' => '02:00'
        );
        
        try {
            $url = $this->generate_preventivo_link($test_data, 'preventivo_nuovo');
            
            return array(
                'success' => true,
                'url' => $url,
                'message' => 'Test WhatsApp generato con successo'
            );
        } catch (Exception $e) {
            return array(
                'success' => false,
                'message' => 'Errore test: ' . $e->getMessage()
            );
        }
    }

    /**
     * ✅ NUOVO: Genera messaggi WhatsApp FUNNEL di pre-vendita
     * Sistema di messaggi sequenziali per convertire il cliente
     * 
     * @param array $preventivo_data Dati preventivo
     * @param array $options Opzioni (delay tra messaggi, etc.)
     * @return array Array di URL WhatsApp per ogni messaggio del funnel
     */
    public function generate_presale_funnel($preventivo_data, $options = array()) {
        $this->log("Generazione funnel WhatsApp pre-vendita per: " . ($preventivo_data['nome_referente'] ?? 'N/A'));

        // Prepara dati messaggio
        $message_data = $this->prepare_presale_message_data($preventivo_data, $options);
        
        // Genera tutti i messaggi del funnel
        $funnel_messages = $this->get_presale_funnel_templates();
        
        $funnel_urls = array();
        $phone_number = $this->extract_phone_number($preventivo_data['cellulare'] ?? '');
        
        foreach ($funnel_messages as $step => $template_info) {
            // Renderizza messaggio con template
            $message_content = $this->render_funnel_template($template_info['template'], $message_data);
            
            // Genera URL WhatsApp
            $whatsapp_url = $this->build_whatsapp_url($phone_number, $message_content);
            
            $funnel_urls[$step] = array(
                'step' => $step,
                'name' => $template_info['name'],
                'delay_hours' => $template_info['delay_hours'] ?? 0,
                'message' => $message_content,
                'url' => $whatsapp_url
            );
        }
        
        $this->log("Funnel WhatsApp generato con " . count($funnel_urls) . " messaggi");
        
        return $funnel_urls;
    }

    /**
     * Prepara i dati per i messaggi funnel di pre-vendita
     * 
     * @param array $preventivo_data Dati preventivo
     * @param array $options Opzioni
     * @return array Dati preparati
     */
    private function prepare_presale_message_data($preventivo_data, $options = array()) {
        // Usa la classe Config per ottenere i contatti
        $config_class = null;
        if (class_exists('Disco747_Config')) {
            $config_class = 'Disco747_Config';
        } elseif (class_exists('Disco747_CRM\\Core\\Disco747_Config')) {
            $config_class = 'Disco747_CRM\\Core\\Disco747_Config';
        }
        
        $config = $config_class ? $config_class::get_instance() : null;
        
        return array(
            'nome_referente' => $preventivo_data['nome_referente'] ?? '',
            'cognome_referente' => $preventivo_data['cognome_referente'] ?? '',
            'nome_cliente' => trim(($preventivo_data['nome_referente'] ?? '') . ' ' . ($preventivo_data['cognome_referente'] ?? '')),
            'data_evento' => $this->format_date($preventivo_data['data_evento'] ?? ''),
            'tipo_evento' => $preventivo_data['tipo_evento'] ?? '',
            'numero_invitati' => $preventivo_data['numero_invitati'] ?? 0,
            'tipo_menu' => $preventivo_data['tipo_menu'] ?? '',
            'importo_totale' => $this->format_currency($preventivo_data['importo_preventivo'] ?? 0),
            'acconto' => $this->format_currency($preventivo_data['acconto'] ?? 0),
            'telefono_sede' => $config ? $config->get('company_phone', '06 123456789') : '06 123456789',
            'email_sede' => $config ? $config->get('email_from_address', 'info@747disco.it') : 'info@747disco.it'
        );
    }

    /**
     * Ottiene i template dei messaggi funnel di pre-vendita
     * Sistema a 4 step per massimizzare le conversioni
     * 
     * @return array Template funnel
     */
    private function get_presale_funnel_templates() {
        return array(
            'step1_welcome' => array(
                'name' => 'Benvenuto e Anticipation',
                'delay_hours' => 0, // Invio immediato
                'template' => "🎉 Ciao {{nome_referente}}!\n\nSiamo FELICISSIMI di averti preparato un preventivo unico per il tuo {{tipo_evento}} del {{data_evento}}!\n\n✨ A 747 Disco non facciamo solo feste... creiamo ricordi indimenticabili!\n\nHai visto la nostra email? Contiene tutti i dettagli e una SPECIALE SORPRESA che ti aspetta! 😉\n\nFammi sapere se hai domande o se vuoi personalizzare qualcosa! 💬"
            ),
            'step2_urgency' => array(
                'name' => 'Creazione Urgenza',
                'delay_hours' => 24, // Dopo 24 ore
                'template' => "Ciao {{nome_referente}}! 👋\n\nHai avuto modo di visionare il preventivo per il tuo {{tipo_evento}}?\n\n⏰ Ti ricordo che hai tempo solo 7 giorni per confermare e ricevere:\n\n✨ Servizio Fotografico Professionale (€250)\n🍫 Crepes Nutella Express per tutti (€200)\n\n💰 Valore totale: €450 di OMAGGI GRATIS!\n\nQuesta offerta è riservata ESCLUSIVAMENTE a chi conferma entro 7 giorni dalla richiesta preventivo.\n\nVuoi che ti aiuti a chiarire qualche dubbio? Sono qui! 💬"
            ),
            'step3_social_proof' => array(
                'name' => 'Social Proof e Trust',
                'delay_hours' => 48, // Dopo 48 ore
                'template' => "Ciao {{nome_referente}}! 🙌\n\nSpero che il preventivo ti sia piaciuto!\n\nSai cosa dice sempre chi organizza il proprio evento da noi?\n\n⭐ \"La festa più bella che abbiamo mai fatto! Location spettacolare, servizio impeccabile e attenzione ai dettagli incredibile.\"\n\n🎊 Abbiamo già organizzato centinaia di {{tipo_evento}} come il tuo e ogni volta ci emozioniamo come fosse la prima!\n\nIl tuo evento del {{data_evento}} sarà SPETTACOLARE!\n\nVuoi confermare? Chiamami o scrivimi! 📞💬"
            ),
            'step4_final_offer' => array(
                'name' => 'Offerta Finale e CTA',
                'delay_hours' => 72, // Dopo 72 ore (3 giorni)
                'template' => "{{nome_referente}}, ultimo messaggio prima che scada l'offerta! ⏰\n\nStai ancora pensando al tuo {{tipo_evento}} del {{data_evento}}?\n\n🎁 Ricordo che hai ancora qualche giorno per confermare e ricevere €450 di omaggi GRATIS!\n\n📋 Riepilogo rapido:\n• {{tipo_evento}} per {{numero_invitati}} persone\n• Menu {{tipo_menu}}\n• Investimento: {{importo_totale}}\n• + €450 di OMAGGI se confermi entro 7 giorni!\n\n💬 Sono qui per qualsiasi chiarimento o personalizzazione!\n\nNon perdere questa opportunità unica! \n\nChiamami al {{telefono_sede}} o rispondi a questo messaggio! 🚀"
            )
        );
    }

    /**
     * Renderizza un template funnel sostituendo i placeholder
     * 
     * @param string $template Template del messaggio
     * @param array $message_data Dati per sostituzione
     * @return string Messaggio renderizzato
     */
    private function render_funnel_template($template, $message_data) {
        $message = $template;
        
        // Sostituisci tutti i placeholder
        foreach ($message_data as $key => $value) {
            $message = str_replace('{{' . $key . '}}', $value, $message);
        }
        
        return $message;
    }

    /**
     * ✅ NUOVO: Genera singolo messaggio WhatsApp di pre-vendita
     * Versione semplificata per invio immediato
     * 
     * @param array $preventivo_data Dati preventivo
     * @param string $step Step del funnel ('step1', 'step2', etc.)
     * @return string URL WhatsApp
     */
    public function generate_presale_whatsapp_message($preventivo_data, $step = 'step1_welcome') {
        $this->log("Generazione messaggio WhatsApp pre-vendita step: $step");

        // Prepara dati messaggio
        $message_data = $this->prepare_presale_message_data($preventivo_data);
        
        // Ottieni template del funnel
        $funnel_templates = $this->get_presale_funnel_templates();
        
        if (!isset($funnel_templates[$step])) {
            $step = 'step1_welcome'; // Default al primo step
        }
        
        $template_info = $funnel_templates[$step];
        
        // Renderizza messaggio
        $message_content = $this->render_funnel_template($template_info['template'], $message_data);
        
        // Ottieni numero telefono pulito
        $phone_number = $this->extract_phone_number($preventivo_data['cellulare'] ?? '');
        
        // Genera URL WhatsApp
        $whatsapp_url = $this->build_whatsapp_url($phone_number, $message_content);
        
        $this->log("Messaggio WhatsApp pre-vendita generato: " . substr($whatsapp_url, 0, 50) . '...');
        
        return $whatsapp_url;
    }

    /**
     * Log centralizzato
     * 
     * @param string $message Messaggio
     * @param string $level Livello log
     */
    private function log($message, $level = 'INFO') {
        if ($this->debug_mode || $level === 'ERROR') {
            error_log("[747Disco-WhatsApp] [{$level}] {$message}");
        }
    }
}