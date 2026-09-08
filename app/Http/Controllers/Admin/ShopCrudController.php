<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\ShopRequest;
use App\Models\Shop;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Http\Controllers\Operations\CreateOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\ShowOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;

class ShopCrudController extends CrudController
{
    use CreateOperation;
    use ListOperation;
    use ShowOperation { show as protected showShopDetails; }
    use UpdateOperation;

    public function show(string $id): RedirectResponse
    {
        $shop = backpack_user()->accessibleShops()->whereKey($id)->firstOrFail();

        return redirect()->route('workspace.show', $shop);
    }

    public function setup(): void
    {
        CRUD::setModel(Shop::class);
        CRUD::setRoute(backpack_url('shop'));
        CRUD::setEntityNameStrings('shop', 'shops');
        CRUD::addClause('whereIn', 'id', backpack_user()->accessibleShops()->select('shops.id'));
        if (backpack_user()->is_platform_admin) {
            CRUD::denyAccess(['create', 'update']);
        } elseif (request()->route('id')) {
            $shop = backpack_user()->accessibleShops()->whereKey(request()->route('id'))->firstOrFail();
            if (! backpack_user()->manages($shop, true) || $shop->status === 'frozen') {
                CRUD::denyAccess('update');
            }
        }
    }

    protected function setupListOperation(): void
    {
        foreach (['name', 'slug', 'location', 'email', 'status'] as $name) {
            CRUD::column($name)->label(ucfirst($name))->type('text');
        }
    }

    protected function setupShowOperation(): void
    {
        $this->setupListOperation();
        foreach (['logo', 'banner'] as $name) {
            CRUD::column($name)->label(ucfirst($name))->type('image');
            $this->crud->modifyColumn($name, ['disk' => 'public', 'height' => '200px', 'value' => fn (Shop $entry) => $entry->{$name} ? Str::start($entry->{$name}, 'shops/') : null]);
        }
        foreach (['description', 'contacts'] as $name) {
            CRUD::column($name)->label(ucfirst($name))->type('text');
        }
    }

    protected function setupCreateOperation(): void
    {
        CRUD::setValidation(ShopRequest::class);
        foreach (['name' => 'text', 'slug' => 'text', 'description' => 'textarea', 'location' => 'text', 'contacts' => 'text', 'email' => 'email'] as $name => $type) {
            CRUD::field($name)->label(ucfirst($name))->type($type);
        }
        CRUD::field('slug')->hint('Optional. A unique storefront address is generated if left blank.');
        foreach (['logo', 'banner'] as $name) {
            CRUD::field($name)->label(ucfirst($name))->type('upload')->withFiles(['disk' => 'public', 'path' => 'shops']);
        }
    }

    protected function setupUpdateOperation(): void
    {
        $this->setupCreateOperation();
    }
}
