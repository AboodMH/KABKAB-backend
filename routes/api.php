<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\BarcodePrintController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\CompanyPaymentController;
use App\Http\Controllers\CreateInputInvoiceController;
use App\Http\Controllers\CreateOutputInvoiceController;
use App\Http\Controllers\DailyReportController;
use App\Http\Controllers\DailyReportDataController;
use App\Http\Controllers\ExchangeAndReturnOutputController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\InputController;
use App\Http\Controllers\InputInvoiceController;
use App\Http\Controllers\OutputController;
use App\Http\Controllers\OutputInvoiceController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\StaffController;
use App\Http\Controllers\TemporaryInputController;
use App\Http\Controllers\TemporaryOutputController;
use App\Http\Controllers\WithdrawlController;
use Illuminate\Support\Facades\Route;


Route::post('/register', [AuthController::class, 'register']);

Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/profile', [AuthController::class, 'profile']);
    
    Route::post('/logout', [AuthController::class, 'logout']);
    
});


// حماية مسارات الشركة والدفع باستخدام Sanctum
Route::middleware('auth:sanctum')->group(function () {

    Route::get('/company/payment/{date}', [CompanyPaymentController::class, 'getDataByMonth']);

    Route::apiResource('/company/payment', CompanyPaymentController::class);

    Route::apiResource('/company', CompanyController::class);

    Route::get('/company/details/{id}', [CompanyController::class, 'showDetails']);
});



// حماية مسارات المنتجات المؤقتة باستخدام Sanctum
Route::middleware('auth:sanctum')->group(function () {
    // عرض جميع المنتجات المؤقتة
    Route::get('/temporary/input', [TemporaryInputController::class, 'index']);
    
    // إضافة منتج مؤقت جديد
    Route::post('/temporary/input', [TemporaryInputController::class, 'store']);
    
    // تعديل منتج مؤقت معين
    Route::put('/temporary/input/{id}', [TemporaryInputController::class, 'update']);
    
    // حذف العديد من المنتجات
    Route::delete('/temporary/input/multideletion', [TemporaryInputController::class, 'destroyMultiple']);

    // حذف منتج مؤقت معين
    Route::delete('/temporary/input/{id}', [TemporaryInputController::class, 'destroy']);
    
    // حذف جميع المنتجات المؤقتة
    Route::delete('/temporary/input', [TemporaryInputController::class, 'clearAll']); // لحذف الكل
});



// حماية مسارات المنتجات المؤقتة للإخراج باستخدام Sanctum
Route::middleware('auth:sanctum')->group(function () {
    // جلب جميع المنتجات المؤقتة
    Route::get('/temporary/output', [TemporaryOutputController::class, 'index']);
    
    // إضافة منتج مؤقت جديد
    Route::post('/temporary/output', [TemporaryOutputController::class, 'store']);
    
    // تعديل منتج مؤقت حسب id
    Route::put('/temporary/output/{id}', [TemporaryOutputController::class, 'update']);
    
    // حذف منتج مؤقت حسب id
    Route::delete('/temporary/output/{id}', [TemporaryOutputController::class, 'destroy']);
    
    // حذف جميع المنتجات المؤقتة
    Route::delete('/temporary/output', [TemporaryOutputController::class, 'clearAll']);
});



// حماية مسارات فواتير الإدخال باستخدام Sanctum
Route::middleware('auth:sanctum')->group(function () {

    // إنشاء فاتورة مدخلات (من Temporary)
    Route::post('/input/invoice', [CreateInputInvoiceController::class, 'createInputsInvoice']);

    // جلب رقم الفاتورة التالي لفواتير الإدخال
    Route::get('/input/invoice/next-invoice-no', [CreateInputInvoiceController::class, 'getInvoiceNo']);

    // عرض جميع فواتير الإدخال حسب التاريخ (YYYY-MM)
    Route::get('/input/invoice/date/{date}', [InputInvoiceController::class, 'index']);

    // تعديل فاتورة إدخال محددة
    Route::put('/input/invoice/{id}', [InputInvoiceController::class, 'update']);

    // حذف فاتورة إدخال معينة مع المدخلات المرتبطة بها
    Route::delete('/input/invoice/{id}', [InputInvoiceController::class, 'destroy']);

    // جلب منتجات الفاتورة
    Route::get('/input/invoice/product/{invoice_no}', [InputInvoiceController::class, 'getProductOfInvoice']);

    // عرض مدخلات الفاتورة بناءً على رقم الفاتورة
    Route::get('/input/{invoice_no}', [InputController::class, 'index']);

    // تعديل مدخل معين
    Route::put('/input/{id}', [InputController::class, 'update']);

    // حذف مدخل معين
    Route::delete('/input/{id}', [InputController::class, 'destroy']);
});



