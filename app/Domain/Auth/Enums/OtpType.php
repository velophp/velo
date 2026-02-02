<?php

namespace App\Domain\Auth\Enums;

enum OtpType: string
{
    case PASSWORD_RESET = 'password_reset';
    case EMAIL_VERIFICATION = 'email_verification';
    case AUTHENTICATION = 'authentication';
    case EMAIL_CHANGE = 'email_change';
}
