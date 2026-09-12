<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\View\View;

class HelpController extends Controller
{
    public function __invoke(): View
    {
        $user = backpack_user();
        $context = $this->roleContext($user);
        $sections = $this->sections($context);

        return view('admin.help', compact('context', 'sections'));
    }

    /** @return array{role:string, description:string, canCreateShop:bool, canManageShop:bool, canUseShopWorkspace:bool, isPlatformAdmin:bool} */
    private function roleContext(User $user): array
    {
        if ($user->is_platform_admin) {
            return [
                'role' => 'App admin',
                'description' => 'You can review shops, shop users, products, activity logs, and error logs for moderation. Sales, finance, tax, income, expenses, receipts, and payment credentials stay hidden.',
                'canCreateShop' => false,
                'canManageShop' => false,
                'canUseShopWorkspace' => false,
                'isPlatformAdmin' => true,
            ];
        }

        $hasManagedShop = $user->accessibleShops()
            ->where(fn ($query) => $query
                ->where('owner_id', $user->id)
                ->orWhereHas('members', fn ($query) => $query->where('user_id', $user->id)->where('role', 'admin')))
            ->exists();

        $hasWorkspace = $user->accessibleShops()->exists();
        $role = $hasManagedShop ? 'Shop admin' : ($hasWorkspace ? 'Store manager' : 'Shop owner');

        return [
            'role' => $role,
            'description' => match ($role) {
                'Shop admin' => 'You can create and manage shops, users, products, stock, checkout settings, discounts, sales, and finance records for shops you administer.',
                'Store manager' => 'You can work inside shops assigned to you, manage products, sell through the till, and record sales-related work where your shop permits it. Shop setup, staff, payment credentials, discounts, and inventory administration stay with shop admins.',
                default => 'You can create a shop, complete its profile, add products, and submit it for platform approval before customers can buy from it.',
            },
            'canCreateShop' => true,
            'canManageShop' => $hasManagedShop,
            'canUseShopWorkspace' => $hasWorkspace,
            'isPlatformAdmin' => false,
        ];
    }

    /**
     * @param  array{role:string, description:string, canCreateShop:bool, canManageShop:bool, canUseShopWorkspace:bool, isPlatformAdmin:bool}  $context
     * @return array<int, array{title:string, summary:string, items:array<int, array{title:string, body:string, fields?:array<int, array{name:string, help:string}>}>}>
     */
    private function sections(array $context): array
    {
        if ($context['isPlatformAdmin']) {
            return $this->platformAdminSections();
        }

        return array_values(array_filter([
            $this->shopOverviewSection($context),
            $this->productsSection(),
            $context['canManageShop'] ? $this->inventorySection() : null,
            $context['canManageShop'] ? $this->operationsSection() : null,
            $this->salesSection(),
            $context['canManageShop'] ? $this->discountsSection() : null,
            $context['canManageShop'] ? $this->teamAndPaymentsSection() : null,
            $this->financeSection(),
            $this->storefrontSection(),
            $this->activityAndSecuritySection($context),
        ]));
    }

    /** @return array<int, array{title:string, summary:string, items:array<int, array{title:string, body:string, fields?:array<int, array{name:string, help:string}>}>}> */
    private function platformAdminSections(): array
    {
        return [
            [
                'title' => 'Moderation overview',
                'summary' => 'Use the overview to see the whole platform without entering shop financial areas.',
                'items' => [
                    ['title' => 'Dashboard cards', 'body' => 'The top cards show total shops, shops pending approval, users, products, and products pending attention. Different colors separate the moderation counts at a glance.'],
                    ['title' => 'Shops needing attention', 'body' => 'The overview lists shops that are pending, frozen, rejected, or have content that needs review. Open a shop to review its profile, users, and products.'],
                    ['title' => 'Registered shops', 'body' => 'The Shops page lets you inspect shop records. App admins can approve, freeze, or reject shops, but cannot edit or delete shop business data.'],
                ],
            ],
            [
                'title' => 'Shop and product moderation',
                'summary' => 'Moderate public-facing shop and product information before customers see it.',
                'items' => [
                    ['title' => 'Shop details', 'body' => 'The shop detail page shows the logo, banner, description, location, contact details, assigned users, and products. It does not expose sales, finance, tax, payment credentials, or receipts.'],
                    ['title' => 'Storefront preview', 'body' => 'Open the storefront link to preview the shop page even if it is not yet approved. Preview mode shows moderation controls and hides checkout controls.'],
                    ['title' => 'Approve, freeze, and reject', 'body' => 'Approve a valid shop so its storefront can be public. Freeze or reject when a shop should not operate. Give clear reasons so the moderation history stays useful.'],
                ],
            ],
            [
                'title' => 'Logs',
                'summary' => 'Use logs for moderation and support without entering shop financial records.',
                'items' => [
                    ['title' => 'Activity logs', 'body' => 'Activity logs show changes to moderation-safe records such as shops, products, categories, brands, variants, and shop members. Sensitive payment data is excluded.'],
                    ['title' => 'Error logs', 'body' => 'Error logs show Laravel application errors. Use them to diagnose failed pages, uploads, or checkout errors. Only app admins can see error logs.'],
                ],
            ],
            [
                'title' => 'Account security',
                'summary' => 'Manage your own admin login settings.',
                'items' => [
                    ['title' => 'Login and security', 'body' => 'Use this page to update credentials used to access the admin area. App admins do not use shop payment or finance screens.'],
                ],
            ],
        ];
    }

