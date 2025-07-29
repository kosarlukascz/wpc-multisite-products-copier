<?php
/**
 * Abstract Integration Class
 *
 * @package WPC_Multisite_Products_Copier
 * @since 2.0.0
 */

namespace WPC\Integrations;

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Abstract class for third-party integrations
 */
abstract class WPC_Integration_Abstract {
    
    /**
     * Integration name
     *
     * @var string
     */
    protected string $name = '';
    
    /**
     * Integration version
     *
     * @var string
     */
    protected string $version = '1.0.0';
    
    /**
     * Logger instance
     *
     * @var \WPC\Utilities\WPC_Logger|null
     */
    protected $logger = null;
    
    /**
     * Constructor
     *
     * @param \WPC\Utilities\WPC_Logger|null $logger Logger instance
     */
    public function __construct($logger = null) {
        $this->logger = $logger;
        $this->init();
    }
    
    /**
     * Initialize the integration
     *
     * @return void
     */
    abstract protected function init(): void;
    
    /**
     * Check if integration is available
     *
     * @return bool
     */
    abstract public function is_available(): bool;
    
    /**
     * Handle product creation
     *
     * @param int $source_product_id Source product ID
     * @param int $target_product_id Target product ID
     * @param int $source_blog_id Source blog ID
     * @param int $target_blog_id Target blog ID
     * @param array $context Additional context data
     * @return bool Success status
     */
    abstract public function handle_product_create(
        int $source_product_id,
        int $target_product_id,
        int $source_blog_id,
        int $target_blog_id,
        array $context = []
    ): bool;
    
    /**
     * Handle product update
     *
     * @param int $source_product_id Source product ID
     * @param int $target_product_id Target product ID
     * @param int $source_blog_id Source blog ID
     * @param int $target_blog_id Target blog ID
     * @param array $context Additional context data
     * @return bool Success status
     */
    abstract public function handle_product_update(
        int $source_product_id,
        int $target_product_id,
        int $source_blog_id,
        int $target_blog_id,
        array $context = []
    ): bool;
    
    /**
     * Get integration name
     *
     * @return string
     */
    public function get_name(): string {
        return $this->name;
    }
    
    /**
     * Get integration version
     *
     * @return string
     */
    public function get_version(): string {
        return $this->version;
    }
    
    /**
     * Log a message
     *
     * @param string $message Message to log
     * @param array $data Additional data
     * @param string $level Log level
     * @return void
     */
    protected function log(string $message, array $data = [], string $level = 'info'): void {
        if ($this->logger) {
            $this->logger->log("[{$this->name}] {$message}", $data, $level);
        }
    }
}