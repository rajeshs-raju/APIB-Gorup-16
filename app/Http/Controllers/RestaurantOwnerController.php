<?php

namespace App\Http\Controllers;

use App\Models\Menu;
use App\Models\Order;
use Illuminate\Http\Request;
use Validator;
use Illuminate\Support\Facades\Auth;
use App\Models\Restaurant;

class RestaurantOwnerController extends Controller
{
    public function createRestaurant(Request $request)
    {
        $existingRestaurant = Restaurant::where('owner_id', Auth::id())->first();

        if ($existingRestaurant) {
            return response()->json([
                'message' => 'Restaurant already exists for this owner.',
                'owner' => [
                    'name' => Auth::user()->name,
                    'email' => Auth::user()->email,
                ],
                'restaurant_details' => $existingRestaurant,
            ], 400);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'address' => 'required|string',
            'hours_of_operation' => 'required|string|max:100',
            'delivery_zones' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $restaurant = new Restaurant();
        $restaurant->owner_id = Auth::id();
        $restaurant->name = $request->name;
        $restaurant->address = $request->address;
        $restaurant->hours_of_operation = $request->hours_of_operation;
        $restaurant->delivery_zones = $request->delivery_zones;
        $restaurant->save();

        return response()->json([
            'message' => 'Restaurant created successfully.',
            'owner' => [
                'name' => Auth::user()->name,
                'email' => Auth::user()->email,
            ],
            'restaurant_details' => $restaurant,
        ], 201);
    }

    public function manageMenus(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'required|string|max:1000',
            'price' => 'required|numeric|min:1',
            'availability' => 'required|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 422);
        }

        try {
            $user = Auth::user();
            
            if ($user->role_id != 2) {
                return response()->json(['error' => 'You are not a restaurant owner.'], 403);
            }

            $restaurant = Restaurant::where('owner_id', $user->id)->first();

            if (!$restaurant) {
                return response()->json(['error' => 'No restaurant exists for this owner.'], 404);
            }

            $menu = Menu::create([
                'restaurant_id' => $restaurant->id,
                'name' => $request->name,
                'description' => $request->description,
                'price' => $request->price,
                'availability' => $request->availability,
            ]);

            return response()->json([
                'message' => 'Menu item added successfully.',
                'menu' => $menu,
                'owner' => [
                    'name' => Auth::user()->name,
                    'email' => Auth::user()->email,
                ],
                'restaurant_details' => $restaurant,
            ], 201);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to add menu item. Please try again later.'], 500);
        }
    }

