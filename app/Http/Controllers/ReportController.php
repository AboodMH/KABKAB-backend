<?php

namespace App\Http\Controllers;

use App\Models\Withdrawal;
use Illuminate\Http\Request;
use App\Models\InputInvoice;
use App\Models\OutputInvoice;
use App\Models\Expense;
use Carbon\Carbon;

class ReportController extends Controller
{
    public function getReport(Request $request)
    {
        $type = $request->query('type');
        $date = $request->query('date');

        if (!$type || !$date) {
            return response()->json(['error' => 'النوع أو التاريخ غير موجود'], 400);
        }

        // تحديد بداية ونهاية الفترة حسب نوع التقرير
        if ($type === 'monthly') {
            $start = Carbon::parse($date)->startOfMonth();
            $end = Carbon::parse($date)->endOfMonth();
        } elseif ($type === 'yearly') {
            $start = Carbon::parse($date)->startOfYear();
            $end = Carbon::parse($date)->endOfYear();
        } else {
            return response()->json(['error' => 'نوع التقرير غير صالح'], 400);
        }

        // الحسابات
        $total_input = InputInvoice::whereBetween('date', [$start, $end])->sum('value');
        $total_output = OutputInvoice::whereBetween('date', [$start, $end])->sum('value');
        $total_expense = Expense::whereBetween('date', [$start, $end])->sum('value');
        $total_withdrawl = Withdrawal::whereBetween('date', [$start, $end])->sum('amount');


        // الربح = المخرجات - المدخلات - المصاريف
        $net_profit = $total_input - $total_output - $total_expense;

        return response()->json([
            'from' => $start->toDateString(),
            'to' => $end->toDateString(),
            'total_input' => $total_input,
            'total_output' => $total_output,
            'total_expense' => $total_expense,
            'total_withdrawl' => $total_withdrawl,
            'net_profit' => $net_profit,
        ]);
    }
}
