@extends(backpack_view('blank'))

@section('header')
<section class="container-fluid">
    <h1>Help</h1>
    <p class="text-muted mb-0">Role: {{ $context['role'] }}. {{ $context['description'] }}</p>
</section>
@endsection

@section('content')
<div class="row">
    <aside class="col-lg-3 mb-4">
        <div class="card sticky-top" style="top: 1rem;">
            <div class="card-header"><strong>On this page</strong></div>
            <div class="list-group list-group-flush">
                @foreach($sections as $section)
                    <a class="list-group-item list-group-item-action" href="#help-{{ Str::slug($section['title']) }}">{{ $section['title'] }}</a>
                @endforeach
            </div>
        </div>
    </aside>
    <div class="col-lg-9">
        @foreach($sections as $section)
            <section id="help-{{ Str::slug($section['title']) }}" class="card mb-4">
                <div class="card-body p-4">
                    <div class="mb-4">
                        <h2 class="h3 mb-2">{{ $section['title'] }}</h2>
                        <p class="text-muted mb-0">{{ $section['summary'] }}</p>
                    </div>

                    <div class="accordion" id="accordion-{{ $loop->index }}">
                        @foreach($section['items'] as $item)
                            @php($itemId = 'help-'.Str::slug($section['title']).'-'.$loop->index)
                            <div class="accordion-item">
                                <h3 class="accordion-header" id="heading-{{ $itemId }}">
                                    <button class="accordion-button {{ $loop->first ? '' : 'collapsed' }}" type="button" data-bs-toggle="collapse" data-bs-target="#collapse-{{ $itemId }}" aria-expanded="{{ $loop->first ? 'true' : 'false' }}" aria-controls="collapse-{{ $itemId }}">
                                        {{ $item['title'] }}
                                    </button>
                                </h3>
                                <div id="collapse-{{ $itemId }}" class="accordion-collapse collapse {{ $loop->first ? 'show' : '' }}" aria-labelledby="heading-{{ $itemId }}" data-bs-parent="#accordion-{{ $loop->parent->index }}">
                                    <div class="accordion-body">
                                        <p>{{ $item['body'] }}</p>
                                        @if(!empty($item['fields']))
                                            <div class="table-responsive mt-3">
                                                <table class="table table-sm align-middle mb-0">
                                                    <thead>
                                                        <tr><th style="width: 30%;">Field</th><th>What it means</th></tr>
                                                    </thead>
                                                    <tbody>
                                                        @foreach($item['fields'] as $field)
                                                            <tr><td><strong>{{ $field['name'] }}</strong></td><td>{{ $field['help'] }}</td></tr>
                                                        @endforeach
                                                    </tbody>
                                                </table>
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </section>
        @endforeach
    </div>
</div>
@endsection
