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
        var $siteSelect = $('#wpc_mpc_target_site');
        var $createBtn = $('#wpc_mpc_create');
        var $updateBtn = $('#wpc_mpc_update');
        var $status = $('.wpc-mpc-status');
        var $statusMessage = $status.find('.status-message');
        var $spinner = $status.find('.spinner');

        // Handle site selection change
        $siteSelect.on('change', function() {
            var selectedOption = $(this).find('option:selected');
            var blogId = $(this).val();
            var isSynced = selectedOption.data('synced') === 1;
            var productId = selectedOption.data('product-id');

            if (blogId) {
                // Enable/disable buttons based on sync status
                if (isSynced && productId) {
                    $createBtn.prop('disabled', true);
                    $updateBtn.prop('disabled', false);
                } else {
                    $createBtn.prop('disabled', false);
                    $updateBtn.prop('disabled', true);
                }
            } else {
                // No site selected
                $createBtn.prop('disabled', true);
                $updateBtn.prop('disabled', true);
            }
        });

        // Handle create button click
        $createBtn.on('click', function() {
            var blogId = $siteSelect.val();
            
            if (!blogId) {
                alert(wpc_mpc_ajax.messages.select_site);
                return;
            }

            // Show status
            showStatus(wpc_mpc_ajax.messages.creating);

            // Make AJAX request
            $.ajax({
                url: wpc_mpc_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'wpc_mpc_create_product',
                    nonce: wpc_mpc_ajax.nonce,
                    product_id: wpc_mpc_ajax.product_id,
                    target_blog_id: blogId
                },
                success: function(response) {
                    if (response.success) {
                        showStatus(response.data.message, 'success');
                        
                        // Update the select option to reflect sync status
                        var $option = $siteSelect.find('option[value="' + blogId + '"]');
                        $option.attr('data-synced', '1');
                        $option.attr('data-product-id', response.data.target_product_id);
                        
                        // Update button states
                        $createBtn.prop('disabled', true);
                        $updateBtn.prop('disabled', false);
                        
                        // Reload the page after 2 seconds to show updated sync info
                        setTimeout(function() {
                            location.reload();
                        }, 2000);
                    } else {
                        showStatus(response.data.message || wpc_mpc_ajax.messages.error, 'error');
                    }
                },
                error: function() {
                    showStatus(wpc_mpc_ajax.messages.error, 'error');
                },
                complete: function() {
                    hideSpinner();
                }
            });
        });

        // Handle update button click
        $updateBtn.on('click', function() {
            var blogId = $siteSelect.val();
            
            if (!blogId) {
                alert(wpc_mpc_ajax.messages.select_site);
                return;
            }

            // Show status
            showStatus(wpc_mpc_ajax.messages.updating);

            // Make AJAX request
            $.ajax({
                url: wpc_mpc_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'wpc_mpc_update_product',
                    nonce: wpc_mpc_ajax.nonce,
                    product_id: wpc_mpc_ajax.product_id,
                    target_blog_id: blogId
                },
                success: function(response) {
                    if (response.success) {
                        showStatus(response.data.message, 'success');
                    } else {
                        showStatus(response.data.message || wpc_mpc_ajax.messages.error, 'error');
                    }
                },
                error: function() {
                    showStatus(wpc_mpc_ajax.messages.error, 'error');
                },
                complete: function() {
                    hideSpinner();
                }
            });
        });

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
    });

})(jQuery);