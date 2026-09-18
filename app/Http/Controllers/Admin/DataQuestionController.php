<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DataQuestion;
use App\Models\Order;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class DataQuestionController extends Controller
{
    public function index(Request $request, string $shop): View
    {
        $shop = $this->shop($shop);
        $history = $shop->dataQuestions()->with('user:id,name')->latest()->paginate(12)->withQueryString();
        $selectedQuestion = null;
        $selectedId = (string) $request->query('question_id', '');
        if ($selectedId !== '') {
            $selectedQuestion = $shop->dataQuestions()->with('user:id,name')->whereKey($selectedId)->firstOrFail();
        }

        $question = $selectedQuestion?->question ?? '';
        $answer = $selectedQuestion?->answer;
        $canManage = true;
        $examples = [
            'Which sales person sold the most in 2026?',
            'Generate a bar chart comparing Ama Seller and Kojo Seller monthly sales for the year 2026.',
            'What were total sales in 2026?',
            'Which product sold the most this year?',
            'How many orders did we have today?',
        ];

        return view('admin.data-questions', compact('shop', 'canManage', 'question', 'answer', 'examples', 'history', 'selectedQuestion'));
    }

    public function store(Request $request, string $shop): RedirectResponse
    {
        $shop = $this->shop($shop);
        $data = $request->validate([
            'question' => ['required', 'string', 'max:255'],
        ]);
        $question = trim((string) $data['question']);
        $savedQuestion = $shop->dataQuestions()->create([
            'user_id' => backpack_user()->id,
            'question' => $question,
            'answer' => $this->answer($shop, $question),
        ]);

        return redirect()->route('workspace.ask-data', ['shop' => $shop->id, 'question_id' => $savedQuestion->id])->with('success', 'Question answered and saved.');
    }

    public function destroy(string $shop, DataQuestion $dataQuestion): RedirectResponse
    {
        $shop = $this->shop($shop);
        abort_unless($dataQuestion->shop_id === $shop->id, 404);
        $dataQuestion->delete();

        return redirect()->route('workspace.ask-data', $shop)->with('success', 'Saved question deleted.');
    }

    private function shop(string $id): Shop
    {
        abort_if(backpack_user()->is_platform_admin, 403);

        $shop = backpack_user()->accessibleShops()->findOrFail($id);
        abort_unless(backpack_user()->manages($shop, true), 403);

        return $shop;
    }

    /** @return array<string, mixed> */
    private function answer(Shop $shop, string $question): array
    {
        $normalized = Str::of($question)->lower()->squish()->toString();
        [$from, $to, $period] = $this->period($normalized);

        if (Str::contains($normalized, ['chart', 'graph', 'compare', 'comparing', 'comparison']) && Str::contains($normalized, ['monthly', 'month'])) {
            return $this->monthlySalesComparison($shop, $question, $normalized, $from, $to, $period);
        }

        if (Str::contains($normalized, ['sales person', 'salesperson', 'cashier', 'seller', 'staff', 'user']) && Str::contains($normalized, ['most', 'top', 'best', 'highest'])) {
            return $this->topSalesperson($shop, $from, $to, $period, $this->resultLimit($normalized));
        }

        if (Str::contains($normalized, ['product', 'item']) && Str::contains($normalized, ['most', 'top', 'best', 'highest'])) {
            return $this->topProduct($shop, $from, $to, $period, $this->resultLimit($normalized));
        }

        if (Str::contains($normalized, ['how many', 'number of', 'count']) && Str::contains($normalized, ['order', 'sale'])) {
            return $this->orderCount($shop, $from, $to, $period);
        }

        if (Str::contains($normalized, ['sales', 'revenue', 'sold', 'total'])) {
            return $this->totalSales($shop, $from, $to, $period);
        }

        return [
            'type' => 'unsupported',
            'title' => 'I could not answer that yet.',
            'summary' => 'Try asking about total sales, order counts, top salespeople, top products, or a monthly bar chart comparing salespeople. You can include a year like 2026 or ask about today.',
            'rows' => [],
        ];
    }

    /** @return array{Carbon, Carbon, string} */
    private function period(string $question): array
    {
        if (Str::contains($question, 'today')) {
            return [now()->startOfDay(), now()->endOfDay(), 'today'];
        }

        if (preg_match('/\b(20\d{2})\b/', $question, $matches) === 1) {
            $year = (int) $matches[1];

            return [Carbon::create($year)->startOfYear(), Carbon::create($year)->endOfYear(), (string) $year];
        }

        return [now()->startOfYear(), now()->endOfYear(), 'this year'];
    }

    private function resultLimit(string $question): int
    {
        if (preg_match('/\b(?:top|best|list|show)?\s*(\d{1,2})\b/', $question, $matches) === 1) {
            return max(1, min((int) $matches[1], 50));
        }

        return 5;
    }

    /** @return array<string, mixed> */
    private function topSalesperson(Shop $shop, Carbon $from, Carbon $to, string $period, int $limit = 5): array
    {
        $rows = $shop->orders()
            ->where('status', 'paid')
            ->whereBetween('paid_at', [$from, $to])
            ->whereNotNull('seller_id')
            ->join('users', 'orders.seller_id', '=', 'users.id')
            ->select('users.name')
            ->selectRaw('COUNT(orders.id) as orders_count')
            ->selectRaw('SUM(orders.total) as sales_total')
            ->groupBy('users.id', 'users.name')
            ->orderByDesc('sales_total')
            ->limit($limit)
            ->get();

        $leader = $rows->first();

        return [
            'type' => 'table',
            'title' => Str::plural('Top salesperson', $limit).' for '.$period,
            'summary' => $leader ? $leader->name.' sold the most with '.$shop->currency.' '.number_format($leader->sales_total / 100, 2).' from '.$leader->orders_count.' paid orders.' : 'No paid sales with a salesperson were found for '.$period.'.',
            'columns' => ['Salesperson', 'Paid orders', 'Sales total'],
            'rows' => $rows->map(fn ($row): array => [
                $row->name,
                number_format((int) $row->orders_count),
                $shop->currency.' '.number_format($row->sales_total / 100, 2),
            ])->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function monthlySalesComparison(Shop $shop, string $question, string $normalized, Carbon $from, Carbon $to, string $period): array
    {
        $salespeople = $this->salespeopleForQuestion($shop, $question, $normalized, $from, $to);
        if ($salespeople->count() < 2) {
            return [
                'type' => 'unsupported',
                'title' => 'Choose at least two salespeople.',
                'summary' => 'I could not find two staff names in that question. Try “Generate a bar chart comparing Ama Seller and Kojo Seller monthly sales for 2026.”',
                'rows' => [],
            ];
        }

        $orders = Order::query()
            ->where('shop_id', $shop->id)
            ->where('status', 'paid')
            ->whereBetween('paid_at', [$from, $to])
            ->whereIn('seller_id', $salespeople->pluck('id'))
            ->select(['id', 'seller_id', 'total', 'paid_at'])
            ->orderBy('paid_at')
            ->get();

        $labels = collect(range(1, 12))->map(fn (int $month): string => Carbon::create(2000, $month)->format('M'))->all();
        $series = $salespeople->map(function (User $user) use ($orders): array {
            $monthly = array_fill(1, 12, 0);
            foreach ($orders->where('seller_id', $user->id) as $order) {
                $monthly[$order->paid_at->month] += $order->total;
            }

            return [
                'label' => $user->name,
                'values' => array_values($monthly),
                'formatted_values' => array_map(fn (int $amount): string => number_format($amount / 100, 2), array_values($monthly)),
            ];
        })->values()->all();
        $max = collect($series)->flatMap(fn (array $item): array => $item['values'])->max() ?: 0;

        return [
            'type' => 'bar_chart',
            'title' => 'Monthly staff sales comparison for '.$period,
            'summary' => 'Comparing monthly paid sales for '.$salespeople->pluck('name')->join(', ', ' and ').'.',
            'chart' => [
                'currency' => $shop->currency,
                'labels' => $labels,
                'series' => $series,
                'max' => $max,
            ],
            'columns' => array_merge(['Month'], $salespeople->pluck('name')->all()),
            'rows' => collect($labels)->map(function (string $label, int $index) use ($series, $shop): array {
                return array_merge([$label], collect($series)->map(fn (array $item): string => $shop->currency.' '.number_format($item['values'][$index] / 100, 2))->all());
            })->all(),
        ];
    }

    /** @return Collection<int, User> */
    private function salespeopleForQuestion(Shop $shop, string $question, string $normalized, Carbon $from, Carbon $to): Collection
    {
        $salespeople = User::query()
            ->whereIn('id', $shop->orders()
                ->where('status', 'paid')
                ->whereBetween('paid_at', [$from, $to])
                ->whereNotNull('seller_id')
                ->select('seller_id'))
            ->orderBy('name')
            ->get(['id', 'name']);

        $mentioned = $salespeople->filter(fn (User $user): bool => Str::contains($normalized, Str::of($user->name)->lower()->squish()->toString()))->values();
        if ($mentioned->count() >= 2) {
            return $mentioned;
        }

        if (preg_match_all('/staff\s*(\d+)/i', $question, $matches) > 0) {
            $selected = collect($matches[1])
                ->map(fn (string $number): ?User => $salespeople->values()->get(((int) $number) - 1))
                ->filter()
                ->unique('id')
                ->values();
            if ($selected->count() >= 2) {
                return $selected;
            }
        }

        if (Str::contains($normalized, ['compare', 'comparing', 'comparison'])) {
            return $salespeople->take(2)->values();
        }

        return $mentioned;
    }

    /** @return array<string, mixed> */
    private function topProduct(Shop $shop, Carbon $from, Carbon $to, string $period, int $limit = 5): array
    {
        $rows = DB::table('order_items')
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->where('orders.shop_id', $shop->id)
            ->where('orders.status', 'paid')
            ->whereBetween('orders.paid_at', [$from, $to])
            ->select('order_items.name')
            ->selectRaw('SUM(order_items.quantity) as quantity_sold')
            ->selectRaw('SUM(order_items.quantity * order_items.unit_price) as sales_total')
            ->groupBy('order_items.product_id', 'order_items.name')
            ->orderByDesc('quantity_sold')
            ->limit($limit)
            ->get();

        $leader = $rows->first();

        return [
            'type' => 'table',
            'title' => Str::plural('Top product', $limit).' for '.$period,
            'summary' => $leader ? $leader->name.' sold the most with '.number_format((int) $leader->quantity_sold).' units sold.' : 'No paid product sales were found for '.$period.'.',
            'columns' => ['Product', 'Quantity sold', 'Sales total'],
            'rows' => $rows->map(fn ($row): array => [
                $row->name,
                number_format((int) $row->quantity_sold),
                $shop->currency.' '.number_format($row->sales_total / 100, 2),
            ])->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function totalSales(Shop $shop, Carbon $from, Carbon $to, string $period): array
    {
        $summary = $shop->orders()
            ->where('status', 'paid')
            ->whereBetween('paid_at', [$from, $to])
            ->selectRaw('COUNT(*) as orders_count')
            ->selectRaw('COALESCE(SUM(total), 0) as sales_total')
            ->first();

        return [
            'type' => 'metric',
            'title' => 'Total sales for '.$period,
            'summary' => 'Sales were '.$shop->currency.' '.number_format(($summary?->sales_total ?? 0) / 100, 2).' from '.number_format((int) ($summary?->orders_count ?? 0)).' paid orders.',
            'rows' => [],
        ];
    }

    /** @return array<string, mixed> */
    private function orderCount(Shop $shop, Carbon $from, Carbon $to, string $period): array
    {
        $orders = $shop->orders()
            ->where('status', 'paid')
            ->whereBetween('paid_at', [$from, $to])
            ->count();

        return [
            'type' => 'metric',
            'title' => 'Paid orders for '.$period,
            'summary' => 'There were '.number_format($orders).' paid orders for '.$period.'.',
            'rows' => [],
        ];
    }
}
