<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\Contactrequests;
use App\Models\CustomPage;
use App\Models\Faq;
use App\Models\OutdoorFacilities;
use App\Models\report_reasons;
use App\Models\SeoSettings;
use App\Models\Slider;
use App\Services\ApiResponseService;
use App\Services\HelperService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class ContentApiController extends Controller
{
    public function get_articles(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'offset' => 'nullable|integer|min:0',
            'limit' => 'nullable|integer|min:1|max:200',
            'category_id' => 'nullable|integer',
            'id' => 'nullable|integer',
            'slug_id' => 'nullable|string',
        ]);
        if ($validator->fails()) {
            ApiResponseService::validationError($validator->errors()->first());
        }
        $offset = isset($request->offset) ? $request->offset : 0;
        $limit = isset($request->limit) ? $request->limit : 10;
        $article = Article::with('category:id,category,slug_id', 'category.translations', 'translations')->select('id', 'slug_id', 'image', 'title', 'description', 'view_count', 'meta_title', 'meta_description', 'meta_keywords', 'category_id', 'created_at');

        if (isset($request->category_id)) {
            $category_id = $request->category_id;
            if ($category_id == 0) {
                $article = $article->clone()->where('category_id', '');
            } else {

                $article = $article->clone()->where('category_id', $category_id);
            }
        }

        if (isset($request->id)) {
            $similarArticles = $article->clone()->where('id', '!=', $request->id)->get()->map(function ($item) {
                if ($item->category) {
                    $item->category->translated_name = $item->category->translated_name;
                }
                $item->translated_title = $item->translated_title;
                $item->translated_description = $item->translated_description;

                return $item;
            });
            $article = $article->clone()->where('id', $request->id);
            $article->increment('view_count');
        } elseif (isset($request->slug_id)) {
            $similarArticles = $article->clone()->where('slug_id', '!=', $request->slug_id)->get()->map(function ($item) {
                if ($item->category) {
                    $item->category->translated_name = $item->category->translated_name;
                }
                $item->translated_title = $item->translated_title;
                $item->translated_description = $item->translated_description;

                return $item;
            });
            $article = $article->clone()->where('slug_id', $request->slug_id);
            if (! $request->has('with_seo') || ($request->has('with_seo') && $request->with_seo != 1)) {
                $article->increment('view_count');
            }
        }

        $total = $article->clone()->get()->count();
        $result = $article->clone()->orderBy('id', 'ASC')->skip($offset)->take($limit)->get()->map(function ($item) {
            if ($item->category) {
                $item->category->translated_name = $item->category->translated_name;
            }
            $item->translated_title = $item->translated_title;
            $item->translated_description = $item->translated_description;

            unset($item->meta_image);
            $item->meta_image = $item->image;

            $item->posted_on = $item->created_at->diffForHumans();

            return $item;
        });
        if (! $result->isEmpty()) {
            $response['data'] = $result;
            $response['similar_articles'] = $similarArticles ?? [];
            $response['error'] = false;
            $response['message'] = trans('Data Fetched Successfully');
            $response['total'] = $total;
            $response['data'] = $result;
        } else {
            $response['error'] = false;
            $response['message'] = trans('No Data Found');
            $response['total'] = $total;
            $response['data'] = [];
        }

        return response()->json($response);
    }

    public function getSlider(Request $request)
    {
        $sliderData = Slider::select('id', 'type', 'image', 'web_image', 'category_id', 'propertys_id', 'show_property_details', 'link')->with(['category' => function ($query) {
            $query->select('id,category')->where('status', 1)->with('translations');
        }], 'property:id,title,title_image,price,propery_type as property_type')->orderBy('id', 'desc')->get()->map(function ($slider) {
            if (collect($slider->property)->isNotEmpty()) {
                $slider->property->parameters = $slider->property->parameters;
                if ($slider->category) {
                    $slider->category->translated_name = $slider->category->translated_name;
                }
            }

            return $slider;
        });

        if (collect($sliderData)->isNotEmpty()) {
            $response['error'] = false;
            $response['message'] = trans('Data Fetched Successfully');
            $response['data'] = $sliderData;
        } else {
            $response['error'] = false;
            $response['message'] = trans('No Data Found');
            $response['data'] = [];
        }

        return response()->json($response);
    }

    public function get_custom_pages(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'offset' => 'nullable|integer|min:0',
            'limit' => 'nullable|integer|min:1|max:200',
            'id' => 'nullable|integer',
            'slug_id' => 'nullable|string',
        ]);
        if ($validator->fails()) {
            ApiResponseService::validationError($validator->errors()->first());
        }
        $offset = $request->offset ?? 0;
        $limit = $request->limit ?? 10;

        $query = CustomPage::withCurrentLanguageTranslations()
            ->select('id', 'slug_id', 'title', 'icon', 'content', 'status')
            ->where('status', 1);

        if ($request->filled('id')) {
            $query->where('id', $request->id);
        } elseif ($request->filled('slug_id')) {
            $query->where('slug_id', $request->slug_id);
        }

        $total = $query->count();
        $result = $query->orderBy('id', 'ASC')->skip($offset)->take($limit)->get()->map(function ($item) {
            $item->translated_title = $item->translated_title;
            $item->translated_content = $item->translated_content;

            return $item;
        });

        $response['error'] = false;
        $response['message'] = $result->isEmpty() ? trans('No Data Found') : trans('Data Fetched Successfully');
        $response['total'] = $total;
        $response['data'] = $result;

        return response()->json($response);
    }

    public function getFaqData(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'offset' => 'nullable|integer|min:0',
            'limit' => 'nullable|integer|min:1|max:200',
            'user_type' => 'nullable|in:user,agent',
        ]);
        if ($validator->fails()) {
            ApiResponseService::validationError($validator->errors()->first());
        }
        try {
            $offset = isset($request->offset) ? $request->offset : 0;
            $limit = isset($request->limit) ? $request->limit : 10;

            $userType = $request->input('user_type', 'user');
            $faqsQuery = Faq::where('status', 1)->where('user_type', $userType);
            $totalData = $faqsQuery->clone()->count();
            $faqsData = $faqsQuery->clone()->with('translations')->select('id', 'question', 'answer')->orderBy('id', 'DESC')->skip($offset)->take($limit)->get()->map(function ($faq) {
                $faq->translated_question = $faq->translated_question;
                $faq->translated_answer = $faq->translated_answer;

                return $faq;
            });
            $response = [
                'error' => false,
                'total' => $totalData ?? 0,
                'data' => $faqsData,
                'message' => trans('Data Fetched Successfully'),
            ];

            return response()->json($response);
        } catch (Exception $e) {
            $response = [
                'error' => true,
                'message' => trans('Something Went Wrong'),
            ];

            return response()->json($response, 500);
        }
    }

    public function get_facilities(Request $request)
    {
        $facilities = OutdoorFacilities::with('translations');

        // if (isset($request->search) && !empty($request->search)) {
        //     $search = $request->search;
        //     $facilities->where('category', 'LIKE', "%$search%");
        // }

        if (isset($request->id) && ! empty($request->id)) {
            $id = $request->id;
            $facilities->where('id', '=', $id);
        }
        $total = $facilities->clone()->count();
        $result = $facilities->clone()->get()->map(function ($facility) {
            $facility->translated_name = $facility->translated_name;

            return $facility;
        });

        if (! $result->isEmpty()) {
            $response['error'] = false;
            $response['message'] = trans('Data Fetched Successfully');

            $response['total'] = $total;
            $response['data'] = $result;
        } else {
            $response['error'] = false;
            $response['message'] = trans('No Data Found');
            $response['data'] = [];
        }

        return response()->json($response);
    }

    public function get_report_reasons(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'nullable|integer',
        ]);
        if ($validator->fails()) {
            ApiResponseService::validationError($validator->errors()->first());
        }
        $reportReasonQuery = report_reasons::with('translations');

        if (isset($request->id) && ! empty($request->id)) {
            $id = $request->id;
            $reportReasonQuery->where('id', $id);
        }
        $total = $reportReasonQuery->clone()->count();
        $result = $reportReasonQuery->clone()->get()->map(function ($reportReason) {
            $reportReason->translated_reason = $reportReason->translated_reason;

            return $reportReason;
        });

        if (! $result->isEmpty()) {
            $response['error'] = false;
            $response['message'] = trans('Data Fetched Successfully');

            $response['total'] = $total;
            $response['data'] = $result;
        } else {
            $response['error'] = false;
            $response['message'] = trans('No Data Found');
            $response['data'] = [];
        }

        return response()->json($response);
    }

    public function get_seo_settings(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'offset' => 'nullable|integer|min:0',
            'limit' => 'nullable|integer|min:1|max:200',
            'page' => 'nullable|string',
        ]);
        if ($validator->fails()) {
            ApiResponseService::validationError($validator->errors()->first());
        }
        try {
            $offset = isset($request->offset) ? $request->offset : 0;
            $limit = isset($request->limit) ? $request->limit : 10;

            $seoSettingQuery = SeoSettings::select('id', 'page', 'image', 'title', 'description', 'keywords', 'schema_markup')
                ->when($request->page, function ($query) use ($request) {
                    $query->where('page', 'LIKE', "%$request->page%");
                })
                ->when(! $request->page, function ($query) {
                    $query->where('page', 'LIKE', '%homepage%');
                });

            $total = $seoSettingQuery->clone()->count();
            $result = $seoSettingQuery->skip($offset)->take($limit)->get();

            if (collect($result)->isNotEmpty()) {
                ApiResponseService::successResponse('Data Fetched Successfully', $result, ['total' => $total]);
            } else {
                ApiResponseService::successResponse('No Data Found');
            }
        } catch (Exception $e) {
            ApiResponseService::errorResponse();
        }
    }

    public function contactUs(Request $request)
    {
        $validator = Validator::make($request->all(), [

            'first_name' => 'required',
            'last_name' => 'required',
            'email' => 'required',
            'subject' => 'required',
            'message' => 'required',
        ]);

        if (! $validator->fails()) {

            $contactrequest = new Contactrequests;
            $contactrequest->first_name = $request->first_name;
            $contactrequest->last_name = $request->last_name;
            $contactrequest->email = $request->email;
            $contactrequest->subject = $request->subject;
            $contactrequest->message = $request->message;
            $contactrequest->save();
            $response['error'] = false;
            $response['message'] = trans('Contact Request Send Successfully');
        } else {

            $response['error'] = true;
            $response['message'] = $validator->errors()->first();
        }

        return response()->json($response);
    }

    public function calculateMortgageCalculator(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'down_payment' => 'nullable|lt:loan_amount',
            'show_all_details' => 'nullable|in:1',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'error' => true,
                'message' => $validator->errors()->first(),
            ]);
        }
        try {
            $loanAmount = $request->loan_amount; // Loan amount
            $downPayment = $request->down_payment; // Down payment
            $interestRate = $request->interest_rate; // Annual interest rate in percentage
            $loanTermYear = $request->loan_term_years; // Loan term in years
            $showAllDetails = 0;
            if ($request->show_all_details == 1) {
                if (Auth::guard('sanctum')->check()) {
                    $packageLimit = HelperService::checkPackageLimit(config('constants.FEATURES.MORTGAGE_CALCULATOR_DETAIL.TYPE', true, true, $request->user_active_role));
                    if ($packageLimit == true) {
                        $showAllDetails = 1;
                    }
                }
            }

            $schedule = $this->mortgageCalculation($loanAmount, $downPayment, $interestRate, $loanTermYear, $showAllDetails);
            ApiResponseService::successResponse('Data Fetched Successfully', $schedule, [], 200);
        } catch (Exception $e) {
            ApiResponseService::logErrorResponse($e, $e->getMessage());
        }
    }

    private function roundArrayValues($array, $pointsValue)
    {
        return array_map(function ($item) use ($pointsValue) {
            if (is_array($item)) {
                return $this->roundArrayValues($item, $pointsValue); // Recursive call
            }

            return is_numeric($item) ? round($item, $pointsValue) : $item; // Base Case
        }, $array);
    }

    private function mortgageCalculation($loanAmount, $downPayment, $interestRate, $loanTermYear, $showAllDetails)
    {
        if ($downPayment > 0) {
            $downPayment = (int) $downPayment;
            $loanAmount = $loanAmount - $downPayment;
        }

        // Convert annual interest rate to monthly interest rate
        $monthlyInterestRate = ($interestRate / 100) / 12;

        // Convert loan term in years to months
        $loanTermMonths = $loanTermYear * 12;

        // Calculate monthly payment
        $monthlyPayment = $loanAmount * ($monthlyInterestRate * pow(1 + $monthlyInterestRate, $loanTermMonths)) / (pow(1 + $monthlyInterestRate, $loanTermMonths) - 1);

        // Initialize an array to store the mortgage schedule
        $schedule = [];
        $schedule['main_total'] = [];

        // Initialize main totals
        $mainTotal = [
            'principal_amount' => $loanAmount,
            'down_payment' => $downPayment,
            'payable_interest' => 0,
            'monthly_emi' => $monthlyPayment,
            'total_amount' => 0,
        ];

        // Get current year and month
        $currentYear = date('Y');
        $currentMonth = date('n');

        // Initialize the remaining balance
        $remainingBalance = $loanAmount;

        // Loop through each month
        for ($i = 0; $i < $loanTermMonths; $i++) {
            $month = ($currentMonth + $i) % 12; // Ensure month wraps around by using modulo 12, so it does not exceed 12
            $year = $currentYear + floor(($currentMonth + $i - 1) / 12); // Calculate the year by incrementing when months exceed December

            // Correct month format
            $month = $month === 0 ? 12 : $month;

            // Calculate interest and principal
            $interest = $remainingBalance * $monthlyInterestRate;
            $principal = $monthlyPayment - $interest;
            $remainingBalance -= $principal;

            // Ensure remaining balance is not negative
            if ($remainingBalance < 0) {
                $remainingBalance = 0;
            }

            // Update yearly totals
            if ($showAllDetails && ! isset($schedule['yearly_totals'][$year])) {
                $schedule['yearly_totals'][$year] = [
                    'year' => $year,
                    'monthly_emi' => 0,
                    'principal_amount' => 0,
                    'interest_paid' => 0,
                    'remaining_balance' => $remainingBalance,
                    'monthly_totals' => [],
                ];
            }

            if ($showAllDetails) {
                $schedule['yearly_totals'][$year]['interest_paid'] += $interest;
                $schedule['yearly_totals'][$year]['principal_amount'] += $principal;

                // Store monthly totals
                $schedule['yearly_totals'][$year]['monthly_totals'][] = [
                    'month' => strtolower(date('F', mktime(0, 0, 0, $month, 1, $year))),
                    'principal_amount' => $principal,
                    'payable_interest' => $interest,
                    'remaining_balance' => $remainingBalance,
                ];
            }

            // Update main total
            $mainTotal['payable_interest'] += $interest;
        }

        // Re-index the year totals array index, year used as index
        if ($showAllDetails) {
            $schedule['yearly_totals'] = array_values($schedule['yearly_totals']);
        } else {
            $schedule['yearly_totals'] = [];
        }

        // Calculate the total amount by addition of principle amount and total payable_interest
        $mainTotal['total_amount'] = $mainTotal['principal_amount'] + $mainTotal['payable_interest'];

        // Add Main Total in Schedule Variable
        $schedule['main_total'] = $mainTotal;

        // Round off values for display
        $schedule['main_total'] = $this->roundArrayValues($schedule['main_total'], 2);
        $schedule['yearly_totals'] = $this->roundArrayValues($schedule['yearly_totals'], 0);

        // Return the mortgage schedule
        return $schedule;
    }
}
