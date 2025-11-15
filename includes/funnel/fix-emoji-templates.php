<?php
/**
 * Script Helper: Aggiorna template Funnel rimuovendo emoji corrotte
 * 
 * COME USARE:
 * 1. Vai su: https://tuosito.it/wp-admin/admin.php?page=disco747-fix-emoji
 * 2. Clicca "Aggiorna Template"
 * 3. Le emoji corrotte saranno rimosse dai template WhatsApp
 * 
 * @package Disco747_CRM
 * @version 1.0.0
 */

// Sicurezza
if (!defined('ABSPATH')) {
    exit('Accesso diretto non consentito');
}

// Solo admin
if (!current_user_can('manage_options')) {
    wp_die('Permessi insufficienti');
}

global $wpdb;
$sequences_table = $wpdb->prefix . 'disco747_funnel_sequences';

// TEMPLATE WHATSAPP SENZA EMOJI (puliti)
$clean_templates = array(
    // Step 1: Invio Preventivo
    array(
        'id' => 1,
        'whatsapp_text' => 'Ciao {{nome_referente}}!

Il tuo preventivo per {{tipo_evento}} del {{data_evento}} è pronto!

Importo: €{{importo_totale}}
Acconto per conferma: €{{acconto}}

Hai domande? Scrivici pure!'
    ),
    // Step 2: Follow-up
    array(
        'id' => 2,
        'whatsapp_text' => 'Ciao {{nome_referente}}! 

Hai visto il preventivo per il {{data_evento}}? 

Se hai domande siamo qui!'
    ),
    // Step 3: Urgenza
    array(
        'id' => 3,
        'whatsapp_text' => 'ULTIMA CHIAMATA {{nome_referente}}!

La data {{data_evento}} sta per essere presa da altri.

Confermi con l\'acconto di €{{acconto}}? 

Rispondimi subito!'
    )
);

echo '<div style="max-width: 800px; margin: 50px auto; font-family: Arial, sans-serif; padding: 20px;">';
echo '<h1 style="color: #c28a4d;">🔧 Fix Emoji Template Funnel</h1>';

// Se richiesto aggiornamento
if (isset($_POST['fix_templates']) && wp_verify_nonce($_POST['_wpnonce'], 'disco747_fix_emoji')) {
    
    echo '<div style="background: #e7f3ff; padding: 20px; border-radius: 8px; margin-bottom: 20px;">';
    echo '<h3>⏳ Aggiornamento in corso...</h3>';
    
    $updated = 0;
    $errors = 0;
    
    foreach ($clean_templates as $template) {
        $result = $wpdb->update(
            $sequences_table,
            array('whatsapp_text' => $template['whatsapp_text']),
            array('id' => $template['id']),
            array('%s'),
            array('%d')
        );
        
        if ($result !== false) {
            echo '<p style="color: green;">✅ Template #' . $template['id'] . ' aggiornato</p>';
            $updated++;
        } else {
            echo '<p style="color: red;">❌ Errore template #' . $template['id'] . '</p>';
            $errors++;
        }
    }
    
    echo '<hr>';
    echo '<p style="font-size: 18px; font-weight: bold; color: #28a745;">';
    echo "✅ Completato! {$updated} template aggiornati";
    if ($errors > 0) {
        echo " ({$errors} errori)";
    }
    echo '</p>';
    echo '</div>';
    
} else {
    // Mostra form
    echo '<div style="background: #fff3cd; padding: 20px; border-radius: 8px; border-left: 4px solid #ffc107; margin-bottom: 20px;">';
    echo '<h3>⚠️ Attenzione</h3>';
    echo '<p>Questa operazione sostituirà i testi WhatsApp dei primi 3 template funnel con versioni <strong>SENZA EMOJI</strong>.</p>';
    echo '<p>I template email NON saranno modificati.</p>';
    echo '</div>';
    
    // Mostra template correnti
    echo '<h3>📝 Template attuali:</h3>';
    
    $current_templates = $wpdb->get_results(
        "SELECT id, step_name, whatsapp_text FROM {$sequences_table} WHERE id IN (1,2,3) ORDER BY id",
        ARRAY_A
    );
    
    foreach ($current_templates as $template) {
        echo '<div style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin-bottom: 15px;">';
        echo '<h4>Template #' . $template['id'] . ': ' . esc_html($template['step_name']) . '</h4>';
        echo '<pre style="background: white; padding: 10px; border-radius: 4px; overflow-x: auto;">';
        echo esc_html($template['whatsapp_text']);
        echo '</pre>';
        echo '</div>';
    }
    
    echo '<hr style="margin: 30px 0;">';
    
    echo '<h3>✨ Nuovi template (SENZA EMOJI):</h3>';
    
    foreach ($clean_templates as $template) {
        echo '<div style="background: #d4edda; padding: 15px; border-radius: 8px; margin-bottom: 15px; border-left: 4px solid #28a745;">';
        echo '<h4>Template #' . $template['id'] . '</h4>';
        echo '<pre style="background: white; padding: 10px; border-radius: 4px;">';
        echo esc_html($template['whatsapp_text']);
        echo '</pre>';
        echo '</div>';
    }
    
    echo '<form method="post" style="margin-top: 30px;">';
    wp_nonce_field('disco747_fix_emoji');
    echo '<button type="submit" name="fix_templates" value="1" style="background: linear-gradient(135deg, #28a745 0%, #20c997 100%); color: white; padding: 15px 40px; border: none; border-radius: 25px; font-weight: 600; font-size: 18px; cursor: pointer; box-shadow: 0 4px 15px rgba(40, 167, 69, 0.3);">';
    echo '🔧 Aggiorna Template Adesso';
    echo '</button>';
    echo '</form>';
}

echo '<div style="margin-top: 30px; padding: 15px; background: #f8f9fa; border-radius: 8px;">';
echo '<p style="color: #6c757d; font-size: 14px; margin: 0;">';
echo '💡 <strong>Suggerimento:</strong> Dopo l\'aggiornamento, i nuovi messaggi WhatsApp non avranno più emoji corrotte (rombi con ?).';
echo '</p>';
echo '</div>';

echo '</div>';
