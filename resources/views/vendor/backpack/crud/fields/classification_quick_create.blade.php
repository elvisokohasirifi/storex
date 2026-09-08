@include('crud::fields.inc.wrapper_start')
<details class="classification-quick-create" data-target="{{ $field['target'] }}" data-endpoint="{{ $field['endpoint'] }}">
    <summary class="text-primary mb-2">+ Add a new {{ $field['label'] }}</summary>
    <div class="border rounded p-3">
        <p class="small text-muted">Saved to the shop selected above, then selected for this product.</p>
        <label class="form-label" for="{{ $field['name'] }}_name">{{ ucfirst($field['label']) }} name</label>
        <input id="{{ $field['name'] }}_name" class="form-control mb-2" data-quick-name maxlength="150" autocomplete="off">
        <label class="form-label" for="{{ $field['name'] }}_description">Description (optional)</label>
        <textarea id="{{ $field['name'] }}_description" class="form-control mb-3" data-quick-description rows="2" maxlength="10000"></textarea>
        <button type="button" class="btn btn-outline-primary" data-quick-save>Add {{ $field['label'] }}</button>
        <p class="small mt-2 mb-0" data-quick-message role="status" aria-live="polite"></p>
    </div>
</details>
@include('crud::fields.inc.wrapper_end')