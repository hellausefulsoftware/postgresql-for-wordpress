<?php

class DeleteSQLRewriter extends AbstractSQLRewriter
{
    public function rewrite(): string
    {
        global $wpdb;

        $sql = $this->original();

        // ORDER BY is not supported in DELETE queries, and not required
        // when LIMIT is not present
        if(false !== strpos($sql, 'ORDER BY') && false === strpos($sql, 'LIMIT')) {
            $pattern = '/ORDER BY \S+ (ASC|DESC)?/';
            $sql = preg_replace($pattern, '', $sql);
        }

        // LIMIT is not allowed in DELETE queries
        $sql = str_replace('LIMIT 1', '', $sql);
        $sql = str_replace(' REGEXP ', ' ~ ', $sql);

        // This handles removal of duplicate entries in table options
        if(false !== strpos($sql, 'DELETE o1 FROM ')) {
            $sql = "DELETE FROM $wpdb->options WHERE option_id IN " .
                "(SELECT o1.option_id FROM $wpdb->options AS o1, $wpdb->options AS o2 " .
                "WHERE o1.option_name = o2.option_name " .
                "AND o1.option_id < o2.option_id)";
        }
        // Rewrite _transient_timeout multi-table delete query for options table
        elseif(0 === strpos($sql, "DELETE a, b FROM {$wpdb->prefix}options a, {$wpdb->prefix}options b") || 
               0 === strpos($sql, "DELETE a, b FROM wp_options a, wp_options b")) {
            $where = substr($sql, strpos($sql, 'WHERE ') + 6);
            $where = rtrim($where, " \t\n\r;");
            // Fix string/number comparison by adding check and cast
            $where = str_replace('AND b.option_value', 'AND b.option_value ~ \'^[0-9]+$\' AND CAST(b.option_value AS BIGINT)', $where);
            // Mirror WHERE clause to delete both sides of self-join.
            $where2 = strtr($where, array('a.' => 'b.', 'b.' => 'a.'));
            $sql = "DELETE FROM {$wpdb->options} a USING {$wpdb->options} b WHERE " .
                '(' . $where . ') OR (' . $where2 . ');';
        }

        // Rewrite _transient_timeout multi-table delete query for sitemeta table
        elseif(0 === strpos($sql, "DELETE a, b FROM {$wpdb->prefix}sitemeta a, {$wpdb->prefix}sitemeta b") || 
               0 === strpos($sql, "DELETE a, b FROM wp_sitemeta a, wp_sitemeta b")) {
            $where = substr($sql, strpos($sql, 'WHERE ') + 6);
            $where = rtrim($where, " \t\n\r;");
            // Fix string/number comparison by adding check and cast
            $where = str_replace('AND b.meta_value', 'AND b.meta_value ~ \'^[0-9]+$\' AND CAST(b.meta_value AS BIGINT)', $where);
            // Mirror WHERE clause to delete both sides of self-join.
            $where2 = strtr($where, array('a.' => 'b.', 'b.' => 'a.'));
            // Use $wpdb->sitemeta if it exists, otherwise use prefix
            $sitemeta_table = isset($wpdb->sitemeta) ? $wpdb->sitemeta : "{$wpdb->prefix}sitemeta";
            $sql = "DELETE FROM {$sitemeta_table} a USING {$sitemeta_table} b WHERE " .
                '(' . $where . ') OR (' . $where2 . ');';
        }
        
        // Generic handler for DELETE queries with multiple aliases and table prefixes
        elseif(preg_match('/^DELETE\s+([a-zA-Z0-9_]+),\s*([a-zA-Z0-9_]+)\s+FROM\s+([a-zA-Z0-9_]+)([a-zA-Z0-9_]+)\s+([a-zA-Z0-9_]+),\s*([a-zA-Z0-9_]+)([a-zA-Z0-9_]+)\s+([a-zA-Z0-9_]+)/i', $sql, $matches)) {
            $alias1 = $matches[1];
            $alias2 = $matches[2];
            $prefix = $matches[3]; // This should be wp_ or the custom prefix
            $table1 = $matches[4];
            $tableAlias1 = $matches[5];
            $prefix2 = $matches[6]; // Should match prefix
            $table2 = $matches[7];
            $tableAlias2 = $matches[8];
            
            // Extract WHERE clause
            $where = substr($sql, strpos($sql, 'WHERE ') + 6);
            $where = rtrim($where, " \t\n\r;");
            
            // Use proper table names from $wpdb if available, otherwise use prefix
            $table1_name = isset($wpdb->$table1) ? $wpdb->$table1 : "{$wpdb->prefix}$table1";
            $table2_name = isset($wpdb->$table2) ? $wpdb->$table2 : "{$wpdb->prefix}$table2";
            
            // Build the PostgreSQL-compatible query with aliases
            if ($table1 === $table2) {
                // Same table case (like options a, options b)
                $sql = "DELETE FROM $table1_name $tableAlias1 USING $table1_name $tableAlias2 WHERE $where;";
            } else {
                // Different tables case
                $sql = "DELETE FROM $table1_name $tableAlias1 USING $table2_name $tableAlias2 WHERE $where;";
            }
        }

        // Akismet sometimes doesn't write 'comment_ID' with 'ID' in capitals where needed ...
        if(false !== strpos($sql, $wpdb->comments)) {
            $sql = str_replace(' comment_id ', ' comment_ID ', $sql);
        }

        return $sql;
    }
}
