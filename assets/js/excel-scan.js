jQuery(document).ready(function($) {
    'use strict';

    console.log('[Excel-Scan] Script caricato');
    console.log('[Excel-Scan] jQuery version:', $.fn.jquery);
    console.log('[Excel-Scan] ajaxurl:', typeof ajaxurl !== 'undefined' ? ajaxurl : 'NON DEFINITO');
    console.log('[Excel-Scan] disco747ExcelScanData:', typeof disco747ExcelScanData !== 'undefined' ? disco747ExcelScanData : 'NON DEFINITO');

    const ExcelScan = {
        config: {
            ajaxurl: typeof ajaxurl !== 'undefined' ? ajaxurl : '/wp-admin/admin-ajax.php',
            nonce: typeof disco747ExcelScanData !== 'undefined' ? disco747ExcelScanData.nonce : ''
        },
        
        init: function() {
            console.log('[Excel-Scan] Inizializzazione...');
            console.log('[Excel-Scan] Config:', this.config);
            
            const startBtn = $('#start-scan-btn');
            const resetBtn = $('#reset-scan-btn');
            
            console.log('[Excel-Scan] Pulsante #start-scan-btn:', startBtn.length > 0 ? 'TROVATO' : 'NON TROVATO');
            console.log('[Excel-Scan] Pulsante #reset-scan-btn:', resetBtn.length > 0 ? 'TROVATO' : 'NON TROVATO');
            
            if (startBtn.length === 0) {
                console.error('[Excel-Scan] ERRORE: Pulsante #start-scan-btn non trovato nel DOM');
                return;
            }
            
            this.bindEvents();
            console.log('[Excel-Scan] Inizializzazione completata');
        },
        
        bindEvents: function() {
            const self = this;
            
            $('#start-scan-btn').on('click', function(e) {
                console.log('[Excel-Scan] Click su start-scan-btn rilevato');
                self.handleScan(e);
            });
            
            $('#reset-scan-btn').on('click', function(e) {
                console.log('[Excel-Scan] Click su reset-scan-btn rilevato');
                self.handleResetScan(e);
            });
            
            console.log('[Excel-Scan] Eventi collegati correttamente');
        },
        
        handleScan: function(e) {
            e.preventDefault();
            console.log('[Excel-Scan] === AVVIO SCANSIONE ===');
            
            const year = $('#scan-year').val();
            const month = $('#scan-month').val();
            const btn = $('#start-scan-btn');
            const resetBtn = $('#reset-scan-btn');

            console.log('[Excel-Scan] Parametri:', {year: year, month: month});

            $('#progress-section').show();
            $('#progress-bar-fill').css('width', '0%');
            $('#progress-percent').text('0%');
            $('#progress-status').text('Connessione a Google Drive...');
            $('#results-section').hide();
            $('#new-files-box').hide();
            $('#debug-log').text('Avvio scansione...\n');

            btn.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> Scansione...');
            resetBtn.prop('disabled', true);

            const ajaxData = {
                action: 'batch_scan_excel',
                nonce: this.config.nonce,
                year: year,
                month: month
            };

            console.log('[Excel-Scan] Invio richiesta AJAX a:', this.config.ajaxurl);
            console.log('[Excel-Scan] Dati AJAX:', ajaxData);

            $.ajax({
                url: this.config.ajaxurl,
                type: 'POST',
                data: ajaxData,
                timeout: 300000, // ✅ 5 minuti timeout (per scansioni lunghe)
                success: function(response) {
                    console.log('[Excel-Scan] Risposta AJAX ricevuta:', response);
                    ExcelScan.handleScanSuccess(response);
                },
                error: function(xhr, status, error) {
                    console.error('[Excel-Scan] Errore AJAX:', {xhr: xhr, status: status, error: error});
                    ExcelScan.handleScanError(xhr, status, error);
                },
                complete: function() {
                    console.log('[Excel-Scan] Richiesta AJAX completata');
                    btn.prop('disabled', false).html('<span class="dashicons dashicons-update"></span> Analizza Ora');
                    resetBtn.prop('disabled', false);
                }
            });
        },
        
        handleResetScan: function(e) {
            e.preventDefault();
            console.log('[Excel-Scan] === AVVIO RESET & SCAN ===');
            
            if (!confirm('⚠️ ATTENZIONE!\n\nQuesto cancellerà TUTTI i record dalla tabella e rifarà la scansione completa.\n\nSei sicuro di voler procedere?')) {
                console.log('[Excel-Scan] Reset annullato dall\'utente');
                return;
            }
            
            const year = $('#scan-year').val();
            const month = $('#scan-month').val();
            const btn = $('#reset-scan-btn');
            const scanBtn = $('#start-scan-btn');

            console.log('[Excel-Scan] Parametri reset:', {year: year, month: month});

            $('#progress-section').show();
            $('#progress-bar-fill').css('width', '0%');
            $('#progress-percent').text('0%');
            $('#progress-status').text('🗑️ Svuotamento database...');
            $('#results-section').hide();
            $('#new-files-box').hide();
            $('#debug-log').text('🗑️ Svuotamento database in corso...\n');

            btn.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> Elaborazione...');
            scanBtn.prop('disabled', true);

            const ajaxData = {
                action: 'reset_and_scan_excel',
                nonce: this.config.nonce,
                year: year,
                month: month
            };

            console.log('[Excel-Scan] Invio richiesta AJAX reset a:', this.config.ajaxurl);
            console.log('[Excel-Scan] Dati AJAX reset:', ajaxData);

            $.ajax({
                url: this.config.ajaxurl,
                type: 'POST',
                data: ajaxData,
                timeout: 300000, // ✅ 5 minuti timeout (per scansioni lunghe)
                success: function(response) {
                    console.log('[Excel-Scan] Risposta AJAX reset ricevuta:', response);
                    ExcelScan.handleScanSuccess(response);
                },
                error: function(xhr, status, error) {
                    console.error('[Excel-Scan] Errore AJAX reset:', {xhr: xhr, status: status, error: error});
                    ExcelScan.handleScanError(xhr, status, error);
                },
                complete: function() {
                    console.log('[Excel-Scan] Richiesta AJAX reset completata');
                    btn.prop('disabled', false).html('<span class="dashicons dashicons-trash"></span> Svuota e Rianalizza');
                    scanBtn.prop('disabled', false);
                }
            });
        },
        
        handleScanSuccess: function(response) {
            console.log('[Excel-Scan] === GESTIONE RISPOSTA SUCCESSO ===');
            console.log('[Excel-Scan] Response completa:', response);
            
            if (response.success) {
                console.log('[Excel-Scan] Risposta positiva, elaborazione dati...');
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
                
                if (d.errors > 0) {
                    $('#error-card').show();
                }
                
                $('#results-section').fadeIn();
                
                const log = '✅ SCANSIONE COMPLETATA\n' + '='.repeat(50) + '\n\n' +
                    '📊 RISULTATI:\n' +
                    '   File trovati:    ' + (d.total_files || 0) + '\n' +
                    '   Processati:      ' + (d.processed || 0) + '\n' +
                    '   Nuovi:           ' + (d.new_records || 0) + '\n' +
                    '   Aggiornati:      ' + (d.updated_records || 0) + '\n' +
                    '   Errori:          ' + (d.errors || 0) + '\n\n' +
                    '='.repeat(50) + '\n' +
                    '⏱️  Completato: ' + new Date().toLocaleString('it-IT');
                
                $('#debug-log').text(log);
                
                if (d.new_files_list && d.new_files_list.length > 0) {
                    console.log('[Excel-Scan] Mostrando ' + d.new_files_list.length + ' file processati');
                    this.showNewFiles(d.new_files_list);
                } else {
                    console.log('[Excel-Scan] Nessun file da mostrare');
                    $('#new-files-box').fadeIn();
                    $('#new-files-table-body').html('<tr><td colspan="5" style="text-align: center; padding: 30px; color: #999;">Nessun file processato</td></tr>');
                }
            } else {
                console.error('[Excel-Scan] Risposta negativa dal server');
                $('#progress-status').text('❌ Errore');
                const errorMsg = (response.data && response.data.message) ? response.data.message : 'Errore sconosciuto';
                $('#debug-log').text('❌ ERRORE:\n' + errorMsg);
                alert('❌ Errore: ' + errorMsg);
            }
        },
        
        handleScanError: function(xhr, status, error) {
            console.error('[Excel-Scan] === ERRORE AJAX ===');
            console.error('[Excel-Scan] Status:', status);
            console.error('[Excel-Scan] Error:', error);
            console.error('[Excel-Scan] Response:', xhr.responseText);
            
            $('#progress-status').text('❌ Errore connessione');
            $('#debug-log').text('❌ ERRORE AJAX:\n' +
                'Status: ' + status + '\n' +
                'Error: ' + error + '\n' +
                'Response: ' + (xhr.responseText || 'Nessuna risposta'));
            alert('❌ Errore di connessione: ' + error);
        },
        
        showNewFiles: function(files) {
            console.log('[Excel-Scan] Popolamento tabella con ' + files.length + ' file');
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
                
                if (stato.toLowerCase() === 'confermato') {
                    badge = 'badge-success';
                    icon = '✅';
                } else if (stato.toLowerCase() === 'annullato') {
                    badge = 'badge-danger';
                    icon = '❌';
                }
                
                const row = '<tr>' +
                    '<td style="font-weight: bold; color: #667eea;">' + (f.data_evento || '-') + '</td>' +
                    '<td>' + (f.tipo_evento || '-') + '</td>' +
                    '<td><span class="badge ' + badge + '">' + (f.tipo_menu || '-') + '</span></td>' +
                    '<td>' + icon + ' ' + stato + '</td>' +
                    '<td class="mobile-hide" style="font-size: 12px; color: #666;">' + (f.filename || '-') + '</td>' +
                    '</tr>';
                
                tbody.append(row);
            });
            
            $('#new-files-box').fadeIn();
            
            setTimeout(function() {
                const box = $('#new-files-box')[0];
                if (box) {
                    box.scrollIntoView({
                        behavior: 'smooth',
                        block: 'nearest'
                    });
                }
            }, 300);
            
            console.log('[Excel-Scan] Tabella popolata con successo');
        }
    };

    console.log('[Excel-Scan] Avvio inizializzazione...');
    ExcelScan.init();
});