    /** @param array{canCreateShop:bool, canManageShop:bool} $context */
    private function shopOverviewSection(array $context): array
    {
        return [
            'title' => 'Shops and overview',
            'summary' => 'Start from the overview to create shops, open workspaces, and see shop status.',
            'items' => [
                ['title' => 'Create a shop', 'body' => $context['canCreateShop'] ? 'Create a shop by entering its name, location, contact details, and email. Logo, banner, and description are optional but help customers understand the shop.' : 'Only shop users can create shops. App admins review submitted shops instead.', 'fields' => [
                    ['name' => 'Name', 'help' => 'The public shop name. It is also used to create the unique storefront slug.'],
                    ['name' => 'Description', 'help' => 'Optional public description. Line breaks are preserved on the storefront.'],
                    ['name' => 'Logo and banner', 'help' => 'Optional images used on the storefront and admin preview.'],
                    ['name' => 'Location, contacts, email', 'help' => 'Public contact details customers use to identify and reach the shop.'],
                    ['name' => 'Enable inventory management', 'help' => 'When enabled, products need cost price and quantity, and stock changes move through inventory tools instead of direct quantity edits.'],
                ]],
                ['title' => 'Shop status', 'body' => 'New or edited shops can go pending until an app admin reviews them. Approved shops can appear publicly. Frozen or rejected shops need action before normal selling continues.'],
                ['title' => 'Shop workspace', 'body' => 'Open a shop workspace to see its summary, products, till, users, payment settings, inventory pages, and other tools permitted for your role.'],
            ],
        ];
    }

    private function productsSection(): array
    {
        return [
            'title' => 'Products, categories, and brands',
            'summary' => 'Set up products customers can buy and cashiers can sell.',
            'items' => [
                ['title' => 'Products', 'body' => 'Products hold the details used in the admin, till, and storefront. You can add one product, duplicate an existing product, or upload products in bulk from the products page.', 'fields' => [
                    ['name' => 'Name', 'help' => 'The product name customers and cashiers see.'],
                    ['name' => 'Description', 'help' => 'Optional details shown to customers on the storefront.'],
                    ['name' => 'Image', 'help' => 'Optional product photo used on storefront product cards.'],
                    ['name' => 'Cost price', 'help' => 'What the shop pays for the item. Required when inventory management is enabled and used for margins and stock valuation.'],
                    ['name' => 'Selling price', 'help' => 'The normal customer price.'],
                    ['name' => 'Sale price', 'help' => 'Optional discounted product-specific price. Leave blank when the normal selling price should apply.'],
                    ['name' => 'Quantity', 'help' => 'Current stock count. With inventory management enabled, quantity is controlled through stock movements instead of direct edits.'],
                    ['name' => 'Reorder level', 'help' => 'The quantity where the product should be treated as low stock. The default is 10.'],
                    ['name' => 'Barcode and SKU', 'help' => 'Barcode supports scanning in product forms and the till. SKU is an internal stock code.'],
                    ['name' => 'Visibility', 'help' => 'Published products can appear publicly. Draft products stay private to the shop workspace and POS.'],
                ]],
                ['title' => 'Categories and brands', 'body' => 'Categories and brands organize products and power storefront filters. If a category or brand does not exist while creating a product, add it first from its page or use the available inline creation controls.'],
                ['title' => 'Bulk products', 'body' => 'Use bulk upload on the products page to create up to 100 products from CSV. Download the sample CSV first so the column names match exactly.'],
            ],
        ];
    }

