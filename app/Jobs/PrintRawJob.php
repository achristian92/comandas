<?php

namespace App\Jobs;

use App\Models\PrintJob;
use App\Services\PrinterConnectivity;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class PrintRawJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $printJobId;
    public $tries = 5;
    public $timeout = 30;
    public $backoff = [10, 30, 60, 120, 300];

    public function __construct(int $printJobId)
    {
        $this->printJobId = $printJobId;
    }

    public function handle()
    {
        $printJob = PrintJob::find($this->printJobId);
        
        if (!$printJob) {
            Log::error('PrintJob not found', ['id' => $this->printJobId]);
            return;
        }

        if ($printJob->status === PrintJob::STATUS_PRINTED) {
            Log::info('PrintJob already printed, skipping', ['id' => $this->printJobId]);
            return;
        }

        Log::info('PrintJob processing started', [
            'id' => $printJob->id,
            'type' => $printJob->type,
            'printer_ip' => $printJob->printer_ip,
            'printer_port' => $printJob->printer_port,
            'attempt' => $printJob->attempts + 1,
            'max_attempts' => $printJob->max_attempts,
        ]);

        $timeout = (int) config('printing.socket_timeout', 5);
        
        if (!PrinterConnectivity::checkWithFallback($printJob->printer_ip, $printJob->printer_port, $timeout)) {
            $error = sprintf(
                'Impresora no alcanzable: %s:%d',
                $printJob->printer_ip,
                $printJob->printer_port
            );
            
            Log::warning('Printer not reachable', [
                'id' => $printJob->id,
                'printer_ip' => $printJob->printer_ip,
                'printer_port' => $printJob->printer_port,
                'attempt' => $printJob->attempts + 1,
            ]);
            
            $printJob->incrementAttempts($error);
            
            if ($printJob->hasReachedMaxAttempts()) {
                $printJob->markAsFailed($error);
                Log::error('PrintJob failed after max attempts', [
                    'id' => $printJob->id,
                    'attempts' => $printJob->attempts,
                    'error' => $error,
                ]);
                return;
            }
            
            $this->release($this->getBackoffDelay($printJob->attempts));
            return;
        }

        try {
            $this->sendRawToPrinter(
                $printJob->printer_ip,
                $printJob->printer_port,
                $printJob->payload,
                $timeout
            );
            
            $printJob->markAsPrinted();
            
            Log::info('PrintJob completed successfully', [
                'id' => $printJob->id,
                'type' => $printJob->type,
                'printer_ip' => $printJob->printer_ip,
                'attempts' => $printJob->attempts + 1,
            ]);
            
        } catch (\Throwable $e) {
            $error = sprintf('%s: %s', get_class($e), $e->getMessage());
            
            Log::error('PrintJob execution error', [
                'id' => $printJob->id,
                'printer_ip' => $printJob->printer_ip,
                'error' => $error,
                'attempt' => $printJob->attempts + 1,
            ]);
            
            $printJob->incrementAttempts($error);
            
            if ($printJob->hasReachedMaxAttempts()) {
                $printJob->markAsFailed($error);
                Log::error('PrintJob failed after max attempts', [
                    'id' => $printJob->id,
                    'attempts' => $printJob->attempts,
                    'error' => $error,
                ]);
                return;
            }
            
            $this->release($this->getBackoffDelay($printJob->attempts));
        }
    }

    private function sendRawToPrinter(string $ip, int $port, string $payload, int $timeout): void
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

    private function getBackoffDelay(int $attempts): int
    {
        if (isset($this->backoff[$attempts])) {
            return $this->backoff[$attempts];
        }
        
        return end($this->backoff);
    }

    public function failed(\Throwable $exception)
    {
        $printJob = PrintJob::find($this->printJobId);
        
        if ($printJob && $printJob->status !== PrintJob::STATUS_PRINTED) {
            $error = sprintf('%s: %s', get_class($exception), $exception->getMessage());
            $printJob->markAsFailed($error);
            
            Log::error('PrintJob failed permanently', [
                'id' => $printJob->id,
                'error' => $error,
                'attempts' => $printJob->attempts,
            ]);
        }
    }
}
