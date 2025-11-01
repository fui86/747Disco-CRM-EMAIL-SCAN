<?php
/**
 * Form Preventivo con 3 Pulsanti Enhanced - 747 Disco CRM
 * Versione 12.1.0 - AGGIUNTO: Pulsanti PDF, Email, WhatsApp
 * 
 * MODIFICHE:
 * - Aggiunti 3 pulsanti post-creazione preventivo
 * - Generazione PDF on-demand
 * - Invio Email con template selezionabile
 * - Invio WhatsApp con template selezionabile
 * - Grafica migliorata ma rispettando lo stile esistente
 */

if (!defined('ABSPATH')) exit;

// ============================================================================
// MODALITÃ€ MODIFICA: Carica dati esistenti se presente edit_id
// ============================================================================
$is_edit_mode = false;
$edit_data = null;
$edit_id = 0;

if (!empty($_GET['edit_id'])) {
    $is_edit_mode = true;
    $edit_id = intval($_GET['edit_id']);
    
    global $wpdb;
    $table = $wpdb->prefix . 'disco747_preventivi';
    
    $edit_data = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $edit_id), ARRAY_A);
    
    if ($edit_data) {
        $edit_data['email'] = $edit_data['mail'] ?? $edit_data['email'] ?? '';
        $edit_data['telefono'] = $edit_data['cellulare'] ?? $edit_data['telefono'] ?? '';
        $edit_data['importo_totale'] = $edit_data['importo_preventivo'] ?? $edit_data['importo_totale'] ?? 0;
    }
}

// Helper function per ottenere valore del campo
function get_field_value($field_name, $default = '', $edit_data = null) {
    if ($edit_data && isset($edit_data[$field_name])) {
        return esc_attr($edit_data[$field_name]);
    }
    return esc_attr($default);
}

// Valori predefiniti
$default_values = array(
    'tipo_menu' => 'Menu 7',
    'numero_invitati' => 50,
    'orario_inizio' => '20:30',
    'orario_fine' => '01:30',
    'omaggio1' => 'Crepes alla Nutella',
    'omaggio2' => 'Servizio Fotografico',
    'omaggio3' => '',
    'importo_base' => 0,
    'extra1_importo' => 0,
    'extra2_importo' => 0,
    'extra3_importo' => 0,
    'acconto' => 0
);

$sconti_menu = array(
    'Menu 7' => 400,
    'Menu 7-4' => 500,
    'Menu 7-4-7' => 600
);

$page_title = $is_edit_mode ? 'Modifica Preventivo #' . $edit_id : 'Nuovo Preventivo';
$submit_text = $is_edit_mode ? '💾 Aggiorna Preventivo' : '💾 Salva Preventivo';

?>

