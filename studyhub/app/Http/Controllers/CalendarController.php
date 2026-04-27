<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CalendarController extends Controller
{
    /**
     * Show the calendar page.
     * All CRUD is handled by the frontend directly via Supabase REST.
     * This controller just serves the view with the necessary config.
     */
    public function index()
    {
        // ✅ Add this debug to see what's in session
        if (!session('user_id')) {
            return redirect()->route('login')->with('error', 'Session expired. Please login again.');
        }
        return view('calendar.index', [
            'supabaseUrl'     => config('services.supabase.url'),
            'supabaseAnonKey' => config('services.supabase.anon_key'),
            'supabaseSvcKey'  => config('services.supabase.service_key'),
            'userId'          => session('user_id'),
        ]);
    }
}
