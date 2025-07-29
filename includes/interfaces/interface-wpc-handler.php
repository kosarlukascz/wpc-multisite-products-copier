<?php
/**
 * Handler Interface
 *
 * @package WPC_Multisite_Products_Copier
 * @since 2.0.0
 */

namespace WPC\Interfaces;

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Interface for all handler classes
 */
interface WPC_Handler_Interface {
    
    /**
     * Initialize the handler
     *
     * @return void
     */
    public function init(): void;
    
    /**
     * Check if handler is available
     *
     * @return bool
     */
    public function is_available(): bool;
    
    /**
     * Get handler name
     *
     * @return string
     */
    public function get_name(): string;
    
    /**
     * Get handler version
     *
     * @return string
     */
    public function get_version(): string;
}