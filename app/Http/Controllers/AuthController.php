<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use App\Models\Role;

class AuthController extends Controller
{
    public function register(Request $request)
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
            // Get the role from the 'roles' table based on the role name
            $role = \App\Models\Role::where('name', $request->role)->first(); // Get the role record by name

            if (!$role) {
                return response()->json(['error' => 'Invalid role specified.'], 400); // If role is not found
            }

            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'role_id' => $role->id
            ]);
            
            $token = $user->createToken('FoodDeliveryApp')->accessToken;

            return response()->json([
                'message' => 'User registered successfully',
                'data' => $user,
                'token' => $token
            ], 201);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Registration failed. Please try again later.'], 500);
        }
    }

    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|string|email|max:255',
            'password' => 'required|string|min:6',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 422);
        }

        if (Auth::attempt(['email' => $request->email, 'password' => $request->password])) {
            $user = Auth::user();
            // Fetch the role name using the role_id from the roles table
            $role = \App\Models\Role::find($user->role_id);

            // If role exists, get the role name; otherwise, set as "Unknown"
            $roleName = $role ? $role->name : 'Unknown';
            $token = $user->createToken('FoodDeliveryApp')->accessToken;

            return response()->json([
                'message' => 'Login successful',
                'data' => $user,
                'role' => $roleName,
                'token' => $token
            ], 200);
        }

        // Check if the email exists in the system, and provide specific error messages
        $user = \App\Models\User::where('email', $request->email)->first();

        if (!$user) {
            // If the email doesn't exist
            return response()->json(['error' => 'Email not found'], 401);
        }

        // If the email exists but password is wrong
        return response()->json(['error' => 'Incorrect password'], 401);
    }
}