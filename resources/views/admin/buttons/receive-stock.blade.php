@if(request()->query('shop_id'))
    <a href="{{ route('workspace.inventory', request()->query('shop_id')) }}" class="btn btn-success" title="Receive stock">
        <i class="la la-plus-circle"></i> Receive Stock
    </a>
@endif
