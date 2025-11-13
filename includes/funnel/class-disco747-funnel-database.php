<?php
/**
 * Funnel Database Manager - 747 Disco CRM
 * Gestisce le tabelle per il sistema di automazione funnel
 * 
 * @package    Disco747_CRM
 * @subpackage Funnel
 * @version    1.0.0
 */

namespace Disco747_CRM\Funnel;

if (!defined('ABSPATH')) {
    exit('Accesso diretto non consentito');
}

class Disco747_Funnel_Database {
    
    private $sequences_table;
    private $tracking_table;
    private $charset_collate;
    
    public function __construct() {
        global $wpdb;
        
        $this->sequences_table = $wpdb->prefix . 'disco747_funnel_sequences';
        $this->tracking_table = $wpdb->prefix . 'disco747_funnel_tracking';
        $this->charset_collate = $wpdb->get_charset_collate();
    }
    
    /**
     * Crea le tabelle necessarie per il funnel
     */
    public function create_tables() {
        global $wpdb;
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        // Tabella Sequenze (Configurazione)
        $sql_sequences = "CREATE TABLE IF NOT EXISTS {$this->sequences_table} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            funnel_type varchar(50) NOT NULL DEFAULT 'pre_conferma',
            step_number int(11) NOT NULL DEFAULT 1,
            step_name varchar(100) DEFAULT '',
            days_offset int(11) NOT NULL DEFAULT 0,
            send_time time DEFAULT '09:00:00',
            email_enabled tinyint(1) NOT NULL DEFAULT 1,
            email_subject text DEFAULT NULL,
            email_body longtext DEFAULT NULL,
            whatsapp_enabled tinyint(1) NOT NULL DEFAULT 0,
            whatsapp_text text DEFAULT NULL,
            active tinyint(1) NOT NULL DEFAULT 1,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY funnel_type (funnel_type),
            KEY active (active)
        ) {$this->charset_collate}";
        
        dbDelta($sql_sequences);
        
        // Verifica e aggiungi colonna send_time se manca
        $this->maybe_add_send_time_column();
        
