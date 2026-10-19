<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Advertisement;
use App\Models\Customer;
use App\Models\Feature;
use App\Models\OldPackage;
use App\Models\OldUserPurchasedPackage;
use App\Models\Package;
use App\Models\PackageFeature;
use App\Models\PayAsYouGo;
use App\Models\PaymentTransaction;
use App\Models\UserPackage;
use App\Models\UserPackageLimit;
use App\Models\UserPayAsYouGoCredit;
use App\Services\ApiResponseService;
use App\Services\HelperService;
use Carbon\Carbon;
use DateTime;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class PackageApiController extends Controller
{
    public function get_package(Request $request)
    {
        if ($request->platform == 'ios') {
            $packages = OldPackage::where('status', 1)
                ->where('ios_product_id', '!=', '')
                ->orderBy('price', 'ASC')
                ->get();
        } else {
            if (! $request->has('user_type') || ! in_array($request->user_type, ['user', 'agent'])) {
                ApiResponseService::validationError('user_type is required. Must be user or agent.');
            }
            $packages = Package::where('status', 1)
                ->where('user_type', $request->user_type)
                ->with(['package_features' => function ($query) {
                    $query->with(['feature' => function ($query) {
                        $query->where('status', 1)->with('translations');
                    }]);
                }, 'translations'])
                ->orderBy('price', 'ASC')
                ->get();
        }

        $packages->transform(function ($item) use ($request) {
            if (collect(Auth::guard('sanctum')->user())->isNotEmpty()) {
                $currentDate = Carbon::now()->format('Y-m-d');

                $loggedInUserId = Auth::guard('sanctum')->user()->id;
                $user_package = OldUserPurchasedPackage::where('modal_id', $loggedInUserId)->where(function ($query) use ($currentDate) {
                    $query->whereDate('start_date', '<=', $currentDate)
                        ->whereDate('end_date', '>=', $currentDate);
                });

                if ($request->type == 'property') {
                    $user_package->where('prop_status', 1);
                } elseif ($request->type == 'advertisement') {
                    $user_package->where('adv_status', 1);
                }

                $user_package = $user_package->where('package_id', $item->id)->first();

                if (! empty($user_package)) {
                    $startDate = new DateTime(Carbon::now());
                    $endDate = new DateTime($user_package->end_date);

                    // Calculate the difference between two dates
                    $interval = $startDate->diff($endDate);

                    // Get the difference in days
                    $diffInDays = $interval->days;

                    $item['is_active'] = 1;
                    $item['type'] = $item->type === 'premium_user' ? 'premium_user' : 'product_listing';

                    if (! ($item->type === 'premium_user')) {
                        $item['used_limit_for_property'] = $user_package->used_limit_for_property;
                        $item['used_limit_for_advertisement'] = $user_package->used_limit_for_advertisement;
                        $item['property_status'] = $user_package->prop_status;
                        $item['advertisement_status'] = $user_package->adv_status;
                    }

                    $item['start_date'] = $user_package->start_date;
                    $item['end_date'] = $user_package->end_date;
                    $item['remaining_days'] = $diffInDays;
                } else {
                    $item['is_active'] = 0;
                }
            }

            if (! ($item->type === 'premium_user')) {
                $item['advertisement_limit'] = $item->advertisement_limit == '' ? 'unlimited' : ($item->advertisement_limit == 0 ? 'not_available' : $item->advertisement_limit);
                $item['property_limit'] = $item->property_limit == '' ? 'unlimited' : ($item->property_limit == 0 ? 'not_available' : $item->property_limit);
            } else {
                unset($item['property_limit']);
                unset($item['advertisement_limit']);
            }

            // Add features list
            if ($item->relationLoaded('package_features')) {
                $item['features'] = $item->package_features->filter(function ($pf) {
                    return $pf->feature !== null;
                })->map(function ($package_feature) {
                    return [
                        'id' => $package_feature->feature->id,
                        'name' => $package_feature->feature->name,
                        'translated_name' => $package_feature->feature->translated_name,
                        'limit_type' => $package_feature->limit_type,
                        'limit' => $package_feature->limit,
                    ];
                })->values();
            }

            $item['translated_name'] = $item->translated_name ?? $item->name;
            $item['package_status'] = $item->package_payment_status;
            $item['payment_transaction_id'] = $item->payment_transaction_id;

            return $item;
        });

        // Sort the packages based on is_active flag (active packages first)
        $packages = $packages->sortByDesc('is_active');

        // Get all features (shared across user and agent)
        $userType = $request->user_active_role;
        $allFeatures = Feature::where('status', 1)
            ->whereIn('user_type', [$userType, 'all'])
            ->with('translations')
            ->get()
            ->map(function ($feature) {
                return [
                    'id' => $feature->id,
                    'name' => $feature->name,
                    'translated_name' => $feature->translated_name,
                ];
            });

        $response = [
            'error' => false,
            'message' => trans('Data Fetched Successfully'),
            'data' => $packages->values()->all(),
            'all_features' => $allFeatures,
        ];

        return response()->json($response);
    }

    public function assign_package(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'package_id' => 'required_without:pay_as_you_go_id|exists:packages,id',
            'pay_as_you_go_id' => 'required_without:package_id|exists:pay_as_you_gos,id',
            'product_id' => 'required_if:in_app,true',
        ]);
        if ($validator->fails()) {
            ApiResponseService::validationError($validator->errors()->first());
        }
        try {
            $loggedInUserId = Auth::user()->id;

            $payAsYouGo = null;
            $package = null;

            if ($request->in_app == 'true' || $request->in_app === true) {
                $payAsYouGo = PayAsYouGo::where('ios_product_id', $request->product_id)->first();
                if (! $payAsYouGo) {
                    $package = Package::where('ios_product_id', $request->product_id)->first();
                }
            } else {
                if ($request->has('pay_as_you_go_id') && ! empty($request->pay_as_you_go_id)) {
                    $payAsYouGo = PayAsYouGo::where('id', $request->pay_as_you_go_id)->first();
                    // if ($payAsYouGo->price > 0) {
                    //     ApiResponseService::validationError("Pay As You Go package is paid cannot assign directly");
                    // }
                } else {
                    $package = Package::where('id', $request->package_id)->first();
                    if (! $package) {
                        ApiResponseService::validationError('Package not found');
                    }
                    if ($package->package_type == 'paid') {
                        ApiResponseService::validationError('Package is paid cannot assign directly');
                    }
                }
            }
            if ($package) {
                // Block agent package purchase if user is not an approved agent
                if ($package->user_type === 'agent' && ! Auth::user()->is_agent) {
                    ApiResponseService::validationError('You must be an approved agent to purchase this package. Please apply for agent verification first.');
                }

                // Validate package user_type matches user's active role
                $userActiveRole = $request->user_active_role; // This should be set by ActiveRoleMiddleware
                // dd($userActiveRole, $package->user_type);
                if ($package->user_type !== $userActiveRole) {
                    ApiResponseService::validationError('This package is for '.$package->user_type.'. Please switch to '.$package->user_type.' mode to purchase this package.');
                }

                // dd($loggedInUserId, $package->id);
                // Check if user already has an active package
                $isAllFeatureLimitExits = HelperService::checkPackageLimitExists($loggedInUserId, $package->id, $userActiveRole);
                if ($isAllFeatureLimitExits == true) {
                    ApiResponseService::validationError('same package purchase in past have all features limits available');
                }

                // Check if package is one_time and user already purchased it
                if ($package->purchase_type == 'one_time' && HelperService::checkUserPurchasedPackage($loggedInUserId, $package->id)) {
                    ApiResponseService::validationError('This package can only be purchased once');
                }
            }

            if (collect($payAsYouGo)->isNotEmpty()) {
                DB::beginTransaction();

                // Create Payment Transaction log first
                $paymentTransaction = PaymentTransaction::create([
                    'user_id' => $loggedInUserId,
                    'pay_as_you_go_id' => $payAsYouGo->id,
                    'amount' => 0, // Assigned directly or via in app
                    'payment_gateway' => ($request->in_app == 'true' || $request->in_app === true) ? 'in_app' : null,
                    'payment_type' => ($request->in_app == 'true' || $request->in_app === true) ? 'in app purchase' : 'free',
                    'payment_status' => 'success',
                    'order_id' => Str::uuid(),
                    'transaction_id' => Str::uuid(),
                ]);

                // Assign Pay As You Go Credit to user
                UserPayAsYouGoCredit::create([
                    'user_id' => $loggedInUserId,
                    'pay_as_you_go_id' => $payAsYouGo->id,
                    'payment_transaction_id' => $paymentTransaction->id,
                    'used' => 0,
                ]);
                DB::commit();
                ApiResponseService::successResponse('Pay As You Go Package Assigned Successfully');

            } elseif (collect($package)->isNotEmpty()) {
                DB::beginTransaction();
                // Assign Package to user
                $userPackage = UserPackage::create([
                    'package_id' => $package->id,
                    'user_id' => $loggedInUserId,
                    'start_date' => Carbon::now(),
                    'end_date' => $package->package_type == 'unlimited' ? null : Carbon::now()->addHours($package->duration),
                    'role_context' => $package->user_type,
                ]);

                // Create Payment Transaction
                PaymentTransaction::create([
                    'user_id' => $loggedInUserId,
                    'package_id' => $package->id,
                    'amount' => 0,
                    'payment_gateway' => ($request->in_app == 'true' || $request->in_app === true) ? 'in_app' : null,
                    'payment_type' => ($request->in_app == 'true' || $request->in_app === true) ? 'in app purchase' : 'free',
                    'payment_status' => 'success',
                    'order_id' => Str::uuid(),
                    'transaction_id' => Str::uuid(),
                ]);

                // Assign limited count feature to user with limits
                $packageFeatures = PackageFeature::where(['package_id' => $package->id, 'limit_type' => 'limited'])->get();
                if (collect($packageFeatures)->isNotEmpty()) {
                    $userPackageLimitData = [];
                    foreach ($packageFeatures as $key => $feature) {
                        $userPackageLimitData[] = [
                            'user_package_id' => $userPackage->id,
                            'package_feature_id' => $feature->id,
                            'total_limit' => $feature->limit,
                            'used_limit' => 0,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                    }

                    if (! empty($userPackageLimitData)) {
                        UserPackageLimit::insert($userPackageLimitData);
                    }
                }
                DB::commit();
                ApiResponseService::successResponse('Package Purchased Successfully');
            } else {
                ApiResponseService::validationError('Package not found');
            }
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Assign Package Error: '.$e->getMessage().' at '.$e->getFile().':'.$e->getLine());
            ApiResponseService::errorResponse();
        }
    }

    public function user_purchase_package(Request $request)
    {

        $start_date = Carbon::now();
        $validator = Validator::make($request->all(), [
            'package_id' => 'required',
        ]);

        if (! $validator->fails()) {
            $loggedInUserId = Auth::user()->id;
            if (isset($request->flag)) {
                $user_exists = OldUserPurchasedPackage::where('modal_id', $loggedInUserId)->get();
                if ($user_exists) {
                    OldUserPurchasedPackage::where('modal_id', $loggedInUserId)->delete();
                }
            }

            $package = Package::find($request->package_id);
            $user = Customer::find($loggedInUserId);
            $data_exists = OldUserPurchasedPackage::where('modal_id', $loggedInUserId)->get();
            if (count($data_exists) == 0 && $package) {
                $user_package = new OldUserPurchasedPackage;
                $user_package->modal()->associate($user);
                $user_package->package_id = $request->package_id;
                $user_package->start_date = $start_date;
                $user_package->end_date = $package->duratio != 0 ? Carbon::now()->addDays($package->duration) : null;
                $user_package->save();

                $user->subscription = 1;
                $user->update();

                $response['error'] = false;
                $response['message'] = trans('Purchased Package Added Successfully');
            } else {
                $response['error'] = true;
                $response['message'] = trans('Data Already Exists Or Package Not Found Or Add Flag For Add New Package');
            }
        } else {
            $response['error'] = true;
            $response['message'] = trans('Please fill all data and Submit');
        }

        return response()->json($response);
    }

    public function removeAllPackages(Request $request)
    {
        try {
            DB::beginTransaction();

            $loggedInUserId = Auth::user()->id;

            // Remove only payment transactions for the current role
            $paymentTransaction = PaymentTransaction::where(['user_id' => $loggedInUserId, 'role_context' => $request->user_active_role])->get();
            foreach ($paymentTransaction as $transaction) {
                $transaction->bank_receipt_files()->delete();
                $transaction->delete();
            }

            // Remove only user packages matching the current role
            $userPackage = UserPackage::where(['user_id' => $loggedInUserId, 'role_context' => $request->user_active_role])->get();
            foreach ($userPackage as $package) {
                $package->delete();
            }

            DB::commit();
            $response = [
                'error' => false,
                'message' => trans('Data Deleted Successfully'),
            ];

            return response()->json($response);
        } catch (Exception $e) {
            DB::rollback();
            $response = [
                'error' => true,
                'message' => trans('Something Went Wrong'),
            ];

            return response()->json($response, 500);
        }
    }

    public function getFeatures(Request $request)
    {
        try {
            $userType = $request->user_active_role;
            $features = Feature::where('status', 1)->whereIn('user_type', [$userType, 'all'])->with('translations')->get()->map(function ($feature) {
                $feature->translated_name = $feature->translated_name;

                return $feature;
            });
            ApiResponseService::successResponse('Data Fetched Successfully', $features);
        } catch (Exception $e) {
            ApiResponseService::errorResponse();
        }
    }

    public function getFeaturedData(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'type' => 'required|in:property,project',
            ]);

            if ($validator->fails()) {
                ApiResponseService::validationError($validator->errors()->first());
            }
            $offset = isset($request->offset) ? $request->offset : 0;
            $limit = isset($request->limit) ? $request->limit : 10;
            $loggedInUserID = Auth::user()->id;
            $advertisementQuery = Advertisement::select('id', 'status', 'start_date', 'end_date', 'property_id', 'project_id')->where('role_context', $request->user_active_role);

            if ($request->type == 'property') {
                $advertisementQuery->whereHas('property', function ($query) use ($loggedInUserID) {
                    $query->where(['post_type' => 1, 'added_by' => $loggedInUserID]);
                })->with('property:id,category_id,slug_id,title,propery_type,city,state,country,price,title_image,is_premium,rentduration,role_context', 'property.category:id,category,image', 'property.translations');
            } else {
                $advertisementQuery->whereHas('project', function ($query) use ($loggedInUserID) {
                    $query->where(['added_by' => $loggedInUserID]);
                })->with('project:id,category_id,slug_id,title,type,city,state,country,image,role_context', 'project.category:id,category,image', 'project.translations');
            }

            $total = $advertisementQuery->count();
            $data = $advertisementQuery->take($limit)->skip($offset)->orderBy('id', 'DESC')->get()->map(function ($item) {
                if ($item->property) {
                    if ($item->property->category) {
                        $item->property->category->translated_name = $item->property->category->translated_name;
                    }
                    $item->property->translated_title = $item->property->translated_title;
                    $item->property->translated_description = $item->property->translated_description;
                    $item->property->is_premium = $item->property->is_premium == 1 ? true : false;
                    $item->property->property_type = $item->property->propery_type;
                }
                if ($item->project) {
                    $item->project->translated_title = $item->project->translated_title;
                    $item->project->translated_description = $item->project->translated_description;
                    if ($item->project->category) {
                        $item->project->category->translated_name = $item->project->category->translated_name;
                    }
                }

                return $item;
            });

            ApiResponseService::successResponse('Data Fetched Successfully', $data, ['total' => $total]);
        } catch (Exception $e) {
            return ApiResponseService::errorResponse();
        }
    }

    public function getPackages(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'platform_type' => 'nullable|in:ios',
        ]);

        if ($validator->fails()) {
            ApiResponseService::validationError($validator->errors()->first());
        }
        try {
            $auth = Auth::guard('sanctum');
            // user_type from param is used for ACTIVE (purchased) packages
            $activeRoleForPurchased = $request->user_active_role;
            // dd($activeRoleForPurchased);

            // X-Active-Role from header is used for AVAILABLE packages list
            $activeRoleForList = $request->user_active_role;

            $packageQuery = Package::where('user_type', $activeRoleForList);
            $filteredPackageQuery = $packageQuery->clone()->when($request->has('platform_type') && $request->platform_type == 'ios', function ($query) {
                $query->where(function ($query) {
                    $query->whereNotNull('ios_product_id')->orWhere('package_type', 'free');
                });
            });

            $getActivePackages = [];
            $getAllActivePackageIds = [];

            if ($auth->check()) {
                $userId = $auth->user()->id;

                // Only return active packages for the requested user_type
                // Pass $activeRoleForPurchased explicitly
                $getAllActivePackageIds = HelperService::getAllActivePackageIds($userId, $activeRoleForPurchased);

                if (! empty($getAllActivePackageIds)) {
                    $getActivePackages = UserPackage::whereIn('package_id', $getAllActivePackageIds)->where('user_id', $userId)
                        // Note: .forRole() might be restricted to activeRoleForList, so we manually filter if needed
                        // or ensure forRole() accepts an argument. If it doesn't, we might need to handle it.
                        // Based on the requirement, active packages MUST match activeRoleForPurchased
                        ->whereHas('package', function ($q) use ($activeRoleForPurchased) {
                            $q->where('user_type', $activeRoleForPurchased);
                        })
                        ->with('user_package_limits.package_feature.feature.translations')
                        ->with(['package' => function ($query) use ($userId) {
                            $query->with(['package_features' => function ($query) use ($userId) {
                                $query->with(['feature.translations', 'user_package_limits' => function ($subQuery) use ($userId) {
                                    $subQuery->whereHas('user_package', function ($userQuery) use ($userId) {
                                        $userQuery->where('user_id', $userId)->orderBy('id', 'desc');
                                    });
                                }]);
                            }, 'translations']);
                        }])
                        ->groupBy('package_id')
                        ->get()
                        ->map(function ($userPackage) {
                            return [
                                'id' => $userPackage->package->id,
                                'name' => $userPackage->package->name,
                                'package_type' => $userPackage->package->package_type,
                                'ios_product_id' => $userPackage->package->ios_product_id,
                                'price' => $userPackage->package->price,
                                'duration' => $userPackage->package->duration,
                                'start_date' => $userPackage->start_date,
                                'end_date' => $userPackage->end_date,
                                'is_renewed' => $userPackage->is_renewed,
                                'is_renew_allowed' => $userPackage->is_renew_allowed,
                                'created_at' => $userPackage->package->created_at,
                                'package_status' => $userPackage->package->package_payment_status,
                                'payment_transaction_id' => $userPackage->package->payment_transaction_id,
                                'user_type' => $userPackage->package->user_type,
                                'translated_name' => $userPackage->package->translated_name ?? $userPackage->package->name,
                                'features' => $userPackage->package->package_features->map(function ($pacakgeFeatures) {
                                    return [
                                        'id' => $pacakgeFeatures->feature->id,
                                        'name' => $pacakgeFeatures->feature->name,
                                        'translated_name' => $pacakgeFeatures->feature->translated_name ?? $pacakgeFeatures->feature->name,
                                        'limit_type' => $pacakgeFeatures->limit_type,
                                        'limit' => $pacakgeFeatures->limit,
                                        'used_limit' => $pacakgeFeatures->limit_type == 'unlimited' ? null : $pacakgeFeatures->user_package_limits->where('package_feature_id', $pacakgeFeatures->id)->first()->used_limit ?? 0,
                                        'total_limit' => $pacakgeFeatures->limit_type == 'unlimited' ? null : $pacakgeFeatures->user_package_limits->where('package_feature_id', $pacakgeFeatures->id)->first()->total_limit ?? $pacakgeFeatures->limit,
                                    ];
                                }),
                                'is_active' => 1,
                            ];
                        });
                }
            }

            $getOtherPackagesQuery = $filteredPackageQuery->clone()->where('status', 1)->has('package_features');

            // Available packages are from the activeRoleForList (header role)
            if (! empty($getAllActivePackageIds) && $activeRoleForPurchased === $activeRoleForList) {
                $getOtherPackagesQuery = $getOtherPackagesQuery->whereNotIn('id', $getAllActivePackageIds);
            }

            $getOtherPackageData = $getOtherPackagesQuery->whereHas('package_features.feature', function ($query) {
                $query->where('status', 1);
            })->with(['package_features' => function ($query) {
                $query->with(['feature' => function ($query) {
                    $query->where('status', 1)->with('translations');
                }]);
            }, 'translations'])
                ->get()
                ->map(function ($package) {
                    if ($package->package_features) {
                        return [
                            'id' => $package->id,
                            'name' => $package->name,
                            'package_type' => $package->package_type,
                            'price' => $package->price,
                            'ios_product_id' => $package->ios_product_id,
                            'duration' => $package->duration,
                            'created_at' => $package->created_at,
                            'package_status' => $package->package_payment_status,
                            'payment_transaction_id' => $package->payment_transaction_id,
                            'user_type' => $package->user_type,
                            'translated_name' => $package->translated_name,
                            'features' => $package->package_features->map(function ($package_feature) {
                                return [
                                    'id' => $package_feature->feature->id,
                                    'name' => $package_feature->feature->name,
                                    'translated_name' => $package_feature->feature->translated_name,
                                    'limit_type' => $package_feature->limit_type,
                                    'limit' => $package_feature->limit,
                                ];
                            }),
                        ];
                    }
                });
            $userActiveRole = $request->user_active_role;
            // dd($userActiveRole);
            $features = Feature::where('status', 1)->whereIn('user_type', [$userActiveRole, 'all'])->with('translations')->get()->map(function ($feature) {
                return [
                    'id' => $feature->id,
                    'name' => $feature->name,
                    'translated_name' => $feature->translated_name,
                ];
            });
            // if active_role is agent then payasyougo must be empty
            if ($activeRoleForList === 'agent') {
                $payAsYouGoPackages = [];
            } else {

                $payAsYouGoPackages = PayAsYouGo::where('status', 1)->get();
            }
            ApiResponseService::successResponse('Data Fetched Successfully', $getOtherPackageData, [
                'active_packages' => $getActivePackages,
                'all_features' => $features,
                'pay_as_you_go' => $payAsYouGoPackages,
            ]);
        } catch (Exception $e) {
            ApiResponseService::errorResponse($e->getMessage());
        }
    }

    public function getAgentPackages(Request $request)
    {
        try {
            $packages = Package::where('status', 1)->where('user_type', ['agent', 'all'])->with(['package_features' => function ($query) {
                $query->with(['feature' => function ($query) {
                    $query->where('status', 1)->with('translations');
                }]);
            }, 'translations'])->orderBy('price', 'ASC')->get();

            $features = Feature::where('status', 1)->whereIn('user_type', ['agent', 'all'])->with('translations')->get()->map(function ($feature) {
                return [
                    'id' => $feature->id,
                    'name' => $feature->name,
                    'translated_name' => $feature->translated_name,
                ];
            });

            ApiResponseService::successResponse('Data Fetched Successfully', $packages, [
                'all_features' => $features,
            ]);
        } catch (Exception $e) {
            ApiResponseService::errorResponse();
        }
    }

    public function checkPackageLimit(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'type' => 'required|in:'.implode(',', array_column(config('constants.FEATURES'), 'TYPE')),
        ]);
        if ($validator->fails()) {
            return ApiResponseService::validationError($validator->errors()->first());
        }
        try {
            $data = HelperService::checkPackageLimit($request->type, true, true, $request->user_active_role);

            return ApiResponseService::successResponse('Data Fetched Successfully', $data);
        } catch (Exception $e) {
            return ApiResponseService::errorResponse();
        }
    }
}
