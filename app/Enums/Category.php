<?php

namespace App\Enums;

enum Category: string
{
    case Billing = 'billing';
    case Technical = 'technical';
    case Account = 'account';
    case General = 'general';
}
