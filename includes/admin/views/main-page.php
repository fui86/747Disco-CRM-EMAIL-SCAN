<?php
/**
 * Dashboard Principale 747 Disco CRM - VERSIONE RIMODELLATA
 * 
 * FEATURES:
 * - Preventivi futuri da oggi in poi
 * - Filtri: Tutti, Attivi, Confermati, Questo Mese
 * - Link nuovo preventivo in evidenza
 * - Grafici statistiche
 * - Tabella ultimi preventivi
 * - Completamente responsive
 * 
 * @package    Disco747_CRM
 * @subpackage Admin/Views
 * @version    12.0.0
 */

if (!defined('ABSPATH')) exit;

// Sicurezza dati
$stats = $stats ?? array('total' => 0, 'attivi' => 0, 'confermati' => 0, 'this_month' => 0, 'annullati' => 0);
$preventivi_futuri = $preventivi_futuri ?? array();
$preventivi_recenti = $preventivi_recenti ?? array();
$chart_data = $chart_data ?? array('preventivi_per_mese' => array(), 'confermati' => 0, 'non_confermati' => 0);
$system_status = $system_status ?? array('plugin_version' => '11.8.0', 'storage_type' => 'googledrive', 'storage_connected' => false);

$current_user = wp_get_current_user();
$user_name = $current_user->display_name ?? 'Utente';
$version = $system_status['plugin_version'];
?>

