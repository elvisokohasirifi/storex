<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Shop;
use App\Services\SalesService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
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

    public function addToCart(Request $request, Shop $shop): RedirectResponse
    {
        abort_unless($shop->status === 'approved', 404);
        $data = $request->validate([
            'product_id' => ['required', 'uuid'],
            'quantity' => ['required', 'integer', 'min:1', 'max:10000'],
        ]);
        $product = $shop->products()->where('visibility', 'published')->whereKey($data['product_id'])->firstOrFail();
        if ($product->quantity !== null && $product->quantity < 1) {
            throw ValidationException::withMessages(['product_id' => 'This product is sold out.']);
        }
        $cart = $this->cart($request, $shop);
        $current = (int) ($cart[$product->id] ?? 0);
        $quantity = $current + $data['quantity'];
        if ($product->quantity !== null) {
            $quantity = min($quantity, $product->quantity);
        }
        $cart[$product->id] = $quantity;
        $this->putCart($request, $shop, $cart);

        return back()->with('success', $product->name.' added to cart.');
    }

    public function cartPage(Request $request, Shop $shop, SalesService $sales): Response
    {
        abort_unless($shop->status === 'approved', 404);
        [$cartProducts, $productPrices, $cart, $totals] = $this->cartDetails($request, $shop, $sales);

        return response()->view('storefront.cart', compact('shop', 'cartProducts', 'productPrices', 'cart', 'totals'))->header('Cache-Control', 'private, no-store');
    }

    public function updateCart(Request $request, Shop $shop): RedirectResponse
    {
        abort_unless($shop->status === 'approved', 404);
        $data = $request->validate(['items' => ['required', 'array', 'max:100'], 'items.*' => ['nullable', 'integer', 'min:0', 'max:10000']]);
        $products = $shop->products()->where('visibility', 'published')->whereIn('id', array_keys($data['items']))->get()->keyBy('id');
        $cart = [];
        foreach ($data['items'] as $productId => $quantity) {
            $product = $products->get($productId);
            if (! $product || (int) $quantity < 1) {
                continue;
            }
            $cart[$productId] = $product->quantity === null ? (int) $quantity : min((int) $quantity, $product->quantity);
        }
        $this->putCart($request, $shop, $cart);

        return back()->with('success', 'Cart updated.');
    }

    public function checkoutPage(Request $request, Shop $shop, SalesService $sales): Response
    {
        abort_unless($shop->status === 'approved', 404);
        [$cartProducts, $productPrices, $cart, $totals] = $this->cartDetails($request, $shop, $sales);
        abort_if($cartProducts->isEmpty(), 404);

        return response()->view('storefront.checkout', compact('shop', 'cartProducts', 'productPrices', 'cart', 'totals'))->header('Cache-Control', 'private, no-store');
    }

    /** @return array{0: Collection<int, Product>, 1: Collection<string, array{base: int, final: int, discount: int, discount_name: ?string}>, 2: array<string, int>, 3: array{subtotal: int, discount: int, total: int}} */
    private function cartDetails(Request $request, Shop $shop, SalesService $sales): array
    {
        $cart = $this->cart($request, $shop);
        $cartProducts = $shop->products()->with(['category', 'brand'])->where('visibility', 'published')->whereIn('id', array_keys($cart))->orderBy('name')->get();
        $discounts = $sales->activeDiscounts($shop);
        $productPrices = $cartProducts->mapWithKeys(fn (Product $product) => [$product->id => $sales->priceFor($product, $discounts)]);
        $subtotal = $cartProducts->sum(fn (Product $product): int => $productPrices[$product->id]['base'] * $cart[$product->id]);
        $lineTotal = $cartProducts->sum(fn (Product $product): int => $productPrices[$product->id]['final'] * $cart[$product->id]);
        $checkoutDiscount = $sales->checkoutDiscountFor($lineTotal, $discounts);
        $total = $lineTotal - $checkoutDiscount['amount'];

        return [$cartProducts, $productPrices, $cart, ['subtotal' => $subtotal, 'discount' => $subtotal - $total, 'total' => $total]];
    }

    /** @return array<string, int> */
    private function cart(Request $request, Shop $shop): array
    {
        return $request->session()->get($this->cartKey($shop), []);
    }

    /** @param array<string, int> $cart */
    private function putCart(Request $request, Shop $shop, array $cart): void
    {
        $request->session()->put($this->cartKey($shop), $cart);
    }

    private function cartKey(Shop $shop): string
    {
        return 'storefront_cart_'.$shop->id;
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
        [$cartProducts, $cartProductPrices, $cart, $cartTotals] = $isPreview ? [collect(), collect(), [], ['subtotal' => 0, 'discount' => 0, 'total' => 0]] : $this->cartDetails($request, $shop, $sales);

        $contacts = collect(preg_split('/[\r\n,;]+/', $shop->contacts ?? '') ?: [])->map(function (string $contact): array {
            $label = trim($contact);
            $phone = preg_replace('/[\s().-]+/', '', $label);

            return ['label' => $label, 'phone' => preg_match('/^\+?[0-9]{7,15}$/', $phone) ? $phone : null];
        })->filter(fn (array $contact) => $contact['label'] !== '');

        return response()->view('storefront.shop', compact('shop', 'products', 'productPrices', 'search', 'isAdminPreview', 'isOwnerPreview', 'isPreview', 'contacts', 'cartProducts', 'cartProductPrices', 'cart', 'cartTotals'))
            ->header('Cache-Control', 'private, no-store');
    }
}
