/**
 * WPC Multisite Products Copier Admin JavaScript
 *
 * @package WPC_Multisite_Products_Copier
 * @since 1.0.0
 */

(function($) {
    'use strict';

    $(document).ready(function() {
        var $metabox = $('.wpc-mpc-metabox');
        var $checkboxes = $('.wpc-target-site-checkbox');
        var $createBtn = $('#wpc_mpc_create_multiple');
        var $updateBtn = $('#wpc_mpc_update_multiple');
        var $status = $('.wpc-mpc-status');
        var $statusMessage = $status.find('.status-message');
        var $spinner = $status.find('.spinner');
        
        var isProcessing = false;
        var processQueue = [];
        var currentIndex = 0;

        // Handle checkbox changes
        $checkboxes.on('change', updateButtonStates);
        
        // Handle select all/none/not synced links
        $('#wpc-select-all').on('click', function(e) {
            e.preventDefault();
            $checkboxes.prop('checked', true);
            updateButtonStates();
        });
        
        $('#wpc-select-none').on('click', function(e) {
            e.preventDefault();
            $checkboxes.prop('checked', false);
            updateButtonStates();
        });
        
        $('#wpc-select-not-synced').on('click', function(e) {
            e.preventDefault();
            $checkboxes.each(function() {
                var isSynced = $(this).data('synced') === 1;
                $(this).prop('checked', !isSynced);
            });
            updateButtonStates();
        });

        // Update button states based on selected checkboxes
        function updateButtonStates() {
            var selectedSites = getSelectedSites();
            var hasSelection = selectedSites.length > 0;
            var hasCreate = selectedSites.some(function(site) { return !site.synced; });
            var hasUpdate = selectedSites.some(function(site) { return site.synced; });
            
            $createBtn.prop('disabled', !hasCreate);
            $updateBtn.prop('disabled', !hasUpdate);
        }

        // Get selected sites with their sync status
        function getSelectedSites() {
            var sites = [];
            $checkboxes.filter(':checked').each(function() {
                sites.push({
                    blogId: $(this).val(),
                    synced: $(this).data('synced') === 1,
                    productId: $(this).data('product-id')
                });
            });
            return sites;
        }

        // Handle create button click
        $createBtn.on('click', function() {
            var selectedSites = getSelectedSites().filter(function(site) {
                return !site.synced;
            });
            
            if (selectedSites.length === 0) {
                alert(wpc_mpc_ajax.messages.select_site);
                return;
            }
            
            if (confirm(sprintf(wpc_mpc_ajax.messages.confirm_create || 'Create product on %d selected sites?', selectedSites.length))) {
                processQueue = selectedSites;
                currentIndex = 0;
                isProcessing = true;
                processNextCreate();
            }
        });

        // Handle update button click
        $updateBtn.on('click', function() {
            var selectedSites = getSelectedSites().filter(function(site) {
                return site.synced;
            });
            
            if (selectedSites.length === 0) {
                alert(wpc_mpc_ajax.messages.select_site);
                return;
            }
            
            if (confirm(sprintf(wpc_mpc_ajax.messages.confirm_update || 'Update product on %d selected sites?', selectedSites.length))) {
                processQueue = selectedSites;
                currentIndex = 0;
                isProcessing = true;
                processNextUpdate();
            }
        });

        // Process create queue
        function processNextCreate() {
            if (currentIndex >= processQueue.length || !isProcessing) {
                // All done
                hideSpinner();
                if (currentIndex > 0) {
                    showStatus(sprintf('Successfully created product on %d sites.', currentIndex), 'success');
                    setTimeout(function() {
                        location.reload();
                    }, 2000);
                }
                return;
            }
            
            var site = processQueue[currentIndex];
            var progress = sprintf('Creating on site %d of %d...', currentIndex + 1, processQueue.length);
            showStatus(progress);
            
            $.ajax({
                url: wpc_mpc_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'wpc_mpc_create_product',
                    nonce: wpc_mpc_ajax.nonce,
                    product_id: wpc_mpc_ajax.product_id,
                    target_blog_id: site.blogId
                },
                success: function(response) {
                    if (response.success) {
                        // Update checkbox data
                        var $checkbox = $checkboxes.filter('[value="' + site.blogId + '"]');
                        $checkbox.attr('data-synced', '1');
                        $checkbox.attr('data-product-id', response.data.target_product_id);
                        
                        currentIndex++;
                        processNextCreate();
                    } else {
                        showStatus(response.data.message || wpc_mpc_ajax.messages.error, 'error');
                        isProcessing = false;
                    }
                },
                error: function() {
                    showStatus(wpc_mpc_ajax.messages.error, 'error');
                    isProcessing = false;
                }
            });
        }

        // Process update queue
        function processNextUpdate() {
            if (currentIndex >= processQueue.length || !isProcessing) {
                // All done
                hideSpinner();
                if (currentIndex > 0) {
                    showStatus(sprintf('Successfully updated product on %d sites.', currentIndex), 'success');
                }
                return;
            }
            
            var site = processQueue[currentIndex];
            var progress = sprintf('Updating on site %d of %d...', currentIndex + 1, processQueue.length);
            showStatus(progress);
            
            $.ajax({
                url: wpc_mpc_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'wpc_mpc_update_product',
                    nonce: wpc_mpc_ajax.nonce,
                    product_id: wpc_mpc_ajax.product_id,
                    target_blog_id: site.blogId
                },
                success: function(response) {
                    if (response.success) {
                        currentIndex++;
                        processNextUpdate();
                    } else {
                        showStatus(response.data.message || wpc_mpc_ajax.messages.error, 'error');
                        isProcessing = false;
                    }
                },
                error: function() {
                    showStatus(wpc_mpc_ajax.messages.error, 'error');
                    isProcessing = false;
                }
            });
        }

        // Helper functions
        function showStatus(message, type) {
            $statusMessage.text(message);
            $status.show();
            $spinner.addClass('is-active');
            
            // Add status class
            $status.removeClass('notice-success notice-error');
            if (type === 'success') {
                $status.addClass('notice-success');
            } else if (type === 'error') {
                $status.addClass('notice-error');
            }
        }

        function hideSpinner() {
            $spinner.removeClass('is-active');
            
            // Hide status after 5 seconds for success messages
            if ($status.hasClass('notice-success')) {
                setTimeout(function() {
                    $status.fadeOut();
                }, 5000);
            }
        }
        
        // Helper sprintf function
        function sprintf(format) {
            var args = Array.prototype.slice.call(arguments, 1);
            return format.replace(/%[sd]/g, function(match) {
                var replacement = args.shift();
                return match === '%s' ? String(replacement) : Number(replacement);
            });
        }
        
        // Initialize button states
        updateButtonStates();
    });

})(jQuery);