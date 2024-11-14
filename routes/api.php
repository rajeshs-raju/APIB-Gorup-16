<?php

use Illuminate\Http\Request;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\RestaurantOwnerController;
use App\Http\Controllers\DeliveryController;
use App\Http\Controllers\AdminController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

// Authentication Routes
Route::post('register', [AuthController::class, 'register']);
Route::post('login', [AuthController::class, 'login']);

// Customer Routes
Route::middleware(['auth:api', 'role:Customer'])->group(function () {
    Route::get('restaurants', [CustomerController::class, 'browseRestaurants']);
    Route::post('order', [CustomerController::class, 'placeOrder']);
    Route::get('order/{id}/track', [CustomerController::class, 'trackOrder']);
    Route::get('orders/history', [CustomerController::class, 'viewOrderHistory']);
});

// Restaurant Owner Routes
Route::middleware(['auth:api', 'role:Restaurant Owner'])->group(function () {
    Route::post('restaurant/menu', [RestaurantOwnerController::class, 'manageMenus']);
    Route::get('restaurant/orders', [RestaurantOwnerController::class, 'viewOrders']);
});

// Delivery Personnel Routes
Route::middleware(['auth:api', 'role:Delivery Personnel'])->group(function () {
    Route::get('deliveries', [DeliveryController::class, 'viewAvailableDeliveries']);
    Route::patch('delivery/{id}/status', [DeliveryController::class, 'updateDeliveryStatus']);
});

// Administrator Routes
Route::middleware(['auth:api', 'role:Admin'])->group(function () {
    Route::get('admin/users', [AdminController::class, 'manageUsers']);
    Route::get('admin/reports', [AdminController::class, 'generateReports']);
});
