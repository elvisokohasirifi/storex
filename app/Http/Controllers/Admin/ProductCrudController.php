<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\ProductRequest;
use App\Models\Product;
use App\Models\Shop;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Http\Controllers\Operations\CreateOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\ShowOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use Illuminate\Support\Str;

class ProductCrudController extends CrudController
{
    use CreateOperation;
    use ListOperation;
    use ShowOperation;
    use UpdateOperation;

    public function setup(): void
    {
        CRUD::setModel(Product::class);
        CRUD::setRoute(backpack_url('product'));
        CRUD::setEntityNameStrings('product', 'products');
        CRUD::addClause('whereIn', 'shop_id', backpack_user()->accessibleShops()->select('shops.id'));
        CRUD::with('shop');
        if (backpack_user()->is_platform_admin) {
            CRUD::denyAccess(['create', 'update']);
        } elseif (request()->route('id')) {
            $product = Product::whereIn('shop_id', backpack_user()->accessibleShops()->select('shops.id'))->whereKey(request()->route('id'))->firstOrFail();
            if ($product->status === 'frozen' || $product->shop->status === 'frozen') {
                CRUD::denyAccess('update');
            }
        }
    }

    protected function setupListOperation(): void
    {
        foreach (['name', 'barcode', 'selling_price', 'quantity', 'status'] as $name) {
            CRUD::column($name)->label(ucwords(str_replace('_', ' ', $name)))->type('text');
        }
        CRUD::column('shop')->label('Shop')->type('select');
        $this->crud->modifyColumn('shop', ['entity' => 'shop', 'attribute' => 'name', 'model' => Shop::class]);
        if (! backpack_user()->is_platform_admin) {
            CRUD::button('duplicate')->stack('line')->view('admin.buttons.duplicate');
        }
    }

    protected function setupShowOperation(): void
    {
        $this->setupListOperation();
        CRUD::column('image')->label('Product image')->type('image');
        $this->crud->modifyColumn('image', ['disk' => 'public', 'height' => '200px', 'value' => fn (Product $entry) => $entry->image ? Str::start($entry->image, 'products/') : null]);
        foreach (['description', 'sku', 'cost_price'] as $name) {
            CRUD::column($name)->label(ucwords(str_replace('_', ' ', $name)))->type('text');
        }
    }

    protected function setupCreateOperation(): void
    {
        CRUD::setValidation(ProductRequest::class);
        CRUD::field('shop_id')->label('Shop')->type('select_from_array');
        $this->crud->modifyField('shop_id', ['options' => backpack_user()->accessibleShops()->where('status', '!=', 'frozen')->pluck('name', 'id')->all()]);
        foreach (['name' => 'text', 'description' => 'textarea', 'cost_price' => 'number', 'selling_price' => 'number', 'quantity' => 'number', 'barcode' => 'text', 'sku' => 'text'] as $name => $type) {
            CRUD::field($name)->label(ucwords(str_replace('_', ' ', $name)))->type($type);
        }
        foreach (['cost_price', 'selling_price'] as $name) {
            CRUD::field($name)->attributes(['step' => '0.01', 'min' => '0']);
        }
        CRUD::field('quantity')->hint('Leave blank to sell without tracking stock. Zero means out of stock.');
        CRUD::field('image')->label('Product image')->type('upload')->withFiles(['disk' => 'public', 'path' => 'products']);
    }

    protected function setupUpdateOperation(): void
    {
        $this->setupCreateOperation();
    }
}
