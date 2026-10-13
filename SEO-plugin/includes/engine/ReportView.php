<?php
namespace HaloAudit;

final class ReportView
{
    /** What a visitor sees before the email gate: scores and counts only. */
    public static function free(array $report): array
    {
        return [
            'url' => $report['url'],
            'keyword' => $report['keyword'],
            'is_education' => (bool) $report['is_education'],
            'speed_done' => (bool) $report['speed_done'],
            'summary' => $report['summary'],
            'labels' => Scorer::LABELS,
        ];
    }

    /** The free view plus every finding. */
    public static function full(array $report): array
    {
        return self::free($report) + ['checks' => $report['checks']];
    }
}
