<?php

namespace App\Http\Controllers;

use App\Actions\RecordAudit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProfileController extends Controller
{
    public function update(Request $request, RecordAudit $audit): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:255']]);
        $user = $request->user();
        abort_unless($user !== null, 403);
        DB::transaction(function () use ($user, $data, $audit): void {
            $user->name = trim($data['name']);
            $user->save();
            $audit->handle($user, 'Memperbarui nama tampilan akun', $user);
        });

        return back()->with('status', 'Nama tampilan akun diperbarui. Nama SK dan biodata tetap menggunakan catatan masing-masing.');
    }
}
