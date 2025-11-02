/**
 * 747 Disco CRM - Excel Scanner Advanced
 * Sistema di scansione e analisi file Excel da Google Drive
 * 
 * @version 1.1.0
 * @since 11.8.0
 */

(function($) {
    'use strict';

    // ========================================================================
    // DEBUG PANEL - Sistema di logging avanzato
    // ========================================================================
    
    const DebugPanel = {
        logs: [],
        maxLogs: 500,
        
        log: function(category, message, type = 'info') {
            const timestamp = new Date().toISOString().substr(11, 8);
            const logEntry = {
                time: timestamp,
                category: category,
                message: message,
                type: type
            };
            
            this.logs.push(logEntry);
            if (this.logs.length > this.maxLogs) {
                this.logs.shift();
            }
            
            // Log anche in console normale
            console.log(`[${timestamp}] [${category}] ${message}`);
        },
        
        logStep: function(stepNumber, description) {
            this.log(`STEP ${stepNumber}`, description, 'success');
        },
        
        logError: function(context, error) {
            this.log(context, `ERRORE: ${error}`, 'error');
        },
        
        logClick: function(selector) {
            this.log('CLICK', `Click rilevato su: ${selector}`, 'click');
        },
        
        logAjax: function(action, status) {
            this.log('AJAX', `${action} - ${status}`, 'ajax');
        }
    };

    // ========================================================================
    // OGGETTO EXCEL SCANNER CON DEBUG
    // ========================================================================

    const ExcelScanner = {
        
        currentPage: 1,
        totalPages: 1,
        currentSearch: '',
        isLoading: false,
        isBatchScanning: false,

        config: {
            maxRetries: 3,
            retryDelay: 1000,
            maxLogLines: 1000,
            autoRefreshInterval: 30000
        },

        init: function() {
            DebugPanel.logStep(1, 'Inizializzazione ExcelScanner...');
            
            if (!this.checkRequirements()) {
                DebugPanel.logError('INIT', 'Requisiti mancanti');
                return;
            }

            DebugPanel.logStep(2, 'Requisiti OK - Binding eventi...');
            this.bindEvents();
            
            DebugPanel.logStep(3, 'Eventi bindati - Inizializzazione UI...');
            this.initUI();
            
            // Carica tabella iniziale se Google Drive è configurato
            if (window.disco747ExcelScanData?.gdriveAvailable) {
                this.loadExcelTable();
            }
            
            DebugPanel.logStep(4, 'ExcelScanner pronto!');
        },

        checkRequirements: function() {
            if (typeof window.disco747ExcelScanData === 'undefined') {
                DebugPanel.logError('REQUISITI', 'window.disco747ExcelScanData non trovato');
                return false;
            }

            if (typeof $ === 'undefined') {
                DebugPanel.logError('REQUISITI', 'jQuery non trovato');
                return false;
            }

            DebugPanel.log('REQUISITI', 'Tutti i requisiti soddisfatti', 'success');
            DebugPanel.log('CONFIG', `ajaxurl: ${window.disco747ExcelScanData?.ajaxurl || 'N/D'}`, 'info');
            DebugPanel.log('CONFIG', `nonce: ${window.disco747ExcelScanData?.nonce ? 'presente' : 'MANCANTE'}`, window.disco747ExcelScanData?.nonce ? 'success' : 'error');
            DebugPanel.log('CONFIG', `gdriveAvailable: ${window.disco747ExcelScanData?.gdriveAvailable}`, 'info');
            
            return true;
        },

        initUI: function() {
            this.updateUIState();
            
            if (typeof $.fn.tooltip === 'function') {
                $('[data-toggle="tooltip"]').tooltip();
            }

            if (window.disco747ExcelScanData?.gdriveAvailable) {
                $('#excel-search').focus();
            }
            
            DebugPanel.log('UI', 'Interfaccia inizializzata', 'success');
        },

        updateUIState: function() {
            const available = window.disco747ExcelScanData?.gdriveAvailable;
            
            if (!available) {
                $('#excel-search, #manual-file-id').prop('disabled', true);
                $('button[id*="btn"]:not(#refresh-all-btn)').prop('disabled', true);
                $('#files-count').text('N/D - Google Drive non configurato');
                DebugPanel.log('UI', 'Google Drive NON disponibile - UI disabilitata', 'warning');
            } else {
                DebugPanel.log('UI', 'Google Drive disponibile - UI attiva', 'success');
            }
        },

        bindEvents: function() {
            DebugPanel.log('BINDING', 'Inizio binding eventi...', 'info');
            
            // ✅ Pulsante batch scan
            const $batchBtn = $('#disco747-start-batch-scan');
            if ($batchBtn.length === 0) {
                DebugPanel.logError('BINDING', '#disco747-start-batch-scan NON TROVATO nel DOM!');
            } else {
                DebugPanel.log('BINDING', `#disco747-start-batch-scan TROVATO (${$batchBtn.length} elementi)`, 'success');
                
                $batchBtn.on('click', (e) => {
                    DebugPanel.logClick('#disco747-start-batch-scan');
                    DebugPanel.log('EVENT', 'Handler batch scan invocato', 'success');
                    this.startBatchScan();
                });
                
                DebugPanel.log('BINDING', 'Handler click collegato a #disco747-start-batch-scan', 'success');
            }
            
            // Ricerca e filtri
            $('#search-files-btn').on('click', () => this.loadExcelTable(1));
            $('#refresh-files-btn').on('click', () => this.loadExcelTable(1));
            $('#filter-menu').on('change', () => this.loadExcelTable(1));
            $('#search-excel').on('keypress', (e) => {
                if (e.which === 13) {
                    e.preventDefault();
                    this.loadExcelTable(1);
                }
            });
            
            // Altri pulsanti
            $('#refresh-all-btn').on('click', () => this.refreshAll());
            $('#analyze-manual-btn').on('click', () => this.analyzeManualId());
            $('#clear-results-btn').on('click', () => this.clearResults());
            
            $('#toggle-log-btn').on('click', () => this.toggleLog());
            $('#copy-log-btn').on('click', () => this.copyLogToClipboard());
            $('#download-log-btn').on('click', () => this.downloadLog());
            
            $('#export-results-btn').on('click', () => this.exportResults());
            
            $('#prev-page-btn').on('click', () => this.prevPage());
            $('#next-page-btn').on('click', () => this.nextPage());

            $('#manual-file-id').on('keypress', (e) => {
                if (e.which === 13) this.analyzeManualId();
            });

            $('#manual-file-id').on('input', (e) => {
                this.validateFileId($(e.target).val());
            });

            DebugPanel.log('BINDING', 'Tutti gli eventi collegati con successo', 'success');
        },

        /**
         * FUNZIONE PRINCIPALE: Batch Scan PROGRESSIVO con retry automatico
         * ✅ FIX 503: Processa 10 file alla volta invece di tutti insieme
         */
        startBatchScan: function() {
            DebugPanel.logStep('BATCH-1', 'Avvio batch scan progressivo...');
            
            if (this.isBatchScanning) {
                DebugPanel.logError('BATCH', 'Batch scan già in corso');
                alert('⚠️ Scansione batch già in corso');
                return;
            }
            
            if (!window.disco747ExcelScanData?.gdriveAvailable) {
                DebugPanel.logError('BATCH', 'Google Drive non configurato');
                alert('❌ Google Drive non configurato');
                return;
            }
            
            DebugPanel.logStep('BATCH-2', 'Inizializzazione batch progressivo...');
            
            // Stato UI
            this.isBatchScanning = true;
            $('#disco747-start-batch-scan').prop('disabled', true).text('🔄 Scansione in corso...');
            
            // Mostra progress se presente
            const $progress = $('#batch-scan-progress');
            if ($progress.length) {
                $progress.show().find('.progress-bar').css('width', '0%').text('Inizializzazione...');
            }
            
            // Reset statistiche
            this.batchStats = {
                totalFiles: 0,
                processedFiles: 0,
                successCount: 0,
                errorCount: 0,
                batchesCompleted: 0,
                retries: 0
            };
            
            // Avvia processamento batch progressivo
            this.processBatchChunk(1);
        },
        
        /**
         * Processa un chunk di file (10 alla volta)
         * @param {number} attempt - Numero tentativo (per retry automatico)
         */
        processBatchChunk: function(attempt = 1) {
            const batchSize = 10; // 10 file alla volta invece di 35!
            const maxRetries = 3;
            
            DebugPanel.log('CHUNK', `Batch chunk - Tentativo ${attempt}/${maxRetries}`, 'info');
            
            const ajaxData = {
                action: 'batch_scan_excel',
                nonce: window.disco747ExcelScanData?.nonce || '',
                _wpnonce: window.disco747ExcelScanData?.nonce || '',
                batch_size: batchSize,  // ✅ Limitiamo a 10 file
                offset: this.batchStats.processedFiles // Offset per continuare da dove eravamo
            };
            
            $.ajax({
                url: window.disco747ExcelScanData?.ajaxurl || ajaxurl,
                type: 'POST',
                data: ajaxData,
                dataType: 'json',
                timeout: 90000, // ✅ Timeout aumentato a 90 secondi
                beforeSend: function() {
                    DebugPanel.logAjax('batch_scan_excel', `CHUNK ${attempt} - Invio richiesta...`);
                },
                success: (response) => {
                    DebugPanel.log('CHUNK-SUCCESS', 'Risposta ricevuta', 'success');
                    
                    if (response.success && response.data) {
                        const result = response.data;
                        
                        // Aggiorna statistiche
                        if (this.batchStats.totalFiles === 0) {
                            this.batchStats.totalFiles = result.found || 0;
                        }
                        
                        this.batchStats.processedFiles += (result.processed || 0);
                        this.batchStats.successCount += (result.new || 0) + (result.updated || 0);
                        this.batchStats.errorCount += (result.errors || 0);
                        this.batchStats.batchesCompleted++;
                        
                        // Aggiorna progress bar
                        if (this.batchStats.totalFiles > 0) {
                            const percentage = Math.round((this.batchStats.processedFiles / this.batchStats.totalFiles) * 100);
                            $('#batch-scan-progress .progress-bar')
                                .css('width', percentage + '%')
                                .text(`${this.batchStats.processedFiles}/${this.batchStats.totalFiles} file (${percentage}%)`);
                        }
                        
                        // Log messaggi
                        if (result.messages && result.messages.length > 0) {
                            result.messages.forEach(msg => {
                                this.addActivityLog(msg, msg.includes('✅') ? 'success' : msg.includes('❌') ? 'error' : 'info');
                            });
                        }
                        
                        // ✅ Continua con il prossimo batch se ci sono ancora file
                        if (result.has_more || (this.batchStats.processedFiles < this.batchStats.totalFiles)) {
                            DebugPanel.log('CHUNK', `Continuo con prossimo batch (${this.batchStats.processedFiles}/${this.batchStats.totalFiles})`, 'info');
                            
                            // Piccolo delay tra i batch per non sovraccaricare il server
                            setTimeout(() => {
                                this.processBatchChunk(1); // Reset tentativi per nuovo chunk
                            }, 1000);
                            
                        } else {
                            // ✅ Completato!
                            this.completeBatchScan();
                        }
                        
                    } else {
                        const errorMsg = response.data?.message || response.data || 'Errore sconosciuto';
                        DebugPanel.logError('CHUNK', errorMsg);
                        
                        // ✅ RETRY automatico per errori non fatali
                        if (attempt < maxRetries) {
                            this.retryBatchChunk(attempt + 1);
                        } else {
                            this.showNotification('error', `❌ Errore dopo ${maxRetries} tentativi: ${errorMsg}`);
                            this.completeBatchScan();
                        }
                    }
                },
                error: (xhr, status, error) => {
                    DebugPanel.logError('AJAX', `Errore AJAX: ${status} - ${error}`);
                    console.error('XHR:', xhr);
                    
                    // ✅ RETRY automatico per errori 503 o timeout
                    if ((xhr.status === 503 || status === 'timeout' || status === 'error') && attempt < maxRetries) {
                        DebugPanel.log('RETRY', `Errore ${xhr.status}, retry ${attempt + 1}/${maxRetries}`, 'warning');
                        this.retryBatchChunk(attempt + 1);
                    } else {
                        this.showNotification('error', `❌ Errore di connessione: ${error}`);
                        this.completeBatchScan();
                    }
                }
            });
        },
        
        /**
         * Retry con backoff esponenziale
         */
        retryBatchChunk: function(attempt) {
            const retryDelay = Math.min(2000 * Math.pow(2, attempt - 1), 10000); // Max 10 secondi
            
            this.batchStats.retries++;
            
            DebugPanel.log('RETRY', `Attendo ${retryDelay}ms prima del retry #${attempt}`, 'warning');
            this.addActivityLog(`⚠️ Retry automatico in corso (tentativo ${attempt})...`, 'warning');
            
            setTimeout(() => {
                this.processBatchChunk(attempt);
            }, retryDelay);
        },
        
        /**
         * Completa la scansione batch
         */
        completeBatchScan: function() {
            DebugPanel.log('BATCH-COMPLETE', 'Batch scan completato!', 'success');
            
            const stats = this.batchStats;
            
            // Messaggio finale
            let finalMessage = `✅ Scansione completata!\n\n`;
            finalMessage += `📊 File totali trovati: ${stats.totalFiles}\n`;
            finalMessage += `✅ File processati: ${stats.processedFiles}\n`;
            finalMessage += `💾 Salvati con successo: ${stats.successCount}\n`;
            finalMessage += `❌ Errori: ${stats.errorCount}\n`;
            finalMessage += `🔄 Batch completati: ${stats.batchesCompleted}\n`;
            if (stats.retries > 0) {
                finalMessage += `⚠️ Retry automatici: ${stats.retries}\n`;
            }
            
            this.showNotification('success', finalMessage);
            this.addActivityLog(finalMessage, 'success');
            
            // Reset stato
            this.isBatchScanning = false;
            $('#disco747-start-batch-scan').prop('disabled', false).text('✅ Analizza Ora');
            
            // Ricarica tabella
            this.loadExcelTable();
            
            // Nascondi progress dopo 3 secondi
            setTimeout(() => {
                $('#batch-scan-progress').fadeOut();
            }, 3000);
        },

        /**
         * Carica la tabella dei file Excel analizzati
         * @param {number} page - Numero pagina da caricare (default: 1)
         */
        loadExcelTable: function(page = 1) {
            DebugPanel.log('TABLE', 'Caricamento tabella - pagina ' + page, 'info');
            
            const $container = $('#excel-table-container');
            if (!$container.length) {
                DebugPanel.logError('TABLE', 'Container #excel-table-container non trovato!');
                return;
            }
            
            // Mostra loading
            $container.html(`
                <div style="text-align: center; padding: 40px;">
                    <div class="spinner is-active" style="float: none; margin: 0 auto 20px;"></div>
                    <p style="color: #666;">Caricamento dati in corso...</p>
                </div>
            `);
            
            // Prepara dati AJAX
            const ajaxData = {
                action: 'load_excel_analysis_table',
                nonce: window.disco747ExcelScanData?.nonce || '',
                _wpnonce: window.disco747ExcelScanData?.nonce || '',
                page: page,
                search: $('#search-excel').val() || '',
                filter_menu: $('#filter-menu').val() || ''
            };
            
            DebugPanel.log('AJAX', `Chiamata load_excel_analysis_table - pagina ${page}`, 'ajax');
            
            $.ajax({
                url: window.disco747ExcelScanData?.ajaxurl || ajaxurl,
                type: 'POST',
                data: ajaxData,
                success: (response) => {
                    DebugPanel.log('AJAX', 'Risposta ricevuta da load_excel_analysis_table', 'success');
                    
                    if (response.success && response.data) {
                        // Aggiorna HTML tabella
                        if (response.data.html) {
                            $container.html(response.data.html);
                            DebugPanel.log('TABLE', 'HTML tabella aggiornato', 'success');
                        }
                        
                        // Aggiorna paginazione se presente
                        if (response.data.pagination) {
                            $('#excel-pagination').html(response.data.pagination).show();
                            
                            // Bind eventi paginazione
                            $('#excel-pagination .prev-page, #excel-pagination .next-page').on('click', (e) => {
                                e.preventDefault();
                                const newPage = $(e.target).data('page');
                                if (newPage) {
                                    this.loadExcelTable(newPage);
                                }
                            });
                            
                            DebugPanel.log('TABLE', 'Paginazione aggiornata', 'info');
                        }
                        
                        // Aggiorna statistiche
                        if (response.data.stats) {
                            this.updateStats(response.data.stats);
                            DebugPanel.log('TABLE', 'Statistiche aggiornate', 'info');
                        }
                        
                        // Bind eventi sui nuovi elementi
                        this.bindTableEvents();
                        
                        DebugPanel.log('TABLE', `Tabella caricata con successo - ${response.data.total || 0} record`, 'success');
                        
                    } else {
                        const errorMsg = response.data?.message || response.data || 'Errore sconosciuto';
                        $container.html(`
                            <div style="text-align: center; padding: 40px; color: #dc3545;">
                                <div style="font-size: 3rem; margin-bottom: 20px;">⚠️</div>
                                <p>Errore caricamento dati: ${errorMsg}</p>
                                <button class="button button-secondary" onclick="window.ExcelScanner.loadExcelTable()">
                                    🔄 Riprova
                                </button>
                            </div>
                        `);
                        DebugPanel.logError('TABLE', 'Errore risposta: ' + errorMsg);
                    }
                },
                error: (xhr, status, error) => {
                    DebugPanel.logError('AJAX', `Errore chiamata: ${status} - ${error}`);
                    
                    $container.html(`
                        <div style="text-align: center; padding: 40px; color: #dc3545;">
                            <div style="font-size: 3rem; margin-bottom: 20px;">❌</div>
                            <p>Errore di connessione al server</p>
                            <p style="font-size: 0.9rem; color: #999;">${error}</p>
                            <button class="button button-secondary" onclick="window.ExcelScanner.loadExcelTable()">
                                🔄 Riprova
                            </button>
                        </div>
                    `);
                },
                complete: () => {
                    DebugPanel.log('AJAX', 'Chiamata load_excel_analysis_table completata', 'info');
                }
            });
        },

        /**
         * Aggiorna le statistiche nella UI
         * @param {object} stats - Oggetto con le statistiche
         */
        updateStats: function(stats) {
            if (!stats) return;
            
            // Aggiorna contatori principali
            if (stats.total_files !== undefined) {
                $('#stats-total').text(Number(stats.total_files).toLocaleString('it-IT'));
            }
            if (stats.analyzed_success !== undefined) {
                $('#stats-success').text(Number(stats.analyzed_success).toLocaleString('it-IT'));
            }
            if (stats.confirmed_count !== undefined) {
                $('#stats-confirmed').text(Number(stats.confirmed_count).toLocaleString('it-IT'));
            }
            if (stats.analysis_errors !== undefined) {
                $('#stats-errors').text(Number(stats.analysis_errors).toLocaleString('it-IT'));
            }
            
            // Aggiorna debug panel se presente
            if (stats.total_files !== undefined) {
                $('#debug-db-records').text(Number(stats.total_files).toLocaleString('it-IT'));
            }
            
            DebugPanel.log('STATS', `Statistiche aggiornate - Total: ${stats.total_files || 0}`, 'info');
        },

        /**
         * Bind eventi sulla tabella (dopo caricamento AJAX)
         */
        bindTableEvents: function() {
            // Tooltip se disponibile
            if (typeof $.fn.tooltip === 'function') {
                $('[data-toggle="tooltip"]').tooltip();
            }
            
            // Click su righe tabella per dettagli
            $('#excel-table-container tbody tr').on('click', function(e) {
                if (!$(e.target).is('a, button')) {
                    $(this).toggleClass('selected');
                }
            });
            
            DebugPanel.log('EVENTS', 'Eventi tabella collegati', 'info');
        },

        // ========================================================================
        // ALTRE FUNZIONI DI UTILITY
        // ========================================================================

        validateFileId: function(fileId) {
            const btn = $('#analyze-manual-btn');
            const input = $('#manual-file-id');
            
            if (!fileId || fileId.length < 10) {
                btn.prop('disabled', true);
                input.removeClass('valid').addClass('invalid');
                return false;
            }
            
            const gdFileIdPattern = /^[a-zA-Z0-9_-]{25,50}$/;
            
            if (gdFileIdPattern.test(fileId)) {
                btn.prop('disabled', false);
                input.removeClass('invalid').addClass('valid');
                return true;
            } else {
                btn.prop('disabled', true);
                input.removeClass('valid').addClass('invalid');
                return false;
            }
        },

        analyzeManualId: function() {
            const fileId = $('#manual-file-id').val().trim();
            
            if (!this.validateFileId(fileId)) {
                alert('Inserisci un File ID valido');
                $('#manual-file-id').focus().select();
                return;
            }
            
            DebugPanel.log('MANUAL', `Analisi manuale File ID: ${fileId}`, 'info');
        },

        refreshAll: function() {
            DebugPanel.log('REFRESH-ALL', 'Refresh completo', 'info');
            this.currentSearch = '';
            this.currentPage = 1;
            $('#excel-search').val('');
            $('#filter-menu').val('');
            this.loadExcelTable(1);
        },

        clearResults: function() {
            DebugPanel.log('CLEAR', 'Pulizia risultati', 'info');
            $('#results-container').empty();
            $('#results-summary').hide();
        },

        toggleLog: function() {
            $('#log-panel').toggle();
            const isVisible = $('#log-panel').is(':visible');
            $('#toggle-log-btn').text(isVisible ? '📋 Nascondi Log' : '📋 Mostra Log');
        },

        copyLogToClipboard: function() {
            const logText = DebugPanel.logs.map(entry => 
                `[${entry.time}] [${entry.category}] ${entry.message}`
            ).join('\n');
            
            const textarea = $('<textarea>').val(logText).appendTo('body').select();
            document.execCommand('copy');
            textarea.remove();
            
            this.showNotification('success', '📋 Log copiato negli appunti');
        },

        downloadLog: function() {
            const logText = DebugPanel.logs.map(entry => 
                `[${entry.time}] [${entry.category}] ${entry.message}`
            ).join('\n');
            
            const blob = new Blob([logText], {type: 'text/plain'});
            const url = URL.createObjectURL(blob);
            const a = $('<a>').attr({
                href: url,
                download: `excel-scan-log-${new Date().toISOString().substr(0,10)}.txt`
            });
            a[0].click();
            URL.revokeObjectURL(url);
        },

        exportResults: function() {
            DebugPanel.log('EXPORT', 'Esportazione risultati', 'info');
            // TODO: Implementare export CSV/Excel
        },

        prevPage: function() {
            if (this.currentPage > 1) {
                this.currentPage--;
                this.loadExcelTable(this.currentPage);
            }
        },

        nextPage: function() {
            if (this.currentPage < this.totalPages) {
                this.currentPage++;
                this.loadExcelTable(this.currentPage);
            }
        },

        /**
         * Aggiunge log attività nel pannello visuale
         */
        addActivityLog: function(message, type = 'info') {
            const timestamp = new Date().toLocaleTimeString('it-IT');
            const log = document.getElementById('activity-log');
            if (!log) return;
            
            const color = type === 'error' ? '#ff6b6b' : type === 'success' ? '#51cf66' : '#00ff00';
            
            const logEntry = document.createElement('div');
            logEntry.style.color = color;
            logEntry.innerHTML = `[${timestamp}] ${message}`;
            
            log.appendChild(logEntry);
            log.scrollTop = log.scrollHeight;
        },

        /**
         * Mostra notifica toast
         */
        showNotification: function(type, message) {
            // Se esiste sistema notifiche WordPress
            if (typeof wp !== 'undefined' && wp.data && wp.data.dispatch) {
                wp.data.dispatch('core/notices').createNotice(
                    type === 'error' ? 'error' : 'success',
                    message,
                    {
                        isDismissible: true,
                        type: 'snackbar',
                    }
                );
            } else {
                // Fallback alert
                alert(message);
            }
        }
    };

    // ========================================================================
    // INIZIALIZZAZIONE AL DOCUMENT READY
    // ========================================================================

    $(document).ready(function() {
        DebugPanel.log('INIT', '🚀 747 Disco Excel Scanner - Document Ready', 'success');
        DebugPanel.log('CONFIG', `jQuery version: ${$.fn.jquery}`, 'info');
        
        // Esponi globalmente per debug
        window.ExcelScanner = ExcelScanner;
        window.ExcelScannerDebug = DebugPanel;
        
        // Inizializza scanner
        ExcelScanner.init();
        
        // Verifica pulsante nel DOM
        if ($('#disco747-start-batch-scan').length > 0) {
            DebugPanel.log('DOM-CHECK', `Pulsante trovato: #disco747-start-batch-scan`, 'success');
            DebugPanel.log('DOM-CHECK', `Testo pulsante: "${$('#disco747-start-batch-scan').text()}"`, 'info');
            DebugPanel.log('DOM-CHECK', `Pulsante abilitato: ${!$('#disco747-start-batch-scan').prop('disabled')}`, !$('#disco747-start-batch-scan').prop('disabled') ? 'success' : 'warning');
        }

        // Log completo configurazione
        DebugPanel.log('FINAL-CHECK', '🎵 747 Disco Excel Scanner inizializzato', 'success');
        DebugPanel.log('VERSION', '1.1.0', 'info');
        DebugPanel.log('TIMESTAMP', new Date().toISOString(), 'info');
    });

})(jQuery);