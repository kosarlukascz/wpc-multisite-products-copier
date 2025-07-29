/**
 * Product Mapping Dashboard JavaScript
 */
(function($) {
    'use strict';

    var WPCProductMapping = {
        currentPage: 1,
        searchTerm: '',
        syncFilter: '',
        categoryFilter: '',
        isLoading: false,

        init: function() {
            this.bindEvents();
            this.loadProductMap();
        },

        bindEvents: function() {
            var self = this;

            // Search input with debounce
            var searchTimeout;
            $('#wpc-product-search').on('input', function() {
                clearTimeout(searchTimeout);
                var value = $(this).val();
                searchTimeout = setTimeout(function() {
                    self.searchTerm = value;
                    self.currentPage = 1;
                    self.loadProductMap();
                }, 500);
            });

            // Filter changes
            $('#wpc-sync-filter, #wpc-category-filter').on('change', function() {
                self.syncFilter = $('#wpc-sync-filter').val();
                self.categoryFilter = $('#wpc-category-filter').val();
                self.currentPage = 1;
                self.loadProductMap();
            });

            // Refresh button
            $('#wpc-refresh-map').on('click', function() {
                self.loadProductMap();
            });

            // Export button
            $('#wpc-export-map').on('click', function() {
                self.exportMapping();
            });

            // Modal close
            $('.wpc-modal-close, .wpc-modal').on('click', function(e) {
                if (e.target === this) {
                    $('#wpc-product-details-modal').fadeOut(200);
                }
            });

            // Sync actions delegation
            $(document).on('click', '.wpc-sync-action', function(e) {
                e.preventDefault();
                var $button = $(this);
                var sourceProductId = $button.data('source-product');
                var targetBlogId = $button.data('target-blog');
                var action = $button.data('action');

                if (confirm(wpc_mapping.strings.confirm_sync)) {
                    self.syncProduct(sourceProductId, targetBlogId, action, $button);
                }
            });

            // View product details
            $(document).on('click', '.wpc-view-details', function(e) {
                e.preventDefault();
                var productId = $(this).data('product-id');
                var productName = $(this).data('product-name');
                self.showProductDetails(productId, productName);
            });

            // Pagination
            $(document).on('click', '.wpc-pagination a', function(e) {
                e.preventDefault();
                var page = $(this).data('page');
                if (page && page !== self.currentPage) {
                    self.currentPage = page;
                    self.loadProductMap();
                }
            });
        },

        loadProductMap: function() {
            if (this.isLoading) return;

            var self = this;
            this.isLoading = true;

            $('#wpc-mapping-container').html(
                '<div class="wpc-loading">' +
                '<span class="spinner is-active"></span>' +
                '<p>' + wpc_mapping.strings.loading + '</p>' +
                '</div>'
            );

            var data = {
                action: 'wpc_get_product_mapping',
                nonce: wpc_mapping.nonce,
                page: this.currentPage,
                search: this.searchTerm,
                sync_filter: this.syncFilter,
                category: this.categoryFilter
            };

            $.post(wpc_mapping.ajax_url, data, function(response) {
                if (response.success) {
                    self.renderProductMap(response.data);
                } else {
                    self.showError(response.data || wpc_mapping.strings.error);
                }
            }).fail(function() {
                self.showError(wpc_mapping.strings.error);
            }).always(function() {
                self.isLoading = false;
            });
        },

        renderProductMap: function(data) {
            if (!data.products || data.products.length === 0) {
                $('#wpc-mapping-container').html(
                    '<div class="wpc-no-products">' +
                    '<p>' + wpc_mapping.strings.no_products + '</p>' +
                    '</div>'
                );
                return;
            }

            var html = '<div class="wpc-mapping-table-wrapper">';
            html += '<table class="wpc-mapping-table wp-list-table widefat fixed striped">';
            
            // Header
            html += '<thead><tr>';
            html += '<th class="wpc-product-column">' + 'Product' + '</th>';
            
            data.sites.forEach(function(site) {
                var isSource = site.id == wpc_mapping.source_blog_id;
                html += '<th class="wpc-site-column' + (isSource ? ' wpc-source-site' : '') + '">';
                html += site.name;
                if (isSource) {
                    html += ' <span class="wpc-source-badge">Source</span>';
                }
                html += '</th>';
            });
            
            html += '</tr></thead>';
            
            // Body
            html += '<tbody>';
            
            data.products.forEach(function(product) {
                html += '<tr>';
                
                // Product info cell
                html += '<td class="wpc-product-info">';
                html += '<strong>' + self.escapeHtml(product.name) + '</strong>';
                if (product.sku) {
                    html += '<br><small>SKU: ' + self.escapeHtml(product.sku) + '</small>';
                }
                if (product.categories && product.categories.length > 0) {
                    html += '<br><small>Categories: ' + self.escapeHtml(product.categories.join(', ')) + '</small>';
                }
                html += '<br><a href="#" class="wpc-view-details" data-product-id="' + product.id + '" data-product-name="' + self.escapeHtml(product.name) + '">View Details</a>';
                html += '</td>';
                
                // Site status cells
                data.sites.forEach(function(site) {
                    var status = product.site_status[site.id];
                    var isSource = site.id == wpc_mapping.source_blog_id;
                    
                    html += '<td class="wpc-site-status' + (isSource ? ' wpc-source-site' : '') + '">';
                    
                    if (isSource) {
                        // Source site
                        html += '<div class="wpc-status-indicator wpc-status-source">';
                        html += '<span class="wpc-status-dot wpc-status-source"></span>';
                        html += '<a href="' + status.edit_url + '" target="_blank">Edit</a>';
                        html += '</div>';
                    } else if (status.exists) {
                        // Product exists on target site
                        var statusClass = status.is_outdated ? 'wpc-status-outdated' : 'wpc-status-synced';
                        html += '<div class="wpc-status-indicator ' + statusClass + '">';
                        html += '<span class="wpc-status-dot ' + statusClass + '"></span>';
                        
                        if (status.is_outdated) {
                            html += '<button class="button button-small wpc-sync-action" ';
                            html += 'data-source-product="' + product.id + '" ';
                            html += 'data-target-blog="' + site.id + '" ';
                            html += 'data-action="update">';
                            html += wpc_mapping.strings.update;
                            html += '</button>';
                        } else {
                            html += '<a href="' + status.edit_url + '" target="_blank" class="button button-small">' + wpc_mapping.strings.view + '</a>';
                        }
                        
                        html += '</div>';
                    } else {
                        // Product doesn't exist
                        html += '<div class="wpc-status-indicator wpc-status-not-exists">';
                        html += '<span class="wpc-status-dot wpc-status-not-exists"></span>';
                        html += '<button class="button button-small button-primary wpc-sync-action" ';
                        html += 'data-source-product="' + product.id + '" ';
                        html += 'data-target-blog="' + site.id + '" ';
                        html += 'data-action="create">';
                        html += wpc_mapping.strings.create;
                        html += '</button>';
                        html += '</div>';
                    }
                    
                    html += '</td>';
                });
                
                html += '</tr>';
            });
            
            html += '</tbody></table></div>';
            
            // Pagination
            if (data.pages > 1) {
                html += '<div class="wpc-pagination">';
                html += this.renderPagination(data.current_page, data.pages);
                html += '</div>';
            }

            $('#wpc-mapping-container').html(html);
        },

        renderPagination: function(currentPage, totalPages) {
            var html = '<div class="tablenav"><div class="tablenav-pages">';
            
            // Previous
            if (currentPage > 1) {
                html += '<a href="#" class="prev-page" data-page="' + (currentPage - 1) + '">‹</a> ';
            }
            
            // Page numbers
            var startPage = Math.max(1, currentPage - 2);
            var endPage = Math.min(totalPages, currentPage + 2);
            
            if (startPage > 1) {
                html += '<a href="#" data-page="1">1</a> ';
                if (startPage > 2) html += '... ';
            }
            
            for (var i = startPage; i <= endPage; i++) {
                if (i === currentPage) {
                    html += '<span class="current">' + i + '</span> ';
                } else {
                    html += '<a href="#" data-page="' + i + '">' + i + '</a> ';
                }
            }
            
            if (endPage < totalPages) {
                if (endPage < totalPages - 1) html += '... ';
                html += '<a href="#" data-page="' + totalPages + '">' + totalPages + '</a> ';
            }
            
            // Next
            if (currentPage < totalPages) {
                html += '<a href="#" class="next-page" data-page="' + (currentPage + 1) + '">›</a>';
            }
            
            html += '</div></div>';
            return html;
        },

        syncProduct: function(sourceProductId, targetBlogId, action, $button) {
            var self = this;
            var originalText = $button.text();
            
            $button.prop('disabled', true).text(wpc_mapping.strings.checking);

            var data = {
                action: 'wpc_sync_product',
                nonce: wpc_mapping.nonce,
                source_product_id: sourceProductId,
                target_blog_id: targetBlogId,
                sync_action: action
            };

            $.post(wpc_mapping.ajax_url, data, function(response) {
                if (response.success) {
                    self.showNotice(wpc_mapping.strings.sync_success, 'success');
                    // Reload the map to show updated status
                    self.loadProductMap();
                } else {
                    self.showNotice(response.data || wpc_mapping.strings.sync_error, 'error');
                    $button.prop('disabled', false).text(originalText);
                }
            }).fail(function() {
                self.showNotice(wpc_mapping.strings.sync_error, 'error');
                $button.prop('disabled', false).text(originalText);
            });
        },

        showProductDetails: function(productId, productName) {
            var modal = $('#wpc-product-details-modal');
            var modalTitle = $('#wpc-modal-title');
            var modalBody = $('#wpc-modal-body');
            
            modalTitle.text(productName);
            modalBody.html('<div class="spinner is-active"></div>');
            modal.fadeIn(200);

            // In a real implementation, you would load product details via AJAX
            // For now, we'll just show the sync status across all sites
            var html = '<h3>Sync Status Across Network</h3>';
            html += '<p>Product ID: ' + productId + '</p>';
            html += '<p><em>Additional product details would be loaded here...</em></p>';
            
            setTimeout(function() {
                modalBody.html(html);
            }, 500);
        },

        exportMapping: function() {
            var self = this;
            
            var data = {
                action: 'wpc_export_mapping',
                nonce: wpc_mapping.nonce
            };

            $.post(wpc_mapping.ajax_url, data, function(response) {
                if (response.success) {
                    // Create download link
                    var blob = new Blob([atob(response.data.content)], { type: response.data.mime_type });
                    var url = window.URL.createObjectURL(blob);
                    var a = document.createElement('a');
                    a.href = url;
                    a.download = response.data.filename;
                    document.body.appendChild(a);
                    a.click();
                    window.URL.revokeObjectURL(url);
                    document.body.removeChild(a);
                    
                    self.showNotice(wpc_mapping.strings.export_success, 'success');
                } else {
                    self.showNotice(response.data || 'Export failed', 'error');
                }
            }).fail(function() {
                self.showNotice('Export failed', 'error');
            });
        },

        showNotice: function(message, type) {
            var noticeClass = type === 'success' ? 'notice-success' : 'notice-error';
            var notice = $('<div class="notice ' + noticeClass + ' is-dismissible"><p>' + message + '</p></div>');
            
            $('.wrap > h1').after(notice);
            
            // Auto dismiss after 5 seconds
            setTimeout(function() {
                notice.fadeOut(function() {
                    $(this).remove();
                });
            }, 5000);
        },

        showError: function(message) {
            $('#wpc-mapping-container').html(
                '<div class="wpc-error">' +
                '<p>' + message + '</p>' +
                '</div>'
            );
        },

        escapeHtml: function(text) {
            var map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return text.replace(/[&<>"']/g, function(m) { return map[m]; });
        }
    };

    // Initialize on document ready
    $(document).ready(function() {
        if ($('#wpc-mapping-container').length) {
            WPCProductMapping.init();
        }
    });

})(jQuery);