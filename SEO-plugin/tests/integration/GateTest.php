<?php
use HaloAudit\Wp\Gate;
use HaloAudit\Wp\Installer;
use HaloAudit\Wp\RateLimiter;
use HaloAudit\Wp\ReportStore;

class Halo_Gate_Test extends WP_UnitTestCase
{
    private Gate $gate;
    private ReportStore $store;
    private string $code = '';
    private string $key;

    public function set_up(): void
    {
        parent::set_up();
        $this->store = new ReportStore();
        $this->gate = new Gate($this->store);
        add_filter('halo_audit_send_code', function ($sent, $email, $code) {
            $this->code = $code;
            return true;
        }, 10, 3);
        $this->key = $this->store->create([
            'engine_version' => '2.0.0', 'url' => 'https://example.com/', 'keyword' => 'k', 'created_at' => time(),
            'is_education' => false, 'speed_done' => false, 'checks' => [], 'summary' => ['groups' => [], 'overall' => 50, 'grade' => 'C'],
        ]);
    }

    public function test_rate_limiter_counts_and_blocks(): void
    {
        $this->assertTrue(RateLimiter::allow('t', 'a', 2, 60));
        $this->assertTrue(RateLimiter::allow('t', 'a', 2, 60));
        $this->assertFalse(RateLimiter::allow('t', 'a', 2, 60));
        $this->assertTrue(RateLimiter::allow('t', 'b', 2, 60));
    }

    public function test_code_is_six_digits_and_stored_only_as_a_hash(): void
    {
        global $wpdb;
        $this->assertTrue($this->gate->send('asha@example.com', '203.0.113.5'));
        $this->assertMatchesRegularExpression('/^\d{6}$/', $this->code);
        $row = $wpdb->get_row('SELECT * FROM ' . Installer::otpTable(), ARRAY_A);
        $this->assertNotSame($this->code, $row['code_hash']);
        $this->assertStringNotContainsString($this->code, $row['code_hash']);
    }

    public function test_invalid_email_is_rejected(): void
    {
        $this->assertSame('halo_email', $this->gate->send('not-an-email', '203.0.113.5')->get_error_code());
    }

    public function test_fourth_code_for_one_email_in_an_hour_is_refused(): void
    {
        foreach ([1, 2, 3] as $i) {
            $this->assertTrue($this->gate->send('asha@example.com', '203.0.113.' . $i));
        }
        $error = $this->gate->send('asha@example.com', '203.0.113.9');
        $this->assertSame('halo_rate', $error->get_error_code());
        $this->assertSame(429, $error->get_error_data()['status']);
    }

    public function test_eleventh_code_from_one_ip_is_refused(): void
    {
        foreach (range(1, 10) as $i) {
            $this->assertTrue($this->gate->send("user$i@example.com", '203.0.113.5'));
        }
        $this->assertSame('halo_rate', $this->gate->send('user11@example.com', '203.0.113.5')->get_error_code());
    }

    public function test_right_code_saves_the_lead_and_returns_a_token(): void
    {
        $this->gate->send('Asha@Example.com', '203.0.113.5');
        $token = $this->gate->verify('asha@example.com', $this->code, 'Asha Rao', $this->key);

        $this->assertIsString($token);
        $this->assertSame(['name' => 'Asha Rao', 'email' => 'asha@example.com'], $this->gate->parse($token));
        $row = $this->store->get($this->key);
        $this->assertSame('asha@example.com', $row['email']);
        $this->assertNotNull($row['lead_verified_at']);
    }

    public function test_a_code_works_once(): void
    {
        $this->gate->send('asha@example.com', '203.0.113.5');
        $this->gate->verify('asha@example.com', $this->code, 'Asha', $this->key);
        $this->assertWPError($this->gate->verify('asha@example.com', $this->code, 'Asha', $this->key));
    }

    public function test_five_wrong_tries_void_the_code(): void
    {
        $this->gate->send('asha@example.com', '203.0.113.5');
        foreach (range(1, 5) as $i) {
            $this->assertSame('halo_code', $this->gate->verify('asha@example.com', '000000', 'Asha', $this->key)->get_error_code());
        }
        $this->assertSame('halo_code_void', $this->gate->verify('asha@example.com', $this->code, 'Asha', $this->key)->get_error_code());
        $this->assertNull($this->store->get($this->key)['email']);
    }

    public function test_expired_code_is_refused(): void
    {
        global $wpdb;
        $this->gate->send('asha@example.com', '203.0.113.5');
        $wpdb->query('UPDATE ' . Installer::otpTable() . " SET expires_at = '2000-01-01 00:00:00'");
        $this->assertSame('halo_code', $this->gate->verify('asha@example.com', $this->code, 'Asha', $this->key)->get_error_code());
    }

    public function test_tampered_or_expired_tokens_do_not_parse(): void
    {
        $good = $this->gate->token('Asha', 'asha@example.com', time() + 60);
        $this->assertNotNull($this->gate->parse($good));
        $this->assertNull($this->gate->parse($good . 'x'));
        $this->assertNull($this->gate->parse(str_replace('.', '.A', $good)));
        $this->assertNull($this->gate->parse($this->gate->token('Asha', 'asha@example.com', time() - 1)));
        $this->assertNull($this->gate->parse('garbage'));
    }

    public function test_current_reads_the_cookie(): void
    {
        $this->assertNull($this->gate->current());
        $_COOKIE[Gate::COOKIE] = $this->gate->token('Asha', 'asha@example.com', time() + 60);
        $this->assertSame('asha@example.com', $this->gate->current()['email']);
        unset($_COOKIE[Gate::COOKIE]);
    }
}
