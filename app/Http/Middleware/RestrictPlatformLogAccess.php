<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RestrictPlatformLogAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $prefix = trim((string) config('backpack.base.route_prefix', 'admin'), '/');

        if ($request->is($prefix.'/log*')) {
            abort_unless(backpack_user()?->is_platform_admin, 403);
        }

        if ($request->is($prefix.'/activity-log*')) {
            abort_unless($this->canViewActivityLogs(), 403);
        }

        return $next($request);
    }

    private function canViewActivityLogs(): bool
    {
        $user = backpack_user();

        if (! $user) {
            return false;
        }

        if ($user->is_platform_admin) {
            return true;
        }

        return $user->accessibleShops()
            ->where(fn ($query) => $query
                ->where('owner_id', $user->id)
                ->orWhereHas('members', fn ($query) => $query->where('user_id', $user->id)->where('role', 'admin')))
            ->exists();
    }
}
