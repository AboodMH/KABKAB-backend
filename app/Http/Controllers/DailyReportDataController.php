<?php

namespace App\Http\Controllers;


use App\Models\Expense;
use App\Models\OutputInvoice;
use App\Models\Payment;
use App\Models\Withdrawal;



class DailyReportDataController extends Controller
{
    public function getReportData()
    {
        $userId = auth()->id();

        $payments_cash = Payment::
            where('user_id', $userId)
            ->where('is_deported', 0)
            ->where('type', 'inside')
            ->where('method', 'cash')
            ->get();

        $payments_card = Payment::
            where('user_id', $userId)
            ->where('is_deported', 0)
            ->where('type', 'inside')
            ->where('method', '!=', value: 'cash')
            ->get();

        $outside_payments = Payment::
            where('user_id', auth()->id())
            ->where('is_deported', 0)
            ->where('type', 'outside')
            ->get();

        $expenses = Expense::
            where('user_id', $userId)
            ->where('is_deported', 0)
            ->get();


        $withdrawals = Withdrawal::
            where('user_id', $userId)
            ->where('is_deported', 0)
            ->get();

        $data = [
            'cash' => $payments_cash->sum(fn($p) => $p->amount),
            'card' => $payments_card->sum(fn($p) => $p->amount),
            'outside' => $outside_payments->sum(fn($p) => $p->amount),
            'expense' => $expenses->sum(fn($p) => $p->amount),
            'withdrawal' => $withdrawals->sum(fn($p) => $p->amount),
        ];

        return response()->json([
            'data' => $data,
        ]);
    }

    public function deportation($id){
        OutputInvoice::
            where('user_id', auth()->id())
            ->where('is_deported', 0)
            ->update(['is_deported' => 1, 'report_no' => $id]);
        
        Payment::
            where('user_id', auth()->id())
            ->where('is_deported', 0)
            ->update(['is_deported' => 1, 'report_no' => $id]);


        Expense::
            where('user_id', auth()->id())
            ->where('is_deported', 0)
            ->update(['is_deported' => 1, 'report_no' => $id]);

        Withdrawal::
            where('user_id', auth()->id())
            ->where('is_deported', 0)
            ->update(['is_deported' => 1, 'report_no' => $id]);


        return response()->json(['message' => 'تم ترحيل الفواتير بنجاح']);
    }

}
