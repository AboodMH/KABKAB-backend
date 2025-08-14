<?php

namespace App\Http\Controllers;

use App\Models\TemporaryInput;
use Exception;
use Illuminate\Http\Request;
use Storage;
use Str;
use Validator;

class TemporaryInputController extends Controller
{
    // fetch all of teemporary inputs
    public function index()
    {
        $temporaryProducts = TemporaryInput::all();
        return response()->json( data: $temporaryProducts);
    }

    /**
     * تخزين منتج جديد في التخزين المؤقت
     */
    public function store(Request $request)
    {
        try {
            // التحقق من صحة البيانات باستخدام Validator
            $validator = Validator::make($request->all(), [
                'product_no' => 'required|string',
                'product_name' => 'required|string',
                'buy_price' => 'required|numeric|min:0',
                'sell_price' => 'required|numeric|min:0',
                'quantity' => 'required|integer|min:1',
                'image' => 'nullable|image|mimes:jpg,jpeg,png,gif|max:2048',
            ], [
                'product_no.required' => 'يجب إدخال رقم المنتج',
                'product_name.required' => 'يجب إدخال اسم المنتج',
                'buy_price.required' => 'يجب إدخال سعر الشراء',
                'sell_price.required' => 'يجب إدخال سعر البيع',
                'quantity.required' => 'يجب إدخال الكمية',
                'image.image' => 'يجب أن يكون الملف صورة',
                'image.mimes' => 'يجب أن تكون الصورة من نوع JPG, JPEG, PNG, أو GIF',
                'image.max' => 'يجب ألا يتجاوز حجم الصورة 2 ميجابايت',
            ]);

            // إذا فشل التحقق، أرجع رسالة خطأ
            if ($validator->fails()) {
                return response()->json([
                    'error' => 'فشلت عملية التحقق من البيانات',
                    'messages' => $validator->errors()
                ], 422);
            }

            // جلب البيانات التي تم التحقق منها
            $validatedData = $validator->validated();

            $user = auth('sanctum')->user(); // أو auth()->user()
            if (!$user) {
                return response()->json(['error' => 'User not authenticated'], 401);
            }

            $validatedData['user_id'] = $user->id;

            // معالجة رفع الصورة
            if ($request->hasFile('image')) {
                $imageName = Str::random(40) . '.' . $request->file('image')->getClientOriginalExtension();
                $filePath = 'products/image';
                Storage::disk('public')->putFileAs($filePath, $request->file('image'), $imageName);
                $validatedData['image'] = $filePath . '/' . $imageName;
            } else {
                $validatedData['image'] = null;
            }

            // التحقق مما إذا كان المنتج موجودًا بالفعل
            $existingProduct = TemporaryInput::where('product_no', $validatedData['product_no'])->first();

            if ($existingProduct) {
                $existingProduct->quantity += $validatedData['quantity'];
                $existingProduct->save();
                return response()->json(['message' => 'تم تحديث الكمية للمنتج الموجود بنجاح'], 200);
            } else {
                // إنشاء باركود فريد للمنتج
                $number = mt_rand(100000000, 999999999);
                while (TemporaryInput::where('barcode', $number)->exists()) {
                    $number = mt_rand(100000000, 999999999);
                }
                $validatedData['barcode'] = $number;

                TemporaryInput::create($validatedData);
                return response()->json(['message' => 'تم إضافة المنتج بنجاح'], 201);
            }
        } catch (Exception $e) {
            // في حال حدوث أي خطأ غير متوقع، يتم إرجاع رسالة الخطأ
            return response()->json([
                'error' => 'حدث خطأ أثناء العملية',
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
                'barcode' => 'nullable|string',
                'product_no' => 'nullable|string',
                'product_name' => 'nullable|string',
                'buy_price' => 'nullable|numeric|min:0',
                'sell_price' => 'nullable|numeric|min:0',
                'quantity' => 'nullable|integer|min:1',
                'image' => 'nullable|image|mimes:jpg,jpeg,png,gif|max:2048',
            ],
            [
                'barcode.string' => 'يجب أن يكون الباركود سلسلة',
                'product_no.string' => 'يجب أن يكون رقم المنتج سلسلة',
                'product_name.string' => 'يجب أن يكون اسم المنتج سلسلة',
                'buy_price.numeric' => 'يجب أن يكون سعر الشراء عددًا',
                'buy_price.min' => 'يجب أن يكون سعر الشراء أكبر أو يساوي 0',
                'sell_price.numeric' => 'يجب أن يكون سعر البيع عددًا',
                'sell_price.min' => 'يجب أن يكون سعر البيع أكبر أو يساوي 0',
                'quantity.integer' => 'يجب أن يكون الكمية عددًا',
                'quantity.min' => 'يجب أن يكون الكمية أكبر أو يساوي 1',
                'image.image' => 'يجب أن يكون الصورة صورة',
                'image.max' => 'يجب أن يكون حجم الصورة 2048 كيلوبايت'
            ]
        );

            if ($validator->fails()) {
                return response()->json([
                    'error' => 'فشلت عملية التحقق من البيانات',
                    'messages' => $validator->errors()
                ], 422);
            }

            $product = TemporaryInput::findOrFail($id);
            $validatedData = $validator->validated();

            if ($request->hasFile('image')) {
                if ($product->image) {
                    Storage::disk('public')->delete($product->image);
                }

                $imageName = Str::random(40) . '.' . $request->file('image')->getClientOriginalExtension();
                $filePath = 'products/image';
                Storage::disk('public')->putFileAs($filePath, $request->file('image'), $imageName);
                $validatedData['image'] = $filePath . '/' . $imageName;
            }

            $product->update([
                'barcode' => $validatedData['barcode'] ?? $product['barcode'],
                'product_no' => $validatedData['product_no'] ?? $product['product_no'],
                'product_name' => $validatedData['product_name'] ?? $product['product_name'],
                'buy_price' => $validatedData['buy_price'] ?? $product['buy_price'],
                'sell_price' => $validatedData['sell_price'] ?? $product['sell_price'],
                'quantity' => $validatedData['quantity'] ?? $product['quantity'],
                'image' => $validatedData['image'] ?? $product['image'],
                'user_id' => auth()->id()
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
            $product = TemporaryInput::findOrFail($id);
            
            if ($product->image) {
                Storage::disk('public')->delete($product->image);
            }

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
     * حذف منتجات عده بناءا على ids
     */
    public function destroyMultiple(Request $request)
    {
        try {
            $ids = $request->input('ids', []);

            if (empty($ids)) {
                return response()->json(['error' => 'لم يتم تحديد أي منتجات'], 400);
            }

            $products = TemporaryInput::whereIn('id', $ids)->get();

            foreach ($products as $product) {
                if ($product->image) {
                    Storage::disk('public')->delete($product->image);
                }
                $product->delete();
            }

            return response()->json(['message' => 'تم حذف المنتجات المحددة بنجاح'], 200);
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
            // جلب جميع السجلات أولًا
            $products = TemporaryInput::all();

            TemporaryInput::query()->delete();
            
            return response()->json(['message' => 'تم حذف جميع المنتجات بنجاح'], 200);
        } catch (Exception $e) {
            return response()->json([
                'error' => 'حدث خطأ أثناء العملية',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
