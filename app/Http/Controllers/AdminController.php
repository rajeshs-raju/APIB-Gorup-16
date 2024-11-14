<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    public function manageUsers() {
        return User::all();
    }

    public function generateReports() {
        // Report logic placeholder
        return response()->json(['report' => 'Data']);
    }
}
