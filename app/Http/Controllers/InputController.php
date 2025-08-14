<?php

namespace App\Http\Controllers;

use App\Models\Input;
use Illuminate\Http\Request;
use Validator;

class InputController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index($invoice_no)
    {
        if (empty($invoice_no)) {
            return response()->json(['error' => 'رقم الفاتورة مطلوب'], 400);
        }

        // جلب المنتجات بناءً على invoice_no
        $Inputs = Input::where('invoice_no', $invoice_no)->get();

        // التأكد من أن المنتجات موجودة
        if ($Inputs->isEmpty()) {
            return response()->json(['message' => 'هذه الفاتوره غير موجوده'], 404);
        }

        return response()->json($Inputs);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        try {
            $validator = Validator::make($request->all(), [
                'invoice_no' => 'nullable|numeric|exists:inputs_invoice,invoice_no',
                'product_id' => 'nullable|numeric|exists:products,id',
                'quantity' => 'nullable|integer|min:1',
            ],[
                'invoice_no.numeric' => 'رقم الفاتورة يجب أن يكون رقم',
                'invoice_no.exists' => 'رقم الفاتورة غير موجود',
                'product_id.numeric' => 'اسم المنتج يجب أن يكون رقم',
                'product_id.exists' => 'اسم المنتج غير موجود',
                'quantity.integer' => 'الكمية يجب أن تكون رقم',
                'quantity.min' => 'الكمية يجب أن تكون أكبر من 1',
            ]);

            if ($validator->fails()) {
                return response()->json(['error' => 'حدث خطأ أثناء إنشاء البيانات','message' => $validator->messages()], 422);
            }

            $validatedData = $validator->validated();
            $input = Input::findOrFail($id);

            $input->update([
                'invoice_no' => $validatedData['invoice_no'] ?? $input->invoice_no,
                'product_id' => $validatedData['product_id'] ?? $input->product_id,
                'quantity' => $validatedData['quantity'] ?? $input->quantity,
            ]);
            
            return response()->json(['message' => 'تم تعديل البيانات بنجاح'],);

        }catch(\Exception $e) {
            return response()->json(['error' => 'حدث خطأ أثناء تعديل البيانات']);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        try {
            $input = Input::findOrFail($id);

            $input->delete();
            
            return response()->json(['message' => 'تم حذف البيانات بنجاح']);
            
        }catch(\Exception $e) {
            return response()->json(['error' => 'حدث خطأ أثناء حذف البيانات']);
        
        }

    }
}
