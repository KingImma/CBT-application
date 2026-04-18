<?php

namespace App\Jobs;

use App\Models\Tenant;
use App\Mail\WelcomeSchoolMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendWelcomeEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly Tenant $tenant,
        public readonly string $adminEmail,
        public readonly string $adminName
    ) {}

    public function handle(): void
    {
        Mail::to($this->adminEmail)->send(
            new WelcomeSchoolMail($this->tenant, $this->adminName)
        );
    }
}