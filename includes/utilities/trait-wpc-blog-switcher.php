<?php
/**
 * Blog Switcher Trait
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
 * Trait for handling blog switching operations
 */
trait WPC_Blog_Switcher {
    
    /**
     * Switch to a blog and execute a callback
     *
     * @param int $blog_id Blog ID to switch to
     * @param callable $callback Callback to execute
     * @param mixed ...$args Arguments to pass to callback
     * @return mixed Callback return value
     */
    protected function switch_to_blog_and_run(int $blog_id, callable $callback, ...$args) {
        $current_blog_id = get_current_blog_id();
        $switched = false;
        
        try {
            if ($current_blog_id !== $blog_id) {
                switch_to_blog($blog_id);
                $switched = true;
            }
            
            return $callback(...$args);
            
        } finally {
            if ($switched) {
                restore_current_blog();
            }
        }
    }
    
    /**
     * Ensure we're on the correct blog
     *
     * @param int $blog_id Expected blog ID
     * @return bool True if switched, false if already on correct blog
     */
    protected function ensure_blog_context(int $blog_id): bool {
        if (get_current_blog_id() !== $blog_id) {
            switch_to_blog($blog_id);
            return true;
        }
        return false;
    }
    
    /**
     * Get data from a specific blog
     *
     * @param int $blog_id Blog ID
     * @param callable $data_getter Callback to get data
     * @return mixed Data from callback
     */
    protected function get_from_blog(int $blog_id, callable $data_getter) {
        return $this->switch_to_blog_and_run($blog_id, $data_getter);
    }
    
    /**
     * Set data on a specific blog
     *
     * @param int $blog_id Blog ID
     * @param callable $data_setter Callback to set data
     * @return mixed Result from callback
     */
    protected function set_on_blog(int $blog_id, callable $data_setter) {
        return $this->switch_to_blog_and_run($blog_id, $data_setter);
    }
}