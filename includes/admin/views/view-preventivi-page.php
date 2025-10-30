<?php
/**
 * Pagina View Preventivi - 747 Disco CRM
 * Versione 11.8.3 - Con Filtri e Modal Modifica
 * 
 * @package    Disco747_CRM
 * @subpackage Admin/Views
 * @version    11.8.3-FILTERS-EDIT
 */

if (!defined('ABSPATH')) {
    exit('Accesso diretto non consentito');
}

global $wpdb;
$table_name = $wpdb->prefix . 'disco747_preventivi';

// Parametri filtri
$filters = array(
    'search' => sanitize_text_field($_GET['search'] ?? ''),
    'stato' => sanitize_key($_GET['stato'] ?? ''),
    'menu' => sanitize_text_field($_GET['menu'] ?? ''),
    'anno' => intval($_GET['anno'] ?? 0),
    'mese' => intval($_GET['mese'] ?? 0),
    'order_by' => sanitize_key($_GET['order_by'] ?? 'created_at'),
    'order' => sanitize_key($_GET['order'] ?? 'DESC')
);

// Paginazione
$page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
$per_page = 50;
$offset = ($page - 1) * $per_page;

// Query base
$where = array('1=1');
$where_values = array();

// Applica filtri
if (!empty($filters['search'])) {
    $where[] = "(nome_cliente LIKE %s OR email LIKE %s OR telefono LIKE %s OR tipo_evento LIKE %s)";
    $search_term = '%' . $wpdb->esc_like($filters['search']) . '%';
    $where_values[] = $search_term;
    $where_values[] = $search_term;
    $where_values[] = $search_term;
    $where_values[] = $search_term;
}

if (!empty($filters['stato'])) {
    $where[] = "stato = %s";
    $where_values[] = $filters['stato'];
}

if (!empty($filters['menu'])) {
    $where[] = "tipo_menu LIKE %s";
    $where_values[] = '%' . $wpdb->esc_like($filters['menu']) . '%';
}

if ($filters['anno'] > 0) {
    $where[] = "YEAR(data_evento) = %d";
    $where_values[] = $filters['anno'];
}

if ($filters['mese'] > 0) {
    $where[] = "MONTH(data_evento) = %d";
    $where_values[] = $filters['mese'];
}

$where_clause = implode(' AND ', $where);

// Conta totali
if (!empty($where_values)) {
    $count_query = $wpdb->prepare("SELECT COUNT(*) FROM {$table_name} WHERE {$where_clause}", $where_values);
} else {
    $count_query = "SELECT COUNT(*) FROM {$table_name} WHERE {$where_clause}";
}
$total_preventivi = $wpdb->get_var($count_query);
$total_pages = ceil($total_preventivi / $per_page);

// Query preventivi
$order_clause = sprintf('ORDER BY %s %s', 
    sanitize_key($filters['order_by']), 
    $filters['order'] === 'ASC' ? 'ASC' : 'DESC'
);

if (!empty($where_values)) {
    $query = $wpdb->prepare(
        "SELECT * FROM {$table_name} WHERE {$where_clause} {$order_clause} LIMIT %d OFFSET %d",
        array_merge($where_values, array($per_page, $offset))
    );
} else {
    $query = $wpdb->prepare(
        "SELECT * FROM {$table_name} WHERE {$where_clause} {$order_clause} LIMIT %d OFFSET %d",
        $per_page,
        $offset
    );
}

$preventivi = $wpdb->get_results($query);

// Statistiche
$stats = array(
    'totale' => $wpdb->get_var("SELECT COUNT(*) FROM {$table_name}"),
    'attivi' => $wpdb->get_var("SELECT COUNT(*) FROM {$table_name} WHERE stato = 'attivo'"),
    'confermati' => $wpdb->get_var("SELECT COUNT(*) FROM {$table_name} WHERE stato = 'confermato'"),
    'annullati' => $wpdb->get_var("SELECT COUNT(*) FROM {$table_name} WHERE stato = 'annullato'")
);

