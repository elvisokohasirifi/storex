<?php

namespace App\Http\Controllers\Admin;

use App\Models\Brand;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductVariant;
use App\Models\Shop;
use App\Models\ShopMember;
use App\Models\User;
use Backpack\ActivityLog\Enums\ActivityLogEnum;
use Backpack\ActivityLog\Models\ActivityLog;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class ActivityLogCrudController extends \Backpack\ActivityLog\Http\Controllers\ActivityLogCrudController
{
    protected function setupListOperation()
    {
        $this->scopeReadableActivityLogs();

        parent::setupListOperation();
    }

    protected function setupShowOperation()
    {
        parent::setupShowOperation();
    }

    public function getCauserOptions(Request $request): array
    {
        return $this->getScopedMorphOptions($request, ActivityLogEnum::CAUSER);
    }

    public function getSubjectOptions(Request $request): array
    {
        return $this->getScopedMorphOptions($request, ActivityLogEnum::SUBJECT);
    }

    private function scopeReadableActivityLogs(): void
    {
        CRUD::addBaseClause(fn (Builder $query) => $this->applyReadableActivityLogScope($query));
    }

    private function applyReadableActivityLogScope(Builder $query): Builder
    {
        $user = backpack_user();

        if (! $user) {
            return $query->whereRaw('1 = 0');
        }

        if ($user->is_platform_admin) {
            return $query;
        }

        $shopIds = $this->administratedShopIds($user);

        if ($shopIds->isEmpty()) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(fn (Builder $query) => $this->whereActivityRelatesToShops($query, $shopIds));
    }

    /** @return Collection<int, string> */
    private function administratedShopIds(User $user): Collection
    {
        return $user->accessibleShops()
            ->where(fn (Builder $query) => $query
                ->where('owner_id', $user->id)
                ->orWhereHas('members', fn (Builder $query) => $query->where('user_id', $user->id)->where('role', 'admin')))
            ->pluck('shops.id');
    }

    /** @param Collection<int, string> $shopIds */
    private function whereActivityRelatesToShops(Builder $query, Collection $shopIds): void
    {
        $query
            ->where(fn (Builder $query) => $query
                ->where('subject_type', Shop::class)
                ->whereIn('subject_id', $shopIds))
            ->orWhere(fn (Builder $query) => $query
                ->where('subject_type', Product::class)
                ->whereIn('subject_id', Product::query()->select('id')->whereIn('shop_id', $shopIds)))
            ->orWhere(fn (Builder $query) => $query
                ->where('subject_type', ProductCategory::class)
                ->whereIn('subject_id', ProductCategory::query()->select('id')->whereIn('shop_id', $shopIds)))
            ->orWhere(fn (Builder $query) => $query
                ->where('subject_type', Brand::class)
                ->whereIn('subject_id', Brand::query()->select('id')->whereIn('shop_id', $shopIds)))
            ->orWhere(fn (Builder $query) => $query
                ->where('subject_type', ProductVariant::class)
                ->whereIn('subject_id', ProductVariant::query()
                    ->select('product_variants.id')
                    ->whereIn('product_id', Product::query()->select('id')->whereIn('shop_id', $shopIds))))
            ->orWhere(fn (Builder $query) => $query
                ->where('subject_type', ShopMember::class)
                ->whereIn('subject_id', ShopMember::query()->select('id')->whereIn('shop_id', $shopIds)));
    }

    private function getScopedMorphOptions(Request $request, ActivityLogEnum $morphField): array
    {
        $term = $request->string('term')->toString();
        $morphFieldName = Str::lower($morphField->name).'_type';
        $morphFieldKey = Str::lower($morphField->name).'_id';
        $activityQuery = $this->applyReadableActivityLogScope(ActivityLog::query());

        return (clone $activityQuery)
            ->select($morphFieldName)
            ->whereNotNull($morphFieldName)
            ->distinct()
            ->pluck($morphFieldName)
            ->map(function (string $type) use ($activityQuery, $morphFieldKey, $term) {
                $typeClass = Relation::getMorphedModel($type) ?? $type;

                if (! class_exists($typeClass) || ! is_subclass_of($typeClass, Model::class)) {
                    return [];
                }

                $model = new $typeClass;

                if (! method_exists($model, 'identifiableAttribute')) {
                    return [];
                }

                return $model
                    ->newQuery()
                    ->whereIn($model->getKeyName(), (clone $activityQuery)
                        ->select($morphFieldKey)
                        ->where($morphFieldName, $type)
                        ->whereNotNull($morphFieldKey))
                    ->where($model->identifiableAttribute(), 'like', "%{$term}%")
                    ->limit(5)
                    ->pluck($model->identifiableAttribute(), $model->getKeyName())
                    ->mapWithKeys(fn ($value, $id) => ["$type,$id" => Str::limit((string) $value, 28)]);
            })
            ->flatMap(fn ($entry) => $entry)
            ->filter()
            ->toArray();
    }
}
