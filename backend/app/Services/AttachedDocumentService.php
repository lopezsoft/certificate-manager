<?php

namespace App\Services;

use App\Common\VerificationDigit;
use App\Models\business\Customer;
use App\Models\Company;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

class AttachedDocumentService
{
    public static function extractWhenExists($shipping): object
    {
        if (Storage::disk('attachment')->exists($shipping->attachedPath)){
           $content = base64_encode(Storage::disk('attachment')->get($shipping->attachedPath));
           $url     = Storage::disk('attachment')->url($shipping->attachedPath);
           $path    = $shipping->attachedPath;
        } else {
            $aws_main_path  = env('AWS_MAIN_PATH', 'test');
            $path           = "{$aws_main_path}/{$shipping->attachedPath}";
            if (Storage::cloud()->exists($path)) {
                $content = base64_encode(Storage::cloud()->get($path));
                $url     = Storage::cloud()->url($path);
                $path    = $shipping->attachedPath;
            }
        }
        return (object) [
            'path'      => $path ?? null,
            'url'       => $url ?? null,
            'data'      => $content ?? null,
        ];
    }
    public static function isExists($shipping): bool
    {
        $path   = $shipping->attachedPath ?? '.xml';
        if (is_null($path)) {
            $path = '.xml';
        };
        if (empty($path)) {
            $path = '.xml';
        };
        $exists = (Storage::disk('attachment')->exists($path));
        if (!$exists) {
            $aws_main_path  = env('AWS_MAIN_PATH', 'test');
            $path           = "{$aws_main_path}/{$shipping->attachedPath}";
            $exists         = Storage::cloud()->exists($path);
        }
        return $exists;
    }
    public static function isExistsZip($shipping): bool
    {
        $path   = $shipping->attachedZipPath ?? '.zip';
        if (is_null($path)) {
            $path = '.xml';
        };
        $exists = (Storage::disk('attachment')->exists($path));
        if (!$exists) {
            $aws_main_path  = env('AWS_MAIN_PATH', 'test');
            $path           = "{$aws_main_path}/{$shipping->attachedZipPath}";
            $exists         = Storage::cloud()->exists($path);
        }
        return $exists;
    }
    public static function getCustomer($dni): Object
    {
        $customer               = (object) Customer::query()->where('dni', $dni)->first()->getOriginal();
        $customerAll            = (array) $customer;

        $customer               = new User($customerAll);
        // Customer company
        $customer->company      = new Company($customerAll);
        $customer->company->dv  = VerificationDigit::getDigit(intval($dni));
        return $customer;
    }
}
