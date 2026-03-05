<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\SendPasswordResetOtpJob;
use App\Models\User;
use App\Services\WebpService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * @group Admin Dashboard
 * @authenticated
 */
class AuthController extends Controller
{

    public function sample()
    {
        $samples = \App\Models\sapmle::all();
        return response()->json([
            'success' => true,
            'data'    => $samples,
        ]);
    }

    public function user_details(Request $request)
    {
        $perPage = $request->get('per_page', 10); // default 10
        $search  = $request->get('search');

        $query = User::where('role', 'user')
            ->withCount('wishlists')
            ->withCount('orders')
            ->withSum('orders as total_amount', 'total_amount');

        // 🔍 Search by name, email, phone
        if (! empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        $users = $query->paginate($perPage);

        // ✨ Transform data
        $customers = $users->getCollection()->map(function ($user, $index) use ($users) {
            return [
                '#'            => ($users->currentPage() - 1) * $users->perPage() + ($index + 1),
                'name'         => $user->name,
                'email'        => $user->email,
                'phone'        => $user->phone,
                'wishlist'     => $user->wishlists_count ?? 0,
                'orders'       => $user->orders_count ?? 0,
                'total_amount' => '₹ ' . ($user->total_amount ?? 0),
            ];
        });

        return response()->json([
            'success' => true,
            'data'    => $customers,
            'meta'    => [
                'current_page' => $users->currentPage(),
                'per_page'     => $users->perPage(),
                'total'        => $users->total(),
                'last_page'    => $users->lastPage(),
            ],
        ]);
    }



    // User Registration
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|unique:users,email',
            'password' => 'required|min:6',
            'phone'    => 'required|digits:10|unique:users,phone',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors'  => $validator->errors()->first(),
            ], 422);
        }

        $user = User::create([
            'name'     => $request->name,
            'email'    => $request->email,
            'password' => Hash::make($request->password),
            'phone'    => $request->phone,
        ]);

        $token = JWTAuth::fromUser($user);

        return response()->json([
            'success' => true,
            'token'   => $token,
            'user'    => $user,
        ]);
    }
    // User Login
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'login'    => 'required|string', // email OR phone
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors'  => $validator->errors()->first(),
            ], 422);
        }

        $loginField = filter_var($request->login, FILTER_VALIDATE_EMAIL)
            ? 'email'
            : 'phone';

        // Fetch user manually
        $user = User::where($loginField, $request->login)->first();

        // User not found
        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found',
            ], 404);
        }

                                     // ❌ Block non-admins explicitly
        if ($user->role != 'user') { // 2 = admin
            return response()->json([
                'success' => false,
                'message' => 'Access denied. User only.',
            ], 403);
        }

        // Password check
        if (! Hash::check($request->password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid credentials',
            ], 401);
        }

        // Generate JWT token manually
        $token = JWTAuth::fromUser($user);

        return response()->json([
            'success'    => true,
            'token'      => $token,
            'token_type' => 'Bearer',
            'user'       => $user,
        ]);
    }

    public function admin_register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|unique:users,email',
            'password' => 'required|min:6',
            'phone'    => 'required|digits:10|unique:users,phone',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors'  => $validator->errors()->first(),
            ], 422);
        }

        $user = User::create([
            'name'     => $request->name,
            'email'    => $request->email,
            'password' => Hash::make($request->password),
            'phone'    => $request->phone,
            'role'     => 'admin',
        ]);

        $token = JWTAuth::fromUser($user);

        return response()->json([
            'success' => true,
            'token'   => $token,
            'user'    => $user,
        ]);
    }

    public function admin_login(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'username' => 'required|string', // email OR phone
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors'  => $validator->errors()->first(),
            ], 422);
        }

        $loginField = filter_var($request->username, FILTER_VALIDATE_EMAIL)
            ? 'email'
            : 'phone';

        // Fetch user manually
        $user = User::where($loginField, $request->username)->first();

        // User not found
        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found',
            ], 404);
        }

        if ($user->role != 'admin') { // 2 = admin
            return response()->json([
                'success' => false,
                'message' => 'Access denied. Admins only.',
            ], 403);
        }

        // Password check
        if (! Hash::check($request->password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid credentials',
            ], 401);
        }

        // Generate JWT token manually
        $token = JWTAuth::fromUser($user);

        return response()->json([
            'success'    => true,
            'token'      => $token,
            'token_type' => 'Bearer',
            'user'       => $user,

        ]);
    }

    public function super_admin_login(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'username' => 'required|string', // email OR phone
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors'  => $validator->errors()->first(),
            ], 422);
        }

        $loginField = filter_var($request->username, FILTER_VALIDATE_EMAIL)
            ? 'email'
            : 'phone';

        // Fetch user manually
        $user = User::where($loginField, $request->username)->first();

        // User not found
        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found',
            ], 404);
        }

        if ($user->role != 'superadmin') { // 2 = admin
            return response()->json([
                'success' => false,
                'message' => 'Access denied. Admins only.',
            ], 403);
        }

        // Password check
        if (! Hash::check($request->password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid credentials',
            ], 401);
        }

        // Generate JWT token manually
        $token = JWTAuth::fromUser($user);

        return response()->json([
            'success'    => true,
            'token'      => $token,
            'token_type' => 'Bearer',
            'user'       => $user,

        ]);
    }

    public function sendOtp(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'email' => 'required | email | exists: users, email',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors'  => $validator->errors()->first(),
            ], 422);
        }

        $otp = rand(100000, 999999);

        DB::table('password_resets')->updateOrInsert(
            ['email' => $request->email],
            ['otp' => $otp, 'created_at' => now()]
        );

        dispatch(new SendPasswordResetOtpJob($request->email, $otp));

        return response()->json([
            'success' => true,
            'message' => 'OTP senttoyouremail',
        ]);
    }

    public function verifyOtp(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'email' => 'required | email',
            'otp'   => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors'  => $validator->errors()->first(),
            ], 422);
        }

        $record = DB::table('password_resets')
            ->where('email', $request->email)
            ->where('otp', $request->otp)
            ->first();

        if (! $record) {
            return response()->json(['message' => 'Invalid OTP'], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'OTP verified',
        ]);
    }

    public function resetPassword(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'email'    => 'required | email',
            'otp'      => 'required',
            'password' => 'required | min: 6',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors'  => $validator->errors()->first(),
            ], 422);
        }

        $record = DB::table('password_resets')
            ->where('email', $request->email)
            ->where('otp', $request->otp)
            ->first();

        if (! $record) {
            return response()->json(['message' => 'Invalid OTP'], 422);
        }

        User::where('email', $request->email)
            ->update(['password' => Hash::make($request->password)]);

        DB::table('password_resets')->where('email', $request->email)->delete();

        return response()->json([
            'success' => true,
            'message' => 'Password resetsuccessfully',
        ]);
    }

    public function profile()
    {
        return response()->json([
            'success' => true,
            'user'    => Auth::user(),
        ]);
    }

    public function update(Request $request)
    {
        $user = $request->user();

        /* ================= VALIDATION ================= */
        $validator = Validator::make($request->all(), [
            'name'          => 'required|string|max:255',
            'email'         => 'nullable|email|max:255|unique:users,email,' . $user->id,
            'profile_image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:4096',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        /* ================= UPDATE TEXT FIELDS ================= */
        $user->name  = $request->name;
        $user->email = $request->email ?? $user->email;

        /* ================= IMAGE → WEBP ================= */
        if ($request->hasFile('profile_image')) {

            $file = $request->file('profile_image');

            // 👉 temp source path
            $srcPath = $file->getRealPath();

            // 👉 destination path (storage/app/public/avatars)
            $fileName = Str::uuid() . '.webp';
            $destPath = storage_path('app/public/avatars/' . $fileName);

            // 👉 convert + resize (300x300 optional)
            WebpService::convert(
                $srcPath,
                $destPath,
                70,  // quality
                300, // width
                300  // height
            );

            // 👉 delete old avatar
            if ($user->avatar && Storage::disk('public')->exists($user->avatar)) {
                Storage::disk('public')->delete($user->avatar);
            }

            // 👉 save relative path
            $user->avatar = 'avatars/' . $fileName;
        }

        $user->save();

        /* ================= RESPONSE ================= */
        return response()->json([
            'success' => true,
            'user'    => [
                'id'     => $user->id,
                'name'   => $user->name,
                'email'  => $user->email,
                'phone'  => $user->phone,
                'avatar' => $user->avatar
                    ? asset('storage/' . $user->avatar)
                    : null,
            ],
        ]);
    }

    public function logout()
    {
        Auth::logout();

        return response()->json([
            'success' => true,
            'message' => 'Logged out successfully',
        ]);
    }

}