?>

<div class="wrap disco747-wrap">
    
    <!-- Header -->
    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 30px;">
        <h1 style="margin: 0;">📊 Database Preventivi</h1>
        <div style="display: flex; gap: 10px;">
            <a href="<?php echo admin_url('admin.php?page=disco747-scan-excel'); ?>" class="button">
                🔄 Excel Scan
            </a>
            <button type="button" id="export-csv-btn" class="button button-primary">
                📥 Export CSV
            </button>
        </div>
    </div>

    <!-- Statistiche -->
    <div class="disco747-card" style="margin-bottom: 30px;">
        <div class="disco747-card-content">
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px;">
                <div class="stat-box" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                    <div class="stat-label">📊 Totale</div>
                    <div class="stat-value"><?php echo number_format($stats['totale']); ?></div>
                </div>
                <div class="stat-box" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: white;">
                    <div class="stat-label">🔵 Attivi</div>
                    <div class="stat-value"><?php echo number_format($stats['attivi']); ?></div>
                </div>
                <div class="stat-box" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); color: white;">
                    <div class="stat-label">✅ Confermati</div>
                    <div class="stat-value"><?php echo number_format($stats['confermati']); ?></div>
                </div>
                <div class="stat-box" style="background: linear-gradient(135deg, #fa709a 0%, #fee140 100%); color: white;">
                    <div class="stat-label">❌ Annullati</div>
                    <div class="stat-value"><?php echo number_format($stats['annullati']); ?></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filtri -->
    <div class="disco747-card" style="margin-bottom: 30px;">
        <div class="disco747-card-header">
            🔍 Filtri di Ricerca
            <?php if (array_filter($filters)): ?>
                <a href="<?php echo admin_url('admin.php?page=disco747-view-preventivi'); ?>" 
                   style="float: right; font-size: 13px; color: #2271b1; text-decoration: none;">
                    ✖ Cancella Filtri
                </a>
            <?php endif; ?>
        </div>
        <div class="disco747-card-content">
            <form method="get" action="" id="filters-form">
                <input type="hidden" name="page" value="disco747-view-preventivi">
                
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                    <!-- Ricerca -->
                    <div>
                        <label style="display: block; margin-bottom: 5px; font-weight: 600;">🔍 Cerca</label>
                        <input type="text" name="search" 
                               value="<?php echo esc_attr($filters['search']); ?>" 
                               placeholder="Nome, email, telefono..."
                               style="width: 100%; padding: 8px;">
                    </div>

                    <!-- Stato -->
                    <div>
                        <label style="display: block; margin-bottom: 5px; font-weight: 600;">📌 Stato</label>
                        <select name="stato" style="width: 100%; padding: 8px;">
                            <option value="">Tutti gli stati</option>
                            <option value="attivo" <?php selected($filters['stato'], 'attivo'); ?>>Attivo</option>
                            <option value="confermato" <?php selected($filters['stato'], 'confermato'); ?>>Confermato</option>
                            <option value="annullato" <?php selected($filters['stato'], 'annullato'); ?>>Annullato</option>
                            <option value="bozza" <?php selected($filters['stato'], 'bozza'); ?>>Bozza</option>
                        </select>
                    </div>

                    <!-- Menu -->
                    <div>
                        <label style="display: block; margin-bottom: 5px; font-weight: 600;">🍽️ Menu</label>
                        <select name="menu" style="width: 100%; padding: 8px;">
                            <option value="">Tutti i menu</option>
                            <option value="Menu 7" <?php selected($filters['menu'], 'Menu 7'); ?>>Menu 7</option>
                            <option value="Menu 74" <?php selected($filters['menu'], 'Menu 74'); ?>>Menu 74</option>
                            <option value="Menu 747" <?php selected($filters['menu'], 'Menu 747'); ?>>Menu 747</option>
                        </select>
                    </div>

                    <!-- Anno -->
                    <div>
                        <label style="display: block; margin-bottom: 5px; font-weight: 600;">📅 Anno</label>
                        <select name="anno" style="width: 100%; padding: 8px;">
                            <option value="">Tutti gli anni</option>
                            <?php
                            $current_year = date('Y');
                            for ($y = $current_year; $y >= ($current_year - 3); $y--):
                            ?>
                                <option value="<?php echo $y; ?>" <?php selected($filters['anno'], $y); ?>>
                                    <?php echo $y; ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                    </div>

                    <!-- Mese -->
                    <div>
                        <label style="display: block; margin-bottom: 5px; font-weight: 600;">📆 Mese</label>
                        <select name="mese" style="width: 100%; padding: 8px;">
                            <option value="">Tutti i mesi</option>
                            <?php
                            $mesi = array(
                                1 => 'Gennaio', 2 => 'Febbraio', 3 => 'Marzo', 4 => 'Aprile',
                                5 => 'Maggio', 6 => 'Giugno', 7 => 'Luglio', 8 => 'Agosto',
                                9 => 'Settembre', 10 => 'Ottobre', 11 => 'Novembre', 12 => 'Dicembre'
                            );
                            foreach ($mesi as $num => $nome):
                            ?>
                                <option value="<?php echo $num; ?>" <?php selected($filters['mese'], $num); ?>>
                                    <?php echo $nome; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Ordina per -->
                    <div>
                        <label style="display: block; margin-bottom: 5px; font-weight: 600;">🔢 Ordina per</label>
                        <select name="order_by" style="width: 100%; padding: 8px;">
                            <option value="created_at" <?php selected($filters['order_by'], 'created_at'); ?>>Data Creazione</option>
                            <option value="data_evento" <?php selected($filters['order_by'], 'data_evento'); ?>>Data Evento</option>
                            <option value="nome_cliente" <?php selected($filters['order_by'], 'nome_cliente'); ?>>Cliente</option>
                            <option value="importo_totale" <?php selected($filters['order_by'], 'importo_totale'); ?>>Importo</option>
                        </select>
                    </div>
                </div>

                <div style="margin-top: 15px;">
                    <button type="submit" class="button button-primary">🔍 Applica Filtri</button>
                    <a href="<?php echo admin_url('admin.php?page=disco747-view-preventivi'); ?>" 
                       class="button">Ripristina</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Tabella Preventivi -->
    <div class="disco747-card">
        <div class="disco747-card-header">
            📋 Preventivi (<?php echo number_format($total_preventivi); ?> risultati)
        </div>
        <div class="disco747-card-content" style="padding: 0;">
            
            <?php if (empty($preventivi)): ?>
                <div style="padding: 40px; text-align: center; color: #666;">
                    <div style="font-size: 48px; margin-bottom: 15px;">📭</div>
                    <h3 style="margin: 0 0 10px 0;">Nessun preventivo trovato</h3>
                    <p style="margin: 0;">Prova a modificare i filtri o esegui un Batch Scan</p>
                </div>
            <?php else: ?>
                
                <div style="overflow-x: auto;">
                    <table class="wp-list-table widefat fixed striped" style="margin: 0;">
                        <thead>
                            <tr>
                                <th style="width: 60px;">ID</th>
                                <th style="width: 80px;">Prev. ID</th>
                                <th style="width: 100px;">Data Evento</th>
                                <th>Cliente</th>
                                <th>Tipo Evento</th>
                                <th style="width: 100px;">Menu</th>
                                <th style="width: 70px;">Invitati</th>
                                <th style="width: 100px;">Importo</th>
                                <th style="width: 90px;">Acconto</th>
                                <th style="width: 80px;">Stato</th>
                                <th style="width: 180px;">Azioni</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($preventivi as $prev): ?>
                                <tr data-preventivo-id="<?php echo $prev->id; ?>">
                                    <td><strong>#<?php echo $prev->id; ?></strong></td>
                                    <td>
                                        <span style="background: #FFD700; color: #000; padding: 2px 8px; border-radius: 4px; font-weight: bold; font-size: 11px;">
                                            <?php echo $prev->preventivo_id ?: 'N/A'; ?>
                                        </span>
                                    </td>
                                    <td><?php echo $prev->data_evento ? date('d/m/Y', strtotime($prev->data_evento)) : 'N/A'; ?></td>
                                    <td>
                                        <strong><?php echo esc_html($prev->nome_cliente ?: 'N/A'); ?></strong>
                                        <?php if ($prev->telefono): ?>
                                            <br><small style="color: #666;">📞 <?php echo esc_html($prev->telefono); ?></small>
                                        <?php endif; ?>
                                        <?php if ($prev->email): ?>
                                            <br><small style="color: #666;">✉️ <?php echo esc_html($prev->email); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo esc_html($prev->tipo_evento ?: 'N/A'); ?></td>
                                    <td><?php echo esc_html($prev->tipo_menu ?: 'N/A'); ?></td>
                                    <td style="text-align: center;"><?php echo intval($prev->numero_invitati); ?></td>
                                    <td style="text-align: right;">
                                        <strong>€ <?php echo number_format(floatval($prev->importo_totale), 2, ',', '.'); ?></strong>
                                    </td>
                                    <td style="text-align: right;">
                                        <?php if (floatval($prev->acconto) > 0): ?>
                                            <span style="color: #2ea044;">€ <?php echo number_format(floatval($prev->acconto), 2, ',', '.'); ?></span>
                                        <?php else: ?>
                                            <span style="color: #999;">€ 0,00</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php
                                        $stato = strtolower($prev->stato);
                                        $badge_colors = array(
                                            'confermato' => '#2ea044',
                                            'attivo' => '#0969da',
                                            'annullato' => '#cf222e',
                                            'bozza' => '#999'
                                        );
                                        $badge_color = $badge_colors[$stato] ?? '#999';
                                        ?>
                                        <span style="background: <?php echo $badge_color; ?>; color: #fff; padding: 3px 8px; border-radius: 4px; font-size: 11px; font-weight: bold;">
                                            <?php echo esc_html(strtoupper($prev->stato ?: 'N/A')); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div style="display: flex; gap: 5px; flex-wrap: wrap;">
                                            <button type="button" 
                                                    class="button button-small btn-edit-preventivo" 
                                                    data-id="<?php echo $prev->id; ?>"
                                                    title="Modifica preventivo">
                                                ✏️ Modifica
                                            </button>
                                            <?php if ($prev->googledrive_file_id): ?>
                                                <a href="https://drive.google.com/file/d/<?php echo esc_attr($prev->googledrive_file_id); ?>/view" 
                                                   target="_blank" 
                                                   class="button button-small" 
                                                   title="Apri su Google Drive"
                                                   style="font-size: 11px;">
                                                    📁 Drive
                                                </a>
                                            <?php endif; ?>
                                            <button type="button" 
                                                    class="button button-small btn-delete-preventivo" 
                                                    data-id="<?php echo $prev->id; ?>"
                                                    title="Elimina preventivo"
                                                    style="color: #d63638;">
                                                ❌
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Paginazione -->
                <?php if ($total_pages > 1): ?>
                    <div style="padding: 20px; border-top: 1px solid #e9ecef; background: #f8f9fa;">
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <div>
                                Pagina <?php echo $page; ?> di <?php echo $total_pages; ?> 
                                (<?php echo number_format($total_preventivi); ?> preventivi totali)
                            </div>
                            <div style="display: flex; gap: 10px;">
                                <?php if ($page > 1): ?>
                                    <a href="<?php echo add_query_arg('paged', $page - 1); ?>" class="button">
                                        ← Precedente
                                    </a>
                                <?php endif; ?>
                                
                                <?php if ($page < $total_pages): ?>
                                    <a href="<?php echo add_query_arg('paged', $page + 1); ?>" class="button button-primary">
                                        Successiva →
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>

