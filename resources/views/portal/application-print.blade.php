<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Formulir Pendaftaran - {{ $application->application_number }} - {{ $application->participant->name_on_sk }}</title>
    @vite(['resources/css/app.css'])
    <style>
        :root {
            --kop-line: #1b072b;
        }
        body {
            background-color: #f6f3f8;
            color: #212529;
            font-family: system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            margin: 0;
            padding: 0;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
        .print-toolbar {
            position: sticky;
            top: 0;
            z-index: 1000;
            background: #ffffff;
            border-bottom: 1px solid #e2d8ec;
            padding: 12px 24px;
            box-shadow: 0 4px 12px rgba(42, 8, 69, 0.08);
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
        }
        .print-container {
            max-width: 860px;
            margin: 24px auto 40px;
            background: #ffffff;
            padding: 38px 45px;
            border-radius: 8px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            border: 1px solid #e9e2f0;
        }
        .kop-wrapper {
            display: flex;
            align-items: center;
            gap: 18px;
            padding-bottom: 14px;
            border-bottom: 3px double var(--kop-line);
            margin-bottom: 20px;
        }
        .kop-logo {
            width: 72px;
            height: 72px;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #4A154B, #6a1b9a);
            color: #ffffff;
            border-radius: 12px;
            font-size: 2.2rem;
        }
        .kop-text {
            flex: 1;
            text-align: center;
        }
        .kop-text h1 {
            font-size: 1rem;
            font-weight: 800;
            letter-spacing: 0.04em;
            margin: 0;
            text-transform: uppercase;
        }
        .kop-text h2 {
            font-size: 1.15rem;
            font-weight: 800;
            margin: 2px 0;
            text-transform: uppercase;
            color: #4A154B;
        }
        .kop-text h3 {
            font-size: 0.85rem;
            font-weight: 700;
            margin: 2px 0 4px;
            text-transform: uppercase;
        }
        .kop-text p {
            font-size: 0.72rem;
            margin: 0;
            color: #495057;
            line-height: 1.4;
        }
        .form-header-badge {
            text-align: center;
            margin-bottom: 22px;
        }
        .form-header-badge h4 {
            font-size: 1.1rem;
            font-weight: 800;
            letter-spacing: 0.05em;
            text-transform: uppercase;
            color: #301044;
            margin: 0 0 4px;
        }
        .form-header-badge p {
            font-size: 0.78rem;
            color: #6c757d;
            margin: 0;
        }
        .meta-summary-table {
            width: 100%;
            margin-bottom: 20px;
            border-collapse: collapse;
        }
        .meta-summary-table td {
            padding: 5px 8px;
            font-size: 0.8rem;
            vertical-align: top;
        }
        .meta-summary-table td.label-col {
            width: 28%;
            font-weight: 600;
            color: #495057;
        }
        .meta-summary-table td.colon-col {
            width: 2%;
        }
        .meta-summary-table td.val-col {
            width: 70%;
            font-weight: 600;
            color: #212529;
        }
        .section-header {
            background-color: #f4ecf8;
            border-left: 4px solid #6a1b9a;
            padding: 6px 12px;
            font-size: 0.82rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.03em;
            color: #4A154B;
            margin-top: 18px;
            margin-bottom: 10px;
            break-after: avoid;
        }
        .bio-table {
            width: 100%;
            margin-bottom: 12px;
            border-collapse: collapse;
        }
        .bio-table tr {
            border-bottom: 1px solid #f1e9f6;
        }
        .bio-table td {
            padding: 5px 8px;
            font-size: 0.78rem;
            line-height: 1.45;
            vertical-align: top;
        }
        .bio-table td.label-col {
            width: 30%;
            color: #495057;
            font-weight: 500;
        }
        .bio-table td.colon-col {
            width: 2%;
            color: #6c757d;
        }
        .bio-table td.val-col {
            width: 68%;
            color: #1a1a1a;
            font-weight: 600;
        }
        .docs-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 8px;
            margin-bottom: 18px;
        }
        .docs-table th, .docs-table td {
            border: 1px solid #dcd1e6;
            padding: 6px 10px;
            font-size: 0.75rem;
        }
        .docs-table th {
            background-color: #fbf8fd;
            color: #4A154B;
            font-weight: 700;
            text-align: left;
        }
        .docs-table tbody tr:nth-child(even) {
            background-color: #faf7fc;
        }
        .statement-box {
            border: 1px solid #e0d5eb;
            background: #faf8fc;
            padding: 10px 14px;
            border-radius: 6px;
            margin: 18px 0;
            font-size: 0.75rem;
            line-height: 1.6;
            color: #495057;
        }
        .signature-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
            margin-top: 24px;
            break-inside: avoid;
        }
        .signature-box {
            text-align: center;
            font-size: 0.78rem;
        }
        .signature-space {
            height: 65px;
        }
        .signature-name {
            font-weight: 700;
            text-decoration: underline;
            margin-bottom: 2px;
        }
        .status-tag {
            display: inline-block;
            padding: 2px 8px;
            font-size: 0.7rem;
            font-weight: 700;
            border-radius: 4px;
            border: 1px solid #ced4da;
        }

        @media print {
            .no-print {
                display: none !important;
            }
            body {
                background: #ffffff !important;
                margin: 0 !important;
                padding: 0 !important;
            }
            .print-container {
                box-shadow: none !important;
                border: none !important;
                margin: 0 !important;
                padding: 0 !important;
                max-width: 100% !important;
                width: 100% !important;
            }
            @page {
                size: A4 portrait;
                margin: 12mm 15mm 12mm 15mm;
            }
            .section-header {
                background-color: #f1f1f1 !important;
                color: #000000 !important;
                border-left-color: #333333 !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            .docs-table th {
                background-color: #f1f1f1 !important;
                color: #000000 !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
        }
    </style>
</head>
<body>

    <!-- Toolbar Atas (Disembunyikan saat cetak) -->
    <div class="print-toolbar no-print">
        <div class="d-flex align-items-center gap-2">
            <span class="badge bg-purple px-2 py-1 text-white fw-bold">PMB PPG IAKN</span>
            <span class="small text-muted">Pratinjau Formulir Cetak & Berkas</span>
        </div>
        <div class="d-flex align-items-center gap-2">
            <button type="button" class="btn btn-primary d-inline-flex align-items-center gap-2 px-3 fw-bold shadow-sm" onclick="window.print()">
                <i class="bi bi-printer-fill" aria-hidden="true"></i>
                <span>Cetak Formulir (Ctrl + P)</span>
            </button>
            <button type="button" class="btn btn-outline-secondary d-inline-flex align-items-center gap-1" onclick="window.close()">
                <i class="bi bi-x-circle" aria-hidden="true"></i>
                <span>Tutup</span>
            </button>
        </div>
    </div>

    <!-- Lembar Cetak Formulir Biodata -->
    <div class="print-container">
        <!-- Kop Surat Resmi -->
        <header class="kop-wrapper">
            <div class="kop-logo">
                <i class="bi bi-mortarboard-fill"></i>
            </div>
            <div class="kop-text">
                <h1>Kementerian Agama Republik Indonesia</h1>
                <h2>Institut Agama Kristen Negeri (IAKN) Palangka Raya</h2>
                <h3>Panitia Penerimaan Mahasiswa Baru - Program Studi PPG</h3>
                <p>Jl. Tampung Penyang, RTA Milono Km. 6, Kota Palangka Raya, Kalimantan Tengah 73111<br>Laman Resmi: ppg.iaknpky.ac.id · Pos-el: pmb.ppg@iaknpky.ac.id</p>
            </div>
        </header>

        <!-- Judul Dokumen -->
        <div class="form-header-badge">
            <h4>Formulir Pendaftaran & Biodata Calon Mahasiswa</h4>
            <p>Tahun Akademik {{ $application->period->academic_year ?? date('Y') }} · {{ $application->period->name }}</p>
        </div>

        <!-- Identitas Pokok Pendaftaran -->
        <table class="meta-summary-table">
            <tr>
                <td class="label-col">Nomor Pendaftaran PMB</td>
                <td class="colon-col">:</td>
                <td class="val-col"><span style="font-family: monospace; font-size: 0.95rem; color: #4A154B;">{{ $application->application_number }}</span></td>
            </tr>
            <tr>
                <td class="label-col">Tanggal Pendaftaran Pertama</td>
                <td class="colon-col">:</td>
                <td class="val-col">{{ $application->submitted_at?->timezone('Asia/Jakarta')->translatedFormat('d F Y H:i') ?? 'Belum Dikirim' }} WIB</td>
            </tr>
            <tr>
                <td class="label-col">Status Pendaftaran</td>
                <td class="colon-col">:</td>
                <td class="val-col">
                    <span class="status-tag">{{ $application->status->label() }}</span>
                    @if($application->submission_revision > 1)
                        <span class="text-muted ms-1">(Revisi ke-{{ $application->submission_revision }})</span>
                    @endif
                </td>
            </tr>
        </table>

        <!-- Bagian I: Kepesertaan SK -->
        <div class="section-header">I. Data Kepesertaan SK Kementerian / LPTK</div>
        <table class="bio-table">
            <tr>
                <td class="label-col">Nama Sesuai SK Resmi</td>
                <td class="colon-col">:</td>
                <td class="val-col">{{ $application->participant->name_on_sk }}</td>
            </tr>
            <tr>
                <td class="label-col">Nomor SK Penetapan</td>
                <td class="colon-col">:</td>
                <td class="val-col">{{ $application->participant->sk_number }}</td>
            </tr>
            <tr>
                <td class="label-col">Nomor Urut dalam SK</td>
                <td class="colon-col">:</td>
                <td class="val-col">Urut ke-{{ $application->participant->sk_order }}</td>
            </tr>
            <tr>
                <td class="label-col">Nomor Pengenal Resmi (ID)</td>
                <td class="colon-col">:</td>
                <td class="val-col">{{ $application->participant->official_participant_id ?? '—' }}</td>
            </tr>
            <tr>
                <td class="label-col">Operator Pendamping</td>
                <td class="colon-col">:</td>
                <td class="val-col">{{ $application->participant->assignedOperator?->name ?? 'Belum Ditugaskan' }}</td>
            </tr>
        </table>

        <!-- Bagian II: Biodata Pribadi -->
        <div class="section-header">II. Biodata Pribadi Calon Mahasiswa</div>
        <table class="bio-table">
            <tr>
                <td class="label-col">Nama Lengkap (Sesuai Ijazah)</td>
                <td class="colon-col">:</td>
                <td class="val-col">{{ $values['full_name'] ?? $application->participant->name_on_sk }}</td>
            </tr>
            <tr>
                <td class="label-col">Nomor Induk Kependudukan (NIK)</td>
                <td class="colon-col">:</td>
                <td class="val-col">{{ $values['nik'] ?? '—' }}</td>
            </tr>
            <tr>
                <td class="label-col">Nomor Induk Siswa Nasional (NISN)</td>
                <td class="colon-col">:</td>
                <td class="val-col">{{ $values['nisn'] ?? '—' }}</td>
            </tr>
            <tr>
                <td class="label-col">Tempat, Tanggal Lahir</td>
                <td class="colon-col">:</td>
                <td class="val-col">
                    {{ $values['birth_place'] ?? '—' }}, 
                    {{ !empty($values['birth_date']) ? \Carbon\Carbon::parse($values['birth_date'])->translatedFormat('d F Y') : '—' }}
                </td>
            </tr>
            <tr>
                <td class="label-col">Jenis Kelamin</td>
                <td class="colon-col">:</td>
                <td class="val-col">{{ $display['gender_code'] ?? ($values['gender_code'] ?? '—') }}</td>
            </tr>
            <tr>
                <td class="label-col">Agama</td>
                <td class="colon-col">:</td>
                <td class="val-col">{{ $display['religion_code'] ?? ($values['religion_code'] ?? '—') }}</td>
            </tr>
            <tr>
                <td class="label-col">Kewarganegaraan</td>
                <td class="colon-col">:</td>
                <td class="val-col">{{ $display['nationality_code'] ?? ($values['nationality_code'] ?? '—') }}</td>
            </tr>
            <tr>
                <td class="label-col">Nama Ibu Kandung</td>
                <td class="colon-col">:</td>
                <td class="val-col">{{ $values['mother_name'] ?? '—' }}</td>
            </tr>
        </table>

        <!-- Bagian III: Kontak & Alamat -->
        <div class="section-header">III. Kontak & Alamat Tempat Tinggal</div>
        <table class="bio-table">
            <tr>
                <td class="label-col">Nomor WhatsApp Aktif</td>
                <td class="colon-col">:</td>
                <td class="val-col">
                    {{ $values['whatsapp_number'] ?? '—' }}
                    @if($verified)
                        <span class="text-success small ms-2"><i class="bi bi-check-circle-fill"></i> Terverifikasi</span>
                    @endif
                </td>
            </tr>
            <tr>
                <td class="label-col">Email Kontak / Akun</td>
                <td class="colon-col">:</td>
                <td class="val-col">{{ $values['contact_email'] ?? ($application->user->email ?? '—') }}</td>
            </tr>
            <tr>
                <td class="label-col">Telepon Lain</td>
                <td class="colon-col">:</td>
                <td class="val-col">{{ $values['other_phone'] ?? '—' }}</td>
            </tr>
            <tr>
                <td class="label-col">Alamat Jalan / Rumah</td>
                <td class="colon-col">:</td>
                <td class="val-col">{{ $values['street_address'] ?? '—' }}</td>
            </tr>
            <tr>
                <td class="label-col">Dusun / RT / RW</td>
                <td class="colon-col">:</td>
                <td class="val-col">
                    {{ $values['hamlet'] ?? '—' }} / RT {{ $values['rt'] ?? '-' }} / RW {{ $values['rw'] ?? '-' }}
                </td>
            </tr>
            <tr>
                <td class="label-col">Kelurahan / Desa</td>
                <td class="colon-col">:</td>
                <td class="val-col">{{ $values['village_name'] ?? '—' }}</td>
            </tr>
            <tr>
                <td class="label-col">Kecamatan</td>
                <td class="colon-col">:</td>
                <td class="val-col">{{ $display['district_code'] ?? ($values['district_code'] ?? '—') }}</td>
            </tr>
            <tr>
                <td class="label-col">Kota / Kabupaten</td>
                <td class="colon-col">:</td>
                <td class="val-col">{{ $display['city_code'] ?? ($values['city_code'] ?? '—') }}</td>
            </tr>
            <tr>
                <td class="label-col">Provinsi & Kode Pos</td>
                <td class="colon-col">:</td>
                <td class="val-col">{{ $display['province_code'] ?? ($values['province_code'] ?? '—') }} · {{ $values['postal_code'] ?? '—' }}</td>
            </tr>
            <tr>
                <td class="label-col">Jenis Tinggal & Transportasi</td>
                <td class="colon-col">:</td>
                <td class="val-col">{{ $display['residence_type_code'] ?? ($values['residence_type_code'] ?? '—') }} · {{ $display['transport_type_code'] ?? ($values['transport_type_code'] ?? '—') }}</td>
            </tr>
        </table>

        <!-- Bagian IV: Pendidikan Asal & Kesejahteraan -->
        <div class="section-header">IV. Pendidikan Asal & Kesejahteraan</div>
        <table class="bio-table">
            <tr>
                <td class="label-col">Perguruan Tinggi Asal (S1)</td>
                <td class="colon-col">:</td>
                <td class="val-col">{{ $application->workspace->education?->pt_name_snapshot ?? 'Belum diisi' }}</td>
            </tr>
            <tr>
                <td class="label-col">Program Studi Asal</td>
                <td class="colon-col">:</td>
                <td class="val-col">{{ $application->workspace->education?->prodi_name_snapshot ?? 'Belum diisi' }}</td>
            </tr>
            <tr>
                <td class="label-col">Penerima KPS</td>
                <td class="colon-col">:</td>
                <td class="val-col">
                    @if(!empty($values['is_kps_recipient']))
                        Ya (No. KPS: {{ $values['kps_number'] ?? '—' }})
                    @else
                        Tidak
                    @endif
                </td>
            </tr>
            <tr>
                <td class="label-col">NPWP</td>
                <td class="colon-col">:</td>
                <td class="val-col">{{ $values['npwp'] ?? '—' }}</td>
            </tr>
            <tr>
                <td class="label-col">Kebutuhan Khusus</td>
                <td class="colon-col">:</td>
                <td class="val-col">{{ $display['special_need_code'] ?? ($values['special_need_code'] ?? 'Tidak ada') }}</td>
            </tr>
        </table>

        <!-- Bagian V: Berkas Dokumen yang Telah Diunggah -->
        <div class="section-header">V. Daftar Berkas Persyaratan yang Telah Diunggah</div>
        @if($documents->isNotEmpty())
            <table class="docs-table">
                <thead>
                    <tr>
                        <th style="width: 5%; text-align: center;">No</th>
                        <th style="width: 45%;">Nama Berkas / Dokumen</th>
                        <th style="width: 15%;">Format / Tipe</th>
                        <th style="width: 15%;">Ukuran</th>
                        <th style="width: 20%;">Tanggal Unggah</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($documents as $idx => $doc)
                        <tr>
                            <td style="text-align: center;">{{ $idx + 1 }}</td>
                            <td>
                                <strong>{{ $doc->display_name }}</strong>
                                <small class="d-block text-muted">{{ $doc->original_name }}</small>
                            </td>
                            <td>{{ strtoupper($doc->extension) }}</td>
                            <td>{{ $doc->formattedSize() }}</td>
                            <td>{{ $doc->created_at->timezone('Asia/Jakarta')->translatedFormat('d/m/Y H:i') }} WIB</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @else
            <div class="p-3 text-center text-muted border rounded my-2 small">
                <em>Belum ada dokumen persyaratan yang diunggah oleh calon mahasiswa.</em>
            </div>
        @endif

        <!-- Pernyataan Kebenaran Data -->
        <div class="statement-box">
            <strong>Pernyataan Calon Mahasiswa:</strong> Seluruh data biodata dan berkas dokumen yang tertera di atas telah diisi dengan sebenarnya sesuai dengan dokumen resmi yang sah. Apabila di kemudian hari ditemukan ketidakbenaran atau pemalsuan data, saya bersedia menerima sanksi pembatalan kepesertaan PPG sesuai ketentuan hukum yang berlaku.
        </div>

        <!-- Tanda Tangan & Pengesahan -->
        <div class="signature-grid">
            <div class="signature-box">
                <p class="mb-1">Mengetahui & Memeriksa,</p>
                <p class="text-muted small">Panitia PMB PPG IAKN Palangka Raya</p>
                <div class="signature-space"></div>
                <div class="signature-name">{{ $application->participant->assignedOperator?->name ?? 'Petugas Verifikator' }}</div>
                <div class="small text-muted">NIP/Petugas Verifikasi</div>
            </div>

            <div class="signature-box">
                <p class="mb-1">Palangka Raya, {{ now()->timezone('Asia/Jakarta')->translatedFormat('d F Y') }}</p>
                <p class="text-muted small">Calon Mahasiswa PPG,</p>
                <div class="signature-space"></div>
                <div class="signature-name">{{ $values['full_name'] ?? $application->participant->name_on_sk }}</div>
                <div class="small text-muted">Pendaftar</div>
            </div>
        </div>

        <!-- Footer Cetak -->
        <div class="mt-4 pt-2 border-top text-muted d-flex justify-content-between small" style="font-size: 0.68rem;">
            <span>Dicetak melalui Portal PMB PPG IAKN Palangka Raya pada {{ now()->timezone('Asia/Jakarta')->translatedFormat('d/m/Y H:i:s') }} WIB</span>
            <span>Halaman Formulir Resmi PMB</span>
        </div>
    </div>

</body>
</html>
