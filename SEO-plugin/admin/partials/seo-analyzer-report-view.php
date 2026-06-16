<div class="wrap">
    <h1>SEO Analysis Report</h1>
    <table class="form-table">
        <tr>
            <th>Name</th>
            <td><?php echo esc_html($report['name']); ?></td>
        </tr>
        <tr>
            <th>Email</th>
            <td><?php echo esc_html($report['email']); ?></td>
        </tr>
        <tr>
            <th>URL</th>
            <td><?php echo esc_html($report['url']); ?></td>
        </tr>
        <tr>
            <th>Keyword</th>
            <td><?php echo esc_html($report['keyword']); ?></td>
        </tr>
        <tr>
            <th>Created At</th>
            <td><?php echo esc_html($report['created_at']); ?></td>
        </tr>
    </table>

    <h2>Analysis Results</h2>
    <pre><?php echo esc_html(print_r(json_decode($report['results'], true), true)); ?></pre>
</div>