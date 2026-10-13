<?php
use HaloAudit\Wp\Installer;
use HaloAudit\Wp\ReportStore;

class Halo_Report_Store_Test extends WP_UnitTestCase
{
    private function report(): array
    {
        return [
            'engine_version' => '2.0.0', 'url' => 'https://example.com/', 'keyword' => 'mba', 'created_at' => time(),
            'is_education' => true, 'speed_done' => false,
            'checks' => [['id' => 'https', 'group' => 'technical', 'severity' => 3, 'title' => 'T', 'why' => 'W', 'status' => 'pass', 'evidence' => 'E', 'message' => '']],
            'summary' => ['groups' => [], 'overall' => 81, 'grade' => 'B'],
        ];
    }

    public function test_schema_has_the_new_columns(): void
    {
        global $wpdb;
        $columns = $wpdb->get_col('SHOW COLUMNS FROM ' . Installer::resultsTable());
        foreach (['report_key', 'engine_version', 'grade', 'is_education', 'lead_verified_at', 'scan_id'] as $column) {
            $this->assertContains($column, $columns);
        }
        $otp = $wpdb->get_col('SHOW COLUMNS FROM ' . Installer::otpTable());
        $this->assertContains('code_hash', $otp);
        $this->assertContains('attempts', $otp);
        $this->assertNotContains('otp', $otp);
        $this->assertSame(Installer::DB_VERSION, get_option('halo_audit_db_version'));
        $this->assertNotFalse(wp_next_scheduled(Installer::CRON_HOOK));
    }

    public function test_create_and_get_round_trip(): void
    {
        $store = new ReportStore();
        $key = $store->create($this->report());
        $this->assertMatchesRegularExpression('/^[a-f0-9]{32}$/', $key);

        $row = $store->get($key);
        $this->assertSame('https://example.com/', $row['report']['url']);
        $this->assertSame(81, $row['report']['summary']['overall']);
        $this->assertNull($row['email']);
        $this->assertNull($row['lead_verified_at']);
    }

    public function test_get_rejects_bad_and_unknown_keys(): void
    {
        $store = new ReportStore();
        $this->assertNull($store->get("1' OR '1'='1"));
        $this->assertNull($store->get(str_repeat('a', 32)));
        $this->assertFalse(ReportStore::isKey('ABC'));
    }

    public function test_update_replaces_the_report(): void
    {
        $store = new ReportStore();
        $key = $store->create($this->report());
        $changed = $this->report();
        $changed['speed_done'] = true;
        $changed['summary']['overall'] = 90;
        $changed['summary']['grade'] = 'A';
        $store->update($key, $changed);

        global $wpdb;
        $this->assertTrue($store->get($key)['report']['speed_done']);
        $this->assertSame('A', $wpdb->get_var($wpdb->prepare('SELECT grade FROM ' . Installer::resultsTable() . ' WHERE report_key = %s', $key)));
    }

    public function test_attach_lead_sets_name_email_and_time(): void
    {
        $store = new ReportStore();
        $key = $store->create($this->report());
        $store->attachLead($key, 'Asha Rao', 'asha@example.com');
        $row = $store->get($key);
        $this->assertSame('Asha Rao', $row['name']);
        $this->assertSame('asha@example.com', $row['email']);
        $this->assertNotNull($row['lead_verified_at']);
    }

    public function test_purge_removes_only_old_unverified_new_style_reports(): void
    {
        global $wpdb;
        $store = new ReportStore();
        $table = Installer::resultsTable();

        $oldUnverified = $store->create($this->report());
        $oldVerified = $store->create($this->report());
        $fresh = $store->create($this->report());
        $store->attachLead($oldVerified, 'A', 'a@example.com');
        $wpdb->query($wpdb->prepare("UPDATE $table SET created_at = %s WHERE report_key IN (%s, %s)", gmdate('Y-m-d H:i:s', time() - 8 * DAY_IN_SECONDS), $oldUnverified, $oldVerified));
        $wpdb->insert($table, ['name' => 'Legacy', 'email' => 'l@example.com', 'url' => 'https://old.example/', 'keyword' => 'k', 'results' => '{}', 'created_at' => '2024-01-01 00:00:00']);

        $this->assertSame(1, $store->purgeUnverified());
        $this->assertNull($store->get($oldUnverified));
        $this->assertNotNull($store->get($oldVerified));
        $this->assertNotNull($store->get($fresh));
        $this->assertSame('1', $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE name = 'Legacy'"));
    }

    public function test_psi_key_is_migrated_from_the_old_table(): void
    {
        global $wpdb;
        $old = $wpdb->prefix . 'seo_analyzer_api_keys';
        $wpdb->query("CREATE TABLE $old (id mediumint(9) NOT NULL AUTO_INCREMENT, api_key varchar(255) NOT NULL, PRIMARY KEY (id))");
        $wpdb->insert($old, ['api_key' => 'OLDKEY']);
        delete_option('halo_audit_psi_key');
        delete_option('halo_audit_db_version');

        Installer::maybeUpgrade();

        $this->assertSame('OLDKEY', get_option('halo_audit_psi_key'));
        $this->assertNull($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $old)));
    }
}
