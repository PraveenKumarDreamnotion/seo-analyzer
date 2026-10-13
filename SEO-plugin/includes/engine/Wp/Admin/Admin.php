<?php
namespace HaloAudit\Wp\Admin;

use HaloAudit\Wp\Installer;

final class Admin
{
    private const CAPABILITY = 'manage_options';
    private const EXPORT_ACTION = 'halo_audit_export';

    public static function register(): void
    {
        add_action('admin_menu', [self::class, 'menu']);
        add_action('admin_init', [Settings::class, 'register']);
        add_action('admin_post_' . self::EXPORT_ACTION, [self::class, 'export']);
    }

    public static function menu(): void
    {
        add_menu_page('SEO Analyzer', 'SEO Analyzer', self::CAPABILITY, 'seo-analyzer', [self::class, 'homePage'], 'dashicons-chart-area', 6);
        add_submenu_page('seo-analyzer', 'SEO Analyzer Settings', 'Settings', self::CAPABILITY, Settings::PAGE, [Settings::class, 'page']);
        add_submenu_page('seo-analyzer', 'SEO Analysis Reports', 'Reports', self::CAPABILITY, 'seo-analyzer-reports', [self::class, 'reportsPage']);
    }

    public static function homePage(): void
    {
        echo '<div class="wrap"><h1>SEO Analyzer</h1><p>Add the tool to any page with this shortcode:</p>'
            . '<p><code>[seo_analyzer title="Halo Audit"]</code></p></div>';
    }

    public static function reportsPage(): void
    {
        if (!current_user_can(self::CAPABILITY)) {
            wp_die(esc_html__('You do not have permission to view this page.', 'seo-analyzer'), '', ['response' => 403]);
        }
        $table = new ReportsTable();
        $table->prepare_items();
        $export = wp_nonce_url(admin_url('admin-post.php?action=' . self::EXPORT_ACTION), self::EXPORT_ACTION);

        echo '<div class="wrap"><h1 class="wp-heading-inline">' . esc_html(get_admin_page_title()) . '</h1> ';
        echo '<a class="page-title-action" href="' . esc_url($export) . '">Export leads as CSV</a><hr class="wp-header-end">';
        $table->display();
        echo '</div>';
    }

    /** @return array<int,array<string,mixed>> verified leads only */
    public static function exportRows(): array
    {
        global $wpdb;
        return $wpdb->get_results(
            'SELECT name, email, url, keyword, overall_score, grade, is_education, created_at FROM ' . Installer::resultsTable()
            . " WHERE email IS NOT NULL AND email <> '' ORDER BY id DESC",
            ARRAY_A
        );
    }

    /** Stops a spreadsheet from running a cell as a formula. */
    public static function csvCell(string $value): string
    {
        return $value !== '' && strpbrk($value[0], "=+-@\t\r") !== false ? "'" . $value : $value;
    }

    public static function export(): void
    {
        if (!current_user_can(self::CAPABILITY)) {
            wp_die(esc_html__('You do not have permission to do this.', 'seo-analyzer'), '', ['response' => 403]);
        }
        check_admin_referer(self::EXPORT_ACTION);

        nocache_headers();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="halo-audit-leads-' . gmdate('Y-m-d') . '.csv"');

        $out = fopen('php://output', 'w');
        fputcsv($out, ['Name', 'Email', 'Page', 'Keyword', 'Score', 'Grade', 'Education', 'Date']);
        foreach (self::exportRows() as $row) {
            fputcsv($out, array_map(static fn($value): string => self::csvCell((string) $value), array_values($row)));
        }
        fclose($out);
        exit;
    }
}
