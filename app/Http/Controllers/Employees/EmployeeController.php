<?php

namespace App\Http\Controllers\Employees;

use App\Models\Store;
use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Employees\EmployeeTrash;
use App\Http\Controllers\Employees\EmployeeActions;
use App\Http\Controllers\Employees\EmployeeReports;
use App\Http\Controllers\Employees\EmployeeService;

class EmployeeController extends Controller
{
    public function index(Request $request)
    {
        return EmployeeService::index($request);
    }

    public function show(Employee $employee)
    {
        return redirect()->route('user.employees.actions', array_filter([
            'employee' => $employee->id,
            'return_to' => request()->query('return_to'),
            'month' => request()->query('month'),
        ], fn ($value) => $value !== null));
    }

    public function promote(Request $request, Employee $employee)
    {
        return EmployeeActions::promote($request, $employee);
    }

    public function checkEmail(Request $request)
    {
        return EmployeeActions::checkEmail($request);
    }

    public function demote(Employee $employee)
    {
        return EmployeeActions::demote($employee);
    }

    public function create(Request $request)
    {
        // نستخدم الحارس المناسب لجلب ID المالك
        $userId = auth('web')->id() ?: auth('admin')->id();

        $store = null;
        if ($request->filled('store')) {
            $store = Store::where('id', $request->store)
                ->where('user_id', $userId)
                ->first();
        }

        $stores = Store::where('user_id', $userId)
            ->orderBy('id')
            ->get();

        return view('employees.create', compact('store', 'stores'));
    }

    public function store(Request $request, EmployeeService $service)
    {
        $ownerId = auth('web')->id() ?: auth('admin')->id();

        $validated = $request->validate([
            'store_id' => [
                'required',
                Rule::exists('stores', 'id')->where(fn ($query) => $query->where('user_id', $ownerId)),
            ],
            'name' => 'required|string|max:255',
            'salary' => 'required|numeric|min:0',
        ]);

        $store = Store::where('id', $validated['store_id'])
            ->where('user_id', $ownerId)
            ->firstOrFail();

        $data = $request->all();
        $data['user_id'] = $store->user_id;
        $data['added_by'] = auth()->id() ?: $store->user_id;
        $data['status'] = 'active';

        $service->create($data);

        $returnTo = $service->safeReturnTo($request->input('return_to'));

        return redirect($returnTo ?? route('user.employees.index'))
            ->with('success', 'تم إضافة الموظف بنجاح');
    }

    public function edit(Employee $employee)
    {
        return EmployeeService::edit($employee);
    }

    public function update(Request $request, Employee $employee)
    {
        return EmployeeService::update($request, $employee);
    }

    public function destroy(Employee $employee)
    {
        return EmployeeTrash::delete($employee, request());
    }

    public function suspend(Employee $employee)
    {
        return EmployeeService::suspend($employee, request());
    }

    public function activate(Employee $employee)
    {
        return EmployeeService::activate($employee, request());
    }

    public function trash()
    {
        return EmployeeTrash::list();
    }

    public function restore($id)
    {
        return EmployeeTrash::restore($id);
    }

    public function forceDelete($id)
    {
        return EmployeeTrash::forceDelete($id);
    }

    public function exportPdf($id)
    {
        return EmployeeReports::exportPdf($id);
    }

    /**
     * 2026-05-16: دالة توافق مؤقتة لأي route قديم ما زال يستخدم اسم exportSnappy.
     */
    public function exportSnappy($id)
    {
        return $this->exportPdf($id);
    }
}