<div class="wrap disco747-form-preventivo" style="max-width: 1200px; margin: 30px auto; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;">
    
    <!-- Header -->
    <div style="background: linear-gradient(135deg, #2b1e1a 0%, #1a1310 100%); padding: 30px; border-radius: 15px 15px 0 0; box-shadow: 0 4px 15px rgba(0,0,0,0.2);">
        <h1 style="color: #c28a4d; margin: 0; font-size: 2.2rem; font-weight: 700; text-transform: uppercase; letter-spacing: 2px;">
            🎉 <?php echo esc_html($page_title); ?>
        </h1>
        <p style="color: #a0a0a0; margin: 10px 0 0 0; font-size: 1rem;">
            Compila tutti i campi per generare il preventivo personalizzato
        </p>
    </div>

    <!-- Form principale -->
    <form id="disco747-form-preventivo" method="post" style="background: white; border-radius: 0 0 15px 15px; box-shadow: 0 4px 20px rgba(0,0,0,0.1); overflow: hidden;">
        
        <?php wp_nonce_field('disco747_preventivo', 'disco747_preventivo_nonce'); ?>
        <input type="hidden" name="is_edit_mode" value="<?php echo $is_edit_mode ? '1' : '0'; ?>">
        <?php if ($is_edit_mode): ?>
            <input type="hidden" name="edit_id" value="<?php echo $edit_id; ?>">
        <?php endif; ?>

        <!-- SEZIONE 1: Dati Referente -->
        <div style="background: linear-gradient(135deg, #c28a4d 0%, #a67339 100%); color: white; padding: 20px;">
            <h2 style="margin: 0; display: flex; align-items: center; gap: 10px; font-size: 1.4rem;">
                👤 Dati Referente
            </h2>
        </div>
        
        <div style="padding: 30px; border-bottom: 1px solid #e9ecef;">
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px;">
                
                <div>
                    <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #2b1e1a;">
                        Nome * <span style="color: #dc3545;">●</span>
                    </label>
                    <input type="text" name="nome_referente" required
                           value="<?php echo get_field_value('nome_referente', '', $edit_data); ?>"
                           style="width: 100%; padding: 12px; border: 2px solid #e9ecef; border-radius: 8px; font-size: 14px; transition: border-color 0.3s ease;"
                           placeholder="Es: Mario">
                </div>
                
                <div>
                    <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #2b1e1a;">
                        Cognome * <span style="color: #dc3545;">●</span>
                    </label>
                    <input type="text" name="cognome_referente" required
                           value="<?php echo get_field_value('cognome_referente', '', $edit_data); ?>"
                           style="width: 100%; padding: 12px; border: 2px solid #e9ecef; border-radius: 8px; font-size: 14px; transition: border-color 0.3s ease;"
                           placeholder="Es: Rossi">
                </div>
                
                <div>
                    <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #2b1e1a;">
                        📱 Cellulare * <span style="color: #dc3545;">●</span>
                    </label>
                    <input type="tel" name="cellulare" required
                           value="<?php echo get_field_value('telefono', '', $edit_data); ?>"
                           style="width: 100%; padding: 12px; border: 2px solid #e9ecef; border-radius: 8px; font-size: 14px; transition: border-color 0.3s ease;"
                           placeholder="Es: 333 1234567">
                </div>
                
                <div>
                    <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #2b1e1a;">
                        📧 Email * <span style="color: #dc3545;">●</span>
                    </label>
                    <input type="email" name="mail" required
                           value="<?php echo get_field_value('email', '', $edit_data); ?>"
                           style="width: 100%; padding: 12px; border: 2px solid #e9ecef; border-radius: 8px; font-size: 14px; transition: border-color 0.3s ease;"
                           placeholder="Es: mario.rossi@email.com">
                </div>
                
            </div>
        </div>

        <!-- SEZIONE 2: Dettagli Evento -->
        <div style="background: linear-gradient(135deg, #c28a4d 0%, #a67339 100%); color: white; padding: 20px;">
            <h2 style="margin: 0; display: flex; align-items: center; gap: 10px; font-size: 1.4rem;">
                🎊 Dettagli Evento
            </h2>
        </div>
        
        <div style="padding: 30px; border-bottom: 1px solid #e9ecef;">
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px;">
                
                <div>
                    <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #2b1e1a;">
                        📅 Data Evento * <span style="color: #dc3545;">●</span>
                    </label>
                    <input type="date" name="data_evento" required
                           value="<?php echo get_field_value('data_evento', '', $edit_data); ?>"
                           style="width: 100%; padding: 12px; border: 2px solid #e9ecef; border-radius: 8px; font-size: 14px; transition: border-color 0.3s ease;">
                </div>
                
                <div>
                    <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #2b1e1a;">
                        🎉 Tipo Evento * <span style="color: #dc3545;">●</span>
                    </label>
                    <input type="text" name="tipo_evento" required
                           value="<?php echo get_field_value('tipo_evento', '', $edit_data); ?>"
                           style="width: 100%; padding: 12px; border: 2px solid #e9ecef; border-radius: 8px; font-size: 14px; transition: border-color 0.3s ease;"
                           placeholder="Es: Festa 18 anni, Compleanno, Matrimonio">
                </div>
                
                <div>
                    <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #2b1e1a;">
                        🍽ï¸ Tipo Menu * <span style="color: #dc3545;">●</span>
                    </label>
                    <select name="tipo_menu" id="tipo_menu" required
                            style="width: 100%; padding: 12px; border: 2px solid #e9ecef; border-radius: 8px; font-size: 14px; transition: border-color 0.3s ease;">
                        <option value="Menu 7" <?php echo get_field_value('tipo_menu', 'Menu 7', $edit_data) == 'Menu 7' ? 'selected' : ''; ?>>Menu 7</option>
                        <option value="Menu 7-4" <?php echo get_field_value('tipo_menu', '', $edit_data) == 'Menu 7-4' ? 'selected' : ''; ?>>Menu 7-4</option>
                        <option value="Menu 7-4-7" <?php echo get_field_value('tipo_menu', '', $edit_data) == 'Menu 7-4-7' ? 'selected' : ''; ?>>Menu 7-4-7</option>
                    </select>
                </div>
                
                <div>
                    <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #2b1e1a;">
                        👥 Numero Invitati * <span style="color: #dc3545;">●</span>
                    </label>
                    <input type="number" name="numero_invitati" min="1" required
                           value="<?php echo get_field_value('numero_invitati', '50', $edit_data); ?>"
                           style="width: 100%; padding: 12px; border: 2px solid #e9ecef; border-radius: 8px; font-size: 14px; transition: border-color 0.3s ease;"
                           placeholder="Es: 50">
                </div>
                
            </div>
            
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-top: 20px;">
                
                <div>
                    <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #2b1e1a;">
                        🕐 Orario Inizio *
                    </label>
                    <input type="time" name="orario_inizio"
                           value="<?php echo get_field_value('orario_inizio', '20:30', $edit_data); ?>"
                           style="width: 100%; padding: 12px; border: 2px solid #e9ecef; border-radius: 8px; font-size: 14px; transition: border-color 0.3s ease;">
                </div>
                
                <div>
                    <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #2b1e1a;">
                        🕐 Orario Fine *
                    </label>
                    <input type="time" name="orario_fine"
                           value="<?php echo get_field_value('orario_fine', '01:30', $edit_data); ?>"
                           style="width: 100%; padding: 12px; border: 2px solid #e9ecef; border-radius: 8px; font-size: 14px; transition: border-color 0.3s ease;">
                </div>
                
            </div>
        </div>

        <!-- SEZIONE 3: Omaggi Inclusi -->
        <div style="background: linear-gradient(135deg, #28a745 0%, #20c997 100%); color: white; padding: 20px;">
            <h2 style="margin: 0; display: flex; align-items: center; gap: 10px; font-size: 1.4rem;">
                🎁 Omaggi Inclusi nel Pacchetto
            </h2>
        </div>
        
        <div style="padding: 30px; border-bottom: 1px solid #e9ecef;">
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px;">
                
                <div>
                    <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #2b1e1a;">
                        🎁 Omaggio 1
                    </label>
                    <input type="text" name="omaggio1"
                           value="<?php echo get_field_value('omaggio1', $default_values['omaggio1'], $edit_data); ?>"
                           style="width: 100%; padding: 12px; border: 2px solid #e9ecef; border-radius: 8px; font-size: 14px; transition: border-color 0.3s ease;"
                           placeholder="Es: Crepes alla Nutella">
                </div>
                
                <div>
                    <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #2b1e1a;">
                        🎁 Omaggio 2
                    </label>
                    <input type="text" name="omaggio2"
                           value="<?php echo get_field_value('omaggio2', $default_values['omaggio2'], $edit_data); ?>"
                           style="width: 100%; padding: 12px; border: 2px solid #e9ecef; border-radius: 8px; font-size: 14px; transition: border-color 0.3s ease;"
                           placeholder="Es: Servizio Fotografico">
                </div>
                
                <div>
                    <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #2b1e1a;">
                        🎁 Omaggio 3
                    </label>
                    <input type="text" name="omaggio3"
                           value="<?php echo get_field_value('omaggio3', '', $edit_data); ?>"
                           style="width: 100%; padding: 12px; border: 2px solid #e9ecef; border-radius: 8px; font-size: 14px; transition: border-color 0.3s ease;"
                           placeholder="Omaggio aggiuntivo (opzionale)">
                </div>
                
            </div>
        </div>

        <!-- SEZIONE 4: Extra a Pagamento -->
        <div style="background: linear-gradient(135deg, #c28a4d 0%, #a67339 100%); color: white; padding: 20px;">
            <h2 style="margin: 0; display: flex; align-items: center; gap: 10px; font-size: 1.4rem;">
                💰 Extra a Pagamento
            </h2>
        </div>
        
        <div style="padding: 30px; border-bottom: 1px solid #e9ecef;">
            
            <!-- Extra 1 -->
            <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 15px; margin-bottom: 15px;">
                <div>
                    <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #2b1e1a;">
                        ➕ Extra 1 - Descrizione
                    </label>
                    <input type="text" name="extra1"
                           value="<?php echo get_field_value('extra1', '', $edit_data); ?>"
                           style="width: 100%; padding: 12px; border: 2px solid #e9ecef; border-radius: 8px; font-size: 14px; transition: border-color 0.3s ease;"
                           placeholder="Es: Decorazioni Extra">
                </div>
                <div>
                    <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #2b1e1a;">
                        💵 Importo Extra 1 (€)
                    </label>
                    <input type="number" name="extra1_importo" id="extra1_importo" min="0" step="0.01"
                           value="<?php echo get_field_value('extra1_importo', '0', $edit_data); ?>"
                           style="width: 100%; padding: 12px; border: 2px solid #e9ecef; border-radius: 8px; font-size: 14px; transition: border-color 0.3s ease;"
                           placeholder="0.00">
                </div>
            </div>
            
            <!-- Extra 2 -->
            <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 15px; margin-bottom: 15px;">
                <div>
                    <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #2b1e1a;">
                        ➕ Extra 2 - Descrizione
                    </label>
                    <input type="text" name="extra2"
                           value="<?php echo get_field_value('extra2', '', $edit_data); ?>"
                           style="width: 100%; padding: 12px; border: 2px solid #e9ecef; border-radius: 8px; font-size: 14px; transition: border-color 0.3s ease;"
                           placeholder="Es: Servizio Video">
                </div>
                <div>
                    <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #2b1e1a;">
                        💵 Importo Extra 2 (€)
                    </label>
                    <input type="number" name="extra2_importo" id="extra2_importo" min="0" step="0.01"
                           value="<?php echo get_field_value('extra2_importo', '0', $edit_data); ?>"
                           style="width: 100%; padding: 12px; border: 2px solid #e9ecef; border-radius: 8px; font-size: 14px; transition: border-color 0.3s ease;"
                           placeholder="0.00">
                </div>
            </div>
            
            <!-- Extra 3 -->
            <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 15px;">
                <div>
                    <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #2b1e1a;">
                        ➕ Extra 3 - Descrizione
                    </label>
                    <input type="text" name="extra3"
                           value="<?php echo get_field_value('extra3', '', $edit_data); ?>"
                           style="width: 100%; padding: 12px; border: 2px solid #e9ecef; border-radius: 8px; font-size: 14px; transition: border-color 0.3s ease;"
                           placeholder="Es: Torta Personalizzata">
                </div>
                <div>
                    <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #2b1e1a;">
                        💵 Importo Extra 3 (€)
                    </label>
                    <input type="number" name="extra3_importo" id="extra3_importo" min="0" step="0.01"
                           value="<?php echo get_field_value('extra3_importo', '0', $edit_data); ?>"
                           style="width: 100%; padding: 12px; border: 2px solid #e9ecef; border-radius: 8px; font-size: 14px; transition: border-color 0.3s ease;"
                           placeholder="0.00">
                </div>
            </div>
            
        </div>

        <!-- SEZIONE 5: Riepilogo Economico -->
        <div style="background: linear-gradient(135deg, #ffc107 0%, #ff9800 100%); color: white; padding: 20px;">
            <h2 style="margin: 0; display: flex; align-items: center; gap: 10px; font-size: 1.4rem;">
                💰 Riepilogo Economico
            </h2>
        </div>
        
        <div style="padding: 30px; border-bottom: 1px solid #e9ecef;">
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px;">
                
                <div>
                    <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #2b1e1a;">
                        💵 Importo Base Preventivo (€) *
                    </label>
                    <input type="number" name="importo_preventivo" id="importo_preventivo" min="0" step="0.01" required
                           value="<?php echo get_field_value('importo_totale', '0', $edit_data); ?>"
                           style="width: 100%; padding: 12px; border: 2px solid #e9ecef; border-radius: 8px; font-size: 14px; transition: border-color 0.3s ease;"
                           placeholder="0.00">
                </div>
                
                <div>
                    <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #2b1e1a;">
                        💳 Acconto Versato (€)
                    </label>
                    <input type="number" name="acconto" id="acconto" min="0" step="0.01"
                           value="<?php echo get_field_value('acconto', '0', $edit_data); ?>"
                           style="width: 100%; padding: 12px; border: 2px solid #e9ecef; border-radius: 8px; font-size: 14px; transition: border-color 0.3s ease;"
                           placeholder="0.00">
                </div>
                
            </div>
            
            <!-- Display Calcoli Automatici -->
            <div style="background: #f8f9fa; padding: 20px; border-radius: 8px; margin-top: 20px;">
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                    
                    <div style="text-align: center;">
                        <div style="font-size: 0.9rem; color: #6c757d; margin-bottom: 5px;">Sconto Menu</div>
                        <div id="sconto_valore" style="font-size: 1.5rem; font-weight: 700; color: #28a745;">€ 0</div>
                    </div>
                    
                    <div style="text-align: center;">
                        <div style="font-size: 0.9rem; color: #6c757d; margin-bottom: 5px;">Totale Lordo</div>
                        <div id="totale_lordo_display" style="font-size: 1.5rem; font-weight: 700; color: #c28a4d;">€ 0</div>
                    </div>
                    
                    <div style="text-align: center;">
                        <div style="font-size: 0.9rem; color: #6c757d; margin-bottom: 5px;">Saldo Residuo</div>
                        <div id="saldo_display" style="font-size: 1.5rem; font-weight: 700; color: #dc3545;">€ 0</div>
                    </div>
                    
                </div>
            </div>
        </div>

        <!-- SEZIONE 6: Note Aggiuntive -->
        <div style="background: linear-gradient(135deg, #6c757d 0%, #5a6268 100%); color: white; padding: 20px;">
            <h2 style="margin: 0; display: flex; align-items: center; gap: 10px; font-size: 1.4rem;">
                📝 Note Aggiuntive
            </h2>
        </div>
        
        <div style="padding: 30px; border-bottom: 1px solid #e9ecef;">
            <div>
                <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #2b1e1a;">
                    📋 Note e Richieste Speciali
                </label>
                <textarea name="note_aggiuntive" rows="4"
                          style="width: 100%; padding: 12px; border: 2px solid #e9ecef; border-radius: 8px; font-size: 14px; transition: border-color 0.3s ease; resize: vertical;"
                          placeholder="Inserisci note aggiuntive, richieste speciali o dettagli importanti per l'evento..."><?php echo get_field_value('note_aggiuntive', '', $edit_data); ?></textarea>
            </div>
        </div>

        <!-- SEZIONE 7: Note Interne -->
        <div style="background: linear-gradient(135deg, #495057 0%, #343a40 100%); color: white; padding: 20px;">
            <h2 style="margin: 0; display: flex; align-items: center; gap: 10px; font-size: 1.4rem;">
                🔒 Note Interne
            </h2>
        </div>
        
        <div style="padding: 30px; border-bottom: 1px solid #e9ecef;">
            <div>
                <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #2b1e1a;">
                    🔒 Note Interne (Visibili solo al team)
                </label>
                <textarea name="note_interne" rows="3"
                          style="width: 100%; padding: 12px; border: 2px solid #e9ecef; border-radius: 8px; font-size: 14px; transition: border-color 0.3s ease; resize: vertical; background: #f8f9fa;"
                          placeholder="Note per uso interno del team (non visibili al cliente)..."><?php echo get_field_value('note_interne', '', $edit_data); ?></textarea>
            </div>
            
            <div style="background: #fff3cd; padding: 15px; border-radius: 8px; margin-top: 15px; border-left: 4px solid #ffc107;">
                <div style="display: flex; align-items: center; gap: 8px;">
                    <span style="color: #856404;">âš ï¸</span>
                    <small style="color: #856404; font-weight: 500;">
                        Queste note sono riservate al team e non verranno mostrate al cliente nei preventivi o nelle comunicazioni.
                    </small>
                </div>
            </div>
        </div>

        <!-- PULSANTI AZIONE PRINCIPALI -->
        <div style="background: #f8f9fa; padding: 30px; text-align: center; border-bottom: 3px solid #c28a4d;">
            <div style="display: flex; gap: 15px; justify-content: center; flex-wrap: wrap;">
                
                <!-- Pulsante Salva -->
                <button type="submit" name="action" value="save_preventivo"
                        style="background: linear-gradient(135deg, #28a745 0%, #20c997 100%); color: white; padding: 15px 30px; border: none; border-radius: 25px; font-weight: 600; font-size: 16px; cursor: pointer; box-shadow: 0 4px 15px rgba(40, 167, 69, 0.3); transition: all 0.3s ease;">
                    <?php echo $submit_text; ?> 💾
                </button>
                
                <?php if ($is_edit_mode): ?>
                <!-- Pulsante Salva e Rigenera File -->
                <button type="submit" name="action" value="save_and_regenerate"
                        style="background: linear-gradient(135deg, #17a2b8 0%, #138496 100%); color: white; padding: 15px 30px; border: none; border-radius: 25px; font-weight: 600; font-size: 16px; cursor: pointer; box-shadow: 0 4px 15px rgba(23, 162, 184, 0.3); transition: all 0.3s ease;">
                    Salva e Rigenera File 📄
                </button>
                <?php endif; ?>
                
                <!-- Pulsante Salva come Bozza -->
                <button type="submit" name="action" value="save_draft"
                        style="background: linear-gradient(135deg, #6c757d 0%, #5a6268 100%); color: white; padding: 15px 30px; border: none; border-radius: 25px; font-weight: 600; font-size: 16px; cursor: pointer; box-shadow: 0 4px 15px rgba(108, 117, 125, 0.3); transition: all 0.3s ease;">
                    Salva come Bozza 📝
                </button>
                
                <!-- Pulsante Annulla -->
                <a href="<?php echo esc_url(admin_url('admin.php?page=disco747-crm')); ?>" 
                   style="background: rgba(108, 117, 125, 0.1); color: #6c757d; padding: 15px 30px; border: 2px solid #6c757d; border-radius: 25px; font-weight: 600; font-size: 16px; text-decoration: none; display: inline-block; transition: all 0.3s ease;">
                    â† Annulla
                </a>
                
            </div>
        </div>
        
    </form>
    
    <!-- ============================================================================ -->
    <!-- PULSANTI POST-CREAZIONE (PDF, EMAIL, WHATSAPP) -->
    <!-- ============================================================================ -->
    
    <div id="post-creation-actions" style="<?php echo $is_edit_mode ? '' : 'display: none;'; ?> background: white; border-radius: 15px; box-shadow: 0 4px 20px rgba(0,0,0,0.15); margin-top: 30px; overflow: hidden;">
        
        <!-- Header Sezione -->
        <div style="background: linear-gradient(135deg, #c28a4d 0%, #a67339 100%); color: white; padding: 25px; text-align: center;">
            <h2 style="margin: 0; font-size: 1.6rem; font-weight: 700;">
                ✅ Preventivo Creato con Successo!
            </h2>
            <p style="margin: 10px 0 0 0; font-size: 1rem; opacity: 0.9;">
                Ora puoi generare il PDF, inviare l'email o il messaggio WhatsApp al cliente
            </p>
        </div>
        
        <!-- Container Pulsanti -->
        <div style="padding: 40px; background: #f8f9fa;">
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 25px; max-width: 1000px; margin: 0 auto;">
                
                <!-- PULSANTE 1: Genera PDF -->
                <div style="background: white; padding: 25px; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); text-align: center; transition: transform 0.3s ease;">
                    <div style="font-size: 3rem; margin-bottom: 15px;">📄</div>
                    <h3 style="margin: 0 0 10px 0; color: #2b1e1a; font-size: 1.2rem;">Genera PDF</h3>
                    <p style="color: #6c757d; font-size: 0.9rem; margin-bottom: 20px;">
                        Crea il file PDF del preventivo e scaricalo immediatamente
                    </p>
                    <button type="button" id="btn-generate-pdf"
                            style="background: linear-gradient(135deg, #dc3545 0%, #c82333 100%); color: white; padding: 12px 25px; border: none; border-radius: 25px; font-weight: 600; font-size: 15px; cursor: pointer; box-shadow: 0 4px 12px rgba(220, 53, 69, 0.3); transition: all 0.3s ease; width: 100%;">
                        📄 Genera e Scarica PDF
                    </button>
                </div>
                
                <!-- PULSANTE 2: Invia Email -->
                <div style="background: white; padding: 25px; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); text-align: center; transition: transform 0.3s ease;">
                    <div style="font-size: 3rem; margin-bottom: 15px;">📧</div>
                    <h3 style="margin: 0 0 10px 0; color: #2b1e1a; font-size: 1.2rem;">Invia Email</h3>
                    <p style="color: #6c757d; font-size: 0.9rem; margin-bottom: 20px;">
                        Invia l'email al cliente con il PDF allegato
                    </p>
                    <button type="button" id="btn-send-email"
                            style="background: linear-gradient(135deg, #007bff 0%, #0056b3 100%); color: white; padding: 12px 25px; border: none; border-radius: 25px; font-weight: 600; font-size: 15px; cursor: pointer; box-shadow: 0 4px 12px rgba(0, 123, 255, 0.3); transition: all 0.3s ease; width: 100%;">
                        📧 Invia Email
                    </button>
                </div>
                
                <!-- PULSANTE 3: Invia WhatsApp -->
                <div style="background: white; padding: 25px; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); text-align: center; transition: transform 0.3s ease;">
                    <div style="font-size: 3rem; margin-bottom: 15px;">💬</div>
                    <h3 style="margin: 0 0 10px 0; color: #2b1e1a; font-size: 1.2rem;">Invia WhatsApp</h3>
                    <p style="color: #6c757d; font-size: 0.9rem; margin-bottom: 20px;">
                        Apri WhatsApp con il messaggio precompilato
                    </p>
                    <button type="button" id="btn-send-whatsapp"
                            style="background: linear-gradient(135deg, #25D366 0%, #128C7E 100%); color: white; padding: 12px 25px; border: none; border-radius: 25px; font-weight: 600; font-size: 15px; cursor: pointer; box-shadow: 0 4px 12px rgba(37, 211, 102, 0.3); transition: all 0.3s ease; width: 100%;">
                        💬 Invia WhatsApp
                    </button>
                </div>
                
            </div>
        </div>
        
    </div>
    
