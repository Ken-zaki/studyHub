<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\FocusTrack;

class FocusTrackSeeder extends Seeder
{
    public function run(): void
    {
        FocusTrack::create([
            'title'     => 'Relaxing Music',
            'artist'    => 'StudyHub',
            'file_path' => 'music/relaxing-music.mp3',
            'is_active' => true,
        ]);
    }
}