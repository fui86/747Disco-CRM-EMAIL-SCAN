/**
 * 747 Disco CRM - Excel Scan CHUNKED
 * Fix per errore 503 - Elaborazione a lotti
 * 
 * @version 1.0.0
 * @since 2025-11-02
 */

(function($) {
    'use strict';
    
    console.log('[Excel-Scan-CHUNKED] ?? Modulo chunked caricato');
    
    // Aspetta che il DOM sia pronto
    $(document).ready(function() {
        
        console.log('[Excel-Scan-CHUNKED] ?? Aspetto 1 secondo per sovrascrivere excel-scan.js...');
        
        // Delay per sovrascrivere excel-scan.js
        setTimeout(function() {
            
            console.log('[Excel-Scan-CHUNKED] ? Sovrascrivo handler di #start-scan-btn...');
            
            // Rimuovi TUTTI gli handler esistenti
            $('#start-scan-btn').off('click');
            
            let isScanningChunked = false;
            
            // Registra nuovo handler CHUNKED
            $('#start-scan-btn').on('click', async function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                console.log('='.repeat(70));
                console.log('[CHUNKED-SCAN] ?????? AVVIO SCANSIONE BATCH OTTIMIZZATA');
                console.log('[CHUNKED-SCAN] Timestamp:', new Date().toLocaleTimeString());
                console.log('='.repeat(70));
                
                if (isScanningChunked) {
                    alert('?? Scansione gi? in corso');
                    return false;
                }
                
                isScanningChunked = true;
                
                const year = $('#scan-year').val();
                const month = $('#scan-month').val();
                const btn = $(this);
                const resetBtn = $('#reset-scan-btn');
                
                // UI Setup
                btn.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> Scansione...');
                resetBtn.prop('disabled', true);
                
                $('#progress-section').show();
                $('#progress-bar-fill').css('width', '0%');
                $('#progress-percent').text('0%');
                $('#progress-status').text('?? Inizializzazione...');
                $('#results-section').hide();
                $('#new-files-box').hide();
                $('#debug-log').text('?? SCANSIONE CHUNKED AVVIATA\n\n');
                
                // Parametri
                let offset = 0;
                const limit = 10;
                let totalProcessed = 0;
                let totalSaved = 0;
                let totalErrors = 0;
                let grandTotal = 0;
                let hasMore = true;
                let batchNumber = 1;
                let allFiles = [];
                
                console.log(`[CHUNKED] Anno: ${year}, Mese: ${month || 'tutti'}, Limit: ${limit}`);
                $('#debug-log').append(`?? Anno: ${year}\n?? Mese: ${month || 'tutti i mesi'}\n?? File per batch: ${limit}\n\n`);
                
                // Loop ricorsivo
                while (hasMore) {
                    try {
                        console.log(`[CHUNKED] ?? Batch #${batchNumber}: offset=${offset}, limit=${limit}`);
                        
                        $('#progress-status').text(`?? Batch ${batchNumber}... (${totalProcessed}/${grandTotal || '?'} file)`);
                        $('#debug-log').append(`\n?? Batch #${batchNumber} (offset ${offset})...\n`);
                        
                        // Chiamata AJAX
                        const response = await $.ajax({
                            url: ajaxurl || '/wp-admin/admin-ajax.php',
                            type: 'POST',
                            data: {
                                action: 'batch_scan_excel_chunked',
                                offset: offset,
                                limit: limit,
                                year: year,
                                month: month,
                                nonce: window.disco747ExcelScanData?.nonce || ''
                            },
                            timeout: 90000
                        });
                        
                        console.log(`[CHUNKED] ? Risposta batch #${batchNumber}:`, response);
                        
                        if (response.success && response.data) {
                            const data = response.data;
                            
                            // Aggiorna contatori
                            totalProcessed += data.processed || 0;
                            totalSaved += data.saved || 0;
                            totalErrors += data.errors || 0;
                            grandTotal = data.total || grandTotal;
                            hasMore = data.has_more || false;
                            offset = data.next_offset || (offset + limit);
                            
                            // Salva file
                            if (data.files && data.files.length > 0) {
                                allFiles = allFiles.concat(data.files);
                                
                                data.files.forEach(file => {
                                    const icon = file.status === 'success' ? '?' : '?';
                                    $('#debug-log').append(`  ${icon} ${file.name}\n`);
                                    console.log(`[CHUNKED] ${icon} ${file.name}`);
                                });
                            }
                            
                            // Progress bar
                            if (grandTotal > 0) {
                                const percentage = data.percentage || Math.round((totalProcessed / grandTotal) * 100);
                                $('#progress-bar-fill').css('width', percentage + '%');
                                $('#progress-percent').text(percentage + '%');
                                console.log(`[CHUNKED] Progress: ${percentage}% (${totalProcessed}/${grandTotal})`);
                            }
                            
                            $('#debug-log').append(`   ? Processati: ${data.processed}, Salvati: ${data.saved}, Errori: ${data.errors}\n`);
                            
                            batchNumber++;
                            
                            // Pausa tra batch
                            if (hasMore) {
                                console.log('[CHUNKED] ?? Pausa 500ms prima del prossimo batch...');
                                await new Promise(resolve => setTimeout(resolve, 500));
                            }
                            
                        } else {
                            throw new Error(response.data?.message || 'Errore sconosciuto');
                        }
                        
                    } catch (error) {
                        console.error('[CHUNKED] ? ERRORE batch:', error);
                        $('#progress-status').text('? Errore durante la scansione');
                        $('#debug-log').append(`\n? ERRORE: ${error.message || error}\n`);
                        totalErrors++;
                        hasMore = false;
                        alert('? Errore: ' + (error.message || error));
                    }
                }
                
                // COMPLETAMENTO
                console.log(`[CHUNKED] ?? SCANSIONE COMPLETATA!`);
                console.log(`[CHUNKED] Risultati: Total=${totalProcessed}, Saved=${totalSaved}, Errors=${totalErrors}`);
                
                $('#progress-bar-fill').css('width', '100%');
                $('#progress-percent').text('100%');
                $('#progress-status').text(`? Completato! ${totalProcessed} file processati`);
                
                // Statistiche
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
                
                // Tabella file
                if (allFiles.length > 0) {
                    const tbody = $('#new-files-table-body');
                    tbody.empty();
                    
                    allFiles.forEach(function(f) {
                        const row = f.status === 'success' 
                            ? `<tr><td>-</td><td>${f.name}</td><td><span class="badge badge-success">? Salvato</span></td><td>ID: ${f.id}</td><td class="mobile-hide">${f.name}</td></tr>`
                            : `<tr><td>-</td><td>${f.name}</td><td><span class="badge badge-danger">? Errore</span></td><td style="font-size:11px;">${f.error}</td><td class="mobile-hide">${f.name}</td></tr>`;
                        tbody.append(row);
                    });
                    
                    $('#new-files-box').fadeIn();
                }
                
                // Log finale
                $('#debug-log').append(`\n${'='.repeat(60)}\n`);
                $('#debug-log').append(`? SCANSIONE COMPLETATA CON SUCCESSO\n\n`);
                $('#debug-log').append(`?? RISULTATI FINALI:\n`);
                $('#debug-log').append(`   File trovati:     ${grandTotal}\n`);
                $('#debug-log').append(`   Processati:       ${totalProcessed}\n`);
                $('#debug-log').append(`   Salvati:          ${totalSaved}\n`);
                $('#debug-log').append(`   Errori:           ${totalErrors}\n`);
                $('#debug-log').append(`\n??  Completato: ${new Date().toLocaleString('it-IT')}\n`);
                $('#debug-log').append(`${'='.repeat(60)}\n`);
                
                // Reset UI
                btn.prop('disabled', false).html('<span class="dashicons dashicons-update"></span> Analizza Ora');
                resetBtn.prop('disabled', false);
                isScanningChunked = false;
                
                return false;
            });
            
            console.log('[Excel-Scan-CHUNKED] ? Handler CHUNKED registrato con successo su #start-scan-btn');
            console.log('[Excel-Scan-CHUNKED] ?? Pronto! Clicca "Analizza Ora" per testare.');
            
        }, 1500); // 1.5 secondi per essere sicuri
        
        console.log('[Excel-Scan-CHUNKED] ? Attendo 1.5 secondi prima di registrare handler...');
    });
    
})(jQuery);
