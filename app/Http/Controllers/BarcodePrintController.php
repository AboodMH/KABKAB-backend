<?php

namespace App\Http\Controllers;

use App\Models\InputInvoice;
use DB;
use Exception;
use Http;
use Illuminate\Http\Request;
use Symfony\Component\Console\Input\Input;

class BarcodePrintController extends Controller
{
    public function printBarcodeLabelDependInvoiceId($invoiceId)
    {
        try {
            $products = DB::table('inputs')
                ->join('products', 'inputs.product_id', '=', 'products.id')
                ->where('inputs.input_invoice_id', $invoiceId)
                ->select(
                    'products.barcode',
                    'products.product_no',
                    'products.sell_price',
                    'inputs.quantity'
                )
                ->get();

            $pythonServerUrl = 'http://127.0.0.1:5005/print-label';

            foreach ($products as $product) {
                $data = [
                    'price'   => $product->sell_price,
                    'product_name' => $product->product_no,
                    'barcode'      => $product->barcode,
                    'quantity'     => $product->quantity,
                ];

                $response = Http::post($pythonServerUrl, $data);
            }

        } catch (Exception $e) {
            return response()->json([
                'message' => 'خطأ في الاتصال بسيرفر الطباعة',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