<div class="wrap disco747-dashboard-enhanced" style="max-width: 1600px; margin: 20px auto; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;">
    
    <!-- ============================================================================ -->
    <!-- HEADER con Pulsante Nuovo Preventivo in evidenza -->
    <!-- ============================================================================ -->
    <div style="background: linear-gradient(135deg, #2b1e1a 0%, #c28a4d 50%, #2b1e1a 100%); color: white; padding: 35px 40px; border-radius: 20px; margin-bottom: 30px; box-shadow: 0 10px 40px rgba(43, 30, 26, 0.4); position: relative; overflow: hidden;">
        
        <!-- Decorazioni -->
        <div style="position: absolute; top: -80px; right: -80px; width: 250px; height: 250px; background: rgba(255,215,0,0.15); border-radius: 50%; opacity: 0.6;"></div>
        <div style="position: absolute; bottom: -60px; left: -60px; width: 200px; height: 200px; background: rgba(255,255,255,0.08); border-radius: 50%;"></div>
        
        <div style="position: relative; z-index: 2; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 25px;">
            <div>
                <h1 style="margin: 0 0 12px 0; font-size: 3rem; font-weight: 800; text-shadow: 3px 3px 6px rgba(0,0,0,0.4); letter-spacing: -1px;">
                    🎉 747 Disco CRM
                </h1>
                <p style="margin: 0; font-size: 1.3rem; opacity: 0.95; font-weight: 400; text-shadow: 1px 1px 3px rgba(0,0,0,0.3);">
                    Benvenuto, <?php echo esc_html($user_name); ?>! · PreventiviParty Enhanced v<?php echo esc_html($version); ?>
                </p>
            </div>
            
            <!-- PULSANTE NUOVO PREVENTIVO IN EVIDENZA -->
            <a href="<?php echo esc_url(admin_url('admin.php?page=disco747-crm&action=new_preventivo')); ?>" 
               class="btn-nuovo-preventivo"
               style="background: linear-gradient(135deg, #28a745 0%, #20c997 100%); color: white; padding: 18px 35px; border-radius: 30px; text-decoration: none; font-weight: 700; font-size: 1.2rem; box-shadow: 0 6px 25px rgba(40, 167, 69, 0.5); transition: all 0.3s ease; display: inline-block; border: 3px solid rgba(255,255,255,0.3);">
                <span style="font-size: 1.4rem; margin-right: 8px;">➕</span> NUOVO PREVENTIVO
            </a>
        </div>
    </div>

    <!-- ============================================================================ -->
    <!-- PREVENTIVI FUTURI - Sezione In Evidenza -->
    <!-- ============================================================================ -->
    <div style="background: white; border-radius: 20px; box-shadow: 0 8px 30px rgba(0,0,0,0.12); margin-bottom: 35px; overflow: hidden;">
        
        <!-- Header Sezione -->
        <div style="background: linear-gradient(135deg, #dc3545 0%, #c82333 100%); padding: 25px 35px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;">
            <div>
                <h2 style="margin: 0; font-size: 1.8rem; font-weight: 700; color: white;">
                    📅 Preventivi in Essere
                </h2>
                <p style="margin: 5px 0 0 0; color: rgba(255,255,255,0.9); font-size: 1rem;">
                    Eventi da oggi in poi · <?php echo count($preventivi_futuri); ?> preventivi programmati
                </p>
            </div>
            
            <!-- Filtri Rapidi -->
            <div class="filtri-rapidi" style="display: flex; gap: 10px; flex-wrap: wrap;">
                <button class="filter-btn active" data-filter="all" style="background: white; color: #dc3545; border: 2px solid white; padding: 10px 18px; border-radius: 20px; font-weight: 600; cursor: pointer; transition: all 0.3s;">
                    Tutti (<?php echo count($preventivi_futuri); ?>)
                </button>
                <button class="filter-btn" data-filter="attivi" style="background: rgba(255,255,255,0.2); color: white; border: 2px solid rgba(255,255,255,0.4); padding: 10px 18px; border-radius: 20px; font-weight: 600; cursor: pointer; transition: all 0.3s;">
                    ⏳ Attivi (<?php echo count(array_filter($preventivi_futuri, function($p) { return $p['stato'] === 'attivo'; })); ?>)
                </button>
                <button class="filter-btn" data-filter="confermati" style="background: rgba(255,255,255,0.2); color: white; border: 2px solid rgba(255,255,255,0.4); padding: 10px 18px; border-radius: 20px; font-weight: 600; cursor: pointer; transition: all 0.3s;">
                    ✅ Confermati (<?php echo count(array_filter($preventivi_futuri, function($p) { return floatval($p['acconto']) > 0; })); ?>)
                </button>
                <button class="filter-btn" data-filter="questo-mese" style="background: rgba(255,255,255,0.2); color: white; border: 2px solid rgba(255,255,255,0.4); padding: 10px 18px; border-radius: 20px; font-weight: 600; cursor: pointer; transition: all 0.3s;">
                    📆 Questo Mese (<?php echo count(array_filter($preventivi_futuri, function($p) { return date('Y-m', strtotime($p['data_evento'])) === date('Y-m'); })); ?>)
                </button>
            </div>
        </div>
        
        <!-- Lista Preventivi Futuri -->
        <div id="preventivi-futuri-container" style="padding: 30px;">
            <?php if (empty($preventivi_futuri)): ?>
                <div style="text-align: center; padding: 60px 20px; color: #6c757d;">
                    <div style="font-size: 4rem; margin-bottom: 15px; opacity: 0.3;">📭</div>
                    <h3 style="margin: 0 0 10px 0; font-size: 1.5rem; color: #495057;">Nessun Preventivo Futuro</h3>
                    <p style="margin: 0; font-size: 1.1rem;">Non ci sono preventivi programmati da oggi in poi</p>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=disco747-crm&action=new_preventivo')); ?>" 
                       style="display: inline-block; margin-top: 25px; background: #28a745; color: white; padding: 12px 25px; border-radius: 25px; text-decoration: none; font-weight: 600;">
                        ➕ Crea Primo Preventivo
                    </a>
                </div>
            <?php else: ?>
                <div id="preventivi-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(340px, 1fr)); gap: 20px;">
                    <?php foreach ($preventivi_futuri as $prev): 
                        $is_confermato = floatval($prev['acconto']) > 0;
                        $is_attivo = $prev['stato'] === 'attivo';
                        $is_annullato = $prev['stato'] === 'annullato';
                        $is_questo_mese = date('Y-m', strtotime($prev['data_evento'])) === date('Y-m');
                        
                        $border_color = $is_annullato ? '#dc3545' : ($is_confermato ? '#28a745' : '#ffc107');
                        $data_filter = array();
                        $data_filter[] = 'all';
                        if ($is_attivo && !$is_confermato) $data_filter[] = 'attivi';
                        if ($is_confermato) $data_filter[] = 'confermati';
                        if ($is_questo_mese) $data_filter[] = 'questo-mese';
                    ?>
                    <div class="preventivo-card" data-filters="<?php echo implode(' ', $data_filter); ?>" style="background: white; border: 3px solid <?php echo $border_color; ?>; border-radius: 15px; padding: 20px; transition: all 0.3s ease; box-shadow: 0 3px 15px rgba(0,0,0,0.08);">
                        
                        <!-- Header Card -->
                        <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 15px;">
                            <div>
                                <h3 style="margin: 0 0 5px 0; font-size: 1.3rem; color: #2b1e1a; font-weight: 700;">
                                    <?php echo esc_html($prev['nome_cliente']); ?>
                                </h3>
                                <div style="font-size: 0.9rem; color: #6c757d;">
                                    #<?php echo esc_html($prev['preventivo_id'] ?? $prev['id']); ?>
                                </div>
                            </div>
                            <div style="text-align: right;">
                                <?php if ($is_annullato): ?>
                                    <span style="background: #dc3545; color: white; padding: 6px 12px; border-radius: 15px; font-size: 0.85rem; font-weight: 700; white-space: nowrap;">
                                        ❌ ANNULLATO
                                    </span>
                                <?php elseif ($is_confermato): ?>
                                    <span style="background: #28a745; color: white; padding: 6px 12px; border-radius: 15px; font-size: 0.85rem; font-weight: 700; white-space: nowrap;">
                                        ✅ CONFERMATO
                                    </span>
                                <?php else: ?>
                                    <span style="background: #ffc107; color: #2b1e1a; padding: 6px 12px; border-radius: 15px; font-size: 0.85rem; font-weight: 700; white-space: nowrap;">
                                        ⏳ ATTIVO
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Info Evento -->
                        <div style="background: #f8f9fa; padding: 15px; border-radius: 12px; margin-bottom: 15px;">
                            <div style="display: grid; gap: 10px;">
                                <div style="display: flex; align-items: center; gap: 10px;">
                                    <span style="font-size: 1.3rem;">📅</span>
                                    <div>
                                        <div style="font-size: 0.85rem; color: #6c757d;">Data Evento</div>
                                        <div style="font-weight: 700; color: #2b1e1a; font-size: 1.1rem;">
                                            <?php echo date('d/m/Y', strtotime($prev['data_evento'])); ?>
                                        </div>
                                    </div>
                                </div>
                                
                                <div style="display: flex; align-items: center; gap: 10px;">
                                    <span style="font-size: 1.3rem;">🎉</span>
                                    <div>
                                        <div style="font-size: 0.85rem; color: #6c757d;">Tipo Evento</div>
                                        <div style="font-weight: 600; color: #495057;">
                                            <?php echo esc_html($prev['tipo_evento']); ?>
                                        </div>
                                    </div>
                                </div>
                                
                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                                    <div style="display: flex; align-items: center; gap: 8px;">
                                        <span style="font-size: 1.1rem;">🍽️</span>
                                        <div>
                                            <div style="font-size: 0.75rem; color: #6c757d;">Menu</div>
                                            <div style="font-weight: 600; color: #495057; font-size: 0.9rem;">
                                                <?php echo esc_html($prev['tipo_menu']); ?>
                                            </div>
                                        </div>
                                    </div>
                                    <div style="display: flex; align-items: center; gap: 8px;">
                                        <span style="font-size: 1.1rem;">👥</span>
                                        <div>
                                            <div style="font-size: 0.75rem; color: #6c757d;">Invitati</div>
                                            <div style="font-weight: 600; color: #495057; font-size: 0.9rem;">
                                                <?php echo esc_html($prev['numero_invitati']); ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Importo -->
                        <div style="background: linear-gradient(135deg, #c28a4d 0%, #a67339 100%); padding: 15px; border-radius: 12px; margin-bottom: 15px; text-align: center;">
                            <div style="color: rgba(255,255,255,0.8); font-size: 0.85rem; margin-bottom: 5px; text-transform: uppercase; letter-spacing: 1px;">
                                Importo Totale
                            </div>
                            <div style="color: white; font-size: 2rem; font-weight: 800;">
                                €<?php echo number_format(floatval($prev['importo_totale']), 2, ',', '.'); ?>
                            </div>
                            <?php if ($is_confermato): ?>
                                <div style="margin-top: 8px; padding-top: 8px; border-top: 1px solid rgba(255,255,255,0.3);">
                                    <div style="display: flex; justify-content: space-between; color: white; font-size: 0.9rem;">
                                        <span>Acconto: €<?php echo number_format(floatval($prev['acconto']), 2, ',', '.'); ?></span>
                                        <span>Saldo: €<?php echo number_format(floatval($prev['importo_totale']) - floatval($prev['acconto']), 2, ',', '.'); ?></span>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Contatti -->
                        <div style="margin-bottom: 15px; padding: 12px; background: #fff8e6; border-radius: 8px; border-left: 4px solid #ffc107;">
                            <div style="font-size: 0.9rem; color: #495057; margin-bottom: 6px;">
                                <strong>📧</strong> <?php echo esc_html($prev['email']); ?>
                            </div>
                            <div style="font-size: 0.9rem; color: #495057;">
                                <strong>📱</strong> <?php echo esc_html($prev['telefono']); ?>
                            </div>
                        </div>
                        
                        <!-- Azioni -->
                        <div style="display: flex; gap: 8px;">
                            <a href="<?php echo esc_url(admin_url('admin.php?page=disco747-crm&action=edit_preventivo&edit_id=' . $prev['id'])); ?>" 
                               style="flex: 1; background: #007bff; color: white; padding: 10px; border-radius: 8px; text-align: center; text-decoration: none; font-weight: 600; font-size: 0.9rem; transition: all 0.3s;">
                                ✏️ Modifica
                            </a>
                            <?php if (!empty($prev['googledrive_url']) || !empty($prev['excel_url'])): ?>
                            <a href="<?php echo esc_url($prev['googledrive_url'] ?? $prev['excel_url']); ?>" 
                               target="_blank"
                               style="flex: 1; background: #28a745; color: white; padding: 10px; border-radius: 8px; text-align: center; text-decoration: none; font-weight: 600; font-size: 0.9rem; transition: all 0.3s;">
                                📄 Excel
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ============================================================================ -->
    <!-- STATISTICHE PRINCIPALI -->
    <!-- ============================================================================ -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 25px; margin-bottom: 35px;">
        
        <!-- Totale Preventivi -->
        <div class="stat-card" style="background: linear-gradient(135deg, #6c757d 0%, #5a6268 100%); color: white; padding: 30px; border-radius: 18px; text-align: center; box-shadow: 0 8px 25px rgba(108, 117, 125, 0.3); transition: all 0.3s;">
            <div style="font-size: 1rem; opacity: 0.9; margin-bottom: 10px; text-transform: uppercase; letter-spacing: 1.5px; font-weight: 600;">
                Totale Preventivi
            </div>
            <div style="font-size: 3.5rem; font-weight: 800; margin: 15px 0; text-shadow: 2px 2px 4px rgba(0,0,0,0.2);">
                <?php echo number_format($stats['total']); ?>
            </div>
            <div style="font-size: 1.3rem; opacity: 0.8;">📋</div>
        </div>
        
        <!-- Preventivi Attivi -->
        <div class="stat-card" style="background: linear-gradient(135deg, #ffc107 0%, #ff9800 100%); color: white; padding: 30px; border-radius: 18px; text-align: center; box-shadow: 0 8px 25px rgba(255, 193, 7, 0.3); transition: all 0.3s;">
            <div style="font-size: 1rem; opacity: 0.9; margin-bottom: 10px; text-transform: uppercase; letter-spacing: 1.5px; font-weight: 600;">
                In Attesa
            </div>
            <div style="font-size: 3.5rem; font-weight: 800; margin: 15px 0; text-shadow: 2px 2px 4px rgba(0,0,0,0.2);">
                <?php echo number_format($stats['attivi']); ?>
            </div>
            <div style="font-size: 1.3rem; opacity: 0.8;">⏳</div>
        </div>
        
        <!-- Preventivi Confermati -->
        <div class="stat-card" style="background: linear-gradient(135deg, #28a745 0%, #20c997 100%); color: white; padding: 30px; border-radius: 18px; text-align: center; box-shadow: 0 8px 25px rgba(40, 167, 69, 0.3); transition: all 0.3s;">
            <div style="font-size: 1rem; opacity: 0.9; margin-bottom: 10px; text-transform: uppercase; letter-spacing: 1.5px; font-weight: 600;">
                Confermati
            </div>
            <div style="font-size: 3.5rem; font-weight: 800; margin: 15px 0; text-shadow: 2px 2px 4px rgba(0,0,0,0.2);">
                <?php echo number_format($stats['confermati']); ?>
            </div>
            <div style="font-size: 1.3rem; opacity: 0.8;">✅</div>
        </div>
        
        <!-- Questo Mese -->
        <div class="stat-card" style="background: linear-gradient(135deg, #c28a4d 0%, #a67339 100%); color: white; padding: 30px; border-radius: 18px; text-align: center; box-shadow: 0 8px 25px rgba(194, 138, 77, 0.3); transition: all 0.3s;">
            <div style="font-size: 1rem; opacity: 0.9; margin-bottom: 10px; text-transform: uppercase; letter-spacing: 1.5px; font-weight: 600;">
                Questo Mese
            </div>
            <div style="font-size: 3.5rem; font-weight: 800; margin: 15px 0; text-shadow: 2px 2px 4px rgba(0,0,0,0.2);">
                <?php echo number_format($stats['this_month']); ?>
            </div>
            <div style="font-size: 0.9rem; opacity: 0.8; margin-top: 5px;">
                <?php echo date('F Y'); ?>
            </div>
        </div>
    </div>

    <!-- ============================================================================ -->
    <!-- GRAFICI STATISTICHE -->
    <!-- ============================================================================ -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(450px, 1fr)); gap: 30px; margin-bottom: 35px;">
        
        <!-- Grafico: Preventivi per Mese -->
        <div style="background: white; padding: 30px; border-radius: 20px; box-shadow: 0 8px 30px rgba(0,0,0,0.12);">
            <h3 style="margin: 0 0 25px 0; font-size: 1.4rem; color: #2b1e1a; font-weight: 700; border-bottom: 3px solid #007bff; padding-bottom: 12px;">
                📈 Andamento Preventivi (Ultimi 6 Mesi)
            </h3>
            <canvas id="chart-preventivi-mese" style="max-height: 250px;"></canvas>
        </div>
        
        <!-- Grafico: Conferme vs Non Confermati -->
        <div style="background: white; padding: 30px; border-radius: 20px; box-shadow: 0 8px 30px rgba(0,0,0,0.12);">
            <h3 style="margin: 0 0 25px 0; font-size: 1.4rem; color: #2b1e1a; font-weight: 700; border-bottom: 3px solid #28a745; padding-bottom: 12px;">
                🎯 Tasso di Conferma (Ultimi 30 Giorni)
            </h3>
            <canvas id="chart-conferme" style="max-height: 250px;"></canvas>
        </div>
    </div>

    <!-- ============================================================================ -->
    <!-- ULTIMI PREVENTIVI CREATI -->
    <!-- ============================================================================ -->
    <div style="background: white; border-radius: 20px; box-shadow: 0 8px 30px rgba(0,0,0,0.12); margin-bottom: 35px; overflow: hidden;">
        
        <div style="background: linear-gradient(135deg, #6f42c1 0%, #5a32a3 100%); padding: 25px 35px;">
            <h2 style="margin: 0; font-size: 1.8rem; font-weight: 700; color: white;">
                🕐 Ultimi Preventivi Creati
            </h2>
            <p style="margin: 5px 0 0 0; color: rgba(255,255,255,0.9); font-size: 1rem;">
                I 10 preventivi più recenti, ordinati per data di creazione
            </p>
        </div>
        
        <div style="padding: 25px; overflow-x: auto;">
            <?php if (empty($preventivi_recenti)): ?>
                <div style="text-align: center; padding: 40px; color: #6c757d;">
                    <p style="font-size: 1.1rem; margin: 0;">Nessun preventivo disponibile</p>
                </div>
            <?php else: ?>
                <table style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr style="background: #f8f9fa; border-bottom: 2px solid #dee2e6;">
                            <th style="padding: 15px; text-align: left; font-weight: 700; color: #495057; font-size: 0.95rem;">ID</th>
                            <th style="padding: 15px; text-align: left; font-weight: 700; color: #495057; font-size: 0.95rem;">Cliente</th>
                            <th style="padding: 15px; text-align: left; font-weight: 700; color: #495057; font-size: 0.95rem;">Evento</th>
                            <th style="padding: 15px; text-align: center; font-weight: 700; color: #495057; font-size: 0.95rem;">Data Evento</th>
                            <th style="padding: 15px; text-align: center; font-weight: 700; color: #495057; font-size: 0.95rem;">Menu</th>
                            <th style="padding: 15px; text-align: right; font-weight: 700; color: #495057; font-size: 0.95rem;">Importo</th>
                            <th style="padding: 15px; text-align: center; font-weight: 700; color: #495057; font-size: 0.95rem;">Stato</th>
                            <th style="padding: 15px; text-align: center; font-weight: 700; color: #495057; font-size: 0.95rem;">Azioni</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($preventivi_recenti as $prev): 
                            $is_confermato = floatval($prev['acconto']) > 0;
                            $is_annullato = $prev['stato'] === 'annullato';
                        ?>
                        <tr style="border-bottom: 1px solid #dee2e6; transition: background 0.2s;" onmouseover="this.style.background='#f8f9fa'" onmouseout="this.style.background='white'">
                            <td style="padding: 15px;">
                                <strong style="color: #6f42c1; font-size: 1rem;">
                                    #<?php echo esc_html($prev['preventivo_id'] ?? $prev['id']); ?>
                                </strong>
                            </td>
                            <td style="padding: 15px;">
                                <div style="font-weight: 700; color: #2b1e1a; margin-bottom: 3px;">
                                    <?php echo esc_html($prev['nome_cliente']); ?>
                                </div>
                                <div style="font-size: 0.85rem; color: #6c757d;">
                                    <?php echo esc_html($prev['email']); ?>
                                </div>
                            </td>
                            <td style="padding: 15px; color: #495057;">
                                <?php echo esc_html($prev['tipo_evento']); ?>
                            </td>
                            <td style="padding: 15px; text-align: center; font-weight: 600; color: #2b1e1a;">
                                <?php echo date('d/m/Y', strtotime($prev['data_evento'])); ?>
                            </td>
                            <td style="padding: 15px; text-align: center;">
                                <span style="background: #e9ecef; padding: 5px 10px; border-radius: 8px; font-size: 0.85rem; font-weight: 600; color: #495057;">
                                    <?php echo esc_html($prev['tipo_menu']); ?>
                                </span>
                            </td>
                            <td style="padding: 15px; text-align: right;">
                                <div style="font-weight: 700; color: #28a745; font-size: 1.1rem;">
                                    €<?php echo number_format(floatval($prev['importo_totale']), 2, ',', '.'); ?>
                                </div>
                                <?php if ($is_confermato): ?>
                                <div style="font-size: 0.8rem; color: #6c757d; margin-top: 3px;">
                                    Acc: €<?php echo number_format(floatval($prev['acconto']), 2, ',', '.'); ?>
                                </div>
                                <?php endif; ?>
                            </td>
                            <td style="padding: 15px; text-align: center;">
                                <?php if ($is_annullato): ?>
                                    <span style="background: #dc3545; color: white; padding: 6px 12px; border-radius: 12px; font-size: 0.8rem; font-weight: 700;">
                                        ❌ Annullato
                                    </span>
                                <?php elseif ($is_confermato): ?>
                                    <span style="background: #28a745; color: white; padding: 6px 12px; border-radius: 12px; font-size: 0.8rem; font-weight: 700;">
                                        ✅ Confermato
                                    </span>
                                <?php else: ?>
                                    <span style="background: #ffc107; color: #2b1e1a; padding: 6px 12px; border-radius: 12px; font-size: 0.8rem; font-weight: 700;">
                                        ⏳ Attivo
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td style="padding: 15px; text-align: center;">
                                <a href="<?php echo esc_url(admin_url('admin.php?page=disco747-crm&action=edit_preventivo&edit_id=' . $prev['id'])); ?>" 
                                   style="background: #007bff; color: white; padding: 6px 12px; border-radius: 6px; text-decoration: none; font-size: 0.85rem; font-weight: 600; display: inline-block;">
                                    ✏️ Modifica
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <!-- ============================================================================ -->
    <!-- LINK GESTIONE COMPLETA -->
    <!-- ============================================================================ -->
    <div style="text-align: center; margin-bottom: 35px;">
        <a href="<?php echo esc_url(admin_url('admin.php?page=disco747-view-preventivi')); ?>" 
           style="background: linear-gradient(135deg, #17a2b8 0%, #138496 100%); color: white; padding: 15px 40px; border-radius: 30px; text-decoration: none; font-weight: 700; font-size: 1.1rem; box-shadow: 0 6px 20px rgba(23, 162, 184, 0.4); display: inline-block; transition: all 0.3s;">
            📊 Visualizza Tutti i Preventivi nel Database
        </a>
    </div>

