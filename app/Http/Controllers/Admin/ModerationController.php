<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ModerationLog;
use App\Models\Product;
use App\Models\Shop;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ModerationController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless(backpack_user()->is_platform_admin, 403);
        $type = $request->query('type') === 'products' ? 'products' : 'shops';
        $status = in_array($request->query('status'), ['approved', 'rejected', 'frozen'], true) ? $request->query('status') : 'pending';
        $records = ($type === 'products' ? Product::with('shop') : Shop::query())->where('status', $status)->latest()->paginate(30)->withQueryString();
        $logs = ModerationLog::latest()->limit(15)->get();

        return view('admin.moderation', compact('type', 'status', 'records', 'logs'));
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless(backpack_user()->is_platform_admin, 403);
        $data = $request->validate([
            'type' => ['required', Rule::in(['shops', 'products'])],
            'ids' => ['required', 'array', 'min:1', 'max:100'], 'ids.*' => ['required', 'uuid', 'distinct'],
            'status' => ['required', Rule::in(['approved', 'rejected', 'frozen'])],
            'reason' => ['nullable', 'required_unless:status,approved', 'string', 'max:2000'],
        ]);
        DB::transaction(function () use ($data) {
            $class = $data['type'] === 'shops' ? Shop::class : Product::class;
            $records = $class::whereIn('id', $data['ids'])->orderBy('id')->lockForUpdate()->get();
            abort_unless($records->count() === count($data['ids']), 422);
            foreach ($records as $record) {
                $record->forceFill(['status' => $data['status']])->save();
                ModerationLog::create(['actor_id' => backpack_user()->id, 'subject_type' => $data['type'], 'subject_id' => $record->id, 'status' => $data['status'], 'reason' => $data['reason'] ?? null]);
            }
        });

        return back()->with('success', count($data['ids']).' records '.$data['status'].'.');
    }
}
