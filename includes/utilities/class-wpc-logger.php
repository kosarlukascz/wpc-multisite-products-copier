<?php
/**
 * Logger Class
 *
 * @package WPC_Multisite_Products_Copier
 * @since 2.0.0
 */

namespace WPC\Utilities;

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Logger utility class
 */
class WPC_Logger {
    
    /**
     * Enable debug logging
     *
     * @var bool
     */
    private bool $debug_enabled;
    
    /**
     * Log file path
     *
     * @var string
     */
    private string $log_file = '';
    
    /**
     * Log directory
     *
     * @var string
     */
    private string $log_dir = '';
    
    /**
     * Maximum log file size in bytes (5MB)
     *
     * @var int
     */
    private int $max_file_size = 5242880;
    
    /**
     * Constructor
     *
     * @param bool $debug_enabled Enable debug logging
     */
    public function __construct(bool $debug_enabled = false) {
        $this->debug_enabled = $debug_enabled;
        $this->init();
    }
    
    /**
     * Initialize logging
     *
     * @return void
     */
    private function init(): void {
        if (!$this->debug_enabled) {
            return;
        }
        
        $upload_dir = wp_upload_dir();
        $this->log_dir = $upload_dir['basedir'] . '/wpc-mpc-logs';
        
        // Create log directory if it doesn't exist
        if (!file_exists($this->log_dir)) {
            wp_mkdir_p($this->log_dir);
            
            // Add .htaccess to protect logs
            $htaccess = $this->log_dir . '/.htaccess';
            if (!file_exists($htaccess)) {
                file_put_contents($htaccess, 'Deny from all');
            }
        }
        
        // Set log file path with date
        $this->log_file = $this->log_dir . '/debug-' . date('Y-m-d') . '.log';
        
        // Rotate log if too large
        $this->rotate_log_if_needed();
    }
    
    /**
     * Log a message
     *
     * @param string $message The message to log
     * @param mixed $data Optional data to log
     * @param string $level Log level (info, warning, error)
     * @return void
     */
    public function log(string $message, $data = null, string $level = 'info'): void {
        if (!$this->debug_enabled || empty($this->log_file)) {
            return;
        }
        
        $timestamp = date('Y-m-d H:i:s');
        $blog_id = get_current_blog_id();
        $log_entry = "[{$timestamp}] [{$level}] [Blog {$blog_id}] {$message}";
        
        if ($data !== null) {
            $log_entry .= "\nData: " . print_r($data, true);
        }
        
        $log_entry .= "\n" . str_repeat('-', 80) . "\n";
        
        // Write to log file
        file_put_contents($this->log_file, $log_entry, FILE_APPEND | LOCK_EX);
    }
    
    /**
     * Log an error
     *
     * @param string $message Error message
     * @param mixed $data Additional data
     * @return void
     */
    public function error(string $message, $data = null): void {
        $this->log($message, $data, 'error');
    }
    
    /**
     * Log a warning
     *
     * @param string $message Warning message
     * @param mixed $data Additional data
     * @return void
     */
    public function warning(string $message, $data = null): void {
        $this->log($message, $data, 'warning');
    }
    
    /**
     * Log info
     *
     * @param string $message Info message
     * @param mixed $data Additional data
     * @return void
     */
    public function info(string $message, $data = null): void {
        $this->log($message, $data, 'info');
    }
    
    /**
     * Rotate log file if it's too large
     *
     * @return void
     */
    private function rotate_log_if_needed(): void {
        if (!file_exists($this->log_file)) {
            return;
        }
        
        if (filesize($this->log_file) > $this->max_file_size) {
            $archive_file = $this->log_dir . '/debug-' . date('Y-m-d-His') . '.log';
            rename($this->log_file, $archive_file);
            
            // Keep only last 10 log files
            $this->cleanup_old_logs();
        }
    }
    
    /**
     * Clean up old log files
     *
     * @return void
     */
    private function cleanup_old_logs(): void {
        $files = glob($this->log_dir . '/debug-*.log');
        
        if (count($files) > 10) {
            // Sort by modification time
            usort($files, function($a, $b) {
                return filemtime($a) - filemtime($b);
            });
            
            // Remove oldest files
            $files_to_remove = array_slice($files, 0, count($files) - 10);
            foreach ($files_to_remove as $file) {
                unlink($file);
            }
        }
    }
    
    /**
     * Enable debug logging
     *
     * @return void
     */
    public function enable(): void {
        $this->debug_enabled = true;
        $this->init();
    }
    
    /**
     * Disable debug logging
     *
     * @return void
     */
    public function disable(): void {
        $this->debug_enabled = false;
    }
    
    /**
     * Check if debug is enabled
     *
     * @return bool
     */
    public function is_enabled(): bool {
        return $this->debug_enabled;
    }
    
    /**
     * Get log directory
     *
     * @return string
     */
    public function get_log_dir(): string {
        return $this->log_dir;
    }
}