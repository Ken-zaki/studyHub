<?php

namespace App\Http\Controllers;

use App\Models\FriendRequest;
use Illuminate\Http\Request;

class DiagnosticsController extends Controller
{
    public function friendRequests()
    {
        $userId = trim((string) session('user_id', ''));

        $allRequests = FriendRequest::all();
        $myOutgoing = FriendRequest::where('sender_id', $userId)->get();
        $myIncoming = FriendRequest::where('receiver_id', $userId)->get();

        $data = [
            'session_user_id' => $userId,
            'total_requests_in_db' => count($allRequests),
            'all_requests' => $allRequests->toArray(),
            'my_outgoing' => $myOutgoing->toArray(),
            'my_incoming' => $myIncoming->toArray(),
        ];

        // Also save to a file for easier debugging
        file_put_contents(
            storage_path('logs/diagnostics.txt'),
            json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n",
            FILE_APPEND
        );

        return response()->json($data);
    }
}
