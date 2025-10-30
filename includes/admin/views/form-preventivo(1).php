<?php
/**
 * Form Preventivo Completo - 747 Disco CRM
 * Versione 12.1.0 - CON PULSANTI PDF, EMAIL, WHATSAPP
 * 
 * MODIFICHE:
 * - Aggiunta sezione "Azioni Post-Creazione" con 3 pulsanti
 * - JavaScript per gestione PDF, Email, WhatsApp
 * - Modal per selezione template
 */

if (!defined('ABSPATH')) exit;

// ============================================================================
// MODALITÀ MODIFICA: Carica dati esistenti se presente edit_id
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

// Helper function
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
$submit_text = $is_edit_mode ? '💾 Salva Modifiche' : '💾 Salva Preventivo';
?>

<div class="wrap disco747-form-preventivo">
    
    <!-- Header -->
    <div style="background: linear-gradient(135deg, #c28a4d 0%, #b8b1b3 100%); padding: 30px; border-radius: 15px; margin-bottom: 30px; box-shadow: 0 6px 20px rgba(0,0,0,0.1);">
        <h1 style="color: white; margin: 0; font-size: 2rem; text-shadow: 0 2px 4px rgba(0,0,0,0.2);">
            <?php echo esc_html($page_title); ?>
        </h1>
        <p style="color: rgba(255,255,255,0.9); margin: 10px 0 0 0; font-size: 14px;">
            Compila il form per creare o modificare un preventivo
        </p>
    </div>

    <!-- Form -->
    <form id="disco747-form-preventivo" method="post" action="" enctype="multipart/form-data" style="background: white; border-radius: 15px; box-shadow: 0 6px 20px rgba(0,0,0,0.1); overflow: hidden;">
        
        <?php wp_nonce_field('disco747_preventivo_nonce', 'disco747_preventivo_nonce'); ?>
        <?php if ($is_edit_mode): ?>
            <input type="hidden" name="is_edit_mode" value="1">
            <input type="hidden" name="edit_id" value="<?php echo $edit_id; ?>">
        <?php endif; ?>

        <!-- SEZIONE 1: DATI REFERENTE -->
        <div style="background: linear-gradient(135deg, #c28a4d 0%, #b8b1b3 100%); color: white; padding: 20px;">
            <h2 style="margin: 0; display: flex; align-items: center; gap: 10px; font-size: 1.4rem;">
                👤 Dati Referente
            </h2>
        </div>
        
        <div style="padding: 30px; border-bottom: 1px solid #e9ecef;">
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px;">
                
                <div>
                    <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #2b1e1a;">
                        <span style="color: #dc3545;">*</span> Nome Referente
                    </label>
                    <input type="text" 
                           name="nome_referente" 
                           value="<?php echo get_field_value('nome_referente', '', $edit_data); ?>" 
                           required 
                           style="width: 100%; padding: 12px; border: 2px solid #e9ecef; border-radius: 8px; font-size: 14px;">
                </div>
                
                <div>
                    <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #2b1e1a;">
                        <span style="color: #dc3545;">*</span> Cognome Referente
                    </label>
                    <input type="text" 
                           name="cognome_referente" 
                           value="<?php echo get_field_value('cognome_referente', '', $edit_data); ?>" 
                           required 
                           style="width: 100%; padding: 12px; border: 2px solid #e9ecef; border-radius: 8px; font-size: 14px;">
                </div>
                
                <div>
                    <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #2b1e1a;">
                        📞 Cellulare
                    </label>
                    <input type="tel" 
                           name="cellulare" 
                           value="<?php echo get_field_value('cellulare', '', $edit_data); ?>" 
                           style="width: 100%; padding: 12px; border: 2px solid #e9ecef; border-radius: 8px; font-size: 14px;">
                </div>
                
                <div>
                    <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #2b1e1a;">
                        ✉️ Email
                    </label>
                    <input type="email" 
                           name="mail" 
                           value="<?php echo get_field_value('mail', '', $edit_data); ?>" 
                           style="width: 100%; padding: 12px; border: 2px solid #e9ecef; border-radius: 8px; font-size: 14px;">
                </div>
                
            </div>
        </div>

        <!-- SEZIONE 2: DATI EVENTO -->
        <div style="background: linear-gradient(135deg, #ffc107 0%, #e0a800 100%); color: #2b1e1a; padding: 20px;">
            <h2 style="margin: 0; display: flex; align-items: center; gap: 10px; font-size: 1.4rem;">
                🎉 Dati Evento
            </h2>
        </div>
        
        <div style="padding: 30px; border-bottom: 1px solid #e9ecef;">
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px;">
                
                <div>
                    <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #2b1e1a;">
                        <span style="color: #dc3545;">*</span> Data Evento
                    </label>
                    <input type="date" 
                           name="data_evento" 
                           value="<?php echo get_field_value('data_evento', '', $edit_data); ?>" 
                           required 
                           style="width: 100%; padding: 12px; border: 2px solid #e9ecef; border-radius: 8px; font-size: 14px;">
                </div>
                
                <div>
                    <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #2b1e1a;">
                        <span style="color: #dc3545;">*</span> Tipo Evento
                    </label>
                    <input type="text" 
                           name="tipo_evento" 
                           value="<?php echo get_field_value('tipo_evento', '', $edit_data); ?>" 
                           required 
                           style="width: 100%; padding: 12px; border: 2px solid #e9ecef; border-radius: 8px; font-size: 14px;"
                           placeholder="Es: Festa 18 anni">
                </div>
                
                <div>
                    <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #2b1e1a;">
                        🕐 Orario Inizio
                    </label>
                    <input type="time" 
                           name="orario_inizio" 
                           value="<?php echo get_field_value('orario_inizio', $default_values['orario_inizio'], $edit_data); ?>" 
                           style="width: 100%; padding: 12px; border: 2px solid #e9ecef; border-radius: 8px; font-size: 14px;">
                </div>
                
                <div>
                    <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #2b1e1a;">
                        🕐 Orario Fine
                    </label>
                    <input type="time" 
                           name="orario_fine" 
                           value="<?php echo get_field_value('orario_fine', $default_values['orario_fine'], $edit_data); ?>" 
                           style="width: 100%; padding: 12px; border: 2px solid #e9ecef; border-radius: 8px; font-size: 14px;">
                </div>
                
                <div>
                    <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #2b1e1a;">
                        <span style="color: #dc3545;">*</span> Numero Invitati
                    </label>
                    <input type="number" 
                           name="numero_invitati" 
                           value="<?php echo get_field_value('numero_invitati', $default_values['numero_invitati'], $edit_data); ?>" 
                           required 
                           min="1"
                           style="width: 100%; padding: 12px; border: 2px solid #e9ecef; border-radius: 8px; font-size: 14px;">
                </div>
                
            </div>
        </div>

        <!-- SEZIONE 3: MENU -->
        <div style="background: linear-gradient(135deg, #28a745 0%, #20c997 100%); color: white; padding: 20px;">
            <h2 style="margin: 0; display: flex; align-items: center; gap: 10px; font-size: 1.4rem;">
                🍽️ Menù Selezionato
            </h2>
        </div>
        
        <div style="padding: 30px; border-bottom: 1px solid #e9ecef;">
            <div>
                <label style="display: block; margin-bottom: 12px; font-weight: 600; color: #2b1e1a; font-size: 16px;">
                    <span style="color: #dc3545;">*</span> Tipo Menù
                </label>
                <select name="tipo_menu" 
                        id="tipo_menu"
                        required
                        onchange="calcolaImporti()"
                        style="width: 100%; padding: 15px; border: 2px solid #e9ecef; border-radius: 8px; font-size: 16px; font-weight: 600; background: white;">
                    <option value="Menu 7" <?php echo get_field_value('tipo_menu', $default_values['tipo_menu'], $edit_data) === 'Menu 7' ? 'selected' : ''; ?>>Menu 7 (Sconto: 400€)</option>
                    <option value="Menu 7-4" <?php echo get_field_value('tipo_menu', '', $edit_data) === 'Menu 7-4' ? 'selected' : ''; ?>>Menu 7-4 (Sconto: 500€)</option>
                    <option value="Menu 7-4-7" <?php echo get_field_value('tipo_menu', '', $edit_data) === 'Menu 7-4-7' ? 'selected' : ''; ?>>Menu 7-4-7 (Sconto: 600€)</option>
                </select>
            </div>
        </div>

        <!-- SEZIONE 4: OMAGGI -->
        <div style="background: linear-gradient(135deg, #17a2b8 0%, #138496 100%); color: white; padding: 20px;">
            <h2 style="margin: 0; display: flex; align-items: center; gap: 10px; font-size: 1.4rem;">
                🎁 Omaggi Inclusi
            </h2>
        </div>
        
        <div style="padding: 30px; border-bottom: 1px solid #e9ecef;">
            <div style="display: grid; gap: 15px;">
                
                <div>
                    <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #2b1e1a;">
                        🎁 Omaggio 1
                    </label>
                    <input type="text" 
                           name="omaggio1" 
                           value="<?php echo get_field_value('omaggio1', $default_values['omaggio1'], $edit_data); ?>" 
                           style="width: 100%; padding: 12px; border: 2px solid #e9ecef; border-radius: 8px; font-size: 14px;">
                </div>
                
                <div>
                    <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #2b1e1a;">
                        🎁 Omaggio 2
                    </label>
                    <input type="text" 
                           name="omaggio2" 
                           value="<?php echo get_field_value('omaggio2', $default_values['omaggio2'], $edit_data); ?>" 
                           style="width: 100%; padding: 12px; border: 2px solid #e9ecef; border-radius: 8px; font-size: 14px;">
                </div>
                
                <div>
                    <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #2b1e1a;">
                        🎁 Omaggio 3
                    </label>
                    <input type="text" 
                           name="omaggio3" 
                           value="<?php echo get_field_value('omaggio3', $default_values['omaggio3'], $edit_data); ?>" 
                           style="width: 100%; padding: 12px; border: 2px solid #e9ecef; border-radius: 8px; font-size: 14px;">
                </div>
                
            </div>
            
            <div style="background: #d1ecf1; border-left: 4px solid #17a2b8; padding: 15px; margin-top: 20px; border-radius: 8px;">
                <strong style="color: #0c5460;">ℹ️ Info Omaggi:</strong>
                <div style="color: #0c5460; font-size: 13px; margin-top: 8px;">
                    Gli omaggi sono inclusi gratuitamente. Ricorda al cliente di confermare entro 48 ore per mantenerli.
                </div>
            </div>
        </div>

        <!-- SEZIONE 5: EXTRA A PAGAMENTO -->
        <div style="background: linear-gradient(135deg, #6f42c1 0%, #5a32a3 100%); color: white; padding: 20px;">
            <h2 style="margin: 0; display: flex; align-items: center; gap: 10px; font-size: 1.4rem;">
                💎 Extra a Pagamento
            </h2>
        </div>
        
        <div style="padding: 30px; border-bottom: 1px solid #e9ecef;">
            <div style="display: grid; gap: 20px;">
                
                <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 15px;">
                    <div>
                        <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #2b1e1a;">
                            💎 Extra 1 - Descrizione
                        </label>
                        <input type="text" 
                               name="extra1" 
                               value="<?php echo get_field_value('extra1', '', $edit_data); ?>" 
                               style="width: 100%; padding: 12px; border: 2px solid #e9ecef; border-radius: 8px; font-size: 14px;"
                               placeholder="Es: Torta personalizzata">
                    </div>
                    <div>
                        <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #2b1e1a;">
                            💰 Importo (€)
                        </label>
                        <input type="number" 
                               name="extra1_importo" 
                               id="extra1_importo"
                               value="<?php echo get_field_value('extra1_importo', $default_values['extra1_importo'], $edit_data); ?>" 
                               min="0"
                               step="0.01"
                               onchange="calcolaImporti()"
                               style="width: 100%; padding: 12px; border: 2px solid #e9ecef; border-radius: 8px; font-size: 14px;">
                    </div>
                </div>
                
                <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 15px;">
                    <div>
                        <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #2b1e1a;">
                            💎 Extra 2 - Descrizione
                        </label>
                        <input type="text" 
                               name="extra2" 
                               value="<?php echo get_field_value('extra2', '', $edit_data); ?>" 
                               style="width: 100%; padding: 12px; border: 2px solid #e9ecef; border-radius: 8px; font-size: 14px;"
                               placeholder="Es: Decorazioni balloon">
                    </div>
                    <div>
                        <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #2b1e1a;">
                            💰 Importo (€)
                        </label>
                        <input type="number" 
                               name="extra2_importo" 
                               id="extra2_importo"
                               value="<?php echo get_field_value('extra2_importo', $default_values['extra2_importo'], $edit_data); ?>" 
                               min="0"
                               step="0.01"
                               onchange="calcolaImporti()"
                               style="width: 100%; padding: 12px; border: 2px solid #e9ecef; border-radius: 8px; font-size: 14px;">
                    </div>
                </div>
                
                <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 15px;">
                    <div>
                        <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #2b1e1a;">
                            💎 Extra 3 - Descrizione
                        </label>
                        <input type="text" 
                               name="extra3" 
                               value="<?php echo get_field_value('extra3', '', $edit_data); ?>" 
                               style="width: 100%; padding: 12px; border: 2px solid #e9ecef; border-radius: 8px; font-size: 14px;"
                               placeholder="Es: Servizio video">
                    </div>
                    <div>
                        <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #2b1e1a;">
                            💰 Importo (€)
                        </label>
                        <input type="number" 
                               name="extra3_importo" 
                               id="extra3_importo"
                               value="<?php echo get_field_value('extra3_importo', $default_values['extra3_importo'], $edit_data); ?>" 
                               min="0"
                               step="0.01"
                               onchange="calcolaImporti()"
                               style="width: 100%; padding: 12px; border: 2px solid #e9ecef; border-radius: 8px; font-size: 14px;">
                    </div>
                </div>
                
            </div>
        </div>

        <!-- SEZIONE 6: CALCOLI ECONOMICI -->
        <div style="background: linear-gradient(135deg, #dc3545 0%, #c82333 100%); color: white; padding: 20px;">
            <h2 style="margin: 0; display: flex; align-items: center; gap: 10px; font-size: 1.4rem;">
                💰 Calcoli Economici
            </h2>
        </div>
        
        <div style="padding: 30px; border-bottom: 1px solid #e9ecef;">
            <div style="display: grid; gap: 20px;">
                
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                    
                    <div>
                        <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #2b1e1a;">
                            💵 Importo Base (€)
                        </label>
                        <input type="number" 
                               name="importo_base" 
                               id="importo_base"
                               value="<?php echo get_field_value('importo_base', $default_values['importo_base'], $edit_data); ?>" 
                               min="0"
                               step="0.01"
                               onchange="calcolaImporti()"
                               style="width: 100%; padding: 12px; border: 2px solid #e9ecef; border-radius: 8px; font-size: 14px;">
                    </div>
                    
                    <div>
                        <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #2b1e1a;">
                            🏷️ Sconto All-Inclusive
                        </label>
                        <input type="text" 
                               id="sconto_valore" 
                               readonly
                               value="400 €"
                               style="width: 100%; padding: 12px; border: 2px solid #28a745; border-radius: 8px; font-size: 14px; background: #d4edda; font-weight: 600; color: #155724;">
                    </div>
                    
                </div>
                
                <div style="background: #f8f9fa; padding: 20px; border-radius: 12px; border: 2px solid #e9ecef;">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                        <div>
                            <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #2b1e1a;">
                                📊 Totale Lordo
                            </label>
                            <div id="totale_lordo_display" style="font-size: 20px; font-weight: 700; color: #6c757d;">
                                0,00 €
                            </div>
                        </div>
                        <div>
                            <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #2b1e1a;">
                                💳 Totale Preventivo
                            </label>
                            <div id="importo_preventivo_display" style="font-size: 24px; font-weight: 700; color: #dc3545;">
                                0,00 €
                            </div>
                            <input type="hidden" name="importo_totale" id="importo_preventivo" value="0">
                        </div>
                    </div>
                    
                    <div style="border-top: 2px dashed #dee2e6; padding-top: 15px;">
                        <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #2b1e1a;">
                            💰 Acconto Versato (€)
                        </label>
                        <input type="number" 
                               name="acconto" 
                               id="acconto"
                               value="<?php echo get_field_value('acconto', $default_values['acconto'], $edit_data); ?>" 
                               min="0"
                               step="0.01"
                               onchange="calcolaImporti()"
                               style="width: 100%; padding: 12px; border: 2px solid #e9ecef; border-radius: 8px; font-size: 14px;">
                        
                        <div style="margin-top: 15px; padding: 15px; background: white; border-radius: 8px; border: 2px solid #17a2b8;">
                            <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #17a2b8;">
                                💵 Saldo Residuo
                            </label>
                            <div id="saldo_display" style="font-size: 22px; font-weight: 700; color: #17a2b8;">
                                0,00 €
                            </div>
                        </div>
                    </div>
                </div>
                
            </div>
        </div>

        <!-- SEZIONE 7: NOTE AGGIUNTIVE -->
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
                <textarea name="note_aggiuntive" 
                          rows="4" 
                          style="width: 100%; padding: 12px; border: 2px solid #e9ecef; border-radius: 8px; font-size: 14px; resize: vertical;"
                          placeholder="Inserisci note aggiuntive, richieste speciali o dettagli importanti per l'evento..."><?php echo get_field_value('note_aggiuntive', '', $edit_data); ?></textarea>
            </div>
        </div>

        <!-- SEZIONE 8: NOTE INTERNE -->
        <div style="background: linear-gradient(135deg, #495057 0%, #343a40 100%); color: white; padding: 20px;">
            <h2 style="margin: 0; display: flex; align-items: center; gap: 10px; font-size: 1.4rem;">
                🔒 Note Interne
            </h2>
        </div>
        
        <div style="padding: 30px; border-bottom: 1px solid #e9ecef;">
            <div>
                <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #2b1e1a;">
                    🔐 Note Interne (Visibili solo al team)
                </label>
                <textarea name="note_interne" 
                          rows="3" 
                          style="width: 100%; padding: 12px; border: 2px solid #e9ecef; border-radius: 8px; font-size: 14px; resize: vertical; background: #f8f9fa;"
                          placeholder="Note per uso interno del team (non visibili al cliente)..."><?php echo get_field_value('note_interne', '', $edit_data); ?></textarea>
            </div>
            
            <div style="background: #fff3cd; padding: 15px; border-radius: 8px; margin-top: 15px; border-left: 4px solid #ffc107;">
                <div style="display: flex; align-items: center; gap: 8px;">
                    <span style="color: #856404;">⚠️</span>
                    <small style="color: #856404; font-weight: 500;">
                        Queste note sono riservate al team e non verranno mostrate al cliente.
                    </small>
                </div>
            </div>
        </div>

        <!-- ======================================================================= -->
        <!-- SEZIONE 9: AZIONI POST-CREAZIONE (NUOVA!) -->
        <!-- Visibile SOLO dopo il salvataggio del preventivo -->
        <!-- ======================================================================= -->
        
        <div id="sezione-azioni-post-creazione" style="display: none; margin: 0; padding: 30px; background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%); border-top: 3px solid #c28a4d;">
            
            <div style="text-align: center; margin-bottom: 25px;">
                <h2 style="color: #2b1e1a; margin: 0 0 10px 0; font-size: 1.6rem;">
                    🎯 Azioni Preventivo
                </h2>
                <p style="color: #666; margin: 0; font-size: 14px;">
                    Il preventivo è stato creato! Ora puoi generare il PDF, inviare l'email al cliente o inviare un messaggio WhatsApp.
                </p>
            </div>

            <!-- Messaggio conferma creazione -->
            <div id="info-preventivo-creato" style="background: white; padding: 20px; border-radius: 12px; border-left: 4px solid #28a745; margin-bottom: 25px; display: none;">
                <div style="display: flex; align-items: center; gap: 15px;">
                    <div style="font-size: 32px;">✅</div>
                    <div style="flex: 1;">
                        <strong style="color: #28a745; font-size: 16px;">Preventivo Salvato!</strong>
                        <div style="color: #666; font-size: 13px; margin-top: 5px;" id="preventivo-file-info">
                            File Excel creato e salvato su Google Drive.
                        </div>
                    </div>
                </div>
            </div>

            <!-- Griglia dei 3 pulsanti principali -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px;">
                
                <!-- PULSANTE 1: Genera PDF -->
                <div style="background: white; padding: 20px; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); text-align: center; border-top: 4px solid #dc3545;">
                    <div style="font-size: 48px; margin-bottom: 10px;">📄</div>
                    <h3 style="color: #2b1e1a; margin: 0 0 10px 0; font-size: 1.1rem;">Genera PDF</h3>
                    <p style="color: #666; font-size: 13px; margin: 0 0 15px 0;">Crea e scarica il PDF del preventivo</p>
                    <button type="button" 
                            id="btn-genera-pdf" 
                            class="disco747-btn-action"
                            style="width: 100%; background: linear-gradient(135deg, #dc3545 0%, #c82333 100%); color: white; border: none; padding: 12px 20px; border-radius: 8px; font-weight: 600; cursor: pointer; transition: all 0.3s ease; font-size: 15px;">
                        📄 Genera PDF
                    </button>
                    <div id="pdf-status" style="margin-top: 10px; font-size: 12px; min-height: 20px;"></div>
                </div>

                <!-- PULSANTE 2: Invia Email -->
                <div style="background: white; padding: 20px; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); text-align: center; border-top: 4px solid #17a2b8;">
                    <div style="font-size: 48px; margin-bottom: 10px;">✉️</div>
                    <h3 style="color: #2b1e1a; margin: 0 0 10px 0; font-size: 1.1rem;">Invia Email</h3>
                    <p style="color: #666; font-size: 13px; margin: 0 0 15px 0;">Invia l'email al cliente con template</p>
                    <button type="button" 
                            id="btn-invia-email" 
                            class="disco747-btn-action"
                            style="width: 100%; background: linear-gradient(135deg, #17a2b8 0%, #138496 100%); color: white; border: none; padding: 12px 20px; border-radius: 8px; font-weight: 600; cursor: pointer; transition: all 0.3s ease; font-size: 15px;">
                        ✉️ Invia Email
                    </button>
                    <div id="email-status" style="margin-top: 10px; font-size: 12px; min-height: 20px;"></div>
                </div>

                <!-- PULSANTE 3: Invia WhatsApp -->
                <div style="background: white; padding: 20px; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); text-align: center; border-top: 4px solid #25d366;">
                    <div style="font-size: 48px; margin-bottom: 10px;">💬</div>
                    <h3 style="color: #2b1e1a; margin: 0 0 10px 0; font-size: 1.1rem;">Invia WhatsApp</h3>
                    <p style="color: #666; font-size: 13px; margin: 0 0 15px 0;">Invia messaggio WhatsApp al cliente</p>
                    <button type="button" 
                            id="btn-invia-whatsapp" 
                            class="disco747-btn-action"
                            style="width: 100%; background: linear-gradient(135deg, #25d366 0%, #1ebe57 100%); color: white; border: none; padding: 12px 20px; border-radius: 8px; font-weight: 600; cursor: pointer; transition: all 0.3s ease; font-size: 15px;">
                        💬 Invia WhatsApp
                    </button>
                    <div id="whatsapp-status" style="margin-top: 10px; font-size: 12px; min-height: 20px;"></div>
                </div>
            </div>
        </div>

        <!-- Hidden fields per memorizzare dati preventivo -->
        <input type="hidden" id="preventivo-id-creato" value="">
        <input type="hidden" id="preventivo-pdf-path" value="">
        <input type="hidden" id="preventivo-excel-path" value="">

        <!-- PULSANTI AZIONE FORM -->
        <div style="background: #f8f9fa; padding: 30px; text-align: center;">
            <div style="display: flex; gap: 15px; justify-content: center; flex-wrap: wrap;">
                
                <button type="submit" 
                        name="action" 
                        value="save_preventivo"
                        style="background: linear-gradient(135deg, #28a745 0%, #20c997 100%); color: white; padding: 15px 30px; border: none; border-radius: 25px; font-weight: 600; font-size: 16px; cursor: pointer; box-shadow: 0 4px 15px rgba(40, 167, 69, 0.3); transition: all 0.3s ease;">
                    <?php echo $submit_text; ?> 📄
                </button>
                
                <a href="<?php echo esc_url(admin_url('admin.php?page=disco747-crm')); ?>" 
                   style="background: rgba(108, 117, 125, 0.1); color: #6c757d; padding: 15px 30px; border: 2px solid #6c757d; border-radius: 25px; font-weight: 600; font-size: 16px; text-decoration: none; display: inline-block; transition: all 0.3s ease;">
                    ← Annulla
                </a>
                
            </div>
        </div>
        
    </form>
    
