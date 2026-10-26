<?php

namespace App\Http\Controllers\Admin;

use App\Actions\RecordAudit;
use App\Http\Controllers\Controller;
use App\Models\ApplicationSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function edit(): View
    {
        return view('admin.settings', ['setting' => ApplicationSetting::query()->where('key', 'public')->first()]);
    }

    public function update(Request $request, RecordAudit $audit): RedirectResponse
    {
        $data = $request->validate([
            'helpdesk_name' => ['required', 'string', 'max:255'],
            'helpdesk_whatsapp' => ['nullable', 'regex:/^\+?[0-9]{8,15}$/'],
            'service_hours' => ['nullable', 'string', 'max:100'],
            'notice' => ['nullable', 'string', 'max:2000'],
            'version' => ['required', 'integer', 'min:0'],
        ], ['helpdesk_whatsapp.regex' => 'Gunakan nomor internasional, misalnya +62812…, tanpa spasi.', 'helpdesk_name.required' => 'Nama helpdesk wajib diisi.']);
        DB::transaction(function () use ($request, $data, $audit): void {
            ApplicationSetting::query()->firstOrCreate(['key' => 'public'], ['value' => []]);
            $setting = ApplicationSetting::query()->where('key', 'public')->lockForUpdate()->firstOrFail();
            if ($setting->version !== (int) $data['version']) {
                throw ValidationException::withMessages(['version' => 'Pengaturan telah berubah. Muat ulang halaman sebelum menyimpan.']);
            }
            unset($data['version']);
            $setting->value = $data;
            $setting->version++;
            $setting->updatedBy()->associate($request->user());
            $setting->save();
            $audit->handle($request->user(), 'Memperbarui informasi publik', $setting, ['version' => $setting->version]);
        });

        return redirect()->route('admin.settings')->with('status', 'Informasi publik berhasil diperbarui.');
    }
}