</div>

<!-- ============================================================================ -->
<!-- MODAL: Selezione Template Email -->
<!-- ============================================================================ -->
<div id="modal-email-template" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); z-index: 10000; justify-content: center; align-items: center;">
    <div style="background: white; border-radius: 15px; max-width: 600px; width: 90%; max-height: 80vh; overflow-y: auto; box-shadow: 0 10px 40px rgba(0,0,0,0.3);">
        
        <div style="background: linear-gradient(135deg, #007bff 0%, #0056b3 100%); color: white; padding: 25px; border-radius: 15px 15px 0 0;">
            <h3 style="margin: 0; font-size: 1.4rem;">📧 Seleziona Template Email</h3>
        </div>
        
        <div style="padding: 30px;">
            
            <label style="display: block; margin-bottom: 10px; font-weight: 600; color: #2b1e1a;">
                Scegli il template da inviare:
            </label>
            
            <select id="email-template-select" style="width: 100%; padding: 12px; border: 2px solid #e9ecef; border-radius: 8px; font-size: 14px; margin-bottom: 20px;">
                <option value="1">Template 1 - Standard</option>
                <option value="2">Template 2 - Promozionale</option>
                <option value="3">Template 3 - Formale</option>
            </select>
            
            <div style="margin-bottom: 20px;">
                <label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                    <input type="checkbox" id="email-attach-pdf" checked style="width: 18px; height: 18px; cursor: pointer;">
                    <span style="font-weight: 600; color: #2b1e1a;">Allega PDF al messaggio</span>
                </label>
            </div>
            
            <div style="background: #e7f3ff; padding: 15px; border-radius: 8px; border-left: 4px solid #007bff; margin-bottom: 25px;">
                <p style="margin: 0; color: #004085; font-size: 0.9rem;">
                    ℹï¸ L'email sarÃ  inviata da <strong>eventi@747disco.it</strong> con copia a <strong>info@747disco.it</strong>
                </p>
            </div>
            
            <div style="display: flex; gap: 15px; justify-content: flex-end;">
                <button type="button" id="cancel-email-modal"
                        style="background: #6c757d; color: white; padding: 12px 25px; border: none; border-radius: 25px; font-weight: 600; cursor: pointer;">
                    Annulla
                </button>
                <button type="button" id="confirm-send-email"
                        style="background: linear-gradient(135deg, #007bff 0%, #0056b3 100%); color: white; padding: 12px 25px; border: none; border-radius: 25px; font-weight: 600; cursor: pointer; box-shadow: 0 4px 12px rgba(0, 123, 255, 0.3);">
                    📧 Invia Email
                </button>
            </div>
            
        </div>
        
    </div>