</div>

<!-- ============================================================================ -->
<!-- CHART.JS per Grafici -->
<!-- ============================================================================ -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    
    // ========================================================================
    // GRAFICO 1: Preventivi per Mese
    // ========================================================================
    const ctxMese = document.getElementById('chart-preventivi-mese');
    if (ctxMese) {
        new Chart(ctxMese, {
            type: 'line',
            data: {
                labels: <?php echo json_encode(array_column($chart_data['preventivi_per_mese'], 'month')); ?>,
                datasets: [{
                    label: 'Preventivi',
                    data: <?php echo json_encode(array_column($chart_data['preventivi_per_mese'], 'count')); ?>,
                    backgroundColor: 'rgba(0, 123, 255, 0.1)',
                    borderColor: '#007bff',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4,
                    pointBackgroundColor: '#007bff',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2,
                    pointRadius: 6,
                    pointHoverRadius: 8
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        padding: 12,
                        titleFont: {
                            size: 14,
                            weight: 'bold'
                        },
                        bodyFont: {
                            size: 13
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1,
                            font: {
                                size: 12,
                                weight: '600'
                            }
                        },
                        grid: {
                            color: 'rgba(0, 0, 0, 0.05)'
                        }
                    },
                    x: {
                        ticks: {
                            font: {
                                size: 11,
                                weight: '600'
                            }
                        },
                        grid: {
                            display: false
                        }
                    }
                }
            }
        });
    }
    
    // ========================================================================
    // GRAFICO 2: Conferme vs Non Confermati (Donut Chart)
    // ========================================================================
    const ctxConferme = document.getElementById('chart-conferme');
    if (ctxConferme) {
        const confermati = <?php echo $chart_data['confermati']; ?>;
        const nonConfermati = <?php echo $chart_data['non_confermati']; ?>;
        const total = confermati + nonConfermati;
        const percentage = total > 0 ? Math.round((confermati / total) * 100) : 0;
        
        new Chart(ctxConferme, {
            type: 'doughnut',
            data: {
                labels: ['✅ Confermati', '⏳ Da Confermare'],
                datasets: [{
                    data: [confermati, nonConfermati],
                    backgroundColor: [
                        'rgba(40, 167, 69, 0.8)',
                        'rgba(255, 193, 7, 0.8)'
                    ],
                    borderColor: [
                        '#28a745',
                        '#ffc107'
                    ],
                    borderWidth: 3
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            padding: 20,
                            font: {
                                size: 13,
                                weight: '600'
                            },
                            usePointStyle: true,
                            pointStyle: 'circle'
                        }
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        padding: 12,
                        callbacks: {
                            label: function(context) {
                                const value = context.parsed;
                                const percentage = total > 0 ? Math.round((value / total) * 100) : 0;
                                return context.label + ': ' + value + ' (' + percentage + '%)';
                            }
                        }
                    }
                },
                cutout: '65%'
            }
        });
        
        // Aggiungi percentuale al centro
        const chartContainer = ctxConferme.parentElement;
        const centerText = document.createElement('div');
        centerText.style.cssText = 'position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); text-align: center; pointer-events: none;';
        centerText.innerHTML = '<div style="font-size: 2.5rem; font-weight: 800; color: #28a745; line-height: 1;">' + percentage + '%</div><div style="font-size: 0.9rem; color: #6c757d; margin-top: 5px; font-weight: 600;">Confermati</div>';
        chartContainer.style.position = 'relative';
        chartContainer.appendChild(centerText);
    }
    
    // ========================================================================
    // FILTRI PREVENTIVI FUTURI
    // ========================================================================
    const filterButtons = document.querySelectorAll('.filter-btn');
    const preventivoCards = document.querySelectorAll('.preventivo-card');
    
    filterButtons.forEach(btn => {
        btn.addEventListener('click', function() {
            const filter = this.dataset.filter;
            
            // Aggiorna pulsanti attivi
            filterButtons.forEach(b => {
                b.classList.remove('active');
                b.style.background = 'rgba(255,255,255,0.2)';
                b.style.color = 'white';
                b.style.borderColor = 'rgba(255,255,255,0.4)';
            });
            
            this.classList.add('active');
            this.style.background = 'white';
            this.style.color = '#dc3545';
            this.style.borderColor = 'white';
            
            // Filtra card
            preventivoCards.forEach(card => {
                const filters = card.dataset.filters.split(' ');
                if (filter === 'all' || filters.includes(filter)) {
                    card.style.display = 'block';
                    card.style.animation = 'fadeIn 0.3s ease';
                } else {
                    card.style.display = 'none';
                }
            });
        });
    });
});
</script>

