<?php

namespace App\Http\Controllers;

use App\Models\Input;
use App\Models\InputInvoice;
use App\Models\Product;
use DB;
use Illuminate\Http\Request;
use Validator;

class InputInvoiceController extends Controller
{
    /**
     * Get products of invoice
     */
    public function getProductOfInvoice($invoice_no)
    {
        $products = Input::
            join('products', 'inputs.product_id', '=', 'products.id')
            ->where('inputs.input_invoice_id', $invoice_no)
            ->select(
                'products.barcode',
                'products.product_no',
                'products.product_name',
                'products.buy_price',
                'products.sell_price',
                'products.image',
                'inputs.quantity'
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

    /**
     * Display a listing of the resource.
     */
    public function index($date)
    {
        list($year, $month) = explode('-', $date);

        $inputsInvoice = InputInvoice::whereYear('date', $year)
                                        ->whereMonth('date', $month)
                                        ->orderBy('date', 'asc')
                                        ->get();

        return response()->json($inputsInvoice);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        try {
            // التحقق من البيانات باستخدام Validator
            $validator = Validator::make($request->all(), [
                'invoice_no' => 'nullable|numeric',
                'company_id' => 'nullable|numeric|exists:companies,id',
                'value' => 'nullable|numeric',
                'quantity' => 'nullable|numeric',
                'note' => 'nullable|string',
                'discount' => 'nullable|numeric',
                'discount_type' => 'nullable|in:percentage,fixed'
            ],[
                'invoice_no.numeric' => 'يجب ان يكون رقم الفاتورة رقم',
                'company_id.numeric' => 'يجب ان يكون رقم الشركة رقم',
                'company_id.exists' => 'يجب ان يكون رقم الشركة موجود',
                'value.numeric' => 'يجب ان يكون قيمة الفاتورة رقم',
                'quantity.numeric' => 'يجب ان يكون كمية الفاتورة رقم',
                'note.string' => 'يجب ان يكون ملاحظات الفاتورة نص',
                'discount.numeric' => 'يجب ان يكون قيمة الخصم رقم',
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
            $inputsDiscount = InputInvoice::findOrFail($id);
            $validatedData=$validator->validated();

            // تحديث الخصم
            $inputsDiscount->update([
                'invoice_no' => $validatedData['invoice_no'] ?? $inputsDiscount->invoice_no,
                'company_id' => $validatedData['company_id'] ?? $inputsDiscount->company_id,
                'date' => $inputsDiscount->date,
                'value' => $validatedData['value'] ?? $inputsDiscount->value,
                'quantity' => $validatedData['quantity'] ?? $inputsDiscount->quantity,
                'note' => $validatedData['note'] ?? $inputsDiscount->note,
                'discount' => $validatedData['discount'] ?? $inputsDiscount->discount,
                'discount_type' => $validatedData['discount_type'] ?? $inputsDiscount->discount_type
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'تم تعديل الفاتورة بنجاح',
                'data' => $inputsDiscount,
            ], 200); // 200: OK
        } catch (\Exception $e) {
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
    public function destroy(string $id)
    {
        try {
                DB::transaction(function () use ($id) {
                $inputInvoice = InputInvoice::findOrFail($id);
        
                // جلب الإدخالات المرتبطة بالفاتورة
                $inputs = Input::where('invoice_id', $inputInvoice->id)->get();
        
                foreach ($inputs as $input) {
                    // استرجاع الكمية إلى المنتج
                    $product = Product::find($input->product_id);
                    
                    if ($product) {
                        $usedInOtherInvoices = Input::where('product_id', $input->product_id)
                            ->where('invoice_id', '!=', $inputInvoice->id)
                            ->exists();
                        
                        
                        if ($usedInOtherInvoices) {
                            $product->quantity = max(0, $product->quantity - $input->quantity); // أو + على حسب المنطق
                            $product->state = $product->quantity <= 0 ? 'out_of_stock' : 'available';
                            $product->save();
                        } else{
                            // المنتج غير مستخدم في أي فاتورة أخرى → احذفه
                            $product->delete();
                        }
                        
                    }
        
                    // حذف الإدخال
                    $input->delete();
                }
        
                // حذف الفاتورة بعد الانتهاء
                $inputInvoice->delete();
            });

            return response()->json([
                'status' => 'success',
                'message' => 'تم حذف الفاتورة بنجاح',
            ], 200); // 200: OK

        } catch (\Exception $e) {
            // في حالة حدوث استثناء أثناء المعالجة
            return response()->json([
                'status' => 'error',
                'message' => 'An error occurred: ' . $e->getMessage(),
            ], 500); // 500: Internal Server Error
        }
                        
    }
}
