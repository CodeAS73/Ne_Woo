@if(session('success'))<div class="notice success-notice" role="status">{{ session('success') }}</div>@endif
@if($errors->any())<div class="notice error-notice" role="alert">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
