<?php

namespace App\Http\Controllers;

use App\Models\DirectMessage;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MessageController extends Controller
{
    public function index(Request $request): View
    {
        $currentUserId = (string) $request->session()->get('user_id', '');
        $selectedUserId = (string) $request->query('user', '');

        $users = User::query()
            ->select('id', 'name')
            ->when($currentUserId !== '', fn ($query) => $query->where('id', '!=', $currentUserId))
            ->orderBy('name')
            ->get();

        if ($selectedUserId === '' && $users->isNotEmpty()) {
            $selectedUserId = (string) $users->first()->id;
        }

        $messages = collect();

        if ($currentUserId !== '' && $selectedUserId !== '') {
            $messages = DirectMessage::query()
                ->where(function ($query) use ($currentUserId, $selectedUserId) {
                    $query->where('sender_id', $currentUserId)
                        ->where('receiver_id', $selectedUserId);
                })
                ->orWhere(function ($query) use ($currentUserId, $selectedUserId) {
                    $query->where('sender_id', $selectedUserId)
                        ->where('receiver_id', $currentUserId);
                })
                ->orderBy('created_at')
                ->get();
        }

        return view('home.messages', [
            'currentUserId' => $currentUserId,
            'users' => $users,
            'selectedUserId' => $selectedUserId,
            'messages' => $messages,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $currentUserId = (string) $request->session()->get('user_id', '');

        if ($currentUserId === '') {
            return back()->withErrors(['session' => 'Please log in first.']);
        }

        $validated = $request->validate([
            'receiver_id' => ['required', 'string', 'max:191'],
            'message' => ['required', 'string', 'max:2000'],
        ]);

        if ($validated['receiver_id'] === $currentUserId) {
            return back()->withErrors(['receiver_id' => 'You cannot message yourself.']);
        }

        DirectMessage::create([
            'sender_id' => $currentUserId,
            'receiver_id' => $validated['receiver_id'],
            'message' => $validated['message'],
        ]);

        return redirect()->route('messages', ['user' => $validated['receiver_id']]);
    }
}
