<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\TemporaryOutput;
use Exception;
use Illuminate\Http\Request;
use Validator;

class TemporaryOutputController extends Controller
{
    // fetch all of outputs temporary
    public function index()
    {   
        $user = auth()->user();
        $temporaryProducts = TemporaryOutput::where('user_id', $user->id)->get();
        return response()->json( data: $temporaryProducts);
    }

    /**
     * تخزين منتج جديد في التخزين المؤقت
     */
    public function store(Request $request)
    {
        try {
            // التحقق من صحة البيانات
            $validator = Validator::make($request->all(), [
                'barcode' => 'required|string',
                'quantity' => 'required|integer|min:1',
            ], [
                'barcode.required' => 'يجب إدخال رقم المنتج',
                'quantity.required' => 'يجب إدخال الكمية',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'error' => 'فشلت عملية التحقق من البيانات',
                    'messages' => $validator->errors()
                ], 422);
            }

            $validated = $validator->validated();

            $validated['user_id'] = auth()->id();

            // جلب المنتج من جدول products باستخدام barcode
            $product = Product::where('barcode', $validated['barcode'])->orWhere('product_no', $validated['barcode'])->first();

            if (!$product) {
                return response()->json([
                    'error' => 'المنتج غير موجود'
                ], 404);
            }
            // التحقق من الكمية المطلوبة
            if ($product->quantity < $validated['quantity']) {
                return response()->json([
                    'error' => "المتوفرة في المخزون {$product->quantity}"
                ], 400);
            }

            // التحقق إذا كان المنتج مضاف مسبقًا
            $existing = TemporaryOutput::where('product_id', $product->id)->where('user_id', $validated['user_id'])->first();


            if ($existing) {
                $existing->quantity += $validated['quantity'];
                $existing->save();
                return response()->json(['message' => 'تم تحديث الكمية للمنتج المؤقت بنجاح'], 200);
            } else {
                TemporaryOutput::create([
                    'product_id' => $product->id,
                    'price' => $product->sell_price,
                    'quantity' => $validated['quantity'],
                    'user_id' => $validated['user_id'],
                ]);
                return response()->json(['message' => 'تمت إضافة المنتج المؤقت بنجاح'], 201);
            }

        } catch (Exception $e) {
            return response()->json([
                'error' => 'حدث خطأ غير متوقع',
                'message' => $e->getMessage()
            ], 500);
        }
    }


    /**
     * تحديث منتج موجود
     */
    public function update(Request $request, string $id)
    {
        try {
            $validator = Validator::make($request->all(), [
                'product_id' => 'nullable|integer|exists:products,id',
                'price' => 'nullable|numeric|min:0',
                'quantity' => 'nullable|integer|min:1',
                'user_id' => 'nullable|integer|exists:users,id',
            ],
            [
                'product_id.exists' => 'المنتج غير موجود',
                'price.decimal' => 'يجب ان يكون السعر رقما',
                'quantity.integer' => 'يجب أن تكون الكمية عدد صحيح',
                'quantity.min' => 'يجب أن تكون الكمية أكبر من 0',
                'user_id.exists' => 'المستخدم غير موجود',
            ]
        );

            if ($validator->fails()) {
                return response()->json([
                    'error' => 'فشلت عملية التحقق من البيانات',
                    'messages' => $validator->errors()
                ], 422);
            }

            $product = TemporaryOutput::findOrFail($id);
            $validatedData = $validator->validated();

            $p = Product::findOrFail($validatedData['product_id'] ?? $product->product_id);
            if (!$p) {
                return response()->json(['error' => 'المنتج غير موجود'], 404);
            }
            if ($p->quantity < ($validatedData['quantity'] ?? $product->quantity)) {
                return response()->json(['error' => "الكمية المتوفرة في المخزن {$p->quantity}"], 400);
            }


            $product->update([
                'product_id' => $validatedData['product_id'] ?? $product->product_id,
                'price' => $validatedData['price'] ?? $product->price,
                'quantity' => $validatedData['quantity'] ?? $product->quantity,
                'user_id' => $validatedData['user_id'] ?? $product->user_id,
            ]);
            
            return response()->json(['message' => 'تم تعديل المنتج بنجاح'], 200);
        } catch (Exception $e) {
            return response()->json([
                'error' => 'حدث خطأ أثناء العملية',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * حذف المنتج
     */
    public function destroy(string $id)
    {
        try {
            $product = TemporaryOutput::findOrFail($id);

            $product->delete();
            return response()->json(['message' => 'تم حذف المنتج بنجاح'], 200);
        } catch (Exception $e) {
            return response()->json([
                'error' => 'حدث خطأ أثناء العملية',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * حذف جميع المنتجات المؤقتة
     */
    public function clearAll()
    {
        try {
            TemporaryOutput::where('user_id', auth()->id())->delete();
            return response()->json(['message' => 'تم حذف جميع المنتجات بنجاح'], 200);
        } catch (Exception $e) {
            return response()->json([
                'error' => 'حدث خطأ أثناء العملية',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
