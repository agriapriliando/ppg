<?php
// Data contoh dan rute untuk prototipe tampilan. Tidak melakukan autentikasi.
function e($value) { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }
function portal_url($role, $page = 'dashboard') { return 'portal.php?role=' . urlencode($role) . '&page=' . urlencode($page); }
$roleNames = ['pendaftar' => 'Pendaftar', 'operator' => 'Operator', 'admin' => 'Administrator'];
$menus = [
    'pendaftar' => [
        'dashboard' => ['Beranda Pendaftar', 'grid-1x2'], 'klaim' => ['Kepesertaan SK', 'patch-check'],
        'formulir' => ['Formulir Pendaftaran', 'file-earmark-text'], 'status' => ['Status & Perbaikan', 'clock-history'],
        'bukti' => ['Bukti Pendaftaran', 'printer'], 'akun' => ['Akun & Keamanan', 'person-gear'], 'bantuan' => ['Pusat Bantuan', 'headset']
    ],
    'operator' => [
        'dashboard' => ['Beranda Operator', 'grid-1x2'], 'antrean' => ['Antrean Verifikasi', 'list-check'],
        'klaim' => ['Klaim & WhatsApp', 'whatsapp'], 'peserta' => ['Peserta Penugasan', 'people'],
        'riwayat' => ['Riwayat Aktivitas', 'clock-history'], 'akun' => ['Akun & Keamanan', 'person-gear']
    ],
    'admin' => [
        'dashboard' => ['Ringkasan PMB', 'grid-1x2'], 'peserta' => ['Daftar Peserta SK', 'people'],
        'impor' => ['Impor SK', 'file-earmark-arrow-up'], 'penugasan' => ['Pembagian Operator', 'diagram-3'],
        'antrean' => ['Antrean Verifikasi', 'list-check'], 'klaim' => ['Klaim & WhatsApp', 'whatsapp'],
        'akun-pengguna' => ['Kelola Pengguna', 'person-badge'], 'periode' => ['Periode & Gelombang', 'calendar-range'],
        'referensi' => ['Referensi Pendidikan', 'mortarboard'], 'neo' => ['Neo Feeder', 'database-gear'],
        'audit' => ['Audit & Ekspor', 'shield-check'], 'pengaturan' => ['Pengaturan Aplikasi', 'sliders']
    ]
];
function panel_start($title, $subtitle = '') { echo '<section class="workspace-card mb-4"><div class="panel-heading"><h2>' . e($title) . '</h2>'; if ($subtitle) echo '<p>' . e($subtitle) . '</p>'; echo '</div><div class="panel-body">'; }
function panel_end() { echo '</div></section>'; }
function field($label, $model, $type = 'text', $required = false, $options = null) {
    $id = 'field-' . str_replace('.', '-', $model);
    echo '<div class="col-md-6"><label for="' . e($id) . '" class="form-label">' . e($label) . ($required ? ' <span class="text-danger">*</span>' : '') . '</label>';
    $attrs = ' id="' . e($id) . '" x-model="form.' . e($model) . '"' . ($required ? ' required' : '') . ' :disabled="locked"';
    if ($model === 'province') $attrs .= ' @change="form.city = \'\'; form.district = \'\'"';
    if ($options) { echo '<select class="form-select"' . $attrs . '><option value="">Pilih ' . e(strtolower($label)) . '</option>'; foreach ($options as $option) echo '<option>' . e($option) . '</option>'; echo '</select>'; }
    else echo '<input class="form-control" type="' . e($type) . '"' . $attrs . ($type === 'tel' ? ' inputmode="tel" pattern="[+0-9 ()-]{10,18}"' : '') . ($model === 'nik' ? ' inputmode="numeric" pattern="[0-9]{16}" minlength="16" maxlength="16"' : '') . ($model === 'nisn' ? ' inputmode="numeric" pattern="[0-9]{10}" maxlength="10"' : '') . '>';
    echo '</div>';
}
