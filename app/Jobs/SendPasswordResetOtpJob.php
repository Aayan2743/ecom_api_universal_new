<?php
namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendPasswordResetOtpJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public string $email;
    public int $otp;

    public function __construct(string $email, int $otp)
    {
        $this->email = $email;
        $this->otp   = $otp;
    }

    public function handle(): void
    {
        Mail::raw(
            "Your OTP for password reset is: {$this->otp}\nThis OTP will expire in 10 minutes.",
            function ($mail) {
                $mail->to($this->email)
                    ->subject('Password Reset OTP');
            }
        );
    }
}
