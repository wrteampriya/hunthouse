<?php

namespace App\Http\Controllers;

use App\Models\Country;
use App\Models\Currency;
use App\Services\BootstrapTableService;
use App\Services\GeoNamesMapService;
use App\Services\GooglePlacesService;
use App\Services\HelperService;
use App\Services\ResponseService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use League\ISO3166\ISO3166;

class CountryController extends Controller
{
    public function index()
    {
        if (! has_permissions('read', 'country')) {
            return redirect()->back()->with('error', trans(PERMISSION_ERROR_MSG));
        }
        $languages = HelperService::getActiveLanguages(null, true);
        $currencies = Currency::where('status', 1)->get();
        $globalDefaultLanguageId = HelperService::currentContentLanguageId();
        $isoCountries = collect((new ISO3166)->all())
            ->map(fn ($c) => ['code' => $c['alpha2'], 'name' => $c['name']])
            ->sortBy('name')
            ->values();

        return view('countries.index', compact('languages', 'currencies', 'globalDefaultLanguageId', 'isoCountries'));
    }

    public function store(Request $request)
    {
        if (! has_permissions('create', 'country')) {
            return redirect()->back()->with('error', trans(PERMISSION_ERROR_MSG));
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'code' => 'required|string|in:'.$this->validIsoCodes().'|unique:countries,code',
            'default_language_id' => 'nullable|exists:languages,id',
            // Required: property/project forms auto-set and lock the currency
            // field to the selected country's resolved currency — a country
            // with no currency silently unlocks that field instead of
            // enforcing a consistent currency, defeating the point of the lock.
            'currency_id' => 'required|exists:currencies,id',
        ]);
        if ($validator->fails()) {
            return redirect()->back()->with('error', $validator->errors()->first());
        }

