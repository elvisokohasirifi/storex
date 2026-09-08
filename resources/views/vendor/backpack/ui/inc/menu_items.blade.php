<x-backpack::menu-item title="Overview" icon="la la-home" :link="route('workspace.index')" />
<x-backpack::menu-item title="Shops" icon="la la-store" :link="route('shop.index')" />
<x-backpack::menu-item title="Inventory" icon="la la-box" :link="route('product.index')" />
@if(!backpack_user()?->is_platform_admin)
<x-backpack::menu-item title="Sales" icon="la la-receipt" :link="route('order.index')" />
@endif
@if(backpack_user()?->is_platform_admin)
<x-backpack::menu-item title="Approvals & moderation" icon="la la-check-circle" :link="route('moderation.index')" />
@endif
<x-backpack::menu-item title="Visit marketplace" icon="la la-external-link" :link="route('home')" />
@if(!backpack_user()?->is_platform_admin)
<x-backpack::menu-item title="Income & expenses" icon="la la-file-invoice-dollar" :link="route('ledger-entry.index')" />
<x-backpack::menu-item title="Finance & tax records" icon="la la-chart-bar" :link="route('finance.index')" />
@endif
<x-backpack::menu-item title="Login & security" icon="la la-lock" :link="route('account.security')" />
