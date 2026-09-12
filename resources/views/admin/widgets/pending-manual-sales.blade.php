<div class="row g-3 mb-4">
    <div class="col-lg-4">
        <div class="card h-100 border-warning">
            <div class="card-body">
                <p class="text-muted mb-2">Pending manual payments</p>
                <div class="h1 mb-1">{{ number_format($widget['pendingManualCount']) }}</div>
                <p class="mb-0">Cash or mobile money orders waiting for payment confirmation.</p>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card h-100 border-info">
            <div class="card-body">
                <p class="text-muted mb-2">Pending manual value</p>
                <div class="h1 mb-1">{{ number_format($widget['pendingManualTotal'] / 100, 2) }}</div>
                <p class="mb-0">Combined value of manual orders still marked pending.</p>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card h-100 border-success">
            <div class="card-body">
                <p class="text-muted mb-2">What to do</p>
                <div class="h3 mb-2">Confirm after payment</div>
                <p class="mb-0">Use Confirm paid only after receiving cash or verifying the mobile money transfer.</p>
            </div>
        </div>
    </div>
</div>
<div class="card mb-4">
    <div class="card-header d-flex align-items-center justify-content-between gap-3">
        <h3 class="card-title mb-0">Manual payments awaiting confirmation</h3>
        <span class="badge bg-warning text-dark">{{ number_format($widget['pendingManualCount']) }} pending</span>
    </div>
    <div class="card-body table-responsive">
        <table class="table table-hover align-middle">
            <thead><tr><th>Reference</th><th>Shop</th><th>Customer</th><th>Method</th><th>Total</th><th>Items</th><th></th></tr></thead>
            <tbody>
            @forelse($widget['pendingManualOrders'] as $order)
                <tr>
                    <td>{{ $order->reference }}</td>
                    <td>{{ $order->shop->name }}</td>
                    <td>{{ $order->customer_name }}<div class="small text-muted">{{ $order->customer_phone }}</div></td>
                    <td>{{ strtoupper($order->payment_method) }}</td>
                    <td>{{ $order->currency }} {{ number_format($order->total / 100, 2) }}</td>
                    <td>{{ $order->items->sum('quantity') }}</td>
                    <td>
                        <form method="post" action="{{ route('workspace.sale.confirm-manual', [$order->shop, $order]) }}">
                            @csrf
                            <button class="btn btn-sm btn-success">Confirm paid</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="7">No cash or mobile money payments are waiting.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
