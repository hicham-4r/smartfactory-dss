@if (session('success'))
    <div
        class="alert alert-success"
        role="alert"
    >
        {{ session('success') }}
    </div>
@endif

@if ($errors->any())
    <div
        class="alert alert-danger"
        role="alert"
    >
        <div class="fw-semibold mb-2">
            Please correct the following:
        </div>

        <ul class="mb-0">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif