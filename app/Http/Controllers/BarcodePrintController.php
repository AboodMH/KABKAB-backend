<?php

namespace App\Http\Controllers;

use App\Models\Product;
use DB;
use Exception;
use Http;


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

    public function printBarcodeLabelDependProductNo($productNo, $quantity)
    {
        try {
            $product = Product::where('product_no', $productNo)->first();

            $pythonServerUrl = 'http://127.0.0.1:5005/print-label';

             $data = [
                'price'   => $product->sell_price,
                'product_name' => $product->product_no,
                'barcode'      => $product->barcode,
                'quantity'     => $quantity,
            ];

            Http::post($pythonServerUrl, $data);

        } catch (Exception $e) {
            return response()->json([
                'message' => 'خطأ في الاتصال بسيرفر الطباعة',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
