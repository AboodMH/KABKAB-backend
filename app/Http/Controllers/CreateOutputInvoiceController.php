<?php

namespace App\Http\Controllers;

use App\Models\Output;
use App\Models\OutputInvoice;
use App\Models\Payment;
use App\Models\Product;
use App\Models\TemporaryOutput;
use DB;
use Exception;
use Http;
use Illuminate\Http\Request;
use Validator;

class CreateOutputInvoiceController extends Controller
{
    // data verification and call create output and invoice
    public function createOutputsInvoice(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'value' => 'required|numeric',
            'quantity' => 'required|numeric',
            'note' => 'nullable|string',
            'discount' => 'nullable|numeric',
            'payments' => 'required|array',
            'payments.*.payment_method' => 'required|in:cash,card,click',
            'payments.*.amount_paid' => 'required|numeric|min:0',
            'payments.*.payment_note' => 'nullable|string',
            'with_print' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->messages()], 422);
        }

        $validatedData = $validator->validated();

        DB::beginTransaction();

        try {
            $invoiceData = $this->createInvoice($validatedData);
            $invoiceId = $invoiceData['invoice_id'];
            $invoiceNo = $invoiceData['invoice_no'];

            $outputCreation = $this->createOutputs($invoiceId);

            if (isset($outputCreation['error'])) {
                DB::rollBack();
                return response()->json([
                    'status' => 'error',
                    'message' => 'فشل في إضافة المنتجات إلى الفاتورة',
                    'error' => $outputCreation['error'],
                ], 400);
            }

            // حساب المبلغ الكلي المدفوع
            $totalAmountPaid = array_sum(array_column($validatedData['payments'], 'amount_paid'));

            // حساب الصرف فقط إذا كان الدفع نقدًا وكافيًا
            $change = 0;
            foreach ($validatedData['payments'] as $payment) {
                $isCash = $payment['payment_method'] === 'cash';
                if ($isCash && $totalAmountPaid > $validatedData['value']) {
                    $change = $totalAmountPaid - $validatedData['value'];
                    break;
                }
            }

            $printPayment = [];

            foreach ($validatedData['payments'] as $payment) {
                $paymentsData = [
                    'method' => $payment['payment_method'],
                    'amount_paid' => $payment['amount_paid'],
                    'note' => $payment['payment_note'] ?? null,
                    'output_invoice_id' => $invoiceId,
                    'invoice_type' => 'output',
                    'change' => ($payment['payment_method'] === 'cash') ? $change : 0
                ];

                $printPayment[] = [
                    'payment_method' => $payment['payment_method'],
                    'amount_paid' => $payment['amount_paid'],
                    'payment_note' => $payment['payment_note'] ?? null,

                ];

                $createPayment = $this->createPayment($paymentsData);
                if ($createPayment->getStatusCode() !== 201) {
                    DB::rollBack();
                    return response()->json([
                        'status' => 'error',
                        'message' => 'فشل في إضافة الدفعة',
                        'error' => $createPayment->getData(),
                    ], $createPayment->getStatusCode());
                }
            }

            $printData = [
                'invoice_no' => $invoiceNo,
                'payments' => $printPayment,
                
            ];

            if ($validatedData['with_print']) {
                $printInvoice = $this->printInvoice($printData);
                if ($printInvoice->getStatusCode() !== 201) {
                    DB::rollBack();
                    return response()->json([
                        'status' => 'error',
                        'message' => 'فشل في طباعة الفاتورة',
                        'error' => $printInvoice->getData(),
                    ], $printInvoice->getStatusCode());
                }
            } else {
                TemporaryOutput::where('user_id', auth()->id())->delete();
            }

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'تم انشاء الفاتورة بنجاح',
                'invoice_no' => $invoiceNo
            ], 201);

        } catch (Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }


    // create outputs invoice
    public function createInvoice($validatedData)
    {
        $validatedData['date'] = now();

        $validatedData['user_id'] = auth()->id();


        $validatedData["note"] ??= "لايوجد ملاحظة";

        
        $validatedData['discount'] ??= 0;

        // تعيين نوع الخصم اما نسبة او قيمة ثابتة
        $validatedData['discount'] > 0 && $validatedData['discount'] < 1 ? $validatedData['discount_type'] = "percentage" : $validatedData['discount_type'] = "fixed";


        $validatedData['invoice_no'] = $this->getInvoiceNo();

        // حفظ الفاتورة في قاعدة البيانات
        $invoice = OutputInvoice::create($validatedData);

        return [
            'invoice_id' => $invoice->id,
            'invoice_no' => $invoice->invoice_no
        ];
    }


    // fetch products from temporary outputs and create outputs
    public function createOutputs($invoiceId)
    {
        $getProductsData = $this->getProductsData($invoiceId);

        // التحقق مما إذا كانت هناك مشكلة في جلب البيانات
        if (isset($getProductsData['error'])) {
            return ['error' => $getProductsData['error']];
        }

        // التأكد من أن البيانات موجودة لمنع الأخطاء
        $invoiceId = $getProductsData['output_invoice_id'] ?? null;
        $productsData = $getProductsData['products'] ?? [];


        $validator = Validator::make([
            'output_invoice_id' => $invoiceId,
            'products' => $productsData,
        ], [
            'output_invoice_id' => 'required|numeric|exists:output_invoices,id',
            'products' => 'required|array',
            'products.*.product_id' => 'required|integer|exists:products,id',
            'products.*.price' => 'required|numeric|min:0',
            'products.*.quantity' => 'required|integer|min:1',
        ],[
            'output_invoice_id.required' => 'رقم الفاتورة مطلوب',
            'output_invoice_id.numeric' => 'رقم الفاتورة يجب أن يكون عدد',
            'output_invoice_id.exists' => 'رقم الفاتورة غير موجود',
            'products.required' => 'المنتجات مطلوبة',
            'products.array' => 'المنتجات يجب أن تكون مصفوفة',
            'products.*.product_id.required' => 'رقم المنتج مطلوب',
            'products.*.product_id.integer' => 'رقم المنتج يجب أن يكون عدد',
            'products.*.product_id.exists' => 'رقم المنتج غير موجود',
            'products.*.price.required' => 'السعر مطلوب',
            'products.*.price.numeric' => 'يجب ان يكون السعر رقما',
            'products.*.quantity.required' => 'الكمية مطلوبة',
            'products.*.quantity.integer' => 'الكمية يجب أن تكون عدد',
            'products.*.quantity.min' => 'الكمية يجب أن تكون أكبر أو تساوي 1',
        ]);

        if ($validator->fails()) {
            return ['error' => $validator->errors()];
        }


        $outputValidatedData = $validator->validated();
        $products = [];

        $allProducts = Product::whereIn('id', collect($outputValidatedData['products'])->pluck('product_id'))->get()->keyBy('id');

        foreach ($outputValidatedData['products'] as $productData) {

            $product = $allProducts[$productData['product_id']] ?? null;

            if (!$product) {
                return ['error' => 'المنتج غير موجود'];
            }

            // تحقق من الكمية
            if ($product->quantity < $productData['quantity']) {
                return ['error' => "الكمية غير كافية للمنتج {$product->name}. المتوفر: {$product->quantity}"];
            }

            // خصم الكمية وتحديث الحالة إذا لزم
            if ($product->product_no !== 'NON-000') {
                $product->quantity -= $productData['quantity'];
                if ($product->quantity == 0) {
                    $product->state = 'out_of_stock';
                }
            }

            $product->save();

            $products[] = [
                'output_invoice_id' => $outputValidatedData['output_invoice_id'],
                'product_id' => $product->id,
                'price' => $productData['price'],
                'quantity' => $productData['quantity'],
                'created_at' => now(),
            ];
        }


        if (!empty($products)) {
            Output::insert($products);
        }

        return ['status' => 'success'];

    }


    public function createPayment($data)
    {
        try {
            $validator = Validator::make([
                'method' => $data['method'],
                'amount_paid' => $data['amount_paid'],
                'note' => $data['note'],
            ], [
                'method' => 'required|in:cash,card,click',
                'amount_paid' => 'required|numeric|min:0',
                'note' => 'nullable|string',
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 400);
            }

            $validatedData = $validator->validated();

            $validatedData['type'] = "inside";
            $validatedData['amount'] = $validatedData['amount_paid'] - ($data['change'] ?? 0);
            $validatedData['output_invoice_id'] = $data['output_invoice_id'];
            $validatedData['change'] = $data['change'] ?? 0;
            $validatedData['user_id'] = auth()->id();
            $validatedData['is_deported'] = false;

            Payment::create($validatedData);

            return response()->json(['message' => 'تم إضافة الدفعة بنجاح'], 201);

        } catch (Exception $e) {
            return response()->json(['error' => 'حدث خطأ أثناء إضافة الدفعة: ' . $e->getMessage()], 500);
        }
    }


    public function printInvoice($data)
    {
        // تحقق من البيانات الواردة
        $validator = Validator::make([
            "invoice_no" => $data['invoice_no'],
            "payments" => $data['payments']
        ], [
            'invoice_no' => 'required|numeric|exists:output_invoices,invoice_no',
            'payments' => 'required|array',
            'payments.*.payment_method' => 'required|in:cash,card,click',
            'payments.*.amount_paid' => 'required|numeric|min:0',
            'payments.*.payment_note' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'رقم الفاتورة مطلوب ويجب أن يكون عددًا صحيحًا',
                'errors' => $validator->errors()
            ], 422);
        }

        $validatedData = $validator->validated();

        try {
            // هنا عدّل ليجلب بيانات الفاتورة من قاعدة البيانات أو مصدر البيانات
            // المثال التالي بيانات ثابتة للتوضيح

            $invoiceNo = $validatedData['invoice_no'];


            $cashier = auth()->user()->name;


            // بيانات الفاتورة
            $date_time = now()->format('Y-m-d H:i');

            // المنتجات المرتبطة بالفاتورة
            $temporaryOutputs = TemporaryOutput::where('user_id', auth()->id())->get()->keyBy('product_id');

            // جلب معرفات المنتجات فقط
            $productIds = $temporaryOutputs->pluck('product_id')->all();

            // جلب المنتجات من قاعدة البيانات
            $products = Product::whereIn('id', $productIds)->get()->keyBy('id');

            $items = [];

            foreach ($products as $id => $product) {
                $output = $temporaryOutputs[$id] ?? null;

                $items[] = [
                    'product' => "{$product['product_no']} {$product['product_name']}",
                    'qty' => $output ? $output->quantity : 1,
                    'price' => $output['price']
                ];
            }


            // المدفوعات المرتبطة بالفاتورة
            $payments = $validatedData['payments'];

            // تأكد أن هناك مدفوعات صالحة (مثلاً المجموع أكبر من صفر)
            $totalPaid = array_reduce($payments, function ($sum, $p) {
                return $sum + ($p['amount_paid'] ?? 0);
            }, 0);

            if ($totalPaid <= 0) {
                return response()->json([
                    'message' => 'يجب أن يكون هناك مبلغ مدفوع صالح للطباعة'
                ], 422);
            }

            // تأكد أن المنتجات ليست فارغة
            if (empty($items)) {
                return response()->json([
                    'message' => 'لا توجد منتجات للطباعة في هذه الفاتورة'
                ], 422);
            }

            $dataToPrint = [
                "invoice_type" => "sell",
                'invoice_no' => $invoiceNo,
                'cashier' => $cashier,
                'date_time' => $date_time,
                'items' => $items,
                'payments' => $payments,
            ];

            $pythonServerUrl = 'http://127.0.0.1:9000/print';

            $response = Http::post($pythonServerUrl, $dataToPrint);

            if ($response->successful()) {
                TemporaryOutput::where('user_id', auth()->id())->delete();

                return response()->json(['message' => 'تم إرسال الفاتورة للطباعة'], 201);
            } else {
                return response()->json(['message' => 'فشل في إرسال الفاتورة للطباعة'], 500);
            }
        } catch (Exception $e) {
            return response()->json([
                'message' => 'خطأ في الاتصال بسيرفر الطباعة',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    // fertch products from temporary outputs
    public function getProductsData($invoiceId)
    {

        if (!$invoiceId) {
            return ['error' => 'لا يوجد رقم فاتورة متاح'];
        }
    
        // جلب جميع المنتجات المؤقتة
        $temporaryOutputs = TemporaryOutput::where('user_id', auth()->id())->get();
    
        if ($temporaryOutputs->isEmpty()) {
            return ['error' => 'لا توجد منتجات مؤقتة'];
        }

        $products = $temporaryOutputs->map(function ($product) {
            return [
                'product_id' => $product->product_id,
                'price' => $product->price,
                'quantity' => $product->quantity,
            ];
        })->toArray();

        return [
            'output_invoice_id' => $invoiceId,
            'products' => $products,
        ];
    }


    // fetch invoice number
    public function getInvoiceNo()
    {
        return DB::transaction(function () {
            $latestInvoiceNo = OutputInvoice::lockForUpdate()->max('invoice_no');
            return $latestInvoiceNo ? $latestInvoiceNo + 1 : 1;
        });
    }

    
}
