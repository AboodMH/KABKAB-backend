<?php

namespace App\Http\Controllers;

use App\Models\DailyReport;
use App\Models\OutputInvoice;
use Carbon\Carbon;
use DB;
use Exception;
use Illuminate\Http\Request;

class DailyReportController extends Controller
{
    // عرض قائمة التقارير اليومية
    public function index()
    {
        $reports = DailyReport::paginate(25);
        return response()->json($reports);
    }

    // تخزين تقرير جديد
    public function store(Request $request)
    {
        $userId = auth()->id();

        $hasInvoicesToDeport = OutputInvoice::where('user_id', $userId)
            ->where('is_deported', 0)
            ->exists();

        if (! $hasInvoicesToDeport) {
            return response()->json([
                'message' => 'لا توجد فواتير بحاجة إلى الترحيل. لا يمكن إنشاء تقرير يومي.',
            ], 400);
        }

        $validated = $request->validate([
            'date' => 'required|date|unique:daily_reports,date,NULL,id,user_id,' . $userId,
            'shift' => 'required|in:morning,evening,full',
            'cash' => 'required|integer|min:0',
            'card' => 'required|integer|min:0',
            'outside' => 'required|integer|min:0',
            'expense' => 'required|integer|min:0',
            'withdrawal' => 'required|integer|min:0',
            'amount_in_box' => 'required|numeric|min:0',
            'difference' => 'required|numeric',
            'note' => 'nullable|string',
        ]);

        $validated['user_id'] = $userId;
        $validated['outside'] = $validated['outside'] ?? 0;
        $validated['expense'] = $validated['expense'] ?? 0;
        $validated['withdrawal'] = $validated['withdrawal'] ?? 0;

        try {
            DB::beginTransaction();

            // إنشاء التقرير
            $report = DailyReport::create($validated);

            // ترحيل الفواتير
            app(DailyReportDataController::class)->deportation($report->id);

            DB::commit();

            return response()->json([
                'message' => 'تم إنشاء التقرير بنجاح',
                'data' => $report
            ], 201);

        } catch (Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'حدث خطأ أثناء إنشاء التقرير: ' . $e->getMessage(),
            ], 500);
        }
    }

    // عرض تقرير معين
    public function show($id)
    {
        $report = DailyReport::findOrFail($id);
        return response()->json($report);
    }

    // تحديث تقرير
    public function update(Request $request, $id)
    {
        $report = DailyReport::findOrFail($id);

        $validated = $request->validate([
            'date' => 'nullable|date',
            'shift' => 'nullable|in:morning,evening,full',
            'cash' => 'nullable|integer|min:0',
            'card' => 'nullable|integer|min:0',
            'outside' => 'nullable|integer|min:0',
            'expense' => 'nullable|integer|min:0',
            'withdrawal' => 'nullable|integer|min:0',
            'amount_in_box' => 'nullable|numeric|min:0',
            'difference' => 'nullable|numeric',
            'note' => 'nullable|string',
            'user_id' => 'nullable|exists:users,id',
        ]);

        $report->update([
            'date'          => $validated['date'] ?? $report->date,
            'shift'         => $validated['shift'] ?? $report->shift,
            'cash'          => $validated['cash'] ?? $report->cash,
            'card'          => $validated['card'] ?? $report->card,
            'outside'       => $validated['outside'] ?? $report->refund,
            'expense'       => $validated['expense'] ?? $report->expense,
            'withdrawal'    => $validated['withdrawal'] ?? $report->withdrawal,
            'amount_in_box' => $validated['amount_in_box'] ?? $report->amount_in_box,
            'difference'    => $validated['difference'] ?? $report->difference,
            'note'          => $validated['note'] ?? $report->note,
            'user_id'       => $validated['user_id'] ?? $report->user_id,
        ]);

        return response()->json([
            'message' => 'تم تحديث التقرير بنجاح',
            'data' => $report
        ]);
    }

    // حذف تقرير
    public function destroy($id)
    {
        $report = DailyReport::findOrFail($id);
        $report->delete();

        return response()->json([
            'message' => 'تم حذف التقرير بنجاح'
        ]);
    }

    // عرض قائمة التقارير اليومية حسب الشهر
    public function getReportsByMonth(Request $request)
    {
        $year = $request->input('year', Carbon::now()->year);
        $month = $request->input('month', Carbon::now()->month);

        $reports = DailyReport::whereYear('date', $year)
                            ->whereMonth('date', $month)
                            ->get();

        if ($reports->isEmpty()) {
            return response()->json([
                'message' => 'لا توجد تقارير لهذا الشهر',
                'year' => $year,
                'month' => $month,
                'reports' => []
            ], 200);
        }

        return response()->json($reports, 200);
    }
}
