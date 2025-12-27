<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class PrintService
{
    public static function printRawWithRetry(
        string $type,
        string $printerIp,
        int $printerPort,
        string $payload,
        array $metadata = [],
        int $maxAttempts = null,
        int $timeout = null,
        string $printerType = 'RED'
    ): bool {
        $maxAttempts = $maxAttempts ?? (int) config('printing.max_attempts', 5);
        $timeout = $timeout ?? (int) config('printing.socket_timeout', 5);
        $backoff = [0, 10, 30, 60, 120];
        
        Log::info('Print job started', [
            'type' => $type,
            'printer_type' => $printerType,
            'printer_ip' => $printerIp,
            'printer_port' => $printerPort,
            'payload_size' => strlen($payload),
            'metadata' => $metadata,
        ]);
        
        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            try {
                if ($attempt > 1) {
                    $delay = $backoff[$attempt - 1] ?? end($backoff);
                    if ($delay > 0) {
                        Log::info('Waiting before retry', [
                            'type' => $type,
                            'printer_ip' => $printerIp,
                            'attempt' => $attempt,
                            'delay_seconds' => $delay,
                        ]);
                        sleep($delay);
                    }
                }
                
                if ($printerType === 'USB') {
                    self::sendRawToUsbPrinter($printerIp, $payload);
                } else {
                    if (!PrinterConnectivity::checkWithFallback($printerIp, $printerPort, $timeout)) {
                        throw new \RuntimeException(
                            sprintf('Impresora no alcanzable: %s:%d', $printerIp, $printerPort)
                        );
                    }
                    
                    self::sendRawToPrinter($printerIp, $printerPort, $payload, $timeout);
                }
                
                Log::info('Print job completed successfully', [
                    'type' => $type,
                    'printer_type' => $printerType,
                    'printer_ip' => $printerIp,
                    'printer_port' => $printerPort,
                    'attempt' => $attempt,
                ]);
                
                return true;
                
            } catch (\Throwable $e) {
                $error = sprintf('%s: %s', get_class($e), $e->getMessage());
                
                Log::warning('Print attempt failed', [
                    'type' => $type,
                    'printer_type' => $printerType,
                    'printer_ip' => $printerIp,
                    'printer_port' => $printerPort,
                    'attempt' => $attempt,
                    'max_attempts' => $maxAttempts,
                    'error' => $error,
                ]);
                
                if ($attempt >= $maxAttempts) {
                    Log::error('Print job failed after max attempts', [
                        'type' => $type,
                        'printer_type' => $printerType,
                        'printer_ip' => $printerIp,
                        'printer_port' => $printerPort,
                        'attempts' => $attempt,
                        'error' => $error,
                    ]);
                    return false;
                }
            }
        }
        
        return false;
    }
    
    private static function sendRawToPrinter(string $ip, int $port, string $payload, int $timeout): void
    {
        $errno = 0;
        $errstr = '';
        
        $fp = @fsockopen($ip, $port, $errno, $errstr, $timeout);
        
        if (!$fp) {
            throw new \RuntimeException(
                sprintf('No se pudo conectar a impresora %s:%d (errno: %d) %s', $ip, $port, $errno, $errstr)
            );
        }

        stream_set_timeout($fp, $timeout);
        
        try {
            $written = fwrite($fp, $payload);
            
            if ($written === false) {
                throw new \RuntimeException(
                    sprintf('No se pudo escribir a impresora %s:%d', $ip, $port)
                );
            }
            
            if ($written < strlen($payload)) {
                throw new \RuntimeException(
                    sprintf('Escritura incompleta a impresora %s:%d (%d de %d bytes)', $ip, $port, $written, strlen($payload))
                );
            }
            
            fflush($fp);
            
            $meta = stream_get_meta_data($fp);
            if ($meta['timed_out']) {
                throw new \RuntimeException(
                    sprintf('Timeout escribiendo a impresora %s:%d', $ip, $port)
                );
            }
            
        } finally {
            fclose($fp);
        }
    }

    public static function buildEscposPayload(array $lines, int $feedLines = 4, bool $cut = true): string
    {
        $payload = implode("\n", $lines) . "\n";
        
        if ($feedLines > 0) {
            $payload .= self::escposFeed($feedLines);
        }
        
        if ($cut) {
            $payload .= self::escposCut();
        }
        
        return $payload;
    }

    private static function escposCut(): string
    {
        return pack('C*', 0x1D, 0x56, 0x00);
    }

    private static function escposFeed(int $lines = 3): string
    {
        $lines = max(0, min(10, $lines));
        return pack('C*', 0x1B, 0x64, $lines);
    }

    private static function sendRawToUsbPrinter(string $printerName, string $payload): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'print_');
        
        if ($tempFile === false) {
            throw new \RuntimeException('No se pudo crear archivo temporal para impresión USB');
        }
        
        try {
            if (file_put_contents($tempFile, $payload) === false) {
                throw new \RuntimeException('No se pudo escribir en archivo temporal');
            }
            
            $isWindows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
            
            if ($isWindows) {
                $command = sprintf('copy /B "%s" "\\\\localhost\\%s"', $tempFile, $printerName);
            } else {
                $command = sprintf('lp -d %s %s', escapeshellarg($printerName), escapeshellarg($tempFile));
            }
            
            exec($command . ' 2>&1', $output, $returnCode);
            
            if ($returnCode !== 0) {
                throw new \RuntimeException(
                    sprintf('Error al imprimir en USB: %s (código: %d)', implode(' ', $output), $returnCode)
                );
            }
            
            Log::info('USB print command executed', [
                'os' => $isWindows ? 'Windows' : 'Unix',
                'printer_name' => $printerName,
                'command' => $command,
                'output' => $output,
            ]);
            
        } finally {
            if (file_exists($tempFile)) {
                @unlink($tempFile);
            }
        }
    }
}
