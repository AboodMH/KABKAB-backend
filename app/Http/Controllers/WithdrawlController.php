<?php

namespace App\Http\Controllers;

use App\Models\Withdrawal;
use App\Models\Withdrawl;
use Exception;
use Illuminate\Http\Request;
use Validator;

class WithdrawlController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $withdrawls = Withdrawal::all();
        return response()->json($withdrawls);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'date' => 'nullable|date',
                'employee_id' => 'required|integer|exists:staff,id',
                'amount' => 'required|numeric|min:0',
                'payment_method' => 'nullable|in:cash,credit_card,click,bank_transfer',
                'note' => 'nullable|string',
                'is_deported' => 'nullable|boolean',
            ], [
                'date.date' => 'تأكد من تنسيق التاريخ',
                'employee_id.required' => 'يجب ادخال رقم الموظف',
                'employee_id.integer' => 'يجب ادخال رقم الموظف صحيح',
                'employee_id.exists' => 'رقم الموظف غير موجود',
                'amount.required' => 'يجب ادخال المبلغ',
                'amount.numeric' => 'يجب ادخال المبلغ صحيح',
                'amount.min' => 'يجب ادخال المبلغ أكبر من 0',
                'note.string' => 'يجب ادخال الملاحظة بشكل صحيح',
                'is_deported.boolean' => 'يجب ان تكون قيمة الترحيل منطقية true or false'
            ]);

            // إذا فشل التحقق من البيانات
            if ($validator->fails()) {
                return response()->json([
                    'status' => false,
                    'message' => 'فشل التحقق من البيانات',
                    'errors' => $validator->errors()
                ], 400);
            }

            $validatedData = $validator->validated();
            $validatedData['date'] ??= now();

            $validatedData['note'] ??= '';

            $validatedData['user_id'] = auth()->id();
            
            $validatedData['is_deported'] ??= false;

            $validatedData['payment_method'] ??= 'cash';

            Withdrawal::create($validatedData);

            return response()->json(['message' => 'تم اضافة العملية بنجاح'], 201);
        } catch (Exception $e) {
            return response()->json(['error' => 'حدث خطأ أثناء إضافة العملية: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        try {
            // استخدام Validator بدلاً من validate()
            $validator = Validator::make($request->all(), [
                'employee_id' => 'nullable|integer|exists:staff,id',
                'amount' => 'nullable|numeric|min:0',
                'note' => 'nullable|string',
            ], [
                'employee_id.integer' => 'يجب ادخال رقم الموظف صحيح',
                'employee_id.exists' => 'رقم الموظف غير موجود',
                'amount.numeric' => 'يجب ادخال المبلغ صحيح',
                'amount.min' => 'يجب ادخال المبلغ أكبر من 0',
                'note.string' => 'يجب ادخال ملاحظة صحيحة',
            ]);

            // إذا فشل التحقق من البيانات
            if ($validator->fails()) {
                return response()->json([
                    'status' => false,
                    'message' => 'فشل التحقق من البيانات',
                    'errors' => $validator->errors()
                ], 400);
            }

            $validatedData = $validator->validated();
            $withdrawl = Withdrawal::findOrFail($id);

            $data = [
                'employee_id' => $validatedData['employee_id'] ?? $withdrawl['employee_id'],
                'amount' => $validatedData['amount'] ?? $withdrawl['amount'],
                'note' => $validatedData['note'] ?? $withdrawl['note'],
            ];

            $withdrawl->update($data);

            return response()->json(['message' => 'تم تعديل العملية بنجاح'], 200);
        } catch (Exception $e) {
            return response()->json(['error' => 'حدث خطأ أثناء تعديل العملية: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        try {
            $withdrawl = Withdrawal::findOrFail($id);
            $withdrawl->delete();

            return response()->json(['message' => 'تم حذف العملية بنجاح'], 200);
        } catch (Exception $e) {
            return response()->json(['error' => 'حدث خطأ أثناء حذف العملية: ' . $e->getMessage()], 500);
        }
    }


    public function getDataByMonth($date)
    {
        // تحقق من أن التنسيق صحيح (YYYY-MM)
        if (!preg_match('/^\d{4}-\d{2}$/', $date)) {
            return response()->json(['message' => 'تنسيق التاريخ غير صالح. استخدم YYYY-MM.'], 422);
        }

        list($year, $month) = explode('-', $date);

        $Withdrawls = Withdrawal::
            whereYear('date', $year)
            ->whereMonth('date', $month)
            ->orderBy('date', 'asc')
            ->get();

        return response()->json($Withdrawls);
    }

}
