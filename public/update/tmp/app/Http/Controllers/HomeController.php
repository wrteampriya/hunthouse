<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Property;
use App\Models\Setting;
use App\Models\User;
use App\Services\FileService;
use Carbon\Carbon;
use Exception;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class HomeController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Show the application dashboard.
     *
     * @return Renderable
     */
    public function index()
    {
        $currency_symbol = Setting::where('type', 'currency_symbol')->pluck('data')->first();

        if (! has_permissions('read', 'dashboard')) {
            return redirect('dashboard')->with('error', PERMISSION_ERROR_MSG);
        } else {
            // 0:Sell 1:Rent 2:Sold 3:Rented
            $list['total_sell_property'] = Property::where('propery_type', '0')->get()->count();
            $list['total_rant_property'] = Property::where('propery_type', '1')->get()->count();

            $list['total_properties'] = Property::all()->count();
            $list['total_articles'] = Article::all()->count();
            $list['total_categories'] = Category::all()->count();
            $list['total_customer'] = Customer::all()->count();
            $list['recent_properties'] = Property::orderBy('id', 'DESC')->limit(10)->where('status', 1)->get();
            $today = now();

            /************************************************************************************ */
            // Get Month wise data
            $currentMonth = date('n');
            $monthDates = [];
            for ($month = 1; $month <= $currentMonth; $month++) {
                $monthName = Carbon::create(null, $month, 1)->translatedFormat('M');
                array_push($monthDates, "'" . $monthName . "'");
            }
            $propertiesQuery = Property::whereYear('created_at', date('Y'));

            // Calculate sell and rent counts
            $sellProperties = $propertiesQuery->clone()->where('propery_type', 0)->get();
            $rentProperties = $propertiesQuery->clone()->where('propery_type', 1)->get();

            // Create month series for sell and rent properties
            $sellMonthSeries = array_fill(0, $currentMonth, 0);
            $rentMonthSeries = array_fill(0, $currentMonth, 0);

            // Loop through sell properties and update month series
            foreach ($sellProperties as $property) {
                $monthIndex = Carbon::parse($property->created_at)->format('n') - 1; // Get the month index (0-11)
                if (isset($sellMonthSeries[$monthIndex])) {
                    $sellMonthSeries[$monthIndex]++;
                }
            }

            // Loop through rent properties and update month series
            foreach ($rentProperties as $property) {
                $monthIndex = Carbon::parse($property->created_at)->format('n') - 1; // Get the month index (0-11)
                if (isset($rentMonthSeries[$monthIndex])) {
                    $rentMonthSeries[$monthIndex]++;
                }
            }
            /************************************************************************************ */

            /************************************************************************************ */
            // Get Week wise data
            // Create an array to store the counts for each day of the week
            $sellWeekSeries = array_fill(1, 7, 0);
            $sellWeekPropertyCounts = Property::selectRaw('DAYOFWEEK(created_at) as day_of_week,COUNT(*) as count')->where('propery_type', 0)->groupBy(DB::raw('DAYOFWEEK(created_at)'))->get();
            foreach ($sellWeekPropertyCounts as $count) {
                $sellWeekSeries[$count->day_of_week] = $count->count;
            }

            $rentWeekPropertyCounts = Property::selectRaw('DAYOFWEEK(created_at) as day_of_week,COUNT(*) as count')->where('propery_type', 1)->groupBy(DB::raw('DAYOFWEEK(created_at)'))->get();
            // Create an array to store the counts for each day of the week
            $rentWeekSeries = array_fill(1, 7, 0);
            foreach ($rentWeekPropertyCounts as $count) {
                $rentWeekSeries[$count->day_of_week] = $count->count;
            }

            /************************************************************************************ */
            // Get day wise data
            $sellCountForDay = array_fill(1, 31, 0); // Initialize array for days 1 to 31
            $rentCountForDay = array_fill(1, 31, 0); // Initialize array for days 1 to 31

            // Get all properties
            $properties = $propertiesQuery->clone()->get();

            foreach ($properties as $property) {
                $day = Carbon::parse($property->created_at)->day; // Get the day of the month

                if ($property->getRawOriginal('propery_type') == 0) {
                    $sellCountForDay[$day]++;
                } elseif ($property->getRawOriginal('propery_type') == 1) {
                    $rentCountForDay[$day]++;
                }
            }

            $currentDates = range(1, 31); // Days of the month
            $sellCountForCurrentDay = array_values($sellCountForDay);
            $rentCountForCurrentDay = array_values($rentCountForDay);

            /************************************************************************************ */

            // Properties Data Query
            $properties = Property::select('id', 'category_id', 'title', 'price', 'title_image', 'latitude', 'longitude', 'city', 'total_click', 'propery_type')->with('category')->where('total_click', '>', 0)->orderBy('total_click', 'DESC')->limit(10)->get()->map(function ($property) {
                $property->property_type = ucfirst($property->propery_type);
                $property->promoted = $property->is_promoted;

                return $property;
            });

            // Get Category Data
            $getCategory = Category::withCount('properties')->get();
            $category_name = [];
            $category_count = [];
            foreach ($getCategory as $key => $value) {
                array_push($category_name, '`'.$value->category.'`');
                array_push($category_count, $value->properties_count);
            }

            // Prepare the chart data
            $chartData = [
                'sellmonthSeries' => $sellMonthSeries,
                'sellcountForCurrentDay' => $sellCountForCurrentDay,
                'rentcountForCurrentDay' => $rentCountForCurrentDay,
                'sellweekSeries' => $sellWeekSeries,
                'rentweekSeries' => $rentWeekSeries,
                'rentmonthSeries' => $rentMonthSeries,
                'weekDates' => [
                    0 => "'" . Carbon::now()->startOfWeek(Carbon::SUNDAY)->translatedFormat('D') . "'",
                    1 => "'" . Carbon::now()->startOfWeek(Carbon::SUNDAY)->addDays(1)->translatedFormat('D') . "'",
                    2 => "'" . Carbon::now()->startOfWeek(Carbon::SUNDAY)->addDays(2)->translatedFormat('D') . "'",
                    3 => "'" . Carbon::now()->startOfWeek(Carbon::SUNDAY)->addDays(3)->translatedFormat('D') . "'",
                    4 => "'" . Carbon::now()->startOfWeek(Carbon::SUNDAY)->addDays(4)->translatedFormat('D') . "'",
                    5 => "'" . Carbon::now()->startOfWeek(Carbon::SUNDAY)->addDays(5)->translatedFormat('D') . "'",
                    6 => "'" . Carbon::now()->startOfWeek(Carbon::SUNDAY)->addDays(6)->translatedFormat('D') . "'"
                ],
                'monthDates' => $monthDates,
                'currentDates' => $currentDates,
                'currentDate' => '['.Carbon::now()->format('Y-m-d').']',

            ];

            $rows = [];
            $firebase_settings = [];

            $operate = '';

            $settings['company_name'] = system_setting('company_name');
            $settings['currency_symbol'] = system_setting('currency_symbol');

            $userData = Customer::select(DB::raw('COUNT(*) as count'))
                ->whereYear('created_at', date('Y'))
                ->groupBy(DB::raw('Month(created_at)'))
                ->pluck('count');

            return view('home', compact('list', 'settings', 'properties', 'userData', 'chartData', 'currency_symbol', 'category_name', 'category_count'));
        }
    }

    public function blank_dashboard()
    {

        return view('blank_home');
    }

    public function change_password()
    {

        return view('change_password.index');
    }

    public function changeprofile()
    {
        return view('change_profile.index');
    }

    public function check_password(Request $request)
    {
        $id = Auth::id();
        $oldpassword = $request->old_password;
        $user = DB::table('users')->where('id', $id)->first();

        $response['error'] = password_verify($oldpassword, $user->password) ? true : false;

        return response()->json($response);
    }

    public function store_password(Request $request)
    {

        $confPassword = $request->confPassword;
        $id = Auth::id();
        $role = Auth::user()->type;

        $users = User::find($id);

        if (isset($confPassword) && $confPassword != '') {
            $users->password = Hash::make($confPassword);
        }

        $users->update();

        return back()->with('success', 'Password Change Successfully');
    }

    public function update_profile(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'profile_image' => 'required|image|mimes:jpeg,png,jpg,gif,svg,webp|max:2048',
            ],
                [
                    'profile_image.required' => trans('The profile image field is required.'),
                    'profile_image.image' => trans('The profile image must be an image.'),
                    'profile_image.mimes' => trans('The profile image must be a jpeg, png, jpg, gif, svg, or webp image.'),
                    'profile_image.max' => trans('File size exceeds the :max limit. Please upload a smaller image.'),
                ]);
            if ($validator->fails()) {
                return back()->with('error', $validator->errors()->first());
            }
            $id = Auth::id();
            $role = Auth::user()->type;

            $users = User::find($id);
            if ($role == 0) {
                $users->name = $request->name;
                $users->email = $request->email;
            }

            if ($request->hasFile('profile_image')) {
                $rawImage = $users->getRawOriginal('profile');
                $users->profile = FileService::compressAndReplace($request->file('profile_image'), config('global.ADMIN_PROFILE_IMG_PATH'), $rawImage);
            }
            $users->update();

            return back()->with('success', trans('Data Updated Successfully'));
        } catch (Exception $e) {
            return back()->with('error', trans('Something Went Wrong'));
        }
    }

    public function privacy_policy()
    {
        echo system_setting('privacy_policy');
    }

    public function firebase_messaging_settings(Request $request)
    {
        $file_path = public_path('firebase-messaging-sw.js');

        // Check if file exists
        if (File::exists($file_path)) {

            File::delete($file_path);
        }

        // Move new file
        $request->file->move(public_path(), 'firebase-messaging-sw.js');
    }

    public function getMapsData()
    {
        $apiKey = env('MAP_API_KEY');

        $url = 'https://maps.googleapis.com/maps/api/js?'.http_build_query([
            'libraries' => 'places',
            'key' => $apiKey, // Use the API key from the .env file
            // Add any other parameters you need here
        ]);

        return file_get_contents($url);
    }
}
