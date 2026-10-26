@if($application && $application->ownership_epoch>0 && !$application->hasCurrentOwnerSubmission())
    <div class="notice-box info"><i class="bi bi-person-check" aria-hidden="true"></i><div><strong>Kepesertaan sudah ditautkan ke akun Anda.</strong><p class="mb-0">Tinjau data pribadi Anda, lengkapi formulir dan kirimkan untuk pemeriksaan. Nomor pendaftaran serta tanggal awal tetap. Bukti pendaftaran tersedia setelah pengiriman dari akun Anda.</p></div></div>
@endif