</div>

<!-- ======================================================================= -->
<!-- JAVASCRIPT PER CALCOLI AUTOMATICI -->
<!-- ======================================================================= -->

<script>
// Sconti menu (PHP to JS)
const scontiMenu = <?php echo json_encode($sconti_menu); ?>;

function calcolaImporti() {
    // Recupera valori
    const tipoMenu = document.getElementById('tipo_menu')?.value || 'Menu 7';
    const importoBase = parseFloat(document.getElementById('importo_base')?.value || 0);
    const extra1 = parseFloat(document.getElementById('extra1_importo')?.value || 0);
    const extra2 = parseFloat(document.getElementById('extra2_importo')?.value || 0);
    const extra3 = parseFloat(document.getElementById('extra3_importo')?.value || 0);
    const acconto = parseFloat(document.getElementById('acconto')?.value || 0);
    
    // Sconto menu
    const sconto = scontiMenu[tipoMenu] || 400;
    
    // Calcoli
    const totaleExtra = extra1 + extra2 + extra3;
    const totaleLordo = importoBase + totaleExtra;
    const importoPreventivo = totaleLordo - sconto;
    const saldo = Math.max(0, importoPreventivo - acconto);
    
    // Aggiorna UI
    if (document.getElementById('sconto_valore')) {
        document.getElementById('sconto_valore').value = sconto.toFixed(2).replace('.', ',') + ' €';
    }
    if (document.getElementById('totale_lordo_display')) {
        document.getElementById('totale_lordo_display').textContent = totaleLordo.toFixed(2).replace('.', ',') + ' €';
    }
    if (document.getElementById('importo_preventivo_display')) {
        document.getElementById('importo_preventivo_display').textContent = importoPreventivo.toFixed(2).replace('.', ',') + ' €';
    }
    if (document.getElementById('importo_preventivo')) {
        document.getElementById('importo_preventivo').value = importoPreventivo.toFixed(2);
    }
    if (document.getElementById('saldo_display')) {
        document.getElementById('saldo_display').textContent = saldo.toFixed(2).replace('.', ',') + ' €';
    }
}

