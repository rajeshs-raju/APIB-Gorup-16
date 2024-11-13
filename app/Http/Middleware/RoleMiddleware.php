<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Auth;
use App\Models\Role;

class RoleMiddleware
{
    public function handle($request, Closure $next, $role)
    {
        //dd(Auth::user()->role_id);
        //dd($role);
        // Check if the user is authenticated
        if (!Auth::check()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        // Fetch the role name using the role_id from the roles table
        $roleName = Role::find(Auth::user()->role_id)->name;
        //dd($roleName, $role);

        // Match the role name with the provided $role
        if ($roleName != $role) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        return $next($request);
    }
}
