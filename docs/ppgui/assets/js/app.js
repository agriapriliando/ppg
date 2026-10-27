/**
 * PMB PPG IAKN PALANGKA RAYA
 * Script Interaktif & Alpine.js Helpers
 */

document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('i.bi').forEach(icon => icon.setAttribute('aria-hidden', 'true'));
});

document.addEventListener('alpine:init', () => {
  // Store Global untuk Aplikasi PMB
  Alpine.store('ppg', {
    appName: 'PMB PPG IAKN Palangka Raya',
    periodeAktif: 'Gelombang I - 2026/2027',
    totalKuota: 400,
    operatorCount: 4,
    helpdeskWA: '6281234567890',
  });

  // Data Component: Pengecekan Nama SK Interaktif
  Alpine.data('skChecker', () => ({
    searchQuery: '',
    searchNopes: '',
    hasSearched: false,
    isSearching: false,
    result: null,
    // Data contoh daftar SK untuk interaktivitas instan calon pendaftar
    mockParticipants: [
      {
        nama: 'Yohanes Kristian',
        nomor_sk: 'SK-BIMAS-089/2026',
        nomor_peserta: '260100142',
        prodi: 'Pendidikan Agama Kristen (PAK)',
        status_klaim: 'Belum Diklaim',
        operator: 'Operator 1'
      },
      {
        nama: 'Maria Magdalena',
        nomor_sk: 'SK-BIMAS-089/2026',
        nomor_peserta: '260100188',
        prodi: 'Pendidikan Agama Kristen (PAK)',
        status_klaim: 'Belum Diklaim',
        operator: 'Operator 2'
      },
      {
        nama: 'Daniel Sitorus',
        nomor_sk: 'SK-BIMAS-089/2026',
        nomor_peserta: '260100215',
        prodi: 'Pendidikan Agama Kristen (PAK)',
        status_klaim: 'Belum Diklaim',
        operator: 'Operator 3'
      },
      {
        nama: 'Debora Kahayan',
        nomor_sk: 'SK-BIMAS-089/2026',
        nomor_peserta: '260100340',
        prodi: 'Pendidikan Agama Kristen (PAK)',
        status_klaim: 'Terverifikasi',
        operator: 'Operator 4'
      }
    ],

    checkName() {
      if (!this.searchQuery.trim()) return;
      this.isSearching = true;
      this.hasSearched = false;
      this.result = null;

      setTimeout(() => {
        const query = this.searchQuery.toLowerCase().trim();
        const found = this.mockParticipants.find(item =>
          item.nama.toLowerCase().includes(query)
        );

        if (found) {
          this.result = {
            found: true,
            ...found
          };
        } else {
          this.result = {
            found: false,
            nama: this.searchQuery,
            pesan: 'Nama tidak ditemukan dalam daftar sementara SK. Pastikan ejaan sesuai SK Bimas Kristen atau hubungi Operator untuk verifikasi manual.'
          };
        }
        this.isSearching = false;
        this.hasSearched = true;
      }, 400);
    },

    resetCheck() {
      this.searchQuery = '';
      this.searchNopes = '';
      this.hasSearched = false;
      this.result = null;
    }
  }));

  // Data Component: Modal Klaim Jalur WhatsApp
  Alpine.data('waClaimModal', () => ({
    isOpen: false,
    step: 1,
    namaLengkap: '',
    nomorWA: '',
    nomorPeserta: '',
    isSubmitting: false,
    successMessage: false,

    open() {
      this.isOpen = true;
      this.step = 1;
      this.successMessage = false;
    },
    close() {
      this.isOpen = false;
    },
    submitClaim() {
      if (!this.namaLengkap || !this.nomorWA) return;
      const state = ppgRead();
      if (!state.periodOpen) { this.successMessage = 'Periode contoh sedang ditutup. Permohonan belum dikirim.'; return; }
      this.isSubmitting = true;

      setTimeout(() => {
        const state = ppgRead();
        state.claims.push({name: this.namaLengkap, wa: this.nomorWA, number: this.nomorPeserta, time: new Intl.DateTimeFormat('id-ID', {timeZone: 'Asia/Jakarta', dateStyle: 'medium', timeStyle: 'short'}).format(new Date()) + ' WIB'});
        try { localStorage.setItem(PPG_DEMO_KEY, JSON.stringify(state)); } catch (_) { this.successMessage = 'Penyimpanan browser tidak tersedia. Permohonan belum disimpan.'; this.isSubmitting = false; return; }
        this.isSubmitting = false;
        this.step = 2; // Success step
      }, 700);
    }
  }));

  // Data Component: FAQ Accordion
  Alpine.data('faqAccordion', () => ({
    activeFaq: 1,
    toggle(id) {
      this.activeFaq = this.activeFaq === id ? null : id;
    }
  }));
});

// Event listener untuk efek scroll navbar
window.addEventListener('scroll', () => {
  const navbar = document.querySelector('.ppg-navbar');
  if (navbar) {
    if (window.scrollY > 20) {
      navbar.classList.add('scrolled');
    } else {
      navbar.classList.remove('scrolled');
    }
  }
});