// حماية مسارات فواتير الإخراج باستخدام Sanctum
Route::middleware('auth:sanctum')->group(function () {
    // إنشاء فاتورة إخراج جديدة بناءً على البيانات المؤقتة
    Route::post('/output/invoice', [CreateOutputInvoiceController::class, 'createOutputsInvoice']);

    // جلب رقم الفاتورة التالي لفواتير الإخراج
    Route::get('/output/invoice/next-invoice-no', [CreateOutputInvoiceController::class, 'getInvoiceNo']);

    // عرض كل فواتير الإخراج لشهر معين (بصيغة: 2025-07)
    Route::get('/output/invoice/date/{date}', [OutputInvoiceController::class, 'index']);

    // تعديل فاتورة إخراج معينة
    Route::put('/output/invoice/{id}', [OutputInvoiceController::class, 'update']);

    // حذف فاتورة إخراج معينة (مع استرجاع الكميات)
    Route::delete('/output/invoice/{id}', [OutputInvoiceController::class, 'destroy']);

    // جلب بيانات فاتورة إخراج بناءً على رقم الفاتورة
    Route::get('/output/invoice/product/{invoiceId}', [OutputInvoiceController::class, 'getProductOfInvoice']);

     // عرض كل المنتجات لفاتورة إخراج معينة
    Route::get('/output/{invoice_no}', [OutputController::class, 'index']);

    // تعديل منتج معين داخل الفاتورة
    Route::put('/output/{id}', [OutputController::class, 'update']);

    // حذف منتج معين من الفاتورة
    Route::delete('/output/{id}', [OutputController::class, 'destroy']);

    // البحث عن منتج ضمن فاتورة إخراج باستخدام رقم المنتج ورقم الفاتورة
    Route::post('/output/search', [OutputController::class, 'getOutputProduct']);

});


// حماية مسارات المنتجات باستخدام Sanctum
Route::middleware('auth:sanctum')->group(function () {
    // عرض المنتجات مع دعم البحث والصفحات (index)
    Route::get('/product', [ProductController::class, 'index']);
    
    // البحث عن منتجات حسب نص معين (لإخراج الفاتورة)
    Route::get('/product/search', [ProductController::class, 'getSearchingProducts']);
    
    // جلب منتجات حسب مجموعة معرفات
    Route::get('/product/ids/{ids}', [ProductController::class, 'getProducts']);
    
    // **يجب أن يكون هذا بعد المسارات السابقة**
    Route::get('/product/{product_no}', [ProductController::class, 'show']);
    
    // تحديث منتج حسب الـ id
    Route::put('/product/{id}', [ProductController::class, 'update']);
    
    // حذف منتج حسب الـ id
    Route::delete('/product/{id}', [ProductController::class, 'destroy']);
});



Route::middleware('auth:sanctum')->group(function () {
    // جميع عمليات الدفع
    Route::apiResource('payment', PaymentController::class);
});


Route::middleware('auth:sanctum')->group(function () {
    // جلب التقارير الشهرية والسنوية
    Route::get('/report', [ReportController::class, 'getReport']);

    // جلب التقارير اليومية حسب الشهر
    Route::get('/daily-report/by-month/{month}', [DailyReportController::class, 'getReportsByMonth']);

    // جلب بيانات التقرير اليوميه للتأكد قبل اغلاق الكاش
    Route::get('/daily-report/data', [DailyReportDataController::class, 'getReportData']);

    // التقارير اليومية
    Route::apiResource('/daily-report', DailyReportController::class);

    // ترحيل جميع البيانات لتوليد التقرير اليومي
    Route::put('/daily-report/deportation/{id}', [DailyReportDataController::class, 'deportation']);

    // طباعة وتخزين التقرير اليومي
    Route::get('/daily-report/{reportId}/{date}', [DailyReportDataController::class, 'generateDailyReportPDF']);
});



Route::middleware('auth:sanctum')->group(function () {
    // الاستبدال
    Route::post('/output/product/exchange', [ExchangeAndReturnOutputController::class, 'exchangeProduct']);

    // الاسترجاع
    Route::post('/output/product/return', [ExchangeAndReturnOutputController::class, 'returnProduct']);
});


Route::middleware('auth:sanctum')->group(function () {
    // جلب ارقام واسماء الموظفيين
    Route::get('/staff/getNameAndId', [StaffController::class, 'getNameAndId']);

    // الموطفين
    Route::apiResource('/staff', StaffController::class);
});


Route::middleware('auth:sanctum')->group(function () {
    // جلب السحوبات حسب الشهر
    Route::get( '/withdrawl/{date}', [WithdrawlController::class, 'getDataByMonth']);
    
    // السحوبات
    Route::apiResource('/withdrawl', WithdrawlController::class);

    // جلب المصاريف حسب الشهر
    Route::get('/expense/{date}', [ExpenseController::class, 'getDataByMonth']);

    // المصاريف
    Route::apiResource('/expense', ExpenseController::class);
});


Route::middleware('auth:sanctum')->group(function () {
    
    Route::get('/barcode/print/invoice/{invoiceId}', [BarcodePrintController::class, 'printBarcodeLabelDependInvoiceId']);

    Route::get('/barcode/print/product/{productId}/{quantity}', [BarcodePrintController::class, 'printBarcodeLabelDependProduct']);
});



