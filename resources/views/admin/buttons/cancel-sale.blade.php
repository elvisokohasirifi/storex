@if(in_array($entry->status, ['pending', 'paid', 'paid_review'], true) && backpack_user()?->manages($entry->shop, true))
<form method="post" action="{{ route('workspace.sale.cancel', [$entry->shop_id, $entry]) }}" class="d-inline" onsubmit="return confirm('Cancel this sale and reverse tracked stock?')">
    @csrf
    <input type="hidden" name="reason" value="Cancelled from sales list">
    <button class="btn btn-sm btn-outline-danger" type="submit">Cancel</button>
</form>
@endif
