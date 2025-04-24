<?php

namespace App\Common;

use PhpOffice\PhpSpreadsheet\Shared\Date;

class DateFunctions
{
    public static function getRealTimeXls($date = null): string
    {
        $tz         = new \DateTimeZone('America/Bogota');

        $localDate  = new \DateTime(now(), $tz);
        $localDate  = $localDate->format('H:i:s');

        if ($date) {
            $objetDateTime  = Date::excelToDateTimeObject($date, $tz);
            $dateTime       = $objetDateTime->getTimestamp();
            $localDate      = date('H:i:s', $dateTime);
        }
        return $localDate;
    }

    public static function getRealDateXls($date = null): string
    {
        $tz         = new \DateTimeZone('America/Bogota');
        $localDate  = new \DateTime(now(), $tz);
        $localDate  = $localDate->format('Y-m-d');

        if ($date && strlen($date) > 3) {
            $objetDateTime  = Date::excelToDateTimeObject($date, $tz);
            $dateTime       = $objetDateTime->getTimestamp();
            $localDate      = date('Y-m-d', $dateTime);
        }
        return $localDate;
    }

    public static function getRealDate($date = null, $validateYear = false): string
    {
        if ($validateYear) {
            $date       = date('Y-m-d', strtotime(str_replace('/', '-', $date ?? date('Y-m-d'))));
            $yearNow    = intval(date('Y', strtotime(date('Y-m-d'))));
            $yearDate   = intval(date('Y', strtotime($date)));
            if ($yearDate < $yearNow) {
                $date            = date('Y-m-d', strtotime(date('Y-m-d')));
            }
            return $date;
        } else {
            return date('Y-m-d', strtotime(str_replace('/', '-', $date ?? date('Y-m-d'))));
        }
    }

    public static function transformDate($date = null): string
    {
        return date('Y-m-d', strtotime(str_replace('/', '-', $date ?? date('Y-m-d'))));
    }

    public static function transformTime($start_time): string
    {
        return date('H:i:s', strtotime($start_time));
    }
}