</div>

<!-- ============================================================================ -->
<!-- MODAL: Selezione Template WhatsApp -->
<!-- ============================================================================ -->
<div id="modal-whatsapp-template" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); z-index: 10000; justify-content: center; align-items: center;">
    <div style="background: white; border-radius: 15px; max-width: 600px; width: 90%; max-height: 80vh; overflow-y: auto; box-shadow: 0 10px 40px rgba(0,0,0,0.3);">
        
        <div style="background: linear-gradient(135deg, #25D366 0%, #128C7E 100%); color: white; padding: 25px; border-radius: 15px 15px 0 0;">
            <h3 style="margin: 0; font-size: 1.4rem;">💬 Seleziona Template WhatsApp</h3>
        </div>
        
        <div style="padding: 30px;">
            
            <label style="display: block; margin-bottom: 10px; font-weight: 600; color: #2b1e1a;">
                Scegli il template da inviare:
            </label>
            
            <select id="whatsapp-template-select" style="width: 100%; padding: 12px; border: 2px solid #e9ecef; border-radius: 8px; font-size: 14px; margin-bottom: 20px;">
                <option value="1">Template 1 - Cordiale</option>
                <option value="2">Template 2 - Promozionale</option>
                <option value="3">Template 3 - Breve</option>
            </select>
            
            <div style="background: #d4edda; padding: 15px; border-radius: 8px; border-left: 4px solid #28a745; margin-bottom: 25px;">
                <p style="margin: 0; color: #155724; font-size: 0.9rem;">
                    ℹï¸ VerrÃ  aperta l'app WhatsApp con il messaggio giÃ  precompilato, pronto per essere inviato al cliente
                </p>
            </div>
            
            <div style="display: flex; gap: 15px; justify-content: flex-end;">
                <button type="button" id="cancel-whatsapp-modal"
                        style="background: #6c757d; color: white; padding: 12px 25px; border: none; border-radius: 25px; font-weight: 600; cursor: pointer;">
                    Annulla
                </button>
                <button type="button" id="confirm-send-whatsapp"
                        style="background: linear-gradient(135deg, #25D366 0%, #128C7E 100%); color: white; padding: 12px 25px; border: none; border-radius: 25px; font-weight: 600; cursor: pointer; box-shadow: 0 4px 12px rgba(37, 211, 102, 0.3);">
                    💬 Apri WhatsApp
                </button>
            </div>
            
        </div>
        
    </div>
