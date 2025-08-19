<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Input;
use App\Models\InputInvoice;
use App\Models\Product;
use App\Models\TemporaryInput;
use DB;
use Exception;
use Http;
use Illuminate\Http\Request;
use Validator;

class CreateInputInvoiceController extends Controller
{
    // data verification and call create inputs and products and invoice functions
    public function createInputsInvoice(Request $request)
    {       
        $validator = Validator::make($request->all(), [
            'date' => 'nullable|date',
            'company_id' => 'required|numeric|exists:companies,id',
            'value' => 'required|numeric',
            'quantity' => 'required|numeric',
            'note' => 'nullable|string',
            'discount' => 'nullable|numeric',
            'with_print' => 'nullable|boolean',
        ],[
            'company_id.required' => 'يجب عليك اختيار الشركة',
            'company_id.exists' => 'الشركة غير موجودة',
            'value.required' => 'يجب عليك اختيار القيمة',
            'value.numeric' => 'يجب عليك اختيار القيمة',
            'quantity.required' => 'يجب عليك اختيار الكمية',
            'quantity.numeric' => 'يجب عليك اختيار الكمية',
            'note.string' => 'يجب عليك اختيار الملاحظة',
            'discount.required' => 'يجب عليك اختيار الخصم',
            'discount.numeric' => 'يجب عليك اختيار الخصم',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors(),
            ], 422); 
        }

        $validatedData = $validator->validated();

        $validatedData['discount'] ??= 0;

        DB::beginTransaction();

