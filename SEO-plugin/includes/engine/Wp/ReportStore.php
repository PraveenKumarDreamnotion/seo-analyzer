<?php
namespace HaloAudit\Wp;

final class ReportStore
{
    public static function isKey(string $key): bool
    {
        return preg_match('/^[a-f0-9]{32}$/', $key) === 1;
    }

    public function create(array $report): string
    {
        global $wpdb;
        $key = bin2hex(random_bytes(16));
        $wpdb->insert(Installer::resultsTable(), [
            'report_key' => $key,
            'url' => mb_substr($report['url'], 0, 255),
            'keyword' => mb_substr($report['keyword'], 0, 255),
            'results' => wp_json_encode($report),
            'overall_score' => $report['summary']['overall'],
            'grade' => $report['summary']['grade'],
            'engine_version' => $report['engine_version'],
            'is_education' => $report['is_education'] ? 1 : 0,
            'created_at' => gmdate('Y-m-d H:i:s'),
        ]);
        return $key;
    }

    public function get(string $key): ?array
    {
        global $wpdb;
        if (!self::isKey($key)) {
            return null;
        }
        $row = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . Installer::resultsTable() . ' WHERE report_key = %s', $key), ARRAY_A);
        if (!$row) {
            return null;
        }
        $report = json_decode($row['results'], true);
        if (!is_array($report)) {
            return null;
        }
        return [
            'key' => $key,
            'report' => $report,
            'name' => $row['name'],
            'email' => $row['email'],
            'lead_verified_at' => $row['lead_verified_at'],
        ];
    }

    public function update(string $key, array $report): void
    {
        global $wpdb;
        if (!self::isKey($key)) {
            return;
        }
        $wpdb->update(Installer::resultsTable(), [
            'results' => wp_json_encode($report),
            'overall_score' => $report['summary']['overall'],
            'grade' => $report['summary']['grade'],
        ], ['report_key' => $key]);
    }

    public function attachLead(string $key, string $name, string $email): void
    {
        global $wpdb;
        if (!self::isKey($key)) {
            return;
        }
        $wpdb->update(Installer::resultsTable(), [
            'name' => mb_substr($name, 0, 100),
            'email' => mb_substr($email, 0, 100),
            'lead_verified_at' => gmdate('Y-m-d H:i:s'),
        ], ['report_key' => $key]);
    }

    public function purgeUnverified(int $days = 7): int
    {
        global $wpdb;
        $cutoff = gmdate('Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS);
        return (int) $wpdb->query($wpdb->prepare(
            'DELETE FROM ' . Installer::resultsTable() . ' WHERE report_key IS NOT NULL AND lead_verified_at IS NULL AND created_at < %s',
            $cutoff
        ));
    }
}