</div>

<!-- ============================================================================ -->
<!-- JAVASCRIPT: Calcoli Automatici (ORIGINALE - NON MODIFICATO) -->
<!-- ============================================================================ -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    console.log('✅ Form Preventivo 747 Disco caricato');
    
    // Sconti menu
    const scontiMenu = {
        'Menu 7': 400,
        'Menu 7-4': 500,
        'Menu 7-4-7': 600
    };
    
    // Funzione calcolo importi
    function calcolaImporti() {
        const tipoMenu = document.getElementById('tipo_menu')?.value || 'Menu 7';
        const importoBase = parseFloat(document.getElementById('importo_preventivo')?.value || 0);
        const extra1 = parseFloat(document.getElementById('extra1_importo')?.value || 0);
        const extra2 = parseFloat(document.getElementById('extra2_importo')?.value || 0);
        const extra3 = parseFloat(document.getElementById('extra3_importo')?.value || 0);
        const acconto = parseFloat(document.getElementById('acconto')?.value || 0);
        
        const sconto = scontiMenu[tipoMenu] || 0;
        const totaleExtra = extra1 + extra2 + extra3;
        const totaleLordo = importoBase + totaleExtra;
        const saldo = totaleLordo - acconto;
        
        // Aggiorna display
        if (document.getElementById('sconto_valore')) {
            document.getElementById('sconto_valore').textContent = '€ ' + sconto.toFixed(2);
        }
        if (document.getElementById('totale_lordo_display')) {
            document.getElementById('totale_lordo_display').textContent = '€ ' + totaleLordo.toFixed(2);
        }
        if (document.getElementById('saldo_display')) {
            document.getElementById('saldo_display').textContent = '€ ' + saldo.toFixed(2);
        }
    }
    
    // Event listeners per calcolo automatico
    ['tipo_menu', 'importo_preventivo', 'extra1_importo', 'extra2_importo', 'extra3_importo', 'acconto'].forEach(id => {
        const element = document.getElementById(id);
        if (element) {
            element.addEventListener('change', calcolaImporti);
            element.addEventListener('input', calcolaImporti);
        }
    });
    
    // Calcolo iniziale
    calcolaImporti();
});
</script>

