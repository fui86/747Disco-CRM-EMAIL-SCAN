<?php
/**
 * Dashboard Principale 747 Disco CRM - VERSIONE MINIMALISTA
 * 
 * FEATURES:
 * - Solo link di navigazione rapida
 * - Nessun dato/statistica visualizzato
 * - Design pulito e veloce
 * 
 * @package    Disco747_CRM
 * @subpackage Admin/Views
 * @version    12.1.0
 */

if (!defined('ABSPATH')) exit;

$current_user = wp_get_current_user();
$user_name = $current_user->display_name ?? 'Utente';
$version = DISCO747_CRM_VERSION ?? '11.8.0';
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
    <!-- 📅 CALENDARIO EVENTI STILE IPHONE - v7.0 DESKTOP+MOBILE -->
    <!-- ============================================================================ -->
    <?php
    // Carica eventi del mese corrente
    $calendario_mese = isset($_GET['cal_month']) ? intval($_GET['cal_month']) : date('n');
    $calendario_anno = isset($_GET['cal_year']) ? intval($_GET['cal_year']) : date('Y');
    
    $primo_giorno = "{$calendario_anno}-" . sprintf('%02d', $calendario_mese) . "-01";
    $ultimo_giorno = date('Y-m-t', strtotime($primo_giorno));
    
    $eventi_calendario = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$table} 
         WHERE data_evento BETWEEN %s AND %s 
         AND stato IN ('attivo', 'confermato')
         ORDER BY data_evento ASC",
        $primo_giorno,
        $ultimo_giorno
    ), ARRAY_A);
    
    // Raggruppa eventi per data
    $eventi_per_data = array();
    foreach ($eventi_calendario as $evento) {
        $data = date('Y-m-d', strtotime($evento['data_evento']));
        if (!isset($eventi_per_data[$data])) {
            $eventi_per_data[$data] = array();
        }
        $eventi_per_data[$data][] = $evento;
    }
    
    $mesi_nomi = array(
        1 => 'Gennaio', 2 => 'Febbraio', 3 => 'Marzo', 4 => 'Aprile',
        5 => 'Maggio', 6 => 'Giugno', 7 => 'Luglio', 8 => 'Agosto',
        9 => 'Settembre', 10 => 'Ottobre', 11 => 'Novembre', 12 => 'Dicembre'
    );
    ?>
    
    <div id="calendario-eventi" style="background: white; border-radius: 20px; box-shadow: 0 8px 30px rgba(0,0,0,0.12); overflow: hidden; margin-bottom: 30px;">
        
        <!-- Header -->
        <div style="background: linear-gradient(135deg, #1d1d1f 0%, #000000 100%); padding: 25px 30px; text-align: center;">
            
            <!-- Navigazione mese -->
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <button onclick="cambioMese(-1)" style="background: rgba(255,255,255,0.15); border: none; color: white; width: 45px; height: 45px; border-radius: 50%; cursor: pointer; font-size: 1.8rem; display: flex; align-items: center; justify-content: center; transition: all 0.2s;" onmouseover="this.style.background='rgba(255,255,255,0.25)';" onmouseout="this.style.background='rgba(255,255,255,0.15)';">‹</button>
                
                <div>
                    <h2 id="calendario-titolo" style="margin: 0; font-size: 2rem; font-weight: 700; color: white;">
                        <?php echo $mesi_nomi[$calendario_mese]; ?>
                    </h2>
                    <p style="margin: 5px 0 0 0; font-size: 1.2rem; font-weight: 600; color: rgba(255,255,255,0.7);">
                        <?php echo $calendario_anno; ?>
                    </p>
                </div>
                
                <button onclick="cambioMese(1)" style="background: rgba(255,255,255,0.15); border: none; color: white; width: 45px; height: 45px; border-radius: 50%; cursor: pointer; font-size: 1.8rem; display: flex; align-items: center; justify-content: center; transition: all 0.2s;" onmouseover="this.style.background='rgba(255,255,255,0.25)';" onmouseout="this.style.background='rgba(255,255,255,0.15)';">›</button>
            </div>
            
            <p style="margin: 0; color: rgba(255,255,255,0.8); font-size: 1rem;">
                <?php echo count($eventi_calendario); ?> eventi questo mese
            </p>
            
        </div>
        
        <!-- Griglia Calendario -->
        <div style="padding: 30px;">
            
            <div class="cal-grid-container" style="display: grid; grid-template-columns: repeat(7, 1fr); gap: 10px;">
                
                <!-- Intestazioni giorni -->
                <?php 
                $giorni_settimana = array('L', 'M', 'M', 'G', 'V', 'S', 'D');
                foreach ($giorni_settimana as $g): 
                ?>
                    <div style="text-align: center; font-weight: 700; color: #8e8e93; font-size: 0.9rem; padding: 10px 0; text-transform: uppercase;">
                        <?php echo $g; ?>
                    </div>
                <?php endforeach; ?>
                
                <!-- Giorni del mese -->
                <?php
                $primo_giorno_settimana = date('N', strtotime($primo_giorno));
                $giorni_nel_mese = date('t', strtotime($primo_giorno));
                $oggi = date('Y-m-d');
                
                // Celle vuote iniziali
                for ($i = 1; $i < $primo_giorno_settimana; $i++) {
                    echo '<div class="cal-day-empty"></div>';
                }
                
                // Giorni del mese
                for ($giorno = 1; $giorno <= $giorni_nel_mese; $giorno++) {
                    $data_corrente = "{$calendario_anno}-" . sprintf('%02d', $calendario_mese) . "-" . sprintf('%02d', $giorno);
                    $ha_eventi = isset($eventi_per_data[$data_corrente]);
                    $is_oggi = $data_corrente === $oggi;
                    
                    $confermati = 0;
                    $attivi = 0;
                    if ($ha_eventi) {
                        foreach ($eventi_per_data[$data_corrente] as $evt) {
                            if ($evt['stato'] === 'confermato' || floatval($evt['acconto']) > 0) {
                                $confermati++;
                            } else {
                                $attivi++;
                            }
                        }
                    }
                    
                    $bg = $is_oggi ? 'linear-gradient(135deg, #007aff 0%, #0056b3 100%)' : ($ha_eventi ? '#f0f0f5' : 'transparent');
                    $color = $is_oggi ? 'white' : ($ha_eventi ? '#000' : '#8e8e93');
                    $weight = $is_oggi ? '700' : ($ha_eventi ? '600' : '400');
                    ?>
                    <div class="cal-day" 
                         onclick="<?php echo $ha_eventi ? "mostraEventi('{$data_corrente}')" : ''; ?>" 
                         style="background: <?php echo $bg; ?>; border-radius: 14px; display: flex; flex-direction: column; align-items: center; justify-content: center; font-size: 1.2rem; font-weight: <?php echo $weight; ?>; color: <?php echo $color; ?>; cursor: <?php echo $ha_eventi ? 'pointer' : 'default'; ?>; transition: all 0.2s; position: relative; min-height: 80px; padding: 10px;"
                         <?php if ($ha_eventi): ?>
                         onmouseover="this.style.transform='scale(1.05)'; this.style.boxShadow='0 4px 12px rgba(0,0,0,0.15)';"
                         onmouseout="this.style.transform='scale(1)'; this.style.boxShadow='none';"
                         <?php endif; ?>>
                        
                        <span><?php echo $giorno; ?></span>
                        
                        <?php if ($ha_eventi): ?>
                            <div style="display: flex; gap: 4px; margin-top: 6px;">
                                <?php if ($confermati > 0): ?>
                                    <div class="cal-dot" style="width: 8px; height: 8px; background: #34c759; border-radius: 50%;"></div>
                                <?php endif; ?>
                                <?php if ($attivi > 0): ?>
                                    <div class="cal-dot" style="width: 8px; height: 8px; background: #007aff; border-radius: 50%;"></div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php } ?>
                
            </div>
            
            <!-- Eventi del giorno selezionato -->
            <div id="eventi-giorno" style="display: none; margin-top: 30px; padding-top: 30px; border-top: 2px solid #e5e5ea;">
                <h3 id="eventi-giorno-titolo" style="margin: 0 0 20px 0; font-size: 1.3rem; font-weight: 700; color: #1d1d1f;"></h3>
                <div id="eventi-giorno-lista"></div>
            </div>
            
        </div>
        
    </div>
    
    <script>
    const eventiPerData = <?php echo json_encode($eventi_per_data); ?>;
    
    function cambioMese(delta) {
        const params = new URLSearchParams(window.location.search);
        let mese = <?php echo $calendario_mese; ?> + delta;
        let anno = <?php echo $calendario_anno; ?>;
        
        if (mese > 12) { mese = 1; anno++; }
        if (mese < 1) { mese = 12; anno--; }
        
        params.set('cal_month', mese);
        params.set('cal_year', anno);
        window.location.search = params.toString();
    }
    
    function mostraEventi(data) {
        const eventi = eventiPerData[data];
        if (!eventi || eventi.length === 0) return;
        
        const container = document.getElementById('eventi-giorno');
        const titolo = document.getElementById('eventi-giorno-titolo');
        const lista = document.getElementById('eventi-giorno-lista');
        
        const dataObj = new Date(data + 'T00:00:00');
        const formatter = new Intl.DateTimeFormat('it-IT', { weekday: 'long', day: 'numeric', month: 'long' });
        titolo.textContent = '📅 ' + formatter.format(dataObj).charAt(0).toUpperCase() + formatter.format(dataObj).slice(1);
        
        lista.innerHTML = eventi.map(function(evento) {
            const badge = (evento.stato === 'confermato' || parseFloat(evento.acconto) > 0) 
                ? '<span style="background: #34c759; color: white; padding: 8px 16px; border-radius: 12px; font-size: 0.85rem; font-weight: 700;">💰 Confermato</span>'
                : '<span style="background: #007aff; color: white; padding: 8px 16px; border-radius: 12px; font-size: 0.85rem; font-weight: 700;">📋 Attivo</span>';
            
            return `
                <div style="background: #f8f9fa; padding: 20px; border-radius: 14px; margin-bottom: 15px;">
                    <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 12px; gap: 15px; flex-wrap: wrap;">
                        <div style="flex: 1; min-width: 200px;">
                            <div style="font-weight: 700; color: #1d1d1f; font-size: 1.2rem; margin-bottom: 5px;">
                                ${evento.tipo_evento || 'Evento'}
                            </div>
                            <div style="color: #6c757d; font-size: 1rem;">
                                ${evento.nome_referente || ''} ${evento.cognome_referente || ''}
                            </div>
                        </div>
                        ${badge}
                    </div>
                    <div style="display: flex; gap: 12px; flex-wrap: wrap;">
                        ${evento.telefono ? `<a href="https://wa.me/${evento.telefono.replace(/[^0-9]/g, '')}" target="_blank" style="background: #25D366; color: white; padding: 10px 20px; border-radius: 10px; text-decoration: none; font-size: 0.95rem; font-weight: 600; display: inline-flex; align-items: center; gap: 8px;">📱 WhatsApp</a>` : ''}
                        ${evento.email ? `<a href="mailto:${evento.email}" style="background: #007aff; color: white; padding: 10px 20px; border-radius: 10px; text-decoration: none; font-size: 0.95rem; font-weight: 600; display: inline-flex; align-items: center; gap: 8px;">✉️ Email</a>` : ''}
                    </div>
                </div>
            `;
        }).join('');
        
        container.style.display = 'block';
        container.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }
    </script>
    
    <script>
    // FORZA GRIGLIA 7 COLONNE SU MOBILE
    document.addEventListener('DOMContentLoaded', function() {
        const grid = document.querySelector('.cal-grid-container');
        if (grid && window.innerWidth <= 768) {
            grid.style.display = 'grid';
            grid.style.gridTemplateColumns = 'repeat(7, 1fr)';
            console.log('✅ Griglia 7 colonne forzata');
        }
    });
    </script>
    
    <style>
    /* DESKTOP: Grande e bello (default) */
    .cal-grid-container {
        display: grid !important;
        grid-template-columns: repeat(7, 1fr) !important;
        gap: 10px;
    }
    
    .cal-day {
        min-height: 80px;
    }
    
    .cal-day-empty {
        min-height: 80px;
    }
    
    /* MOBILE: Compatto SOLO in verticale */
    @media screen and (max-width: 768px) and (orientation: portrait) {
        /* Container calendario */
        #calendario-eventi {
            margin: 0 5px 20px 5px;
            border-radius: 16px;
        }
        
        /* Header compatto */
        #calendario-eventi > div:first-child {
            padding: 15px 10px !important;
        }
        
        /* Bottoni frecce più piccoli */
        #calendario-eventi button {
            width: 35px !important;
            height: 35px !important;
            font-size: 1.4rem !important;
        }
        
        /* Titolo mese */
        #calendario-titolo {
            font-size: 1.3rem !important;
        }
        
        /* Anno */
        #calendario-eventi > div:first-child > div > div > p {
            font-size: 0.9rem !important;
        }
        
        /* Contatore eventi */
        #calendario-eventi > div:first-child > p {
            font-size: 0.8rem !important;
        }
        
        /* Container griglia */
        #calendario-eventi > div:nth-child(2) {
            padding: 12px !important;
        }
        
        /* GRIGLIA: FORZA 7 COLONNE SEMPRE */
        .cal-grid-container {
            display: grid !important;
            grid-template-columns: repeat(7, 1fr) !important;
            gap: 3px !important;
            grid-auto-flow: row !important;
        }
        
        /* Intestazioni giorni */
        .cal-grid-container > div:nth-child(-n+7) {
            font-size: 0.7rem !important;
            padding: 5px 0 !important;
        }
        
        /* CELLE GIORNI: compatte ma cliccabili */
        .cal-day {
            min-height: 42px !important;
            max-height: 42px !important;
            font-size: 0.95rem !important;
            padding: 4px !important;
            border-radius: 10px !important;
            width: 100% !important;
        }
        
        .cal-day-empty {
            min-height: 42px !important;
            max-height: 42px !important;
            width: 100% !important;
        }
        
        /* Pallini eventi più piccoli */
        .cal-day > div {
            margin-top: 3px !important;
            gap: 2px !important;
        }
        
        .cal-dot {
            width: 5px !important;
            height: 5px !important;
        }
        
        /* Eventi giorno */
        #eventi-giorno {
            margin-top: 15px !important;
            padding-top: 15px !important;
        }
        
        #eventi-giorno-titolo {
            font-size: 1rem !important;
        }
        
        #eventi-giorno-lista > div {
            padding: 15px !important;
            margin-bottom: 10px !important;
        }
        
        #eventi-giorno-lista a {
            padding: 8px 14px !important;
            font-size: 0.85rem !important;
        }
    }
    
    /* MOBILE PICCOLO: ancora più compatto */
    @media screen and (max-width: 480px) and (orientation: portrait) {
        .cal-grid-container {
            display: grid !important;
            grid-template-columns: repeat(7, 1fr) !important;
            gap: 2px !important;
        }
        
        .cal-day {
            min-height: 38px !important;
            max-height: 38px !important;
            font-size: 0.85rem !important;
            padding: 2px !important;
            width: 100% !important;
        }
        
        .cal-day-empty {
            min-height: 38px !important;
            max-height: 38px !important;
            width: 100% !important;
        }
        
        .cal-dot {
            width: 4px !important;
            height: 4px !important;
        }
    }
    
    /* TABLET: Medio */
    @media screen and (min-width: 769px) and (max-width: 1024px) {
        .cal-day {
            min-height: 70px !important;
            font-size: 1.1rem !important;
        }
        
        .cal-day-empty {
            min-height: 70px !important;
        }
    }
    </style>

    <!-- SEZIONE INSIGHTS: Link Analisi Finanziaria + Eventi Imminenti -->
    <!-- ============================================================================ -->
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px; margin-bottom: 35px;">
        
        <!-- ============= STATISTICHE & AZIONI RAPIDE ============= -->
        <div style="background: white; border-radius: 20px; box-shadow: 0 8px 30px rgba(0,0,0,0.12); overflow: hidden;">
            <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 25px 30px;">
                <h2 style="margin: 0; font-size: 1.6rem; font-weight: 700; color: white;">
                    📊 Statistiche & Azioni
                </h2>
                <p style="margin: 5px 0 0 0; color: rgba(255,255,255,0.9); font-size: 0.95rem;">
                    Panoramica rapida e scorciatoie
                </p>
            </div>
            
            <div style="padding: 25px;">
                
                <!-- Statistiche Rapide -->
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 25px;">
                    
                    <!-- Preventivi Totali -->
                    <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 20px; border-radius: 12px; color: white; text-align: center;">
                        <div style="font-size: 0.8rem; opacity: 0.9; margin-bottom: 8px; text-transform: uppercase; letter-spacing: 0.5px;">
                            📋 Totali
                        </div>
                        <div style="font-size: 2.5rem; font-weight: 800; line-height: 1;">
                            <?php echo number_format($stats['total']); ?>
                        </div>
                    </div>
                    
                    <!-- Preventivi Attivi -->
                    <div style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); padding: 20px; border-radius: 12px; color: white; text-align: center;">
                        <div style="font-size: 0.8rem; opacity: 0.9; margin-bottom: 8px; text-transform: uppercase; letter-spacing: 0.5px;">
                            🔵 Attivi
                        </div>
                        <div style="font-size: 2.5rem; font-weight: 800; line-height: 1;">
                            <?php echo number_format($stats['attivi']); ?>
                        </div>
                    </div>
                    
                    <!-- Preventivi Confermati -->
                    <div style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); padding: 20px; border-radius: 12px; color: white; text-align: center;">
                        <div style="font-size: 0.8rem; opacity: 0.9; margin-bottom: 8px; text-transform: uppercase; letter-spacing: 0.5px;">
                            ✅ Confermati
                        </div>
                        <div style="font-size: 2.5rem; font-weight: 800; line-height: 1;">
                            <?php echo number_format($stats['confermati']); ?>
                        </div>
                    </div>
                    
                    <!-- Questo Mese -->
                    <div style="background: linear-gradient(135deg, #fa709a 0%, #fee140 100%); padding: 20px; border-radius: 12px; color: white; text-align: center;">
                        <div style="font-size: 0.8rem; opacity: 0.9; margin-bottom: 8px; text-transform: uppercase; letter-spacing: 0.5px;">
                            📆 Questo Mese
                        </div>
                        <div style="font-size: 2.5rem; font-weight: 800; line-height: 1;">
                            <?php echo number_format($stats['this_month']); ?>
                        </div>
                    </div>
                    
                </div>
                
                <!-- Azioni Rapide -->
                <div style="background: #f8f9fa; padding: 20px; border-radius: 12px; margin-bottom: 20px;">
                    <h3 style="margin: 0 0 15px 0; font-size: 0.9rem; font-weight: 700; color: #495057; text-transform: uppercase; letter-spacing: 0.5px;">
                        ⚡ Azioni Rapide
                    </h3>
                    <div style="display: grid; gap: 10px;">
                        
                        <a href="<?php echo admin_url('admin.php?page=disco747-crm&action=new_preventivo'); ?>" 
                           style="background: linear-gradient(135deg, #28a745 0%, #20c997 100%); color: white; padding: 15px 20px; border-radius: 10px; text-decoration: none; font-weight: 600; display: flex; align-items: center; gap: 12px; transition: all 0.3s ease; box-shadow: 0 2px 8px rgba(40, 167, 69, 0.2);"
                           onmouseover="this.style.transform='translateX(5px)'; this.style.boxShadow='0 4px 16px rgba(40, 167, 69, 0.3)'"
                           onmouseout="this.style.transform='translateX(0)'; this.style.boxShadow='0 2px 8px rgba(40, 167, 69, 0.2)'">
                            <span style="font-size: 1.5rem;">➕</span>
                            <span>Nuovo Preventivo</span>
                        </a>
                        
                        <a href="<?php echo admin_url('admin.php?page=disco747-view-preventivi'); ?>" 
                           style="background: linear-gradient(135deg, #007bff 0%, #0056b3 100%); color: white; padding: 15px 20px; border-radius: 10px; text-decoration: none; font-weight: 600; display: flex; align-items: center; gap: 12px; transition: all 0.3s ease; box-shadow: 0 2px 8px rgba(0, 123, 255, 0.2);"
                           onmouseover="this.style.transform='translateX(5px)'; this.style.boxShadow='0 4px 16px rgba(0, 123, 255, 0.3)'"
                           onmouseout="this.style.transform='translateX(0)'; this.style.boxShadow='0 2px 8px rgba(0, 123, 255, 0.2)'">
                            <span style="font-size: 1.5rem;">📊</span>
                            <span>View Database</span>
                        </a>
                        
                        <a href="<?php echo admin_url('admin.php?page=disco747-scan-excel'); ?>" 
                           style="background: linear-gradient(135deg, #6f42c1 0%, #5a32a3 100%); color: white; padding: 15px 20px; border-radius: 10px; text-decoration: none; font-weight: 600; display: flex; align-items: center; gap: 12px; transition: all 0.3s ease; box-shadow: 0 2px 8px rgba(111, 66, 193, 0.2);"
                           onmouseover="this.style.transform='translateX(5px)'; this.style.boxShadow='0 4px 16px rgba(111, 66, 193, 0.3)'"
                           onmouseout="this.style.transform='translateX(0)'; this.style.boxShadow='0 2px 8px rgba(111, 66, 193, 0.2)'">
                            <span style="font-size: 1.5rem;">🔄</span>
                            <span>Scansione Excel</span>
                        </a>
                        
                        <a href="<?php echo admin_url('admin.php?page=disco747-financial'); ?>" 
                           style="background: linear-gradient(135deg, #ffc107 0%, #ff9800 100%); color: white; padding: 15px 20px; border-radius: 10px; text-decoration: none; font-weight: 600; display: flex; align-items: center; gap: 12px; transition: all 0.3s ease; box-shadow: 0 2px 8px rgba(255, 193, 7, 0.2);"
                           onmouseover="this.style.transform='translateX(5px)'; this.style.boxShadow='0 4px 16px rgba(255, 193, 7, 0.3)'"
                           onmouseout="this.style.transform='translateX(0)'; this.style.boxShadow='0 2px 8px rgba(255, 193, 7, 0.2)'">
                            <span style="font-size: 1.5rem;">💰</span>
                            <span>Analisi Finanziaria</span>
                        </a>
                        
                    </div>
                </div>
                
                <!-- Info Rapide -->
                <div style="background: #e7f3ff; border-left: 4px solid #007bff; padding: 15px; border-radius: 8px;">
                    <div style="display: flex; align-items: start; gap: 12px;">
                        <div style="font-size: 1.5rem;">💡</div>
                        <div>
                            <div style="font-weight: 700; color: #0056b3; margin-bottom: 5px; font-size: 0.9rem;">
                                Tasso di Conversione
                            </div>
                            <div style="color: #495057; font-size: 0.85rem;">
                                <?php 
                                $tasso = $stats['total'] > 0 ? round(($stats['confermati'] / $stats['total']) * 100, 1) : 0;
                                echo $tasso; 
                                ?>% dei preventivi vengono confermati
                                <span style="color: #6c757d;">(<?php echo $stats['confermati']; ?>/<?php echo $stats['total']; ?>)</span>
                            </div>
                        </div>
                    </div>
                </div>
                
            </div>
        </div>
        
        <!-- ============= EVENTI IMMINENTI ============= -->
        <div style="background: white; border-radius: 20px; box-shadow: 0 8px 30px rgba(0,0,0,0.12); overflow: hidden;">
            <div style="background: linear-gradient(135deg, #dc3545 0%, #c82333 100%); padding: 25px 30px;">
                <h2 style="margin: 0; font-size: 1.6rem; font-weight: 700; color: white;">
                    ⚡ Eventi Imminenti
                </h2>
                <p style="margin: 5px 0 0 0; color: rgba(255,255,255,0.9); font-size: 0.95rem;">
                    Prossimi 14 giorni · <?php echo count($eventi_imminenti); ?> eventi
                </p>
            </div>
            
            <div style="padding: 25px; max-height: 520px; overflow-y: auto;">
                <?php if (empty($eventi_imminenti)): ?>
                    <div style="text-align: center; padding: 80px 20px; color: #6c757d;">
                        <div style="font-size: 4rem; margin-bottom: 15px; opacity: 0.2;">😴</div>
                        <h3 style="margin: 0 0 8px 0; font-size: 1.3rem; color: #495057;">Nessun Evento Imminente</h3>
                        <p style="margin: 0; font-size: 0.95rem;">I prossimi 14 giorni sono liberi</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($eventi_imminenti as $evento): 
                        $is_confermato = floatval($evento['acconto']) > 0;
                        $is_annullato = $evento['stato'] === 'annullato';
                        $giorni_mancanti = floor((strtotime($evento['data_evento']) - time()) / (60 * 60 * 24));
                        
                        // Colore urgenza
                        if ($giorni_mancanti <= 3) {
                            $urgenza_color = '#dc3545';
                            $urgenza_bg = '#ffe6e6';
                            $urgenza_text = 'URGENTE';
                        } elseif ($giorni_mancanti <= 7) {
                            $urgenza_color = '#ff9800';
                            $urgenza_bg = '#fff8e6';
                            $urgenza_text = 'Vicino';
                        } else {
                            $urgenza_color = '#28a745';
                            $urgenza_bg = '#e8f5e9';
                            $urgenza_text = 'Programmato';
                        }
                    ?>
                    <div style="background: white; border: 2px solid <?php echo $urgenza_color; ?>; border-radius: 12px; padding: 18px; margin-bottom: 15px; transition: all 0.3s; box-shadow: 0 2px 8px rgba(0,0,0,0.06);" onmouseover="this.style.boxShadow='0 4px 16px rgba(0,0,0,0.12)'" onmouseout="this.style.boxShadow='0 2px 8px rgba(0,0,0,0.06)'">
                        
                        <!-- Header Evento -->
                        <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 12px;">
                            <div style="flex: 1;">
                                <h4 style="margin: 0 0 5px 0; font-size: 1.1rem; font-weight: 700; color: #2b1e1a;">
                                    <?php echo esc_html($evento['nome_cliente']); ?>
                                </h4>
                                <div style="font-size: 0.8rem; color: #6c757d;">
                                    #<?php echo esc_html($evento['preventivo_id'] ?? $evento['id']); ?> · <?php echo esc_html($evento['tipo_evento']); ?>
                                </div>
                            </div>
                            <div style="text-align: right;">
                                <?php if ($is_annullato): ?>
                                    <span style="background: #dc3545; color: white; padding: 4px 10px; border-radius: 10px; font-size: 0.75rem; font-weight: 700; white-space: nowrap;">
                                        ❌ ANNULLATO
                                    </span>
                                <?php elseif ($is_confermato): ?>
                                    <span style="background: #28a745; color: white; padding: 4px 10px; border-radius: 10px; font-size: 0.75rem; font-weight: 700; white-space: nowrap;">
                                        ✅ OK
                                    </span>
                                <?php else: ?>
                                    <span style="background: #ffc107; color: #2b1e1a; padding: 4px 10px; border-radius: 10px; font-size: 0.75rem; font-weight: 700; white-space: nowrap;">
                                        ⏳ ATTIVO
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Data e Urgenza -->
                        <div style="background: <?php echo $urgenza_bg; ?>; padding: 12px; border-radius: 8px; margin-bottom: 12px; border-left: 4px solid <?php echo $urgenza_color; ?>;">
                            <div style="display: flex; justify-content: space-between; align-items: center;">
                                <div>
                                    <div style="font-size: 1.3rem; font-weight: 800; color: <?php echo $urgenza_color; ?>; margin-bottom: 3px;">
                                        <?php echo date('d/m/Y', strtotime($evento['data_evento'])); ?>
                                    </div>
                                    <div style="font-size: 0.8rem; color: #6c757d;">
                                        <?php echo ($giorni_mancanti == 0) ? 'OGGI!' : ($giorni_mancanti == 1 ? 'DOMANI!' : "Tra {$giorni_mancanti} giorni"); ?>
                                    </div>
                                </div>
                                <div style="background: <?php echo $urgenza_color; ?>; color: white; padding: 6px 12px; border-radius: 10px; font-size: 0.75rem; font-weight: 700;">
                                    <?php echo $urgenza_text; ?>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Dettagli Rapidi -->
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 12px; font-size: 0.85rem;">
                            <div style="background: #f8f9fa; padding: 8px; border-radius: 6px; text-align: center;">
                                <div style="color: #6c757d; font-size: 0.75rem; margin-bottom: 3px;">Menu</div>
                                <div style="font-weight: 700; color: #495057;"><?php echo esc_html($evento['tipo_menu']); ?></div>
                            </div>
                            <div style="background: #f8f9fa; padding: 8px; border-radius: 6px; text-align: center;">
                                <div style="color: #6c757d; font-size: 0.75rem; margin-bottom: 3px;">Invitati</div>
                                <div style="font-weight: 700; color: #495057;"><?php echo esc_html($evento['numero_invitati']); ?> pax</div>
                            </div>
                        </div>
                        
                        <!-- Importo -->
                        <div style="background: linear-gradient(135deg, #c28a4d 0%, #a67339 100%); padding: 12px; border-radius: 8px; margin-bottom: 12px; text-align: center;">
                            <div style="color: rgba(255,255,255,0.8); font-size: 0.75rem; margin-bottom: 3px;">Importo</div>
                            <div style="color: white; font-size: 1.5rem; font-weight: 800;">
                                €<?php echo number_format(floatval($evento['importo_totale']), 0, ',', '.'); ?>
                            </div>
                            <?php if ($is_confermato): ?>
                                <div style="color: rgba(255,255,255,0.9); font-size: 0.75rem; margin-top: 4px;">
                                    Saldo: €<?php echo number_format(floatval($evento['importo_totale']) - floatval($evento['acconto']), 0, ',', '.'); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Azioni -->
                        <div style="display: flex; gap: 8px;">
                            <a href="<?php echo esc_url(admin_url('admin.php?page=disco747-crm&action=edit_preventivo&edit_id=' . $evento['id'])); ?>" 
                               style="flex: 1; background: #007bff; color: white; padding: 8px; border-radius: 6px; text-align: center; text-decoration: none; font-weight: 600; font-size: 0.8rem;">
                                ✏️ Gestisci
                            </a>
                            <?php 
                            // Formatta numero per WhatsApp (rimuovi spazi, trattini, parentesi)
                            $whatsapp_number = preg_replace('/[^0-9+]/', '', $evento['telefono']);
                            // Se non inizia con +, aggiungi prefisso Italia
                            if (substr($whatsapp_number, 0, 1) !== '+') {
                                $whatsapp_number = '+39' . $whatsapp_number;
                            }
                            ?>
                            <a href="https://wa.me/<?php echo esc_attr($whatsapp_number); ?>" 
                               target="_blank"
                               title="Apri chat WhatsApp con <?php echo esc_attr($evento['nome_cliente']); ?>"
                               style="background: #25D366; color: white; padding: 8px 12px; border-radius: 6px; text-decoration: none; font-size: 0.8rem; display: flex; align-items: center; justify-content: center;">
                                📱
                            </a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
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

