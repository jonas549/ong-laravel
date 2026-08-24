<?php

namespace App\Http\Controllers;

use App\Models\Edition;

class EditionController extends Controller
{
    public function index()
    {
        return view('public.editions.index', [
            'ediciones' => Edition::activos()->ordered()->get(),
        ]);
    }
}
