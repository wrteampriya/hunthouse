<?php

namespace App\Http\Controllers;

use Exception;
use App\Models\Category;
use App\Models\parameter;
use Illuminate\Http\Request;
use App\Services\FileService;
use App\Services\HelperService;
use App\Models\AssignParameters;
use App\Services\ResponseService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Services\BootstrapTableService;

class ParameterController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        if (!has_permissions('read', 'facility')) {
            return redirect()->back()->with('error', trans(PERMISSION_ERROR_MSG));
        }
        $languages = HelperService::getActiveLanguages();
        return view('parameter.index', compact('languages'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        // dd($request->all());
        if (!has_permissions('create', 'facility')) {
            ResponseService::errorResponse(PERMISSION_ERROR_MSG);
        }

        $request->validate([
            'parameter' => 'required|regex:/^[^\'"]*$/',
            'options'   => 'required',
            'image'     => 'required|mimes:svg|max:2048',
        ]);
        try {
            $jsonOptionValue = null;

            // Convert The option data to json encode
            if(isset($request->opt)){
                $options = array();
                if($request->options == 'radiobutton'){
                    if(count($request->opt) < 2){
                        ResponseService::validationError(trans('Radio Button must have at least 2 options'));
                    }
                }
                foreach($request->opt as $key => $value){
                    // Validate no quotes in option values
                    if(preg_match('/[\'"]/', $value)){
                        ResponseService::validationError(trans('Option values cannot contain single or double quotes'));
                    }
                    $options[] = array(
                        'value' => htmlspecialchars($value),
                    );
                    if(isset($request->option_translations[$key])){
                        foreach($request->option_translations[$key] as $translation){
                            // Validate no quotes in option translations
                            if(isset($translation['value']) && preg_match('/[\'"]/', $translation['value'])){
                                ResponseService::validationError(trans('Option translations cannot contain single or double quotes'));
                            }
                            $options[$key]['translations'][] = array(
                                'language_id' => $translation['language_id'],
                                'value' => htmlspecialchars($translation['value']),
                            );
                        }
                    }
                }
                $jsonOptionValue = json_encode($options, JSON_FORCE_OBJECT);
            }

            // Get and create if not there destination path of images to be stored
            $destinationPath = public_path('images') . config('global.PARAMETER_IMAGE_PATH');
            if (!is_dir($destinationPath)) {
                mkdir($destinationPath, 0777, true);
            }

            // Add Data to Database
            $parameter = new parameter();
            $parameter->name = $request->parameter;
            $parameter->type_of_parameter = $request->options;
            $parameter->is_required = $request->is_required ?? 0;
            $parameter->type_values = $jsonOptionValue;

            // Add Image if exists
            if ($request->hasFile('image')) {
                $path = config('global.PARAMETER_IMAGE_PATH');
                $requestFile = $request->file('image');
                $parameter->image = FileService::compressAndUpload($requestFile, $path);
            }

            // Save data
            $parameter->save();

            // Add Translations
            if(isset($request->translations) && !empty($request->translations)){
                $translationData = array();
                foreach($request->translations as $translation){
                    // Validate no quotes in parameter name translations
                    if(isset($translation['value']) && preg_match('/[\'"]/', $translation['value'])){
                        ResponseService::validationError(trans('Parameter name translations cannot contain single or double quotes'));
                    }
                    $translationData[] = array(
                        'translatable_id'   => $parameter->id,
                        'translatable_type' => 'App\Models\parameter',
                        'key'               => 'name',
                        'value'             => $translation['value'],
                        'language_id'       => $translation['language_id'],
                    );
                }
                if(!empty($translationData)){
                    HelperService::storeTranslations($translationData);
                }
            }

            ResponseService::successResponse('Parameter Successfully Added');
        } catch (Exception $e) {
            ResponseService::errorResponse("Something Went Wrong");
        }
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show(Request $request)
    {
        if (!has_permissions('read', 'facility')) {
            ResponseService::errorResponse(PERMISSION_ERROR_MSG);
        }

        $offset = $request->input('offset', 0);
        $limit = $request->input('limit', 10);

        $order = $request->input('order', 'ASC');
        $sort = 'id';

        if (isset($_GET['sort'])) {
            if ($_GET['sort'] == 'type') {
                $sort = 'type_of_parameter';
            }
            if ($_GET['sort'] == 'value') {
                $sort = 'type_values';
            }
        }

        $sql = parameter::orderBy($sort, $order)->with('translations');

        if (isset($_GET['search']) && !empty($_GET['search'])) {
            $search = $_GET['search'];
            $sql->where('id', 'LIKE', "%$search%")->orwhere('name', 'LIKE', "%$search%");
        }
        $total = $sql->count();

        if (isset($_GET['limit'])) {
            $sql->skip($offset)->take($limit);
        }

        $res = $sql->get();

        $bulkData = array();
        $bulkData['total'] = $total;
        $rows = array();
        $tempRow = array();
        $count = 1;

        foreach ($res as $row) {

            $tempRow = $row->toArray();

            $typeValue = array();
            if(!empty($row->type_values)){
                foreach($row->type_values as $key => $value){
                    if(isset($value['translations'])){
                        $typeValue[] = $value['value'];
                    }else{
                        if(isset($value['value'])){
                            $typeValue[] = $value['value'];
                        }else{
                            $typeValue[] = $value;
                        }
                    }
                }
            }

            $tempRow['value'] = !empty($typeValue) ? implode(',', $typeValue) : null;

            // $tempRow['type_values'] = $row->type_values;
            $svgClear = HelperService::getSettingData('svg_clr');
            $tempRow['svg_clr'] = !empty($svgClear) ? $svgClear : 0;

            // Build operations buttons
            $operate = '';
            if (has_permissions('update', 'facility')) {
                $operate .= BootstrapTableService::editButton('', true);
            }

            // Check if parameter is being used
            $isUsed = $this->isParameterUsed($row->id);
            $tempRow['is_used'] = $isUsed;

            if (!$isUsed && has_permissions('delete', 'facility')) {
                $operate .= BootstrapTableService::deleteAjaxButton(route('parameters.destroy', $row['id']));
            }
            $tempRow['operate'] = $operate;

            $rows[] = $tempRow;
            $count++;
        }

        $bulkData['rows'] = $rows;
        return response()->json($bulkData);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request)
    {

        try {

            $request->validate([
                'edit_name' => 'required|regex:/^[^\'"]*$/',
                'image' => 'mimes:svg'
            ]);

            if (!has_permissions('update', 'facility')) {
                return redirect()->back()->with('error', trans(PERMISSION_ERROR_MSG));
            } else {
                // $opt_value = isset($request->edit_opt) && !empty($request->edit_opt) ? json_encode($request->edit_opt, JSON_FORCE_OBJECT) : NULL;

                DB::beginTransaction();
                $id =  $request->edit_id;

                $parameter = parameter::find($id);
                $parameter->name = ($request->edit_name) ? $request->edit_name : '';
                $parameter->is_required = (isset($request->edit_is_required) && !empty($request->edit_is_required)) ? 1 : 0;

                // Handle option translations update
                if(isset($request->edit_option_values) && !empty($request->edit_option_values) &&
                   in_array($parameter->type_of_parameter, ['dropdown', 'checkbox', 'radiobutton'])) {

                    $updatedOptions = array();
                    foreach($request->edit_option_values as $optionIndex => $optionValue) {
                        // Validate no quotes in option values
                        if(preg_match('/[\'"]/', $optionValue)){
                            ResponseService::errorRedirectResponse(null, trans('Option values cannot contain single or double quotes'));
                        }
                        $optionTranslations = array();

                        // Get translations for this option
                        if(isset($request->edit_option_translations[$optionIndex])) {
                            foreach($request->edit_option_translations[$optionIndex] as $translation) {
                                if(!empty($translation['value'])) {
                                    // Validate no quotes in option translations
                                    if(preg_match('/[\'"]/', $translation['value'])){
                                        ResponseService::errorRedirectResponse(null, trans('Option translations cannot contain single or double quotes'));
                                    }
                                    $optionTranslations[] = array(
                                        'language_id' => $translation['language_id'],
                                        'value' => htmlspecialchars($translation['value'])
                                    );
                                }
                            }
                        }

                        $updatedOptions[] = array(
                            'value' => htmlspecialchars($optionValue),
                            'translations' => $optionTranslations
                        );
                    }

                    $parameter->type_values = json_encode($updatedOptions, JSON_FORCE_OBJECT);
                }

                if ($request->hasFile('image')) {
                    $path = config('global.PARAMETER_IMAGE_PATH');
                    $requestFile = $request->file('image');
                    $rawImage = $parameter->getRawOriginal('image');
                    $parameter->image = FileService::compressAndReplace($requestFile, $path, $rawImage);
                }

                $parameter->update();


                // Add Translations
                if(isset($request->translations) && !empty($request->translations)){
                    $translationData = array();
                    foreach($request->translations as $translation){
                        // Validate no quotes in parameter name translations
                        if(isset($translation['value']) && preg_match('/[\'"]/', $translation['value'])){
                            ResponseService::errorRedirectResponse(null, trans('Parameter name translations cannot contain single or double quotes'));
                        }
                        $translationData[] = array(
                            'id'                => $translation['id'] ?? null,
                            'translatable_id'   => $parameter->id,
                            'translatable_type' => 'App\Models\parameter',
                            'key'               => 'name',
                            'value'             => $translation['value'],
                            'language_id'       => $translation['language_id'],
                        );
                    }
                    if(!empty($translationData)){
                        HelperService::storeTranslations($translationData);
                    }
                }
                DB::commit();
                ResponseService::successRedirectResponse('Parameter Updated Successfully');
            }
        } catch (Exception $e) {
            DB::rollBack();
            ResponseService::logErrorResponse($e, "Something Went Wrong");
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        if (env('DEMO_MODE') && Auth::user()->email != "superadmin@gmail.com") {
            ResponseService::validationError('This is not allowed in the Demo Version');
        }

        if (!has_permissions('delete', 'facility')) {
            ResponseService::validationError(PERMISSION_ERROR_MSG);
        }

        try {
            DB::beginTransaction();

            $parameter = parameter::find($id);
            if (!$parameter) {
                ResponseService::validationError('Parameter not found');
            }

            // Check if parameter is being used
            $usageCount = 0;
            $usedIn = [];

            // Check in Categories parameter_types field
            $categoriesWithParameter = Category::where('parameter_types', 'LIKE', "%$id%")->get();
            $categoriesCount = 0;
            foreach ($categoriesWithParameter as $category) {
                $parameterTypes = explode(',', $category->parameter_types);
                if (in_array($id, $parameterTypes)) {
                    $categoriesCount++;
                }
            }
            if ($categoriesCount > 0) {
                $usageCount += $categoriesCount;
                $usedIn[] = "Categories ($categoriesCount)";
            }

            if ($usageCount > 0) {
                $message = "Cannot delete this parameter. It is being used in: " . implode(', ', $usedIn);
                ResponseService::validationError($message);
            }

            AssignParameters::where('parameter_id', $id)->delete();
            // Safe to delete
            if ($parameter->delete()) {
                // Delete the parameter image if exists
                if ($parameter->getRawOriginal('image') != '') {
                    $path = config('global.PARAMETER_IMAGE_PATH');
                    $rawImage = $parameter->getRawOriginal('image');
                    FileService::delete($path, $rawImage);
                }
            }

            DB::commit();
            ResponseService::successResponse('Parameter Deleted Successfully');
        } catch (Exception $e) {
            DB::rollBack();
            ResponseService::logErrorResponse($e, "Parameter Delete Error", "Something Went Wrong");
        }
    }

    /**
     * Check if parameter is being used in any related tables
     *
     * @param int $parameterId
     * @return bool
     */
    private function isParameterUsed($parameterId)
    {
        // Check in Categories parameter_types field
        $categoriesWithParameter = Category::where('parameter_types', 'LIKE', "%$parameterId%")->get();
        foreach ($categoriesWithParameter as $category) {
            $parameterTypes = explode(',', $category->parameter_types);
            if (in_array($parameterId, $parameterTypes)) {
                return true;
            }
        }

        return false;
    }
}
