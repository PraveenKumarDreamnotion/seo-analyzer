<?php
namespace HaloAudit\Wp\Admin;

use HaloAudit\Wp\Installer;

if (!class_exists('\WP_List_Table')) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

final class ReportsTable extends \WP_List_Table
{
    private const PER_PAGE = 20;

    public function get_columns(): array
    {
        return [
            'created_at' => 'Date', 'name' => 'Name', 'email' => 'Email', 'url' => 'Page',
            'keyword' => 'Keyword', 'overall_score' => 'Score', 'grade' => 'Grade', 'is_education' => 'Education',
        ];
    }

    public function prepare_items(): void
    {
        global $wpdb;
        $table = Installer::resultsTable();
        $total = (int) $wpdb->get_var("SELECT COUNT(*) FROM $table");
        $offset = ($this->get_pagenum() - 1) * self::PER_PAGE;

        $this->_column_headers = [$this->get_columns(), [], []];
        $this->items = $wpdb->get_results($wpdb->prepare(
            "SELECT id, name, email, url, keyword, overall_score, grade, is_education, created_at FROM $table ORDER BY id DESC LIMIT %d OFFSET %d",
            self::PER_PAGE,
            $offset
        ), ARRAY_A);
        $this->set_pagination_args(['total_items' => $total, 'per_page' => self::PER_PAGE]);
    }

    public function column_default($item, $column_name): string
    {
        $value = $item[$column_name] ?? '';
        if ($column_name === 'is_education') {
            return $value ? 'Yes' : 'No';
        }
        if ($column_name === 'overall_score') {
            return $value === null || $value === '' ? 'Not measured' : esc_html((string) (int) round((float) $value));
        }
        if (($column_name === 'name' || $column_name === 'email') && ($value === null || $value === '')) {
            return '<em>No email given</em>';
        }
        return esc_html((string) $value);
    }

    public function no_items(): void
    {
        echo 'No reports yet.';
    }
}
