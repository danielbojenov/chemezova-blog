<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;

class HomeController extends Controller
{
    /**
     * Show the home page.
     *
     * The template still carries the design's placeholder content; the queries for
     * the featured, latest and popular articles land here once the sections are wired up.
     */
    public function __invoke(): View
    {
        return view('home');
    }
}
