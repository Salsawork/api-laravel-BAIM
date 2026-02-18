<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

use App\Models\Bank;

class BankController extends Controller
{
    public function banks()
    {
        $data = Bank::select('id_bank', 'nama_bank')->get();

        return response()->json([
            'status' => true,
            'data' => $data
        ]);
    }
}
