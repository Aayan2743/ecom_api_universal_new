<?php

namespace App\Http\Controllers;



use App\Http\Controllers\Controller;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\Organization;
use App\Models\OrganizationSubscription;
use Intervention\Image\Facades\Image;
use Illuminate\Support\Str;


class OrganizationSubscriptionController extends Controller
{



public function store(Request $request, Organization $organization)
{
    /* ---------------- VALIDATION ---------------- */
    $validator = Validator::make($request->all(), [
        'total_cards'    => 'required|integer|min:1',
        'price'          => 'required|numeric|min:0',
        'valid_days'     => 'required|integer|min:1',

        'payment_type'   => 'required|in:online,cash',
        'payment_method' => 'required|in:upi,card,cash',
        'payment_proof'  => 'required|image|mimes:jpg,jpeg,png,webp|max:5120',
    ]);

    if ($validator->fails()) {
        return response()->json([
            'message' => 'Validation error',
            'errors'  => $validator->errors(),
        ], 422);
    }

    /* ---------------- DATE FIX ---------------- */
    $validDays = (int) $request->valid_days;
    $startsAt  = now();
    $endsAt    = now()->addDays($validDays);

    /* ---------------- IMAGE → WEBP (RESIZE + COMPRESS) ---------------- */
    $file = $request->file('payment_proof');

    // Load image safely
    $image = imagecreatefromstring(file_get_contents($file));
    if (!$image) {
        return response()->json([
            'message' => 'Invalid image file'
        ], 422);
    }

    $width  = imagesx($image);
    $height = imagesy($image);

    /* 🔥 MAX WIDTH (KEY FOR SIZE REDUCTION) */
    $maxWidth = 1200;

    if ($width > $maxWidth) {
        $newWidth  = $maxWidth;
        $newHeight = intval(($height / $width) * $newWidth);

        $resized = imagecreatetruecolor($newWidth, $newHeight);

        // Preserve transparency for PNG
        imagealphablending($resized, false);
        imagesavealpha($resized, true);

        imagecopyresampled(
            $resized,
            $image,
            0, 0, 0, 0,
            $newWidth,
            $newHeight,
            $width,
            $height
        );

        imagedestroy($image);
        $image = $resized;
    }

    // Ensure directory exists
    $folder = storage_path('app/public/payment-proofs');
    if (!is_dir($folder)) {
        mkdir($folder, 0755, true);
    }

    // Save as WebP
    $fileName = Str::uuid() . '.webp';
    $filePath = $folder . '/' . $fileName;

    /* 🔥 QUALITY: 70 = best balance */
    imagewebp($image, $filePath, 70);
    imagedestroy($image);

    $proofPath = 'payment-proofs/' . $fileName;

    /* ---------------- SAVE SUBSCRIPTION ---------------- */
    $subscription = OrganizationSubscription::create([
        'organization_id' => $organization->id,
        'total_cards'     => (int) $request->total_cards,
        'used_cards'      => 0,
        'price'           => (float) $request->price,
        'status'          => 'active',
        'starts_at'       => $startsAt,
        'ends_at'         => $endsAt,

        'payment_type'    => $request->payment_type,
        'payment_method'  => $request->payment_method,
        'payment_proof'   => $proofPath,
    ]);

    return response()->json([
        'message' => 'Cards purchased successfully',
        'data'    => $subscription,
    ], 201);
}


public function transactions()
{
    $transactions = OrganizationSubscription::with('organization')
        ->latest()
        ->get()
        ->map(function ($t) {
            return [
                'id'              => $t->id,
                'organization'    => $t->organization->name ?? '-',
                'cards'           => $t->total_cards,
                'price'           => $t->price,
                'payment_type'    => ucfirst($t->payment_type),
                'payment_method'  => ucfirst($t->payment_method),
                'status'          => ucfirst($t->status),
                'starts_at'       => optional($t->starts_at)->format('Y-m-d'),
                'ends_at'         => optional($t->ends_at)->format('Y-m-d'),
                'proof'           => $t->payment_proof
                    ? asset('storage/' . $t->payment_proof)
                    : null,
                'created_at'      => $t->created_at->format('Y-m-d'),
            ];
        });

    return response()->json([
        'success' => true,
        'data' => $transactions
    ]);
}


    public function show(Organization $organization)
    {
        $subscriptions = OrganizationSubscription::where(
                'organization_id',
                $organization->id
            )
            ->orderByDesc('created_at')
            ->get()
            ->map(function ($sub) {
                return [
                    'id'       => $sub->id,

                    // ✅ matches UI: "2024-01-10"
                    'date'     => optional($sub->created_at)->format('Y-m-d'),

                    // ✅ matches UI
                    'cards'    => (int) $sub->total_cards,
                    'used'     => (int) $sub->used_cards,

                    // ✅ expiry date
                    'expiry'   => optional($sub->ends_at)->format('Y-m-d'),
                    // 'expiry'   =>$sub->ends_at,

                    // ✅ UI expects "Active" / "Expired"
                    'status'   => $sub->status === 'active'
                        ? 'Active'
                        : 'Expired',

                    // ✅ price
                    'amount'   => (float) $sub->price,

                    // ✅ placeholder for View Cards page
                    'employees' => [],
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $subscriptions
        ]);
    }


    public function index(Request $request)
    {
        $from = $request->from
            ? Carbon::parse($request->from)
            : now()->startOfYear();

        $to = $request->to
            ? Carbon::parse($request->to)
            : now();

        /* ---------- KPI STATS ---------- */
        $stats = [
            'organizations' => Organization::count(),
            'active_plans'  => OrganizationSubscription::where('status', 'active')->count(),
            'users'         => User::count(),
            'revenue'       => OrganizationSubscription::sum('price'),
        ];

        /* ---------- MONTHLY CHART ---------- */
        $chart = OrganizationSubscription::selectRaw('
                MONTH(created_at) as month,
                COUNT(id) as users,
                SUM(price) as revenue
            ')
            ->whereBetween('created_at', [$from, $to])
            ->groupBy('month')
            ->orderBy('month')
            ->get()
            ->map(function ($row) {
                return [
                    'month'   => Carbon::create()->month($row->month)->format('M'),
                    'users'   => (int) $row->users,
                    'revenue' => (int) $row->revenue,
                ];
            });

        return response()->json([
            'success' => true,
            'stats'   => $stats,
            'chart'   => $chart,
        ]);
    }



}