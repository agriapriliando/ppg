<?php

namespace App\Support;

use App\Actions\Imports\ValidateSkImport;
use App\Models\ReferenceDataset;
use App\Models\ReferenceRegion;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

class SkImportWorkbook
{
    public static function make(): Spreadsheet
    {
        $book = new Spreadsheet;
        $sheet = $book->getActiveSheet();
        $sheet->setTitle('Peserta_SK');
        $sheet->fromArray(ValidateSkImport::HEADERS);
        $sheet->getStyle('A:I')->getNumberFormat()->setFormatCode('@');
        $sheet->getStyle('A1:I1')->getFont()->setBold(true);
        foreach (range('A', 'I') as $column) {
            $sheet->getColumnDimension($column)->setWidth(28);
        }
        $sheet->freezePane('A2');
        $guide = $book->createSheet();
        $guide->setTitle('Panduan');
        $guide->fromArray([
            ['Template SK versi 6'],
            ['Kolom I: nomor_hp opsional. Awalan 08 dan +628 otomatis dikonversi ke 628. Isian lain tetap disimpan dan dapat diperbaiki kemudian. Simpan sebagai teks agar angka tetap utuh.'],
            ['Nomor HP kosong atau template lama mempertahankan nomor HP peserta yang sudah tersimpan. Untuk menghapus nomor, gunakan halaman Peserta SK.'],
            ['Kolom A–E wajib. Periode seluruh baris mengikuti pilihan Periode tujuan pada formulir unggah.'],
            ['Tanggal SK: YYYY-MM-DD. Nomor urut harus bilangan bulat positif.'],
            ['id_peserta_sk (Peg ID) wajib teks agar nol awal utuh. Kunci pencocokan adalah periode + id_peserta_sk; nomor SK/urut dapat diperbarui pada peserta yang sama.'],
            ['Kolom F: id_operator opsional. Isi ID login operator (contoh operator.satu), bukan email atau nomor urut.'],
            ['Semua akun Operator aktif dapat digunakan pada seluruh periode tanpa pendaftaran per periode. ID login dapat dilihat pada halaman Kelola Pengguna.'],
            ['Jika id_operator kosong, peserta baru belum ditugaskan; penugasan peserta lama tetap dipertahankan.'],
            ['Kolom H: kode_kota_kabupaten_asal opsional. Salin sebagai teks dari sheet Referensi_Wilayah.'],
            ['Provinsi asal terisi otomatis dari kota/kabupaten; tidak ada kolom kecamatan.'],
            ['Jangan ketik nama kota di kolom kode. Referensi wilayah mengikuti dataset aktif saat template diunduh.'],
            ['Jika Referensi_Wilayah kosong, muat dan aktifkan referensi wilayah terlebih dahulu atau kosongkan kolom asal.'],
            ['Template lama atau kolom asal kosong tetap didukung dan mempertahankan asal peserta yang sudah tersimpan.'],
            ['Jangan gunakan formula, macro, atau tautan eksternal.'],
            ['Satu berkas memuat satu periode, maksimal 5.000 baris.'],
            ['Untuk file yang diperbarui, gunakan mode Perbarui saja. ID tidak ditemukan akan ditunda, bukan membuat peserta baru.'],
            ['Gunakan Tambah dan perbarui untuk impor awal atau file campuran peserta baru dan pembaruan.'],
            ['Sel opsional kosong mempertahankan nomor HP, penugasan, asal, dan catatan internal yang tersimpan.'],
            ['Peserta terikat klaim atau transfer operator ditandai konflik. Gunakan pemeriksaan administratif/menu penugasan.'],
            ['Periksa seluruh hasil validasi dan nilai lama-baru sebelum mengonfirmasi impor.'],
            ['Baris valid dapat disimpan meskipun ada baris error atau konflik. Baris bermasalah ditunda; perbaiki dan unggah ulang.'],
        ]);
        $guide->getColumnDimension('A')->setWidth(110);
        $guide->getStyle('A:A')->getAlignment()->setWrapText(true);
        $regions = $book->createSheet();
        $regions->setTitle('Referensi_Wilayah');
        $regions->fromArray(['Kode Kota/Kabupaten', 'Kota/Kabupaten Asal', 'Provinsi Asal']);
        $datasetId = ReferenceDataset::query()->where('state', 'active')->value('id');
        $cities = ReferenceRegion::from('reference_regions as c')->join('reference_regions as p', function ($join) use ($datasetId): void {
            $join->on('p.code', '=', 'c.parent_code')->where('p.dataset_id', $datasetId)->where('p.level', 'province');
        })->where('c.dataset_id', $datasetId)->where('c.level', 'city')->orderBy('c.name')->select(['c.code', 'c.name', 'p.name as province_name'])->get();
        $row = 2;
        foreach ($cities as $city) {
            foreach ([$city->code, $city->name, $city->getAttribute('province_name')] as $column => $value) {
                $regions->setCellValueExplicit([$column + 1, $row], (string) $value, DataType::TYPE_STRING);
            }
            $row++;
        }
        $regions->getStyle('A1:C1')->getFont()->setBold(true);
        $regions->getColumnDimension('A')->setWidth(25);
        $regions->getColumnDimension('B')->setWidth(40);
        $regions->getColumnDimension('C')->setWidth(40);
        $regions->freezePane('A2');
        $regions->setAutoFilter('A1:C'.max(1, $row - 1));

        return $book;
    }
}
