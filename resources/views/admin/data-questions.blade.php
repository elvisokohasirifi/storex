@extends('admin.shop-page')
@section('page_title', 'Ask data')
@section('shop_content')
<div class="row g-4 align-items-start">
    <div class="col-lg-8">
        <div class="card mb-4">
            <div class="card-header"><h3 class="card-title mb-0">Ask questions about {{ $shop->name }}</h3></div>
            <div class="card-body">
                <p class="text-muted">Ask in plain English and Storex will answer from this shop's sales data. This version supports saved answers, sales totals, order counts, top products, top salespeople, and monthly staff comparison charts.</p>
                <form method="post" action="{{ route('workspace.ask-data.store', $shop) }}" class="mb-3">
                    @csrf
                    <label class="form-label w-100">Your question
                        <textarea class="form-control" name="question" rows="3" maxlength="255" placeholder="Generate a bar chart comparing Ama Seller and Kojo Seller monthly sales for the year 2026." required data-question-input>{{ old('question', $question) }}</textarea>
                    </label>
                    <button class="btn btn-primary" type="submit">Ask and save question</button>
                </form>
                <div class="d-flex flex-wrap gap-2" aria-label="Example questions">
                    @foreach($examples as $example)
                        <button class="btn btn-sm btn-outline-secondary" type="button" data-example-question="{{ $example }}">{{ $example }}</button>
                    @endforeach
                </div>
            </div>
        </div>

        @if($answer)
            <div class="card mb-4">
                <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <h3 class="card-title mb-0">{{ $answer['title'] }}</h3>
                    @if($selectedQuestion)
                        <span class="badge bg-light text-dark">Saved {{ $selectedQuestion->created_at->format('M j, Y g:i A') }}</span>
                    @endif
                </div>
                <div class="card-body">
                    <p class="lead mb-0">{{ $answer['summary'] }}</p>
                </div>
                @if(($answer['type'] ?? null) === 'bar_chart' && ! empty($answer['chart']))
                    @php($chart = $answer['chart'])
                    @php($max = max((int) ($chart['max'] ?? 0), 1))
                    <div class="card-body border-top">
                        <div class="vstack gap-4">
                            @foreach($chart['labels'] as $monthIndex => $label)
                                <div>
                                    <div class="fw-semibold mb-2">{{ $label }}</div>
                                    <div class="vstack gap-2">
                                        @foreach($chart['series'] as $seriesIndex => $series)
                                            @php($value = (int) ($series['values'][$monthIndex] ?? 0))
                                            @php($width = max(4, (int) round(($value / $max) * 100)))
                                            <div class="d-flex align-items-center gap-2">
                                                <div class="small text-muted" style="width: 120px">{{ $series['label'] }}</div>
                                                <div class="flex-grow-1 bg-light rounded overflow-hidden" style="height: 1.4rem">
                                                    <div class="h-100 {{ $seriesIndex % 2 === 0 ? 'bg-primary' : 'bg-success' }}" style="width: {{ $width }}%"></div>
                                                </div>
                                                <div class="small fw-semibold text-end" style="width: 110px">{{ $chart['currency'] }} {{ number_format($value / 100, 2) }}</div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
                @if(! empty($answer['rows']))
                    <div class="table-responsive">
                        <table class="table table-striped mb-0">
                            <thead><tr>@foreach($answer['columns'] as $column)<th>{{ $column }}</th>@endforeach</tr></thead>
                            <tbody>
                                @foreach($answer['rows'] as $row)
                                    <tr>@foreach($row as $cell)<td>{{ $cell }}</td>@endforeach</tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        @endif
    </div>

    <aside class="col-lg-4">
        <div class="card">
            <div class="card-header"><h3 class="card-title mb-0">Saved questions</h3></div>
            <div class="list-group list-group-flush">
                @forelse($history as $item)
                    <div class="list-group-item">
                        <a class="fw-semibold d-block" href="{{ route('workspace.ask-data', ['shop' => $shop->id, 'question_id' => $item->id]) }}">{{ Str::limit($item->question, 90) }}</a>
                        <p class="small text-muted mb-2">Asked by {{ $item->user?->name ?? 'Unknown user' }} · {{ $item->created_at->diffForHumans() }}</p>
                        <form method="post" action="{{ route('workspace.ask-data.destroy', ['shop' => $shop->id, 'dataQuestion' => $item->id]) }}" onsubmit="return confirm('Delete this saved question?')">
                            @csrf
                            @method('DELETE')
                            <button class="btn btn-sm btn-outline-danger" type="submit">Delete</button>
                        </form>
                    </div>
                @empty
                    <div class="list-group-item text-muted">No saved questions yet.</div>
                @endforelse
            </div>
            @if($history->hasPages())
                <div class="card-footer">{{ $history->links() }}</div>
            @endif
        </div>
    </aside>
</div>
@push('after_scripts')
<script>
(() => {
    const input = document.querySelector('[data-question-input]');
    document.querySelectorAll('[data-example-question]').forEach((button) => {
        button.addEventListener('click', () => {
            input.value = button.dataset.exampleQuestion || '';
            input.focus();
        });
    });
})();
</script>
@endpush
@endsection
