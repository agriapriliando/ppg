export function publicClaimForm(options) {
    return {
        identifier: options.identifier || '',
        phone: options.phone || '',
        periodId: '',
        preferredPeriod: String(options.periodId || ''),
        participants: [],
        name: '',
        periodName: '',
        loading: false,
        error: '',
        showConfirmation: false,
        agreed: false,
        submitting: false,
        version: 0,
        timer: null,
        controller: null,
        get ready() { return Boolean(this.name && this.periodId && !this.loading && !this.error); },
        init() { this.changed(); },
        destroy() { clearTimeout(this.timer); this.controller?.abort(); this.version++; },
        changed() {
            clearTimeout(this.timer);
            this.controller?.abort();
            this.version++;
            this.participants = [];
            this.periodId = '';
            this.name = '';
            this.periodName = '';
            this.error = '';
            this.loading = false;
            this.showConfirmation = false;
            this.agreed = false;
            this.phone = this.phone.replace(/[^0-9]/g, '');
            if (this.phone.startsWith('08')) this.phone = '62' + this.phone.slice(1);
            else if (this.phone.startsWith('8')) this.phone = '62' + this.phone;
            if (!this.identifier.trim() || !/^628[0-9]{7,12}$/.test(this.phone)) return;
            this.loading = true;
            const version = this.version;
            this.timer = setTimeout(() => this.lookup(version), 500);
        },
        async lookup(version = this.version) {
            this.controller = new AbortController();
            try {
                const response = await fetch(options.lookupUrl, {
                    method: 'POST',
                    headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': options.csrf },
                    body: JSON.stringify({ identifier: this.identifier.trim(), whatsapp_number: this.phone }),
                    signal: this.controller.signal,
                });
                if (version !== this.version) return;
                if (response.status === 419) throw new Error('Sesi formulir berakhir. Muat ulang halaman sebelum melanjutkan.');
                if (response.status === 429) throw new Error('Terlalu banyak pencarian. Tunggu satu menit, lalu coba lagi.');
                const data = await response.json();
                if (version !== this.version) return;
                if (!response.ok) throw new Error(Object.values(data.errors || {}).flat().join(' ') || 'Data peserta belum dapat diperiksa. Coba lagi.');
                this.participants = data.participants;
                this.periodId = this.participants.length === 1
                    ? String(this.participants[0].period_id)
                    : (this.participants.some(item => String(item.period_id) === this.preferredPeriod) ? this.preferredPeriod : '');
                this.selectPeriod();
            } catch (error) {
                if (version === this.version && error.name !== 'AbortError') {
                    this.error = error instanceof TypeError
                        ? 'Pencarian gagal. Periksa koneksi internet, lalu klik Coba lagi.'
                        : (error.message || 'Pencarian gagal. Periksa koneksi dan coba lagi.');
                }
            } finally {
                if (version === this.version) this.loading = false;
            }
        },
        retry() { this.changed(); },
        selectPeriod() {
            const participant = this.participants.find(item => String(item.period_id) === String(this.periodId));
            this.name = participant?.name || '';
            this.periodName = participant?.period_name || '';
            this.agreed = false;
            this.showConfirmation = false;
        },
        closeConfirmation() {
            if (!this.submitting) { this.showConfirmation = false; this.agreed = false; }
        },
        submit(event) {
            if (!this.ready || this.submitting) { event.preventDefault(); return; }
            if (!this.showConfirmation || !this.agreed) { event.preventDefault(); this.showConfirmation = true; }
            else this.submitting = true;
        },
    };
}
