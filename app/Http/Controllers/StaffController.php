<?php

namespace App\Http\Controllers;

use App\Models\Staff;
use App\Models\User;
use DB;
use Exception;
use Hash;
use Illuminate\Http\Request;
use Storage;
use Str;
use Validator;

class StaffController extends Controller
{
    /**
     * Display a listing of staff name and id
     */
    public function getNameAndId()
    {
        $data = Staff::select('id', 'name')->get();

        return response()->json($data);
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $staff = Staff::with('user:id,name,email,role')->get();
        return response()->json($staff);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        try {
            // استخدام Validator بدلاً من validate()
            $validator = Validator::make($request->all(), [
                'type' => 'required|string',
                'role'=> 'required|string|in:admin,user',
                'name'=> 'required|string|max:255',
                'email'=> 'required|string|email|max:255|unique:users',
                'password'=> 'required|string|min:6|confirmed',
                'phone' => 'required|string|max:15|regex:/^(\+?[0-9]{1,3})?([0-9]{10})$/',
                'salary' => 'required|numeric|min:0',
                'work_hours' => 'required|integer',
                'break_hours' => 'required|integer',
                'off_days' => 'required|integer',
                'image' => 'nullable|image|mimes:jpg,jpeg,png,gif|max:2048',
            ], [
                'type.required' => 'يجب ادخال نوع العمل',
                'type.string' => 'ادخل نوع العمل بشكل صحيح',
                'name.required' => 'يجب ادخال الاسم',
                'name.string' => 'ادخل الاسم بشكل صحيح',
                'phone.required' => 'يجب ادخال رقم الهاتف',
                'phone.string' => 'ادخل رقم الهاتف بشكل صحيح',
                'phone.max' => 'يجب ان يكون رقم الهاتف 15 رقم او اقل',
                'phone.unique' => 'هذا رقم الهاتف موجود مسبقا',
                'phone.regex' => 'ادخل رقم الهاتف بشكل صحيح',
                'salary.required' => 'يجب ادخال الراتب',
                'salary.numeric' => 'ادخل الراتب بشكل صحيح',
                'salary.min' => 'يجب ان يكون الراتب اكبر من 0',
                'work_hours.required' => 'يجب ادخال عدد ساعات العمل',
                'work_hours.integer' => 'ادخل عدد ساعات العمل بشكل صحيح',
                'break_hours.required' => 'يجب ادخال عدد ساعات الاستراحة',
                'break_hours.integer' => 'ادخل عدد ساعات الاستراحة بشكل صحيح',
                'off_days.required' => 'يجب ادخال عدد ايام الاستراحة',
                'off_days.integer' => 'ادخل عدد ايام الاستراحة بشكل صحيح',
                'image.image' => 'ادخل صورة بشكل صحيح',
                'image.mimes' => 'ادخل صورة من الملفات المسموحة',
                'image.max' => 'ادخل صورة بشكل صحيح',
            ]);

            // إذا فشل التحقق من البيانات
            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 400);
            }

            $validatedData = $validator->validated();

            // بدء المعاملة
            DB::beginTransaction();

            if ($request->hasFile('image')) {
                // إنشاء اسم عشوائي للصورة مع الامتداد
                $imageName = Str::random(40) . '.' . $request->file('image')->getClientOriginalExtension();
                // رفع الصورة إلى مجلد محدد داخل storage/public
                $filePath = 'staff/image';
                Storage::disk('public')->putFileAs($filePath, $request->file('image'), $imageName);
                // تخزين مسار الصورة في البيانات
                $validatedData['image'] = $filePath . '/' . $imageName;
            } else {
                $validatedData['image'] = null; // إذا لم يتم رفع صورة
            }
            

            $user = User::create([
                'role' => $validatedData['role'],
                'name' => $validatedData['name'],
                'email' => $validatedData['email'],
                'phone' => $validatedData['phone'],
                'password' => Hash::make($validatedData['password'])
            ]);


