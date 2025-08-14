<?php

namespace App\Http\Controllers;

use App\Models\Company;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Validator;

class CompanyController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        try {
            $companies = Company::all();
            return response()->json(data: $companies);
        } catch (Exception $e) {
            return response()->json(['error' => 'حدث خطأ أثناء استرجاع الشركات: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        try {
            // تعريف القواعد والرسائل المخصصة للتحقق
            $validator = Validator::make($request->all(), [
                'name' => 'required|string',
                'address' => 'required|string',
                'discount' => 'nullable|numeric|min:0',
                'balance' => 'nullable|numeric|min:0',
            ], [
                'name.required' => 'يجب إدخال الاسم',
                'name.string' => 'يجب أن يكون الاسم نصًا',
                'address.required' => 'يجب إدخال العنوان',
                'address.string' => 'يجب أن يكون العنوان نصًا',
                'discount.numeric' => 'يجب أن تكون قيمة الخصم رقمًا',
                'discount.min' => 'يجب أن تكون قيمة الخصم أكبر من 0',
                'balance.numeric' => 'يجب أن تكون قيمة الرصيد رقمًا',
                'balance.min' => 'يجب أن تكون قيمة الرصيد أكبر من 0',
            ]);
        
            // التحقق من فشل التحقق
            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }
        
            // دمج البيانات من التحقق مع القيم الافتراضية
            $validatedData = array_merge($validator->validated(), [
                'discount' => $request->input('discount', 0),
                'balance' => $request->input('balance', 0),
            ]);
        
            // إنشاء الشركة
            Company::create($validatedData);
        
            return response()->json([
                'success' => true,
                'message' => 'تم إضافة الشركة بنجاح'
            ], 201);
        } catch (Exception $e) {
            return response()->json(['error' => 'حدث خطأ أثناء إضافة الشركة: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Show a specific company depend on id
     */
    public function show($id)
    {
        $company = Company::findOrFail($id);
        return response()->json($company);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        try {
            // تعريف القواعد والرسائل المخصصة للتحقق
            $validator = Validator::make($request->all(), [
                'name' => 'nullable|string',
                'address' => 'nullable|string',
                'discount' => 'nullable|numeric|min:0',
                'balance' => 'nullable|numeric|min:0',
            ], [
                'name.string' => 'يجب ان يكون الاسم نصاً',
                'address.string' => 'يجب ان يكون العنوان نصاً',
                'discount.numeric' => 'يجب ان تكون قيمة الخصم رقماً',
                'discount.min' => 'يجب ان تكون قيمة الخصم اكبر من 0',
                'balance.numeric' => 'يجب ان تكون قيمة الرصيد رقماً',
                'balance.min' => 'يجب ان تكون قيمة الرصيد اكبر من 0',
            ]);
        
            // التحقق من فشل التحقق
            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }
        
            // البحث عن الشركة باستخدام id
            $company = Company::findOrFail($id);

            $validatedData = $validator->validated();
        
            // دمج البيانات الجديدة مع البيانات القديمة إذا كانت null
            $data = [
                'name' => $validatedData['name'] ?? $company['name'],
                'address' => $validatedData['address'] ?? $company['address'],
                'discount' => $validatedData['discount'] ?? $company['discount'],
                'balance' => $validatedData['balance'] ?? $company['balance'],
            ];
        
            // تحديث الشركة
            $company->update($data);
        
            return response()->json(['message' => 'تم تعديل الشركة بنجاح'], 200);
        } catch (Exception $e) {
            return response()->json(['error' => 'حدث خطأ أثناء تعديل الشركة: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        try {
            $company = Company::findOrFail($id);
            $company->delete();
        
            return response()->json(['message' => 'تم حذف الشركة بنجاح'], 200);
        } catch (Exception $e) {
            return response()->json(['error' => 'حدث خطأ أثناء حذف الشركة: ' . $e->getMessage()], 500);
        }
    }
    

    /**
     * Fetch all data related on company invoices and payments and data
     */
    public function showDetails($id)
    {
        $company = Company::with([
            'inputInvoices' => function ($query) {
                $query->orderBy('date', 'desc');
            },
            'payments' => function ($query) {
                $query->orderBy('date', 'desc');
            }
        ])->findOrFail($id);

        return response()->json($company);
    }


}
