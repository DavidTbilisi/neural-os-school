<?php

namespace App\Http\Controllers;

use App\Services\Meter\Report;
use Illuminate\View\View;

/**
 * The learner's private METER dashboard — Evaluate + Report over their own event
 * log. Read-only, so a plain controller (no Livewire).
 */
class DashboardController extends Controller
{
    public function show(): View
    {
        $report = (new Report(auth()->user()))->build();

        return view('dashboard', ['report' => $report]);
    }
}
