<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index()
    {
        if (!session('user_id')) {
            return redirect()->route('login')->with('error', 'Session expired. Please login again.');
        }

        return view('home.dashboard', [
            'supabaseUrl'     => config('services.supabase.url'),
            'supabaseAnonKey' => config('services.supabase.anon_key'),
            'supabaseSvcKey'  => config('services.supabase.service_key'),
            'userId'          => session('user_id'),
        ]);
    }
}
