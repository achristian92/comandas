<?php

namespace App\Console\Commands;

use App\Models\PrintJob;
use Illuminate\Console\Command;

class CleanOldPrintJobs extends Command
{
    protected $signature = 'print:clean {--days=7 : Number of days to keep printed jobs} {--failed-days=30 : Number of days to keep failed jobs}';

    protected $description = 'Clean old print jobs from database';

    public function handle()
    {
        $days = (int) $this->option('days');
        $failedDays = (int) $this->option('failed-days');
        
        $printedDeleted = PrintJob::printed()
            ->where('printed_at', '<', now()->subDays($days))
            ->delete();
            
        $this->info("Deleted {$printedDeleted} printed jobs older than {$days} days");
        
        $failedDeleted = PrintJob::failed()
            ->where('failed_at', '<', now()->subDays($failedDays))
            ->delete();
            
        $this->info("Deleted {$failedDeleted} failed jobs older than {$failedDays} days");
        
        $total = $printedDeleted + $failedDeleted;
        $this->info("Total deleted: {$total} print jobs");
        
        return 0;
    }
}
