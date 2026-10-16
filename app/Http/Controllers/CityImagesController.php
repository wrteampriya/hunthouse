<?php

namespace App\Http\Controllers;

use Exception;
use App\Models\Setting;
use App\Models\Property;
use App\Models\CityImage;
use Illuminate\Http\Request;
use App\Services\FileService;
use App\Services\ResponseService;
use Illuminate\Support\Facades\DB;
use App\Services\BootstrapTableService;
use Illuminate\Support\Facades\Validator;

class CityImagesController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        if (!has_permissions('read', 'city_images')) {
            return redirect()->back()->with('error', trans(PERMISSION_ERROR_MSG));
        }
        $cityImageStyle = Setting::where('type', 'city_image_style')->first();
        return view('property.cities', compact('cityImageStyle'));
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        if (!has_permissions('read', 'city_images')) {
            ResponseService::errorResponse(PERMISSION_ERROR_MSG);
        }
        $offset = request('offset', 0);
        $limit = request('limit', 10);
        $sort = request('sort', 'id');
        $order = request('order', 'DESC');
        $search = request('search');

        $sql = CityImage::withCount(['property' => function ($query) {
                $query->where('status', 1);
            }])->when($search, function ($query) use ($search) {
            $query->where(function ($query) use ($search) {
                $query->where('id', 'LIKE', "%$search%")
                    ->orWhere('city', 'LIKE', "%$search%");
            });
        });


        $total = $sql->count();

        $sql->orderBy($sort, $order)->skip($offset)->take($limit);
        $res = $sql->get();
        $bulkData = array();
        $bulkData['total'] = $total;
        $rows = array();
        $no = 1;
        foreach ($res as $row) {
            if($row->property_count <= 0){
                CityImage::where('id',$row->id)->update(array('status' =>false));
            }
            $row = (object)$row;

            $operate = '';
            if(has_permissions('update', 'city_images')){
                $operate = BootstrapTableService::editButton('', true, null, null, $row->id, null);
            }
            if(has_permissions('delete', 'city_images')){
                $operate .= BootstrapTableService::deleteAjaxButton(route('city-images.destroy', $row->id));
            }

            $tempRow = $row->toArray();
            $tempRow['total_properties'] = $row->property_count;
            $tempRow['edit_status_url'] = route('city-images.status-update');
            $tempRow['exclude_status_toggle'] = 0;
            if($row->property_count <= 0){
                $tempRow['exclude_status_toggle'] = 1;
            }
            $tempRow['operate'] = $operate;
            $rows[] = $tempRow;
        }

        $bulkData['rows'] = $rows;
        return response()->json($bulkData);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        if (!has_permissions('update', 'city_images')) {
            ResponseService::errorResponse(PERMISSION_ERROR_MSG);
        }
        $validator = Validator::make($request->all(), [
            'image' => 'nullable|mimes:jpg,png,jpeg,webp',
        ]);
        if ($validator->fails()) {
            ResponseService::validationError($validator->errors()->first());
        }
        try {
            $cityImageData = CityImage::find($id);
            if ($request->hasFile('image')) {
                $path = config('global.CITY_IMAGE_PATH');
                $rawImage = $cityImageData->getRawOriginal('image');
                $image = FileService::compressAndReplace($request->file('image'), $path, $rawImage);
                $cityImageData->image = $image;
            }
            $cityImageData->save();
            ResponseService::successResponse(trans('Data Updated Successfully'));
        } catch (Exception $e) {
            ResponseService::logErrorResponse($e,trans('Something Went Wrong'));
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        if (!has_permissions('delete', 'city_images')) {
            ResponseService::errorResponse(PERMISSION_ERROR_MSG);
        }
        try {
            $query = CityImage::where('id', $id);
            $data = $query->clone()->first();
            if(collect($data)->isNotEmpty()){
                if (!empty($data->getRawOriginal('image'))) {
                    $url = $data->image;
                    $relativePath = parse_url($url, PHP_URL_PATH);
                    if (file_exists(public_path()  . $relativePath)) {
                        unlink(public_path()  . $relativePath);
                    }
                }
            }
            $query->clone()->delete();
            ResponseService::successResponse(trans('Data Deleted Successfully'));
        } catch (Exception $e) {
            ResponseService::logErrorResponse($e,trans('Something Went Wrong'));
        }
    }


    public function statusUpdate(Request $request){
        if (!has_permissions('update', 'city_images')) {
            ResponseService::errorResponse(PERMISSION_ERROR_MSG);
        }
        $validator = Validator::make($request->all(), [
            'id'        => 'required',
            'status'    => 'required|in:0,1',
        ]);
        if ($validator->fails()) {
            ResponseService::validationError($validator->errors()->first());
        }
        try {
            CityImage::where('id', $request->id)->update(array('status' => $request->status));
            ResponseService::successResponse(trans('Data Updated Successfully'));
        } catch (Exception $e) {
            ResponseService::logErrorResponse($e,trans('Something Went Wrong'));
        }
    }
    public function cityImageSettings(Request $request){
        if (!has_permissions('update', 'city_images')) {
            ResponseService::errorResponse(PERMISSION_ERROR_MSG);
        }
        $validator = Validator::make($request->all(), [
            'city_image_style' => 'required|in:style_1,style_2',
        ]);
        if ($validator->fails()) {
            ResponseService::validationError($validator->errors()->first());
        }
        try {
            /**
             * Style 1 : With Image and Text
             * Style 2 : Only Text
             */
            Setting::updateOrCreate(['type' => 'city_image_style'], ['data' => $request->city_image_style]);
            ResponseService::successResponse(trans('Data Updated Successfully'));
        } catch (Exception $e) {
            ResponseService::logErrorResponse($e,trans('Something Went Wrong'));
        }
    }
}
