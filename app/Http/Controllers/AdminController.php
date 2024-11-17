<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Order;
use App\Models\Delivery;
use App\Models\Restaurant;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Validator;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;
use App\Models\Role;
use Illuminate\Support\Facades\DB;

class AdminController extends Controller
{
    public function manageUsers(Request $request)
    {
        try {
            $users = User::with(['role'])->get();

            return response()->json(['message' => 'Users fetched successfully', 'data' => $users], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to fetch users'], 500);
        }
    }

    public function createUser(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:6|confirmed',
            //'role_id' => 'required|exists:roles,id',
            'role' => 'required|string|in:Customer,Restaurant Owner,Delivery Personnel',
        ], [
            // Custom error messages
            'role.in' => 'You can only choose a role from: Customer, Restaurant Owner, Delivery Personnel.',
            'role.required' => 'The role is required.',
            'role.string' => 'The role must be a string.',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 422);
        }

        try {
            $role = \App\Models\Role::where('name', $request->role)->first(); // Get the role record by name

            if (!$role) {
                return response()->json(['error' => 'Invalid role specified.'], 400); // If role is not found
            }

            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'role_id' => $role->id,
            ]);

            return response()->json(['message' => 'User created successfully', 'data' => $user], 201);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to create user'], 500);
        }
    }

    public function updateUser(Request $request, $userId)
    {
        try {
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'email' => 'required|email|unique:users,email,' . $userId,
                'password' => 'nullable|string|min:8',
            ]);

            // If validation fails, return validation errors
            if ($validator->fails()) {
                return response()->json(['error' => $validator->errors()], 422);
            }

            // Find the user by ID
            $user = User::find($userId);

            if (!$user) {
                return response()->json(['error' => 'User not found.'], 404);
            }

            // Update the user's name and email
            $user->name = $request->input('name');
            $user->email = $request->input('email');

            // If a password is provided, hash and update it
            if ($request->has('password')) {
                $user->password = Hash::make($request->input('password'));
            }

            // Save the updated user
            $user->save();

            return response()->json([
                'message' => 'User updated successfully.',
                'data' => $user
            ], 200);

        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to update user. Please try again later.'], 500);
        }
    }

    public function deleteUser($userId)
    {
        try {
            if ($userId == 4) {
                return response()->json(['error' => 'You cannot delete this user.'], 403);
            }
    
            $user = User::find($userId);
    
            if (!$user) {
                return response()->json(['error' => 'User not found.'], 404);
            }
    
            $user->delete();
    
            return response()->json([
                'message' => 'User deleted successfully.'
            ], 200);
    
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to delete user. Please try again later.'], 500);
        }
    }    

    public function viewOrders()
    {
        try {
            $orders = Order::all();

            if ($orders->isEmpty()) {
                return response()->json(['message' => 'No orders found.'], 200);
            }

            return response()->json([
                'message' => 'Orders fetched successfully.',
                'data' => $orders
            ], 200);

        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to fetch orders. Please try again later.'], 500);
        }
    }

    public function updateOrderStatus(Request $request, $orderId)
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required|string|in:pending,accepted,preparing,ready for delivery,delivering,delivered',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => 'Invalid status. Please select from the allowed statuses: pending, accepted, preparing, ready for delivery, delivering, delivered.'], 422);
        }

        try {
            $order = Order::find($orderId);

            if (!$order) {
                return response()->json(['error' => 'Order not found'], 404);
            }

            if ($order->status == 'delivered') {
                return response()->json(['error' => 'Order status cannot be changed after it is marked as delivered.'], 400);
            }

            $order->status = $request->status;
            $order->save();

            return response()->json(['message' => 'Order status updated successfully', 'data' => $order], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to update order status'], 500);
        }
    }

    public function generateReports(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'report_type' => 'required|string|in:most_popular_restaurants,average_delivery_time,order_trends',
            ]);
    
            if ($validator->fails()) {
                return response()->json(['error' => 'Invalid report type. Select from the available options: most_popular_restaurants, average_delivery_time, order_trends'], 422);
            }
    
            $reportType = $request->get('report_type');
    
            $report = null;
    
            switch ($reportType) {
                case 'most_popular_restaurants':
                    $report = DB::table('orders')
                        ->select('restaurant_id', DB::raw('COUNT(*) as order_count'))
                        ->groupBy('restaurant_id')
                        ->orderByDesc('order_count')
                        ->take(10)
                        ->get();
                    break;
    
                case 'average_delivery_time':
                    $report = DB::table('deliveries')
                        ->join('orders', 'deliveries.order_id', '=', 'orders.id')
                        ->select(DB::raw('AVG(TIMESTAMPDIFF(MINUTE, orders.created_at, deliveries.updated_at)) as avg_delivery_time'))
                        ->where('orders.status', 'delivered')
                        ->first();
                    break;
    
                case 'order_trends':
                    $report = DB::table('orders')
                        ->select(DB::raw('MONTH(created_at) as month'), DB::raw('YEAR(created_at) as year'), DB::raw('COUNT(*) as order_count'), DB::raw('SUM(total) as total_revenue'))
                        ->groupBy(DB::raw('YEAR(created_at), MONTH(created_at)'))
                        ->orderByDesc(DB::raw('YEAR(created_at), MONTH(created_at)'))
                        ->get();
                    break;
    
                default:
                    return response()->json(['error' => 'Invalid report type.'], 400);
            }
    
            return response()->json([
                'message' => 'Report generated successfully.',
                'report_type' => $reportType,
                'data' => $report,
            ], 200);
    
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to generate report. Please try again later.'], 500);
        }
    }    
}
