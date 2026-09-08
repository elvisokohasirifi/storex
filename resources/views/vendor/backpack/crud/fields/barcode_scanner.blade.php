@include('crud::fields.inc.wrapper_start')
<div class="barcode-scanner-control" data-barcode-target="{{ $field['target'] ?? 'barcode' }}">
    <button type="button" class="btn btn-outline-primary" data-barcode-scan>
        <i class="la la-barcode" aria-hidden="true"></i> Scan barcode
    </button>
    <p class="small text-muted mt-2 mb-0" data-barcode-status role="status" aria-live="polite"></p>
</div>
@include('crud::fields.inc.wrapper_end')
