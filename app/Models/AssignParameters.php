<?php

namespace App\Models;

use App\Services\FileService;
use App\Traits\HasAppTimezone;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class AssignParameters extends Model
{
    use HasFactory, HasAppTimezone;
    protected $dates = ['created_at', 'updated_at', 'deleted_at'];

    protected $table = 'assign_parameters';

    protected $fillable = [
        'modal_type',
        'modal_id',
        'property_id',
        'parameter_id',
        'value'
    ];

    protected static function boot()
    {
        parent::boot();

        static::deleting(function ($parameter) {
            if (empty($parameter->value)) {
                return;
            }

            $folderPath = config('global.PARAMETER_IMG_PATH');
            $filePath = $folderPath . $parameter->value;

            // Define allowed mime types
            $allowedMimeTypes = [
                'image/jpeg', 'image/png', 'image/jpg', 'image/gif', 'image/webp',
                'application/pdf',
                'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'text/plain'
            ];

            // Check if file exists via FileService
            if (FileService::fileExists($filePath)) {
                try {
                    // Get absolute path safely
                    $absolutePath = FileService::getAbsolutePath(config('filesystems.default'), $filePath);

                    if ($absolutePath && file_exists($absolutePath)) {
                        $mimeType = mime_content_type($absolutePath);

                        // Delete only if mime is allowed
                        if (in_array($mimeType, $allowedMimeTypes, true)) {
                            FileService::delete($folderPath, $parameter->value);
                        }
                    }
                } catch (\Exception $e) {
                    Log::warning('Parameter file delete failed', [
                        'file' => $filePath,
                        'error' => $e->getMessage()
                    ]);
                }
            }
        });
    }




    public function modal()
    {
        return $this->morphTo();
    }
    public function parameter()
    {
        return  $this->belongsTo(parameter::class,'parameter_id');
    }


    public function getValueAttribute($value)
    {
        if(!empty($value)){
            $a = json_decode($value, true);
            if (json_last_error() == JSON_ERROR_NONE) {
                if ($a == NULL) {
                    /** Was Getting Null in string that's why commented $value return code */
                    // return $value;
                    return "";
                } else {
                    return $a;
                }
            }else{
                return $value;
            }
        }
        return "";
    }
//     public function getValueAttribute($value)
// {
//     // Try to decode JSON strings
//     $decoded = json_decode($value, true);
//     if ($decoded !== null) {
//         return $decoded;
//     }

//     // Try to convert numeric strings to numbers
//     if (is_numeric($value)) {
//         if (strpos($value, '.') !== false) {
//             return floatval($value);
//         } else {
//             return intval($value);
//         }
//     }

//     // Otherwise return the original string
//     return $value;
// }

}
