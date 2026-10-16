<?php

// Guarded with defined() (same pattern as below): config files are
// re-required on every application bootstrap, so a second bootstrap in the
// same PHP process (e.g. PHPUnit running several tests) must not fatal.
defined('PERMISSION_ERROR_MSG') or define('PERMISSION_ERROR_MSG', 'You are not authorize to operate on the module.');
defined('JWT_SECRET_KEY') or define('JWT_SECRET_KEY', '404D635166546A576E5A7234753778214125442A472D4B614E645267556B5870');

defined('PAYPAL_SANDBOX_MODE') or define('PAYPAL_SANDBOX_MODE', true);
defined('sandbox') or define('sandbox', true);
// Sandbox
defined('business') or define('business', 'sb-uefcv23946367@business.example.com');

// Live
// defined('PAYPAL_LIVE_BUSINESS_EMAIL') or define('PAYPAL_LIVE_BUSINESS_EMAIL', '');
// defined('PAYPAL_CURRENCY') or define('PAYPAL_CURRENCY', 'USD');

return [
    'CACHE' => [
        'SYSTEM' => [
            'DEFAULT_LANGUAGE' => 'default_language',
            'DEFAULT_CURRENCY' => 'default_currency',
            'SETTINGS' => 'systemSettings',
        ],
    ],
    'RESPONSE_CODE' => [
        'EXCEPTION_ERROR' => 500,
        'SUCCESS' => 200,
        'VALIDATION_ERROR' => 400,
        'UNAUTHORIZED' => 401,
    ],
    'FEATURES' => [
        'PROPERTY_LIST' => ['NAME' => 'Property List',                     'TYPE' => 'property_list'],
        'PROPERTY_FEATURE' => ['NAME' => 'Property Feature List',             'TYPE' => 'property_feature'],
        'PROJECT_LIST' => ['NAME' => 'Project List',                      'TYPE' => 'project_list'],
        'PROJECT_FEATURE' => ['NAME' => 'Project Feature List',              'TYPE' => 'project_feature'],
        'MORTGAGE_CALCULATOR_DETAIL' => ['NAME' => 'Mortgage Calculator Detail Access', 'TYPE' => 'mortgage_calculator_detail'],
        'PREMIUM_PROPERTIES' => ['NAME' => 'Premium Properties Access',         'TYPE' => 'premium_properties'],
        'PREMIUM_PROJECTS' => ['NAME' => 'Premium Projects Access',           'TYPE' => 'premium_projects'],
        'AGENT_WATERMARK' => ['NAME' => 'Agent Watermark',                    'TYPE' => 'agent_watermark'],
    ],

    'HOMEPAGE_SECTION_TYPES' => [
        'AGENTS_LIST_SECTION' => ['TYPE' => 'agents_list_section',            'TITLE' => 'Agent Sections List'],
        'ARTICLES_SECTION' => ['TYPE' => 'articles_section',               'TITLE' => 'Article Sections List'],
        'CATEGORIES_SECTION' => ['TYPE' => 'categories_section',             'TITLE' => 'Category Sections List'],
        'FAQS_SECTION' => ['TYPE' => 'faqs_section',                   'TITLE' => 'FAQ Sections List'],
        'FEATURED_PROPERTIES_SECTION' => ['TYPE' => 'featured_properties_section',    'TITLE' => 'Featured Properties Sections List'],
        'FEATURED_PROJECTS_SECTION' => ['TYPE' => 'featured_projects_section',      'TITLE' => 'Featured Projects Sections List'],
        'MOST_LIKED_PROPERTIES_SECTION' => ['TYPE' => 'most_liked_properties_section',  'TITLE' => 'Most Liked Properties Sections List'],
        'MOST_VIEWED_PROPERTIES_SECTION' => ['TYPE' => 'most_viewed_properties_section', 'TITLE' => 'Most Viewed Properties Sections List'],
        'NEARBY_PROPERTIES_SECTION' => ['TYPE' => 'nearby_properties_section',      'TITLE' => 'Nearby Properties Sections List'],
        'PROJECTS_SECTION' => ['TYPE' => 'projects_section',               'TITLE' => 'Project Sections List'],
        'PREMIUM_PROJECTS_SECTION' => ['TYPE' => 'premium_projects_section',       'TITLE' => 'Premium Projects Sections List'],
        'PREMIUM_PROPERTIES_SECTION' => ['TYPE' => 'premium_properties_section',     'TITLE' => 'Premium Properties Sections List'],
        'USER_RECOMMENDATIONS_SECTION' => ['TYPE' => 'user_recommendations_section',   'TITLE' => 'User Recommendations Sections List'],
        'PROPERTIES_BY_CITIES_SECTION' => ['TYPE' => 'properties_by_cities_section',   'TITLE' => 'Properties by Cities Sections List'],
        'PROPERTIES_ON_MAP_SECTION' => ['TYPE' => 'properties_on_map_section',      'TITLE' => 'Properties on Map Sections List'],
        'CURATED_PROPERTIES_SECTION' => ['TYPE' => 'curated_properties_section',     'TITLE' => 'Admin Selected Properties Section List'],
        'CURATED_PROJECTS_SECTION' => ['TYPE' => 'curated_projects_section',       'TITLE' => 'Admin Selected Projects Section List'],
    ],
    'API_RESPONSE_KEY' => [
        'ACCOUNT_DEACTIVATED' => 'accountDeactivated',
        'EMAIL_NOT_VERIFIED' => 'emailNotVerified',
        'REQUIRED_AGENT_ROLE' => 'requiredAgentRole',
        'REQUIRED_USER_ROLE' => 'requiredUserRole',
    ],
];
