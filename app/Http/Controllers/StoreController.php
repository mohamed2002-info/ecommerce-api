<?php

namespace App\Http\Controllers;

use App\Models\Store;

class StoreController extends Controller
{
    /** Public: the list of boutiques (Sfax / Tunis / Sousse). */
    public function index()
    {
        return response()->json(
            Store::orderBy('id')->get(['id', 'name', 'city', 'slug'])
        );
    }
}
