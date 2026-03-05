<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function stats(Request $request)
    {
        $start = $request->start_date
            ? \Carbon\Carbon::parse($request->start_date)->startOfDay()
            : now()->startOfMonth();

        $end = $request->end_date
            ? \Carbon\Carbon::parse($request->end_date)->endOfDay()
            : now()->endOfMonth();

        $customers = \App\Models\User::whereBetween('created_at', [$start, $end])->count();

        $products = \App\Models\Product::count();

        $orders = \App\Models\Order::whereBetween('created_at', [$start, $end])->count();

        // 🔥 Revenue without payment_status
        $revenue = \App\Models\Order::whereBetween('created_at', [$start, $end])
            ->sum('total_amount');

        // Revenue Chart (Grouped by Month)
        $revenueChart = \App\Models\Order::selectRaw("
            DATE_FORMAT(created_at, '%b') as month,
            SUM(total_amount) as revenue
        ")
            ->whereBetween('created_at', [$start, $end])
            ->groupByRaw("DATE_FORMAT(created_at, '%b')")
            ->get();

        // Orders Chart (Grouped by Date)
        $ordersChart = \App\Models\Order::selectRaw("
            DATE(created_at) as day,
            COUNT(*) as orders
        ")
            ->whereBetween('created_at', [$start, $end])
            ->groupByRaw("DATE(created_at)")
            ->get();

        return response()->json([
            'status' => true,
            'data'   => [
                'customers'     => $customers,
                'products'      => $products,
                'orders'        => $orders,
                'revenue'       => $revenue,
                'revenue_chart' => $revenueChart,
                'orders_chart'  => $ordersChart,
            ],
        ]);
    }

}
