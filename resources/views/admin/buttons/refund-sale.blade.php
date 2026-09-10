@if(in_array($entry->status, ['paid', 'paid_review'], true) && backpack_user()?->manages($entry->shop, true))
<form method="post" action="{{ route('workspace.sale.refund', [$entry->shop_id, $entry]) }}" class="d-inline" onsubmit="return confirm('Refund this sale and reverse tracked stock?')">
    @csrf
    <input type="hidden" name="reason" value="Refunded from sales list">
    <button class="btn btn-sm btn-outline-warning" type="submit">Refund</button>
</form>
@endif