<!-- ============================================================================ -->
<!-- CSS CUSTOM -->
<!-- ============================================================================ -->
<style>
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}

.disco747-dashboard-enhanced .stat-card:hover {
    transform: translateY(-5px) scale(1.02);
    box-shadow: 0 12px 35px rgba(0,0,0,0.2) !important;
}

.disco747-dashboard-enhanced .preventivo-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.18) !important;
}

.disco747-dashboard-enhanced .btn-nuovo-preventivo:hover {
    transform: translateY(-3px) scale(1.05);
    box-shadow: 0 10px 35px rgba(40, 167, 69, 0.7) !important;
}

.disco747-dashboard-enhanced a[style*="background:"]:hover {
    filter: brightness(110%);
    transform: translateY(-2px);
}

.disco747-dashboard-enhanced table tr:hover {
    background: #f8f9fa !important;
}

.filter-btn {
    transition: all 0.3s ease;
}

.filter-btn:hover {
    transform: scale(1.05);
    opacity: 0.9;
}

.filter-btn.active {
    background: white !important;
    color: #dc3545 !important;
    border-color: white !important;
}

/* ============================================================================ */
/* RESPONSIVE DESIGN */
/* ============================================================================ */

@media (max-width: 1200px) {
    .disco747-dashboard-enhanced [style*="grid-template-columns: repeat(auto-fill, minmax(340px, 1fr))"] {
        grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)) !important;
    }
}