// Esegui calcolo al caricamento
document.addEventListener('DOMContentLoaded', function() {
    calcolaImporti();
    console.log('✅ Form Preventivo 747 Disco caricato');
});

// Validazione email
function validateEmail(email) {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return emailRegex.test(email);
}
</script>

<!-- ======================================================================= -->
<!-- JAVASCRIPT AJAX PER SUBMIT FORM E GESTIONE PULSANTI -->
<!-- ======================================================================= -->

<script>
jQuery(document).ready(function($) {
    'use strict';
    
    console.log('🎯 [747Disco-AJAX] Form AJAX Handler caricato');
    
    const $form = $('#disco747-form-preventivo');
    const $submitButtons = $form.find('button[type="submit"]');
    
    if (!$form.length) {
        console.error('❌ Form preventivo non trovato!');
        return;
    }
    
    // Variabili globali per dati preventivo
    let preventivoData = {
        id: null,
        pdfPath: null,
        excelPath: null
    };
    
    // ========================================================================
    // SUBMIT FORM PRINCIPALE
    // ========================================================================
    
    $form.on('submit', function(e) {
        e.preventDefault();
        
        console.log('🚀 [747Disco-AJAX] Submit form intercettato');
        
        $submitButtons.prop('disabled', true).html('⏳ Salvataggio...');
        
        const formData = new FormData(this);
        formData.append('action', 'disco747_save_preventivo');
        
        const wpNonce = formData.get('disco747_preventivo_nonce');
        if (wpNonce) {
            formData.append('disco747_nonce', wpNonce);
        }
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                console.log('✅ Risposta server:', response);
                
                if (response.success) {
                    alert('✅ ' + (response.data.message || 'Preventivo creato con successo!'));
                    
                    // NUOVO: Memorizza dati e mostra sezione azioni
                    if (response.data.preventivo_id) {
                        preventivoData.id = response.data.preventivo_id;
                        $('#preventivo-id-creato').val(response.data.preventivo_id);
                        
                        if (response.data.paths) {
                            preventivoData.pdfPath = response.data.paths.pdf_path || '';
                            preventivoData.excelPath = response.data.paths.excel_path || '';
                            $('#preventivo-pdf-path').val(preventivoData.pdfPath);
                            $('#preventivo-excel-path').val(preventivoData.excelPath);
                        }
                        
                        // Mostra info file
                        if (response.data.files) {
                            let fileInfo = '';
                            if (response.data.files.excel) {
                                fileInfo += '📊 File Excel: <strong>' + response.data.files.excel + '</strong><br>';
                            }
                            if (response.data.files.pdf) {
                                fileInfo += '📄 PDF: <strong>' + response.data.files.pdf + '</strong>';
                            }
                            if (fileInfo) {
                                $('#preventivo-file-info').html(fileInfo);
                            }
                        }
                        
                        // Mostra sezione azioni con animazione
                        $('#info-preventivo-creato').fadeIn(400);
                        $('#sezione-azioni-post-creazione').slideDown(500, function() {
                            $('html, body').animate({
                                scrollTop: $('#sezione-azioni-post-creazione').offset().top - 100
                            }, 500);
                        });
                    }
                    
                } else {
                    const errorMsg = response.data || response.message || 'Errore sconosciuto';
                    alert('❌ Errore: ' + errorMsg);
                }
            },
            error: function(xhr, status, error) {
                console.error('❌ Errore AJAX:', error);
                alert('❌ Errore di connessione: ' + error);
            },
            complete: function() {
                $submitButtons.each(function() {
                    const $btn = $(this);
                    const originalText = $btn.attr('data-original-text') || '💾 Salva Preventivo';
                    $btn.html(originalText).prop('disabled', false);
                });
            }
        });
    });
    
    // Salva testo originale pulsanti
    $submitButtons.each(function() {
        $(this).attr('data-original-text', $(this).html());
    });
    
    // ========================================================================
    // PULSANTE 1: GENERA PDF
    // ========================================================================
    
    $(document).on('click', '#btn-genera-pdf', function() {
        const $btn = $(this);
        const preventivoId = $('#preventivo-id-creato').val();
        
        if (!preventivoId) {
            alert('⚠️ Nessun preventivo trovato. Salva prima il preventivo.');
            return;
        }
        
        console.log('📄 [GeneraPDF] Avvio generazione per ID:', preventivoId);
        
        $btn.prop('disabled', true).html('⏳ Generazione...');
        $('#pdf-status').html('<span style="color: #17a2b8;">⏳ Generazione PDF in corso...</span>');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'disco747_generate_pdf',
                nonce: $('#disco747_preventivo_nonce').val(),
                preventivo_id: preventivoId
            },
            success: function(response) {
                console.log('✅ [GeneraPDF] Risposta:', response);
                
                if (response.success) {
                    preventivoData.pdfPath = response.data.pdf_path;
                    $('#preventivo-pdf-path').val(response.data.pdf_path);
                    
                    $('#pdf-status').html('<span style="color: #28a745;">✅ PDF generato!</span>');
                    
                    if (response.data.download_url) {
                        const downloadBtn = '<br><a href="' + response.data.download_url + '" target="_blank" style="display: inline-block; margin-top: 8px; padding: 6px 12px; background: #28a745; color: white; text-decoration: none; border-radius: 6px; font-size: 12px; font-weight: 600;">⬇️ Scarica PDF</a>';
                        $('#pdf-status').append(downloadBtn);
                    }
                    
                    alert('✅ PDF generato con successo!');
                    $btn.html('✅ PDF Generato').css('background', 'linear-gradient(135deg, #28a745 0%, #20c997 100%)');
                    
                } else {
                    $('#pdf-status').html('<span style="color: #dc3545;">❌ Errore generazione</span>');
                    alert('❌ Errore: ' + (response.data || 'Errore generazione PDF'));
                    $btn.prop('disabled', false).html('📄 Genera PDF');
                }
            },
            error: function(xhr, status, error) {
                console.error('❌ [GeneraPDF] Errore:', error);
                $('#pdf-status').html('<span style="color: #dc3545;">❌ Errore connessione</span>');
                alert('❌ Errore di connessione');
                $btn.prop('disabled', false).html('📄 Genera PDF');
            }
        });
    });
    
    // ========================================================================
    // PULSANTE 2: INVIA EMAIL (con modal)
    // ========================================================================
    
    $(document).on('click', '#btn-invia-email', function() {
        const preventivoId = $('#preventivo-id-creato').val();
        
        if (!preventivoId) {
            alert('⚠️ Nessun preventivo trovato.');
            return;
        }
        
        mostraModalEmailTemplates(preventivoId);
    });
    
    function mostraModalEmailTemplates(preventivoId) {
        const modalHTML = `
        <div id="email-template-modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); z-index: 999999; padding: 20px; overflow-y: auto;">
            <div style="background: white; max-width: 700px; margin: 50px auto; border-radius: 15px; overflow: hidden; box-shadow: 0 10px 40px rgba(0,0,0,0.3);">
                
                <div style="background: linear-gradient(135deg, #17a2b8 0%, #138496 100%); color: white; padding: 25px; display: flex; justify-content: space-between; align-items: center;">
                    <h3 style="margin: 0; color: white; font-size: 1.4rem;">✉️ Seleziona Template Email</h3>
                    <button onclick="jQuery('#email-template-modal').fadeOut(300, function(){ jQuery(this).remove(); })" style="background: none; border: none; color: white; font-size: 28px; cursor: pointer;">×</button>
                </div>
                
                <div style="padding: 30px;">
                    <p style="color: #666; margin: 0 0 20px 0;">Scegli quale template email inviare. Il PDF verrà allegato se disponibile.</p>
                    
                    <div style="display: grid; gap: 15px; margin-bottom: 20px;">
                        <label style="display: flex; align-items: center; padding: 15px; background: #f8f9fa; border: 2px solid #e9ecef; border-radius: 8px; cursor: pointer;">
                            <input type="radio" name="email_template" value="1" checked style="margin-right: 12px;">
                            <div><strong>Template 1 - Preventivo Nuovo</strong></div>
                        </label>
                        <label style="display: flex; align-items: center; padding: 15px; background: #f8f9fa; border: 2px solid #e9ecef; border-radius: 8px; cursor: pointer;">
                            <input type="radio" name="email_template" value="2" style="margin-right: 12px;">
                            <div><strong>Template 2 - Follow-up</strong></div>
                        </label>
                        <label style="display: flex; align-items: center; padding: 15px; background: #f8f9fa; border: 2px solid #e9ecef; border-radius: 8px; cursor: pointer;">
                            <input type="radio" name="email_template" value="3" style="margin-right: 12px;">
                            <div><strong>Template 3 - Conferma</strong></div>
                        </label>
                    </div>
                    
                    <div style="display: flex; gap: 10px; justify-content: flex-end;">
                        <button onclick="jQuery('#email-template-modal').fadeOut(300, function(){ jQuery(this).remove(); })" style="padding: 12px 24px; background: #6c757d; color: white; border: none; border-radius: 8px; cursor: pointer;">Annulla</button>
                        <button onclick="inviaEmailConTemplate(${preventivoId})" style="padding: 12px 24px; background: linear-gradient(135deg, #17a2b8 0%, #138496 100%); color: white; border: none; border-radius: 8px; cursor: pointer;">✉️ Invia</button>
                    </div>
                </div>
            </div>
        </div>`;
        
        $('#email-template-modal').remove();
        $('body').append(modalHTML);
        $('#email-template-modal').fadeIn(300);
    }
    
    window.inviaEmailConTemplate = function(preventivoId) {
        const templateId = $('input[name="email_template"]:checked').val();
        const pdfPath = $('#preventivo-pdf-path').val();
        
        $('#email-template-modal').fadeOut(300, function(){ $(this).remove(); });
        
        $('#btn-invia-email').prop('disabled', true).html('⏳ Invio...');
        $('#email-status').html('<span style="color: #17a2b8;">⏳ Invio email...</span>');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'disco747_send_email_template',
                nonce: $('#disco747_preventivo_nonce').val(),
                preventivo_id: preventivoId,
                template_id: templateId,
                pdf_path: pdfPath,
                attach_pdf: true
            },
            success: function(response) {
                if (response.success) {
                    $('#email-status').html('<span style="color: #28a745;">✅ Email inviata!</span>');
                    alert('✅ Email inviata a: ' + (response.data.recipient || 'cliente'));
                    $('#btn-invia-email').html('✅ Email Inviata').css('background', 'linear-gradient(135deg, #28a745 0%, #20c997 100%)');
                } else {
                    $('#email-status').html('<span style="color: #dc3545;">❌ Errore</span>');
                    alert('❌ Errore: ' + (response.data || 'Errore invio'));
                    $('#btn-invia-email').prop('disabled', false).html('✉️ Invia Email');
                }
            },
            error: function() {
                $('#email-status').html('<span style="color: #dc3545;">❌ Errore connessione</span>');
                alert('❌ Errore connessione');
                $('#btn-invia-email').prop('disabled', false).html('✉️ Invia Email');
            }
        });
    };
    
    // ========================================================================
    // PULSANTE 3: INVIA WHATSAPP (con modal)
    // ========================================================================
    
    $(document).on('click', '#btn-invia-whatsapp', function() {
        const preventivoId = $('#preventivo-id-creato').val();
        
        if (!preventivoId) {
            alert('⚠️ Nessun preventivo trovato.');
            return;
        }
        
        mostraModalWhatsAppTemplates(preventivoId);
    });
    
    function mostraModalWhatsAppTemplates(preventivoId) {
        const modalHTML = `
        <div id="whatsapp-template-modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); z-index: 999999; padding: 20px; overflow-y: auto;">
            <div style="background: white; max-width: 700px; margin: 50px auto; border-radius: 15px; overflow: hidden; box-shadow: 0 10px 40px rgba(0,0,0,0.3);">
                
                <div style="background: linear-gradient(135deg, #25d366 0%, #1ebe57 100%); color: white; padding: 25px; display: flex; justify-content: space-between; align-items: center;">
                    <h3 style="margin: 0; color: white; font-size: 1.4rem;">💬 Seleziona Template WhatsApp</h3>
                    <button onclick="jQuery('#whatsapp-template-modal').fadeOut(300, function(){ jQuery(this).remove(); })" style="background: none; border: none; color: white; font-size: 28px; cursor: pointer;">×</button>
                </div>
                
                <div style="padding: 30px;">
                    <p style="color: #666; margin: 0 0 20px 0;">Scegli quale template WhatsApp inviare.</p>
                    
                    <div style="display: grid; gap: 15px; margin-bottom: 20px;">
                        <label style="display: flex; align-items: center; padding: 15px; background: #f8f9fa; border: 2px solid #e9ecef; border-radius: 8px; cursor: pointer;">
                            <input type="radio" name="whatsapp_template" value="1" checked style="margin-right: 12px;">
                            <div><strong>Template 1 - Preventivo Nuovo</strong></div>
                        </label>
                        <label style="display: flex; align-items: center; padding: 15px; background: #f8f9fa; border: 2px solid #e9ecef; border-radius: 8px; cursor: pointer;">
                            <input type="radio" name="whatsapp_template" value="2" style="margin-right: 12px;">
                            <div><strong>Template 2 - Follow-up</strong></div>
                        </label>
                        <label style="display: flex; align-items: center; padding: 15px; background: #f8f9fa; border: 2px solid #e9ecef; border-radius: 8px; cursor: pointer;">
                            <input type="radio" name="whatsapp_template" value="3" style="margin-right: 12px;">
                            <div><strong>Template 3 - Conferma</strong></div>
                        </label>
                    </div>
                    
                    <div style="display: flex; gap: 10px; justify-content: flex-end;">
                        <button onclick="jQuery('#whatsapp-template-modal').fadeOut(300, function(){ jQuery(this).remove(); })" style="padding: 12px 24px; background: #6c757d; color: white; border: none; border-radius: 8px; cursor: pointer;">Annulla</button>
                        <button onclick="inviaWhatsAppConTemplate(${preventivoId})" style="padding: 12px 24px; background: linear-gradient(135deg, #25d366 0%, #1ebe57 100%); color: white; border: none; border-radius: 8px; cursor: pointer;">💬 Apri WhatsApp</button>
                    </div>
                </div>
            </div>
        </div>`;
        
        $('#whatsapp-template-modal').remove();
        $('body').append(modalHTML);
        $('#whatsapp-template-modal').fadeIn(300);
    }
    
    window.inviaWhatsAppConTemplate = function(preventivoId) {
        const templateId = $('input[name="whatsapp_template"]:checked').val();
        
        $('#whatsapp-template-modal').fadeOut(300, function(){ $(this).remove(); });
        
        $('#btn-invia-whatsapp').prop('disabled', true).html('⏳ Preparazione...');
        $('#whatsapp-status').html('<span style="color: #25d366;">⏳ Preparazione link...</span>');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'disco747_send_whatsapp_template',
                nonce: $('#disco747_preventivo_nonce').val(),
                preventivo_id: preventivoId,
                template_id: templateId
            },
            success: function(response) {
                if (response.success) {
                    $('#whatsapp-status').html('<span style="color: #28a745;">✅ Link generato!</span>');
                    
                    if (response.data.whatsapp_url) {
                        window.open(response.data.whatsapp_url, '_blank');
                        alert('✅ WhatsApp aperto!\nNumero: ' + (response.data.phone || 'N/A'));
                    }
                    
                    $('#btn-invia-whatsapp').html('✅ WhatsApp Aperto').css('background', 'linear-gradient(135deg, #28a745 0%, #20c997 100%)');
                    
                    setTimeout(function() {
                        $('#btn-invia-whatsapp').prop('disabled', false).html('💬 Invia WhatsApp').css('background', 'linear-gradient(135deg, #25d366 0%, #1ebe57 100%)');
                    }, 3000);
                    
                } else {
                    $('#whatsapp-status').html('<span style="color: #dc3545;">❌ Errore</span>');
                    alert('❌ Errore: ' + (response.data || 'Errore generazione link'));
                    $('#btn-invia-whatsapp').prop('disabled', false).html('💬 Invia WhatsApp');
                }
            },
            error: function() {
                $('#whatsapp-status').html('<span style="color: #dc3545;">❌ Errore connessione</span>');
                alert('❌ Errore connessione');
                $('#btn-invia-whatsapp').prop('disabled', false).html('💬 Invia WhatsApp');
            }
        });
    };
    
    console.log('✅ [747Disco-AJAX] Handler registrati correttamente');
});
</script>

