<?php
namespace HaloAudit\Wp;

final class RateLimiter
{
    /** Fixed window. The window starts at the first counted call and does not slide. */
    public static function allow(string $bucket, string $key, int $limit, int $windowSeconds): bool
    {
        $name = 'halo_rl_' . md5($bucket . '|' . $key);
        $state = get_transient($name);
        $now = time();

        if (!is_array($state) || $state['until'] <= $now) {
            $state = ['count' => 0, 'until' => $now + $windowSeconds];
        }
        if ($state['count'] >= $limit) {
            return false;
        }
        $state['count']++;
        set_transient($name, $state, max(1, $state['until'] - $now));
        return true;
    }
}
