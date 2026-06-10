<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\User;
use App\Models\Order;
use Illuminate\Support\Facades\DB;

class AdminAnalyticsController extends Controller
{
    public function getAnalytics()
    {
        // Compter les produits en attente
        $pendingCount = Product::where('approval_status', 'pending')->count();
        
        // Compter les utilisateurs actifs
        $activeUsers = User::where('status', 'active')->count();
        
        // Calculer les ventes totales
        $totalSales = (int) Order::sum('total_amount') ?? 0;
        
        // Compter les acheteurs
        $totalPurchases = (int) Order::distinct('user_id')->count('user_id') ?? 0;

        // Données mensuelles (exemple statique - adapter selon votre logique)
        $monthlyData = [
            ['month' => 'Jan', 'sales' => 12500, 'purchases' => 450],
            ['month' => 'Fév', 'sales' => 15000, 'purchases' => 520],
            ['month' => 'Mar', 'sales' => 18200, 'purchases' => 650],
            ['month' => 'Avr', 'sales' => 16800, 'purchases' => 590],
            ['month' => 'Mai', 'sales' => 21500, 'purchases' => 780],
            ['month' => 'Juin', 'sales' => 19200, 'purchases' => 710],
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'pending_approvals' => $pendingCount,
                'active_users' => $activeUsers,
                'total_sales' => $totalSales,
                'total_purchases' => $totalPurchases,
                'monthly_data' => $monthlyData,
            ]
        ]);
    }
}
