<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\CompanyPayment;
use Exception;
use Illuminate\Http\Request;
use Validator;

class CompanyPaymentController extends Controller
{
    /**
     * Fetch data depend on month
     */
    public function getDataByMonth($date)
    {
        // تحقق من أن التنسيق صحيح (YYYY-MM)
        if (!preg_match('/^\d{4}-\d{2}$/', $date)) {
            return response()->json(['message' => 'تنسيق التاريخ غير صالح. استخدم YYYY-MM.'], 422);
        }

        list($year, $month) = explode('-', $date);

        $company_payments = CompanyPayment::with('company:id,name') // جلب فقط الاسم والـ id
            ->whereYear('date', $year)
            ->whereMonth('date', $month)
            ->orderBy('date', 'asc')
            ->get();

        return response()->json($company_payments);
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $company_payments = CompanyPayment::paginate(10); // 10 سجلات في الصفحة
        return response()->json($company_payments);
    }


    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'date' => 'nullable|date',
                'company_id' => 'required|integer|exists:companies,id',
                'amount' => 'required|numeric|min:0',
                'payment_method' => 'required|string',
                'note' => 'nullable|string',
            ], [
                'company_id.required' => 'يجب إدخال الشركة',
                'company_id.integer' => 'يجب أن يكون رقم الشركة صحيحا',
                'company_id.exists' => 'يجب أن يكون رقم الشركة موجودا',
                'amount.required' => 'يجب إدخال المبلغ',
                'amount.numeric' => 'يجب أن يكون المبلغ صحيحا',
                'amount.min' => 'يجب أن يكون المبلغ أكبر من 0',
                'payment_method.required' => 'يجب إدخال طريقة الدفع',
                'payment_method.string' => 'يجب أن يكون طريقة الدفع نصا',
                'note.string' => 'يجب أن يكون الملاحظة نصا',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            $validatedData = $validator->validated();
            if (empty($validatedData['note'])) {
                $validatedData['note'] = 'لايوجد ملاحظة';
            }

            $validatedData['date'] ??= now();

            // إنشاء الدفع
            $payment = CompanyPayment::create($validatedData);

            // خصم المبلغ من رصيد الشركة
            $company = Company::findOrFail($validatedData['company_id']);
            $company->balance -= $validatedData['amount'];
            $company->save();

            return response()->json(['message' => 'تمت إضافة الدفع بنجاح'], 201);
        } catch (Exception $e) {
            return response()->json(['error' => 'حدث خطأ أثناء إضافة الدفع: ' . $e->getMessage()], 500);
        }
    }


    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        try {
            $validator = Validator::make($request->all(), [
                'company_id' => 'nullable|integer|exists:companies,id',
                'amount' => 'nullable|numeric|min:0',
                'payment_method' => 'nullable|string',
                'note' => 'nullable|string',
            ], [
                'company_id.integer' => 'يجب أن يكون رقم الشركة صحيحا',
                'company_id.exists' => 'يجب أن يكون رقم الشركة موجودا',
                'amount.numeric' => 'يجب أن يكون المبلغ صحيحا',
                'amount.min' => 'يجب أن يكون المبلغ أكبر من 0',
                'payment_method.string' => 'يجب أن يكون طريقة الدفع نصا',
                'note.string' => 'يجب أن يكون الملاحظة نصا',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            $payment = CompanyPayment::findOrFail($id);
            $oldAmount = $payment->amount;

            $validatedData = $validator->validated();

            // تحديث رصيد الشركة (مع الأخذ بالحالة إذا تغيرت الشركة أو المبلغ)
            $oldCompany = $payment->company;

            $newCompanyId = $validatedData['company_id'] ?? $payment->company_id;
            $newAmount = $validatedData['amount'] ?? $payment->amount;

            if ($newCompanyId == $payment->company_id) {
                // نفس الشركة → نطرح الفارق بين المبلغ القديم والجديد
                $difference = $oldAmount - $newAmount;
                $oldCompany->balance = $difference;
                $oldCompany->save();
            } else {
                // اختلفت الشركة → نعيد المبلغ للشركة القديمة، ونطرحه من الجديدة
                $oldCompany->balance += $oldAmount;
                $oldCompany->save();

                $newCompany = Company::findOrFail($newCompanyId);
                $newCompany->balance -= $newAmount;
                $newCompany->save();
            }

            // تحديث البيانات
            $payment->update([
                'company_id' => $newCompanyId,
                'amount' => $newAmount,
                'payment_method' => $validatedData['payment_method'] ?? $payment->payment_method,
                'note' => $validatedData['note'] ?? $payment->note,
                'date' => $payment->date,
            ]);

            return response()->json(['message' => 'تم تعديل الدفع بنجاح'], 200);
        } catch (Exception $e) {
            return response()->json(['error' => 'حدث خطأ أثناء تعديل الدفع: ' . $e->getMessage()], 500);
        }
    }


    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        try {
            $companyPayment = CompanyPayment::findOrFail($id);

            // استرجاع الشركة المرتبطة وإعادة المبلغ إلى الرصيد
            $company = $companyPayment->company;
            $company->balance += $companyPayment->amount;
            $company->save();

            // حذف الدفع
            $companyPayment->delete();

            return response()->json(['message' => 'تم حذف الدفع بنجاح وتم تعديل رصيد الشركة'], 200);
        } catch (Exception $e) {
            return response()->json(['error' => 'حدث خطأ أثناء حذف الدفع: ' . $e->getMessage()], 500);
        }
    }

}
