<?php

namespace App\Http\Controllers;

use App\Models\Restaurant;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Menu;
use Illuminate\Http\Request;
use Validator;
use Illuminate\Support\Facades\Auth;

class CustomerController extends Controller
{
    public function browseRestaurants()
    {
        try {
            $restaurants = Restaurant::all();
            if ($restaurants->isEmpty()) {
                return response()->json([
                    'message' => 'No restaurants found.',
                    'data' => []
                ], 404);
            }
            return response()->json([
                'message' => 'Restaurants retrieved successfully.',
                'data' => $restaurants
            ], 200);

        } catch (\Throwable $e) {
            Log::error('Error fetching restaurants: ' . $e->getMessage());
            return response()->json([
                'error' => 'An unexpected error occurred while fetching restaurants.'
            ], 500);
        }
    }

    public function searchMenus(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'query' => 'nullable|string|max:255',
            'restaurant_id' => 'nullable|integer|exists:restaurants,id',
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'error' => $validator->errors()
            ], 422);
        }
        
        $query = $request->input('query');
        $restaurant_id = $request->input('restaurant_id');
        
        $menus = Menu::where('menus.availability', 1)
            ->join('restaurants', 'menus.restaurant_id', '=', 'restaurants.id')
            ->select('menus.*', 'restaurants.name as restaurant_name')
            ->when($query, function ($q) use ($query) {
                $q->where(function ($queryBuilder) use ($query) {
                    $queryBuilder->where('menus.name', 'like', "%$query%")
                        ->orWhere('menus.description', 'like', "%$query%");
                });
            })
            ->when($restaurant_id, function ($q) use ($restaurant_id) {
                $q->where('menus.restaurant_id', $restaurant_id);
            })
            ->get();
        
        return response()->json([
            'message' => 'Menus retrieved successfully',
            'data' => $menus,
        ], 200);
    }    

    public function placeOrder(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'restaurant_id' => 'required|integer|exists:restaurants,id',
            'items' => 'required|array|min:1',
            'items.*.menu_id' => 'required|integer|exists:menus,id',
            'items.*.quantity' => 'required|integer|min:1',
            'shipping_address' => 'required|string|max:255',
            'payment_method' => 'required|string|in:credit_card,cash_on_delivery',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 422);
        }

        try {
            $user = Auth::user();

            $total = 0;
            $orderItems = [];

            foreach ($request->items as $item) {
                $menu = Menu::findOrFail($item['menu_id']);
                
                $itemTotal = $item['quantity'] * $menu->price;
                
                $orderItems[] = [
                    'menu_id' => $item['menu_id'],
                    'quantity' => $item['quantity'],
                    'price' => $itemTotal,
                ];

                $total += $itemTotal;
            }

            $order = Order::create([
                'user_id' => $user->id,
                'restaurant_id' => $request->restaurant_id,
                'status' => 'accepted',
                'total' => $total,
                'shipping_address' => $request->shipping_address,
                'payment_method' => $request->payment_method,
            ]);

            foreach ($orderItems as $orderItem) {
                OrderItem::create([
                    'order_id' => $order->id,
                    'menu_id' => $orderItem['menu_id'],
                    'quantity' => $orderItem['quantity'],
                    'price' => $orderItem['price'],
                ]);
            }

            $orderWithRestaurantName = Order::join('restaurants', 'orders.restaurant_id', '=', 'restaurants.id')
                                        ->select('orders.*', 'restaurants.name as restaurant_name')
                                        ->where('orders.id', $order->id)
                                        ->first();

            return response()->json([
                'message' => 'Order placed successfully',
                'user_details' => $user,
                'order' => $orderWithRestaurantName,
                'order_items' => $orderItems
            ], 201);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to place order. Please try again later.'], 500);
        }
    }

    public function trackOrder(Request $request)
    {
        $user = Auth::user();
    
        $validator = Validator::make($request->all(), [
            'order_id' => 'nullable|exists:orders,id,user_id,' . $user->id,
        ]);
    
        if ($validator->fails()) {
            return response()->json([
                'error' => $validator->errors()
            ], 422);
        }

        $order_id = $request->input('order_id');
    
        if ($order_id) {
            $order = Order::where('id', $order_id)
                ->where('user_id', $user->id)
                ->first();
    
            if (!$order) {
                return response()->json([
                    'error' => 'Order not found'
                ], 404);
            }
    
            $order->restaurant_name = Restaurant::find($order->restaurant_id)->name;
    
            return response()->json([
                'message' => 'Order tracking information retrieved successfully',
                'order' => $order,
                'tracking_info' => 'Your order is on the way! Estimated delivery time: 30 minutes.'
            ], 200);
        } else {
            $orders = Order::where('user_id', $user->id)->get();
    
            $orders = $orders->map(function ($order) {
                $order->restaurant_name = Restaurant::find($order->restaurant_id)->name;
                return $order;
            });
    
            return response()->json([
                'message' => 'All orders retrieved successfully',
                'orders' => $orders,
            ], 200);
        }
    }     

    // public function viewOrderHistory()
    // {
    //     $orders = Order::where('user_id', Auth::id())->get();

    //     return response()->json(['data' => $orders], 200);
    // }
}