            $staff = Staff::create([
                'user_id' => $user->id,
                'type' => $validatedData['type'],
                'name' => $validatedData['name'],
                'salary' => $validatedData['salary'],
                'work_hours' => $validatedData['work_hours'],
                'break_hours' => $validatedData['break_hours'],
                'off_days' => $validatedData['off_days'],
                'image' => $validatedData['image'],
            ]);

            // تنفيذ العملية
            DB::commit();

            return response()->json(['message' => 'تمت اضافة الموظف بنجاح'], 201);
        
        } catch (Exception $e) {
            // التراجع عن كل العمليات في حال وجود خطأ
            DB::rollBack();

            return response()->json(['error' => 'حدث خطأ أثناء إضافة الموظف: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        try {
            $staff = Staff::findOrFail($id);
            $user = $staff->user; // العلاقة بين Staff و User

            // التحقق من البيانات
            $validator = Validator::make($request->all(), [
                'type' => 'nullable|string',
                'role' => 'nullable|string|in:admin,user,accountant,cashier',
                'name' => 'nullable|string|max:255',
                'email' => 'nullable|string|email|max:255|unique:users,email,' . $user->id,
                'password' => 'nullable|string|min:6|confirmed',
                'phone' => 'nullable|string|max:15|unique:staff,phone,' . $id . '|regex:/^(\+?[0-9]{1,3})?([0-9]{10})$/',
                'salary' => 'nullable|numeric|min:0',
                'work_hours' => 'nullable|integer',
                'break_hours' => 'nullable|integer',
                'off_days' => 'nullable|integer',
                'image' => 'nullable|image|mimes:jpg,jpeg,png,gif|max:2048',
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 400);
            }

            $validatedData = $validator->validated();

            DB::beginTransaction();

            // تحديث صورة الموظف إذا تم رفعها
            if ($request->hasFile('image')) {
                if ($staff->image) {
                    Storage::disk('public')->delete($staff->image);
                }
                $imageName = Str::random(40) . '.' . $request->file('image')->getClientOriginalExtension();
                $filePath = 'staff/image';
                Storage::disk('public')->putFileAs($filePath, $request->file('image'), $imageName);
                $validatedData['image'] = $filePath . '/' . $imageName;
            }

            // تحديث بيانات المستخدم
            $user->update([
                'role' => $validatedData['role'] ?? $user->role,
                'name' => $validatedData['name'] ?? $user->name,
                'email' => $validatedData['email'] ?? $user->email,
                'password' => isset($validatedData['password']) ? Hash::make($validatedData['password']) : $user->password,
            ]);

            // تحديث بيانات الموظف
            $staff->update([
                'type' => $validatedData['type'] ?? $staff->type,
                'name' => $validatedData['name'] ?? $staff->name,
                'phone' => $validatedData['phone'] ?? $staff->phone,
                'salary' => $validatedData['salary'] ?? $staff->salary,
                'work_hours' => $validatedData['work_hours'] ?? $staff->work_hours,
                'break_hours' => $validatedData['break_hours'] ?? $staff->break_hours,
                'off_days' => $validatedData['off_days'] ?? $staff->off_days,
                'image' => $validatedData['image'] ?? $staff->image,
            ]);

            DB::commit();

            return response()->json(['message' => 'تم تعديل بيانات الموظف والمستخدم بنجاح'], 200);

        } catch (Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'حدث خطأ أثناء تعديل بيانات الموظف: ' . $e->getMessage()], 500);
        }
    }


    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        try {
            $staff = Staff::findOrFail($id);

            // حذف الصورة
            $imagePath = $staff->image;
            if ($imagePath) {
                Storage::disk('public')->delete($imagePath);
            }

            $staff->delete();
            return response()->json(['message' => 'تم حذف الموظف بنجاح'], 200);
        } catch (Exception $e) {
            return response()->json(['error' => 'حدث خطأ أثناء حذف الموظف: ' . $e->getMessage()], 500);
        }
    }
}
