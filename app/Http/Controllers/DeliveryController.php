<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;

class DeliveryController extends Controller
{
    public function viewAvailableDeliveries() {
        return Order::where('status', 'ready_for_delivery')->get();
    }

    public function updateDeliveryStatus(Request $request, $id) {
        $order = Order::find($id);
        $order->status = $request->status;
        $order->save();

        return response()->json(['message' => 'Status updated']);
    }
}
