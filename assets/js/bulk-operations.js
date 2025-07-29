/**
 * Bulk Operations JavaScript
 */
(function($) {
    'use strict';

    var WPCBulkOperations = {
        operationId: null,
        operationType: null,
        isRunning: false,
        targetSites: [],

        init: function() {
            // Check if we have a bulk operation pending
            var urlParams = new URLSearchParams(window.location.search);
            if (urlParams.has('wpc_bulk_operation')) {
                this.operationId = urlParams.get('wpc_bulk_operation');
                this.operationType = urlParams.get('wpc_bulk_action');
                this.showSiteSelectionModal();
            }

            // Bind events
            this.bindEvents();
        },

        bindEvents: function() {
            var self = this;

            // Start operation button
            $('#wpc-start-bulk-operation').on('click', function() {
                self.startBulkOperation();
            });

            // Cancel button
            $('#wpc-cancel-operation').on('click', function() {
                self.closeSiteSelectionModal();
                self.removeOperationFromUrl();
            });

            // Select all sites checkbox
            $('#wpc-select-all-sites').on('change', function() {
                $('.wpc-site-option input[type="checkbox"]').prop('checked', $(this).is(':checked'));
            });

            // Close modal on escape
            $(document).on('keyup', function(e) {
                if (e.key === 'Escape' && !self.isRunning) {
                    self.closeSiteSelectionModal();
                }
            });
        },

        showSiteSelectionModal: function() {
            // For update operations, we don't need site selection
            if (this.operationType === 'wpc_bulk_update') {
                this.startBulkOperation();
                return;
            }

            $('#wpc-site-selection-modal').fadeIn(200);
            $('body').addClass('modal-open');
        },

        closeSiteSelectionModal: function() {
            $('#wpc-site-selection-modal').fadeOut(200);
            $('body').removeClass('modal-open');
        },

        startBulkOperation: function() {
            var self = this;

            // For copy operations, get selected sites
            if (this.operationType === 'wpc_bulk_copy') {
                this.targetSites = [];
                $('.wpc-site-option input[type="checkbox"]:checked').each(function() {
                    self.targetSites.push($(this).val());
                });

                if (this.targetSites.length === 0) {
                    alert(wpc_bulk.strings.select_sites);
                    return;
                }
            }

            this.isRunning = true;
            this.closeSiteSelectionModal();
            this.processBatch();
        },

        processBatch: function() {
            var self = this;
            var action = this.operationType === 'wpc_bulk_copy' ? 
                'wpc_bulk_copy_products' : 'wpc_bulk_update_products';

            var data = {
                action: action,
                nonce: wpc_bulk.nonce,
                operation_id: this.operationId,
                target_sites: this.targetSites
            };

            $.post(wpc_bulk.ajax_url, data, function(response) {
                if (response.success) {
                    self.updateProgress(response.data);

                    if (!response.data.complete && self.isRunning) {
                        // Process next batch
                        setTimeout(function() {
                            self.processBatch();
                        }, 1000);
                    } else {
                        // Operation complete
                        self.operationComplete(response.data);
                    }
                } else {
                    self.showError(response.data || wpc_bulk.strings.error);
                }
            }).fail(function() {
                self.showError(wpc_bulk.strings.error);
            });
        },

        updateProgress: function(data) {
            var percent = Math.round((data.processed / data.total) * 100);
            var progressText = wpc_bulk.strings.processing
                .replace('%1$d', data.processed)
                .replace('%2$d', data.total);

            $('.wpc-progress-fill').css('width', percent + '%');
            $('.wpc-progress-text').text(progressText);

            // Show errors if any
            if (data.errors && data.errors.length > 0) {
                this.showErrors(data.errors);
            }
        },

        operationComplete: function(data) {
            var self = this;
            
            $('.wpc-progress-fill').css('width', '100%');
            $('.wpc-progress-text').text(wpc_bulk.strings.complete);
            
            // Change notice style
            $('#wpc-bulk-operation-notice')
                .removeClass('notice-info')
                .addClass('notice-success');

            // Show summary
            var summary = '<p><strong>' + wpc_bulk.strings.complete + '</strong></p>';
            
            if (data.results && data.results.length > 0) {
                summary += '<p>' + sprintf(
                    'Successfully processed %d operations.',
                    data.results.length
                ) + '</p>';
            }

            if (data.errors && data.errors.length > 0) {
                summary += '<p class="wpc-error-summary">' + sprintf(
                    '%d errors occurred during the operation.',
                    data.errors.length
                ) + '</p>';
            }

            $('#wpc-bulk-progress').html(summary);

            // Remove operation from URL after 3 seconds
            setTimeout(function() {
                self.removeOperationFromUrl();
            }, 3000);
        },

        showError: function(message) {
            $('#wpc-bulk-operation-notice')
                .removeClass('notice-info')
                .addClass('notice-error');
            
            $('#wpc-bulk-progress').html(
                '<p><strong>' + wpc_bulk.strings.error + '</strong></p>' +
                '<p>' + message + '</p>'
            );

            this.isRunning = false;
        },

        showErrors: function(errors) {
            var errorList = '<ul class="wpc-error-list">';
            errors.forEach(function(error) {
                errorList += '<li>' + error + '</li>';
            });
            errorList += '</ul>';

            $('#wpc-bulk-operation-notice').append(errorList);
        },

        removeOperationFromUrl: function() {
            var url = new URL(window.location);
            url.searchParams.delete('wpc_bulk_operation');
            url.searchParams.delete('wpc_bulk_action');
            window.history.replaceState({}, '', url);
        }
    };

    // Helper function
    function sprintf(format) {
        var args = Array.prototype.slice.call(arguments, 1);
        return format.replace(/%[sd]/g, function(match) {
            var replacement = args.shift();
            return match === '%s' ? String(replacement) : Number(replacement);
        });
    }

    // Initialize on document ready
    $(document).ready(function() {
        WPCBulkOperations.init();
    });

})(jQuery);