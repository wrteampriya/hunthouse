<?php

namespace App\Models;

use App\Services\FileService;
use App\Traits\HasAppTimezone;
use App\Services\HelperService;
use App\Traits\ManageTranslations;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class parameter extends Model
{
    use HasFactory, HasAppTimezone, ManageTranslations;
    protected $dates = ['created_at', 'updated_at', 'deleted_at'];

    protected $table = 'parameters';

    protected $fillable = [
        'name',
        'type_of_parameter',
        'type_values',
        'is_required',
        'image',
        'is_demo'
    ];
    protected $hidden = ["created_at", "updated_at"];
    
    protected static function boot() {
        parent::boot();
        static::deleting(function ($parameter) {
            if(collect($parameter)->isNotEmpty()){
                // before delete() method call this

                // Delete Title Image
                if ($parameter->getRawOriginal('image') != '') {
                    $path = config('global.PARAMETER_IMG_PATH');
                    $rawImage = $parameter->getRawOriginal('image');
                    FileService::delete($path, $rawImage);
                }

                $categories = Category::get();

                foreach ($categories as $category) {
                    $parameterIds = array_filter(explode(',', $category->parameter_types)); // split and remove empty

                    // If this parameter ID exists in the list
                    if (in_array($parameter->id, $parameterIds)) {
                        // Remove the ID
                        $parameterIds = array_diff($parameterIds, [$parameter->id]);

                        // Rebuild comma-separated string
                        $updatedParameterTypes = implode(',', $parameterIds);

                        // Update the category
                        $category->update([
                            'parameter_types' => $updatedParameterTypes,
                        ]);
                    }
                }


                $assignParameters = AssignParameters::where('parameter_id', $parameter->id)->get();
                if(collect($assignParameters)->isNotEmpty()){
                    foreach ($assignParameters as $assignParameter) {
                        if(collect($assignParameter)->isNotEmpty()){
                            $assignParameter->delete(); // This will trigger the deleting and deleted events in modal
                        }
                    }
                }
            }
        });
    }


    public function getTypeValuesAttribute($value)
    {
        $a = json_decode($value, true);
        if ($a == NULL) {
            return $value;
        } else {
            foreach($a as $key => $value){
                if(is_array($value)){
                    $a[$key]['value'] = htmlspecialchars_decode($value['value'], ENT_QUOTES | ENT_HTML5);
                    if(isset($value['translations'])){
                        foreach($value['translations'] as $translationKey => $translation){
                            $translation['value'] = htmlspecialchars_decode($translation['value'], ENT_QUOTES | ENT_HTML5);
                            $a[$key]['translations'][$translationKey] = $translation;
                        }
                    }
                }
            }
            return $a;
        }
    }
    public function getImageAttribute($image)
    {
        $path = $image ? config('global.PARAMETER_IMAGE_PATH') . $image : null;
        return !empty($path) ? FileService::getFileUrl($path) : null;
    }
    public function assigned_parameter()
    {
        return $this->hasOne(AssignParameters::class, 'parameter_id');
    }

    /**
     * Translations relationship
     */
    public function translations()
    {
        return $this->morphMany(Translation::class, 'translatable');
    }

    /**
     * Get translated name attribute
     */
    public function getTranslatedNameAttribute()
    {
        return HelperService::getTranslatedData($this, $this->name, 'name');
    }

    public function getSimpleOptionValues()
    {
        $optionValues = $this->type_values;
        if ($optionValues && is_array($optionValues)) {
            $translatedValue = [];
            foreach ($optionValues as $option) {
                if(is_array($option)){
                    $translatedValue[] = array(
                        'value' => $option['value'],
                        'translated' => $option['value']
                    );
                }else{
                    $translatedValue[] = array(
                        'value' => $option,
                        'translated' => $option
                    );
                }
            }
        }else{
            $translatedValue = $optionValues;
        }
        return $translatedValue;
    }

    /**
     * Get translated option values based on content-language header
     */
    public function getTranslatedOptionValueAttribute()
    {
        $languageCode = request()->header('Content-Language') ?? app()->getLocale();

        if (empty($languageCode)) {
            return $this->getSimpleOptionValues();
        }

        // Get language ID for the code
        $languageId = cache()->remember("language_id_{$languageCode}", 3600, function() use ($languageCode) {
            return Language::where('code', $languageCode)->value('id');
        });

        if (empty($languageId)) {
            return $this->getSimpleOptionValues();
        }

        $optionValues = $this->type_values;

        // If type_values is empty or not an array, return simple values
        if (empty($optionValues) || !is_array($optionValues)) {
            return $this->getSimpleOptionValues();
        }

        $translatedValues = [];

        // Extract only translated values
        foreach ($optionValues as $option) {
            if (is_array($option)) {
                // Look for translation
                $value = $option['value'] ?? '';
                $translatedValue = $value;
                if (isset($option['translations']) && is_array($option['translations'])) {
                    foreach ($option['translations'] as $translation) {
                        if (isset($translation['language_id']) && $translation['language_id'] == $languageId && !empty($translation['value'])) {
                            $translatedValue = $translation['value'];
                            break;
                        }
                    }
                }
                $translatedValues[] = array(
                    'value' => $value,
                    'translated' => $translatedValue
                );
            } else {
                // If option is not an array, use it as is
                $translatedValues[] = array(
                    'value' => $option,
                    'translated' => $option
                );
            }
        }

        return $translatedValues;
    }
}
