<?php
namespace HaloAudit\Wp;

use HaloAudit\Analyzer;
use HaloAudit\PsiClient;
use HaloAudit\ReportView;
use HaloAudit\ScanClient;

final class RestController
{
    public const NS = 'halo-audit/v1';
    public const NONCE_ACTION = 'halo_audit';

    private ReportStore $store;
    private Gate $gate;
    private ScanClient $scan;

    public function __construct(ReportStore $store, Gate $gate, ScanClient $scan)
    {
        $this->store = $store;
        $this->gate = $gate;
        $this->scan = $scan;
    }

    public function register(): void
    {
        $nonce = [$this, 'nonceOk'];
        $unlocked = [$this, 'unlockedOk'];
        $id = '(?P<id>[a-f0-9]{32})';

        register_rest_route(self::NS, '/nonce', ['methods' => 'GET', 'callback' => [$this, 'nonce'], 'permission_callback' => '__return_true']);
        register_rest_route(self::NS, '/analyze', ['methods' => 'POST', 'callback' => [$this, 'analyze'], 'permission_callback' => $nonce]);
        register_rest_route(self::NS, '/speed', ['methods' => 'POST', 'callback' => [$this, 'speed'], 'permission_callback' => $nonce]);
        register_rest_route(self::NS, '/gate/send', ['methods' => 'POST', 'callback' => [$this, 'gateSend'], 'permission_callback' => $nonce]);
        register_rest_route(self::NS, '/gate/verify', ['methods' => 'POST', 'callback' => [$this, 'gateVerify'], 'permission_callback' => $nonce]);
        register_rest_route(self::NS, '/report/' . $id, ['methods' => 'GET', 'callback' => [$this, 'report'], 'permission_callback' => $unlocked]);
        register_rest_route(self::NS, '/scan', ['methods' => 'POST', 'callback' => [$this, 'scanStart'], 'permission_callback' => $unlocked]);
        register_rest_route(self::NS, '/scan/(?P<id>[A-Za-z0-9_-]{1,64})', ['methods' => 'GET', 'callback' => [$this, 'scanStatus'], 'permission_callback' => $unlocked]);
    }

    public static function clientIp(): string
    {
        // REMOTE_ADDR only. Forwarded headers are set by the visitor and are not trusted.
        $ip = isset($_SERVER['REMOTE_ADDR']) ? (string) $_SERVER['REMOTE_ADDR'] : '0.0.0.0';
        return (string) apply_filters('halo_audit_client_ip', $ip);
    }

    /** @return true|\WP_Error A WP_Error with an explicit 403: a bare false gives 401 to logged-out visitors. */
    public function nonceOk(\WP_REST_Request $request)
    {
        if (wp_verify_nonce((string) $request->get_header('x_halo_nonce'), self::NONCE_ACTION) === false) {
            return new \WP_Error('halo_nonce', 'Your session has expired. Try again.', ['status' => 403]);
        }
        return true;
    }

    /** @return true|\WP_Error */
    public function unlockedOk(\WP_REST_Request $request)
    {
        $nonce = $this->nonceOk($request);
        if ($nonce !== true) {
            return $nonce;
        }
        if ($this->gate->current() === null) {
            return new \WP_Error('halo_locked', 'Confirm your email to see this.', ['status' => 403]);
        }
        return true;
    }

    public function nonce(): \WP_REST_Response
    {
        $response = new \WP_REST_Response(['nonce' => wp_create_nonce(self::NONCE_ACTION)]);
        $response->header('Cache-Control', 'no-store, max-age=0');
        return $response;
    }

