<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\FileManagers\FileManagerViews;
use Illuminate\Http\Request;

class FileManagerController extends Controller
{
    public function index(Request $request)
    {
        return FileManagerViews::index($request);
    }
    public function show($id)
    {
        return FileManagerViews::show($id);
    }
    public function destroy($id)
    {
        return FileManagerViews::destroy($id);
    }
}
