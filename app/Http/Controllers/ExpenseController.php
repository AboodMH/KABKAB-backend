<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use Exception;
use Illuminate\Http\Request;
use Storage;
use Str;
use Validator;

class ExpenseController extends Controller
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

        $expenses = Expense::
            whereYear('date', $year)
            ->whereMonth('date', $month)
            ->orderBy('date', 'asc')
            ->get();

        return response()->json($expenses);
    }
    
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $expenses = Expense::all();
        return response()->json($expenses);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'date' => 'nullable|date',
                'name' => 'required|string',
                'amount' => 'required|numeric|min:0',
                'payment_method' => 'nullable|in:cash,credit_card,click,bank_transfer',
                'note' => 'nullable|string',
                'is_deported' => 'nullable|boolean',
                'invoice_image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048'
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 400);
            }

            $validatedData = $validator->validated();

            $validatedData['date'] ??= now();
            $validatedData['note'] ??= '';
            $validatedData['user_id'] = auth()->id();
            $validatedData['is_deported'] ??= false;
            $validatedData['payment_method'] ??= 'cash';

            // رفع الصورة إذا وُجدت
            if ($request->hasFile('image')) {
                $imageName = Str::random(40) . '.' . $request->file('image')->getClientOriginalExtension();
                $filePath = 'expense/image';
                Storage::disk('public')->putFileAs($filePath, $request->file('image'), $imageName);
                $validatedData['image'] = $filePath . '/' . $imageName;
            } else {
                $validatedData['image'] = null;
            }

            Expense::create($validatedData);

            return response()->json(['message' => 'تم إضافة العملية بنجاح'], 201);
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
            $validator = Validator::make($request->all(), [
                'date' => 'nullable|date',
                'name' => 'nullable|string',
                'amount' => 'nullable|numeric|min:0',
                'note' => 'nullable|string',
                'is_deported' => 'nullable|boolean',
                'payment_method' => 'nullable|in:cash,credit_card,click,bank_transfer',
                'invoice_image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048'
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 400);
            }

            $validatedData = $validator->validated();
            $expense = Expense::findOrFail($id);

            if ($request->hasFile('image')) {
                if ($expense->image) {
                    Storage::disk('public')->delete($expense->image);
                }

                $imageName = Str::random(40) . '.' . $request->file('image')->getClientOriginalExtension();
                $filePath = 'expense/image';
                Storage::disk('public')->putFileAs($filePath, $request->file('image'), $imageName);
                $validatedData['image'] = $filePath . '/' . $imageName;
            }

            $expense->update([
                'date' => $validatedData['date'] ?? $expense->date,
                'name' => $validatedData['name'] ?? $expense->name,
                'amount' => $validatedData['amount'] ?? $expense->amount,
                'note' => $validatedData['note'] ?? $expense->note,
                'is_deported' => $validatedData['is_deported'] ?? $expense->is_deported,
                'payment_method' => $validatedData['payment_method'] ?? $expense->payment_method,
                'invoice_image' => $validatedData['invoice_image'] ?? $expense->invoice_image,
            ]);

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
            $expense = Expense::findOrFail($id);

            $expense->delete();
            
            return response()->json(['message' => 'تم حذف العملية بنجاح'], 200);
        } catch (Exception $e) {
            return response()->json(['error' => 'حدث خطأ أثناء حذف العملية: ' . $e->getMessage()], 500);
        }
    }
}