<!-- ============================================================================ -->
<!-- JAVASCRIPT: AJAX Submit Form (ORIGINALE - NON MODIFICATO) -->
<!-- ============================================================================ -->
<script>
jQuery(document).ready(function($) {
    'use strict';
    
    console.log('🎯 [747Disco-AJAX] Form AJAX Handler caricato');
    
    // ========================================================================
    // INIZIALIZZA DATI IN MODALITÀ EDIT
    // ========================================================================
    <?php if ($is_edit_mode && $edit_data): ?>
    window.preventivoData = {
        preventivo_id: '<?php echo esc_js($edit_data['preventivo_id'] ?? ''); ?>',
        id: <?php echo intval($edit_id); ?>,
        db_id: <?php echo intval($edit_id); ?>,
        nome_referente: '<?php echo esc_js($edit_data['nome_referente'] ?? $edit_data['nome_cliente'] ?? ''); ?>',
        cognome_referente: '<?php echo esc_js($edit_data['cognome_referente'] ?? ''); ?>',
        nome_cliente: '<?php echo esc_js(trim(($edit_data['nome_referente'] ?? '') . ' ' . ($edit_data['cognome_referente'] ?? '')) ?: $edit_data['nome_cliente'] ?? ''); ?>',
        email: '<?php echo esc_js($edit_data['email'] ?? $edit_data['mail'] ?? ''); ?>',
        telefono: '<?php echo esc_js($edit_data['telefono'] ?? $edit_data['cellulare'] ?? ''); ?>',
        data_evento: '<?php echo esc_js($edit_data['data_evento'] ?? ''); ?>',
        tipo_evento: '<?php echo esc_js($edit_data['tipo_evento'] ?? ''); ?>',
        tipo_menu: '<?php echo esc_js($edit_data['tipo_menu'] ?? ''); ?>',
        numero_invitati: <?php echo intval($edit_data['numero_invitati'] ?? 0); ?>,
        importo_totale: <?php echo floatval($edit_data['importo_totale'] ?? $edit_data['importo_preventivo'] ?? 0); ?>,
        acconto: <?php echo floatval($edit_data['acconto'] ?? 0); ?>
    };
    console.log('✅ [Edit Mode] preventivoData inizializzato:', window.preventivoData);
    <?php endif; ?>
    
    const $form = $('#disco747-form-preventivo');
    const $submitButtons = $form.find('button[type="submit"]');
    
    if (!$form.length) {
        console.error('❌ Form preventivo non trovato!');
        return;
    }
    
    // INTERCETTA SUBMIT FORM
    $form.on('submit', function(e) {
        e.preventDefault();
        
        console.log('🚀 [747Disco-AJAX] Submit form intercettato');
        
        // Disabilita pulsanti
        $submitButtons.prop('disabled', true).html('â³ Salvataggio...');
        
        // Raccogli dati form
        const formData = new FormData(this);
        formData.append('action', 'disco747_save_preventivo');
        
        // Fix nonce
        const wpNonce = formData.get('disco747_preventivo_nonce');
        if (wpNonce) {
            formData.append('disco747_nonce', wpNonce);
        }
        
        // AJAX Request
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                console.log('✅ Risposta server:', response);
                
                if (response.success) {
                    // ✅ AGGIORNA DATI GLOBALI
                    window.preventivoData = response.data;
                    console.log('✅ [Save Success] preventivoData aggiornato:', window.preventivoData);
                    
                    // ✅ MOSTRA I PULSANTI
                    $('#post-creation-actions').slideDown(500);
                    
                    // ✅ MESSAGGIO SUCCESSO
                    alert('✅ ' + (response.data.message || 'Preventivo salvato con successo!'));
                    
                    // Scroll verso i pulsanti
                    setTimeout(function() {
                        $('html, body').animate({
                            scrollTop: $('#post-creation-actions').offset().top - 100
                        }, 600);
                    }, 100);
                    
                } else {
                    const errorMsg = response.data || response.message || 'Errore sconosciuto';
                    alert('❌ Errore: ' + errorMsg);
                }
            },
            error: function(xhr, status, error) {
                console.error('❌ Errore AJAX:', {xhr, status, error});
                alert('❌ Errore di connessione: ' + error);
            },
            complete: function() {
                $submitButtons.prop('disabled', false).html('💾 Salva Preventivo');
            }
        });
    });
});
</script>

