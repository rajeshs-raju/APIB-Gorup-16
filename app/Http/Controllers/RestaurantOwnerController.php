<?php

namespace App\Http\Controllers;

use App\Models\Menu;
use Illuminate\Http\Request;

class RestaurantOwnerController extends Controller
{
    public function manageMenus(Request $request) {
        Menu::updateOrCreate(
            ['id' => $request->id],
            $request->only('restaurant_id', 'name', 'description', 'price', 'availability')
        );

        return response()->json(['message' => 'Menu updated']);
    }

    public function viewOrders() {
        $orders = Auth::user()->restaurant->orders()->with('items')->get();
        return response()->json($orders);
    }
}
