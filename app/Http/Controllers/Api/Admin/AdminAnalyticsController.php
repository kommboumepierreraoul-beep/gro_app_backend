<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Dispute;
use App\Models\Mission;
use App\Models\MissionApplication;
use App\Models\ModerationPost;
use App\Models\Order;
use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class AdminAnalyticsController extends Controller
{
    public function getAnalytics()
    {
        $now = now();
        $startOfMonth = $now->copy()->startOfMonth();
        $previousMonthStart = $now->copy()->subMonth()->startOfMonth();
        $previousMonthEnd = $now->copy()->subMonth()->endOfMonth();

        $ordersTotal = Order::count();
        $ordersActive = Order::whereIn('status', ['pending', 'paid', 'preparing', 'shipping', 'delivered'])->count();
        $ordersCompleted = Order::where('status', 'completed')->count();
        $ordersPendingPayment = Order::where('payment_status', 'pending')->count();
        $ordersCashOnDelivery = Order::where('payment_method', 'cash_on_delivery')->count();

        $totalSales = (float) Order::whereIn('status', ['paid', 'preparing', 'shipping', 'delivered', 'completed'])
            ->sum('total_amount');
        $monthlySales = (float) Order::where('created_at', '>=', $startOfMonth)->sum('total_amount');
        $previousMonthlySales = (float) Order::whereBetween('created_at', [$previousMonthStart, $previousMonthEnd])
            ->sum('total_amount');

        $walletBalance = (float) Wallet::sum('balance');
        $pendingDeposits = WalletTransaction::where('type', 'deposit')->where('status', 'pending')->count();
        $pendingWithdrawals = WalletTransaction::where('type', 'withdrawal')->where('status', 'pending')->count();

        $monthlyData = collect(range(5, 0))->map(function (int $monthsAgo) {
            $date = now()->subMonths($monthsAgo);
            $start = $date->copy()->startOfMonth();
            $end = $date->copy()->endOfMonth();

            return [
                'month' => $date->locale('fr')->translatedFormat('M'),
                'sales' => (float) Order::whereBetween('created_at', [$start, $end])->sum('total_amount'),
                'purchases' => Order::whereBetween('created_at', [$start, $end])->count(),
                'users' => User::whereBetween('created_at', [$start, $end])->count(),
            ];
        })->values();

        $productStatus = Product::selectRaw("COALESCE(approval_status, status, 'unknown') as status_key, COUNT(*) as total")
            ->groupBy('status_key')
            ->pluck('total', 'status_key');

        $disputeStatus = Dispute::selectRaw("status, COUNT(*) as total")
            ->groupBy('status')
            ->pluck('total', 'status');

        $missionStatus = Mission::selectRaw("status, COUNT(*) as total")
            ->groupBy('status')
            ->pluck('total', 'status');

        $recentOrders = Order::with(['user:id,firstname,lastname,email', 'shop:id,name'])
            ->latest()
            ->limit(6)
            ->get()
            ->map(fn (Order $order) => [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'status' => $order->status,
                'payment_method' => $order->payment_method,
                'payment_status' => $order->payment_status,
                'total_amount' => (float) $order->total_amount,
                'customer' => trim(($order->user?->firstname ?? '') . ' ' . ($order->user?->lastname ?? '')) ?: $order->user?->email,
                'shop' => $order->shop?->name,
                'created_at' => $order->created_at?->toIso8601String(),
            ]);

        $recentDisputes = Dispute::with(['order:id,order_number,total_amount', 'user:id,firstname,lastname', 'seller:id,firstname,lastname'])
            ->latest()
            ->limit(5)
            ->get()
            ->map(fn (Dispute $dispute) => [
                'id' => $dispute->id,
                'status' => $dispute->status,
                'reason' => $dispute->reason,
                'order_number' => $dispute->order?->order_number,
                'customer' => trim(($dispute->user?->firstname ?? '') . ' ' . ($dispute->user?->lastname ?? '')),
                'seller' => trim(($dispute->seller?->firstname ?? '') . ' ' . ($dispute->seller?->lastname ?? '')),
                'created_at' => $dispute->created_at?->toIso8601String(),
            ]);

        return response()->json([
            'success' => true,
            'data' => [
                'overview' => [
                    'users_total' => User::count(),
                    'active_users' => User::where('status', 'active')->count(),
                    'admins_total' => User::where('role', 'admin')->count(),
                    'sellers_total' => Shop::count(),
                    'shops_active' => Shop::where('status', 'active')->count(),
                    'products_total' => Product::count(),
                    'pending_approvals' => Product::where('approval_status', 'pending')->count(),
                    'approved_products' => Product::where('approval_status', 'approved')->count(),
                    'rejected_products' => Product::where('approval_status', 'rejected')->count(),
                    'orders_total' => $ordersTotal,
                    'orders_active' => $ordersActive,
                    'orders_completed' => $ordersCompleted,
                    'orders_pending_payment' => $ordersPendingPayment,
                    'orders_cash_on_delivery' => $ordersCashOnDelivery,
                    'disputes_open' => Dispute::whereIn('status', ['open', 'pending', 'escalated'])->count(),
                    'disputes_escalated' => Dispute::whereNotNull('escalated_at')->count(),
                    'missions_total' => Mission::count(),
                    'missions_published' => Mission::where('status', Mission::STATUS_PUBLISHED)->count(),
                    'mission_applications_pending' => MissionApplication::where('status', 'pending')->count(),
                    'moderation_pending' => ModerationPost::whereIn('status', ['pending', 'review'])->count(),
                    'wallet_balance' => $walletBalance,
                    'pending_deposits' => $pendingDeposits,
                    'pending_withdrawals' => $pendingWithdrawals,
                    'total_sales' => $totalSales,
                    'monthly_sales' => $monthlySales,
                    'previous_monthly_sales' => $previousMonthlySales,
                    'sales_growth' => $this->growthRate($monthlySales, $previousMonthlySales),
                ],
                'product_status' => $productStatus,
                'dispute_status' => $disputeStatus,
                'mission_status' => $missionStatus,
                'monthly_data' => $monthlyData,
                'recent_orders' => $recentOrders,
                'recent_disputes' => $recentDisputes,

                // Backward compatibility for existing frontend pages.
                'pending_approvals' => Product::where('approval_status', 'pending')->count(),
                'active_users' => User::where('status', 'active')->count(),
                'total_sales' => $totalSales,
                'total_purchases' => Order::distinct('user_id')->count('user_id'),
            ],
        ]);
    }

    private function growthRate(float $current, float $previous): float
    {
        if ($previous <= 0) {
            return $current > 0 ? 100.0 : 0.0;
        }

        return round((($current - $previous) / $previous) * 100, 1);
    }
}
