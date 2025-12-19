<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PrintJob extends Model
{
    use HasFactory;

    protected $fillable = [
        'type',
        'printer_ip',
        'printer_port',
        'payload',
        'status',
        'attempts',
        'max_attempts',
        'last_error',
        'printed_at',
        'failed_at',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
        'printed_at' => 'datetime',
        'failed_at' => 'datetime',
        'attempts' => 'integer',
        'max_attempts' => 'integer',
        'printer_port' => 'integer',
    ];

    const STATUS_PENDING = 'pending';
    const STATUS_RETRYING = 'retrying';
    const STATUS_PRINTED = 'printed';
    const STATUS_FAILED = 'failed';

    public function markAsPrinted(): void
    {
        $this->update([
            'status' => self::STATUS_PRINTED,
            'printed_at' => now(),
            'last_error' => null,
        ]);
    }

    public function markAsFailed(string $error): void
    {
        $this->update([
            'status' => self::STATUS_FAILED,
            'failed_at' => now(),
            'last_error' => $error,
        ]);
    }

    public function incrementAttempts(string $error = null): void
    {
        $this->increment('attempts');
        
        $updates = [
            'status' => self::STATUS_RETRYING,
        ];
        
        if ($error) {
            $updates['last_error'] = $error;
        }
        
        $this->update($updates);
    }

    public function hasReachedMaxAttempts(): bool
    {
        return $this->attempts >= $this->max_attempts;
    }

    public function scopePending($query)
    {
        return $query->whereIn('status', [self::STATUS_PENDING, self::STATUS_RETRYING]);
    }

    public function scopeFailed($query)
    {
        return $query->where('status', self::STATUS_FAILED);
    }

    public function scopePrinted($query)
    {
        return $query->where('status', self::STATUS_PRINTED);
    }
}
