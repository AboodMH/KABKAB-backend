<?php

namespace App\Http\Controllers;

use App\Models\Output;
use App\Models\OutputInvoice;
use App\Models\Payment;
use App\Models\Product;
use DB;
use Exception;
use Illuminate\Http\Request;
use Validator;

class OutputInvoiceController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index($date)
    {
        list($year, $month) = explode('-', $date);

        $OutputsInvoice = OutputInvoice::whereYear('date', $year)
                                            ->whereMonth('date', $month)
                                            ->orderBy('date', 'asc')
                                            ->get();

        return response()->json($OutputsInvoice);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        try {
            // التحقق من البيانات باستخدام Validator
            $validator = Validator::make($request->all(), [
                'invoice_no' => 'nullable|numeric',
                'value' => 'nullable|numeric',
                'quantity' => 'nullable|numeric',
                'note' => 'nullable|string',
                'discount' => 'nullable|numeric',
                'discount_type' => 'nullable|in:percentage,fixed'
            ],[
                'invoice_no.numeric' => 'رقم الفاتورة يجب أن يكون رقمًا',
                'value.numeric' => 'قيمة الفاتورة يجب أن تكون رقمًا',
                'quantity.numeric' => 'الكمية يجب أن تكون رقمًا',
                'discount.numeric' => 'خصم الفاتورة يجب أن يكون رقمًا',
                'discount_type.in' => 'نوع الخصم يجب أن يكون من نوعي النسبةأو ثابت',
            ]);

            // إذا كانت البيانات غير صحيحة، سيتم إرجاع الأخطاء بتنسيق JSON
            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'errors' => $validator->errors(),
                ], 422); // 422: Unprocessable Entity
            }

            // البحث عن الخصم باستخدام الـ ID
            $OutputsInvoice = OutputInvoice::findOrFail($id);
            $validatedData = $validator->validated();

            // تحديث الخصم
            $OutputsInvoice->update([
                'invoice_no' => $validatedData['invoice_no'] ?? $OutputsInvoice->invoice_no,
                'date' => $OutputsInvoice->date,
                'value' => $validatedData['value'] ?? $OutputsInvoice->value,
                'quantity' => $validatedData['quantity'] ?? $OutputsInvoice->quantity,
                'note' => $validatedData['note'] ?? $OutputsInvoice->note,
                'discount' => $validatedData['discount'] ?? $OutputsInvoice->discount,
                'discount_type' => $validatedData['discount_type'] ?? $OutputsInvoice->discount_type
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'تم تعديل الفاتورة بنجاح',
                'data' => $OutputsInvoice,
            ], 200); // 200: OK
        } catch (Exception $e) {
            // في حالة حدوث استثناء أثناء المعالجة
            return response()->json([
                'status' => 'error',
                'message' => 'An error occurred: ' . $e->getMessage(),
            ], 500); // 500: Internal Server Error
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        try {
            DB::transaction(function () use ($id) {
                $outputInvoice = OutputInvoice::findOrFail($id);

                // جلب الإخراجات المرتبطة بالفاتورة
                $outputs = Output::where('invoice_id', $outputInvoice->id)->get();

                foreach ($outputs as $output) {
                    // استرجاع المنتج المرتبط
                    $product = Product::find($output->product_id);

                    if ($product) {
                        // استرجاع الكمية المحذوفة (لأنها إخراج)
                        $product->quantity += $output->quantity;
                        $product->state = $product->quantity <= 0 ? 'out_of_stock' : 'available';
                        $product->save();
                    }

                    // حذف الإدخال
                    $output->delete();
                }

                // حذف الفاتورة بعد الانتهاء
                $outputInvoice->delete();
            });

            return response()->json([
                'status' => 'success',
                'message' => 'تم حذف فاتورة الإخراج بنجاح.',
            ], 200); // 200: OK

        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'حدث خطأ أثناء حذف الفاتورة: ' . $e->getMessage(),
            ], 500); // 500: Internal Server Error
        }
    }
    

    // fetch data of invoice depend on invoice number
    public function getProductOfInvoice($invoiceId)
    {
        $products = Output::join('products', 'outputs.product_id', '=', 'products.id')
            ->where('outputs.output_invoice_id', $invoiceId)
            ->select(
                'products.barcode',
                'products.product_no',
                'products.product_name',
                DB::raw("CASE 
                            WHEN products.product_no = 'NON-000' 
                            THEN outputs.sell_price 
                            ELSE products.sell_price 
                        END as sell_price"),
                'products.image',
                'outputs.quantity'
            )
            ->get();

        if ($products->isEmpty()) {
            return response()->json([
                'message' => 'لا توجد منتجات لهذه الفاتورة',
                'data' => []
            ], 404);
        }

        return response()->json([
            'message' => 'تم جلب بيانات المنتجات بنجاح',
            'data' => $products
        ]);
    }

}
