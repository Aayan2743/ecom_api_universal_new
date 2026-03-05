<?php
namespace App\Jobs;

use App\Mail\OrganizationPasswordMail;
use App\Models\Organization;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendPasswordJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 3;
    public int $timeout = 120;

    public function __construct(
        public Organization $organization,
        public string $password
    ) {}

    public function handle(): void
    {
        Mail::to($this->organization->email)
            ->send(
                new OrganizationPasswordMail(
                    $this->organization,
                    $this->password
                )
            );
    }
}
