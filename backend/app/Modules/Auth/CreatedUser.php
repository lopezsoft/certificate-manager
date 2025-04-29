<?php

namespace App\Modules\Auth;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
class CreatedUser
{
    public static function create(Request $request): User
    {
        return User::create([
            'first_name'    => $request->first_name,
            'last_name'     => $request->last_name,
            'email'         => $request->email,
            'type_id'       => $request->type_id ?? 2,
            'password'      => Hash::make($request->password),
            'active'        => 1,
        ]);
    }
}
