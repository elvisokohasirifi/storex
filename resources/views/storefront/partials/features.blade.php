<section id="features" class="features-section">
    <div class="section-heading">
        <div><p class="eyebrow">WHAT STOREX CAN DO</p><h2>Features built for small shops.</h2></div>
        <a class="shop-text-link" href="{{ route('features') }}">See all features ↗</a>
    </div>
    <div class="features-grid">
        @foreach($features as $feature)
            <article class="feature-card">
                <span aria-hidden="true">{{ str_pad((string) ($loop->index + 1), 2, '0', STR_PAD_LEFT) }}</span>
                <h3>{{ $feature['title'] }}</h3>
                <p>{{ $feature['summary'] }}</p>
                <ul>
                    @foreach($feature['points'] as $point)
                        <li>{{ $point }}</li>
                    @endforeach
                </ul>
            </article>
        @endforeach
    </div>
</section>
