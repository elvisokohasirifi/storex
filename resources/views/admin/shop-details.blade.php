<dl class="mb-4">
    @foreach(['logo' => 'Logo', 'banner' => 'Banner'] as $image => $label)
        <dt class="mb-2">{{ $label }}</dt>
        <dd class="mb-4">
            @if($shop->{$image})
                <img class="img-fluid rounded" style="max-height:200px" src="{{ Storage::disk('public')->url(Str::start($shop->{$image}, 'shops/')) }}" alt="{{ $shop->name }} {{ $image }}">
            @else
                <span class="text-muted">Not provided</span>
            @endif
        </dd>
    @endforeach
    <dt class="mb-2">Description</dt>
    <dd class="mb-4 text-break" style="white-space: pre-line">{{ $shop->description ?: 'Not provided' }}</dd>
    <dt class="mb-2">Shop name</dt>
    <dd class="mb-4 text-break">{{ $shop->name }}</dd>
    <dt class="mb-2">Location</dt>
    <dd class="mb-4 text-break">{{ $shop->location }}</dd>
    <dt class="mb-2">Contacts</dt>
    <dd class="mb-4 text-break" style="white-space: pre-line">{{ $shop->contacts ?: 'Not provided' }}</dd>
    <dt class="mb-2">Email</dt>
    <dd class="mb-4 text-break">{{ $shop->email ?: 'Not provided' }}</dd>
    <dt class="mb-2">Status</dt>
    <dd class="mb-4">{{ ucfirst($shop->status) }}</dd>
    <dt class="mb-2">Storefront</dt>
    <dd class="mb-0 text-break"><a href="{{ route('shops.show', $shop->slug) }}">{{ $shop->slug }}</a></dd>
</dl>