<!-- ============================================================================ -->
<!-- JAVASCRIPT: Handler 3 Pulsanti (PDF, EMAIL, WHATSAPP) -->
<!-- ============================================================================ -->
<script>
jQuery(document).ready(function($) {
    'use strict';
    
    console.log('🎯 [747Disco-Actions] Handler pulsanti PDF/Email/WhatsApp caricato');
    
    // ========================================================================
    // PULSANTE 1: Genera PDF
    // ========================================================================
    $('#btn-generate-pdf').on('click', function() {
        console.log('📄 Genera PDF cliccato');
        console.log('📄 window.preventivoData:', window.preventivoData);
        
        if (!window.preventivoData || (!window.preventivoData.preventivo_id && !window.preventivoData.id && !window.preventivoData.db_id)) {
            alert('❌ Errore: Dati preventivo non disponibili');
            console.error('❌ preventivoData mancante o incompleto:', window.preventivoData);
            return;
        }
        
        var $thisBtn = $(this);
        $thisBtn.prop('disabled', true).html('â³ Generazione PDF...');
        
        var pdfPrevId = window.preventivoData.id || window.preventivoData.db_id;
        
        console.log('📄 [PDF] ID estratto:', pdfPrevId);
        
        if (!pdfPrevId) {
            alert('❌ Errore: ID preventivo non trovato');
            return;
        }
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'disco747_generate_pdf',
                nonce: '<?php echo wp_create_nonce("disco747_generate_pdf"); ?>',
                preventivo_id: pdfPrevId
            },
            success: function(response) {
                console.log('✅ [PDF] Risposta:', response);
                
                if (response.success && response.data.pdf_url) {
                    alert('✅ PDF generato con successo!');
                    
                    // Download automatico
                    window.open(response.data.pdf_url, '_blank');
                    
                } else {
                    alert('❌ Errore: ' + (response.data || 'Impossibile generare PDF'));
                }
            },
            error: function(xhr, status, error) {
                console.error('❌ Errore AJAX PDF:', error);
                alert('❌ Errore di connessione: ' + error);
            },
            complete: function() {
                $thisBtn.prop('disabled', false).html('📄 Genera e Scarica PDF');
            }
        });
    });
    
    // ========================================================================
    // PULSANTE 2: Invia Email - Apre Modal
    // ========================================================================
    $('#btn-send-email').on('click', function() {
        console.log('📧 [Email] Invia Email cliccato');
        
        if (!window.preventivoData || (!window.preventivoData.id && !window.preventivoData.db_id)) {
            alert('❌ Errore: Dati preventivo non disponibili. Ricarica la pagina.');
            console.error('❌ preventivoData:', window.preventivoData);
            return;
        }
        
        console.log('📧 [Email] Dati OK:', window.preventivoData);
        
        // Mostra modal selezione template
        $('#modal-email-template').css('display', 'flex').hide().fadeIn(300);
    });
    
    // Chiudi modal email
    $('#cancel-email-modal').on('click', function() {
        $('#modal-email-template').fadeOut(300);
    });
    
    // Conferma invio email
    $('#confirm-send-email').on('click', function() {
        var emailTemplateId = $('#email-template-select').val();
        var emailAttachPdf = $('#email-attach-pdf').is(':checked');
        var emailPrevId = window.preventivoData.id || window.preventivoData.db_id;
        
        console.log('📧 [Email] Conferma invio - Template:', emailTemplateId, 'PDF:', emailAttachPdf, 'ID:', emailPrevId);
        
        if (!emailPrevId) {
            alert('❌ Errore: ID preventivo mancante');
            console.error('❌ preventivoData:', window.preventivoData);
            return;
        }
        
        var $thisBtn = $(this);
        $thisBtn.prop('disabled', true).html('â³ Invio...');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'disco747_send_email_template',
                nonce: '<?php echo wp_create_nonce("disco747_send_email"); ?>',
                preventivo_id: emailPrevId,
                template_id: emailTemplateId,
                attach_pdf: emailAttachPdf ? '1' : '0'
            },
            success: function(response) {
                console.log('✅ [Email] Risposta:', response);
                
                if (response.success) {
                    alert('✅ Email inviata con successo!');
                    $('#modal-email-template').fadeOut(300);
                } else {
                    alert('❌ Errore: ' + (response.data || 'Impossibile inviare email'));
                }
            },
            error: function(xhr, status, error) {
                console.error('❌ [Email] Errore AJAX:', error);
                alert('❌ Errore di connessione: ' + error);
            },
            complete: function() {
                $thisBtn.prop('disabled', false).html('📧 Invia Email');
            }
        });
    });
    
    // ========================================================================
    // PULSANTE 3: Invia WhatsApp - Apre Modal
    // ========================================================================
    $('#btn-send-whatsapp').on('click', function() {
        console.log('💬 [WhatsApp] Invia WhatsApp cliccato');
        
        if (!window.preventivoData || (!window.preventivoData.id && !window.preventivoData.db_id)) {
            alert('❌ Errore: Dati preventivo non disponibili. Ricarica la pagina.');
            console.error('❌ preventivoData:', window.preventivoData);
            return;
        }
        
        console.log('💬 [WhatsApp] Dati OK:', window.preventivoData);
        
        // Mostra modal selezione template
        $('#modal-whatsapp-template').css('display', 'flex').hide().fadeIn(300);
    });
    
    // Chiudi modal whatsapp
    $('#cancel-whatsapp-modal').on('click', function() {
        $('#modal-whatsapp-template').fadeOut(300);
    });
    
    // Conferma invio whatsapp
    $('#confirm-send-whatsapp').on('click', function() {
        var whatsappTemplateId = $('#whatsapp-template-select').val();
        var actionPrevId = window.preventivoData.id || window.preventivoData.db_id;
        
        console.log('💬 [WhatsApp] Conferma invio - Template:', whatsappTemplateId, 'ID:', actionPrevId);
        
        if (!actionPrevId) {
            alert('❌ Errore: ID preventivo mancante');
            console.error('❌ preventivoData:', window.preventivoData);
            return;
        }
        
        var $thisBtn = $(this);
        $thisBtn.prop('disabled', true).html('â³ Preparazione...');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'disco747_send_whatsapp_template',
                nonce: '<?php echo wp_create_nonce("disco747_send_whatsapp"); ?>',
                preventivo_id: actionPrevId,
                template_id: whatsappTemplateId
            },
            success: function(response) {
                console.log('✅ [WhatsApp] Risposta:', response);
                
                if (response.success && response.data.whatsapp_url) {
                    // Apri WhatsApp in nuova finestra
                    window.open(response.data.whatsapp_url, '_blank');
                    
                    alert('✅ WhatsApp aperto! Controlla la finestra per inviare il messaggio.');
                    $('#modal-whatsapp-template').fadeOut(300);
                } else {
                    alert('❌ Errore: ' + (response.data || 'Impossibile preparare messaggio WhatsApp'));
                }
            },
            error: function(xhr, status, error) {
                console.error('❌ [WhatsApp] Errore AJAX:', error);
                alert('❌ Errore di connessione: ' + error);
            },
            complete: function() {
                $thisBtn.prop('disabled', false).html('💬 Apri WhatsApp');
            }
        });
    });
    
    // Chiudi modal cliccando fuori
    $('#modal-email-template, #modal-whatsapp-template').on('click', function(e) {
        if ($(e.target).is(this)) {
            $(this).fadeOut(300);
        }
    });
    
    // ============================================================================
    // CARICAMENTO DINAMICO TEMPLATE
    // ============================================================================
    
    // Funzione per caricare i template disponibili
    function loadAvailableTemplates() {
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'disco747_get_templates',
                nonce: '<?php echo wp_create_nonce("disco747_admin_nonce"); ?>'
            },
            success: function(response) {
                if (response.success) {
                    console.log('✅ Template caricati:', response.data);
                    populateEmailTemplates(response.data.email);
                    populateWhatsAppTemplates(response.data.whatsapp);
                } else {
                    console.error('❌ Errore caricamento template:', response.data);
                }
            },
            error: function() {
                console.error('❌ Errore nella richiesta AJAX template');
            }
        });
    }
    
    // Popola select email
    function populateEmailTemplates(templates) {
        const select = $('#email-template-select');
        if (!select.length) return;
        
        select.html('');
        
        if (templates.length === 0) {
            select.html('<option value="">Nessun template disponibile</option>');
            return;
        }
        
        templates.forEach(function(template) {
            const option = $('<option>')
                .val(template.id)
                .text(template.name);
            select.append(option);
        });
        
        console.log('✅ ' + templates.length + ' template email caricati');
    }
    
    // Popola select WhatsApp
    function populateWhatsAppTemplates(templates) {
        const select = $('#whatsapp-template-select');
        if (!select.length) return;
        
        select.html('');
        
        if (templates.length === 0) {
            select.html('<option value="">Nessun template disponibile</option>');
            return;
        }
        
        templates.forEach(function(template) {
            const option = $('<option>')
                .val(template.id)
                .text(template.name);
            select.append(option);
        });
        
        console.log('✅ ' + templates.length + ' template WhatsApp caricati');
    }
    
    // Carica i template all'avvio
    loadAvailableTemplates();
    
});
</script>

<!-- ============================================================================ -->
<!-- CSS: Stili Aggiuntivi -->
<!-- ============================================================================ -->
<style>
.disco747-form-preventivo input:focus,
.disco747-form-preventivo select:focus,
.disco747-form-preventivo textarea:focus {
    border-color: #c28a4d !important;
    box-shadow: 0 0 0 3px rgba(194, 138, 77, 0.1) !important;
    outline: none !important;
}

.disco747-form-preventivo button:hover {
    transform: translateY(-2px);
    filter: brightness(110%);
}

/* Hover effects per card pulsanti */
#post-creation-actions > div > div > div:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.2) !important;
}

/* Responsive */
@media (max-width: 768px) {
    .disco747-form-preventivo [style*="grid-template-columns"] {
        grid-template-columns: 1fr !important;
    }
}
</style>