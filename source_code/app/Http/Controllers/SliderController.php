<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Property;
use App\Models\Slider;
use App\Services\BootstrapTableService;
use App\Services\FileService;
use App\Services\ResponseService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Spatie\Image\Image;

class SliderController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index()
    {
        if (! has_permissions('read', 'slider')) {
            return redirect()->back()->with('error', trans(PERMISSION_ERROR_MSG));
        } else {
            $slider = Slider::select('id', 'image', 'sequence')->orderBy('sequence', 'ASC')->get();
            $categories = Category::select('id', 'category')->where('status', 1)->orderBy('id', 'DESC')->get();
            $properties = Property::select('id', 'title', 'category_id')->where(['status' => 1, 'request_status' => 'approved'])->orderBy('id', 'DESC')->get();

            return view('slider.index', compact('slider', 'categories', 'properties'));
        }
    }

    /**
     * Store a newly created resource in storage.
     *
     * @return Response
     */
    public function store(Request $request)
    {

        if (! has_permissions('create', 'slider')) {
            return redirect()->back()->with('error', trans(PERMISSION_ERROR_MSG));
        } else {
            $request->validate([
                'type' => 'required|in:1,2,3,4',
                'image' => 'required|mimes:jpg,png,jpeg,webp|max:2048',
                'web_image' => 'required|mimes:jpg,png,jpeg,webp|max:2048',
                'category' => 'nullable|required_if:type,2',
                'property' => 'nullable|required_if:type,3',
                'link' => 'nullable|required_if:type,4',
            ]);

            $destinationPath = public_path('images').config('global.SLIDER_IMG_PATH');

            if (! is_dir($destinationPath)) {
                mkdir($destinationPath, 0777, true);
            }
            $appImageName = null;
            if ($request->hasFile('image')) {
                $path = config('global.SLIDER_IMG_PATH');
                $appImageName = FileService::compressAndUpload($request->file('image'), $path);
            }
            $webImageName = null;
            if ($request->hasFile('web_image')) {
                $path = config('global.SLIDER_IMG_PATH');
                $webImageName = FileService::compressAndUpload($request->file('web_image'), $path);
            }
            Slider::create([
                'type' => $request->type,
                'image' => $appImageName,
                'web_image' => $webImageName,
                'category_id' => (isset($request->category)) ? $request->category : null,
                'propertys_id' => (isset($request->property)) ? $request->property : null,
                'show_property_details' => (isset($request->show_property_details) && $request->show_property_details) ? 1 : 0,
                'link' => (isset($request->link)) ? $request->link : null,
            ]);
            ResponseService::successRedirectResponse('Data Created Successfully');
        }
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  int  $id
     * @return Response
     */
    public function update(Request $request, $id)
    {
        if (! has_permissions('update', 'slider')) {
            ResponseService::errorResponse(PERMISSION_ERROR_MSG);
        } else {
            $validator = Validator::make($request->all(), [
                'edit_image_missing' => 'nullable|in:0,1',
                'edit_web_image_missing' => 'nullable|in:0,1',
                'edit_image' => 'required_if:edit_image_missing,1|mimes:jpg,png,jpeg,webp|max:2048',
                'edit_web_image' => 'required_if:edit_web_image_missing,1|mimes:jpg,png,jpeg,webp|max:2048',
            ], [
                'edit_image.required_if' => trans('The image field is required.'),
                'edit_web_image.required_if' => trans('The web image field is required.'),
            ]);
            if ($validator->fails()) {
                ResponseService::validationError($validator->errors()->first());
            }
            try {
                // Get Slider Data
                $sliderData = Slider::where('id', $id)->first();
                $data = [];

                // Update App Image
                if ($request->has('edit_image')) {
                    $rawImage = $sliderData->getRawOriginal('image');
                    $path = config('global.SLIDER_IMG_PATH');
                    $editAppImageName = FileService::compressAndReplace($request->file('edit_image'), $path, $rawImage);
                    $data['image'] = $editAppImageName;
                }

                // Update Web Image
                if ($request->has('edit_web_image')) {
                    $rawWebImage = $sliderData->getRawOriginal('web_image');
                    $path = config('global.SLIDER_IMG_PATH');
                    $editWebImageName = FileService::compressAndReplace($request->file('edit_web_image'), $path, $rawWebImage);
                    $data['web_image'] = $editWebImageName;
                }
                $sliderData->update($data);
                ResponseService::successResponse(trans('Data Updated Successfully'));
            } catch (Exception $e) {
                ResponseService::logErrorResponse($e, trans('Something Went Wrong'));
            }
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return Response
     */
    public function destroy($id)
    {
        if (env('DEMO_MODE') && Auth::user()->email != 'superadmin@gmail.com') {
            return redirect()->back()->with('error', trans('This is not allowed in the Demo Version'));
        }
        if (! has_permissions('delete', 'slider')) {
            return redirect()->back()->with('error', trans(PERMISSION_ERROR_MSG));
        } else {
            try {
                $slider = Slider::find($id);
                if ($slider) {
                    $slider->delete();
                }
                ResponseService::successRedirectResponse('Data Deleted Successfully');
            } catch (Exception $th) {
                ResponseService::errorRedirectResponse(null, 'something is wrong !!!');
            }
        }
    }

    public function sliderList(Request $request)
    {
        if (! has_permissions('read', 'slider')) {
            ResponseService::errorResponse(PERMISSION_ERROR_MSG);
        }
        $offset = $request->input('offset', 0);
        $limit = $request->input('limit', 10);
        $sort = $request->input('sort', 'id');
        $order = $request->input('order', 'ASC');

        $sql = Slider::with('category:id,category', 'property:id,title,title_image')->orderBy($sort, $order);
        if (isset($_GET['search']) && ! empty($_GET['search'])) {
            $search = $_GET['search'];
            $sql = $sql->where('id', 'LIKE', "%$search%")->orWhere('link', 'LIKE', "%$search%")->orWhereHas('category', function ($query) use ($search) {
                $query->where('category', 'LIKE', "%$search%");
            })->orWhereHas('property', function ($query) use ($search) {
                $query->where('title', 'LIKE', "%$search%");
            });
        }

        $total = $sql->count();
        if (isset($_GET['limit'])) {
            $sql->skip($offset)->take($limit);
        }

        $res = $sql->get();
        $bulkData = [];
        $bulkData['total'] = $total;
        $rows = [];
        $tempRow = [];
        $count = 1;

        foreach ($res as $row) {
            $operate = BootstrapTableService::editButton(route('slider.update', $row->id), true, null, null, $row->id);
            if ($row->default_data == 0) {
                $operate .= BootstrapTableService::deleteButton(route('slider.destroy', $row->id));
            }
            $tempRow = $row->toArray();
            $tempRow['type'] = trans($row->type);
            $tempRow['image_exists'] = ! empty($row->image) ? $row->image : false;
            $tempRow['web_image_exists'] = ! empty($row->web_image) ? $row->web_image : false;
            $tempRow['operate'] = $operate;
            $rows[] = $tempRow;
            $count++;
        }

        $bulkData['rows'] = $rows;

        return response()->json($bulkData);
    }
}
