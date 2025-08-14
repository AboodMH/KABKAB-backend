<?php

namespace App\Http\Controllers;

use App\Models\Exchange;
use App\Models\Input;
use App\Models\InputInvoice;
use App\Models\Output;
use App\Models\OutputInvoice;
use App\Models\Payment;
use App\Models\Product;
use DB;
use Exception;
use Illuminate\Http\Request;

class ExchangeAndReturnOutputController extends Controller
{
    public function exchangeProduct(Request $request)
    {
        $validated = $request->validate([
            'invoice_no' => 'nullable|numeric|exists:output_invoices,invoice_no',

            'returned_products' => 'required|array',
            'returned_products.*.product_number' => 'required|string',
            'returned_products.*.quantity' => 'required|integer|min:1',

            'new_products' => 'required|array',
            'new_products.*.product_number' => 'required|string',
            'new_products.*.price' => 'required|numeric|min:0',
            'new_products.*.quantity' => 'required|integer|min:1',

            'note' => 'nullable|string',
            'discount' => 'nullable|numeric',

            'payments' => 'required|array|min:1',
            'payments.*.method' => 'required|in:cash,card,click',
            'payments.*.amount_paid' => 'required|numeric|min:0',
        ]);

        DB::beginTransaction();
        try {

            // === 1. إنشاء فاتورة الإدخال ===
            $inputInvoice = InputInvoice::create([
                'invoice_no' => InputInvoice::max('invoice_no') + 1,
                'date' => now(),
                'value' => 0,
                'quantity' => 0,
                'note' => $validated['note'] ?? '',
                'discount' => 0,
                'discount_type' => 'fixed',
                'type' => 'exchange',
                'user_id' => auth()->id()
            ]);

            $inputTotalQty = 0;
            $inputTotalValue = 0;

            $previousOutputInvoice = OutputInvoice::where('invoice_no', $validated['invoice_no'])->first();

            foreach ($validated['returned_products'] as $item) {
                $product = Product::where('barcode', $item['product_number'])
                ->orWhere('product_no', $item['product_number'])
                ->first();

                Input::create([
                    'input_invoice_id' => $inputInvoice->id,
                    'product_id' => $product->id,
                    'quantity' => $item['quantity'],
                ]);

                $product->quantity += $item['quantity'];
                $product->state = $product->quantity > 0 ? 'available' : 'out_of_stock';
                $product->save();

                $inputTotalQty += $item['quantity'];
                $inputTotalValue += $product->sell_price * $item['quantity'];
            }

            $inputInvoice->quantity = $inputTotalQty;
            $inputInvoice->value = $inputTotalValue;
            $inputInvoice->save();


            $discount = $validated['discount'] ?? 0;

            $discount > 0 && $discount < 1 ? $discountType = 'percentage' : $discountType = 'fixed';

            // === 2. إنشاء فاتورة الإخراج ===
            $outputInvoice = OutputInvoice::create([
                'invoice_no' => OutputInvoice::max('invoice_no') + 1,
                'date' => now(),
                'value' => 0,
                'quantity' => 0,
                'note' => $validated['note'] ?? '',
                'discount' => $discount,
                'discount_type' => $discountType,
                'type' => 'exchange',
                'user_id' => auth()->id()
            ]);

            $outputTotalQty = 0;
            $outputTotalValue = 0;

            foreach ($validated['new_products'] as $item) {
                $product = Product::where('barcode', $item['product_number'])
                ->orWhere('product_no', $item['product_number'])
                ->first();

                if ($product->quantity < $item['quantity']) {
                    throw new Exception("الكمية غير متوفرة للمنتج: {$product->product_name}");
                }

                Output::create([
                    'output_invoice_id' => $outputInvoice->id,
                    'product_id' => $product->id,
                    'price' => $item['price'],
                    'quantity' => $item['quantity'],
                ]);

                $product->quantity -= $item['quantity'];
                $product->state = $product->quantity > 0 ? 'available' : 'out_of_stock';
                $product->save();

                $outputTotalQty += $item['quantity'];
                $outputTotalValue += $item['price'] * $item['quantity'];
            }

            $discountAmount = ($discountType === 'percentage') ? ($outputTotalValue * $discount) : $discount;
            $finalValue = max(0, $outputTotalValue - $discountAmount);

            $outputInvoice->quantity = $outputTotalQty;
            $outputInvoice->value = $finalValue;
            $outputInvoice->save();

            // === 3. إنشاء سجل الربط في exchange ===
            Exchange::create([
                'previous_output_invoice_no' => $previousOutputInvoice->id ?? null,
                'input_invoice_no' => $inputInvoice->id,
                'output_invoice_no' => $outputInvoice->id,
                'note' => $validated['note'] ?? '',
                'user_id' => auth()->id()
            ]);

            // === 4. إنشاء الدفعات ===
            $diff = abs(round($finalValue - $inputTotalValue, 2));
            $totalAmountPaid = array_sum(array_column($validated['payments'], 'amount_paid'));

            foreach ($validated['payments'] as $pay) {
                $change = 0;
                $isCash = $pay['method'] == 'cash';
                if ($isCash && $totalAmountPaid > $diff) {
                    $change = $totalAmountPaid - $diff;
                }
                $amount = $pay['amount_paid'] - $change;

                Payment::create([
                    'type' => $diff >= 0 ? 'inside' : 'outside',
                    'method' => $pay['method'],
                    'amount' => $amount,
                    'amount_paid' => $pay['amount_paid'],
                    'change' => $change,
                    'invoice_no' => $outputInvoice->id,
                    'invoice_type' => 'output',
                    'note' => 'دفع عملية استبدال'
                ]);
            }

            DB::commit();

            return response()->json([
                'message' => 'تمت عملية الاستبدال بنجاح.',
                'input_invoice_no' => $inputInvoice->id,
                'output_invoice_no' => $outputInvoice->id,
                'paid_difference' => $diff
            ]);
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'فشلت عملية الاستبدال.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function returnProduct(Request $request)
    {
        $validated = $request->validate([
            'returned_products' => 'required|array|min:1',
            'returned_products.*.product_no' => 'required|string|exists:products,product_no',
            'returned_products.*.price' => 'required|numeric|min:0',
            'returned_products.*.quantity' => 'required|integer|min:1',
            'note' => 'nullable|string',
        ]);

        DB::beginTransaction();

        try {
            // إنشاء رقم الفاتورة الجديد
            $invoiceNo = InputInvoice::max('invoice_no') + 1;

            $discount = $validated['discount'] ?? 0;

            $discount > 0 && $discount < 1 ? $discountType = 'percentage' : $discountType = 'fixed';

            // إنشاء فاتورة الإدخال (الإرجاع)
            $inputInvoice = InputInvoice::create([
                'invoice_no' => $invoiceNo,
                'date' => now(),
                'value' => 0,
                'quantity' => 0,
                'note' => $validated['note'] ?? '',
                'type' => 'return',
                'user_id' => auth()->id()
            ]);

            $totalQty = 0;
            $totalValue = 0;

            // تحديث كمية المنتجات وإضافة بيانات الإدخال
            foreach ($validated['returned_products'] as $item) {
                $product = Product::where('product_no', $item['product_no'])->firstOrFail();

                $output = Output::where('product_id', $product->id)->count();

                if ($item['quantity'] > $output) {
                    throw new Exception("الكمية المرجعة أكبر من الكمية المباعة للمنتج: {$product->product_no}");
                }

                Input::create([
                    'input_invoice_id' => $inputInvoice->id,
                    'product_id' => $product->id,
                    'quantity' => $item['quantity'],
                ]);

                $product->quantity += $item['quantity'];
                $product->state = $product->quantity > 0 ? 'available' : 'out_of_stock';
                $product->save();

                $totalQty += $item['quantity'];
                $totalValue += $product->buy_price * $item['quantity'];
            }

            // تحديث بيانات الفاتورة
            $inputInvoice->quantity = $totalQty;
            $inputInvoice->value = $totalValue;
            $inputInvoice->save();

            // تسجيل دفعة الاسترداد
            Payment::create([
                'type' => 'outside ',
                'method' => 'cash',
                'amount' => $item['price'],
                'amount_paid' => 0,
                'change' => 0,
                'invoice_no' => $inputInvoice->id,
                'invoice_type' => 'input',
                'note' => 'استرداد من عملية إرجاع'
            ]);

            DB::commit();

            return response()->json([
                'message' => 'تمت عملية الإرجاع بنجاح.',
                'invoice_no' => $invoiceNo,
                'total' => $totalValue,
            ]);
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'فشلت عملية الإرجاع.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }


}
