<?php

namespace App\Livewire;

use App\Models\Gym;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.public')]
class Gyms extends Component
{
    public function render()
    {
        $gyms = Gym::published()
            ->with(['course', 'domain'])
            ->withCount('items')
            ->orderBy('sort')
            ->orderBy('title')
            ->get();

        return view('livewire.gyms', ['gyms' => $gyms]);
    }
}