    private function inventorySection(): array
    {
        return [
            'title' => 'Inventory management',
            'summary' => 'Track stock movement, value, and low stock when inventory management is enabled on a shop.',
            'items' => [
                ['title' => 'Inventory management page', 'body' => 'This page summarizes stock value, potential sales, potential margin, low stock, and shops using inventory management.'],
                ['title' => 'Receive stock', 'body' => 'Use Receive Stock when new stock arrives. The system increases quantity and records a purchase movement.', 'fields' => [
                    ['name' => 'Product', 'help' => 'The item receiving stock.'],
                    ['name' => 'Quantity', 'help' => 'How many units arrived.'],
                    ['name' => 'Unit cost', 'help' => 'Optional cost per unit. Updates the product cost when provided.'],
                    ['name' => 'Supplier, batch number, expiry date', 'help' => 'Optional details for traceability and expiry monitoring.'],
                ]],
                ['title' => 'Stock adjustment', 'body' => 'Use Stock Adjustment when physical stock does not match the system. Enter the actual quantity, choose a reason such as damaged or expired, and add notes.'],
                ['title' => 'Returns', 'body' => 'Use returns to add stock back after a customer return or correction.'],
                ['title' => 'Stock details', 'body' => 'Each product has a stock details page showing current stock, reorder level, average cost, stock value, and the movement history that explains how the current quantity was reached.'],
                ['title' => 'Low stock and reorder planning', 'body' => 'Products at or below reorder level appear as low stock. Use the current quantity and reorder level to decide how much to purchase.'],
            ],
        ];
    }

    private function operationsSection(): array
    {
        return [
            'title' => 'Operations',
            'summary' => 'Use operations for suppliers, purchase orders, price changes, product variants, transfers, and till shifts.',
            'items' => [
                ['title' => 'Suppliers and purchase orders', 'body' => 'Create supplier records and purchase orders, then receive purchase orders into stock when items arrive.'],
                ['title' => 'Bulk price updates', 'body' => 'Update selling and sale prices with a CSV. Match rows by SKU, barcode, or product name.'],
                ['title' => 'Product variants', 'body' => 'Variants let one product have sellable options with their own SKU, barcode, price, cost, quantity, and reorder level.'],
                ['title' => 'Stock transfers', 'body' => 'Move stock between shops you administer when both shops use inventory management.'],
                ['title' => 'Till shifts', 'body' => 'Open a shift before selling and close it when cashing up. Opening cash and closing cash help compare expected and actual cash.'],
            ],
        ];
    }

    private function salesSection(): array
    {
        return [
            'title' => 'Till, sales, receipts, and manual payments',
            'summary' => 'Sell products through the till and manage manual payments.',
            'items' => [
                ['title' => 'Till / point of sale', 'body' => 'The till lists products in a table. Use Add to cart, plus, minus, and quantity inputs to build the cart. The order summary shows totals before checkout.'],
                ['title' => 'Barcode scanning', 'body' => 'When a device has a camera or scanner, use barcode scanning while adding or editing products and while using the till to find products quickly.'],
                ['title' => 'Manual payments', 'body' => 'If Paystack is not configured on the storefront, customers can reserve an order and pay by cash or mobile money. Manual sales appear under Sales until a shop user confirms payment.'],
                ['title' => 'Receipts', 'body' => 'After a sale, open the receipt page to download the PDF receipt or share it with the customer through WhatsApp. The Back to shop button in receipt context returns to the till.'],
                ['title' => 'Refunds and cancellations', 'body' => 'When a sale is cancelled or refunded, tracked stock movements are reversed so cashiers do not manually reduce or increase stock.'],
            ],
        ];
    }

    private function discountsSection(): array
    {
        return [
            'title' => 'Discounts',
            'summary' => 'Create discounts for the whole shop, selected categories, selected brands, or checkout totals.',
            'items' => [
                ['title' => 'Storewide discounts', 'body' => 'Apply a discount to all eligible products in a shop during the configured period.'],
                ['title' => 'Category and brand discounts', 'body' => 'Limit a discount to products in chosen categories or brands. This works best when products are consistently classified.'],
                ['title' => 'Checkout discounts', 'body' => 'Use checkout-level discounts when the discount should apply to the order total rather than individual product prices.'],
            ],
        ];
    }

