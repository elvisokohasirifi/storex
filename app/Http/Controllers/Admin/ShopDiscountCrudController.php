<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\ShopDiscountRequest;
use App\Models\Brand;
use App\Models\ProductCategory;
use App\Models\Shop;
use App\Models\ShopDiscount;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Http\Controllers\Operations\CreateOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\DeleteOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\ShowOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;

class ShopDiscountCrudController extends CrudController
{
    use CreateOperation;
    use DeleteOperation;
    use ListOperation;
    use ShowOperation;
    use UpdateOperation;

    public function setup(): void
    {
        abort_if(backpack_user()->is_platform_admin, 403);
        $manageableShopIds = backpack_user()->accessibleShops()->where('status', '!=', 'frozen')->get()
            ->filter(fn (Shop $shop) => backpack_user()->manages($shop, true))->modelKeys();
        CRUD::setModel(ShopDiscount::class);
        CRUD::setRoute(backpack_url('shop-discount'));
        CRUD::setEntityNameStrings('discount', 'discounts');
        CRUD::addClause('whereIn', 'shop_id', $manageableShopIds);
        if (is_string(request()->query('shop_id')) && request()->query('shop_id') !== '') {
            CRUD::addClause('where', 'shop_id', request()->query('shop_id'));
        }
        CRUD::with(['shop', 'category', 'brand']);
        if (request()->route('id')) {
            $discount = ShopDiscount::whereIn('shop_id', $manageableShopIds)->whereKey(request()->route('id'))->firstOrFail();
            if (! backpack_user()->manages($discount->shop, true) || $discount->shop->status === 'frozen') {
                CRUD::denyAccess(['update', 'delete']);
            }
        }
    }

    protected function setupListOperation(): void
    {
        foreach (['name', 'scope', 'type', 'value', 'is_active', 'starts_at', 'ends_at'] as $name) {
            CRUD::column($name)->label(ucwords(str_replace('_', ' ', $name)))->type('text');
        }
        CRUD::column('shop')->label('Shop')->type('select');
        $this->crud->modifyColumn('shop', ['entity' => 'shop', 'attribute' => 'name', 'model' => Shop::class]);
        foreach (['category' => ProductCategory::class, 'brand' => Brand::class] as $name => $model) {
            CRUD::addColumn(['name' => $name, 'label' => ucfirst($name), 'type' => 'select', 'entity' => $name, 'attribute' => 'name', 'model' => $model]);
        }
    }

    protected function setupShowOperation(): void
    {
        $this->setupListOperation();
    }

    protected function setupCreateOperation(): void
    {
        CRUD::setValidation(ShopDiscountRequest::class);
        CRUD::field('shop_id')->label('Shop')->type('select_from_array');
        $manageableShops = backpack_user()->accessibleShops()->where('status', '!=', 'frozen')->orderBy('name')->get()
            ->filter(fn (Shop $shop) => backpack_user()->manages($shop, true));
        $this->crud->modifyField('shop_id', ['options' => $manageableShops->pluck('name', 'id')->all()]);
        CRUD::addField([
            'name' => 'scope',
            'label' => 'Applies to',
            'type' => 'select_from_array',
            'options' => [
                'all_products' => 'All products',
                'category' => 'A product category',
                'brand' => 'A brand',
                'checkout' => 'Checkout total',
            ],
            'default' => 'all_products',
            'hint' => 'Product discounts reduce matching item prices. Checkout discounts reduce the final cart total.',
        ]);
        foreach (['category_id' => ProductCategory::class, 'brand_id' => Brand::class] as $name => $model) {
            $options = $model::with('shop')->whereIn('shop_id', $manageableShops->modelKeys())->orderBy('name')->get()
                ->mapWithKeys(fn ($entry) => [$entry->id => $entry->shop->name.' — '.$entry->name])->all();
            CRUD::addField(['name' => $name, 'label' => $name === 'category_id' ? 'Category' : 'Brand', 'type' => 'select_from_array', 'options' => $options, 'allows_null' => true, 'hint' => 'Only required when the discount applies to this target.']);
        }
        CRUD::field('name')->label('Discount name')->type('text');
        CRUD::addField(['name' => 'type', 'label' => 'Discount type', 'type' => 'select_from_array', 'options' => ['percentage' => 'Percentage', 'fixed' => 'Fixed amount'], 'default' => 'percentage']);
        CRUD::field('value')->label('Discount value')->type('number')->attributes(['step' => '0.01', 'min' => '0.01']);
        CRUD::field('is_active')->label('Active')->type('checkbox')->default(1);
        CRUD::field('starts_at')->label('Starts at')->type('datetime');
        CRUD::field('ends_at')->label('Ends at')->type('datetime');
    }

    protected function setupUpdateOperation(): void
    {
        $this->setupCreateOperation();
    }
}
