<?php
/**
 * Pagina Excel Scan - Interfaccia Ottimizzata Mobile-First
 * 
 * @package    Disco747_CRM
 * @subpackage Admin/Views
 * @since      11.8.9-RESET-AND-SCAN
 */

if (!defined('ABSPATH')) {
    exit;
}

$is_drive_configured = isset($is_googledrive_configured) && $is_googledrive_configured;
?>

<div class="wrap disco747-excel-scan">
    <div class="disco747-page-header">
        <h1>
            <span class="dashicons dashicons-media-spreadsheet"></span>
            Scansione File Excel
        </h1>
        <p class="page-subtitle">Importa automaticamente i preventivi da Google Drive</p>
    </div>

    <?php if (!$is_drive_configured): ?>
        <div class="disco747-notice disco747-notice-warning">
            <div class="notice-icon">⚠️</div>
            <div class="notice-content">
                <strong>Configurazione Google Drive Richiesta</strong>
                <p>Per utilizzare la scansione automatica, configura prima l'integrazione con Google Drive.</p>
                <a href="<?php echo admin_url('admin.php?page=disco747-settings'); ?>" class="button button-primary">
                    Vai alle Impostazioni
                </a>
            </div>
        </div>
    <?php else: ?>

        <!-- ✅ FIX: Inizializza variabili JavaScript per excel-scan.js -->
        <script>
        window.disco747ExcelScanData = window.disco747ExcelScanData || {};
        window.disco747ExcelScanData.gdriveAvailable = true;
        window.disco747ExcelScanData.ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';
        window.disco747ExcelScanData.nonce = '<?php echo wp_create_nonce('disco747_batch_scan'); ?>';
        console.log('[Excel-Scan-Fix] ✅ Variabili JavaScript inizializzate:', window.disco747ExcelScanData);
        </script>

        <!-- Box Scansione -->
        <div class="disco747-box">
            <div class="box-header">
                <h2>🚀 Avvia Scansione</h2>
            </div>

            <div class="box-body">
                <div class="scan-form">
                    <div class="form-row">
                        <div class="form-group">
                            <label>📅 Anno</label>
                            <select id="scan-year">
                                <option value="2025" selected>2025</option>
                                <option value="2024">2024</option>
                                <option value="2026">2026</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>📆 Mese (opzionale)</label>
                            <select id="scan-month">
                                <option value="">Tutti i mesi</option>
                                <option value="GENNAIO">Gennaio</option>
                                <option value="FEBBRAIO">Febbraio</option>
                                <option value="MARZO">Marzo</option>
                                <option value="APRILE">Aprile</option>
                                <option value="MAGGIO">Maggio</option>
                                <option value="GIUGNO">Giugno</option>
                                <option value="LUGLIO">Luglio</option>
                                <option value="AGOSTO">Agosto</option>
                                <option value="SETTEMBRE">Settembre</option>
                                <option value="OTTOBRE">Ottobre</option>
                                <option value="NOVEMBRE">Novembre</option>
                                <option value="DICEMBRE">Dicembre</option>
                            </select>
                        </div>
                    </div>

                    <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                        <button id="start-scan-btn" class="btn-scan" style="flex: 1;">
                            <span class="dashicons dashicons-update"></span>
                            Analizza Ora
                        </button>
                        
                        <button id="reset-scan-btn" class="btn-reset" style="flex: 0 0 auto;">
                            <span class="dashicons dashicons-trash"></span>
                            Svuota e Rianalizza
                        </button>
                    </div>
                </div>

                <!-- Progress Bar -->
                <div id="progress-section" style="display: none;">
                    <div class="progress-bar-container">
                        <div class="progress-bar-fill" id="progress-bar-fill">
                            <span id="progress-percent">0%</span>
                        </div>
                    </div>
                    <p class="progress-status" id="progress-status">Inizializzazione...</p>
                </div>

                <!-- Risultati -->
                <div id="results-section" style="display: none;">
                    <div class="results-cards">
                        <div class="stat-card card-blue">
                            <div class="stat-icon">📁</div>
                            <div class="stat-number" id="stat-total">0</div>
                            <div class="stat-label">Trovati</div>
                        </div>

                        <div class="stat-card card-green">
                            <div class="stat-icon">✅</div>
                            <div class="stat-number" id="stat-processed">0</div>
                            <div class="stat-label">Processati</div>
                        </div>

                        <div class="stat-card card-yellow">
                            <div class="stat-icon">🆕</div>
                            <div class="stat-number" id="stat-new">0</div>
                            <div class="stat-label">Nuovi</div>
                        </div>

                        <div class="stat-card card-purple">
                            <div class="stat-icon">🔄</div>
                            <div class="stat-number" id="stat-updated">0</div>
                            <div class="stat-label">Aggiornati</div>
                        </div>

                        <div class="stat-card card-red" id="error-card" style="display: none;">
                            <div class="stat-icon">❌</div>
                            <div class="stat-number" id="stat-errors">0</div>
                            <div class="stat-label">Errori</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tabella File Processati -->
        <div id="new-files-box" class="disco747-box" style="display: none;">
            <div class="box-header" style="background: linear-gradient(135deg, #28a745 0%, #20c997 100%); border-bottom-color: #28a745;">
                <h2 style="color: white;">🆕 File Processati in Questa Scansione</h2>
            </div>
            <div class="box-body">
                <div class="scan-summary" style="background: #e8f8f5; padding: 15px; border-radius: 8px; margin-bottom: 20px; border-left: 4px solid #28a745;">
                    <strong style="color: #155724;">📊 Riepilogo Scansione:</strong>
                    <div style="margin-top: 10px; display: flex; gap: 30px; flex-wrap: wrap;">
                        <span>📁 Totale: <strong id="summary-total">0</strong></span>
                        <span style="color: #28a745;">🆕 Nuovi: <strong id="summary-new">0</strong></span>
                        <span style="color: #17a2b8;">🔄 Aggiornati: <strong id="summary-updated">0</strong></span>
                        <span style="color: #dc3545;">❌ Errori: <strong id="summary-errors">0</strong></span>
                    </div>
                </div>

                <div class="table-wrapper">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th style="width: 15%;">Data Evento</th>
                                <th style="width: 30%;">Tipo Evento</th>
                                <th style="width: 15%;">Menu</th>
                                <th style="width: 15%;">Stato</th>
                                <th class="mobile-hide" style="width: 25%;">Nome File</th>
                            </tr>
                        </thead>
                        <tbody id="new-files-table-body">
                            <tr>
                                <td colspan="5" style="text-align: center; padding: 30px; color: #999;">
                                    Nessun file processato. I risultati appariranno qui dopo la scansione.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Debug Panel -->
        <div class="debug-panel">
            <div class="debug-header" onclick="disco747ToggleDebug()">
                <h3>
                    <span id="debug-icon" class="dashicons dashicons-arrow-down-alt2"></span>
                    Debug & Informazioni Sistema
                </h3>
            </div>
            <div id="debug-body" class="debug-body" style="display: none;">
                <div class="debug-box">
                    <h4>📋 Log Scansione</h4>
                    <pre id="debug-log">Nessun log disponibile. Avvia una scansione.</pre>
                </div>

                <div class="debug-box">
                    <h4>ℹ️ Info Sistema</h4>
                    <table class="info-table">
                        <tr>
                            <td><strong>Google Drive:</strong></td>
                            <td><span class="badge badge-success">✅ Configurato</span></td>
                        </tr>
                        <tr>
                            <td><strong>Versione:</strong></td>
                            <td>11.8.9-RESET-AND-SCAN</td>
                        </tr>
                        <tr>
                            <td><strong>Database:</strong></td>
                            <td>wp_disco747_preventivi</td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>

    <?php endif; ?>