    public function analyze(\WP_REST_Request $request)
    {
        if (!RateLimiter::allow('analyze', self::clientIp(), 10, HOUR_IN_SECONDS)) {
            return new \WP_Error('halo_rate', 'You have run 10 audits in the last hour. Try again later.', ['status' => 429]);
        }
        $url = trim((string) $request->get_param('url'));
        $keyword = sanitize_text_field((string) $request->get_param('keyword'));
        if (strlen($url) > 2000 || mb_strlen($keyword) > 255) {
            return new \WP_Error('halo_input', 'The address or keyword is too long.', ['status' => 400]);
        }

        $out = Plugin::analyzer()->analyze($url, $keyword);
        if (!$out['ok']) {
            return new \WP_Error('halo_' . $out['code'], $out['message'], ['status' => 422]);
        }

        $key = $this->store->create($out['report']);
        $lead = $this->gate->current();
        if ($lead !== null) {
            $this->store->attachLead($key, $lead['name'], $lead['email']);
        }

        return new \WP_REST_Response(ReportView::free($out['report']) + ['report_id' => $key, 'unlocked' => $lead !== null]);
    }

    public function speed(\WP_REST_Request $request)
    {
        $row = $this->store->get((string) $request->get_param('report_id'));
        if ($row === null) {
            return self::notFound();
        }
        $report = $row['report'];
        if (empty($report['speed_done'])) {
            if (!RateLimiter::allow('speed', self::clientIp(), 20, HOUR_IN_SECONDS)) {
                return new \WP_Error('halo_rate', 'Too many speed tests. Try again later.', ['status' => 429]);
            }
            if (function_exists('set_time_limit')) {
                @set_time_limit(120);
            }
            $psi = (new PsiClient(Plugin::http(), (string) get_option('halo_audit_psi_key', '')))->run($report['url']);
            $report = Analyzer::applySpeed($report, $psi);
            $this->store->update($row['key'], $report);
        }
        return new \WP_REST_Response(ReportView::free($report) + ['report_id' => $row['key'], 'unlocked' => $this->gate->current() !== null]);
    }

    public function gateSend(\WP_REST_Request $request)
    {
        if ($this->store->get((string) $request->get_param('report_id')) === null) {
            return self::notFound();
        }
        $sent = $this->gate->send((string) $request->get_param('email'), self::clientIp());
        return is_wp_error($sent) ? $sent : new \WP_REST_Response(['sent' => true]);
    }

    public function gateVerify(\WP_REST_Request $request)
    {
        $row = $this->store->get((string) $request->get_param('report_id'));
        if ($row === null) {
            return self::notFound();
        }
        $name = sanitize_text_field((string) $request->get_param('name'));
        if ($name === '') {
            return new \WP_Error('halo_name', 'Enter your name.', ['status' => 400]);
        }
        $token = $this->gate->verify((string) $request->get_param('email'), (string) $request->get_param('code'), $name, $row['key']);
        if (is_wp_error($token)) {
            return $token;
        }
        if (!headers_sent()) {
            setcookie(Gate::COOKIE, $token, [
                'expires' => time() + Gate::UNLOCK_SECONDS,
                'path' => '/',
                'secure' => is_ssl(),
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        }
        return new \WP_REST_Response(ReportView::full($row['report']) + ['report_id' => $row['key'], 'unlocked' => true]);
    }

    public function report(\WP_REST_Request $request)
    {
        $row = $this->store->get((string) $request['id']);
        if ($row === null) {
            return self::notFound();
        }
        if ($row['lead_verified_at'] === null) {
            $lead = $this->gate->current();
            $this->store->attachLead($row['key'], $lead['name'], $lead['email']);
        }
        return new \WP_REST_Response(ReportView::full($row['report']) + ['report_id' => $row['key'], 'unlocked' => true]);
    }

    public function scanStart(\WP_REST_Request $request)
    {
        $row = $this->store->get((string) $request->get_param('report_id'));
        return $row === null ? self::notFound() : new \WP_REST_Response($this->scan->start($row['key']));
    }

    public function scanStatus(\WP_REST_Request $request): \WP_REST_Response
    {
        return new \WP_REST_Response($this->scan->status((string) $request['id']));
    }

    private static function notFound(): \WP_Error
    {
        return new \WP_Error('halo_not_found', 'This report was not found. Run the audit again.', ['status' => 404]);
    }
}
