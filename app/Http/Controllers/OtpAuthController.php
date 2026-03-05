<?php
namespace App\Http\Controllers;

use App\Models\Otp;
use App\Models\User;
use App\Services\Messenger360Service;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Facades\JWTAuth;

class OtpAuthController extends Controller
{

    protected Messenger360Service $messenger;

    public function __construct(Messenger360Service $messenger)
    {
        $this->messenger = $messenger;
    }
    public function sendOtp(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'identifier' => 'required', // phone or email
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors()->first(),
            ], 422);
        }

        $identifier = $request->identifier;

        /* ================= FIND OR CREATE USER ================= */

        if (filter_var($identifier, FILTER_VALIDATE_EMAIL)) {
            $user = User::firstOrCreate(
                ['email' => $identifier],
                [
                    'name'     => 'User',
                    'password' => bcrypt('123456'),
                    'phone'    => null,
                ]
            );
        } else {
            // sanitize phone
            $phone = preg_replace('/[^0-9]/', '', $identifier);

            $user = User::firstOrCreate(
                ['phone' => $phone],
                [
                    'name'     => 'User',
                    'password' => bcrypt('default_password'),
                    'email'    => null,
                ]
            );

            $identifier = $phone;
        }

        /* ================= GENERATE OTP ================= */

        $otp = rand(100000, 999999);

        Otp::updateOrCreate(
            ['identifier' => $identifier],
            [
                                               // 'otp'         => bcrypt($otp), // 🔐 secure
                'otp'         => bcrypt($otp), // 🔐 secure
                'expires_at'  => now()->addMinutes(5),
                'is_verified' => false,
            ]
        );

        /* ================= SEND WHATSAPP ================= */

        if (is_numeric($identifier)) {
            $message = "Your OTP for login is: $otp. Valid for 5 minutes.";
            $this->messenger->send($identifier, $message);
        }

        /* ================= SEND EMAIL ================= */

        if (filter_var($identifier, FILTER_VALIDATE_EMAIL)) {
            \Mail::raw("Your OTP is: $otp (Valid for 5 minutes)", function ($message) use ($identifier) {
                $message->to($identifier)->subject('Your Login OTP');
            });
        }

        return response()->json([
            'status'  => true,
            'message' => 'OTP sent successfully',
        ]);
    }

    public function verifyOtp(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'identifier' => 'required',
            'otp'        => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors()->first(),
            ], 422);
        }

        $record = Otp::where('identifier', $request->identifier)
            ->where('expires_at', '>', now())
            ->first();

        if (! $record || ! Hash::check($request->otp, $record->otp)) {
            return response()->json([
                'status'  => false,
                'message' => 'Invalid or expired OTP',
            ]);
        }

        $user = User::where('email', $request->identifier)
            ->orWhere('phone', $request->identifier)
            ->first();

        $record->update(['is_verified' => true]);

        /* ================= SEND WELCOME MESSAGE ================= */

        if (! $user->is_welcome_sent && $user->phone) {

            $message = "🎉 Welcome to Hamsini Silks!\n\n"
                . "Your account has been successfully verified.\n"
                . "We are excited to serve you ❤️\n\n"
                . "Happy Shopping 🛍️";

            $this->messenger->send($user->phone, $message);

            $user->update([
                'is_welcome_sent' => true,
            ]);
        }

        // $token = $user->createToken('otp_login')->plainTextToken;

        $token = JWTAuth::fromUser($user);

        return response()->json([
            'status'  => true,
            'message' => 'Login successful',
            'token'   => $token,
            'user'    => $user,
        ]);
    }

}
