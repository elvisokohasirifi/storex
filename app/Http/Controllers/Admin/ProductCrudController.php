<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\ProductRequest;
use App\Models\Brand;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Shop;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Http\Controllers\Operations\CreateOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\ShowOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use Backpack\CRUD\app\Library\Widget;
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
        if (is_string(request()->query('shop_id')) && request()->query('shop_id') !== '') {
            CRUD::addClause('where', 'shop_id', request()->query('shop_id'));
        }
        CRUD::with(['shop', 'category', 'brand']);
        if (backpack_user()->is_platform_admin) {
            CRUD::denyAccess(['create', 'update']);
        } elseif (request()->route('id')) {
            $product = Product::whereIn('shop_id', backpack_user()->accessibleShops()->select('shops.id'))->whereKey(request()->route('id'))->firstOrFail();
            if ($product->shop->status === 'frozen') {
                CRUD::denyAccess('update');
            }
        }
    }

    protected function setupListOperation(): void
    {
        foreach (['name', 'barcode', 'visibility', 'selling_price', 'sale_price', 'quantity'] as $name) {
            CRUD::column($name)->label(ucwords(str_replace('_', ' ', $name)))->type('text');
        }
        CRUD::column('shop')->label('Shop')->type('select');
        $this->crud->modifyColumn('shop', ['entity' => 'shop', 'attribute' => 'name', 'model' => Shop::class]);
        foreach (['category' => ProductCategory::class, 'brand' => Brand::class] as $name => $model) {
            $this->crud->addColumn(['name' => $name, 'label' => ucfirst($name), 'type' => 'select', 'entity' => $name, 'attribute' => 'name', 'model' => $model]);
        }
        if (! backpack_user()->is_platform_admin) {
            CRUD::button('duplicate')->stack('line')->view('admin.buttons.duplicate');
            if ($this->crud->getCurrentOperation() === 'list') {
                CRUD::button('bulk_upload')->stack('top')->view('admin.buttons.bulk-products');
                Widget::add([
                    'name' => 'bulk_product_entry', 'type' => 'view', 'view' => 'admin.product-bulk-entry', 'section' => 'after_content',
                    'shops' => backpack_user()->accessibleShops()->where('status', '!=', 'frozen')->orderBy('name')->get(),
                ]);
            }
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
        foreach (['category_id' => ProductCategory::class, 'brand_id' => Brand::class] as $name => $model) {
            $options = $model::with('shop')->whereIn('shop_id', backpack_user()->accessibleShops()->select('shops.id'))->orderBy('name')->get()
                ->mapWithKeys(fn ($entry) => [$entry->id => $entry->shop->name.' — '.$entry->name])->all();
            $this->crud->addField(['name' => $name, 'label' => $name === 'category_id' ? 'Category' : 'Brand', 'type' => 'select_from_array', 'options' => $options, 'allows_null' => true, 'hint' => 'Optional. Choose an entry belonging to the selected shop.']);
            $classification = $name === 'category_id' ? 'category' : 'brand';
            $this->crud->addField(['name' => 'quick_'.$classification, 'label' => $classification, 'type' => 'classification_quick_create', 'target' => $name, 'endpoint' => route('product-classifications.'.$classification)]);
        }
        Widget::add(['type' => 'script', 'content' => asset('product-classifications.js')]);
        foreach (['name' => 'text', 'description' => 'textarea', 'cost_price' => 'number', 'selling_price' => 'number', 'sale_price' => 'number', 'quantity' => 'number', 'barcode' => 'text', 'sku' => 'text'] as $name => $type) {
            CRUD::field($name)->label(ucwords(str_replace('_', ' ', $name)))->type($type);
        }
        foreach (['cost_price', 'selling_price', 'sale_price'] as $name) {
            CRUD::field($name)->attributes(['step' => '0.01', 'min' => '0']);
        }
        CRUD::field('sale_price')->hint('Optional promotional price. Leave blank to use the normal selling price.')->attributes(['step' => '0.01', 'min' => '0.01']);
        CRUD::addField([
            'name' => 'visibility',
            'label' => 'Visibility',
            'type' => 'select_from_array',
            'options' => ['published' => 'Published', 'draft' => 'Draft'],
            'default' => 'published',
            'hint' => 'Published products appear on the storefront. Draft products stay private to the shop workspace and POS.',
        ]);
        CRUD::addField(['name' => 'barcode_scanner', 'label' => 'Barcode scanner', 'type' => 'barcode_scanner', 'target' => 'barcode']);
        CRUD::field('barcode_scanner')->after('barcode');
        CRUD::field('quantity')->hint('Leave blank to sell without tracking stock. Zero means out of stock.');
        CRUD::field('image')->label('Product image')->type('upload')->withFiles(['disk' => 'public', 'path' => 'products']);
        Widget::add(['type' => 'script', 'content' => asset('barcode-scanner.js')]);
    }

    protected function setupUpdateOperation(): void
    {
        $this->setupCreateOperation();
    }
}