/* ============================================================================ */
/* CALENDARIO RESPONSIVE - MOBILE COMPATTO v3.0 ULTRA AGGRESSIVE */
/* Update: <?php echo time(); ?> */
/* ============================================================================ */

/* TABLET - Calendario Compatto */
@media screen and (max-width: 768px) {
    /* Container principale */
    #calendario-eventi {
        margin: 0 !important;
        padding: 0 !important;
        border-radius: 12px !important;
        margin-bottom: 20px !important;
    }
    
    /* Header background */
    #calendario-eventi > div:first-child {
        padding: 12px 10px !important;
    }
    
    /* Selettori mese/anno container */
    #calendario-eventi > div:first-child > div:first-child {
        gap: 6px !important;
        margin-bottom: 10px !important;
        padding: 0 5px !important;
    }
    
    /* Label "Vai a:" */
    #calendario-eventi > div:first-child > div:first-child > label {
        font-size: 0.7rem !important;
        display: none !important;
    }
    
    /* Select mese e anno */
    #calendario-select-mese,
    #calendario-select-anno {
        padding: 6px 10px !important;
        font-size: 0.75rem !important;
        min-width: 100px !important;
        border-radius: 6px !important;
    }
    
    /* Bottone "Oggi" */
    #calendario-eventi > div:first-child > div:first-child > button {
        padding: 6px 12px !important;
        font-size: 0.75rem !important;
        border-radius: 6px !important;
    }
    
    /* Container navigazione frecce */
    #calendario-eventi > div:first-child > div:nth-child(2) {
        gap: 10px !important;
    }
    
    /* Bottoni frecce */
    #calendario-eventi > div:first-child > div:nth-child(2) > button {
        padding: 6px 10px !important;
        font-size: 1rem !important;
    }
    
    /* Titolo mese */
    #calendario-titolo {
        font-size: 1.1rem !important;
        margin: 0 !important;
    }
    
    /* Contatore eventi */
    #calendario-eventi > div:first-child > div:nth-child(2) > div > p {
        font-size: 0.7rem !important;
        margin-top: 2px !important;
    }
    
    /* Container griglia (padding: 20px) */
    #calendario-eventi > div:nth-child(2) {
        padding: 10px 8px !important;
    }
    
    /* Griglia calendario */
    #calendario-eventi > div:nth-child(2) > div:first-child {
        gap: 2px !important;
        margin-bottom: 12px !important;
    }
    
    /* Intestazioni giorni settimana */
    #calendario-eventi > div:nth-child(2) > div:first-child > div:nth-child(-n+7) {
        font-size: 0.6rem !important;
        padding: 5px 0 !important;
    }
    
    /* Celle giorni (tutti i div dopo le intestazioni) */
    #calendario-eventi > div:nth-child(2) > div:first-child > div[onclick] {
        font-size: 0.7rem !important;
        min-height: 36px !important;
        max-height: 36px !important;
        width: auto !important;
        padding: 2px !important;
    }
    
    /* Pallini eventi dentro le celle */
    #calendario-eventi > div:nth-child(2) > div:first-child > div[onclick] > div {
        margin-top: 1px !important;
        gap: 1px !important;
    }
    
    #calendario-eventi > div:nth-child(2) > div:first-child > div[onclick] > div > div {
        width: 3px !important;
        height: 3px !important;
        margin-top: 0 !important;
    }
    
    /* Area eventi giorno */
    #eventi-giorno {
        padding-top: 12px !important;
        margin-top: 12px !important;
    }
    
    #eventi-giorno-titolo {
        font-size: 0.9rem !important;
        margin-bottom: 10px !important;
    }
    
    #eventi-giorno-lista > div {
        padding: 10px !important;
        margin-bottom: 6px !important;
    }
    
    #eventi-giorno-lista a {
        padding: 6px 10px !important;
        font-size: 0.7rem !important;
    }
}

