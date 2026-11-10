<?php

namespace App\Http\Controllers;

use App\Actions\RecordAudit;
use App\Actions\UpdateUserPhone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProfileController extends Controller
{
    public function updatePhone(Request $request, UpdateUserPhone $update): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user !== null, 403);
        try {
            $update->handle($user, $user, $request->only('phone_number'), true);
        } catch (ValidationException $exception) {
            $exception->errorBag = 'accountPhone';
            throw $exception;
        }

        return back()->with('status', 'Nomor HP WhatsApp aktif berhasil disimpan.');
    }

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
