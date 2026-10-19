<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class VideoUrlRule implements ValidationRule
{
    public function __construct(private ?int $type) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (empty($value)) {
            return;
        }

        // YouTube
        if ($this->type == 1) {

            $youtubePattern = '/^(https?:\/\/)?(www\.)?(youtube\.com|youtu\.be)\/(watch\?v=|embed\/|v\/|shorts\/)?[a-zA-Z0-9_-]{11}([&?].*)?$/';

            if (! preg_match($youtubePattern, $value)) {
                $fail('Invalid YouTube URL.');
            }

            return;
        }

        // Vimeo
        if ($this->type == 2) {

            $vimeoPattern = '/^(https?:\/\/)?(www\.)?(vimeo\.com\/(channels\/\w+\/|groups\/\w+\/videos\/|album\/\d+\/video\/|video\/)?|player\.vimeo\.com\/video\/)?[0-9]+([&?].*)?$/';

            if (! preg_match($vimeoPattern, $value)) {
                $fail('Invalid Vimeo URL.');
            }

            return;
        }
    }
}
