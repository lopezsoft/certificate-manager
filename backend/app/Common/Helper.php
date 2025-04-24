<?php

use Carbon\Carbon;

function formatNameToUpperCase($name): string
{
    return mb_strtoupper($name, "UTF-8");
}
function extractVariationsValue($array): string
{
    return collect($array)->map(function ($row) {
        return "{$row->term_name}";
    })->join(' / ');
}
function transformDate($date = null): string
{
    return date('d-m-Y', strtotime(str_replace('/', '-', $date ?? date('d-m-Y'))));
}
function transformDateTime($date = null): string
{
    return date('d-m-Y h:i:s a', strtotime(str_replace('/', '-', $date ?? date('d-m-Y h:i:s a'))));
}

function transformTime($date = null): string
{
    return date('H:i:s', strtotime(str_replace('/', '-', $date ?? date('H:i:s'))));
}
function getDiffInMonths($date1, $date2): int
{
    $dateFrom = Carbon::parse($date1);
    $dateUp = Carbon::parse($date2);
    return $dateFrom->diffInMonths($dateUp);
}

/**
 * Check if the DNI is a final consumer
 * @param $dni
 * @return bool
 */
function isFinalConsumer($dni): bool
{
    return $dni === '222222222222';
}

/**
 * Check if the document is a support document - 05 or 95
 * @param string $code
 * @return bool
 */
function isSupportDocument(string $code): bool
{
    return in_array($code, ['05', '95'], true);
}
