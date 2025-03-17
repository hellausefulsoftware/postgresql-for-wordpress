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
        // Rewrite _transient_timeout multi-table delete query
        elseif(preg_match('/^DELETE a, b FROM .*?options a, .*?options b/', $sql)) {
            $where = substr($sql, strpos($sql, 'WHERE ') + 6);
            $where = rtrim($where, " \t\n\r;");
            // Fix string/number comparison by adding check and cast
            $where = str_replace('AND b.option_value', 'AND b.option_value ~ \'^[0-9]+$\' AND CAST(b.option_value AS BIGINT)', $where);
            // Mirror WHERE clause to delete both sides of self-join.
            $where2 = strtr($where, array('a.' => 'b.', 'b.' => 'a.'));
            $sql = "DELETE FROM $wpdb->options a USING $wpdb->options b WHERE " .
                '(' . $where . ') OR (' . $where2 . ');';
        }

        // Rewrite _transient_timeout multi-table delete query
        elseif(preg_match('/^DELETE a, b FROM .*?sitemeta a, .*?sitemeta b/', $sql)) {
            $where = substr($sql, strpos($sql, 'WHERE ') + 6);
            $where = rtrim($where, " \t\n\r;");
            // Fix string/number comparison by adding check and cast
            $where = str_replace('AND b.meta_value', 'AND b.meta_value ~ \'^[0-9]+$\' AND CAST(b.meta_value AS BIGINT)', $where);
            // Mirror WHERE clause to delete both sides of self-join.
            $where2 = strtr($where, array('a.' => 'b.', 'b.' => 'a.'));
            $sql = "DELETE FROM $wpdb->sitemeta a USING $wpdb->sitemeta b WHERE " .
                '(' . $where . ') OR (' . $where2 . ');';
        }
        
        // General handler for multi-table DELETE with aliases a, b
        // Example: DELETE a, b FROM wp_posts a JOIN wp_postmeta b ON a.ID = b.post_id WHERE ...
        elseif(preg_match('/^DELETE\s+a,\s*b\s+FROM\s+([^\s,]+)\s+a\s+(?:JOIN|,)\s+([^\s,]+)\s+b\s+/i', $sql, $matches)) {
            $table_a = $matches[1];
            $table_b = $matches[2];
            
            // Remove backticks from table names if they exist
            $table_a = str_replace('`', '', $table_a);
            $table_b = str_replace('`', '', $table_b);
            
            // Extract the WHERE clause
            $where = '';
            if (preg_match('/\s+WHERE\s+(.+)$/i', $sql, $where_matches)) {
                $where = $where_matches[1];
                $where = rtrim($where, " \t\n\r;");
            }
            
            // If it's a JOIN with ON clause, extract that too
            $on_clause = '';
            if (preg_match('/\s+ON\s+([^WHERE]+)(?:\s+WHERE\s+|$)/i', $sql, $on_matches)) {
                $on_clause = $on_matches[1];
                $on_clause = rtrim($on_clause, " \t\n\r;");
                
                // If we have both ON and WHERE, combine them
                if (!empty($where)) {
                    $where = "$on_clause AND $where";
                } else {
                    $where = $on_clause;
                }
            }
            
            // Rewrite to PostgreSQL compatible DELETE...USING syntax
            $sql = "DELETE FROM $table_a a USING $table_b b WHERE $where;";
        }

        // Akismet sometimes doesn't write 'comment_ID' with 'ID' in capitals where needed ...
        if(false !== strpos($sql, $wpdb->comments)) {
            $sql = str_replace(' comment_id ', ' comment_ID ', $sql);
        }

        return $sql;
    }
}
