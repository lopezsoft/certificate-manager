<?php

namespace App\Enums;

/**
 * @property $value
 */
enum ProfileFileManagerEnum: string
{
    case Client = 'Client';
    case Standard = 'Standard';
    case Seller = 'Seller';
    case Manager = 'Manager';
    case Admin = 'Admin';
    case User = 'User';
    case Query = 'Query';

    /**
     * @throws \Exception
     */
    public static  function getProfile(int $profileId): string
    {
        return match($profileId) {
            1   => self::Admin->value,
            2   => self::Standard->value,
            3   => self::Manager->value,
            4   => self::User->value,
            5   => self::Client->value,
            6   => self::Seller->value,
            7   => self::Query->value,
            default => throw new \Exception('Unexpected match value'),
        };
    }
}
