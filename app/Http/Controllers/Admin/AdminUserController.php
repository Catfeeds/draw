<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Model\AdminUser;

class AdminUserController extends Controller
{
    public function index(Request $request) {
    	return AdminUser::all();
    }
}
