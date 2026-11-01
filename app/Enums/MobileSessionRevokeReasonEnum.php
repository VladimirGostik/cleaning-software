<?php

declare(strict_types=1);

namespace App\Enums;

enum MobileSessionRevokeReasonEnum: string
{
    case Logout = 'logout';
    case PasswordChanged = 'password_changed';
    case PasswordReset = 'password_reset';
    case AccessLost = 'access_lost';
    case ReuseDetected = 'reuse_detected';
    case Expired = 'expired';
    case MembershipLost = 'membership_lost';
}
