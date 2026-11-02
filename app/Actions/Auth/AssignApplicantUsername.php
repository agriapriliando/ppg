<?php

namespace App\Actions\Auth;

use App\Models\ApplicationSetting;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AssignApplicantUsername
{
    public function handle(User $user): User
    {
        return DB::transaction(function () use ($user): User {
            ApplicationSetting::query()->firstOrCreate(['key' => 'applicant-username-control'], ['value' => []]);
            ApplicationSetting::query()->where('key', 'applicant-username-control')->lockForUpdate()->firstOrFail();
            $base = substr(Str::slug($user->name, '.'), 0, 54);
            $base = rtrim($base, '.') ?: 'peserta';
            $username = $base;
            $number = 2;
            while (User::query()->where('login_id', $username)->when($user->exists, fn ($query) => $query->where('id', '!=', $user->id))->exists()) {
                $username = $base.$number++;
            }
            $user->login_id = $username;
            $user->save();

            return $user;
        });
    }
}
