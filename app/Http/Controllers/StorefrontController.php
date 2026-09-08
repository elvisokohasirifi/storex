<?php

namespace App\Http\Controllers;

use App\Models\Shop;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StorefrontController extends Controller
{
    public function index(Request $request): View
    {
        $search = mb_substr((string) $request->query('q', ''), 0, 100);
        $shops = Shop::where('status', 'approved')->when($search, fn ($query) => $query->where('name', 'like', '%'.$search.'%'))
            ->withCount(['products' => fn ($query) => $query->where('status', 'approved')])->orderBy('name')->paginate(12)->withQueryString();

        return view('storefront.index', compact('shops', 'search'));
    }

    public function show(Request $request, Shop $shop): View
    {
        abort_unless($shop->status === 'approved', 404);
        $search = mb_substr((string) $request->query('q', ''), 0, 100);
        $products = $shop->products()->where('status', 'approved')->when($search, fn ($query) => $query->where(fn ($query) => $query
            ->where('name', 'like', '%'.$search.'%')->orWhere('barcode', $search)))->orderBy('name')->paginate(24)->withQueryString();

        return view('storefront.shop', compact('shop', 'products', 'search'));
    }
}
