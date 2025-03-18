<?php

declare(strict_types=1);
use PHPUnit\Framework\TestCase;

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . "/../");
}

if (!defined('WPINC')) {
    define('WPINC', 'wp-includes');
}

require_once __DIR__ . "/../pg4wp/db.php";

final class deleteQueryTest extends TestCase
{
    public function test_it_handles_delete_with_standard_prefix()
    {
        $sql = 'DELETE a, b FROM wp_options a, wp_options b WHERE a.option_name = "_transient_timeout_%"' . 
               ' AND b.option_name = CONCAT("_transient_", SUBSTRING(a.option_name, 20))' . 
               ' AND b.option_value < 1705586959';
               
        $expected = 'DELETE FROM wp_options a USING wp_options b WHERE ' . 
                  '(a.option_name = "_transient_timeout_%" AND b.option_name = CONCAT("_transient_", SUBSTRING(a.option_name, 20))' . 
                  ' AND b.option_value ~ \'^[0-9]+$\' AND CAST(b.option_value AS BIGINT) < 1705586959) OR ' . 
                  '(b.option_name = "_transient_timeout_%" AND a.option_name = CONCAT("_transient_", SUBSTRING(b.option_name, 20))' . 
                  ' AND a.option_value ~ \'^[0-9]+$\' AND CAST(a.option_value AS BIGINT) < 1705586959);';
                  
        $postgresql = pg4wp_rewrite($sql);
        $this->assertSame(trim($expected), trim($postgresql));
    }
    
    public function test_it_handles_delete_with_custom_prefix()
    {
        $sql = 'DELETE a, b FROM custom_options a, custom_options b WHERE a.option_name = "_transient_timeout_%"' . 
               ' AND b.option_name = CONCAT("_transient_", SUBSTRING(a.option_name, 20))' . 
               ' AND b.option_value < 1705586959';
                
        // Test with custom prefix
        global $wpdb;
        $original_prefix = $wpdb->prefix;
        $wpdb->prefix = 'custom_';
        
        $expected = 'DELETE FROM custom_options a USING custom_options b WHERE ' . 
                  '(a.option_name = "_transient_timeout_%" AND b.option_name = CONCAT("_transient_", SUBSTRING(a.option_name, 20))' . 
                  ' AND b.option_value ~ \'^[0-9]+$\' AND CAST(b.option_value AS BIGINT) < 1705586959) OR ' . 
                  '(b.option_name = "_transient_timeout_%" AND a.option_name = CONCAT("_transient_", SUBSTRING(b.option_name, 20))' . 
                  ' AND a.option_value ~ \'^[0-9]+$\' AND CAST(a.option_value AS BIGINT) < 1705586959);';
                  
        $postgresql = pg4wp_rewrite($sql);
        $this->assertSame(trim($expected), trim($postgresql));
        
        // Restore the original prefix
        $wpdb->prefix = $original_prefix;
    }
    
    public function test_it_handles_generic_delete_with_multiple_aliases()
    {
        $sql = 'DELETE a, b FROM custom_posts a, custom_postmeta b WHERE a.ID = b.post_id AND a.post_type = "revision"';
                
        // Test with custom prefix
        global $wpdb;
        $original_prefix = $wpdb->prefix;
        $wpdb->prefix = 'custom_';
        
        // For this test we need to ensure wpdb->posts and wpdb->postmeta exist
        $wpdb->posts = 'custom_posts';
        $wpdb->postmeta = 'custom_postmeta';
        
        $expected = 'DELETE FROM custom_posts a USING custom_postmeta b WHERE a."ID" = b.post_id AND a.post_type = "revision";';
                  
        $postgresql = pg4wp_rewrite($sql);
        $this->assertSame(trim($expected), trim($postgresql));
        
        // Restore the original prefix
        $wpdb->prefix = $original_prefix;
    }
    
    protected function setUp(): void
    {
        global $wpdb;
        $wpdb = new class () {
            public $categories = "wp_categories";
            public $comments = "wp_comments";
            public $prefix = "wp_";
            public $options = "wp_options";
            public $sitemeta = "wp_sitemeta";
        };
    }
}