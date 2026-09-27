<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;

class ActivityController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->can('activity.view'), 403);

        return view('activity.index');
    }
}
