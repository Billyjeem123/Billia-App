<?php

namespace App\Services;

use App\Events\AccountRegistered;
use App\Helpers\Utility;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UserService
{
    public function processOnboarding(array $validatedData)
    {
        return DB::transaction(function () use ($validatedData) {
            $user = User::create([
                'first_name' => $validatedData['first_name'],
                'last_name'  => $validatedData['last_name'],
                'email'      => $validatedData['email'],
                'password'   => Hash::make($validatedData['password']),
                'phone'      => $validatedData['phone_number'] ?? null,
                'role'       => 'user',
                'username'   => $validatedData['username'] ?? null,
                'pin'        => Hash::make($validatedData['transaction_pin']),
            ]);

            $user->assignRole('user');

//            $user->wallet()->create([
//                'user_id' => $user->id,
//                'balance' => 0,
//            ]);

//            event(new AccountRegistered($user));

            return ['user' => new UserResource($user) , 'token' => $user->createToken('authToken')->plainTextToken,];
        });
    }


}
