@props(['settings'])
@if(!empty($settings['notice']) && in_array($settings['notice_placement'] ?? 'both', ['both', 'dashboard'], true))
    <section class="public-announcement-card mb-4" role="region" aria-label="Pengumuman Penting">
        <div class="d-flex align-items-start gap-3">
            <div class="announcement-icon-badge flex-shrink-0" aria-hidden="true"><i class="bi bi-megaphone-fill"></i></div>
            <div class="flex-grow-1" style="min-width: 0;">
                <h2 class="h6 fw-bold mb-2"><span class="announcement-tag">PENGUMUMAN PENTING</span></h2>
                <x-notice-text :text="$settings['notice']" />
            </div>
        </div>
    </section>
@endif
