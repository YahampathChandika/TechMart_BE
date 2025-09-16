<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class PrivilegeMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @param  string  $privilege
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next, $privilege)
    {
        $user = auth('api')->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 401);
        }

        // Admins have all privileges
        if ($user->isAdmin()) {
            return $next($request);
        }

        // Check if user has required privilege
        $hasPrivilege = false;
        
        switch ($privilege) {
            case 'can_add_products':
                $hasPrivilege = $user->canAddProducts();
                break;
            case 'can_update_products':
                $hasPrivilege = $user->canUpdateProducts();
                break;
            case 'can_delete_products':
                $hasPrivilege = $user->canDeleteProducts();
                break;
            default:
                $hasPrivilege = false;
        }

        if (!$hasPrivilege) {
            return response()->json([
                'success' => false,
                'message' => 'Access denied. Required privilege: ' . $privilege
            ], 403);
        }

        return $next($request);
    }
}