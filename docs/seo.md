# SEO portal publik

Beranda, panduan, dan halaman informasi periode yang berstatus published serta is_public masuk sitemap. Halaman akun, formulir, area pengelola, serta respons berkas tidak masuk sitemap dan menggunakan X-Robots-Tag: noindex, follow. Akses berkas tetap mengikuti otorisasi yang sudah ada; noindex bukan kontrol akses.

## Konfigurasi production

```dotenv
APP_URL=https://ppg.iaknpky.ac.id
SEO_URL=https://ppg.iaknpky.ac.id
SEO_INDEXABLE=true
SEO_REDIRECT_CANONICAL=true
```

Pada lokal/staging, gunakan domain lingkungan tersebut dan SEO_INDEXABLE=false, SEO_REDIRECT_CANONICAL=false. Tanpa variabel tambahan, indeks serta redirect hanya aktif secara bawaan ketika APP_ENV=production; URL mengikuti APP_URL. Jangan menyalin pengaturan indeks lokal ke production.

Setelah rilis, bangun ulang cache konfigurasi sesuai prosedur deployment. Pastikan reverse proxy menyampaikan host/protokol HTTPS dengan benar untuk mencegah pengalihan berulang. Middleware mengalihkan permintaan GET/HEAD halaman informasi publik ke domain SEO_URL dengan status 301 dan mempertahankan query; canonical HTML menghilangkan query. Terapkan pula pengalihan HTTP ke HTTPS pada web server untuk seluruh situs.

robots.txt dilayani Laravel agar berbeda menurut lingkungan. Hapus file robots.txt statis lama pada deployment yang menyalin file tanpa menghapusnya; file statis dapat menimpa route. Pastikan /robots.txt dan /sitemap.xml diteruskan ke Laravel oleh konfigurasi web server.

## Verifikasi setelah rilis

- Buka beranda, /panduan, /robots.txt, /sitemap.xml, dan informasi periode publik. Periksa status 200, canonical HTTPS, dan XML sitemap.
- Pastikan halaman /masuk dan /klaim-whatsapp memakai noindex; halaman informasi publik production memakai index.
- Kirim https://ppg.iaknpky.ac.id/sitemap.xml melalui Google Search Console. Gunakan URL Inspection untuk memeriksa canonical dan kelayakan indeks.
- Periksa pratinjau tautan WhatsApp dan validasi data terstruktur. Tidak ada jaminan peringkat atau rich result dari metadata.
- Ukur PageSpeed Insights mobile di production serta LCP, INP, dan CLS pada data lapangan. Pemeriksaan lokal tidak mewakili Core Web Vitals pengguna sebenarnya.
- Aktifkan Brotli/gzip serta cache panjang untuk aset build ber-hash pada web server. Jangan memasang cache publik pada respons akun atau yang mengandung data pribadi.

Halaman beranda, panduan, dan informasi periode tidak memuat runtime/style Livewire karena tidak memiliki interaksi Alpine/Livewire. Halaman pendaftaran akun dan login tetap memuatnya. CSS bersama dipertahankan untuk menjaga tampilan; pemisahan CSS berikutnya sebaiknya berdasarkan hasil pengukuran production.
