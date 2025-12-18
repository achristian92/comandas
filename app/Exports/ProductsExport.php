<?php

namespace App\Exports;

use App\Models\Product;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\FromView;

class ProductsExport implements FromView
{

    private $data;

    public function __construct($data)
    {
        $this->data = $data;
    }

    public function view(): \Illuminate\Contracts\View\View
    {
        return view('exports.products', [
            'stocks' => $this->data
        ]);
    }
}
