<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Favourite;
use App\Models\Property;
use App\Services\ApiResponseService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class FavouriteApiController extends Controller
{
    public function add_favourite(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'type' => 'required',
            'property_id' => 'required',
        ]);

        if (! $validator->fails()) {
            $current_user = Auth::user()->id;
            if ($request->type == 1) {
                $fav_prop = Favourite::where('user_id', $current_user)->where(['property_id' => $request->property_id, 'role_context' => $request->user_active_role])->get();

                if (count($fav_prop) > 0) {
                    $response['error'] = false;
                    $response['message'] = trans('Property Already Added To Favourite');

                    return response()->json($response);
                }
                $favourite = new Favourite;
                $favourite->user_id = $current_user;
                $favourite->property_id = $request->property_id;
                $favourite->save();
                $response['error'] = false;
                $response['message'] = trans('Property Added To Favourite Successfully');
            }
            if ($request->type == 0) {
                Favourite::where('property_id', $request->property_id)->where('user_id', $current_user)->where('role_context', $request->user_active_role)->delete();

                $response['error'] = false;
                $response['message'] = trans('Property Removed From Favourite Successfully');
            }
        } else {
            $response['error'] = true;
            $response['message'] = $validator->errors()->first();

        }

        return response()->json($response);
    }

    public function get_favourite_property(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'offset' => 'nullable|integer|min:0',
            'limit' => 'nullable|integer|min:1|max:200',
        ]);
        if ($validator->fails()) {
            ApiResponseService::validationError($validator->errors()->first());
        }
        $offset = isset($request->offset) ? $request->offset : 0;
        $limit = isset($request->limit) ? $request->limit : 25;

        $current_user = Auth::user()->id;

        $favourite = Favourite::where('user_id', $current_user)->where('role_context', $request->user_active_role)->select('property_id')->get();
        $arr = [];
        foreach ($favourite as $p) {
            $arr[] = $p->property_id;
        }

        $property_details = Property::whereIn('id', $arr)->onlyActive()->with('category:id,category,image', 'category.translations', 'translations')->with('assignfacilities.outdoorfacilities')->with('parameters');
        $result = $property_details->clone()->orderBy('id', 'ASC')->skip($offset)->take($limit)->get()->map(function ($property) {
            $property->category->translated_name = $property->category->translated_name;
            $property->translated_title = $property->translated_title;
            $property->translated_description = $property->translated_description;

            return $property;
        });

        $total = $property_details->clone()->count();

        if (! $result->isEmpty()) {
            $response['error'] = false;
            $response['message'] = trans('Data Fetched Successfully');
            $response['data'] = get_property_details($result, $current_user, true);
            $response['total'] = $total;
        } else {
            $response['error'] = false;
            $response['message'] = trans('No Data Found');
            $response['data'] = [];
        }

        return response()->json($response);
    }
}
