<?php

declare(strict_types=1);

namespace App\Domains\Exams\Actions\Seb;

use App\Domains\Exams\Exceptions\SebSessionNotFoundException;
use App\Domains\Exams\Support\SebPendingSessionStore;

final class GetCurrentSebExam
{
    public function __construct(private SebPendingSessionStore $sessionStore) {}

    public function execute(string $studentId): string
    {
        $session = $this->sessionStore->read($studentId);

        throw_if($session === null, SebSessionNotFoundException::class);

        $alreadyConsumed = $session['consumed_at'] !== null;

        if ($alreadyConsumed) {
            $stillInGrace = $this->sessionStore->isWithinGraceWindow($session['consumed_at']);
            throw_unless($stillInGrace, SebSessionNotFoundException::class);

            return $session['exam_id'];
        }

        $this->sessionStore->markConsumed($studentId);

        return $session['exam_id'];
    }
}
