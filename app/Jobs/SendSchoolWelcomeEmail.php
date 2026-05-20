<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Mail\SchoolWelcomeMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendSchoolWelcomeEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(
        public readonly string $adminEmail,
        public readonly string $adminName,
        public readonly string $schoolName,
        public readonly string $handle,
        public readonly string $loginUrl,
    ) {}

    public function handle(): void
    {
        Mail::to($this->adminEmail)->send(new SchoolWelcomeMail(
            schoolName: $this->schoolName,
            adminName: $this->adminName,
            adminEmail: $this->adminEmail,
            handle: $this->handle,
            loginUrl: $this->loginUrl,
        ));
    }
}