        // Tabella Tracking (Stato invii)
        $sql_tracking = "CREATE TABLE IF NOT EXISTS {$this->tracking_table} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            preventivo_id bigint(20) UNSIGNED NOT NULL,
            funnel_type varchar(50) NOT NULL DEFAULT 'pre_conferma',
            current_step int(11) NOT NULL DEFAULT 0,
            status varchar(20) NOT NULL DEFAULT 'active',
            started_at datetime NOT NULL,
            last_sent_at datetime DEFAULT NULL,
            next_send_at datetime DEFAULT NULL,
            completed_at datetime DEFAULT NULL,
            emails_log longtext DEFAULT NULL,
            whatsapp_log longtext DEFAULT NULL,
            notes text DEFAULT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY preventivo_id (preventivo_id),
            KEY funnel_type (funnel_type),
            KEY status (status),
            KEY next_send_at (next_send_at),
            UNIQUE KEY unique_preventivo_funnel (preventivo_id, funnel_type)
        ) {$this->charset_collate};";
        
        dbDelta($sql_tracking);
        
        error_log('[747Disco-Funnel] Tabelle create/verificate con successo');
        
        // Inserisci sequenze di default se la tabella è vuota
        $this->insert_default_sequences();
    }
    
    /**
     * Aggiunge colonna send_time se non esiste
     */
    private function maybe_add_send_time_column() {
        global $wpdb;
        
        // Controlla se la colonna esiste
        $column_exists = $wpdb->get_results($wpdb->prepare(
            "SHOW COLUMNS FROM {$this->sequences_table} LIKE %s",
            'send_time'
        ));
        
        if (empty($column_exists)) {
            $wpdb->query("ALTER TABLE {$this->sequences_table} ADD COLUMN send_time time DEFAULT '09:00:00' AFTER days_offset");
            error_log('[747Disco-Funnel] ✅ Colonna send_time aggiunta');
        }
    }
    
    /**
     * Inserisce sequenze di default per iniziare
     * ✅ FIX: Template Gmail-safe (senza tag <style>, solo CSS inline)
     */
    private function insert_default_sequences() {
        global $wpdb;
        
        $count = $wpdb->get_var("SELECT COUNT(*) FROM {$this->sequences_table}");
        
        if ($count > 0) {
            return; // Già esistono sequenze
        }
        
        error_log('[747Disco-Funnel] Inserimento template Gmail-safe...');
        
        // Sequenze PRE-CONFERMA di default
        // ✅ Template Gmail-safe: NO tag <style>, solo CSS inline
        $default_pre_conferma = array(
            array(
                'funnel_type' => 'pre_conferma',
                'step_number' => 1,
                'step_name' => 'Serve una mano?',
                'days_offset' => 1,
                'send_time' => '14:00:00',
                'email_enabled' => 1,
                'email_subject' => 'Tutto chiaro? | 747 Disco',
                'email_body' => '<!doctype html><html><body style="margin:0;padding:0;background:#1a1a1a"><div style="display:none;font-size:1px;color:#1a1a1a;line-height:1px;max-height:0px;max-width:0px;opacity:0;overflow:hidden;">Omaggi bloccati per te (48 ore): Foto Pro, Crepes Nutella, Sicurezza, SIAE. Conferma in 1 minuto.</div><table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#1a1a1a"><tr><td align="center" style="padding:0 12px"><table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0" style="max-width:600px;width:100%;margin:0 auto;background:#1a1a1a;color:#ffffff"><tr><td style="padding:30px"><table role="presentation" width="100%"><tr><td align="center" style="padding-bottom:22px"><img src="https://747disco.it/wp-content/uploads/2025/06/images.png" width="180" alt="747 Disco" style="width:180px;max-width:100%;height:auto"></td></tr></table><table role="presentation" width="100%" style="background:linear-gradient(135deg,#c28a4d 0%,#a67c44 100%);border-radius:16px"><tr><td align="center" style="padding:24px 20px;border-radius:16px"><h1 style="margin:0;font-size:28px;line-height:1.25;font-weight:900;color:#ffffff">Serve una mano sul preventivo? 👋</h1><p style="margin:10px 0 0;font-size:15px;line-height:1.6;color:#ffffff">Riguarda il tuo <strong>{{tipo_evento}}</strong> del <strong>{{data_evento}}</strong>.</p></td></tr></table><table role="presentation" width="100%"><tr><td style="padding:20px 0 6px 0"><p style="font-size:17px;color:#eaeaea;line-height:1.7;margin:0 0 8px">Ciao <strong>{{nome}}</strong>, è passato un giorno dal preventivo. Se hai dubbi su menu, orari o costi, rispondi pure: ti guidiamo in 2 minuti.</p><p style="font-size:15px;color:#bfbfbf;line-height:1.6;margin:0">Intanto abbiamo <strong>bloccato per te</strong> gli omaggi qui sotto per altre <strong>48 ore</strong>.</p></td></tr></table><table role="presentation" width="100%" style="margin-top:14px;background:#fff4e3;border:2px solid #c28a4d;border-radius:16px;color:#2b1e1a"><tr><td style="padding:18px"><p style="margin:0 0 10px;font-weight:900;font-size:18px;text-align:center;color:#c28a4d">🎁 OMAGGI BLOCCATI PER TE — 48 ORE</p><ul style="margin:0;padding-left:20px;line-height:1.9;font-size:14px"><li>📸 <strong>Servizio Fotografico Pro</strong> (~€250)</li><li>🥞 <strong>Crepes alla Nutella</strong> (~€200)</li><li>🛡️ <strong>Accoglienza &amp; Sicurezza</strong> (~€180)</li><li>🎼 <strong>SIAE Inclusa</strong> (~€200)</li></ul><p style="margin:10px 0 0;color:#7a5a00;font-size:13px;text-align:center">Valore totale: <strong>~€830</strong></p><div style="text-align:center;margin-top:16px"><a href="https://wa.me/393471811119?text=Ciao%20sono%20{{nome}}%20{{cognome}}.%20Confermo%20il%20{{tipo_evento}}%20del%20{{data_evento}}%20🎁" style="background:#25d366;color:#ffffff;text-decoration:none;padding:14px 26px;border-radius:40px;font-weight:800;border:3px solid #1fa855;display:inline-block">💬 Conferma e mantieni gli omaggi</a></div></td></tr></table><table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-top:22px;background:#1a1a1a;border:3px solid #c28a4d;border-radius:16px"><tr><td align="center" style="padding:22px 18px"><p style="color:#d4a574;margin:0 0 12px;font-size:16px">Hai una domanda o vuoi bloccare tutto in 1 minuto?</p><a href="https://wa.me/393471811119?text=Ciao%20sono%20{{nome}}%20{{cognome}}.%20Ho%20ricevuto%20il%20preventivo%20per%20il%20{{data_evento}}%20👍" style="background:#25d366;color:#ffffff;text-decoration:none;padding:14px 26px;border-radius:40px;font-weight:800;border:3px solid #1fa855;display:inline-block">💬 Scrivici su WhatsApp</a><p style="color:#8f8f8f;font-size:12px;margin:10px 0 0">Oppure rispondi a: <a href="mailto:eventi@747disco.it" style="color:#d4a574;text-decoration:none">eventi@747disco.it</a></p></td></tr></table><table role="presentation" width="100%" style="margin-top:26px;border-top:1px solid #333"><tr><td align="center" style="padding:24px"><img src="https://747disco.it/wp-content/uploads/2025/06/images.png" width="120" alt="747 Disco" style="width:120px;height:auto;opacity:.9;margin:0 0 12px"><p style="margin:0;color:#c28a4d;font-weight:700">747 DISCO</p><p style="margin:6px 0;color:#d4a574;font-size:14px">La tua festa inizia qui</p><p style="margin:15px 0 0;color:#999;font-size:12px;line-height:1.6">📧 <a href="mailto:eventi@747disco.it" style="color:#d4a574;text-decoration:none">eventi@747disco.it</a><br>📞 <a href="tel:+393471811119" style="color:#d4a574;text-decoration:none">+39 347 181 1119</a><br>📍 V.le J.F. Kennedy, 131 – Ciampino (RM)</p><p style="margin-top:14px;font-size:11px;color:#666">Hai ricevuto questa email perché hai richiesto un preventivo (ID: {{preventivo_id}}).</p></td></tr></table></td></tr></table></td></tr></table></body></html>',
                'whatsapp_enabled' => 0,
                'whatsapp_text' => '',
                'active' => 1
            ),
            array(
                'funnel_type' => 'pre_conferma',
                'step_number' => 2,
                'step_name' => 'Ultimi posti',
                'days_offset' => 2,
                'send_time' => '10:00:00',
                'email_enabled' => 1,
                'email_subject' => 'Ultimi posti per la tua data | 747 Disco',
                'email_body' => '<!doctype html><html><body style="margin:0;padding:0;background:#1a1a1a"><div style="display:none;font-size:1px;color:#1a1a1a;line-height:1px;max-height:0px;max-width:0px;opacity:0;overflow:hidden;">La tua data {{data_evento}} è ancora disponibile ma le richieste aumentano. Blocca ora i tuoi omaggi!</div><table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#1a1a1a"><tr><td align="center" style="padding:0 12px"><table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0" style="max-width:600px;width:100%;margin:0 auto;background:#1a1a1a;color:#ffffff"><tr><td style="padding:30px"><table role="presentation" width="100%"><tr><td align="center" style="padding-bottom:22px"><img src="https://747disco.it/wp-content/uploads/2025/06/images.png" width="180" alt="747 Disco" style="width:180px;max-width:100%;height:auto"></td></tr></table><table role="presentation" width="100%" style="background:linear-gradient(135deg,#c28a4d 0%,#a67c44 100%);border-radius:16px"><tr><td align="center" style="padding:24px 20px;border-radius:16px"><h1 style="margin:0;font-size:28px;line-height:1.25;font-weight:900;color:#ffffff">La tua data è ancora libera... per poco ⏰</h1><p style="margin:10px 0 0;font-size:15px;line-height:1.6;color:#ffffff">Evento del <strong>{{data_evento}}</strong> — <strong>{{tipo_evento}}</strong></p></td></tr></table><table role="presentation" width="100%"><tr><td style="padding:22px 0 10px 0"><p style="font-size:17px;color:#eaeaea;line-height:1.7;margin:0 0 12px">Ciao <strong>{{nome}}</strong>, sappiamo che stai valutando anche altre location — è normale! Ma prima di decidere, vogliamo dirti una cosa chiara e semplice:</p><p style="font-size:17px;color:#FFD700;line-height:1.7;margin:0 0 16px;text-align:center;font-weight:700">A questo prezzo, con questi servizi inclusi, <u>difficilmente troverai un pacchetto simile</u>.</p><p style="font-size:15px;color:#bfbfbf;line-height:1.7;margin:0">Da noi non paghi solo la sala: hai <strong>staff, musica, sicurezza, catering e omaggi premium</strong> già inclusi. Nessuna sorpresa, nessun costo nascosto: solo una festa perfetta, chiavi in mano.</p></td></tr></table><table role="presentation" width="100%" style="margin-top:20px;background:#121212;border:2px solid #c28a4d;border-radius:14px"><tr><td style="padding:20px 18px"><h3 style="margin:0 0 10px;font-size:18px;color:#c28a4d">⭐ Recensioni reali, emozioni vere</h3><p style="margin:0 0 8px;font-size:14px;line-height:1.7;color:#eaeaea">Centinaia di famiglie e ragazzi ci hanno scelto e ci hanno lasciato <strong>solo recensioni 5 stelle</strong> su Google.</p><p style="margin:0;font-size:14px;color:#d4a574;line-height:1.7">"Tutto perfetto, organizzazione impeccabile."<br>"Location pazzesca, staff gentilissimo, serata indimenticabile."</p><p style="margin:10px 0 0;color:#bfbfbf;font-size:13px">👉 <a href="https://www.google.com/search?q=747Disco+Recensioni" style="color:#FFD700;text-decoration:none">Leggi le recensioni</a></p></td></tr></table><table role="presentation" width="100%" style="margin-top:25px;background:#fff4e3;border:2px solid #c28a4d;border-radius:14px;color:#2b1e1a"><tr><td style="padding:18px"><p style="margin:0 0 10px;font-weight:900;font-size:18px;text-align:center;color:#c28a4d">🎁 OMAGGI ANCORA BLOCCATI PER TE</p><ul style="margin:0;padding-left:20px;line-height:1.9;font-size:14px"><li>📸 <strong>Servizio Fotografico Professionale</strong> (~€250)</li><li>🍫 <strong>Crepes alla Nutella per tutti</strong> (~€200)</li><li>🛡️ <strong>Accoglienza &amp; Sicurezza dedicate</strong> (~€180)</li><li>🎼 <strong>SIAE Inclusa</strong> (~€200)</li></ul><p style="margin:10px 0 0;color:#7a5a00;font-size:13px;text-align:center">Valore: <strong>oltre €800</strong> — inclusi nel tuo preventivo.</p><div style="text-align:center;margin-top:16px"><a href="https://wa.me/393471811119?text=Ciao%20sono%20{{nome}}%20{{cognome}}.%20Confermo%20il%20{{tipo_evento}}%20del%20{{data_evento}}%20🎁" style="background:#25d366;color:#ffffff;text-decoration:none;padding:14px 26px;border-radius:40px;font-weight:800;border:3px solid #1fa855;display:inline-block">💬 Confermo e blocco i miei omaggi</a></div></td></tr></table><table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-top:22px;background:#1a1a1a;border:3px solid #c28a4d;border-radius:16px"><tr><td align="center" style="padding:22px 18px"><p style="color:#d4a574;margin:0 0 12px;font-size:16px">Scrivici ora e blocca il tuo evento con tutti i vantaggi inclusi 👇</p><a href="https://wa.me/393471811119?text=Ciao%20sono%20{{nome}}%20{{cognome}}.%20Voglio%20confermare%20il%20{{tipo_evento}}%20del%20{{data_evento}}%20🎉" style="background:#25d366;color:#ffffff;text-decoration:none;padding:14px 26px;border-radius:40px;font-weight:800;border:3px solid #1fa855;display:inline-block">💬 Conferma ora su WhatsApp</a><p style="color:#8f8f8f;font-size:12px;margin:10px 0 0">Oppure rispondi a: <a href="mailto:eventi@747disco.it" style="color:#d4a574;text-decoration:none">eventi@747disco.it</a></p></td></tr></table><table role="presentation" width="100%" style="margin-top:24px;border-top:1px solid #333"><tr><td align="center" style="padding:24px"><img src="https://747disco.it/wp-content/uploads/2025/06/images.png" width="120" alt="747 Disco" style="width:120px;height:auto;opacity:.9;margin:0 0 12px"><p style="margin:0;color:#c28a4d;font-weight:700">747 DISCO</p><p style="margin:6px 0;color:#d4a574;font-size:14px">La tua festa inizia qui</p><p style="margin:15px 0 0;color:#999;font-size:12px;line-height:1.6">📧 <a href="mailto:eventi@747disco.it" style="color:#d4a574;text-decoration:none">eventi@747disco.it</a><br>📞 <a href="tel:+393471811119" style="color:#d4a574;text-decoration:none">+39 347 181 1119</a><br>📍 V.le J.F. Kennedy, 131 – Ciampino (RM)</p><p style="margin-top:14px;font-size:11px;color:#666">Hai ricevuto questa email perché hai richiesto un preventivo (ID: {{preventivo_id}}).</p></td></tr></table></td></tr></table></td></tr></table></body></html>',
                'whatsapp_enabled' => 0,
                'whatsapp_text' => '',
                'active' => 1
            ),
            array(
                'funnel_type' => 'pre_conferma',
                'step_number' => 3,
                'step_name' => 'Ultime 24 ore',
                'days_offset' => 3,
                'send_time' => '09:00:00',
                'email_enabled' => 1,
                'email_subject' => 'Ultime 24 Ore | 747 Disco',
                'email_body' => '<!doctype html><html><body style="margin:0;padding:0;background:#1a1a1a"><div style="display:none;font-size:1px;color:#1a1a1a;line-height:1px;max-height:0px;max-width:0px;opacity:0;overflow:hidden;">Ultime 24 ore per bloccare la tua data {{data_evento}} e mantenere i 4 omaggi esclusivi.</div><table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#1a1a1a"><tr><td align="center" style="padding:0 12px"><table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0" style="max-width:600px;width:100%;margin:0 auto;background:#1a1a1a;color:#ffffff"><tr><td style="padding:30px"><table role="presentation" width="100%"><tr><td align="center" style="padding-bottom:22px"><img src="https://747disco.it/wp-content/uploads/2025/06/images.png" width="180" alt="747 Disco" style="width:180px;max-width:100%;height:auto"></td></tr></table><table role="presentation" width="100%" style="background:#ff3b30;border:2px solid #5a0000;border-radius:14px"><tr><td align="center" style="padding:16px 14px"><span style="background:#1a1a1a;color:#ffd6d6;border:1px solid #4d0000;padding:6px 12px;border-radius:999px;font-weight:800;font-size:12px;display:inline-block">⏰ CONTA ALLA ROVESCIA</span><h2 style="margin:10px 0 6px 0;color:#ffffff;font-size:22px;line-height:1.25;font-weight:900">ULTIME <span style="color:#ffd700">24 ORE</span> PER BLOCCARE LA TUA DATA</h2><p style="margin:0;color:#ffd6d6;font-size:13px;line-height:1.5">Offerta all-inclusive + 4 omaggi ancora attivi fino a stasera</p></td></tr></table><table role="presentation" width="100%" style="margin-top:20px;background:#121212;border:1px solid #ff5c5c;border-radius:12px"><tr><td style="padding:18px"><p style="margin:0 0 10px;color:#eaeaea;font-size:16px;line-height:1.7">Ciao <strong>{{nome}}</strong>, immaginiamo che tu stia valutando anche altre soluzioni — ma ti avvisiamo con la massima trasparenza:</p><p style="margin:12px 0;color:#FFD700;font-weight:800;text-align:center;font-size:17px">⏳ Stiamo ricevendo <u>più richieste per la stessa data</u> e, alla prima conferma, il sistema chiude la disponibilità.</p><p style="margin:10px 0 0;color:#eaeaea;font-size:15px;line-height:1.8">La tua data <strong>{{data_evento}}</strong> è ancora libera, ma <strong>gli omaggi scadranno tra poche ore</strong>.</p></td></tr></table><table role="presentation" width="100%" style="margin-top:22px;background:#fff4e3;border:2px solid #c28a4d;border-radius:16px;color:#2b1e1a"><tr><td style="padding:18px"><p style="margin:0 0 10px;font-weight:900;font-size:18px;text-align:center;color:#c28a4d">🎁 ULTIME ORE PER I 4 OMAGGI ESCLUSIVI</p><ul style="margin:0;padding-left:20px;line-height:1.9;font-size:14px"><li>📸 <strong>Servizio Fotografico Professionale</strong> (~€250)</li><li>🍫 <strong>Crepes alla Nutella per tutti</strong> (~€200)</li><li>🛡️ <strong>Accoglienza &amp; Sicurezza dedicate</strong> (~€180)</li><li>🎼 <strong>SIAE Inclusa</strong> (~€200)</li></ul><p style="margin:10px 0 0;color:#7a5a00;font-size:13px;text-align:center">Dopo la scadenza, gli omaggi si <strong>azzerrano automaticamente</strong>.</p><div style="text-align:center;margin-top:16px"><a href="https://wa.me/393471811119?text=Ciao%20sono%20{{nome}}%20{{cognome}}.%20Confermo%20il%20{{tipo_evento}}%20del%20{{data_evento}}%20🎁" style="background:#25d366;color:#ffffff;text-decoration:none;padding:14px 26px;border-radius:40px;font-weight:800;border:3px solid #1fa855;display:inline-block">💬 Confermo ora e blocco la mia offerta</a></div></td></tr></table><table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-top:22px;background:#1a1a1a;border:3px solid #c28a4d;border-radius:16px"><tr><td align="center" style="padding:22px 18px"><p style="color:#FFD700;margin:0 0 12px;font-size:17px;font-weight:700">⏰ Ultima chiamata: tra poche ore l\'offerta verrà chiusa.</p><a href="https://wa.me/393471811119?text=Ciao%20sono%20{{nome}}%20{{cognome}}.%20Voglio%20confermare%20il%20{{tipo_evento}}%20del%20{{data_evento}}%20🎉" style="background:#25d366;color:#ffffff;text-decoration:none;padding:14px 26px;border-radius:40px;font-weight:800;border:3px solid #1fa855;display:inline-block">💬 Conferma ora su WhatsApp</a><p style="color:#8f8f8f;font-size:12px;margin:10px 0 0">Oppure rispondi a: <a href="mailto:eventi@747disco.it" style="color:#d4a574;text-decoration:none">eventi@747disco.it</a></p></td></tr></table><table role="presentation" width="100%" style="margin-top:24px;border-top:1px solid #333"><tr><td align="center" style="padding:24px"><img src="https://747disco.it/wp-content/uploads/2025/06/images.png" width="120" alt="747 Disco" style="width:120px;height:auto;opacity:.9;margin:0 0 12px"><p style="margin:0;color:#c28a4d;font-weight:700">747 DISCO</p><p style="margin:6px 0;color:#d4a574;font-size:14px">La tua festa inizia qui</p><p style="margin:15px 0 0;color:#999;font-size:12px;line-height:1.6">📧 <a href="mailto:eventi@747disco.it" style="color:#d4a574;text-decoration:none">eventi@747disco.it</a><br>📞 <a href="tel:+393471811119" style="color:#d4a574;text-decoration:none">+39 347 181 1119</a><br>📍 V.le J.F. Kennedy, 131 – Ciampino (RM)</p><p style="margin-top:14px;font-size:11px;color:#666">Hai ricevuto questa email perché hai richiesto un preventivo (ID: {{preventivo_id}}).</p></td></tr></table></td></tr></table></td></tr></table></body></html>',
                'whatsapp_enabled' => 0,
                'whatsapp_text' => '',
                'active' => 1
            )
        );
        
        foreach ($default_pre_conferma as $sequence) {
            $wpdb->insert($this->sequences_table, $sequence);
        }
        
        // Sequenze PRE-EVENTO di default (vuote, da configurare)
        $default_pre_evento = array(
            array(
                'funnel_type' => 'pre_evento',
                'step_number' => 1,
                'step_name' => 'Upselling -10 giorni',
                'days_offset' => -10,
                'send_time' => '09:00:00',
                'email_enabled' => 1,
                'email_subject' => '🎁 Mancano 10 giorni! Pacchetti extra in sconto',
                'email_body' => '[Da configurare]',
                'whatsapp_enabled' => 0,
                'whatsapp_text' => '[Da configurare]',
                'active' => 0
            ),
            array(
                'funnel_type' => 'pre_evento',
                'step_number' => 2,
                'step_name' => 'Reminder -7 giorni',
                'days_offset' => -7,
                'send_time' => '09:00:00',
                'email_enabled' => 1,
                'email_subject' => '🎉 Una settimana al tuo evento!',
                'email_body' => '[Da configurare]',
                'whatsapp_enabled' => 0,
                'whatsapp_text' => '[Da configurare]',
                'active' => 0
            )
        );
        
        foreach ($default_pre_evento as $sequence) {
            $wpdb->insert($this->sequences_table, $sequence);
        }
        
        error_log('[747Disco-Funnel] Sequenze di default inserite');
    }
    
    /**
     * ✅ Ricarica template Gmail-safe (forza aggiornamento)
     * Usa questa funzione se i template esistenti sono corrotti
     * 
     * @param bool $force Se true, cancella i template esistenti prima di ricaricare
     * @return bool Success
     */
    public function reload_default_templates($force = false) {
        global $wpdb;
        
        if ($force) {
            // Cancella template corrotti
            $deleted = $wpdb->delete($this->sequences_table, array('funnel_type' => 'pre_conferma'));
            error_log("[747Disco-Funnel] Cancellati {$deleted} template corrotti");
        }
        
        // Resetta il count in modo che insert_default_sequences() li reinserisca
        $count_before = $wpdb->get_var("SELECT COUNT(*) FROM {$this->sequences_table} WHERE funnel_type = 'pre_conferma'");
        
        if ($count_before == 0 || $force) {
            $this->insert_default_sequences();
            $count_after = $wpdb->get_var("SELECT COUNT(*) FROM {$this->sequences_table} WHERE funnel_type = 'pre_conferma'");
            error_log("[747Disco-Funnel] ✅ Ricaricati {$count_after} template Gmail-safe");
            return true;
        }
        
        return false;
    }
    
    /**
     * Verifica salute delle tabelle
     */
    public function check_health() {
        global $wpdb;
        
        $health = array(
            'sequences_table_exists' => false,
            'tracking_table_exists' => false,
            'sequences_count' => 0,
            'tracking_count' => 0,
            'status' => 'unknown'
        );
        
        try {
            $sequences_exists = $wpdb->get_var("SHOW TABLES LIKE '{$this->sequences_table}'");
            $tracking_exists = $wpdb->get_var("SHOW TABLES LIKE '{$this->tracking_table}'");
            
            $health['sequences_table_exists'] = ($sequences_exists === $this->sequences_table);
            $health['tracking_table_exists'] = ($tracking_exists === $this->tracking_table);
            
            if ($health['sequences_table_exists']) {
                $health['sequences_count'] = intval($wpdb->get_var("SELECT COUNT(*) FROM {$this->sequences_table}"));
            }
            
            if ($health['tracking_table_exists']) {
                $health['tracking_count'] = intval($wpdb->get_var("SELECT COUNT(*) FROM {$this->tracking_table}"));
            }
            
            $health['status'] = ($health['sequences_table_exists'] && $health['tracking_table_exists']) ? 'ok' : 'error';
            
        } catch (\Exception $e) {
            $health['status'] = 'error';
            $health['error'] = $e->getMessage();
        }
        
        return $health;
    }
    
    /**
     * Reset completo (solo per sviluppo/test)
     */
    public function reset_tables($confirm = false) {
        if (!$confirm) {
            return false;
        }
        
        global $wpdb;
        
        $wpdb->query("DROP TABLE IF EXISTS {$this->sequences_table}");
        $wpdb->query("DROP TABLE IF EXISTS {$this->tracking_table}");
        
        $this->create_tables();
        
        error_log('[747Disco-Funnel] Tabelle resettate');
        
        return true;
    }
}
