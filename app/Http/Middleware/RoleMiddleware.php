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

        $user = Auth::user();
        $roleRecord = Role::find($user->role_id);

        // Check if the role exists in the roles table
        if (!$roleRecord) {
            return response()->json(['error' => 'Role not found'], 403);
        }

        $roleName = $roleRecord->name;

        // Match the role name with the provided $role
        if ($roleName != $role) {
            return response()->json([
                'error' => 'Unauthorized',
                'message' => "With your current Login Credentials You can only access $role URLs. Your current role is $roleName."
            ], 403);    
        }

        return $next($request);
    }
}
