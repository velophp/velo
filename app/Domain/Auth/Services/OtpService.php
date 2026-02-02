<?php

namespace App\Domain\Auth\Services;

class OtpService
{
    public function generate(int $length = 6): array
    {
        $otp = str_pad((string) random_int(0, pow(10, $length) - 1), $length, '0', STR_PAD_LEFT);
        $hashed = hash('sha256', $otp);

        return [$otp, $hashed];
    }
}
