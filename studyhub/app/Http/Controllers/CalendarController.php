<?php

// CalendarController.php

namespace App\Http\Controllers;

class CalendarController extends Controller
{
    public function index()
    {
        if (!session('user_id')) {
            return redirect()->route('login')->with('error', 'Session expired.');
        }
        return view('home.calendar', [
            'supabaseUrl'     => config('services.supabase.url'),
            'supabaseAnonKey' => config('services.supabase.anon_key'),
            'supabaseSvcKey'  => config('services.supabase.service_key'),
            'userId'          => session('user_id'),
        ]);
    }
}
