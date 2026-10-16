<?php

namespace App\Http\Controllers;

use App\Models\Currency;
use App\Services\BootstrapTableService;
use App\Services\HelperService;
use App\Services\ResponseService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class CurrencyController extends Controller
{
    public function index()
    {
        if (! has_permissions('read', 'currency')) {
            return redirect()->back()->with('error', trans(PERMISSION_ERROR_MSG));
        }
        $listOfCurrencies = HelperService::currencyCode();

        return view('currencies.index', compact('listOfCurrencies'));
    }

    public function store(Request $request)
    {
        if (! has_permissions('create', 'currency')) {
            return redirect()->back()->with('error', trans(PERMISSION_ERROR_MSG));
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:8|unique:currencies,code',
            'symbol' => 'required|string|max:16',
        ]);
        if ($validator->fails()) {
            return redirect()->back()->with('error', $validator->errors()->first());
        }

        try {
            DB::beginTransaction();
            Currency::create([
                'name' => $request->name,
                'code' => strtoupper($request->code),
                'symbol' => $request->symbol,
                // New currencies always start inactive — there's no status control
                // on this form; activate via the switch in the list once ready.
                'status' => 0,
            ]);
            DB::commit();
            ResponseService::successRedirectResponse('Data Created Successfully');
        } catch (Exception $e) {
            DB::rollBack();
            ResponseService::errorRedirectResponse(null, $e->getMessage());
        }
    }

    public function show(Request $request)
    {
        if (! has_permissions('read', 'currency')) {
            ResponseService::errorResponse(PERMISSION_ERROR_MSG);
        }
        $offset = $request->input('offset', 0);
        $limit = $request->input('limit', 10);
        $sort = $request->input('sort', 'id');
        $order = $request->input('order', 'DESC');

        $sql = Currency::orderBy($sort, $order);

        if (isset($_GET['search']) && ! empty($_GET['search'])) {
            $search = $_GET['search'];
            $sql->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', "%$search%")->orWhere('code', 'LIKE', "%$search%");
            });
        }

        $total = $sql->count();

        if (isset($_GET['limit'])) {
            $sql->skip($offset)->take($limit);
        }

        $res = $sql->get();
        $rows = [];

        foreach ($res as $row) {
            $tempRow = $row->toArray();
            $tempRow['edit_status_url'] = route('currency.status-update');
            $operate = '';
            if (has_permissions('update', 'currency')) {
                $operate = BootstrapTableService::editButton('', true, '#editCurrencyModal', 'edit-currency', $row->id);
            }
            if (has_permissions('delete', 'currency')) {
                $operate .= BootstrapTableService::deleteButton(route('currency.destroy', $row->id), $row->id);
            }
            $tempRow['operate'] = $operate;
            $rows[] = $tempRow;
        }

        return response()->json(['total' => $total, 'rows' => $rows]);
    }

    public function update(Request $request)
    {
        if (! has_permissions('update', 'currency')) {
            return redirect()->back()->with('error', trans(PERMISSION_ERROR_MSG));
        }

        $validator = Validator::make($request->all(), [
            'edit_id' => 'required|exists:currencies,id',
            'edit_name' => 'required|string|max:255',
            'edit_code' => 'required|string|max:8|unique:currencies,code,'.$request->edit_id,
            'edit_symbol' => 'required|string|max:16',
        ]);
        if ($validator->fails()) {
            return redirect()->back()->with('error', $validator->errors()->first());
        }

        try {
            DB::beginTransaction();
            $currency = Currency::findOrFail($request->edit_id);
            $currency->update([
                'name' => $request->edit_name,
                'code' => strtoupper($request->edit_code),
                'symbol' => $request->edit_symbol,
                // Status is never touched here — there's no status control on
                // this form; it's managed exclusively via the switch in the list.
            ]);
            DB::commit();
            ResponseService::successRedirectResponse('Data Updated Successfully');
        } catch (Exception $e) {
            DB::rollBack();
            ResponseService::errorRedirectResponse(null, $e->getMessage());
        }
    }

    public function statusUpdate(Request $request)
    {
        if (! has_permissions('update', 'currency')) {
            ResponseService::errorResponse(PERMISSION_ERROR_MSG);
        }
        $validator = Validator::make($request->all(), [
            'id' => 'required|exists:currencies,id',
            'status' => 'required|in:0,1',
        ]);
        if ($validator->fails()) {
            ResponseService::validationError($validator->errors()->first());
        }
        try {
            Currency::where('id', $request->id)->update(['status' => $request->status]);
            ResponseService::successResponse(trans('Data Updated Successfully'));
        } catch (Exception $e) {
            ResponseService::logErrorResponse($e, trans('Something Went Wrong'));
        }
    }

    public function destroy($id)
    {
        if (env('DEMO_MODE') && Auth::user()->email != 'superadmin@gmail.com') {
            return redirect()->back()->with('error', trans('This is not allowed in the Demo Version'));
        }
        if (! has_permissions('delete', 'currency')) {
            return redirect()->back()->with('error', trans(PERMISSION_ERROR_MSG));
        }

        $currency = Currency::find($id);
        if (! $currency) {
            return ResponseService::errorRedirectResponse(null, 'Currency Not Found');
        }
        if ($currency->countries()->exists()) {
            return redirect()->back()->with('error', trans('This currency is mapped to one or more countries. Unmap it before deleting.'));
        }

        if ($currency->delete()) {
            ResponseService::successRedirectResponse('Data Deleted Successfully');
        } else {
            ResponseService::errorRedirectResponse(null, 'Something Went Wrong');
        }
    }
}