    private function teamAndPaymentsSection(): array
    {
        return [
            'title' => 'Users and payment credentials',
            'summary' => 'Shop admins control staff access and customer payment options.',
            'items' => [
                ['title' => 'Users', 'body' => 'Add or update shop users from the Users page. Shop admins can manage settings and staff. Store managers can work in assigned shops but cannot manage staff or credentials.', 'fields' => [
                    ['name' => 'Name and email', 'help' => 'Used to create a new user or find an existing user.'],
                    ['name' => 'Initial password', 'help' => 'Required only for new accounts. Existing users keep their current password.'],
                    ['name' => 'Role', 'help' => 'Choose Shop admin for setup permissions or Store manager for day-to-day shop work.'],
                ]],
                ['title' => 'Payment credentials', 'body' => 'Use this page to set Paystack public and secret keys, shop currency, and mobile money details. Secret keys are stored encrypted and are never shown back in the admin.', 'fields' => [
                    ['name' => 'Public key', 'help' => 'Paystack key beginning with pk_test_ or pk_live_.'],
                    ['name' => 'Secret key', 'help' => 'Paystack key beginning with sk_test_ or sk_live_. Leave blank to keep the existing secret.'],
                    ['name' => 'Currency', 'help' => 'Currency used for checkout and receipts. Do not change while pending checkouts are active.'],
                    ['name' => 'Momo number and name', 'help' => 'Shown to customers when they choose manual mobile money payment.'],
                ]],
            ],
        ];
    }

    private function financeSection(): array
    {
        return [
            'title' => 'Income, expenses, finance, and tax records',
            'summary' => 'Track non-sales income and expenses for reporting and taxation. App admins cannot see these records.',
            'items' => [
                ['title' => 'Income and expenses', 'body' => 'Record other income and shop expenses that are not created by product sales.', 'fields' => [
                    ['name' => 'Shop', 'help' => 'The shop the entry belongs to.'],
                    ['name' => 'Type', 'help' => 'Income increases taxable inflows; expense records costs.'],
                    ['name' => 'Category', 'help' => 'Group entries such as rent, utilities, repairs, delivery, or other income.'],
                    ['name' => 'Amount and currency', 'help' => 'The money value of the record.'],
                    ['name' => 'Occurred on', 'help' => 'The date the income or expense happened.'],
                    ['name' => 'Receipt', 'help' => 'Optional file or image proof for the entry.'],
                ]],
                ['title' => 'Finance and tax records', 'body' => 'Use the finance page to filter by shop and date range, review summaries, and export CSV records for tax preparation.'],
            ],
        ];
    }

    private function storefrontSection(): array
    {
        return [
            'title' => 'Storefront, cart, and checkout',
            'summary' => 'Customers browse approved storefronts, add items to cart, and check out.',
            'items' => [
                ['title' => 'Storefront page', 'body' => 'The storefront shows the shop logo, banner, contact links, products, and navigation to all products. The homepage limits product display and links to the full products page.'],
                ['title' => 'Product filters', 'body' => 'The all products page loads products and lets customers filter quickly by search text, category, and brand without reloading the page.'],
                ['title' => 'Cart', 'body' => 'Customers add items with JavaScript, hover over the cart icon to preview items, and open the cart page to adjust quantities.'],
                ['title' => 'Checkout', 'body' => 'Checkout accepts Paystack when credentials exist. Without Paystack, customers can choose cash or momo and receive a short alphanumeric reference for manual payment.'],
            ],
        ];
    }

    /** @param array{canManageShop:bool} $context */
    private function activityAndSecuritySection(array $context): array
    {
        return [
            'title' => 'Activity logs and security',
            'summary' => 'Review permitted activity history and manage your own login details.',
            'items' => array_values(array_filter([
                $context['canManageShop'] ? ['title' => 'Activity logs', 'body' => 'Shop admins can see activity logs that relate to their own shops, including changes to shops, products, categories, brands, variants, and shop members.'] : null,
                ['title' => 'Login methods', 'body' => 'Users can log in with email and password, phone and six-digit PIN, or Google login when configured.'],
                ['title' => 'Login and security', 'body' => 'Use this page to update your own login details and security settings.'],
            ])),
        ];
    }
}