</div>

<style>
.disco747-excel-scan { max-width: 1200px; margin: 20px auto; padding: 0 15px; }
.disco747-page-header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; border-radius: 10px; text-align: center; margin-bottom: 25px; box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3); }
.disco747-page-header h1 { margin: 0; font-size: 28px; display: flex; align-items: center; justify-content: center; gap: 10px; }
.disco747-page-header .dashicons { font-size: 32px; width: 32px; height: 32px; }
.page-subtitle { margin: 10px 0 0; opacity: 0.95; font-size: 15px; }
.disco747-box { background: white; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.08); margin-bottom: 25px; }
.box-header { padding: 20px 25px; background: #f8f9fa; border-bottom: 2px solid #667eea; border-radius: 10px 10px 0 0; }
.box-header h2 { margin: 0; font-size: 20px; color: #2c3e50; }
.box-body { padding: 25px; }
.form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px; }
.form-group label { display: block; font-weight: 600; margin-bottom: 8px; color: #2c3e50; }
.form-group select { width: 100%; padding: 10px 12px; border: 2px solid #e0e6ed; border-radius: 6px; font-size: 14px; background: white; }
.form-group select:focus { outline: none; border-color: #667eea; }
.btn-scan { padding: 15px 24px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none; border-radius: 8px; font-size: 16px; font-weight: 600; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 8px; transition: all 0.3s; box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3); }
.btn-scan:hover { transform: translateY(-2px); box-shadow: 0 6px 16px rgba(102, 126, 234, 0.4); }
.btn-scan:disabled { opacity: 0.7; cursor: not-allowed; transform: none; }
.btn-reset { padding: 15px 24px; background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%); color: white; border: none; border-radius: 8px; font-size: 16px; font-weight: 600; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 8px; transition: all 0.3s; box-shadow: 0 4px 12px rgba(231, 76, 60, 0.3); white-space: nowrap; }
.btn-reset:hover { transform: translateY(-2px); box-shadow: 0 6px 16px rgba(231, 76, 60, 0.4); background: linear-gradient(135deg, #c0392b 0%, #a93226 100%); }
.btn-reset:disabled { opacity: 0.7; cursor: not-allowed; transform: none; }
#progress-section { margin-top: 25px; }
.progress-bar-container { width: 100%; height: 35px; background: #f0f2f5; border-radius: 18px; overflow: hidden; box-shadow: inset 0 2px 4px rgba(0,0,0,0.1); }
.progress-bar-fill { height: 100%; background: linear-gradient(90deg, #667eea 0%, #764ba2 100%); width: 0%; transition: width 0.4s; display: flex; align-items: center; justify-content: center; color: white; font-weight: bold; font-size: 14px; }
.progress-status { margin-top: 10px; text-align: center; color: #546e7a; font-size: 14px; }
#results-section { margin-top: 30px; }
.results-cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px; }
.stat-card { background: white; border: 2px solid; border-radius: 10px; padding: 20px; text-align: center; transition: transform 0.2s; }
.stat-card:hover { transform: translateY(-3px); }
.card-blue { border-color: #3498db; background: #e8f4f8; }
.card-green { border-color: #27ae60; background: #e8f8f5; }
.card-yellow { border-color: #f39c12; background: #fef5e7; }
.card-purple { border-color: #667eea; background: #f0f3ff; }
.card-red { border-color: #e74c3c; background: #fef0ef; }
.stat-icon { font-size: 36px; margin-bottom: 8px; }
.stat-number { font-size: 32px; font-weight: bold; color: #2c3e50; margin-bottom: 5px; }
.stat-label { font-size: 13px; color: #7f8c8d; font-weight: 600; text-transform: uppercase; }
.scan-summary { animation: slideIn 0.5s ease; }
@keyframes slideIn { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
.table-wrapper { overflow-x: auto; }
.data-table { width: 100%; border-collapse: collapse; }
.data-table thead { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; }
.data-table th { padding: 12px; text-align: left; font-weight: 600; font-size: 13px; }
.data-table tbody tr { border-bottom: 1px solid #e0e6ed; transition: background 0.2s; }
.data-table tbody tr:hover { background: #f8f9fa; }
.data-table td { padding: 12px; font-size: 14px; }
.badge { display: inline-block; padding: 4px 10px; border-radius: 12px; font-size: 12px; font-weight: 600; }
.badge-success { background: #d4edda; color: #155724; }
.badge-warning { background: #fff3cd; color: #856404; }
.badge-danger { background: #f8d7da; color: #721c24; }
.badge-info { background: #d1ecf1; color: #0c5460; }
.debug-panel { background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 10px; overflow: hidden; }
.debug-header { padding: 15px 20px; background: #e9ecef; cursor: pointer; user-select: none; }
.debug-header:hover { background: #dee2e6; }
.debug-header h3 { margin: 0; font-size: 16px; color: #2c3e50; display: flex; align-items: center; gap: 8px; }
.debug-body { padding: 20px; }
.debug-box { background: white; border: 1px solid #dee2e6; border-radius: 6px; padding: 15px; margin-bottom: 15px; }
.debug-box:last-child { margin-bottom: 0; }
.debug-box h4 { margin: 0 0 10px; font-size: 14px; color: #2c3e50; }
.debug-box pre { background: #2c3e50; color: #ecf0f1; padding: 15px; border-radius: 6px; max-height: 300px; overflow-y: auto; font-size: 12px; margin: 0; white-space: pre-wrap; }
.info-table { width: 100%; }
.info-table td { padding: 8px 0; font-size: 14px; }
.info-table td:first-child { width: 150px; }
.disco747-notice { display: flex; gap: 15px; padding: 20px; border-radius: 10px; margin-bottom: 20px; }
.disco747-notice-warning { background: #fff3cd; border: 2px solid #ffc107; }
.notice-icon { font-size: 40px; }
.notice-content strong { display: block; margin-bottom: 8px; color: #856404; }
.notice-content p { margin: 0 0 10px; color: #856404; }
@keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
.spin { animation: spin 1s linear infinite; }
@media (max-width: 768px) {
    .disco747-page-header h1 { font-size: 22px; }
    .form-row { grid-template-columns: 1fr; gap: 15px; }
    .results-cards { grid-template-columns: repeat(2, 1fr); }
    .mobile-hide { display: none; }
    .stat-icon { font-size: 28px; }
    .stat-number { font-size: 24px; }
    .scan-summary > div { flex-direction: column; gap: 10px !important; }
    .btn-reset { flex: 1 !important; }
}
@media (max-width: 480px) {
    .results-cards { grid-template-columns: 1fr; }
}
</style>

<script>
function disco747ToggleDebug() {
    const body = document.getElementById('debug-body');
    const icon = document.getElementById('debug-icon');
    if (body.style.display === 'none') {
        body.style.display = 'block';
        icon.classList.remove('dashicons-arrow-down-alt2');
        icon.classList.add('dashicons-arrow-up-alt2');
    } else {
        body.style.display = 'none';
        icon.classList.remove('dashicons-arrow-up-alt2');
        icon.classList.add('dashicons-arrow-down-alt2');
    }
}

jQuery(document).ready(function($) {
    
    // ========================================================================
    // ✅ SCAN CHUNKED - Ottimizzato per evitare 503
    // ========================================================================
    
    let isScanningChunked = false;
    
    $('#start-scan-btn').on('click', async function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        console.log('[Excel-Scan] 🚀 Click rilevato - AVVIO SCAN CHUNKED');
        
        if (isScanningChunked) {
            console.log('[Excel-Scan] ⚠️ Scansione già in corso - ignoro click');
            return false;
        }
        
        isScanningChunked = true;
        
        const year = $('#scan-year').val();
        const month = $('#scan-month').val();
        const btn = $(this);
        const resetBtn = $('#reset-scan-btn');
        
        btn.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> Scansione...');
        resetBtn.prop('disabled', true);
        
        $('#progress-section').show();
        $('#progress-bar-fill').css('width', '0%');
        $('#progress-percent').text('0%');
        $('#progress-status').text('🔄 Inizializzazione...');
        $('#results-section').hide();
        $('#new-files-box').hide();
        $('#debug-log').text('🚀 Avvio scansione CHUNKED...\n');
        
        let offset = 0;
        const limit = 10;
        let totalProcessed = 0;
        let totalSaved = 0;
        let totalErrors = 0;
        let grandTotal = 0;
        let hasMore = true;
        let batchNumber = 1;
        let allFiles = [];
        
        console.log(`[Chunked] Parametri: year=${year}, month=${month}, limit=${limit}`);
        $('#debug-log').append(`📊 Anno: ${year}, Mese: ${month || 'tutti'}\n📦 File per batch: ${limit}\n\n`);
        
        while (hasMore) {
            try {
                console.log(`[Chunked] 📦 Batch #${batchNumber}: offset=${offset}`);
                $('#progress-status').text(`Processando batch ${batchNumber}... (${totalProcessed}/${grandTotal || '?'} file)`);
                $('#debug-log').append(`\n📦 Batch #${batchNumber}...\n`);
                
                const response = await $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'batch_scan_excel_chunked',
                        offset: offset,
                        limit: limit,
                        year: year,
                        month: month,
                        nonce: '<?php echo wp_create_nonce('disco747_batch_scan'); ?>'
                    },
                    timeout: 90000
                });
                
                if (response.success && response.data) {
                    const data = response.data;
                    totalProcessed += data.processed || 0;
                    totalSaved += data.saved || 0;
                    totalErrors += data.errors || 0;
                    grandTotal = data.total || grandTotal;
                    hasMore = data.has_more || false;
                    offset = data.next_offset || (offset + limit);
                    
                    if (data.files && data.files.length > 0) {
                        allFiles = allFiles.concat(data.files);
                        data.files.forEach(file => {
                            const icon = file.status === 'success' ? '✅' : '❌';
                            $('#debug-log').append(`  ${icon} ${file.name}\n`);
                        });
                    }
                    
                    if (grandTotal > 0) {
                        const percentage = data.percentage || Math.round((totalProcessed / grandTotal) * 100);
                        $('#progress-bar-fill').css('width', percentage + '%');
                        $('#progress-percent').text(percentage + '%');
                    }
                    
                    $('#debug-log').append(`   ✅ ${data.processed} processati, ${data.saved} salvati, ${data.errors} errori\n`);
                    batchNumber++;
                    
                    if (hasMore) {
                        await new Promise(resolve => setTimeout(resolve, 500));
                    }
                } else {
                    throw new Error(response.data?.message || 'Errore sconosciuto');
                }
            } catch (error) {
                console.error('[Chunked] ❌ Errore:', error);
                $('#progress-status').text('❌ Errore');
                $('#debug-log').append(`\n❌ ERRORE: ${error.message || error}\n`);
                totalErrors++;
                hasMore = false;
                alert('❌ Errore: ' + (error.message || error));
            }
        }
        
        console.log(`[Chunked] 🎉 Completato! ${totalProcessed} processati`);
        
        $('#progress-bar-fill').css('width', '100%');
        $('#progress-percent').text('100%');
        $('#progress-status').text(`✅ Completato! ${totalProcessed} file`);
        
        $('#stat-total').text(grandTotal);
        $('#stat-processed').text(totalProcessed);
        $('#stat-new').text(totalSaved);
        $('#stat-updated').text(totalProcessed - totalSaved);
        $('#stat-errors').text(totalErrors);
        $('#summary-total').text(grandTotal);
        $('#summary-new').text(totalSaved);
        $('#summary-updated').text(totalProcessed - totalSaved);
        $('#summary-errors').text(totalErrors);
        
        if (totalErrors > 0) $('#error-card').show();
        $('#results-section').fadeIn();
        
        if (allFiles.length > 0) {
            const tbody = $('#new-files-table-body');
            tbody.empty();
            allFiles.forEach(function(f) {
                const row = f.status === 'success' 
                    ? `<tr><td>-</td><td>${f.name}</td><td><span class="badge badge-success">✅</span></td><td>ID: ${f.id}</td><td class="mobile-hide">${f.name}</td></tr>`
                    : `<tr><td>-</td><td>${f.name}</td><td><span class="badge badge-danger">❌</span></td><td>${f.error}</td><td class="mobile-hide">${f.name}</td></tr>`;
                tbody.append(row);
            });
            $('#new-files-box').fadeIn();
        }
        
        $('#debug-log').append(`\n${'='.repeat(50)}\n✅ COMPLETATO\n📊 Total: ${grandTotal}, Processati: ${totalProcessed}, Salvati: ${totalSaved}, Errori: ${totalErrors}\n⏱️  ${new Date().toLocaleString('it-IT')}\n`);
        
        btn.prop('disabled', false).html('<span class="dashicons dashicons-update"></span> Analizza Ora');
        resetBtn.prop('disabled', false);
        isScanningChunked = false;
        
        return false;
    });

    function startBatchScanStandard() {
        const year = $('#scan-year').val();
        const month = $('#scan-month').val();
        const btn = $('#start-scan-btn');
        const resetBtn = $('#reset-scan-btn');

        $('#progress-section').show();
        $('#progress-bar-fill').css('width', '0%');
        $('#progress-percent').text('0%');
        $('#progress-status').text('Connessione a Google Drive...');
        $('#results-section').hide();
        $('#new-files-box').hide();
        $('#debug-log').text('Avvio scansione...\n');

        btn.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> Scansione...');
        resetBtn.prop('disabled', true);

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: { action: 'batch_scan_excel', nonce: '<?php echo wp_create_nonce('disco747_batch_scan'); ?>', year: year, month: month },
            success: function(response) {
                if (response.success) {
                    const d = response.data;
                    $('#progress-bar-fill').css('width', '100%');
                    $('#progress-percent').text('100%');
                    $('#progress-status').text('✅ Completato!');
                    $('#stat-total').text(d.total_files || 0);
                    $('#stat-processed').text(d.processed || 0);
                    $('#stat-new').text(d.new_records || 0);
                    $('#stat-updated').text(d.updated_records || 0);
                    $('#stat-errors').text(d.errors || 0);
                    $('#summary-total').text(d.total_files || 0);
                    $('#summary-new').text(d.new_records || 0);
                    $('#summary-updated').text(d.updated_records || 0);
                    $('#summary-errors').text(d.errors || 0);
                    if (d.errors > 0) $('#error-card').show();
                    $('#results-section').fadeIn();
                    let log = `✅ SCANSIONE COMPLETATA\n${'='.repeat(50)}\n\n📊 RISULTATI:\n   File trovati:    ${d.total_files}\n   Processati:      ${d.processed}\n   Nuovi:           ${d.new_records}\n   Aggiornati:      ${d.updated_records}\n   Errori:          ${d.errors}\n\n${'='.repeat(50)}\n⏱️  Completato: ${new Date().toLocaleString('it-IT')}`;
                    $('#debug-log').text(log);
                    if (d.new_files_list && d.new_files_list.length > 0) {
                        showNewFiles(d.new_files_list);
                    } else {
                        $('#new-files-box').fadeIn();
                        $('#new-files-table-body').html('<tr><td colspan="5" style="text-align: center; padding: 30px; color: #999;">Nessun file processato</td></tr>');
                    }
                } else {
                    $('#progress-status').text('❌ Errore');
                    $('#debug-log').text('❌ ERRORE:\n' + (response.data.message || 'Sconosciuto'));
                    alert('❌ Errore: ' + (response.data.message || 'Errore sconosciuto'));
                }
            },
            error: function(xhr, status, error) {
                $('#progress-status').text('❌ Errore connessione');
                $('#debug-log').text('❌ ERRORE AJAX:\nStatus: ' + status + '\nError: ' + error);
                alert('❌ Errore di connessione: ' + error);
            },
            complete: function() {
                btn.prop('disabled', false).html('<span class="dashicons dashicons-update"></span> Analizza Ora');
                resetBtn.prop('disabled', false);
            }
        });
    }
    // Fine funzione startBatchScanStandard

    $('#reset-scan-btn').on('click', function() {
        if (!confirm('⚠️ ATTENZIONE!\n\nQuesto cancellerà TUTTI i record dalla tabella e rifarà la scansione completa.\n\nSei sicuro di voler procedere?')) return;
        
        const year = $('#scan-year').val();
        const month = $('#scan-month').val();
        const btn = $(this);
        const scanBtn = $('#start-scan-btn');

        $('#progress-section').show();
        $('#progress-bar-fill').css('width', '0%');
        $('#progress-percent').text('0%');
        $('#progress-status').text('🗑️ Svuotamento database...');
        $('#results-section').hide();
        $('#new-files-box').hide();
        $('#debug-log').text('🗑️ Svuotamento database in corso...\n');

        btn.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> Elaborazione...');
        scanBtn.prop('disabled', true);

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: { action: 'reset_and_scan_excel', nonce: '<?php echo wp_create_nonce('disco747_batch_scan'); ?>', year: year, month: month },
            success: function(response) {
                if (response.success) {
                    const d = response.data;
                    $('#progress-bar-fill').css('width', '100%');
                    $('#progress-percent').text('100%');
                    $('#progress-status').text('✅ Completato!');
                    $('#stat-total').text(d.total_files || 0);
                    $('#stat-processed').text(d.processed || 0);
                    $('#stat-new').text(d.new_records || 0);
                    $('#stat-updated').text(d.updated_records || 0);
                    $('#stat-errors').text(d.errors || 0);
                    $('#summary-total').text(d.total_files || 0);
                    $('#summary-new').text(d.new_records || 0);
                    $('#summary-updated').text(d.updated_records || 0);
                    $('#summary-errors').text(d.errors || 0);
                    if (d.errors > 0) $('#error-card').show();
                    $('#results-section').fadeIn();
                    let log = `✅ DATABASE SVUOTATO E RIANALIZZATO\n${'='.repeat(50)}\n\n📊 RISULTATI:\n   File trovati:    ${d.total_files}\n   Processati:      ${d.processed}\n   Nuovi:           ${d.new_records}\n   Errori:          ${d.errors}\n\n${'='.repeat(50)}\n⏱️  Completato: ${new Date().toLocaleString('it-IT')}`;
                    $('#debug-log').text(log);
                    if (d.new_files_list && d.new_files_list.length > 0) {
                        showNewFiles(d.new_files_list);
                    } else {
                        $('#new-files-box').fadeIn();
                        $('#new-files-table-body').html('<tr><td colspan="5" style="text-align: center; padding: 30px;">Nessun file trovato</td></tr>');
                    }
                } else {
                    $('#progress-status').text('❌ Errore');
                    $('#debug-log').text('❌ ERRORE:\n' + (response.data.message || 'Sconosciuto'));
                    alert('❌ Errore: ' + (response.data.message || 'Errore sconosciuto'));
                }
            },
            error: function(xhr, status, error) {
                $('#progress-status').text('❌ Errore connessione');
                $('#debug-log').text('❌ ERRORE AJAX:\nStatus: ' + status + '\nError: ' + error);
                alert('❌ Errore di connessione: ' + error);
            },
            complete: function() {
                btn.prop('disabled', false).html('<span class="dashicons dashicons-trash"></span> Svuota e Rianalizza');
                scanBtn.prop('disabled', false);
            }
        });
    });

    function showNewFiles(files) {
        const tbody = $('#new-files-table-body');
        tbody.empty();
        if (!files || files.length === 0) {
            tbody.html('<tr><td colspan="5" style="text-align: center; padding: 30px; color: #999;">Nessun file da mostrare</td></tr>');
            return;
        }
        files.forEach(function(f) {
            let badge = 'badge-info';
            let icon = '📅';
            let stato = f.stato || 'Attivo';
            if (stato.toLowerCase() === 'confermato') { badge = 'badge-success'; icon = '✅'; }
            else if (stato.toLowerCase() === 'annullato') { badge = 'badge-danger'; icon = '❌'; }
            const row = `<tr><td style="font-weight: bold; color: #667eea;">${f.data_evento || '-'}</td><td>${f.tipo_evento || '-'}</td><td><span class="badge ${badge}">${f.tipo_menu || '-'}</span></td><td>${icon} ${stato}</td><td class="mobile-hide" style="font-size: 12px; color: #666;">${f.filename || '-'}</td></tr>`;
            tbody.append(row);
        });
        $('#new-files-box').fadeIn();
        setTimeout(function() { $('#new-files-box')[0].scrollIntoView({ behavior: 'smooth', block: 'nearest' }); }, 300);
    }
});
</script>