/* MOBILE PICCOLO - Ultra Compatto Stile iPhone */
@media screen and (max-width: 576px) {
    /* Container */
    #calendario-eventi {
        margin: 0 !important;
        margin-bottom: 15px !important;
    }
    
    /* Header */
    #calendario-eventi > div:first-child {
        padding: 10px 8px !important;
    }
    
    /* Selettori container */
    #calendario-eventi > div:first-child > div:first-child {
        flex-direction: row !important;
        gap: 4px !important;
        margin-bottom: 8px !important;
        padding: 0 3px !important;
        justify-content: center !important;
    }
    
    /* Select */
    #calendario-select-mese,
    #calendario-select-anno {
        padding: 5px 8px !important;
        font-size: 0.7rem !important;
        min-width: 85px !important;
        width: auto !important;
    }
    
    /* Bottone Oggi */
    #calendario-eventi > div:first-child > div:first-child > button {
        padding: 5px 10px !important;
        font-size: 0.7rem !important;
        width: auto !important;
    }
    
    /* Titolo */
    #calendario-titolo {
        font-size: 1rem !important;
    }
    
    /* Contatore */
    #calendario-eventi > div:first-child > div:nth-child(2) > div > p {
        font-size: 0.65rem !important;
        margin-top: 1px !important;
        margin-bottom: 0 !important;
    }
    
    /* Frecce */
    #calendario-eventi > div:first-child > div:nth-child(2) > button {
        padding: 5px 8px !important;
        font-size: 0.9rem !important;
    }
    
    /* Container griglia */
    #calendario-eventi > div:nth-child(2) {
        padding: 8px 5px !important;
    }
    
    /* Griglia */
    #calendario-eventi > div:nth-child(2) > div:first-child {
        gap: 1px !important;
        margin-bottom: 10px !important;
    }
    
    /* Intestazioni */
    #calendario-eventi > div:nth-child(2) > div:first-child > div:nth-child(-n+7) {
        font-size: 0.55rem !important;
        padding: 3px 0 !important;
        letter-spacing: -0.3px !important;
    }
    
    /* Celle giorni - RIDOTTE A 32px */
    #calendario-eventi > div:nth-child(2) > div:first-child > div[onclick] {
        font-size: 0.65rem !important;
        min-height: 32px !important;
        max-height: 32px !important;
        width: auto !important;
        padding: 1px !important;
        font-weight: 500 !important;
    }
    
    /* Pallini micro */
    #calendario-eventi > div:nth-child(2) > div:first-child > div[onclick] > div {
        margin-top: 0px !important;
        gap: 1px !important;
    }
    
    #calendario-eventi > div:nth-child(2) > div:first-child > div[onclick] > div > div {
        width: 2.5px !important;
        height: 2.5px !important;
        margin-top: 0 !important;
    }
    
    /* Eventi giorno */
    #eventi-giorno {
        padding-top: 10px !important;
        margin-top: 10px !important;
    }
    
    #eventi-giorno-titolo {
        font-size: 0.85rem !important;
        margin: 0 0 8px 0 !important;
    }
    
    #eventi-giorno-lista > div {
        padding: 8px !important;
        margin-bottom: 5px !important;
        border-radius: 8px !important;
    }
    
    #eventi-giorno-lista a {
        padding: 5px 8px !important;
        font-size: 0.65rem !important;
        border-radius: 12px !important;
    }
}

