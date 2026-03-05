<?php
namespace App\Http\Controllers;

use App\Jobs\SendPasswordJob;
use App\Mail\OrganizationPasswordMail;
use App\Models\Organization;
use App\Models\OrganizationBranding;
use App\Models\OrganizationSubscription;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;
use Storage;

class OrginazationController extends Controller
{

    public function index()
    {
        $organizations = Organization::latest()
            ->get()
            ->map(function ($org) {

                // Get active subscription (or all, based on your logic)
                $subscription = OrganizationSubscription::where(
                    'organization_id',
                    $org->id
                )
                    ->where('status', 'active')
                    ->latest()
                    ->first();

                $totalCards    = $subscription?->total_cards ?? 0;
                $usedCards     = $subscription?->used_cards ?? 0;
                $inactiveCards = max($totalCards - $usedCards, 0);

                return [
                    'id'             => $org->id,
                    'name'           => $org->name,
                    'email'          => $org->email,
                    'phone'          => $org->phone,
                    'created_at'     => optional($org->created_at)->format('Y-m-d'),

                    // 👇 THESE POWER YOUR UI
                    'total_cards'    => $totalCards,
                    'active_cards'   => $usedCards,
                    'inactive_cards' => $inactiveCards,
                ];
            });

        return response()->json([
            'success' => true,
            'message' => 'Organizations fetched successfully',
            'data'    => $organizations,
        ]);
    }

    /* ======================
       CREATE ORGANIZATION
    ====================== */

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name'  => 'required|string|max:255',

            // check email unique in BOTH tables
            'email' => [
                'nullable',
                'email',
                function ($attribute, $value, $fail) {
                    if (
                        Organization::where('email', $value)->exists() ||
                        User::where('email', $value)->exists()
                    ) {
                        $fail('The email has already been taken.');
                    }
                },
            ],

