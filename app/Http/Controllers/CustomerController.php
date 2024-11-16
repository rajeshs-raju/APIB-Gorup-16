<?php

namespace App\Http\Controllers;

use App\Models\Restaurant;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Http\Request;
use Validator;
use Illuminate\Support\Facades\Auth;

class CustomerController extends Controller
{
    /*
    This method lists the available restaurants to customer
    */
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


    public function placeOrder(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'restaurant_id' => 'required|exists:restaurants,id',
            'items' => 'required|array',
            'items.*.menu_id' => 'required|exists:menus,id',
            'items.*.quantity' => 'required|integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 422);
        }

        try {
            $order = Order::create([
                'user_id' => Auth::id(),
                'restaurant_id' => $request->restaurant_id,
                'total' => $this->calculateTotal($request->items),
            ]);

            foreach ($request->items as $item) {
                OrderItem::create([
                    'order_id' => $order->id,
                    'menu_id' => $item['menu_id'],
                    'quantity' => $item['quantity'],
                    'price' => $item['price'],
                ]);
            }

            return response()->json(['message' => 'Order placed successfully', 'order' => $order], 201);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to place order. Please try again later.'], 500);
        }
    }

    public function trackOrder($id)
    {
        $order = Order::find($id);

        if (!$order) {
            return response()->json(['error' => 'Order not found'], 404);
        }

        return response()->json(['data' => $order], 200);
    }

    public function viewOrderHistory()
    {
        $orders = Order::where('user_id', Auth::id())->get();

        return response()->json(['data' => $orders], 200);
    }

    private function calculateTotal($items)
    {
        $total = 0;
        foreach ($items as $item) {
            $total += $item['quantity'] * $item['price'];
        }

        return $total;
    }
}
