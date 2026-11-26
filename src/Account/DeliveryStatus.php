<?php

declare(strict_types=1);

namespace FourStreet\Account;

enum DeliveryStatus
{
    case Sent;
    case Failed;
    case Throttled;
}
