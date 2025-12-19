<?php

namespace App\Http\Controllers;

use App\Models\PrintJob;
use Illuminate\Http\Request;

class PrintStatusController extends Controller
{
    public function index(Request $request)
    {
        $query = PrintJob::query()->latest();
        
        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }
        
        if ($request->has('type')) {
            $query->where('type', $request->input('type'));
        }
        
        $limit = min((int) $request->input('limit', 50), 100);
        
        $jobs = $query->limit($limit)->get();
        
        return response()->json([
            'status' => 'ok',
            'data' => $jobs,
            'summary' => [
                'pending' => PrintJob::where('status', PrintJob::STATUS_PENDING)->count(),
                'retrying' => PrintJob::where('status', PrintJob::STATUS_RETRYING)->count(),
                'printed' => PrintJob::where('status', PrintJob::STATUS_PRINTED)->count(),
                'failed' => PrintJob::where('status', PrintJob::STATUS_FAILED)->count(),
            ],
        ]);
    }

    public function show($id)
    {
        $job = PrintJob::find($id);
        
        if (!$job) {
            return response()->json([
                'status' => 'error',
                'message' => 'Print job not found',
            ], 404);
        }
        
        return response()->json([
            'status' => 'ok',
            'data' => $job,
        ]);
    }

    public function stats()
    {
        $stats = [
            'total' => PrintJob::count(),
            'by_status' => [
                'pending' => PrintJob::where('status', PrintJob::STATUS_PENDING)->count(),
                'retrying' => PrintJob::where('status', PrintJob::STATUS_RETRYING)->count(),
                'printed' => PrintJob::where('status', PrintJob::STATUS_PRINTED)->count(),
                'failed' => PrintJob::where('status', PrintJob::STATUS_FAILED)->count(),
            ],
            'by_type' => PrintJob::selectRaw('type, count(*) as count')
                ->groupBy('type')
                ->pluck('count', 'type'),
            'recent_failures' => PrintJob::failed()
                ->where('failed_at', '>', now()->subHours(24))
                ->count(),
            'success_rate' => $this->calculateSuccessRate(),
        ];
        
        return response()->json([
            'status' => 'ok',
            'data' => $stats,
        ]);
    }

    private function calculateSuccessRate(): float
    {
        $total = PrintJob::count();
        
        if ($total === 0) {
            return 100.0;
        }
        
        $printed = PrintJob::where('status', PrintJob::STATUS_PRINTED)->count();
        
        return round(($printed / $total) * 100, 2);
    }
}
