<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Delivery;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Support\Facades\Auth;
use Validator;

class DeliveryController extends Controller
{
    public function viewAvailableDeliveries(Request $request)
    {
        try {
            $user = Auth::user();

            if ($user->role_id !== 3) {
                return response()->json(['error' => 'You are not a delivery personnel.'], 403);
            }

            $orders = Order::where('status', 'ready for delivery')
                ->whereNotIn('id', function ($query) {
                    $query->select('order_id')->from('deliveries');
                })
                ->with([
                    'customer:id,name,email',
                    'restaurant:id,name,address'
                ])
                ->get();

            if ($orders->isEmpty()) {
                return response()->json(['message' => 'No available deliveries at the moment.'], 200);
            }

            return response()->json([
                'message' => 'Available deliveries fetched successfully.',
                'data' => $orders
            ], 200);

        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to fetch available deliveries. Please try again later.'], 500);
        }
    }
    public function acceptOrder(Request $request, $order_id)
    {
        try {
            $user = Auth::user();

            if ($user->role_id !== 3) {
                return response()->json(['error' => 'You are not a delivery personnel.'], 403);
            }

            $order = Order::find($order_id);

            if (!$order) {
                return response()->json(['error' => 'Order not found.'], 404);
            }

            if ($order->status === 'delivering') {
                return response()->json(['error' => 'This order is already out for delivery.'], 400);
            }

            if ($order->status === 'delivered') {
                return response()->json(['error' => 'This order is already got delivered.'], 400);
            }

            if ($order->status !== 'ready for delivery') {
                return response()->json(['error' => 'This order is not ready for delivery.'], 400);
            }

            $delivery = Delivery::create([
                'order_id' => $order->id,
                'delivery_personnel_id' => $user->id,
                'status' => 'pending',
            ]);

            $order->status = 'delivering';
            $order->save();

            // $deliveryPersonnel = User::join('deliveries', 'users.id', '=', 'deliveries.delivery_personnel_id')
            // ->where('deliveries.id', $delivery->id)
            // ->select('users.name', 'users.email')
            // ->first();

            return response()->json([
                'message' => 'Order accepted and delivery created successfully.',
                'data' => $delivery,
                'delivery_personnel' => [
                    'name' => Auth::user()->name,
                    'email' => Auth::user()->email,
                ],
            ], 200);

        } catch (\Exception $e) {
            dd($e);
            return response()->json(['error' => 'Failed to accept order. Please try again later.'], 500);
        }
    }

    public function updateDeliveryStatus(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'delivery_id' => 'required|integer|exists:deliveries,id',
                'status' => 'required|string|in:picked up,en route,delivered',
            ], [
                'status.in' => 'Invalid status. Please select from: picked up, en route, delivered only.',
                'delivery_id.exists' => 'The provided delivery ID is not valid or does not exist.'
            ]);

            if ($validator->fails()) {
                return response()->json(['error' => $validator->errors()], 422);
            }

            $user = Auth::user();

            if ($user->role_id !== 3) {
                return response()->json(['error' => 'You are not a delivery personnel.'], 403);
            }

            $delivery_id = $request->input('delivery_id');

            $delivery = Delivery::find($delivery_id);

            if (!$delivery) {
                return response()->json(['error' => 'Delivery not found.'], 404);
            }

            if ($delivery->delivery_personnel_id !== $user->id) {
                return response()->json(['error' => 'This delivery is not assigned to you.'], 403);
            }

            if ($delivery->status === 'delivered') {
                return response()->json(['error' => 'This delivery has already been completed and cannot be updated.'], 400);
            }

            $validStatuses = ['picked up', 'en route', 'delivered'];
            $currentStatusIndex = array_search($delivery->status, $validStatuses);
            $newStatusIndex = array_search($request->status, $validStatuses);

            if ($newStatusIndex === false || $newStatusIndex !== $currentStatusIndex + 1) {
                return response()->json(['error' => 'Invalid status transition. Status must progress in the following order: picked up -> en route -> delivered.'], 400);
            }

            $delivery->status = $request->status;
            $delivery->save();

            if ($request->status === 'delivered') {
                $order = $delivery->order;
                $order->status = 'delivered';
                $order->save();
            }

            return response()->json([
                'message' => 'Delivery status updated successfully.',
                'data' => $delivery
            ], 200);

        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to update delivery status. Please try again later.'], 500);
        }
    }

    // Set Delivery Personnel Availability
    public function setAvailability(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'available' => 'required|boolean',  // True for available, False for unavailable
            ]);

            if ($validator->fails()) {
                return response()->json(['error' => $validator->errors()], 422);
            }

            $user = Auth::user();

            // Ensure the user is a delivery personnel (role_id = 3)
            if ($user->role_id !== 3) {
                return response()->json(['error' => 'You are not a delivery personnel.'], 403);
            }

            // Update the delivery personnel availability
            $user->delivery_personnel->available = $request->available;
            $user->delivery_personnel->save();

            return response()->json([
                'message' => 'Delivery personnel availability updated successfully.',
                'data' => $user->delivery_personnel
            ], 200);

        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to update availability. Please try again later.'], 500);
        }
    }
}
