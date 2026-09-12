@if($entry->channel === 'manual' && $entry->status === 'pending' && backpack_user()?->manages($entry->shop, true))
<form method="post" action="{{ route('workspace.sale.confirm-manual', [$entry->shop, $entry]) }}" style="display:inline">
    @csrf
    <button class="btn btn-sm btn-link text-success" type="submit"><i class="la la-check"></i> Confirm paid</button>
</form>
@endif
