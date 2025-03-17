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
        // Rewrite _transient_timeout multi-table delete query for wp_options
        elseif(preg_match('/DELETE a, b FROM (\w+_options) a, \1 b/i', $sql, $matches)) {
            $table_name = $matches[1]; // Captured table name with prefix
            $where = substr($sql, strpos($sql, 'WHERE ') + 6);
            $where = rtrim($where, " \t\n\r;");
            // Fix string/number comparison by adding check and cast
            $where = str_replace('AND b.option_value', 'AND b.option_value ~ \'^[0-9]+$\' AND CAST(b.option_value AS BIGINT)', $where);
            // Mirror WHERE clause to delete both sides of self-join.
            $where2 = strtr($where, array('a.' => 'b.', 'b.' => 'a.'));
            $sql = "DELETE FROM {$table_name} a USING {$table_name} b WHERE " .
                '(' . $where . ') OR (' . $where2 . ');';
        }

        // Rewrite _transient_timeout multi-table delete query for wp_sitemeta
        elseif(preg_match('/DELETE a, b FROM (\w+_sitemeta) a, \1 b/i', $sql, $matches)) {
            $table_name = $matches[1]; // Captured table name with prefix
            $where = substr($sql, strpos($sql, 'WHERE ') + 6);
            $where = rtrim($where, " \t\n\r;");
            // Fix string/number comparison by adding check and cast
            $where = str_replace('AND b.meta_value', 'AND b.meta_value ~ \'^[0-9]+$\' AND CAST(b.meta_value AS BIGINT)', $where);
            // Mirror WHERE clause to delete both sides of self-join.
            $where2 = strtr($where, array('a.' => 'b.', 'b.' => 'a.'));
            $sql = "DELETE FROM {$table_name} a USING {$table_name} b WHERE " .
                '(' . $where . ') OR (' . $where2 . ');';
        }

        // Akismet sometimes doesn't write 'comment_ID' with 'ID' in capitals where needed ...
        if(false !== strpos($sql, $wpdb->comments)) {
            $sql = str_replace(' comment_id ', ' comment_ID ', $sql);
        }

        return $sql;
    }
}