/* MOBILE EXTRA PICCOLO - Massima Compattezza */
@media screen and (max-width: 400px) {
    /* Container */
    #calendario-eventi {
        margin: 0 !important;
        margin-bottom: 12px !important;
    }
    
    /* Header minimale */
    #calendario-eventi > div:first-child {
        padding: 8px 5px !important;
    }
    
    /* Selettori mini */
    #calendario-select-mese,
    #calendario-select-anno {
        padding: 4px 6px !important;
        font-size: 0.65rem !important;
        min-width: 75px !important;
    }
    
    /* Bottone mini */
    #calendario-eventi > div:first-child > div:first-child > button {
        padding: 4px 8px !important;
        font-size: 0.65rem !important;
    }
    
    /* Titolo mini */
    #calendario-titolo {
        font-size: 0.9rem !important;
    }
    
    /* Contatore mini */
    #calendario-eventi > div:first-child > div:nth-child(2) > div > p {
        font-size: 0.6rem !important;
    }
    
    /* Container griglia ultra compatto */
    #calendario-eventi > div:nth-child(2) {
        padding: 6px 3px !important;
    }
    
    /* Giorni mini - 30px (limite minimo touch iOS) */
    #calendario-eventi > div:nth-child(2) > div:first-child > div[onclick] {
        font-size: 0.6rem !important;
        min-height: 30px !important;
        max-height: 30px !important;
    }
}