    public function updateMenuItem(Request $request, $menuId)
    {
        $validator = Validator::make($request->all(), [
            'price' => 'required|numeric|min:1',
            'availability' => 'required|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 422);
        }

        try {
            $user = Auth::user();

            if ($user->role_id != 2) {
                return response()->json(['error' => 'You are not a restaurant owner.'], 403);
            }

            $restaurant = Restaurant::where('owner_id', $user->id)->first();

            if (!$restaurant) {
                return response()->json(['error' => 'No restaurant exists for this owner.'], 404);
            }

            $menu = Menu::where('restaurant_id', $restaurant->id)->find($menuId);

            if (!$menu) {
                return response()->json(['error' => 'Menu item not found.'], 404);
            }

            $menu->price = $request->price;
            $menu->availability = $request->availability;

            $menu->save();

            return response()->json([
                'message' => 'Menu item updated successfully.',
                'updated_menu' => $menu,
                'owner' => [
                    'name' => Auth::user()->name,
                    'email' => Auth::user()->email,
                ],
                'restaurant_details' => $restaurant,
            ], 200);

        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to update menu item. Please try again later.'], 500);
        }
    }

    public function deleteMenu($menuId)
    {
        try {
            $user = Auth::user();

            if ($user->role_id != 2) {
                return response()->json(['error' => 'You are not a restaurant owner.'], 403);
            }

            $restaurant = Restaurant::where('owner_id', $user->id)->first();

            if (!$restaurant) {
                return response()->json(['error' => 'No restaurant exists for this owner.'], 404);
            }

            $menu = Menu::where('restaurant_id', $restaurant->id)->find($menuId);

            if (!$menu) {
                return response()->json(['error' => 'Menu item not found.'], 404);
            }

            $menu->delete();

            return response()->json([
                'message' => 'Menu item deleted successfully.',
                'deleted_menu' => $menu,
                'owner' => [
                    'name' => Auth::user()->name,
                    'email' => Auth::user()->email,
                ],
                'restaurant_details' => $restaurant,
            ], 200);

        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to delete menu item. Please try again later.'], 500);
        }
    }

    public function viewOrders()
    {
        try {
            $user = Auth::user();
    
            if ($user->role_id != 2) {
                return response()->json(['error' => 'You are not a restaurant owner.'], 403);
            }
    
            $restaurant = Restaurant::where('owner_id', $user->id)->first();
    
            if (!$restaurant) {
                return response()->json(['error' => 'No restaurant exists for this owner.'], 404);
            }
    
            $orders = $restaurant->orders()->with('items')->get();
    
            if ($orders->isEmpty()) {
                return response()->json(['message' => 'No orders found for this restaurant.'], 200);
            }
            return response()->json([
                'message' => 'Orders retrieved successfully.',
                'orders' => $orders,
                'owner' => [
                    'name' => Auth::user()->name,
                    'email' => Auth::user()->email,
                ],
                'restaurant_details' => $restaurant,
            ], 200);
    
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to retrieve orders. Please try again later.'], 500);
        }
    }

    public function updateOrderStatus(Request $request, $orderId)
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required|string|in:accepted,preparing,ready for delivery',
        ], [
            'status.in' => 'Only "accepted", "preparing", or "ready for delivery" are valid statuses.'
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 422);
        }

        try {
            $user = Auth::user();

            if ($user->role_id != 2) {
                return response()->json(['error' => 'You are not a restaurant owner.'], 403);
            }

            $restaurant = Restaurant::where('owner_id', $user->id)->first();

            if (!$restaurant) {
                return response()->json(['error' => 'No restaurant exists for this owner.'], 404);
            }

            $order = $restaurant->orders()->find($orderId);

            if (!$order) {
                return response()->json(['error' => 'Order not found for this restaurant.'], 404);
            }

            $currentStatus = $order->status;

            if ($currentStatus == 'pending' && $request->status == 'accepted') {
                $order->status = 'accepted';
                $order->save();
            } elseif ($currentStatus == 'accepted' && $request->status == 'preparing') {
                $order->status = 'preparing';
                $order->save();
            } elseif ($currentStatus == 'preparing' && $request->status == 'ready for delivery') {
                $order->status = 'ready for delivery';
                $order->save();
            } elseif ($currentStatus == 'accepted' && $request->status == 'ready for delivery') {
                $order->status = 'ready for delivery';
                $order->save();
            } elseif ($currentStatus == 'ready for delivery' && $request->status == 'preparing') {
                return response()->json(['error' => 'Invalid status transition. Must follow the sequence: accepted → preparing → ready for delivery.'], 400);
            } elseif ($currentStatus == 'ready for delivery' && $request->status == 'accepted') {
                return response()->json(['error' => 'Invalid status transition. Cannot go back to "accepted" after "ready for delivery", Must follow the sequence: accepted → preparing → ready for delivery.'], 400);
            } elseif ($currentStatus == 'preparing' && $request->status == 'accepted') {
                return response()->json(['error' => 'Invalid status transition. Cannot go back to "accepted" after "preparing", Must follow the sequence: accepted → preparing → ready for delivery.'], 400);
            } elseif ($currentStatus == 'pending' && $request->status == 'preparing') {
                return response()->json(['error' => 'Invalid status transition. Must follow the sequence: accepted → preparing → ready for delivery.'], 400);
            } elseif ($currentStatus == 'pending' && $request->status == 'ready for delivery') {
                return response()->json(['error' => 'Invalid status transition. Must follow the sequence: accepted → preparing → ready for delivery.'], 400);
            } 

            return response()->json([
                'message' => 'Order status updated successfully.',
                'order' => $order
            ], 200);

        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to update order status. Please try again later.'], 500);
        }
    }

    public function updateRestaurantDetails(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'address' => 'required|string',
            'hours_of_operation' => 'nullable|string|max:100',
            'delivery_zones' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 422);
        }

        try {
            $user = Auth::user();

            if ($user->role_id != 2) {
                return response()->json(['error' => 'You are not a restaurant owner.'], 403);
            }

            $restaurant = Restaurant::where('owner_id', $user->id)->first();

            if (!$restaurant) {
                return response()->json(['error' => 'No restaurant exists for this owner.'], 404);
            }

            $restaurant->name = $request->name;
            $restaurant->address = $request->address;
            $restaurant->hours_of_operation = $request->hours_of_operation;
            $restaurant->delivery_zones = $request->delivery_zones;
            $restaurant->save();

            return response()->json([
                'message' => 'Restaurant details updated successfully.',
                'owner' => [
                    'name' => Auth::user()->name,
                    'email' => Auth::user()->email,
                ],
                'updated_restaurant_details' => $restaurant,
            ], 200);

        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to update restaurant details. Please try again later.'], 500);
        }
    }
}
