<?php

/**
 * Global Image MIME Types Configuration
 *
 * This configuration allows you to define image mime types that can be used
 * across the application for both backend validation and frontend file inputs.
 *
 * You can configure different mime types for backend and frontend if needed.
 */

return [
    /**
     * Backend validation mime types
     * Used in Laravel validation rules (mimes:jpg,png,jpeg,webp)
     */
    'backend' => [
        'image' => 'jpg,png,jpeg,webp',
        'image_with_gif' => 'jpg,png,jpeg,gif,webp',
    ],

    /**
     * Frontend file input accept attribute
     * Used in HTML file input accept attribute
     */
    'frontend' => [
        'image' => 'image/jpg,image/png,image/jpeg,image/webp',
        'image_with_gif' => 'image/jpg,image/png,image/jpeg,image/gif,image/webp',
    ],

    /**
     * Helper function to get backend mime string
     */
    'getBackendMimes' => function($includeGif = false) {
        return $includeGif ? config('image_mimes.backend.image_with_gif') : config('image_mimes.backend.image');
    },

    /**
     * Helper function to get frontend accept string
     */
    'getFrontendAccept' => function($includeGif = false) {
        return $includeGif ? config('image_mimes.frontend.image_with_gif') : config('image_mimes.frontend.image');
    },
];

