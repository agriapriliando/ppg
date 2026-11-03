<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditEvent;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AuditController extends Controller
{
    public function __invoke(Request $request): View
    {
        $data = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'in:10,25,50,100'],
        ]);
        $search = $data['search'] ?? '';
        $perPage = isset($data['per_page']) ? (int) $data['per_page'] : 25;

        return view('admin.audit', [
            'search' => $search,
            'perPage' => $perPage,
            'events' => AuditEvent::query()
                ->with('actor')
                ->when(! $request->user()?->isAdministrator(), fn ($query) => $query->where('actor_id', $request->user()?->id))
                ->when($search !== '', fn ($query) => $query->where('action', 'like', '%'.$search.'%'))
                ->orderByDesc('id')
                ->paginate($perPage)
                ->withQueryString(),
        ]);
    }
}
