<?php

// TaskController.php

namespace App\Http\Controllers;

class TaskController extends Controller
{
    public function index()
    {
        if (!session('user_id')) {
            return redirect()->route('login')->with('error', 'Session expired.');
        }
        return view('home.tasks', [
            'supabaseUrl'     => config('services.supabase.url'),
            'supabaseAnonKey' => config('services.supabase.anon_key'),
            'supabaseSvcKey'  => config('services.supabase.service_key'),
            'userId'          => session('user_id'),
        ]);
    }
}
