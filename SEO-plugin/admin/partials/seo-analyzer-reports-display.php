<?php
// Check if the user has the required capabilities
if (!current_user_can('manage_options')) {
    wp_die(__('You do not have sufficient permissions to access this page.'));
}
?>

<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th>ID</th>
                <th>Name</th>
                <th>Email</th>
                <th>URL</th>
                <th>Keyword</th>
                <th>Competitor URL</th>
                <th>Overall Score</th>
                <th>Competitor Overall Score</th>
                <th>Created At</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($reports as $report): ?>
                <tr>
                    <td><?php echo esc_html($report['id']); ?></td>
                    <td><?php echo esc_html($report['name']); ?></td>
                    <td><?php echo esc_html($report['email']); ?></td>
                    <td><?php echo esc_html($report['url']); ?></td>
                    <td><?php echo esc_html($report['keyword']); ?></td>
                    <td><?php echo esc_html($report['competitor_url']); ?></td>
                    <td><?php echo esc_html($report['overall_score']); ?></td>
                    <td><?php echo esc_html($report['competitor_overall_score']); ?></td>
                    <td><?php echo esc_html($report['created_at']); ?></td>
                    <td>
                        <a href="<?php echo admin_url('admin.php?page=seo-analyzer-reports&action=download&id=' . $report['id']); ?>">Download</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>