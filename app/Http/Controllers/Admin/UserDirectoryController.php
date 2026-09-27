<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

/** All shop users across businesses (the central sign-in index). */
class UserDirectoryController extends Controller
{
    public function __invoke(): View
    {
        return view('admin.users');
    }
}
