<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use Exception;
use Illuminate\Http\Request;
use Validator;

class PaymentController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $payments = Payment::all();
        return response()->json($payments);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'type' => 'required|in:inside,outside',
                'method' => 'required|in:cash,card,click',
                'amount' => 'required|numeric|min:0',
                'amount_paid' => 'nullable|numeric|min:0',
                'invoice_no' => 'required|numeric',
                'invoice_type' => 'required|in:input,output',
                'note' => 'nullable|string',
            ], [
                'type.required' => 'يجب تحديد نوع المعاملة (داخل أو خارج)',
                'method.required' => 'يجب تحديد طريقة الدفع',
                'amount.required' => 'يجب إدخال قيمة الفاتورة',
                'invoice_no.required' => 'يجب إدخال رقم الفاتورة',
                'invoice_type.required' => 'يجب تحديد نوع الفاتورة',
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 400);
            }

            $validatedData = $validator->validated();

            $validatedData['method'] ??= 'cash'; // تعيين القيمة الافتراضية للطريقة إذا لم يتم تحديدها

            $validatedData['amount_paid'] ??= $validatedData['amount']; // تعيين القيمة الافتراضية للمبلغ المدفوع إذا لم يتم تحديده

            $validatedData['user_id'] = auth()->id();

            $validatedData['is_deported'] = false;

            // افتراضيًا إذا لم يتم إرسال "change" نحسبه تلقائيًا
            if (!isset($validatedData['change'])) {
                $validatedData['change'] = $validatedData['amount_paid'] - $validatedData['amount'];
                if ($validatedData['change'] < 0) $validatedData['change'] = 0;
            }

            Payment::create($validatedData);

            return response()->json(['message' => 'تم إضافة الدفعة بنجاح'], 201);

        } catch (Exception $e) {
            return response()->json(['error' => 'حدث خطأ أثناء إضافة الدفعة: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $payment = Payment::find($id);

        if (!$payment) {
            return response()->json(['error' => 'لم يتم العثور على الدفع'], 404);
        }

        return response()->json($payment);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        try {
            $payment = Payment::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'type' => 'nullable|in:inside,outside',
                'method' => 'nullable|in:cash,card,click,bank_transfer',
                'amount' => 'nullable|numeric|min:0',
                'amount_paid' => 'nullable|numeric|min:0',
                'invoice_no' => 'nullable|numeric',
                'invoice_type' => 'nullable|in:input,output',
                'note' => 'nullable|string',
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 400);
            }

            $data = array_merge($payment->toArray(), $validator->validated());

            $data['user_id'] = $validator['user_id'] ?? auth()->id();

            $data['is_deported'] = $validator['user_id'] ?? false;

            // إعادة احتساب الباقي إذا لم يُرسل
            if (!isset($data['change']) && isset($data['amount']) && isset($data['amount_paid'])) {
                $data['change'] = $data['amount_paid'] - $data['amount'];
                if ($data['change'] < 0) $data['change'] = 0;
            }

            $payment->update($data);

            return response()->json(['message' => 'تم تعديل الدفعة بنجاح'], 200);

        } catch (Exception $e) {
            return response()->json(['error' => 'حدث خطأ أثناء تعديل الدفعة: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        try {
            $payment = Payment::findOrFail($id);
            $payment->delete();

            return response()->json(['message' => 'تم حذف الدفعة بنجاح'], 200);
        } catch (Exception $e) {
            return response()->json(['error' => 'حدث خطأ أثناء حذف الدفعة: ' . $e->getMessage()], 500);
        }
    }
}