<!-- ======================================================================= -->
<!-- CSS AGGIUNTIVO -->
<!-- ======================================================================= -->

<style>
/* Form Preventivo Styles */
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

.disco747-form-preventivo a:hover {
    transform: translateY(-2px);
    border-color: #c28a4d !important;
    color: #c28a4d !important;
}

/* Stili per pulsanti azioni */
.disco747-btn-action:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 16px rgba(0,0,0,0.2) !important;
}

.disco747-btn-action:active {
    transform: translateY(0);
}

.disco747-btn-action:disabled {
    opacity: 0.6;
    cursor: not-allowed !important;
    transform: none !important;
}

/* Animazione fade-in per sezione azioni */
@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

#sezione-azioni-post-creazione {
    animation: fadeInUp 0.5s ease-out;
}

/* Responsive Design */
@media (max-width: 768px) {
    .disco747-form-preventivo [style*="grid-template-columns"] {
        grid-template-columns: 1fr !important;
    }
    
    .disco747-form-preventivo [style*="display: flex"] {
        flex-direction: column !important;
        gap: 10px !important;
    }
    
    .disco747-form-preventivo [style*="padding: 30px"] {
        padding: 20px !important;
    }
    
    #sezione-azioni-post-creazione > div:first-child {
        padding: 20px !important;
    }
    
    #sezione-azioni-post-creazione [style*="grid-template-columns"] {
        grid-template-columns: 1fr !important;
    }
}

/* Animazioni smooth */
.disco747-form-preventivo input,
.disco747-form-preventivo select,
.disco747-form-preventivo textarea,
.disco747-form-preventivo button {
    transition: all 0.3s ease !important;
}

/* Loading state */
.disco747-form-preventivo button:disabled {
    opacity: 0.7;
    cursor: not-allowed;
    transform: none !important;
}

/* Success/Error borders */
.disco747-form-preventivo .field-success {
    border-color: #28a745 !important;
}

.disco747-form-preventivo .field-error {
    border-color: #dc3545 !important;
}
</style>