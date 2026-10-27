/* Prototipe UI: seluruh perubahan hanya tersimpan di browser, tanpa layanan eksternal. */
const PPG_DEMO_KEY = 'ppg-ui-demo-v1';
function ppgSeed() {
  return {
    status: 'draf', waVerified: false, claimApproved: false, submittedAt: '', savedAt: '',
    note: '', correctionFields: [], claimedName: '', credentialDelivered: false, claimWaVerified: false,
    claims: [], assigned: false, imported: false, importName: '', importHistory: [], waClaimApproved: false,
    periodOpen: true, neoStatus: 'belum_dikirim', users: [], events: [],
    config: {appTitle:'PMB PPG IAKN Palangka Raya', appContact:'Helpdesk PMB IAKN Palangka Raya', start:'08:00', end:'16:00', notice:'Pastikan nama sesuai SK dan nomor WhatsApp aktif sebelum mengirim formulir.', periodName:'Gelombang I · 2026/2027', opens:'2026-10-01T08:00', closes:'2026-10-31T16:00', skNumber:'SK-BIMAS-089/2026', skDate:'2026-09-25', correctionAfterClose:true},
    form: {name: 'Yohanes Kristian', gender: '', birthplace: '', birthdate: '', nik: '', nisn: '', religion: 'Kristen', nationality: 'Indonesia', mother: '', wa: '', email: 'yohanes.contoh@example.com', phone: '', street: '', hamlet: '', rt: '', rw: '', village: '', province: '', city: '', district: '', postal: '', residence: '', transport: '', kps: 'Tidak', kpsNumber: '', npwp: '', needs: 'Tidak ada', university: '', study: ''}
  };
}
function ppgRead() { try { const saved = JSON.parse(localStorage.getItem(PPG_DEMO_KEY)); return saved && saved.form ? {...ppgSeed(), ...saved} : ppgSeed(); } catch (_) { return ppgSeed(); } }
function publicClaim() {
  return {name:'',wa:'',number:'',sent:false,error:'', submit() {
    this.error=''; const state=ppgRead();
    if (!state.periodOpen) { this.error='Periode contoh sedang ditutup. Lihat informasi periode atau hubungi pengelola.'; return; }
    state.claims.push({name:this.name,wa:this.wa,number:this.number,time:new Intl.DateTimeFormat('id-ID',{timeZone:'Asia/Jakarta',dateStyle:'medium',timeStyle:'short'}).format(new Date())+' WIB'});
    try { localStorage.setItem(PPG_DEMO_KEY,JSON.stringify(state)); this.sent=true; } catch (_) { this.error='Penyimpanan browser tidak tersedia. Izinkan penyimpanan lokal untuk mencoba simulasi.'; }
  }};
}
function portalDemo(role) {
  const state = ppgRead();
  return {
    role, state, form: {...state.form}, sidebar: false, modal: '', toastMessage: '', step: 1,
    search: '', statusFilter: '', operatorFilter: '', pageNumber: 1, pageSize: 6, tab: 'semua', consent: false,
    noteInput: '', reason: '', activeId: new URLSearchParams(location.search).get('id') || '142',
    checkedFields: [], checkIdentity: false, checkSk: false, checkData: false,
    referenceSearch: '', activeClaim: 0, transferTarget: '', userTarget: '', confirmWaClaim: false, confirmDelivery: false,
    fileName: '', importStep: 1, selectedOperators: ['1','2','3','4'], newUser: {name:'',email:'',role:'Operator'},
    accountName: state['profile'+role] || (role === 'pendaftar' ? 'Yohanes Kristian' : 'Operator 1'), password: '', confirmPassword: '', periodName: state.config.periodName,
    appContact: 'Helpdesk PMB IAKN Palangka Raya', neoConfigSaved: false, accountTab: 'profil',
    init() {
      if (new URLSearchParams(location.search).has('step')) this.step = Math.min(5, Math.max(1, Number(new URLSearchParams(location.search).get('step')) || 1));
      this.$watch('modal', value => { document.body.style.overflow = value ? 'hidden' : ''; if (value) this.$nextTick(() => { const el = this.$refs.dialog.querySelector('button'); if (el) el.focus(); }); });
    },
    trapFocus(event) { const els = [...this.$refs.dialog.querySelectorAll('button,a[href],input,select,textarea')].filter(el => !el.disabled && el.getClientRects().length); if (!els.length) return; const first = els[0], last = els[els.length-1]; if (event.shiftKey && document.activeElement === first) { last.focus(); event.preventDefault(); } else if (!event.shiftKey && document.activeElement === last) { first.focus(); event.preventDefault(); } },
    persist() { this.state.form = {...this.form}; try { localStorage.setItem(PPG_DEMO_KEY, JSON.stringify(this.state)); } catch (_) { this.notify('Penyimpanan browser tidak tersedia. Perubahan hanya berlaku selama halaman ini terbuka.'); } },
    notify(message) { this.toastMessage = message; clearTimeout(this._toast); this._toast = setTimeout(() => this.toastMessage = '', 5000); },
    log(action, detail = '') { this.state.events.unshift({time: this.now(), actor: this.role === 'admin' ? 'Admin PMB' : this.role === 'operator' ? 'Operator 1' : 'Yohanes Kristian', action, detail}); this.persist(); },
    now() { return new Intl.DateTimeFormat('id-ID', {timeZone:'Asia/Jakarta', dateStyle:'medium', timeStyle:'short'}).format(new Date()) + ' WIB'; },
    label(status) { return {belum_ada:'Belum mendaftar',draf:'Draf',terkirim:'Terkirim',diverifikasi:'Diverifikasi',perbaikan:'Perlu perbaikan',diterima_lengkap:'Diterima lengkap',belum_dikirim:'Belum dikirim',siap:'Siap ditinjau',tersinkron:'Tersinkron (contoh)',perlu_verifikasi:'Perlu verifikasi'}[status] || status; },
    get locked() { return !['draf','perbaikan'].includes(this.state.status); },
    get progress() { const keys = ['name','gender','birthplace','birthdate','nik','mother','wa','street','village','province','city','district','residence','transport','university','study']; return Math.round(keys.filter(k => this.form[k]).length / keys.length * 100); },
    get pendingClaims() { return (this.state.claimApproved ? 0 : 1) + (this.state.waClaimApproved ? 0 : 1) + this.state.claims.filter(c=>!c.reviewed).length; },
    get selected() { return this.rows.find(row => row.id === this.activeId) || this.rows[0]; },
    get ownParticipant() { return this.activeId === '142'; },
    get rows() {
      return [
        {id:'142',name:'Yohanes Kristian',number:'260100142',operator:'1',status:this.state.status,path:'Google',wa:this.state.waVerified,school:'SMA Negeri Contoh 1'},
        {id:'188',name:'Maria Magdalena',number:'260100188',operator:'2',status:'terkirim',path:'WhatsApp',wa:true,school:'SMP Negeri Contoh 2'},
        {id:'215',name:'Daniel Sitorus',number:'260100215',operator:'3',status:'diverifikasi',path:'Google',wa:true,school:'SMA Negeri Contoh 3'},
        {id:'340',name:'Debora Kahayan',number:'260100340',operator:'4',status:'diterima_lengkap',path:'Google',wa:true,school:'SMP Negeri Contoh 4'},
        {id:'143',name:'Markus Pranata',number:'260100143',operator:'1',status:'terkirim',path:'WhatsApp',wa:false,school:'SMA Negeri Contoh 5'},
        {id:'144',name:'Ester Natalia',number:'260100144',operator:'1',status:'perbaikan',path:'Google',wa:true,school:'SMP Negeri Contoh 6'},
        {id:'145',name:'Lukas Aditya',number:'260100145',operator:'1',status:'belum_ada',path:'—',wa:false,school:'SMA Negeri Contoh 7'},
        {id:'146',name:'Rut Damayanti',number:'260100146',operator:'1',status:'diterima_lengkap',path:'Google',wa:true,school:'SMP Negeri Contoh 8'},
        {id:'147',name:'Petrus Wibowo',number:'260100147',operator:'1',status:'diverifikasi',path:'WhatsApp',wa:true,school:'SMA Negeri Contoh 9'},
        {id:'148',name:'Hana Lestari',number:'260100148',operator:'1',status:'draf',path:'Google',wa:false,school:'SMP Negeri Contoh 10'}
      ].map(row => ({...row, ...(this.state['participant'+row.id] || {})}));
    },
    get filteredRows() { return this.rows.filter(row => (this.role !== 'operator' || row.operator === '1') && (!this.operatorFilter || row.operator === this.operatorFilter) && (!this.statusFilter || row.status === this.statusFilter) && (!this.search || (row.name + row.number).toLowerCase().includes(this.search.toLowerCase()))); },
    get pagedRows() { return this.filteredRows.slice((this.pageNumber-1)*this.pageSize, this.pageNumber*this.pageSize); },
    get totalPages() { return Math.max(1, Math.ceil(this.filteredRows.length / this.pageSize)); },
    detailUrl(id) { return 'portal.php?role='+this.role+'&page=detail&id='+id; },
    saveDraft() { if (this.locked) return; this.state.savedAt = this.now(); this.log('Menyimpan draf formulir'); this.notify('Draf contoh tersimpan di browser.'); },
    nextStep() { if (this.locked) { this.step = Math.min(5, this.step+1); return; } const section = document.querySelector('[data-form-step="'+this.step+'"]'); const invalid = section && [...section.querySelectorAll('input,select')].find(el => !el.checkValidity()); if (invalid) { invalid.reportValidity(); return; } this.saveDraft(); this.step = Math.min(5,this.step+1); window.scrollTo({top:180,behavior:'smooth'}); },
    fillExample() { if (this.locked) return; Object.assign(this.form, {gender:'Laki-laki',birthplace:'Palangka Raya',birthdate:'1990-05-12',nik:'0000000000000000',nisn:'0000000000',mother:'Ibu Contoh',wa:'081200000142',street:'Jl. Pendidikan No. 12 (contoh)',hamlet:'—',rt:'001',rw:'002',village:'Menteng',province:'Kalimantan Tengah',city:'Kota Palangka Raya',district:'Jekan Raya',postal:'73112',residence:'Milik sendiri',transport:'Sepeda motor',university:'Institut Agama Kristen Negeri Palangka Raya',study:'Pendidikan Agama Kristen'}); this.saveDraft(); this.notify('Formulir diisi data contoh untuk menelusuri alur.'); },
    submitApplication() {
      if (this.locked) return;
      if (!this.state.claimApproved) { this.notify('Ajukan dan setujui klaim SK melalui tampilan operator terlebih dahulu.'); return; }
      const invalid = [...document.querySelectorAll('[data-form-step] input,[data-form-step] select')].find(el=>!el.checkValidity());
      if (invalid) { this.modal=''; this.step=Number(invalid.closest('[data-form-step]').dataset.formStep); this.$nextTick(()=>invalid.reportValidity()); return; }
      if (this.progress < 100) { this.notify('Lengkapi kolom wajib pada setiap tahap formulir.'); return; }
      if (!this.consent) return;
      this.state.status = 'terkirim'; if (!this.state.submittedAt) this.state.submittedAt = this.now(); this.log('Mengirim pendaftaran','Tanggal kirim pertama tetap tersimpan.'); this.modal = ''; location.href = 'portal.php?role=pendaftar&page=status';
    },
    requestStudentClaim() { this.state.claimedName = this.form.name; this.log('Mengajukan pencocokan SK','Menunggu pemeriksaan operator'); this.notify('Permohonan pencocokan contoh masuk ke antrean operator.'); },
    updateDecision(status) { const current = this.selected.status; if (status === 'perbaikan' && !this.noteInput.trim()) { this.notify('Tuliskan catatan perbaikan yang spesifik.'); return; } if (status === 'diverifikasi' && (!this.checkIdentity || !this.checkSk || !this.checkData)) { this.notify('Selesaikan tiga pemeriksaan sebelum melanjutkan.'); return; } if (status === 'diterima_lengkap' && (!this.selected.wa || (this.ownParticipant && !this.state.claimApproved))) { this.notify('Klaim SK dan WhatsApp aktif harus terverifikasi terlebih dahulu.'); return; } if (!((status === 'diverifikasi' && current === 'terkirim') || (status === 'perbaikan' && ['terkirim','diverifikasi'].includes(current)) || (status === 'diterima_lengkap' && current === 'diverifikasi'))) return;
      if (this.ownParticipant) { this.state.status = status; if (status === 'perbaikan') { this.state.note = this.noteInput; this.state.correctionFields = this.checkedFields.length ? [...this.checkedFields] : ['Biodata']; } }
      else this.state['participant'+this.activeId] = {...(this.state['participant'+this.activeId] || {}),status};
      this.log('Keputusan: '+this.label(status),this.selected.name+(this.noteInput ? ' · '+this.noteInput : '')); this.modal = ''; this.notify('Status contoh diperbarui menjadi '+this.label(status)+'.');
    },
    verifyWa() { if (this.ownParticipant && !this.form.wa) { this.notify('Nomor WhatsApp belum diisi oleh pendaftar.'); return; } if (!this.reason.trim()) { this.notify('Catat ringkasan bukti balasan nomor WhatsApp.'); return; } if (this.ownParticipant) this.state.waVerified = true; else this.state['participant'+this.activeId] = {...(this.state['participant'+this.activeId] || {}),wa:true}; this.log('Verifikasi WhatsApp',this.selected.name+' · '+this.reason); this.modal = ''; this.reason = ''; this.notify('Verifikasi WhatsApp contoh telah dicatat.'); },
    changeWa() { if (this.form.wa !== this.state.form.wa) this.state.waVerified = false; },
    approveClaim() { if (!this.checkIdentity || !this.checkSk) { this.notify('Konfirmasikan kecocokan identitas dan entri SK.'); return; } this.state.claimApproved = true; this.log('Menyetujui klaim SK','Yohanes Kristian · SK baris 142'); this.modal = ''; this.notify('Klaim SK contoh disetujui. Pendaftar dapat mengirim formulir.'); },
    chooseFile(event) { const file = event.target.files[0]; if (!file) return; if (!/\.xlsx$/i.test(file.name)) { this.notify('Pilih berkas dengan format .xlsx.'); event.target.value = ''; return; } this.fileName = file.name; this.importStep = 2; },
    confirmImport() { this.state.imported = true; this.state.importName = this.fileName; this.state.importHistory.unshift({name:this.fileName,time:this.now()}); this.log('Simulasi konfirmasi impor','Pratinjau memakai 400 baris contoh, bukan isi berkas.'); this.importStep = 3; },
    assign() { if (!this.selectedOperators.length) return; this.state.assigned = true; this.log('Pembagian operator contoh',this.selectedOperators.length+' operator · round-robin'); this.modal = ''; this.notify('Simulasi pembagian 400 entri SK selesai.'); },
    saveUser() { this.state.users.push({...this.newUser,active:true}); this.log('Menambah pengguna contoh',this.newUser.name+' · '+this.newUser.role); this.modal = ''; this.newUser = {name:'',email:'',role:'Operator'}; this.notify('Akun contoh ditambahkan.'); },
    downloadCsv() { const rows = [['Nomor peserta','Nama','Operator','Status'],...this.filteredRows.map(r=>[r.number,r.name,'Operator '+r.operator,this.label(r.status)])]; const blob = new Blob(['\ufeff'+rows.map(r=>r.map(v=>'"'+String(v).replaceAll('"','""')+'"').join(',')).join('\r\n')],{type:'text/csv;charset=utf-8;'}); const url = URL.createObjectURL(blob); const a = document.createElement('a'); a.href=url; a.download='peserta-contoh-ppg.csv'; a.click(); URL.revokeObjectURL(url); this.log('Ekspor data contoh'); },
    resetDemo() { try { localStorage.removeItem(PPG_DEMO_KEY); } catch (_) {} location.href='portal.php?role='+this.role; },
    simulateNeo() { if (this.state.status !== 'diterima_lengkap') { this.notify('Selesaikan keputusan PMB untuk peserta contoh Yohanes terlebih dahulu.'); return; } this.state.neoStatus='tersinkron'; this.log('Simulasi sinkronisasi Neo','Biodata → riwayat → baca balik; tanpa koneksi eksternal.'); this.modal=''; this.notify('Simulasi sinkronisasi selesai.'); }
  };
}