        try {
            DB::beginTransaction();
            // Reference point (used to find the nearest supported country when a
            // picked map location falls outside every admin-added country) is
            // derived automatically from the country name — not admin-entered.
            $geo = $this->geocodeCountryName($request->name);
            Country::create([
                'name' => $request->name,
                'code' => strtoupper($request->code),
                'default_language_id' => $request->default_language_id ?: null,
                'currency_id' => $request->currency_id ?: null,
                'latitude' => $geo['lat'] ?? null,
                'longitude' => $geo['lng'] ?? null,
                // New countries always start inactive — there's no status control
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
        if (! has_permissions('read', 'country')) {
            ResponseService::errorResponse(PERMISSION_ERROR_MSG);
        }
        $offset = $request->input('offset', 0);
        $limit = $request->input('limit', 10);
        $sort = $request->input('sort', 'id');
        $order = $request->input('order', 'DESC');

        $sql = Country::with(['defaultLanguage', 'currency'])->orderBy($sort, $order);

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
            $tempRow['default_language_name'] = $row->defaultLanguage->name ?? trans('Global Default');
            $tempRow['currency_name'] = $row->currency ? "{$row->currency->name} ({$row->currency->code})" : '-';
            $tempRow['edit_status_url'] = route('country.status-update');

            $operate = '';
            if (has_permissions('update', 'country')) {
                $operate = BootstrapTableService::editButton('', true, '#editCountryModal', 'edit-country', $row->id);
            }
            if (has_permissions('delete', 'country')) {
                $operate .= BootstrapTableService::deleteButton(route('country.destroy', $row->id), $row->id);
            }
            $tempRow['operate'] = $operate;
            $rows[] = $tempRow;
        }

        return response()->json(['total' => $total, 'rows' => $rows]);
    }

    public function update(Request $request)
    {
        if (! has_permissions('update', 'country')) {
            return redirect()->back()->with('error', trans(PERMISSION_ERROR_MSG));
        }

        $validator = Validator::make($request->all(), [
            'edit_id' => 'required|exists:countries,id',
            'edit_name' => 'required|string|max:255',
            'edit_code' => 'required|string|in:'.$this->validIsoCodes().'|unique:countries,code,'.$request->edit_id,
            'edit_default_language_id' => 'nullable|exists:languages,id',
            'edit_currency_id' => 'required|exists:currencies,id',
        ]);
        if ($validator->fails()) {
            return redirect()->back()->with('error', $validator->errors()->first());
        }

        try {
            DB::beginTransaction();
            $country = Country::findOrFail($request->edit_id);

            // Re-derive the reference point when the name changed, or when it's
            // still missing from an earlier failed geocode — the geocode result
            // is cached by name, so retrying stays cheap either way.
            $latitude = $country->latitude;
            $longitude = $country->longitude;
            if ($request->edit_name !== $country->name || empty($latitude) || empty($longitude)) {
                $geo = $this->geocodeCountryName($request->edit_name);
                $latitude = $geo['lat'] ?? null;
                $longitude = $geo['lng'] ?? null;
            }

            $country->update([
                'name' => $request->edit_name,
                'code' => strtoupper($request->edit_code),
                'default_language_id' => $request->edit_default_language_id ?: null,
                'currency_id' => $request->edit_currency_id ?: null,
                'latitude' => $latitude,
                'longitude' => $longitude,
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
        if (! has_permissions('update', 'country')) {
            ResponseService::errorResponse(PERMISSION_ERROR_MSG);
        }
        $validator = Validator::make($request->all(), [
            'id' => 'required|exists:countries,id',
            'status' => 'required|in:0,1',
        ]);
        if ($validator->fails()) {
            ResponseService::validationError($validator->errors()->first());
        }
        try {
            Country::where('id', $request->id)->update(['status' => $request->status]);
            ResponseService::successResponse(trans('Data Updated Successfully'));
        } catch (Exception $e) {
            ResponseService::logErrorResponse($e, trans('Something Went Wrong'));
        }
    }

    /**
     * Geocode a country name to its centroid, using whichever map provider
     * (Google or GeoNames/OSM) the admin panel is actually configured for —
     * geocoding via a provider that isn't set up would silently return null.
     */
    /**
     * Comma-separated ISO 3166-1 alpha-2 codes, for validating the `code`
     * field against the same list the create/edit dropdown is built from —
     * prevents invalid codes (e.g. "UAE" instead of "AE") reaching the DB.
     */
    private function validIsoCodes(): string
    {
        return cache()->remember('iso3166_alpha2_codes', 86400, function () {
            return collect((new ISO3166)->all())->pluck('alpha2')->implode(',');
        });
    }

    private function geocodeCountryName(string $name): ?array
    {
        if (system_setting('map_service_provider') === 'open_street_maps') {
            return (new GeoNamesMapService)->geocodeAddress($name);
        }

        return (new GooglePlacesService)->geocodeAddress($name);
    }

    public function destroy($id)
    {
        if (env('DEMO_MODE') && Auth::user()->email != 'superadmin@gmail.com') {
            return redirect()->back()->with('error', trans('This is not allowed in the Demo Version'));
        }
        if (! has_permissions('delete', 'country')) {
            return redirect()->back()->with('error', trans(PERMISSION_ERROR_MSG));
        }

        $country = Country::find($id);
        if (! $country) {
            return ResponseService::errorRedirectResponse(null, 'Country Not Found');
        }

        $referencingTables = ['propertys', 'projects', 'customers', 'sliders', 'faqs', 'articles', 'ad_banners'];
        foreach ($referencingTables as $tableName) {
            if (DB::table($tableName)->where('country_id', $id)->exists()) {
                return ResponseService::errorRedirectResponse(null, trans('This country has associated data and cannot be deleted. Please reassign or remove the related data first.'));
            }
        }

        if ($country->delete()) {
            ResponseService::successRedirectResponse('Data Deleted Successfully');
        } else {
            ResponseService::errorRedirectResponse(null, 'Something Went Wrong');
        }
    }
}
