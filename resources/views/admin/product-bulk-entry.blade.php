<div class="card mt-4 mb-4" id="bulk-products"><div class="card-body">
    <h3>Bulk product upload</h3>
    @include('admin.messages')
    <p>Upload a CSV file or paste CSV below. Import up to 100 products at a time.</p>
    <a href="{{ asset('sample-products.csv') }}" class="btn btn-outline-primary mb-3" download="sample-products.csv">
        <i class="la la-download"></i> Download sample CSV
    </a>
    @if($widget['shops']->isNotEmpty())
    <form method="post" enctype="multipart/form-data" action="{{ route('products.bulk') }}">
        @csrf
        <label class="form-label w-100">Shop<select class="form-select" name="shop_id" required>
            @foreach($widget['shops'] as $bulkShop)<option value="{{ $bulkShop->id }}" @selected(old('shop_id', request()->query('shop_id')) === $bulkShop->id)>{{ $bulkShop->name }}</option>@endforeach
        </select></label>
        <label class="form-label w-100">CSV file<input class="form-control" type="file" name="csv_file" accept=".csv,.txt,text/csv,text/plain"></label>
        <p class="small text-muted">Maximum 200 KB. If both are supplied, the pasted CSV is used.</p>
        <label class="form-label w-100">Or paste CSV<textarea class="form-control font-monospace" name="csv" rows="6" placeholder="name,description,cost_price,selling_price,sale_price,quantity,barcode,sku&#10;Fresh bread,Made daily,8.00,12.00,10.00,20,123456,BREAD-01">{{ old('csv') }}</textarea></label>
        <p class="small">Header: <code>name,description,cost_price,selling_price,sale_price,quantity,barcode,sku</code>. Leave cost, sale price, stock, barcode, and SKU blank when not needed. Quote descriptions containing commas or new lines. Add images, categories, and brands after import.</p>
        <button class="btn btn-primary">Upload products</button>
    </form>
    @else<p class="text-muted">Create a shop to upload products. Frozen shops cannot import products.</p>@endif
</div></div>