            // check phone unique in BOTH tables
            'phone' => [
                'nullable',
                'string',
                'max:20',
                function ($attribute, $value, $fail) {
                    if (
                        Organization::where('phone', $value)->exists() ||
                        User::where('phone', $value)->exists()
                    ) {
                        $fail('The phone number has already been taken.');
                    }
                },
            ],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation error',
                'errors'  => $validator->errors()->first(),
            ], 422);
        }

        try {
            DB::beginTransaction();

            // 1️⃣ Generate password
            $plainPassword = Str::random(10);

            // 2️⃣ Create Organization
            $organization = Organization::create([
                'name'  => $request->name,
                'email' => $request->email,
                'phone' => $request->phone,
            ]);

            // 3️⃣ Create User
            $user = User::create([
                'name'            => $organization->name,
                'email'           => $organization->email,
                'phone'           => $organization->phone,
                'password'        => Hash::make($plainPassword),
                'organization_id' => $organization->id,
                'role'            => 'employeer',
            ]);

          SendPasswordJob::dispatch($organization, $plainPassword);

            // 4️⃣ Send mail
            // Mail::to($organization->email)
            //     ->send(new OrganizationPasswordMail($organization, $plainPassword));

            DB::commit();

            return response()->json([
                'message' => 'Organization created and credentials sent successfully',
                'data'    => $organization,
            ], 201);

        } catch (\Throwable $e) {

            DB::rollBack();

            return response()->json([
                'message' => 'Something went wrong',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /* ======================
       SHOW SINGLE ORGANIZATION
    ====================== */

    public function show_old($id)
    {

        $organization = Organization::find($id);

        if (! $organization) {
            return response()->json([
                'success' => false,
                'message' => 'Organization not found',
            ], 404);
        }

        /* ================= SUBSCRIPTIONS ================= */
        $subscriptions = OrganizationSubscription::where(
            'organization_id',
            $organization->id
        )
            ->where('status', 'active')
            ->where(function ($q) {
                $q->whereNull('ends_at')
                    ->orWhere('ends_at', '>', now());
            })
            ->get();

        $totalCards     = $subscriptions->sum('total_cards');
        $usedCards      = $subscriptions->sum('used_cards');
        $remainingCards = max($totalCards - $usedCards, 0);

        /* ================= BRANDING ================= */
        $branding = OrganizationBranding::where(
            'organization_id',
            $organization->id
        )->first();

        return response()->json([
            'success' => true,
            'message' => 'Organization fetched successfully',
            'data'    => [
                /* ========== Organization Base ========== */
                'id'             => $organization->id,
                'name'           => $organization->name,
                'email'          => $organization->email,
                'phone'          => $organization->phone,

                /* ========== Branding (from organization_brandings) ========== */
                'branding'       => [
                    'brand_name' => $organization->name, // source of truth

                    'logo'       => $branding && $branding->logo
                        ? URL::to(Storage::url($branding->logo))
                        : null,

                    'cover_page' => $branding && $branding->cover_page
                        ? URL::to(Storage::url($branding->cover_page))
                        : null,

                    'favicon'    => $branding && $branding->favicon
                        ? URL::to(Storage::url($branding->favicon))
                        : null,
                ],

                /* ========== UI Stats ========== */
                'total_cards'    => $totalCards,
                'active_cards'   => $usedCards,
                'inactive_cards' => $remainingCards,
            ],
        ]);
    }

    public function show($id)
    {
        $organization = Organization::find($id);

        if (! $organization) {
            return response()->json([
                'success' => false,
                'message' => 'Organization not found',
            ], 404);
        }

        /* ================= SUBSCRIPTIONS ================= */
        $subscriptionsQuery = OrganizationSubscription::where(
            'organization_id',
            $organization->id
        );

        // Active & valid subscriptions for stats
        $activeSubscriptions = (clone $subscriptionsQuery)
            ->where('status', 'active')
            ->where(function ($q) {
                $q->whereNull('ends_at')
                    ->orWhere('ends_at', '>', now());
            })
            ->get();

        $totalCards     = $activeSubscriptions->sum('total_cards');
        $usedCards      = $activeSubscriptions->sum('used_cards');
        $remainingCards = max($totalCards - $usedCards, 0);

        /* ================= ALL SUBSCRIPTIONS (UI LIST) ================= */
        $subscriptions = $subscriptionsQuery
            ->orderByDesc('created_at')
            ->get()
            ->map(function ($sub) {
                return [
                    'id'        => $sub->id,
                    'date'      => optional($sub->created_at)->format('Y-m-d'),
                    'cards'     => (int) $sub->total_cards,
                    'used'      => (int) $sub->used_cards,
                    'expiry'    => optional($sub->ends_at)->format('Y-m-d'),
                    'status'    => $sub->status === 'active'
                        ? 'Active'
                        : 'Expired',
                    'amount'    => (float) $sub->price,
                    'employees' => [], // placeholder
                ];
            });

        /* ================= BRANDING ================= */
        $branding = OrganizationBranding::where(
            'organization_id',
            $organization->id
        )->first();

        return response()->json([
            'success' => true,
            'message' => 'Organization fetched successfully',
            'data'    => [
                /* ========== Organization Base ========== */
                'id'             => $organization->id,
                'name'           => $organization->name,
                'email'          => $organization->email,
                'phone'          => $organization->phone,

                /* ========== Branding ========== */
                'branding'       => [
                    'brand_name' => $organization->name,
                    'logo'       => $branding && $branding->logo
                        ? url(Storage::url($branding->logo))
                        : null,
                    'cover_page' => $branding && $branding->cover_page
                        ? url(Storage::url($branding->cover_page))
                        : null,
                    'favicon'    => $branding && $branding->favicon
                        ? url(Storage::url($branding->favicon))
                        : null,
                ],

                /* ========== Card Stats ========== */
                'total_cards'    => $totalCards,
                'active_cards'   => $usedCards,
                'inactive_cards' => $remainingCards,

                /* ========== Subscription History ========== */
                'subscriptions'  => $subscriptions,
            ],
        ]);
    }

    /* ======================
       UPDATE ORGANIZATION
    ====================== */
    public function update(Request $request, $id)
    {
        $organization = Organization::find($id);

        if (! $organization) {
            return response()->json([
                'message' => 'Organization not found',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name'  => 'required|string|max:255',
            'email' => 'nullable|email|unique:organizations,email,' . $organization->id,
            'phone' => 'nullable|string|max:20|unique:organizations,phone,' . $organization->id,
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation error',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $organization->update($request->only([
            'name', 'email', 'phone',
        ]));

        return response()->json([
            'message' => 'Organization updated successfully',
            'data'    => $organization,
        ]);
    }

    /* ======================
       DELETE ORGANIZATION
    ====================== */
    public function destroy($id)
    {
        $organization = Organization::find($id);

        if (! $organization) {
            return response()->json([
                'message' => 'Organization not found',
            ], 404);
        }

        $organization->delete();

        return response()->json([
            'message' => 'Organization deleted successfully',
        ]);
    }

    /* ======================
        ORGANIZATION STATS
    ====================== */

    public function stats(Organization $organization)
    {
        $totalCards    = $organization->cards()->count();
        $activeCards   = $organization->cards()->where('status', 'active')->count();
        $inactiveCards = $organization->cards()->where('status', 'inactive')->count();

        return response()->json([
            'message' => 'Card stats fetched successfully',
            'data'    => [
                'total_cards'    => $totalCards,
                'active_cards'   => $activeCards,
                'inactive_cards' => $inactiveCards,
            ],
        ]);
    }

    // Oeginization Employeer Routes..

    public function showOrginizationBrandSettings_old(Request $request)
    {

        $orgId = Auth::user()->organization_id;

        $branding = OrganizationBranding::firstOrCreate(
            ['organization_id' => $orgId],
            ['brand_name' => auth()->user()->organization->name]
        );

        return response()->json([
            'success' => true,
            'data'    => [
                'id'         => $branding->id,
                'brand_name' => $branding->brand_name,

                // ✅ FULL URL
                'logo'       => $branding->logo
                    ? URL::to(Storage::url($branding->logo))
                    : null,

                'cover_page' => $branding->cover_page
                    ? URL::to(Storage::url($branding->cover_page))
                    : null,

                'favicon'    => $branding->favicon
                    ? URL::to(Storage::url($branding->favicon))
                    : null,
            ],
        ]);
    }

    public function showOrginizationBrandSettings(Request $request)
    {
        $orgId = auth()->user()->organization_id;

        $organization = Organization::findOrFail($orgId);

        $branding = OrganizationBranding::firstOrCreate([
            'organization_id' => $orgId,
        ]);

        return response()->json([
            'success' => true,
            'data'    => [
                'id'         => $branding->id,
                'brand_name' => $organization->name, // ✅ from organizations table

                'logo'       => $branding->logo
                    ? URL::to(Storage::url($branding->logo))
                    : null,

                'cover_page' => $branding->cover_page
                    ? URL::to(Storage::url($branding->cover_page))
                    : null,

                'favicon'    => $branding->favicon
                    ? URL::to(Storage::url($branding->favicon))
                    : null,
            ],
        ]);
    }

    /**
     * CREATE / UPDATE branding (UPSERT)
     */

    public function save(Request $request)
    {
        $orgId = auth()->user()->organization_id;

        $validator = Validator::make($request->all(), [
            'brand_name'  => 'nullable|string|max:255',

            'logo'        => 'nullable|image|mimes:png,jpg,jpeg,svg|max:2048',
            'cover_image' => 'nullable|image|mimes:png,jpg,jpeg|max:4096',
            'favicon'     => 'nullable|image|mimes:png,jpg,jpeg,ico|max:1024',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        /* ================= ORGANIZATION ================= */
        $organization = Organization::findOrFail($orgId);

        if ($request->filled('brand_name')) {
            $organization->name = $request->brand_name;
            $organization->save();
        }

        /* ================= BRANDING ================= */
        $branding = OrganizationBranding::firstOrCreate([
            'organization_id' => $orgId,
        ]);

        if ($request->hasFile('logo')) {
            $branding->logo = $this->storeAsWebp(
                $request->file('logo'),
                "organizations/{$orgId}/branding/logo"
            );
        }

        if ($request->hasFile('cover_image')) {
            $branding->cover_page = $this->storeAsWebp(
                $request->file('cover_image'),
                "organizations/{$orgId}/branding/cover"
            );
        }

        if ($request->hasFile('favicon')) {
            $branding->favicon = $this->storeAsWebp(
                $request->file('favicon'),
                "organizations/{$orgId}/branding/favicon",
                80
            );
        }

        $branding->save();

        return response()->json([
            'success' => true,
            'message' => 'Organization branding saved successfully',
            'data'    => [
                'brand_name' => $organization->name,
                'logo'       => $branding->logo,
                'cover_page' => $branding->cover_page,
                'favicon'    => $branding->favicon,
            ],
        ]);
    }

    private function storeAsWebp($file, $path, $quality = 85)
    {
        $manager = new ImageManager(new Driver());

        $filename = Str::uuid() . '.webp';
        $fullPath = storage_path("app/public/{$path}");

        if (! file_exists($fullPath)) {
            mkdir($fullPath, 0755, true);
        }

        $image = $manager->read($file);
        $image->toWebp($quality)->save("{$fullPath}/{$filename}");

        return "{$path}/{$filename}";
    }

}