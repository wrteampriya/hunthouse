<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Services\ApiResponseService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CategoryApiController extends Controller
{
    public function get_categories(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'offset' => 'nullable|integer|min:0',
            'limit' => 'nullable|integer|min:1|max:200',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'range' => 'nullable|numeric|min:0',
            'id' => 'nullable|integer',
            'slug_id' => 'nullable|string',
        ]);
        if ($validator->fails()) {
            ApiResponseService::validationError($validator->errors()->first());
        }
        $offset = isset($request->offset) ? $request->offset : 0;
        $limit = isset($request->limit) ? $request->limit : 10;
        $latitude = $request->has('latitude') ? $request->latitude : null;
        $longitude = $request->has('longitude') ? $request->longitude : null;
        $range = $request->has('range') ? $request->range : null;

        $categories = Category::select(
            'id',
            'category',
            'image',
            'parameter_types',
            'meta_title',
            'meta_description',
            'meta_keywords',
            'slug_id'
        )
            ->where('status', 1)
            ->withCount(['properties as property_count' => function ($query) use ($latitude, $longitude, $range) {
                $query->onlyActive()
                    ->whereIn('propery_type', [0, 1])
                    ->where('latitude', '!=', 0)
                    ->where('longitude', '!=', 0);

                if ($latitude && $longitude) {
                    if ($range) {
                        $query->whereRaw('
                        (6371 * acos(
                            cos(radians(?)) * cos(radians(latitude)) *
                            cos(radians(longitude) - radians(?)) +
                            sin(radians(?)) * sin(radians(latitude))
                        )) < ?
                    ', [$latitude, $longitude, $latitude, $range]);
                    } else {
                        $query->where('latitude', $latitude)
                            ->where('longitude', $longitude);
                    }
                }
            }]);

        if ($request->boolean('has_property')) {
            $categories->whereHas('properties', function ($query) use ($latitude, $longitude, $range) {
                $query->onlyActive()
                    ->whereIn('propery_type', [0, 1])
                    ->where('latitude', '!=', 0)
                    ->where('longitude', '!=', 0);

                if ($latitude && $longitude) {
                    if ($range) {
                        $query->whereRaw('
                            (6371 * acos(
                                cos(radians(?)) * cos(radians(latitude)) *
                                cos(radians(longitude) - radians(?)) +
                                sin(radians(?)) * sin(radians(latitude))
                            )) < ?
                        ', [$latitude, $longitude, $latitude, $range]);
                    } else {
                        $query->where('latitude', $latitude)
                            ->where('longitude', $longitude);
                    }
                }
            });
        }

        if (isset($request->search) && ! empty($request->search)) {
            $search = $request->search;
            $categories->where('category', 'LIKE', "%$search%")
                ->orWhere(function ($query) use ($search) {
                    $query->searchInAnyTranslation($search);
                });
        }

        if (isset($request->id) && ! empty($request->id)) {
            $id = $request->id;
            $categories->where('id', $id);
        }
        if (isset($request->slug_id) && ! empty($request->slug_id)) {
            $id = $request->slug_id;
            $categories->where('slug_id', $request->slug_id);
        }

        $total = $categories->clone()->count();
        $result = $categories->clone()->with('translations')->orderBy('id', 'ASC')->skip($offset)->take($limit)->get()->map(function ($category) {
            if ($category) {
                $category->translated_name = $category->translated_name;
            }

            return $category;
        });

        $result->map(function ($result) {
            $result['meta_image'] = $result->image;
        });

        if (! $result->isEmpty()) {
            $response['error'] = false;
            $response['message'] = trans('Data Fetched Successfully');
            foreach ($result as $row) {
                $parameterData = $row->parameters;
                if (collect($parameterData)->isNotEmpty()) {
                    $parameterData = $parameterData->map(function ($item) {
                        $item->translated_name = $item->translated_name;
                        $item->translated_option_value = $item->translated_option_value;
                        unset($item->assigned_parameter);

                        return $item;
                    });
                }
                $row->parameter_types = collect($parameterData)->values()->toArray();
            }

            $response['total'] = $total;
            $response['data'] = $result;
        } else {
            $response['error'] = false;
            $response['message'] = trans('No Data Found');
            $response['data'] = [];
        }

        return response()->json($response);
    }
}