@media (max-width: 992px) {
    .disco747-dashboard-enhanced [style*="grid-template-columns: repeat(auto-fit, minmax(450px, 1fr))"] {
        grid-template-columns: 1fr !important;
    }
}

@media (max-width: 768px) {
    .disco747-dashboard-enhanced h1 {
        font-size: 2rem !important;
    }
    
    .disco747-dashboard-enhanced [style*="font-size: 3.5rem"] {
        font-size: 2.5rem !important;
    }
    
    .disco747-dashboard-enhanced [style*="grid-template-columns"] {
        grid-template-columns: 1fr !important;
    }
    
    .filtri-rapidi {
        justify-content: center !important;
        width: 100%;
    }
    
    .disco747-dashboard-enhanced table {
        font-size: 0.85rem;
    }
    
    .disco747-dashboard-enhanced table th,
    .disco747-dashboard-enhanced table td {
        padding: 10px 8px !important;
    }
    
    /* Nascondi colonne meno importanti su mobile */
    .disco747-dashboard-enhanced table th:nth-child(3),
    .disco747-dashboard-enhanced table td:nth-child(3),
    .disco747-dashboard-enhanced table th:nth-child(5),
    .disco747-dashboard-enhanced table td:nth-child(5) {
        display: none;
    }
}

@media (max-width: 576px) {
    .disco747-dashboard-enhanced .preventivo-card {
        padding: 15px !important;
    }
    
    .btn-nuovo-preventivo {
        padding: 15px 25px !important;
        font-size: 1rem !important;
        width: 100%;
        text-align: center;
    }
}

/* Animazione caricamento */
@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.5; }
}

.loading-state {
    animation: pulse 1.5s ease-in-out infinite;
}
</style>
