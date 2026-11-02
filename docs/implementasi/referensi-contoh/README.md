# Referensi dari file terpisah

Buka `/admin/referensi` sebagai administrator, kemudian gunakan **Unggah referensi**.

Gunakan satu kolom unggah untuk memilih satu atau beberapa file. Tidak perlu mengisi versi, sumber, atau memilih jenis referensi. Contoh file:

| Jenis referensi | File |
| --- | --- |
| Pilihan biodata | [options.json](options.json) |
| Wilayah | [GetWilayah.json](../../GetWilayah.json) |
| Perguruan tinggi | [pt.json](pt.json) |
| Program studi | [prodi.json](prodi.json) |

Periksa isi file contoh sebelum dipakai. Data wilayah mengikuti isi file GetWilayah yang diberikan pengguna; aplikasi tidak memverifikasi kemutakhiran atau pengesahan sumbernya.

Klik **Unggah Referensi**. Sejak impor pertama, satu file saja sudah dapat disimpan. Lengkapi jenis lain pada unggahan berikutnya. File tidak otomatis diterbitkan hanya karena tersedia di folder proyek. Pengiriman biodata tetap memerlukan pilihan referensi yang sesuai untuk setiap kolom wajib.

Unggah hanya file yang ingin ditambahkan atau diperbarui. File yang dipilih mengganti jenis data tersebut; untuk pilihan biodata, hanya kelompok yang tercantum dalam file yang diganti. Jenis dan kelompok yang tidak dipilih disalin dari dataset aktif. Versi baru dibuat otomatis dan nama file dicatat sebagai sumber. Contohnya, perubahan wilayah tidak mengganti pilihan biodata, perguruan tinggi, atau program studi. Jika perguruan tinggi dihapus, unggah juga program studi yang sudah disesuaikan. Sistem menolak hubungan yang tidak sah dan mempertahankan dataset aktif ketika impor gagal.

Setiap publikasi menyimpan snapshot baru dari seluruh referensi yang sudah diunggah. Dataset lama tetap tersedia untuk riwayat pendaftaran. Tidak ada perubahan skema database.

## Format file

Setiap file menerima daftar JSON langsung atau objek dengan satu kunci sesuai jenisnya:

```json
{
  "pt": [
    { "external_id": "UJI-PT01", "name": "Institut Contoh Satu (uji)" }
  ]
}
```

Kunci yang didukung: `options`, `regions`, `pt`, dan `prodi`. Struktur tiap baris mengikuti [template gabungan](../referensi.template.json). Pilihan biodata dapat memuat satu atau beberapa kelompok berikut: `gender`, `religion`, `nationality`, `residence`, `transport`, dan `special_need`.

Untuk wilayah, file `GetWilayah.json` dapat diunggah langsung tanpa konversi manual. Aplikasi memeriksa respons berhasil (`error_code: 0`), mengambil baris Indonesia (`id_negara: ID`) pada level 1–3, dan mengabaikan level negara serta kode penanda `999999`. Kode dan nama dirapikan dari spasi; kode tetap berupa teks sehingga nol awal tidak hilang. Induk provinsi dijadikan `null`; hubungan kota/kabupaten dan kecamatan diperiksa. Dari file yang disertakan tersedia 34 provinsi, 514 kabupaten/kota, dan 7.269 kecamatan.

Format wilayah aplikasi juga tetap didukung:

```json
{
  "regions": [
    { "code": "UJI-P01", "parent_code": null, "level": "province", "name": "Provinsi Uji" },
    { "code": "UJI-K01", "parent_code": "UJI-P01", "level": "city", "name": "Kota Uji" },
    { "code": "UJI-C01", "parent_code": "UJI-K01", "level": "district", "name": "Kecamatan Uji" }
  ]
}
```

Maksimal 10 file per unggahan, masing-masing 10 MB. File gabungan dengan kunci `options`, `regions`, `pt`, dan `prodi` juga dapat diunggah melalui kolom yang sama. Jenis referensi dikenali dari isi, bukan nama file. Hindari beberapa file untuk jenis atau kelompok pilihan yang sama pada satu unggahan.

Respons `GetAlatTransportasi.json` dan `GetJenisTinggal.json` juga dapat diunggah langsung; keduanya memperbarui kelompok `transport` dan `residence` tanpa mengganti kelompok biodata lain. Program studi memerlukan perguruan tinggi yang cocok, yang dapat diunggah sebelumnya atau bersamaan; urutan file yang dipilih tidak berpengaruh.
