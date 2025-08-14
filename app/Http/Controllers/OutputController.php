<?php

namespace App\Http\Controllers;

use App\Models\Output;
use App\Models\OutputInvoice;
use App\Models\Product;
use Illuminate\Http\Request;
use Str;
use Validator;

class OutputController extends Controller
{
    // get all outputs for the specified invoice number
    public function index($invoice_no)
    {
        if (empty($invoice_no)) {
            return response()->json(['error' => 'رقم الفاتورة مطلوب'], 400);
        }

        // الحصول على الفاتورة (سجل واحد فقط)
        $invoice = OutputInvoice::where('invoice_no', $invoice_no)->first();

        if (!$invoice) {
            return response()->json(['message' => 'هذه الفاتورة غير موجودة'], 404);
        }

        // جلب المنتجات بناءً على id الفاتورة
        $outputs = Output::where('output_invoice_id', $invoice->id)->get();

        if ($outputs->isEmpty()) {
            return response()->json(['message' => 'لا توجد منتجات مرتبطة بهذه الفاتورة'], 404);
        }

        $data = $outputs->map(function ($product) use ($invoice_no) {
            return [
                'id' => $product->id,
                'invoice_no' => $invoice_no,
                'product_id' => $product->product_id,
                'quantity' => $product->quantity,
            ];
        });

        return response()->json($data);
    }



    // update a specific output for a specific invoice
    public function update(Request $request, string $id)
    {
        $validator = Validator::make($request->all(), [
            'invoice_no' => 'nullable|integer|exists:outputs_invoice,invoice_no',
            'product_id' => 'nullable|integer|exists:products,id',
            'quantity' => 'nullable|integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $output = Output::findOrFail($id);
            $validatedData = $validator->validated();


            $output->update([
                'invoice_no' => $validatedData['invoice_no'] ?? $output['invoice_no'],
                'product_id' => $validatedData['product_id'] ?? $output['product_id'],
                'quantity' => $validatedData['quantity'] ?? $output['quantity'],
            ]);

            return response()->json(['message' => 'تم تعديل المنتج بنجاح'], 200);
        
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['error' => 'لم يتم العثور على المنتج'], 404);
        
        } catch (\Exception $e) {
            return response()->json(['error' => 'حدث خطأ أثناء التحديث', 'message' => $e->getMessage()], 500);
        }
    }


    // delete a specific output
    public function destroy(string $id)
    {
        try {
            $output = Output::findOrFail($id);
            $output->delete();
            return response()->json(['message' => 'تم حذف المنتج بنجاح'], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['error' => 'لم يتم العثور على المنتج'], 404);
        } catch (\Exception $e) {
            return response()->json(['error' => 'حدث خطأ أثناء الحذف', 'message' => $e->getMessage()], 500);
        }
    }


    // retrieve a specific output product by invoice number and product number
    public function getOutputProduct(Request $request)
    {
        $invoiceId = $request->input('invoiceId');
        $product_no = strtoupper($request->input('product_no'));

        if (empty($invoiceId) || empty($product_no)) {
            return response()->json(['error' => 'يجب ادخال رقم المنتج ورقم الفاتورة'], 400);
        }

        $product = Product::whereRaw('UPPER(product_no) = ?', [Str::upper($product_no)])->first();

        if (empty($product)) {
            return response()->json(['error' => 'المنتج غير موجود'], 400);
        }

        $product_id = $product['id'];

        $output = Output::where('output_invoice_id', $invoiceId)
                        ->where('product_id', $product_id)
                        ->first();
        if (empty($output)) {
            return response()->json(['message' => 'هذا المنتج غير موجود'],404);
        }
        return response()->json($output);
    }
}
