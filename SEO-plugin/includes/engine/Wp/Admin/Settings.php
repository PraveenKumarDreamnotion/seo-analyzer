<?php
namespace HaloAudit\Wp\Admin;

final class Settings
{
    public const GROUP = 'halo_audit_settings';
    public const PAGE = 'seo-analyzer-settings';

    /** option => [label, input type, help] */
    private const FIELDS = [
        'halo_audit_psi_key' => ['Google PageSpeed API key (optional)', 'password', 'Turns on the Speed group. Without a key the speed test is skipped, Speed shows "Not measured" and the score is worked out from the other groups. The key stays on the server.'],
        'seo_analyzer_brevo_api_key' => ['Brevo API key (optional)', 'password', 'Sends the 6-digit code through Brevo. Without a key the code is sent with WordPress mail, so the mail setup of this site is used.'],
        'seo_analyzer_sender_email' => ['Sender email (optional)', 'email', 'The From address when the code is sent through Brevo. Empty means the site admin address.'],
        'halo_audit_scan_url' => ['Scan server URL', 'url', 'Not used in this version. Reserved for the live AI visibility scan.'],
        'halo_audit_scan_secret' => ['Scan server secret', 'password', 'Not used in this version.'],
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
        echo '<div class="wrap"><h1>' . esc_html(get_admin_page_title()) . '</h1>';
        echo '<p>Every setting is optional. The audit works as soon as the plugin is active and <code>[seo_analyzer]</code> is on a page.</p>';
        echo '<p>To unlock the full findings a visitor confirms an email address with a 6-digit code. '
            . 'That code is sent through Brevo when a Brevo key is saved below, and with WordPress mail otherwise. '
            . 'Make sure this site can send email, or visitors cannot unlock their report.</p>';
        echo '<form action="options.php" method="post">';
        settings_fields(self::GROUP);
        do_settings_sections(self::PAGE);
        submit_button();
        echo '</form></div>';
    }
}
