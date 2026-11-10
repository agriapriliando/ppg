<div>
    <div role="status" aria-live="polite" class="small text-success px-3">{{ $message }}</div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light"><tr><th class="text-center">Urutan</th><th>Nama Tombol & Deskripsi</th><th>URL Tujuan</th><th>Warna</th><th>Lokasi Tampil</th><th class="text-end pe-3">Aksi</th></tr></thead>
            <tbody>
                @forelse($links as $link)
                    <tr wire:key="quick-link-{{ $link->id }}">
                        <td class="text-center">
                            <div class="d-inline-flex align-items-center gap-1">
                                <span class="badge text-bg-light border text-muted me-1">{{ $loop->iteration }}</span>
                                <div class="btn-group-vertical btn-group-sm">
                                    <button type="button" wire:click="moveUp({{ $link->id }})" wire:loading.attr="disabled" wire:target="moveUp,moveDown" class="btn btn-sm btn-outline-secondary p-0 px-1" aria-label="Naikkan {{ $link->title }}" title="Pindah ke Atas" @disabled($loop->first)><i class="bi bi-arrow-up" aria-hidden="true"></i></button>
                                    <button type="button" wire:click="moveDown({{ $link->id }})" wire:loading.attr="disabled" wire:target="moveUp,moveDown" class="btn btn-sm btn-outline-secondary p-0 px-1" aria-label="Turunkan {{ $link->title }}" title="Pindah ke Bawah" @disabled($loop->last)><i class="bi bi-arrow-down" aria-hidden="true"></i></button>
                                </div>
                            </div>
                        </td>
                        <td><strong class="d-block text-dark"><i class="bi {{ $link->icon ?: 'bi-box-arrow-up-right' }} me-1" aria-hidden="true"></i>{{ $link->title }}</strong>@if($link->description)<small class="text-muted">{{ $link->description }}</small>@endif</td>
                        <td><a href="{{ $link->url }}" target="_blank" rel="noopener noreferrer" class="small text-break">{{ $link->url }}</a></td>
                        <td>
                            @php($color = match($link->button_color) { 'purple' => '#6a3283', 'gold' => '#eab308', 'blue' => '#2563eb', 'green' => '#16a34a', 'teal' => '#0d9488', default => '#334155' })
                            <span class="badge" style="background-color: {{ $color }}; color: {{ $link->button_color === 'gold' ? '#000' : '#fff' }}">{{ ucfirst($link->button_color) }}</span>
                        </td>
                        <td><span @class(['badge border', 'bg-secondary-subtle text-secondary' => !$link->is_active || $link->placement === 'hidden', 'bg-success-subtle text-success' => $link->is_active && $link->placement !== 'hidden'])>{{ $link->placementLabel() }}</span></td>
                        <td class="text-end pe-3">
                            <div class="d-inline-flex gap-1">
                                <button type="button" @click="$dispatch('edit-quick-link', {link: @js($link)})" class="btn btn-sm btn-outline-purple" title="Edit Tautan" aria-label="Edit {{ $link->title }}"><i class="bi bi-pencil-square" aria-hidden="true"></i></button>
                                <form method="post" action="{{ route('admin.quick-links.destroy', $link) }}" onsubmit="return confirm('Hapus tautan cepat ini?')">
                                    @csrf @method('delete')
                                    <button type="submit" class="btn btn-sm btn-outline-danger" aria-label="Hapus {{ $link->title }}"><i class="bi bi-trash" aria-hidden="true"></i></button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-muted py-4">Belum ada tautan cepat.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
