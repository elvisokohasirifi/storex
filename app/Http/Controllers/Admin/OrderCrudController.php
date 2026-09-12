<?php

namespace App\Http\Controllers\Admin;

use App\Models\Order;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\ShowOperation;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use Backpack\CRUD\app\Library\Widget;

class OrderCrudController extends CrudController
{
    use ListOperation;
    use ShowOperation;

    public function setup(): void
    {
        abort_if(backpack_user()->is_platform_admin, 403);
        CRUD::setModel(Order::class);
        CRUD::setRoute(backpack_url('order'));
        CRUD::setEntityNameStrings('sale', 'sales');
        CRUD::addClause('whereIn', 'shop_id', backpack_user()->accessibleShops()->select('shops.id'));
        CRUD::with(['shop']);
    }

    protected function setupListOperation(): void
    {
        $this->addPendingManualSalesWidget();
        $this->setupSaleColumnsAndButtons();
    }

    protected function setupShowOperation(): void
    {
        $this->setupSaleColumnsAndButtons();
    }

    private function addPendingManualSalesWidget(): void
    {
        $pendingManualSalesQuery = Order::whereIn('shop_id', backpack_user()->accessibleShops()->select('shops.id'))
            ->where('channel', 'manual')
            ->where('status', 'pending');

        $pendingManualOrders = Order::with(['shop', 'items'])
            ->whereIn('shop_id', backpack_user()->accessibleShops()->select('shops.id'))
            ->where('channel', 'manual')
            ->where('status', 'pending')
            ->latest()
            ->limit(20)
            ->get();
        $pendingManualCount = (clone $pendingManualSalesQuery)->count();
        $pendingManualTotal = (clone $pendingManualSalesQuery)->sum('total');

        Widget::add([
            'type' => 'view',
            'view' => 'admin.widgets.pending-manual-sales',
            'to' => 'before_content',
            'pendingManualOrders' => $pendingManualOrders,
            'pendingManualCount' => $pendingManualCount,
            'pendingManualTotal' => $pendingManualTotal,
        ]);
    }

    private function setupSaleColumnsAndButtons(): void
    {
        foreach (['receipt_number', 'reference', 'customer_name', 'customer_phone', 'customer_email', 'currency', 'payment_method', 'channel', 'status', 'created_at'] as $name) {
            CRUD::column($name)->label(ucwords(str_replace('_', ' ', $name)))->type('text');
        }
        CRUD::column('total')->label('Total')->type('closure');
        $this->crud->modifyColumn('total', ['function' => fn ($entry) => number_format($entry->total / 100, 2)]);
        CRUD::button('receipt')->stack('line')->view('admin.buttons.receipt');
        CRUD::button('confirm_manual_sale')->stack('line')->view('admin.buttons.confirm-manual-sale');
        CRUD::button('cancel_sale')->stack('line')->view('admin.buttons.cancel-sale');
        CRUD::button('refund_sale')->stack('line')->view('admin.buttons.refund-sale');
    }
}
