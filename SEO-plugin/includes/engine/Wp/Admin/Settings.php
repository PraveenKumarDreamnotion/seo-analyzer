<?php
namespace HaloAudit\Wp\Admin;

final class Settings
{
    public const GROUP = 'halo_audit_settings';
    public const PAGE = 'seo-analyzer-settings';

    /** option => [label, input type, help] */
    private const FIELDS = [
        'halo_audit_psi_key' => ['Google PageSpeed API key', 'password', 'Used for the Speed group. Without a key Google allows only a few tests per day.'],
        'seo_analyzer_brevo_api_key' => ['Brevo API key', 'password', 'Sends the email code. Without a key WordPress mail is used.'],
        'seo_analyzer_sender_email' => ['Sender email', 'email', 'The From address of the email code.'],
        'halo_audit_scan_url' => ['Scan server URL', 'url', 'For the live AI visibility scan. Leave empty until the scan server is ready.'],
        'halo_audit_scan_secret' => ['Scan server secret', 'password', 'Shared secret for the scan server.'],
    ];

    public static function register(): void
    {
        $text = ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field', 'default' => ''];
        register_setting(self::GROUP, 'halo_audit_psi_key', $text);
        register_setting(self::GROUP, 'seo_analyzer_brevo_api_key', $text);
        register_setting(self::GROUP, 'halo_audit_scan_secret', $text);
        register_setting(self::GROUP, 'seo_analyzer_sender_email', ['type' => 'string', 'sanitize_callback' => 'sanitize_email', 'default' => '']);
        register_setting(self::GROUP, 'halo_audit_scan_url', ['type' => 'string', 'sanitize_callback' => [self::class, 'sanitizeUrl'], 'default' => '']);

        add_settings_section('halo_audit_main', '', '__return_false', self::PAGE);
        foreach (self::FIELDS as $option => $field) {
            add_settings_field($option, $field[0], [self::class, 'field'], self::PAGE, 'halo_audit_main', ['option' => $option, 'label_for' => $option]);
        }
    }

    public static function sanitizeUrl($value): string
    {
        return esc_url_raw(trim((string) $value), ['https']);
    }

    public static function field(array $args): void
    {
        $option = $args['option'];
        [, $type, $help] = self::FIELDS[$option];
        printf(
            '<input type="%1$s" id="%2$s" name="%2$s" value="%3$s" class="regular-text" autocomplete="off"><p class="description">%4$s</p>',
            esc_attr($type),
            esc_attr($option),
            esc_attr((string) get_option($option, '')),
            esc_html($help)
        );
    }

    public static function page(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }
        echo '<div class="wrap"><h1>' . esc_html(get_admin_page_title()) . '</h1><form action="options.php" method="post">';
        settings_fields(self::GROUP);
        do_settings_sections(self::PAGE);
        submit_button();
        echo '</form></div>';
    }
}