<!-- Inline script: redirect Modifica to form-preventivo in edit mode -->
<script type="text/javascript">
  (function($){
    $(document).on('click','.btn-edit-preventivo',function(){
      const id = $(this).data('id');
      if(!id) return;
      // admin_url + page=disco747-crm route esistente per edit
      const url = ajaxurl.replace('admin-ajax.php','admin.php')+
                 '?page=disco747-crm&action=new_preventivo&edit_id='+id;
      window.location.href = url;
    });
  })(jQuery);
</script>
                    </div>
                <?php endif; ?>

            <?php endif; ?>
        </div>
    </div>

</div>

<!-- Modal Modifica Preventivo -->
<div id="modal-edit-preventivo" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); z-index: 999999; overflow-y: auto;">
    <div style="max-width: 900px; margin: 50px auto; background: #fff; border-radius: 8px; padding: 30px; position: relative;">
        <button type="button" id="close-modal" style="position: absolute; top: 15px; right: 15px; background: none; border: none; font-size: 24px; cursor: pointer; color: #666;">✖</button>
        
        <h2 style="margin-top: 0;">✏️ Modifica Preventivo</h2>
        
        <form id="form-edit-preventivo" style="margin-top: 30px;">
            <input type="hidden" name="preventivo_id" id="edit-preventivo-id">
            <input type="hidden" name="action" value="disco747_save_preventivo">
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                <!-- Colonna 1 -->
                <div>
                    <label style="display: block; margin-bottom: 5px; font-weight: 600;">Nome Cliente *</label>
                    <input type="text" name="nome_cliente" id="edit-nome-cliente" required style="width: 100%; padding: 8px;">
                </div>

                <div>
                    <label style="display: block; margin-bottom: 5px; font-weight: 600;">Telefono</label>
                    <input type="tel" name="telefono" id="edit-telefono" style="width: 100%; padding: 8px;">
                </div>

                <div>
                    <label style="display: block; margin-bottom: 5px; font-weight: 600;">Email</label>
                    <input type="email" name="email" id="edit-email" style="width: 100%; padding: 8px;">
                </div>

                <div>
                    <label style="display: block; margin-bottom: 5px; font-weight: 600;">Tipo Evento</label>
                    <input type="text" name="tipo_evento" id="edit-tipo-evento" style="width: 100%; padding: 8px;">
                </div>

                <div>
                    <label style="display: block; margin-bottom: 5px; font-weight: 600;">Data Evento *</label>
                    <input type="date" name="data_evento" id="edit-data-evento" required style="width: 100%; padding: 8px;">
                </div>

                <div>
                    <label style="display: block; margin-bottom: 5px; font-weight: 600;">Orario</label>
                    <input type="text" name="orario_evento" id="edit-orario-evento" style="width: 100%; padding: 8px;" placeholder="es: 19:00 - 23:00">
                </div>

                <div>
                    <label style="display: block; margin-bottom: 5px; font-weight: 600;">Menu</label>
                    <select name="tipo_menu" id="edit-tipo-menu" style="width: 100%; padding: 8px;">
                        <option value="Menu 7">Menu 7</option>
                        <option value="Menu 74">Menu 74</option>
                        <option value="Menu 747">Menu 747</option>
                    </select>
                </div>

                <div>
                    <label style="display: block; margin-bottom: 5px; font-weight: 600;">Numero Invitati</label>
                    <input type="number" name="numero_invitati" id="edit-numero-invitati" min="0" style="width: 100%; padding: 8px;">
                </div>

                <div>
                    <label style="display: block; margin-bottom: 5px; font-weight: 600;">Importo Totale €</label>
                    <input type="number" name="importo_totale" id="edit-importo-totale" step="0.01" min="0" style="width: 100%; padding: 8px;">
                </div>

                <div>
                    <label style="display: block; margin-bottom: 5px; font-weight: 600;">Acconto €</label>
                    <input type="number" name="acconto" id="edit-acconto" step="0.01" min="0" style="width: 100%; padding: 8px;">
                </div>

                <div>
                    <label style="display: block; margin-bottom: 5px; font-weight: 600;">Stato</label>
                    <select name="stato" id="edit-stato" style="width: 100%; padding: 8px;">
                        <option value="attivo">Attivo</option>
                        <option value="confermato">Confermato</option>
                        <option value="annullato">Annullato</option>
                        <option value="bozza">Bozza</option>
                    </select>
                </div>
            </div>

            <!-- Omaggi -->
            <div style="margin-top: 20px; padding: 15px; background: #f8f9fa; border-radius: 5px;">
                <h4 style="margin: 0 0 15px 0;">🎁 Omaggi</h4>
                <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 15px;">
                    <div>
                        <label style="display: block; margin-bottom: 5px;">Omaggio 1</label>
                        <input type="text" name="omaggio1" id="edit-omaggio1" style="width: 100%; padding: 8px;">
                    </div>
                    <div>
                        <label style="display: block; margin-bottom: 5px;">Omaggio 2</label>
                        <input type="text" name="omaggio2" id="edit-omaggio2" style="width: 100%; padding: 8px;">
                    </div>
                    <div>
                        <label style="display: block; margin-bottom: 5px;">Omaggio 3</label>
                        <input type="text" name="omaggio3" id="edit-omaggio3" style="width: 100%; padding: 8px;">
                    </div>
                </div>
            </div>

            <!-- Extra -->
            <div style="margin-top: 20px; padding: 15px; background: #fff3cd; border-radius: 5px;">
                <h4 style="margin: 0 0 15px 0;">💰 Extra a Pagamento</h4>
                <div style="display: grid; gap: 15px;">
                    <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 10px;">
                        <input type="text" name="extra1" id="edit-extra1" placeholder="Descrizione extra 1" style="padding: 8px;">
                        <input type="number" name="extra1_importo" id="edit-extra1-importo" placeholder="€" step="0.01" min="0" style="padding: 8px;">
                    </div>
                    <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 10px;">
                        <input type="text" name="extra2" id="edit-extra2" placeholder="Descrizione extra 2" style="padding: 8px;">
                        <input type="number" name="extra2_importo" id="edit-extra2-importo" placeholder="€" step="0.01" min="0" style="padding: 8px;">
                    </div>
                    <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 10px;">
                        <input type="text" name="extra3" id="edit-extra3" placeholder="Descrizione extra 3" style="padding: 8px;">
                        <input type="number" name="extra3_importo" id="edit-extra3-importo" placeholder="€" step="0.01" min="0" style="padding: 8px;">
                    </div>
                </div>
            </div>

            <div style="margin-top: 30px; text-align: right;">
                <button type="button" id="cancel-edit" class="button" style="margin-right: 10px;">Annulla</button>
                <button type="submit" class="button button-primary">💾 Salva Modifiche</button>
            </div>
        </form>
    </div>
