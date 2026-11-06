@if ($errors->any())
    <div {{ $attributes->merge(['class' => 'notice stop']) }} role="alert">
        <div>
            <p>Fix these problems to continue.</p>
            <ul class="acct-problems">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    </div>
@endif
