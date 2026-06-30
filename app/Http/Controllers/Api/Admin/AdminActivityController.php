<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use Illuminate\Http\Request;

class AdminActivityController extends Controller
{
    // Récupérer la timeline d'activités
    public function getActivityLog(Request $request)
    {
        $limit = $request->query('limit', 20);

        $activities = ActivityLog::orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $activities
        ]);
    }
}
