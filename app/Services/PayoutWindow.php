<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use DateTimeInterface;

class PayoutWindow
{
    public function forApproval(DateTimeInterface $approvedAt): CarbonImmutable
    {
        $local = CarbonImmutable::instance($approvedAt)->setTimezone('Africa/Nairobi');
        // Approval at 10:15 waits through the next hour, reaching 11:59.
        $candidate = $local->startOfHour()->addHour()->addMinutes(59);
        if ($candidate->hour < 8) {
            $candidate = $candidate->setTime(8, 59);
        } elseif ($candidate->hour > 19) {
            $candidate = $candidate->addDay()->setTime(8, 59);
        }
        return $candidate->utc();
    }
}