/* Desktop Large */
@media (min-width: 1400px) {
    #calendario-eventi [style*="aspect-ratio: 1"] {
        font-size: 1rem !important;
    }
    
    #calendario-eventi [style*="width: 5px"] {
        width: 6px !important;
        height: 6px !important;
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

<!-- ============================================================================ -->
<!-- MOBILE CALENDAR v4.1.1 GRID FIX - CACHE BUSTER: <?php echo time(); ?> -->
<!-- ============================================================================ -->
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<script>
/**
 * NUCLEAR OPTION - v4.1 RESPONSIVE
 * Rimuove FORZATAMENTE inline styles e applica dimensioni RESPONSIVE
 * Ora funziona perfettamente sia in verticale che in orizzontale!
 */
(function() {
    'use strict';
    
    const isMobile = window.innerWidth <= 768;
    const isSmallMobile = window.innerWidth <= 576;
    
    console.log('[Calendario v4.1] Width:', window.innerWidth, 'Mobile:', isMobile);
    
    if (!isMobile) return; // Desktop = no modifiche
    
    // Aspetta che DOM sia pronto
    function initMobileCalendar() {
        const calendario = document.getElementById('calendario-eventi');
        if (!calendario) {
            console.warn('[Calendario v4.1] Elemento non trovato');
            return;
        }
        
        console.log('[Calendario v4.1] 🔥 NUCLEAR MODE ATTIVO - Dimensioni RESPONSIVE...');
        
        // Dimensioni basate su larghezza
        const cellSize = isSmallMobile ? '36px' : '40px';
        const fontSize = isSmallMobile ? '0.7rem' : '0.75rem';
        const gap = isSmallMobile ? '2px' : '3px';
        
        // 1. HEADER - Rimuovi padding eccessivo
        const header = calendario.children[0];
        if (header) {
            header.style.padding = '15px 10px';
            
            // Selettori mese/anno
            const selettori = header.children[0];
            if (selettori) {
                selettori.style.gap = '8px';
                selettori.style.marginBottom = '10px';
                
                // Label "Vai a:"
                const label = selettori.querySelector('label');
                if (label) label.style.display = 'none';
            }
        }
        
        // 2. SELECT - Ridimensiona
        const selects = calendario.querySelectorAll('select');
        selects.forEach(function(select) {
            select.style.padding = '8px 10px';
            select.style.fontSize = '0.8rem';
            select.style.minWidth = isSmallMobile ? '100px' : '120px';
        });
        
        // 3. BOTTONI - Riduci
        const buttons = calendario.querySelectorAll('button');
        buttons.forEach(function(btn) {
            if (btn.textContent.includes('Oggi')) {
                btn.style.padding = '8px 12px';
                btn.style.fontSize = '0.8rem';
            } else {
                btn.style.padding = '8px 10px';
                btn.style.fontSize = '1rem';
            }
        });
        
        // 4. TITOLO - Riduci
        const titolo = document.getElementById('calendario-titolo');
        if (titolo) {
            titolo.style.fontSize = isSmallMobile ? '1.1rem' : '1.3rem';
        }
        
        // 5. CONTAINER GRIGLIA - Riduci padding
        const container = calendario.children[1];
        if (container) {
            container.style.padding = isSmallMobile ? '10px' : '15px';
        }
        
        // 6. GRIGLIA - Riduci gap + FORZA GRID LAYOUT
        const griglia = container ? container.children[0] : null;
        if (griglia) {
            griglia.style.display = 'grid';
            griglia.style.gridTemplateColumns = 'repeat(7, 1fr)';
            griglia.style.gap = gap;
            griglia.style.marginBottom = '15px';
            console.log('[Calendario v4.1] Grid layout forzato: 7 colonne');
        }
        
        // 7. INTESTAZIONI GIORNI - Riduci
        const intestazioni = griglia ? Array.from(griglia.children).slice(0, 7) : [];
        intestazioni.forEach(function(int) {
            int.style.fontSize = '0.65rem';
            int.style.padding = '5px 0';
        });
        
        // 8. CELLE GIORNI - RESPONSIVE PER VERTICALE/ORIZZONTALE
        const celle = calendario.querySelectorAll('div[onclick]');
        console.log('[Calendario v4.1] Trovate', celle.length, 'celle giorni');
        
        // Calcola dimensione ottimale basata su larghezza disponibile
        const containerWidth = griglia ? griglia.offsetWidth : window.innerWidth - 40;
        const gapTotal = parseFloat(gap) * 6; // 6 gap tra 7 colonne
        const availableWidth = containerWidth - gapTotal;
        const calculatedSize = Math.floor(availableWidth / 7);
        
        // Usa dimensione calcolata ma con limiti
        const minSize = 30; // Minimo touch-friendly
        const maxSize = 50; // Massimo per estetica
        const finalSize = Math.min(Math.max(calculatedSize, minSize), maxSize) + 'px';
        
        console.log('[Calendario v4.1] Larghezza container:', containerWidth + 'px');
        console.log('[Calendario v4.1] Dimensione celle calcolata:', finalSize);
        
        celle.forEach(function(cella) {
            // RIMUOVI aspect-ratio
            cella.style.aspectRatio = 'auto';
            
            // FORZA dimensioni RESPONSIVE
            cella.style.width = finalSize;
            cella.style.height = finalSize;
            cella.style.minWidth = finalSize;
            cella.style.maxWidth = finalSize;
            cella.style.minHeight = finalSize;
            cella.style.maxHeight = finalSize;
            cella.style.fontSize = fontSize;
            cella.style.padding = '0';
            cella.style.display = 'flex';
            cella.style.flexDirection = 'column';
            cella.style.alignItems = 'center';
            cella.style.justifyContent = 'center';
            cella.style.flexShrink = '0'; // Non rimpicciolire
            
            // Pallini eventi
            const pallini = cella.querySelector('div[style*="display: flex"]');
            if (pallini) {
                pallini.style.marginTop = '2px';
                pallini.style.gap = '2px';
                
                const dots = pallini.querySelectorAll('div');
                dots.forEach(function(dot) {
                    dot.style.width = '3px';
                    dot.style.height = '3px';
                });
            }
        });
        
        // Verifica risultato
        setTimeout(function() {
            if (celle.length > 0) {
                const h = celle[0].offsetHeight;
                const w = celle[0].offsetWidth;
                console.log('[Calendario v4.1] ✅ Celle ridimensionate:', w + 'x' + h + 'px');
                
                if (h > 50) {
                    console.error('[Calendario v4.1] ❌ FALLITO! Altezza ancora:', h + 'px');
                } else {
                    console.log('[Calendario v4.1] 🎉 SUCCESS! Calendario compatto RESPONSIVE attivo!');
                }
            }
        }, 100);
    }
    
    // Esegui quando DOM è pronto
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initMobileCalendar);
    } else {
        initMobileCalendar();
    }
    
    // Riesegui su resize (rotate)
    let resizeTimer;
    window.addEventListener('resize', function() {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(function() {
            if (window.innerWidth <= 768) {
                initMobileCalendar();
            }
        }, 250);
    });
})();
</script>
