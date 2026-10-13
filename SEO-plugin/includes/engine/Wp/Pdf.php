<?php
namespace HaloAudit\Wp;

use HaloAudit\Scorer;
use HaloAudit\Url;

final class Pdf
{
    private const STATUS_LABELS = ['pass' => 'Pass', 'warn' => 'Improve', 'fail' => 'Fix', 'not_measured' => 'Not measured'];
    private const STATUS_COLOURS = ['pass' => '#2e7d32', 'warn' => '#b26a00', 'fail' => '#c62828', 'not_measured' => '#6b6b6b'];

    public static function filename(array $report): string
    {
        $host = preg_replace('/[^a-z0-9.-]/', '', Url::host($report['url']));
        return 'halo-audit-' . ($host !== '' ? $host : 'report') . '.pdf';
    }

    /** Every dynamic value is escaped. No value from the audited page reaches mPDF as HTML. */
    public static function html(array $report): string
    {
        $e = static fn($value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
        $summary = $report['summary'];

        $out = '<h1>Halo Audit</h1>';
        $out .= '<p><strong>Page:</strong> ' . $e($report['url']) . '<br><strong>Keyword:</strong> ' . $e($report['keyword'])
            . '<br><strong>Date:</strong> ' . $e(gmdate('j F Y', (int) $report['created_at'])) . '</p>';

        $out .= $summary['overall'] === null
            ? '<p class="score">Not measured</p>'
            : '<p class="score">' . $e($summary['overall']) . ' / 100 &nbsp; Grade ' . $e($summary['grade']) . '</p>';

        $out .= '<table class="groups"><tr><th>Area</th><th>Score</th><th>Pass</th><th>Improve</th><th>Fix</th></tr>';
        foreach ($summary['groups'] as $group => $data) {
            $out .= '<tr><td>' . $e(Scorer::LABELS[$group] ?? $group) . '</td><td>' . ($data['score'] === null ? 'Not measured' : $e($data['score']))
                . '</td><td>' . $e($data['pass']) . '</td><td>' . $e($data['warn']) . '</td><td>' . $e($data['fail']) . '</td></tr>';
        }
        $out .= '</table>';

        $byGroup = [];
        foreach ($report['checks'] as $row) {
            $byGroup[$row['group']][] = $row;
        }
        foreach ($byGroup as $group => $rows) {
            $out .= '<h2>' . $e(Scorer::LABELS[$group] ?? $group) . '</h2><table class="checks">';
            foreach ($rows as $row) {
                $colour = self::STATUS_COLOURS[$row['status']] ?? '#6b6b6b';
                $out .= '<tr><td class="status" style="color:' . $colour . '">' . $e(self::STATUS_LABELS[$row['status']] ?? $row['status']) . '</td><td><strong>'
                    . $e($row['title']) . '</strong><br>' . $e($row['evidence']);
                if ($row['message'] !== '') {
                    $out .= '<br><em>What to do:</em> ' . $e($row['message']);
                }
                $out .= '</td></tr>';
            }
            $out .= '</table>';
        }

        $out .= '<p class="foot">Made with Halo Audit by Thrivemattic. thrivemattic.com</p>';
        return $out;
    }

    public static function render(array $report): string
    {
        $temp = trailingslashit(get_temp_dir()) . 'halo-audit-mpdf';
        wp_mkdir_p($temp);

        $mpdf = new \Mpdf\Mpdf(['tempDir' => $temp, 'format' => 'A4', 'margin_top' => 16, 'margin_bottom' => 16]);
        $mpdf->SetTitle('Halo Audit');
        $mpdf->WriteHTML(self::css(), \Mpdf\HTMLParserMode::HEADER_CSS);
        $mpdf->WriteHTML(self::html($report), \Mpdf\HTMLParserMode::HTML_BODY);
        return $mpdf->Output('', \Mpdf\Output\Destination::STRING_RETURN);
    }

    private static function css(): string
    {
        return 'body{font-family:dejavusans;font-size:10pt;color:#222}'
            . 'h1{font-size:20pt;margin:0 0 6pt}h2{font-size:13pt;margin:14pt 0 4pt;border-bottom:1px solid #ccc}'
            . '.score{font-size:22pt;font-weight:bold;margin:8pt 0}'
            . 'table{width:100%;border-collapse:collapse}td,th{padding:4pt 5pt;border-bottom:1px solid #e5e5e5;vertical-align:top;text-align:left}'
            . '.status{width:70pt;font-weight:bold}.foot{margin-top:18pt;font-size:8pt;color:#666}';
    }
}
