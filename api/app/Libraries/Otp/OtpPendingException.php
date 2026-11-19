<?php

namespace App\Libraries\Otp;

class OtpPendingException extends \RuntimeException {
    private int $remainingSeconds;

    public function __construct(int $remainingSeconds) {
        $this->remainingSeconds = $remainingSeconds;
        parent::__construct(lang('Otp.pending_active', [$remainingSeconds]));
    }

    public function getRemainingSeconds(): int {
        return $this->remainingSeconds;
    }
}
