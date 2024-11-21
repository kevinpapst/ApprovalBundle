<?php

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\ApprovalBundle\Toolbox;

use DateTime;
use Symfony\Contracts\Translation\TranslatorInterface;

final class Formatting
{
    public function __construct(private readonly TranslatorInterface $translator)
    {
    }

    public function parseDate(\DateTimeInterface $dateTime): string
    {
        $dateTime = DateTime::createFromInterface($dateTime);
        $weekNumber = $dateTime->format('W');

        if ($dateTime->format('D') === 'Mon') {
            $startWeekDay = $dateTime->format('d.m.Y');
        } else {
            $startWeekDay = (clone $dateTime)->modify('last monday')->format('d.m.Y');
        }

        $endWeekDay = (clone $dateTime)->modify('next sunday')->format('d.m.Y');

        return $dateTime->format('F Y') . ' - ' . $this->translator->trans('agendaWeek') . ' ' . $weekNumber . ' [' . $startWeekDay . ' - ' . $endWeekDay . ']';
    }

    public function formatDuration(int $duration): string
    {
        $prefix = $duration < 0 ? '-' : '';
        $mins = abs($duration) / 60;
        $hours = floor($mins / 60);
        $mins = $mins - ($hours * 60);
        $preZero = $mins < 9 ? '0' : '';

        return $prefix . $hours . ':' . $preZero . $mins;
    }
}
