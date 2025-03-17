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

final class deleteSqlTest extends TestCase
{
    public function test_delete_multi_table_query_with_default_prefix()
    {
        $sql = 'DELETE a, b FROM wp_options a, wp_options b WHERE a.option_name LIKE "_transient_%" AND a.option_name = CONCAT("_transient_", SUBSTRING(b.option_name, 12)) AND b.option_name LIKE "_transient_timeout_%" AND b.option_value < 1684012345';
        $expected = 'DELETE FROM wp_options a USING wp_options b WHERE (a.option_name LIKE \'_transient_%\' AND a.option_name = CONCAT(\'_transient_\', SUBSTRING(b.option_name, 12)) AND b.option_name LIKE \'_transient_timeout_%\' AND b.option_value ~ \'^[0-9]+$\' AND CAST(b.option_value AS BIGINT) < 1684012345) OR (b.option_name LIKE \'_transient_%\' AND b.option_name = CONCAT(\'_transient_\', SUBSTRING(a.option_name, 12)) AND a.option_name LIKE \'_transient_timeout_%\' AND a.option_value ~ \'^[0-9]+$\' AND CAST(a.option_value AS BIGINT) < 1684012345);';
        $postgresql = pg4wp_rewrite($sql);
        $this->assertSame(trim($expected), trim($postgresql));
    }

    public function test_delete_multi_table_query_with_custom_prefix()
    {
        $sql = 'DELETE a, b FROM gwp_options a, gwp_options b WHERE a.option_name LIKE "_transient_%" AND a.option_name = CONCAT("_transient_", SUBSTRING(b.option_name, 12)) AND b.option_name LIKE "_transient_timeout_%" AND b.option_value < 1684012345';
        $expected = 'DELETE FROM gwp_options a USING gwp_options b WHERE (a.option_name LIKE \'_transient_%\' AND a.option_name = CONCAT(\'_transient_\', SUBSTRING(b.option_name, 12)) AND b.option_name LIKE \'_transient_timeout_%\' AND b.option_value ~ \'^[0-9]+$\' AND CAST(b.option_value AS BIGINT) < 1684012345) OR (b.option_name LIKE \'_transient_%\' AND b.option_name = CONCAT(\'_transient_\', SUBSTRING(a.option_name, 12)) AND a.option_name LIKE \'_transient_timeout_%\' AND a.option_value ~ \'^[0-9]+$\' AND CAST(a.option_value AS BIGINT) < 1684012345);';
        
        // Set a custom prefix for testing
        global $wpdb;
        $originalPrefix = $wpdb->prefix;
        $wpdb->prefix = 'gwp_';
        
        $postgresql = pg4wp_rewrite($sql);
        
        // Restore the original prefix
        $wpdb->prefix = $originalPrefix;
        
        $this->assertSame(trim($expected), trim($postgresql));
    }

    public function test_delete_sitemeta_multi_table_query_with_custom_prefix()
    {
        $sql = 'DELETE a, b FROM gwp_sitemeta a, gwp_sitemeta b WHERE a.meta_key LIKE "_transient_%" AND a.meta_key = CONCAT("_transient_", SUBSTRING(b.meta_key, 12)) AND b.meta_key LIKE "_transient_timeout_%" AND b.meta_value < 1684012345';
        $expected = 'DELETE FROM gwp_sitemeta a USING gwp_sitemeta b WHERE (a.meta_key LIKE \'_transient_%\' AND a.meta_key = CONCAT(\'_transient_\', SUBSTRING(b.meta_key, 12)) AND b.meta_key LIKE \'_transient_timeout_%\' AND b.meta_value ~ \'^[0-9]+$\' AND CAST(b.meta_value AS BIGINT) < 1684012345) OR (b.meta_key LIKE \'_transient_%\' AND b.meta_key = CONCAT(\'_transient_\', SUBSTRING(a.meta_key, 12)) AND a.meta_key LIKE \'_transient_timeout_%\' AND a.meta_value ~ \'^[0-9]+$\' AND CAST(a.meta_value AS BIGINT) < 1684012345);';
        
        // Set a custom prefix for testing
        global $wpdb;
        $originalPrefix = $wpdb->prefix;
        $wpdb->prefix = 'gwp_';
        
        $postgresql = pg4wp_rewrite($sql);
        
        // Restore the original prefix
        $wpdb->prefix = $originalPrefix;
        
        $this->assertSame(trim($expected), trim($postgresql));
    }

    protected function setUp(): void
    {
        global $wpdb;
        $wpdb = new class () {
            public $options = "wp_options";
            public $sitemeta = "wp_sitemeta";
            public $prefix = "wp_";
        };
    }
}