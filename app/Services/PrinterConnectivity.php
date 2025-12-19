<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class PrinterConnectivity
{
    public static function isReachable(string $ip, int $port = 9100, int $timeout = 5): bool
    {
        $errno = 0;
        $errstr = '';
        
        $startTime = microtime(true);
        $fp = @fsockopen($ip, $port, $errno, $errstr, $timeout);
        $elapsed = microtime(true) - $startTime;
        
        if (!$fp) {
            Log::debug('Printer connectivity check failed', [
                'ip' => $ip,
                'port' => $port,
                'errno' => $errno,
                'error' => $errstr,
                'elapsed_ms' => round($elapsed * 1000, 2),
            ]);
            return false;
        }
        
        fclose($fp);
        
        Log::debug('Printer connectivity check succeeded', [
            'ip' => $ip,
            'port' => $port,
            'elapsed_ms' => round($elapsed * 1000, 2),
        ]);
        
        return true;
    }

    public static function ping(string $ip, int $timeout = 2): bool
    {
        $command = sprintf(
            'ping -c 1 -W %d %s > /dev/null 2>&1',
            $timeout,
            escapeshellarg($ip)
        );
        
        exec($command, $output, $returnCode);
        
        $reachable = $returnCode === 0;
        
        Log::debug('Printer ping check', [
            'ip' => $ip,
            'reachable' => $reachable,
        ]);
        
        return $reachable;
    }

    public static function checkWithFallback(string $ip, int $port = 9100, int $timeout = 5): bool
    {
        if (self::isReachable($ip, $port, $timeout)) {
            return true;
        }
        
        return self::ping($ip, min($timeout, 2));
    }
}
