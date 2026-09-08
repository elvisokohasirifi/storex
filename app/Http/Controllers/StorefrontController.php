<?php

namespace App\Http\Controllers;

use App\Models\Shop;
use App\Services\SalesService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class StorefrontController extends Controller
{
    public function index(Request $request): View
    {
        $search = mb_substr((string) $request->query('q', ''), 0, 100);
        $shops = Shop::where('status', 'approved')->when($search, fn ($query) => $query->where('name', 'like', '%'.$search.'%'))
            ->withCount(['products' => fn ($query) => $query->where('visibility', 'published')])->orderBy('name')->paginate(12)->withQueryString();

        return view('storefront.index', compact('shops', 'search'));
    }

    public function show(Request $request, Shop $shop, SalesService $sales): Response
    {
        $isAdminPreview = (bool) backpack_user()?->is_platform_admin;
        $isOwnerPreview = ! $isAdminPreview && backpack_user()?->id === $shop->owner_id;
        $isPreview = $isAdminPreview || $isOwnerPreview;
        abort_unless($shop->status === 'approved' || $isPreview, 404);
        $search = mb_substr((string) $request->query('q', ''), 0, 100);
        $products = $shop->products()->with(['category', 'brand'])->when(! $isPreview, fn ($query) => $query->where('visibility', 'published'))->when($search, fn ($query) => $query->where(fn ($query) => $query
            ->where('name', 'like', '%'.$search.'%')->orWhere('barcode', $search)))->orderBy('name')->paginate(24)->withQueryString();
        $discounts = $sales->activeDiscounts($shop);
        $productPrices = $products->getCollection()->mapWithKeys(fn ($product) => [$product->id => $sales->priceFor($product, $discounts)]);

        $contacts = collect(preg_split('/[\r\n,;]+/', $shop->contacts ?? '') ?: [])->map(function (string $contact): array {
            $label = trim($contact);
            $phone = preg_replace('/[\s().-]+/', '', $label);

            return ['label' => $label, 'phone' => preg_match('/^\+?[0-9]{7,15}$/', $phone) ? $phone : null];
        })->filter(fn (array $contact) => $contact['label'] !== '');

        return response()->view('storefront.shop', compact('shop', 'products', 'productPrices', 'search', 'isAdminPreview', 'isOwnerPreview', 'isPreview', 'contacts'))
            ->header('Cache-Control', 'private, no-store');
    }
}
