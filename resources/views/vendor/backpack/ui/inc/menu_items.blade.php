@php
    $currentUser = backpack_user();
    $canViewActivityLogs = false;

    if ($currentUser?->is_platform_admin) {
        $canViewActivityLogs = true;
    } elseif ($currentUser) {
        $canViewActivityLogs = $currentUser->accessibleShops()
            ->where(fn ($query) => $query
                ->where('owner_id', $currentUser->id)
                ->orWhereHas('members', fn ($query) => $query->where('user_id', $currentUser->id)->where('role', 'admin')))
            ->exists();
    }
@endphp
<x-backpack::menu-item title="Overview" icon="la la-home" :link="route('workspace.index')" />
<x-backpack::menu-item title="Shops" icon="la la-store" :link="route('shop.index')" />
@if(!$currentUser?->is_platform_admin)
<x-backpack::menu-item title="Products" icon="la la-box" :link="route('product.index')" />
<x-backpack::menu-item title="Inventory management" icon="la la-warehouse" :link="route('inventory-management.index')" />
<x-backpack::menu-item title="Product categories" icon="la la-tags" :link="route('product-category.index')" />
<x-backpack::menu-item title="Brands" icon="la la-certificate" :link="route('brand.index')" />
<x-backpack::menu-item title="Discounts" icon="la la-percent" :link="route('shop-discount.index')" />
@php($pendingManualSalesCount = $currentUser->accessibleShops()->join('orders', 'orders.shop_id', '=', 'shops.id')->where('orders.channel', 'manual')->where('orders.status', 'pending')->count())
<x-backpack::menu-item :title="'Sales'.($pendingManualSalesCount ? ' ('.$pendingManualSalesCount.')' : '')" icon="la la-receipt" :link="route('order.index')" />
@endif
@if($currentUser?->is_platform_admin)
<x-backpack::menu-item title="Approvals & moderation" icon="la la-check-circle" :link="route('moderation.index')" />
<x-backpack::menu-item title="Error logs" icon="la la-terminal" :link="backpack_url('log')" />
@endif
<x-backpack::menu-item title="Visit marketplace" icon="la la-external-link" :link="route('home')" />
@if(!$currentUser?->is_platform_admin)
<x-backpack::menu-item title="Income & expenses" icon="la la-file-invoice-dollar" :link="route('ledger-entry.index')" />
<x-backpack::menu-item title="Finance & tax records" icon="la la-chart-bar" :link="route('finance.index')" />
@endif
<x-backpack::menu-item title="Login & security" icon="la la-lock" :link="route('account.security')" />
@if($canViewActivityLogs)
<x-backpack::menu-item title="Activity logs" icon="la la-stream" :link="backpack_url('activity-log')" />
@endif
<x-backpack::menu-item title="Help" icon="la la-question-circle" :link="route('help')" />
