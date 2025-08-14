<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;
use Storage;
use Validator;

class ProductController extends Controller
{
    // get products for searching when make output invoice
    public function getSearchingProducts(Request $request)
    {
        $query = strtoupper($request->input('query'));

        $products = Product::where('state', 1)
            ->where(function ($q) use ($query) {
                $q->whereRaw('Upper(product_no) LIKE ?', ["%{$query}%"])
                ->orWhereRaw('Upper(barcode) LIKE ?', ["%{$query}%"]);
            })
            ->paginate(10);

        $formattedProducts = [];

        foreach ($products as $product) {
            $formattedProducts[] = [
                'product_id' => $product->id,
                'barcode' => $product->barcode,
                'product_no' => $product->product_no,
                'product_name' => $product->product_name,
            ];
        }

        return response()->json($formattedProducts, 200);
    }


    // fetch products depend on ids
    public function getProducts($ids)
    {
        // إزالة الأقواس المربعة وتحويل النص إلى مصفوفة من الأرقام
        $idsArray = explode(',', str_replace(['[', ']', ' '], '', $ids));

        // جلب المنتجات باستخدام معرّفات المنتجات
        $products = Product::whereIn('id', $idsArray)->get();

        // تحديد الأعمدة المراد إرجاعها فقط
        $customizedProducts = $products->map(fn($product) => [
                'id' => $product->id,
                'barcode' => $product->barcode,
                'product_no' => $product->product_no,
                'product_name' => $product->product_name,
                'sell_price' => $product->sell_price,
                'image' => $product->image,
            ]);

        // إرجاع المنتجات كـ JSON
        return response()->json($customizedProducts);
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = $request->input('search');

        $productsQuery = Product::where('state', 1);

        if (!empty($query)) {
            $query = strtoupper($query);
            $productsQuery->whereRaw('UPPER(product_no) LIKE ?', ['%' . $query . '%']); // البحث في بداية النص فقط
        }

        $products = $productsQuery->paginate(20);

        $data = [
            'products' => $products->items(),
            'pages_number' => $products->lastPage(),
        ];

        return response()->json($data, 200);
    }


    // fetch the information of product depend on product number
    public function show(string $product_no)
    {
        $product = Product::where('barcode', $product_no)->orWhere('product_no', $product_no)->first();
        if (!$product) {
            return response()->json(['message' => 'this product is not available'], 404);
        }

        $data = [
            'id' => $product->id,
            'barcode' => $product->barcode,
            'product_no' => $product->product_no,
            'name' => $product->product_name,
            'price' => $product->sell_price,
            'quantity' => $product->quantity,
            'image' => $product->image
        ];

        return response()->json($data);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $validator = Validator::make($request->all(), [
            'date' => 'nullable|date',
            'invoice_no' => 'nullable|integer|min:0',
            'company_id' => 'nullable|integer|exists:companies,id',
            'barcode' => 'nullable|string',
            'product_no' => 'nullable|string',
            'product_name' => 'nullable|string',
            'buy_price' => 'nullable|numeric|min:0',
            'sell_price' => 'nullable|numeric|min:0',
            'quantity' => 'nullable|integer|min:0',
            'image' => 'nullable|string',
        ], [
            'date.date' => 'يجب ان يكون التاريخ صحيحا',
            'invoice_no.integer' => 'يجب ان يكون رقم الفاتورة عدد صحيح',
            'company_id.integer' => 'يجب ان يكون الرقم صحيحا',
            'company_id.exists' => 'يجب ان تكون الشركة موجودة',
            'barcode.string' => 'يجب ان يكون الباركود نصا',
            'product_no.string' => 'يجب ان يكون رقم المنتج نصا',
            'product_name.string' => 'يجب ان يكون اسم المنتج نصا',
            'buy_price.numeric' => 'يجب ان يكون سعر الشراء رقميا',
            'buy_price.min' => 'يجب ان يكون سعر الشراء أكبر من 0',
            'sell_price.numeric' => 'يجب ان يكون سعر البيع رقميا',
            'sell_price.min' => 'يجب ان يكون سعر البيع أكبر من 0',
            'quantity.integer' => 'يجب ان يكون الكمية رقميا',
            'quantity.min' => 'يجب ان يكون الكمية أكبر من 0',
            'image.string' => 'يجب ان تكون الصورة نصا',
        ]);

        // التحقق من صحة البيانات وإرجاع الأخطاء إذا وجدت
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // جلب المنتج من قاعدة البيانات
        $product = Product::findOrFail($id);
        $validatedData = $validator->validated();

        // تحويل التاريخ إذا كان موجودًا
        if (!empty($validatedData['date'])) {
            $validatedData['date'] = date('Y-m-d', strtotime($validatedData['date']));
        }

        if ($validatedData['quantity'] > 0){
            $validatedData['state'] = "available";
        }else{
            $validatedData['state'] = "out_of_stock";
        }

        if ($validatedData['increase_QNT']){
            $validatedData['quantity'] = $product->quantity + $validatedData['quantity'];
        }

        // تحديث البيانات
        $product->update([
            'date' => $validatedData['date'] ?? $product->date,
            'invoice_no' => $validatedData['invoice_no'] ?? $product->invoice_no,
            'company_id' => $validatedData['company_id'] ?? $product->company_id,
            'barcode' => $validatedData['barcode'] ?? $product->barcode,
            'product_no' => $validatedData['product_no'] ?? $product->product_no,
            'product_name' => $validatedData['product_name'] ?? $product->product_name,
            'buy_price' => $validatedData['buy_price'] ?? $product->buy_price,
            'sell_price' => $validatedData['sell_price'] ?? $product->sell_price,
            'quantity' => $validatedData['quantity'] ?? $product->quantity,
            'packing' => $validatedData['packing'] ?? $product->packing,
            'note' => $validatedData['note'] ?? $product->note,
            'image' => $validatedData['image'] ?? $product->image,
            'state' => $validatedData['state'],
        ]);

        return response()->json(['message' => 'تم تعديل المنتج بنجاح'], 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $product=Product::findOrFail($id);
        
        $imagePath = $product->image;
        if ($imagePath) {
            Storage::disk('public')->delete($imagePath);
        }

        $product->delete();
        return response()->json(['message','تم حذف المنتج بنجاح'],200);
    }
}
