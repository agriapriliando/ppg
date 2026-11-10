<?php

namespace App\Http\Controllers\Admin;

use App\Actions\RecordAudit;
use App\Enums\AccountState;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StaffAccountExportController extends Controller
{
    public function __invoke(Request $request, RecordAudit $audit): StreamedResponse
    {
        $actor = $request->user();
        abort_unless($actor?->isAdministrator(), 403);
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'role' => ['nullable', Rule::in([UserRole::Administrator->value, UserRole::Operator->value])],
            'status' => ['nullable', Rule::in([AccountState::Active->value, AccountState::Disabled->value])],
        ]);
        $search = trim($filters['search'] ?? '');
        $query = User::query()->whereIn('role', [UserRole::Administrator, UserRole::Operator])
            ->select(['id', 'name', 'login_id', 'email', 'phone_number', 'role', 'account_state', 'password', 'staff_password_copy'])
            ->when($search !== '', fn ($query) => $query->where(fn ($matches) => $matches
                ->where('name', 'like', "%{$search}%")->orWhere('login_id', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%")->orWhere('phone_number', 'like', "%{$search}%")
                ->when(ctype_digit($search), fn ($ids) => $ids->orWhere('id', (int) $search))))
            ->when(! empty($filters['role']), fn ($query) => $query->where('role', $filters['role']))
            ->when(! empty($filters['status']), fn ($query) => $query->where('account_state', $filters['status']))
            ->orderBy('name')->orderBy('id');
        $audit->handle($actor, 'Mengekspor daftar akun pengelola', $actor, ['search' => $search, 'role' => $filters['role'] ?? null, 'status' => $filters['status'] ?? null]);

        return response()->streamDownload(function () use ($query): void {
            $book = new Spreadsheet;
            try {
                $sheet = $book->getActiveSheet();
                $sheet->setTitle('Akun Pengelola');
                $sheet->fromArray(['ID Akun', 'Nama', 'ID Login', 'Email', 'Peran', 'Status', 'Password', 'Keterangan Password', 'No HP WhatsApp']);
                $index = 2;
                foreach ($query->cursor() as $user) {
                    $password = $user->exportableStaffPassword();
                    $values = [(string) $user->id, $user->name, $user->login_id ?? '', $user->email ?? '', $user->role->label(), $user->account_state->label(), $password ?? '', $password === null ? 'Tidak tersedia; password lama atau sudah berubah tanpa salinan.' : 'Tersedia dan sesuai password login saat ini.', $user->phone_number ?? ''];
                    foreach ($values as $column => $value) {
                        $sheet->setCellValueExplicit([$column + 1, $index], $value, DataType::TYPE_STRING);
                    }
                    $index++;
                }
                $sheet->freezePane('A2');
                $sheet->setAutoFilter('A1:I'.max(1, $index - 1));
                $sheet->getStyle('A1:I1')->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
                $sheet->getStyle('A1:I1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF4A154B');
                foreach (['A' => 12, 'B' => 32, 'C' => 24, 'D' => 34, 'E' => 20, 'F' => 20, 'G' => 24, 'H' => 58, 'I' => 24] as $column => $width) {
                    $sheet->getColumnDimension($column)->setWidth($width);
                }
                $sheet->getStyle('A1:I'.max(1, $index - 1))->getAlignment()->setWrapText(true);
                (new Xlsx($book))->save('php://output');
            } finally {
                $book->disconnectWorksheets();
            }
        }, 'akun-pengelola.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'private, no-store, max-age=0',
            'Pragma' => 'no-cache',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
