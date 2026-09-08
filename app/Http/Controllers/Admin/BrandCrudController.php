<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\BrandRequest;
use App\Models\Brand;
use App\Models\Shop;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Http\Controllers\Operations\CreateOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\ShowOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;

class BrandCrudController extends CrudController
{
    use CreateOperation, ListOperation, ShowOperation, UpdateOperation;

    public function setup(): void
    {
        CRUD::setModel(Brand::class);
        CRUD::setRoute(backpack_url('brand'));
        CRUD::setEntityNameStrings('brand', 'brands');
        CRUD::addClause('whereIn', 'shop_id', backpack_user()->accessibleShops()->select('shops.id'));
        CRUD::with('shop');
        if (backpack_user()->is_platform_admin) {
            CRUD::denyAccess(['create', 'update']);
        } elseif (request()->route('id')) {
            $entry = Brand::whereIn('shop_id', backpack_user()->accessibleShops()->select('shops.id'))->whereKey(request()->route('id'))->firstOrFail();
            if ($entry->shop->status === 'frozen') {
                CRUD::denyAccess('update');
            }
        }
    }

    protected function setupListOperation(): void
    {
        $this->crud->addColumn(['name' => 'name', 'label' => 'Name', 'type' => 'text']);
        $this->crud->addColumn(['name' => 'shop', 'label' => 'Shop', 'type' => 'select', 'entity' => 'shop', 'attribute' => 'name', 'model' => Shop::class]);
    }

    protected function setupShowOperation(): void
    {
        $this->setupListOperation();
        $this->crud->addColumn(['name' => 'description', 'label' => 'Description', 'type' => 'textarea']);
    }

    protected function setupCreateOperation(): void
    {
        CRUD::setValidation(BrandRequest::class);
        $this->crud->addField(['name' => 'shop_id', 'label' => 'Shop', 'type' => 'select_from_array', 'options' => backpack_user()->accessibleShops()->where('status', '!=', 'frozen')->pluck('name', 'id')->all()]);
        $this->crud->addField(['name' => 'name', 'label' => 'Name', 'type' => 'text']);
        $this->crud->addField(['name' => 'description', 'label' => 'Description', 'type' => 'textarea']);
    }

    protected function setupUpdateOperation(): void
    {
        $this->setupCreateOperation();
    }
}
