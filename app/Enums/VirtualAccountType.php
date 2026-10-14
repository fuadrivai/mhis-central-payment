<?php

namespace App\Enums;

enum VirtualAccountType: string
{
    case Fixed = 'fixed';
    case Dynamic = 'dynamic';
}
