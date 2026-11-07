@props(['small' => false])
<div x-data="{ showPassword: false }" class="input-group {{ $small ? 'input-group-sm' : '' }}">
    <input {{ $attributes->merge(['class' => 'form-control', 'type' => 'password']) }} :type="showPassword ? 'text' : 'password'">
    <button type="button" class="btn btn-password-toggle" @click="showPassword = !showPassword"
        aria-controls="{{ $attributes->get('id') }}" :aria-pressed="showPassword"
        :aria-label="showPassword ? 'Sembunyikan kata sandi' : 'Tampilkan kata sandi'"
        :title="showPassword ? 'Sembunyikan kata sandi' : 'Tampilkan kata sandi'">
        <i class="bi" :class="showPassword ? 'bi-eye-slash' : 'bi-eye'" aria-hidden="true"></i>
    </button>
</div>
