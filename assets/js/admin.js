/**
 * WooCommerce Etsy Importer - Admin JavaScript
 */

(function($) {
    'use strict';

    const EtsyImporter = {
        urls: [],
        currentIndex: 0,
        totalUrls: 0,
        importedProducts: [],
        isImporting: false,

        /**
         * Initialize
         */
        init: function() {
            this.bindEvents();
        },

        /**
         * Bind events
         */
        bindEvents: function() {
            $('#wc-etsy-importer-form').on('submit', this.handleFormSubmit.bind(this));
        },

        /**
         * Handle form submit
         */
        handleFormSubmit: function(e) {
            e.preventDefault();

            if (this.isImporting) {
                return;
            }

            const urlsText = $('#etsy-urls').val().trim();

            if (!urlsText) {
                this.showMessage('Please enter at least one Etsy listing URL.', 'error');
                return;
            }

            // Parse URLs (one per line)
            this.urls = urlsText.split('\n')
                .map(url => url.trim())
                .filter(url => url.length > 0);

            this.totalUrls = this.urls.length;

            if (this.totalUrls === 0) {
                this.showMessage('Please enter at least one valid URL.', 'error');
                return;
            }

            // Reset state
            this.currentIndex = 0;
            this.importedProducts = [];

            // Show progress section
            this.showProgressSection();

            // Disable form
            this.disableForm();

            // Start import
            this.startImport();
        },

        /**
         * Show progress section
         */
        showProgressSection: function() {
            $('#import-progress-section').slideDown();
            this.resetProgress();
            this.clearLog();
        },

        /**
         * Reset progress bars
         */
        resetProgress: function() {
            this.updateOverallProgress(0);
            this.updateCurrentProgress(0);
            $('#current-item-url').text('');
            $('#current-progress-status').text('Waiting to start...');
            $('#imported-products-container').hide();
            $('#imported-products-list').empty();
        },

        /**
         * Clear log
         */
        clearLog: function() {
            $('#import-log').empty();
        },

        /**
         * Start import process
         */
        startImport: function() {
            this.isImporting = true;
            this.logInfo('Starting import of ' + this.totalUrls + ' listing(s)...');
            this.importNextUrl();
        },

        /**
         * Import next URL
         */
        importNextUrl: function() {
            if (this.currentIndex >= this.totalUrls) {
                this.completeImport();
                return;
            }

            const url = this.urls[this.currentIndex];
            const itemNumber = this.currentIndex + 1;

            this.logInfo('Processing item ' + itemNumber + ' of ' + this.totalUrls + '...');
            $('#current-item-url').text(url);

            // Update progress
            this.updateCurrentProgress(0);
            $('#current-progress-status').text('Fetching listing data...');

            // Simulate initial progress
            this.updateCurrentProgress(10);

            // Import the listing
            this.importListing(url, itemNumber);
        },

        /**
         * Import single listing
         */
        importListing: function(url, itemNumber) {
            const self = this;

            $.ajax({
                url: wcEtsyImporter.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wcei_import_etsy_listing',
                    nonce: wcEtsyImporter.nonce,
                    url: url
                },
                beforeSend: function() {
                    self.updateCurrentProgress(30);
                    $('#current-progress-status').text('Scraping Etsy listing...');
                },
                success: function(response) {
                    if (response.success) {
                        self.handleImportSuccess(response.data, url, itemNumber);
                    } else {
                        self.handleImportError(response.data.message, url, itemNumber);
                    }
                },
                error: function(jqXHR, textStatus, errorThrown) {
                    self.handleImportError('AJAX error: ' + textStatus, url, itemNumber);
                },
                complete: function() {
                    // Move to next URL
                    self.currentIndex++;
                    setTimeout(function() {
                        self.importNextUrl();
                    }, 500);
                }
            });
        },

        /**
         * Handle import success
         */
        handleImportSuccess: function(data, url, itemNumber) {
            this.updateCurrentProgress(100);
            $('#current-progress-status').html('<span style="color: #46b450;">✓ Product imported successfully!</span>');

            this.logSuccess('Item ' + itemNumber + ': Product "' + data.listing_data.title + '" imported successfully (ID: ' + data.product_id + ')');

            // Add to imported products list
            this.importedProducts.push({
                id: data.product_id,
                title: data.listing_data.title,
                editUrl: data.edit_url
            });

            this.updateImportedProductsList();

            // Update overall progress
            const progress = Math.round((this.currentIndex / this.totalUrls) * 100);
            this.updateOverallProgress(progress);
            this.updateOverallInfo();
        },

        /**
         * Handle import error
         */
        handleImportError: function(message, url, itemNumber) {
            this.updateCurrentProgress(100);
            $('#current-progress-status').html('<span style="color: #dc3232;">✗ Import failed</span>');

            this.logError('Item ' + itemNumber + ': Failed to import - ' + message);

            // Update overall progress
            const progress = Math.round((this.currentIndex / this.totalUrls) * 100);
            this.updateOverallProgress(progress);
            this.updateOverallInfo();
        },

        /**
         * Complete import process
         */
        completeImport: function() {
            this.isImporting = false;
            this.updateOverallProgress(100);
            this.updateCurrentProgress(0);
            $('#current-item-url').text('');
            $('#current-progress-status').text('Import completed!');

            const successCount = this.importedProducts.length;
            const failedCount = this.totalUrls - successCount;

            this.logSuccess('Import process completed!');
            this.logInfo('Successfully imported: ' + successCount + ' product(s)');
            if (failedCount > 0) {
                this.logWarning('Failed: ' + failedCount + ' product(s)');
            }

            this.enableForm();

            if (successCount > 0) {
                this.showMessage('Import completed! ' + successCount + ' product(s) imported successfully.', 'success');
            } else {
                this.showMessage('Import completed with errors. Please check the log for details.', 'error');
            }
        },

        /**
         * Update overall progress
         */
        updateOverallProgress: function(percentage) {
            $('#overall-progress-bar').css('width', percentage + '%');
            $('#overall-progress-text').text(percentage + '%');
        },

        /**
         * Update current progress
         */
        updateCurrentProgress: function(percentage) {
            $('#current-progress-bar').css('width', percentage + '%');
            $('#current-progress-text').text(percentage + '%');
        },

        /**
         * Update overall progress info
         */
        updateOverallInfo: function() {
            const imported = this.currentIndex;
            const total = this.totalUrls;
            $('#overall-progress-info').text(imported + ' of ' + total + ' listings processed');
        },

        /**
         * Update imported products list
         */
        updateImportedProductsList: function() {
            const $list = $('#imported-products-list');
            $list.empty();

            this.importedProducts.forEach(function(product) {
                const $li = $('<li>');
                const $name = $('<span class="product-name">').html('<span class="dashicons dashicons-yes"></span>' + product.title);
                const $actions = $('<div class="product-actions">');

                const $viewBtn = $('<a>')
                    .attr('href', product.editUrl)
                    .attr('target', '_blank')
                    .addClass('button button-secondary')
                    .text('Edit Product');

                $actions.append($viewBtn);
                $li.append($name, $actions);
                $list.append($li);
            });

            $('#imported-products-container').show();
        },

        /**
         * Logging functions
         */
        log: function(message, type) {
            const timestamp = new Date().toLocaleTimeString();
            const $entry = $('<div class="log-entry">');
            const $timestamp = $('<span class="log-timestamp">').text('[' + timestamp + ']');
            const $message = $('<span class="log-' + type + '">').text(message);

            $entry.append($timestamp, ' ', $message);
            $('#import-log').append($entry);

            // Auto-scroll to bottom
            const logElement = document.getElementById('import-log');
            logElement.scrollTop = logElement.scrollHeight;
        },

        logSuccess: function(message) {
            this.log('✓ ' + message, 'success');
        },

        logError: function(message) {
            this.log('✗ ' + message, 'error');
        },

        logInfo: function(message) {
            this.log('ℹ ' + message, 'info');
        },

        logWarning: function(message) {
            this.log('⚠ ' + message, 'warning');
        },

        /**
         * Show message
         */
        showMessage: function(message, type) {
            const $message = $('<div class="wc-etsy-message ' + type + '">');
            const icon = type === 'success' ? 'yes' : 'warning';
            $message.html('<span class="dashicons dashicons-' + icon + '"></span>' + message);

            $('.wc-etsy-importer-form-section').prepend($message);

            setTimeout(function() {
                $message.fadeOut(function() {
                    $(this).remove();
                });
            }, 5000);
        },

        /**
         * Disable form
         */
        disableForm: function() {
            $('#start-import').prop('disabled', true).addClass('importing');
            $('#etsy-urls').prop('disabled', true);
        },

        /**
         * Enable form
         */
        enableForm: function() {
            $('#start-import').prop('disabled', false).removeClass('importing');
            $('#etsy-urls').prop('disabled', false);
        }
    };

    // Initialize on document ready
    $(document).ready(function() {
        EtsyImporter.init();
    });

})(jQuery);