</div>

<style>
.disco747-wrap {
    padding: 20px;
}
.disco747-card {
    background: #fff;
    border: 1px solid #e9ecef;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    margin-bottom: 20px;
}
.disco747-card-header {
    padding: 20px
    border-bottom: 1px solid #e9ecef;
    font-size: 16px;
    font-weight: 600;
}
.disco747-card-content {
    padding: 20px;
}
.stat-box {
    text-align: center;
    padding: 20px;
    border-radius: 8px;
}
.stat-label {
    font-size: 13px;
    margin-bottom: 8px;
    opacity: 0.9;
}
.stat-value {
    font-size: 32px;
    font-weight: bold;
}
.btn-edit-preventivo {
    background: #0073aa;
    color: white;
    border: none;
    padding: 5px 10px;
    cursor: pointer;
    font-size: 11px;
    border-radius: 3px;
}
.btn-edit-preventivo:hover {
    background: #005a87;
}
.btn-delete-preventivo {
    background: #dc3232;
    color: white;
    border: none;
    padding: 5px 10px;
    cursor: pointer;
    font-size: 11px;
    border-radius: 3px;
}
.btn-delete-preventivo:hover {
    background: #a00;
}
</style>

<script>
jQuery(document).ready(function($) {
    console.log('✅ View Preventivi JS caricato');

    // ========================================================================
    // EXPORT CSV
    // ========================================================================
    $('#export-csv-btn').on('click', function() {
        console.log('📥 Export CSV richiesto');
        
        // Costruisci URL con parametri filtri
        var url = '<?php echo admin_url('admin-ajax.php'); ?>';
        var params = {
            action: 'disco747_export_preventivi_csv',
            nonce: '<?php echo wp_create_nonce('disco747_export_csv'); ?>',
            <?php if (!empty($filters['search'])): ?>search: '<?php echo esc_js($filters['search']); ?>',<?php endif; ?>
            <?php if (!empty($filters['stato'])): ?>stato: '<?php echo esc_js($filters['stato']); ?>',<?php endif; ?>
            <?php if (!empty($filters['menu'])): ?>menu: '<?php echo esc_js($filters['menu']); ?>',<?php endif; ?>
            <?php if ($filters['anno'] > 0): ?>anno: <?php echo $filters['anno']; ?>,<?php endif; ?>
            <?php if ($filters['mese'] > 0): ?>mese: <?php echo $filters['mese']; ?>,<?php endif; ?>
        };
        
        var queryString = $.param(params);
        window.location.href = url + '?' + queryString;
    });

    // ========================================================================
    // MODIFICA PREVENTIVO
    // ========================================================================
    $(document).on('click', '.btn-edit-preventivo', function() {
        var preventivoId = $(this).data('id');
        console.log('✏️ Modifica preventivo ID:', preventivoId);
        
        loadPreventivoData(preventivoId);
    });

    function loadPreventivoData(id) {
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'disco747_get_preventivo',
                nonce: '<?php echo wp_create_nonce('disco747_get_preventivo'); ?>',
                preventivo_id: id
            },
            beforeSend: function() {
                console.log('⏳ Caricamento dati preventivo...');
            },
            success: function(response) {
                console.log('✅ Risposta server:', response);
                
                if (response.success && response.data) {
                    populateEditForm(response.data);
                    $('#modal-edit-preventivo').fadeIn(300);
                } else {
                    alert('❌ Errore: ' + (response.data || 'Preventivo non trovato'));
                }
            },
            error: function(xhr, status, error) {
                console.error('❌ Errore AJAX:', error);
                alert('❌ Errore di connessione al server');
            }
        });
    }

    function populateEditForm(data) {
        console.log('📝 Precompilo form con:', data);
        
        $('#edit-preventivo-id').val(data.id);
        $('#edit-nome-cliente').val(data.nome_cliente || '');
        $('#edit-telefono').val(data.telefono || '');
        $('#edit-email').val(data.email || '');
        $('#edit-tipo-evento').val(data.tipo_evento || '');
        $('#edit-data-evento').val(data.data_evento || '');
        $('#edit-orario-evento').val(data.orario_evento || '');
        $('#edit-tipo-menu').val(data.tipo_menu || 'Menu 7');
        $('#edit-numero-invitati').val(data.numero_invitati || 0);
        $('#edit-importo-totale').val(data.importo_totale || 0);
        $('#edit-acconto').val(data.acconto || 0);
        $('#edit-stato').val(data.stato || 'attivo');
        
        // Omaggi
        $('#edit-omaggio1').val(data.omaggio1 || '');
        $('#edit-omaggio2').val(data.omaggio2 || '');
        $('#edit-omaggio3').val(data.omaggio3 || '');
        
        // Extra
        $('#edit-extra1').val(data.extra1 || '');
        $('#edit-extra1-importo').val(data.extra1_importo || 0);
        $('#edit-extra2').val(data.extra2 || '');
        $('#edit-extra2-importo').val(data.extra2_importo || 0);
        $('#edit-extra3').val(data.extra3 || '');
        $('#edit-extra3-importo').val(data.extra3_importo || 0);
    }

    // ========================================================================
    // SUBMIT FORM MODIFICA
    // ========================================================================
    $('#form-edit-preventivo').on('submit', function(e) {
        e.preventDefault();
        console.log('💾 Submit modifica preventivo');
        
        var formData = new FormData(this);
        formData.append('nonce', '<?php echo wp_create_nonce('disco747_save_preventivo'); ?>');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            beforeSend: function() {
                $('#form-edit-preventivo button[type="submit"]')
                    .prop('disabled', true)
                    .text('⏳ Salvataggio...');
            },
            success: function(response) {
                console.log('✅ Risposta salvataggio:', response);
                
                if (response.success) {
                    alert('✅ ' + (response.data.message || 'Preventivo aggiornato con successo!'));
                    $('#modal-edit-preventivo').fadeOut(300);
                    
                    // Ricarica pagina dopo 500ms
                    setTimeout(function() {
                        location.reload();
                    }, 500);
                } else {
                    alert('❌ Errore: ' + (response.data || 'Impossibile salvare il preventivo'));
                    $('#form-edit-preventivo button[type="submit"]')
                        .prop('disabled', false)
                        .text('💾 Salva Modifiche');
                }
            },
            error: function(xhr, status, error) {
                console.error('❌ Errore AJAX:', error);
                alert('❌ Errore di connessione al server');
                $('#form-edit-preventivo button[type="submit"]')
                    .prop('disabled', false)
                    .text('💾 Salva Modifiche');
            }
        });
    });

    // ========================================================================
    // CHIUDI MODAL
    // ========================================================================
    $('#close-modal, #cancel-edit').on('click', function() {
        $('#modal-edit-preventivo').fadeOut(300);
    });

    // Chiudi modal cliccando fuori
    $('#modal-edit-preventivo').on('click', function(e) {
        if ($(e.target).is('#modal-edit-preventivo')) {
            $(this).fadeOut(300);
        }
    });

    // ESC per chiudere modal
    $(document).on('keydown', function(e) {
        if (e.key === 'Escape') {
            $('#modal-edit-preventivo').fadeOut(300);
        }
    });

    // ========================================================================
    // ELIMINA PREVENTIVO
    // ========================================================================
    $(document).on('click', '.btn-delete-preventivo', function() {
        var preventivoId = $(this).data('id');
        var $row = $(this).closest('tr');
        var cliente = $row.find('td:nth-child(4) strong').text();
        
        if (!confirm('⚠️ Sei sicuro di voler eliminare il preventivo di ' + cliente + '?\n\nQuesta azione è irreversibile!')) {
            return;
        }
        
        console.log('🗑️ Eliminazione preventivo ID:', preventivoId);
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'disco747_delete_preventivo',
                nonce: '<?php echo wp_create_nonce('disco747_delete_preventivo'); ?>',
                preventivo_id: preventivoId
            },
            beforeSend: function() {
                $row.css('opacity', '0.5');
            },
            success: function(response) {
                console.log('✅ Risposta eliminazione:', response);
                
                if (response.success) {
                    $row.fadeOut(400, function() {
                        $(this).remove();
                    });
                    alert('✅ Preventivo eliminato con successo');
                } else {
                    alert('❌ Errore: ' + (response.data || 'Impossibile eliminare il preventivo'));
                    $row.css('opacity', '1');
                }
            },
            error: function(xhr, status, error) {
                console.error('❌ Errore AJAX:', error);
                alert('❌ Errore di connessione al server');
                $row.css('opacity', '1');
            }
        });
    });

    console.log('✅ Tutti gli handler JS registrati');
});
</script>