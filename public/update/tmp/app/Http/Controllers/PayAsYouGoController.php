<?php

namespace App\Http\Controllers;

use App\Models\PayAsYouGo;
use App\Models\Setting;
use App\Services\BootstrapTableService;
use App\Services\HelperService;
use App\Services\ResponseService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;

class PayAsYouGoController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index()
    {
        if (! has_permissions('read', 'package')) {
            return redirect()->back()->with('error', trans(PERMISSION_ERROR_MSG));
        }

        // Auto-seed the two default items if they don't exist
        if (PayAsYouGo::count() == 0) {
            PayAsYouGo::create(['name' => 'Single Property Listing', 'price' => 1.00, 'type' => 'property']);
            PayAsYouGo::create(['name' => 'Single Project Listing', 'price' => 1.00, 'type' => 'project']);
        }

        $currency_symbol = Setting::where('type', 'currency_symbol')->pluck('data')->first();

        return view('pay_as_you_go.index', compact('currency_symbol'));
    }

    /**
     * Fetch the resource data for table.
     */
    public function show(Request $request)
    {
        if (! has_permissions('read', 'package')) {
            ResponseService::errorResponse(PERMISSION_ERROR_MSG);
        }

        $offset = $request->input('offset', 0);
        $limit = $request->input('limit', 10);
        $sort = $request->input('sort', 'id');
        $order = $request->input('order', 'ASC');

        $sql = PayAsYouGo::query();

        if (isset($_GET['search']) && ! empty($_GET['search'])) {
            $search = $_GET['search'];
            $sql->where('id', 'LIKE', "%$search%")->orwhere('name', 'LIKE', "%$search%");
        }

        $total = $sql->count();
        if (isset($_GET['limit'])) {
            $sql->skip($offset)->take($limit);
        }

        $res = $sql->orderBy($sort, $order)->get();
        $bulkData = [];
        $bulkData['total'] = $total;
        $rows = [];

        $priceSymbol = HelperService::getSettingData('currency_symbol') ?? '$';

        foreach ($res as $row) {
            $tempRow = $row->toArray();

            $operate = '';
            if (has_permissions('update', 'package')) {
                $operate .= BootstrapTableService::editButton('', true, null, null, $row->id);
            }

            $tempRow['operate'] = $operate;
            if (has_permissions('update', 'package')) {
                $tempRow['edit_status_url'] = route('pay-as-you-go.updatestatus');
            }
            $tempRow['price_symbol'] = $priceSymbol;
            $rows[] = $tempRow;
        }

        $bulkData['rows'] = $rows;

        return response()->json($bulkData);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update($id, Request $request)
    {
        if (! has_permissions('update', 'package')) {
            ResponseService::errorResponse(PERMISSION_ERROR_MSG);
        } else {
            $validator = Validator::make($request->all(), [
                'edit_id' => 'required|exists:pay_as_you_gos,id',
                'name' => 'required|string|max:100',
                'ios_product_id' => 'nullable|string',
                'price' => 'required|numeric|min:1',
            ]);

            if ($validator->fails()) {
                ResponseService::validationError($validator->errors()->first());
            }

            try {
                $data = $request->only('name', 'ios_product_id');
                if ($request->has('price')) {
                    $data['price'] = round($request->price, 2);
                }

                PayAsYouGo::where('id', $id)->update($data);

                ResponseService::successResponse('Data Updated Successfully');
            } catch (Exception $e) {
                ResponseService::logErrorResponse($e, 'PayAsYouGo Controller -> Update method', trans('Something Went Wrong'));
            }
        }
    }

    public function updateStatus(Request $request)
    {
        if (! has_permissions('update', 'package')) {
            ResponseService::errorResponse(PERMISSION_ERROR_MSG);
        } else {
            PayAsYouGo::where('id', $request->id)->update(['status' => $request->status]);
            ResponseService::successResponse($request->status ? 'Package Activated Successfully' : 'Package Deactivated Successfully');
        }
    }
}
