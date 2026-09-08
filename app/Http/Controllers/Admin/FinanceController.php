<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LedgerEntry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FinanceController extends Controller
{
    public function index(Request $request): View|StreamedResponse
    {
        abort_if(backpack_user()->is_platform_admin, 403);
        $data = $request->validate(['shop_id' => ['nullable', 'uuid'], 'from' => ['nullable', 'date_format:Y-m-d'], 'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from']]);
        $shops = backpack_user()->accessibleShops()->orderBy('name')->get();
        $shop = isset($data['shop_id']) ? $shops->firstWhere('id', $data['shop_id']) : $shops->first();
        abort_if(isset($data['shop_id']) && ! $shop, 404);
        $from = $data['from'] ?? now()->startOfYear()->toDateString();
        $to = $data['to'] ?? now()->toDateString();
        $entries = collect();
        $sales = collect();
        $summary = collect();
        if ($shop) {
            $entryQuery = LedgerEntry::where('shop_id', $shop->id)->whereDate('occurred_on', '>=', $from)->whereDate('occurred_on', '<=', $to);
            $saleQuery = $shop->orders()->where('status', 'paid')->whereBetween('paid_at', [$from.' 00:00:00', $to.' 23:59:59']);
            if ($request->boolean('export')) {
                return response()->streamDownload(function () use ($entryQuery, $saleQuery) {
                    $stream = fopen('php://output', 'w');
                    if ($stream === false) {
                        throw new \RuntimeException('Could not open CSV output.');
                    }
                    fputcsv($stream, ['Date', 'Type', 'Category', 'Description / reference', 'Currency', 'Amount', 'Receipt'], escape: '');
                    foreach ($saleQuery->orderBy('paid_at')->cursor() as $sale) {
                        fputcsv($stream, [$sale->paid_at->toDateString(), 'sale', 'Sales', $sale->reference, $sale->currency, number_format($sale->total / 100, 2, '.', ''), ''], escape: '');
                    }
                    foreach ($entryQuery->orderBy('occurred_on')->cursor() as $entry) {
                        $safe = fn (string $value) => preg_match('/^[=+@\\-\\t\\r]/', $value) ? "'".$value : $value;
                        fputcsv($stream, [$entry->occurred_on->toDateString(), $entry->type, $safe($entry->category), $safe($entry->description), $entry->currency, $entry->amount, $entry->receipt ? route('finance.receipt', $entry) : ''], escape: '');
                    }
                    fclose($stream);
                }, 'storex-finance-'.$from.'-'.$to.'.csv', ['Content-Type' => 'text/csv']);
            }
            $sales = $saleQuery->selectRaw('currency, SUM(total) as amount')->groupBy('currency')->get()->keyBy('currency');
            $totals = (clone $entryQuery)->selectRaw('currency, type, SUM(amount) as amount')->groupBy('currency', 'type')->get();
            $currencies = $totals->pluck('currency')->merge($sales->keys())->unique();
            $summary = $currencies->map(function ($currency) use ($totals, $sales) {
                $income = (float) $totals->where('currency', $currency)->where('type', 'income')->sum('amount');
                $expenses = (float) $totals->where('currency', $currency)->where('type', 'expense')->sum('amount');

                return ['currency' => $currency, 'sales' => ($sales->get($currency)->amount ?? 0) / 100, 'income' => $income, 'expenses' => $expenses];
            });
            $entries = $entryQuery->latest('occurred_on')->paginate(30)->withQueryString();
        }

        return view('admin.finance', compact('shops', 'shop', 'from', 'to', 'entries', 'summary'));
    }

    public function receipt(LedgerEntry $entry): StreamedResponse
    {
        abort_if(backpack_user()->is_platform_admin, 403);
        abort_unless(backpack_user()->accessibleShops()->whereKey($entry->shop_id)->exists(), 404);
        $path = Str::start($entry->receipt ?? '', 'receipts/');
        abort_unless($entry->receipt && Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->download($path);
    }
}
