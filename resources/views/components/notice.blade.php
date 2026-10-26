@if(session('status'))
    <div class="alert alert-success" role="status">{{ session('status') }}</div>
@endif
@if($errors->any())
    <div class="alert alert-danger" role="alert" tabindex="-1">
        <strong>Periksa kembali data Anda.</strong>
        <ul class="mb-0 mt-2">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
    </div>
@endif
