<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\LedgerEntryRequest;
use App\Models\LedgerEntry;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Http\Controllers\Operations\CreateOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\ShowOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;

class LedgerEntryCrudController extends CrudController
{
    use CreateOperation;
    use ListOperation;
    use ShowOperation;
    use UpdateOperation;

    public function setup(): void
    {
        abort_if(backpack_user()->is_platform_admin, 403);
        CRUD::setModel(LedgerEntry::class);
        CRUD::setRoute(backpack_url('ledger-entry'));
        CRUD::setEntityNameStrings('income / expense', 'income & expenses');
        CRUD::addClause('whereIn', 'shop_id', backpack_user()->accessibleShops()->select('shops.id'));
        if (request()->route('id')) {
            LedgerEntry::whereIn('shop_id', backpack_user()->accessibleShops()->select('shops.id'))->whereKey(request()->route('id'))->firstOrFail();
        }
    }

    protected function setupListOperation(): void
    {
        foreach (['description', 'type', 'category', 'amount', 'currency', 'occurred_on'] as $name) {
            CRUD::column($name)->label(ucwords(str_replace('_', ' ', $name)))->type('text');
        }
        CRUD::button('receipt-file')->stack('line')->view('admin.buttons.receipt-file');
    }

    protected function setupShowOperation(): void
    {
        $this->setupListOperation();
        CRUD::column('notes')->label('Notes')->type('text');
    }

    protected function setupCreateOperation(): void
    {
        CRUD::setValidation(LedgerEntryRequest::class);
        CRUD::field('shop_id')->label('Shop')->type('select_from_array');
        $this->crud->modifyField('shop_id', ['options' => backpack_user()->accessibleShops()->pluck('name', 'id')->all()]);
        CRUD::field('type')->label('Entry type')->type('select_from_array');
        $this->crud->modifyField('type', ['options' => ['income' => 'Other income', 'expense' => 'Expense']]);
        foreach (['description' => 'text', 'category' => 'text', 'amount' => 'number', 'occurred_on' => 'date', 'notes' => 'textarea'] as $name => $type) {
            CRUD::field($name)->label(ucwords(str_replace('_', ' ', $name)))->type($type);
        }
        CRUD::field('amount')->attributes(['step' => '0.01', 'min' => '0.01']);
        CRUD::field('receipt')->label('Receipt (PDF or image, up to 10 MB)')->type('upload')->withFiles(['disk' => 'local', 'path' => 'receipts']);
    }

    protected function setupUpdateOperation(): void
    {
        $this->setupCreateOperation();
    }
}