        try {

            $invoiceId = $this->createInvoice($validatedData);

            $company = Company::findOrFail($validatedData['company_id']);
            $company->balance += $validatedData['value'];
            $company->save();


            $productCreation = $this->createProduct($validatedData['with_print'], $invoiceId);
            if (isset($productCreation['error'])) {
                DB::rollBack();
                return response()->json([
                    'status' => 'error',
                    'message' => 'فشل في إنشاء المنتجات',
                    'errors' => $productCreation['error'],
                ], 400);
            }

            $inputCreation = $this->createInputs($invoiceId);
            if (isset($inputCreation['error'])) {
                DB::rollBack();
                return response()->json([
                    'status' => 'error',
                    'message' => 'فشل في إنشاء المدخلات',
                    'errors' => $inputCreation['error'],
                ], 400);
            }

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'تم انشاء الفاتورة بنجاح',
            ], 201);

        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'حدث خطأ اثناء انشاء الفاتورة',
                'error' => $e->getMessage(),
                'line' => $e->getLine(), 
            ], 500);
        }
    }

    // create inputs invoice
    public function createInvoice($validatedData)
    {
        $validatedData['date'] ??= now();
        $validatedData['user_id'] = auth()->id();
        $validatedData["note"] ??= "لايوجد ملاحظة";
        $validatedData['discount_type'] = ($validatedData['discount'] > 0 && $validatedData['discount'] < 1) ? 'percentage' : 'fixed';

        $validatedData['invoice_no'] = $this->getInvoiceNo();

        $invoice = InputInvoice::create($validatedData);
        
        return $invoice->id;
    }

    // get products from temporary inputs and create it
    public function createProduct($with_print, $invoiceId)
    {
        $getProductsData = $this->getProductsData($invoiceId);

        // التحقق مما إذا كانت هناك مشكلة في جلب البيانات
        if (isset($getProductsData['error'])) {
            return response()->json(['error' => $getProductsData['error']], 400);
        }

        // التأكد من أن البيانات موجودة لمنع الأخطاء
        $invoiceId = $getProductsData['input_invoice_id'] ?? null;
        $productsData = $getProductsData['products'] ?? [];

        $validator = Validator::make([
            'input_invoice_id' => $invoiceId,
            'products' => $productsData,
        ], [
            'input_invoice_id' => 'required|numeric|exists:input_invoices,id',
            'products' => 'required|array',
            'products.*.barcode' => 'required|string',
            'products.*.product_no' => 'required|string',
            'products.*.product_name' => 'required|string',
            'products.*.buy_price' => 'required|numeric|min:0',
            'products.*.sell_price' => 'required|numeric|min:0',
            'products.*.quantity' => 'required|integer|min:1',
            'products.*.image' => 'nullable|string',
        ], [
            'input_invoice_id.required' => 'رقم الفاتورة مطلوب',
            'input_invoice_id.numeric' => 'رقم الفاتورة يجب أن يكون عدد',
            'input_invoice_id.exists' => 'رقم الفاتورة غير موجود',            
            'products.required' => 'يجب ادخال المنتجات',
            'products.array' => 'يجب ادخال منتج واحد على الاقل',
            'products.*.barcode.required' => 'يجب ادخال الباركود',
            'products.*.barcode.string' => 'يجب ادخال الباركود بشكل صحيح',
            'products.*.product_no.required' => 'يجب ادخال رقم المنتج',
            'products.*.product_no.string' => 'يجب ادخال رقم المنتج بشكل صحيح',
            'products.*.product_name.required' => 'يجب ادخال اسم المنتج',
            'products.*.product_name.string' => 'يجب ادخال اسم المنتج بشكل صحيح',
            'products.*.buy_price.required' => 'يجب ادخال سعر الشراء',
            'products.*.buy_price.numeric' => 'يجب ادخال سعر الشراء بشكل صحيح',
            'products.*.buy_price.min' => 'يجب ان يكون سعر الشراء اكبر من 0',
            'products.*.sell_price.required' => 'يجب ادخال سعر البيع',
            'products.*.sell_price.numeric' => 'يجب ادخال سعر البيع بشكل صحيح',
            'products.*.sell_price.min' => 'يجب ان يكون سعر البيع اكبر من 0',
            'products.*.quantity.required' => 'يجب ادخال الكميه',
            'products.*.quantity.integer' => 'يجب ادخال الكميه بشكل صحيح',
            'products.*.quantity.min' => 'يجب ان يكون الكميه اكبر من 1',
            'products.*.image.string' => 'يجب ادخال الصوره بشكل صحيح',
        ]);
    
        if ($validator->fails()) {
            return ['error' => $validator->errors()];
        }
    
        $validatedData = $validator->validated();
        $products = [];
        $now = now();


        foreach ($validatedData['products'] as $product) {
            $product['created_at'] = $now;
            $product['state'] = "available";

            $existingProduct = Product::where('product_no', $product['product_no'])->first();

            if ($existingProduct) {
                $existingProduct->quantity += $product['quantity'];
                $existingProduct->state = "available";
                $existingProduct->save();
            } else {
                $products[] = $product;

                $printData = [
                    'price' => $product['sell_price'],
                    'product_name' => $product['product_no'],
                    'barcode' => $product['barcode'],
                    'quantity' => $product['quantity'],
                ];
                
                if ($with_print) {
                    $this->printBarcodeLabel($printData);
                }

            }
        }

        if (!empty($products)) {
            Product::insert($products);
        }

        return ['status' => 'success'];
    }


    // get products from temporary inputs and create inputs
    public function createInputs($invoiceNo)
    {
        $getInputsData = $this->getInputsData($invoiceNo); 

            // التحقق مما إذا كانت هناك مشكلة في جلب البيانات
        if (isset($getInputsData['error'])) {
            return response()->json(['error' => $getInputsData['error']], 400);
        }

        // التأكد من أن البيانات موجودة لمنع الأخطاء
        $inputsData = $getInputsData['inputs'] ?? [];

        $validator = Validator::make([
            'inputs' => $inputsData
        ], [
            'inputs' => 'required|array',
            'inputs.*.input_invoice_id' => 'required|numeric|exists:input_invoices,id',
            'inputs.*.product_id' => 'required|numeric|exists:products,id',
            'inputs.*.quantity' => 'required|integer|min:1',
            'inputs.*.created_at' => 'required'
        ],[
            'inputs.required' => 'قائمة المدخلات مطلوبة',
            'inputs.array' => 'قائمة المدخلات يجب أن تكون مصفوفة',
            'inputs.*.input_invoice_id.required' => 'رقم الفاتورة مطلوب',
            'inputs.*.input_invoice_id.numeric' => 'رقم الفاتورة يجب أن يكون رقم',
            'inputs.*.input_invoice_id.exists' => 'رقم الفاتورة غير موجود',
            'inputs.*.product_id.required' => 'رقم المنتج مطلوب',
            'inputs.*.product_id.numeric' => 'رقم المنتج يجب أن يكون عدد',  
            'inputs.*.product_id.exists' => 'المنتج غير موجود',
            'inputs.*.quantity.required' => 'الكمية مطلوبة',
            'inputs.*.quantity.integer' => 'الكمية يجب أن تكون عدد',
            'inputs.*.quantity.min' => 'الكمية يجب أن تكون أكبر من 1',
            'inputs.*.created_at.required' => 'تاريخ الإنشاء مطلوب',
        ]);

        if ($validator->fails()) {
            return ['error' => $validator->errors()];
        }

        $validatedData = $validator->validated();
            
        Input::insert($validatedData['inputs']);

        
        app(TemporaryInputController::class)->clearAll();

        return ['status' => 'success'];
    }


    // create barcode label and print it
    public function printBarcodeLabel($data)
    {
        try {
            $pythonServerUrl = 'http://192.168.0.101:5005/print-label';

            $response = Http::post($pythonServerUrl, $data);

        } catch (Exception $e) {
            return response()->json([
                'message' => 'خطأ في الاتصال بسيرفر الطباعة',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    // bring number of invoice
    public function getInvoiceNo()
    {
        return DB::transaction(function () {
            $latestInvoiceNo = InputInvoice::lockForUpdate()->max('invoice_no');
            return $latestInvoiceNo ? $latestInvoiceNo + 1 : 1;
        });
    }


    // bring essential data for inputs from temporary inputs
    public function getInputsData($invoiceId)
    {

        if (!$invoiceId) {
            return response()->json(['error' => 'لا يوجد رقم فاتورة متاح'], 400);
        }

        // جلب جميع المنتجات المؤقتة
        $temporaryData = TemporaryInput::get();

        $productNumbers = $temporaryData->pluck('product_no')->toArray();

        // جلب المنتجات دفعة واحدة باستخدام whereIn
        $products = Product::whereIn('product_no', $productNumbers)->get()->keyBy('product_no');

        $inputData = [];
        $now = now();
        foreach ($temporaryData as $item) {
            if (isset($products[$item->product_no])) {
                $inputData[] = [
                    "input_invoice_id" => $invoiceId,
                    'product_id' => $products[$item->product_no]->id,
                    'quantity' => $item->quantity,
                    'created_at' => $now,
                ];
            }

        }

        return [
            'inputs' => $inputData
        ];
    }


    // bring products data from temporary inputs
    public function getProductsData($invoiceId)
    {
        if (!$invoiceId) {
            return ['error' => 'لا يوجد رقم فاتورة متاح'];
        }
    
        // جلب جميع المنتجات المؤقتة
        $temporaryProducts = TemporaryInput::get();
    
        if ($temporaryProducts->isEmpty()) {
            return ['error' => 'لا توجد منتجات مؤقتة'];
        }

        $products = $temporaryProducts->map(function ($product) {
            return [
                'barcode' => $product->barcode,
                'product_no' => $product->product_no,
                'product_name' => $product->product_name,
                'buy_price' => $product->buy_price,
                'sell_price' => $product->sell_price,
                'quantity' => $product->quantity,
                'note' => $product->note,
                'image' => $product->image,
            ];
        })->toArray();

        return [
            'input_invoice_id' => $invoiceId,
            'products' => $products,
        ];
    }
}
