<style>
    .sidebar-item .sidebar-link .menu-item {
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        display: inline-block;
        vertical-align: bottom;
        max-width: 170px; /* Adjust based on sidebar width */
    }

    .sidebar-item.has-sub .sidebar-link::after {
        flex-shrink: 0;
    }

    .submenu-item a {
        white-space: normal;
        overflow: hidden;
        word-break: break-word;
    }

    html[dir="rtl"] .sidebar-item.has-sub .sidebar-link {
        padding-left: 2.5rem;
    }
</style>
<div id="sidebar" class="active">
    <div class="sidebar-wrapper active">
        <script>
            // Prevent sidebar flash on medium/small screens
            if (window.innerWidth < 1200) {
                document.getElementById('sidebar').classList.remove('active');
                document.querySelector('.sidebar-wrapper').classList.remove('active');
            }
        </script>
        <div class="sidebar-header position-relative">
            <div class="d-flex justify-content-center">
                <div class="logo">
                    <a href="{{ url('home') }}">
                        <img src="{{ url('assets/images/logo/' . (system_setting('company_logo') ?? null)) }}"
                            alt="Logo" srcset="">
                    </a>
                </div>
            </div>
            <!-- Close button -->
            <button class="sidebar-close-btn" type="button" aria-label="Close sidebar">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>
        <div class="sidebar-menu">
            <ul class="menu" id="sidebarMenu">
                {{-- Dashboard --}}
                @if (has_permissions('read', 'dashboard'))
                    <li class="sidebar-item">
                        <a href="{{ url('home') }}" class='sidebar-link'>
                            <i class="bi bi-grid-fill"></i>
                            <span class="menu-item">{{ __('Dashboard') }}</span>
                        </a>
                    </li>
                @endif

                {{-- Properties & Locations --}}
                @if (
                        has_permissions('read', 'property') ||
                        has_permissions('read', 'project') ||
                        has_permissions('read', 'categories') ||
                        has_permissions('read', 'facility') ||
                        has_permissions('read', 'near_by_places') ||
                        has_permissions('read', 'city_images')
                    )
                    <li class="sidebar-item has-sub">
                        <a href="#" class='sidebar-link'>
                            <i class="bi bi-building"></i>
                            <span class="menu-item">{{ __('Properties & Locations') }}</span>
                        </a>
                        <ul class="submenu" style="padding: 0rem">
                            {{-- Properties --}}
                            @if (has_permissions('read', 'property'))
                                <li class="submenu-item">
                                    <a href="{{ url('property') }}">
                                        {{ __('Properties') }}
                                    </a>
                                </li>
                            @endif

                            {{-- Projects --}}
                            @if (has_permissions('read', 'project'))
                                <li class="submenu-item">
                                    <a href="{{ url('project') }}">
                                        {{ __('Projects') }}
                                    </a>
                                </li>
                            @endif

                            {{-- Categories --}}
                            @if (has_permissions('read', 'categories'))
                                <li class="submenu-item">
                                    <a href="{{ url('categories') }}">{{ __('Categories') }}</a>
                                </li>
                            @endif

                            {{-- Facilities --}}
                            @if (has_permissions('read', 'facility'))
                                <li class="submenu-item">
                                    <a href="{{ url('parameters') }}">{{ __('Facilities') }}</a>
                                </li>
                            @endif

                            {{-- Nearby Places --}}
                            @if (has_permissions('read', 'near_by_places'))
                                <li class="submenu-item">
                                    <a href="{{ url('outdoor_facilities') }}">{{ __('Nearby Places') }}</a>
                                </li>
                            @endif

                            {{-- City Images --}}
                            @if (has_permissions('read', 'city_images'))
                                <li class="submenu-item">
                                    <a href="{{ route('city-images.index') }}">{{ __('City Images') }}</a>
                                </li>
                            @endif
                        </ul>
                    </li>
                @endif

                {{-- Marketing & content --}}
                @if (
                        has_permissions('read', 'advertisement') ||
                        has_permissions('read', 'slider') ||
                        has_permissions('read', 'article') ||
                        has_permissions('read', 'homepage-sections') ||
                        has_permissions('read', 'faqs')
                    )
                    <li class="sidebar-item has-sub">
                        <a href="#" class='sidebar-link'>
                            <i class="bi bi-badge-ad"></i>
                            <span class="menu-item">{{ __('Marketing & content') }}</span>
                        </a>
                        <ul class="submenu" style="padding: 0rem">
                            {{-- Advertisement banners --}}
                            @if (has_permissions('read', 'advertisement'))
                                <li class="submenu-item">
                                    <a href="{{ route('ad-banners.index') }}">{{ __('Advertisement banners') }}</a>
                                </li>
                            @endif

                            {{-- Advertisements --}}
                            @if (has_permissions('read', 'advertisement'))
                                <li class="submenu-item">
                                    <a href="{{ url('featured_properties') }}">{{ __('Advertisements') }}</a>
                                </li>
                            @endif

                            {{-- Homepage Sections --}}
                            @if (has_permissions('read', 'homepage-sections'))
                                <li class="submenu-item">
                                    <a href="{{ route('homepage-sections.index') }}">{{ __('Homepage Sections') }}</a>
                                </li>
                            @endif

                            {{-- Slider --}}
                            @if (has_permissions('read', 'slider'))
                                <li class="submenu-item">
                                    <a href="{{ url('slider') }}">
                                        {{ __('Slider') }}
                                    </a>
                                </li>
                            @endif

                            {{-- Articles --}}
                            @if (has_permissions('read', 'article'))
                                <li class="submenu-item">
                                    <a href="{{ url('article') }}">{{ __('Articles') }}</a>
                                </li>
                            @endif

                            {{-- FAQs --}}
                            @if (has_permissions('read', 'faqs'))
                                <li class="submenu-item">
                                    <a href="{{ route('faqs.index') }}">{{ __('FAQs') }}</a>
                                </li>
                            @endif

                        </ul>
                    </li>
                @endif

                {{-- Users --}}
                @if (
                        has_permissions('read', 'customer') ||
                        has_permissions('read', 'approve_agent_verification') ||
                        has_permissions('read', 'verify_customer_form')
                    )
                    <li class="sidebar-item has-sub">
                        <a href="#" class='sidebar-link'>
                            <i class="bi bi-shield-check"></i>
                            <span class="menu-item">{{ __('Users') }}</span>
                        </a>
                        <ul class="submenu" style="padding: 0rem">
                            {{-- User Verification --}}
                            @if (has_permissions('read', 'approve_agent_verification'))
                                <li class="submenu-item">
                                    <a href="{{ route('user-verification.index') }}">
                                        {{ __('User Verification') }}
                                    </a>
                                </li>
                            @endif

                             {{-- Custom fields --}}
                             @if (has_permissions('read', 'verify_customer_form'))
                                <li class="submenu-item">
                                    <a href="{{ route('verify-customer.form') }}">
                                        {{ __('Custom fields') }}
                                    </a>
                                </li>
                            @endif
                        </ul>
                    </li>
                @endif


                {{-- Agents --}}
                @if (
                        has_permissions('read', 'verify_customer_form') ||
                        has_permissions('read', 'approve_agent_verification')
                    )
                    <li class="sidebar-item has-sub">
                        <a href="#" class='sidebar-link'>
                            <i class="bi bi-person-badge"></i>
                            <span class="menu-item">{{ __('Agents') }}</span>
                        </a>
                        <ul class="submenu" style="padding: 0rem">
                             {{-- Agent Verification --}}
                             @if (has_permissions('read', 'approve_agent_verification'))
                                <li class="submenu-item">
                                    <a href="{{ route('agent-verification.index') }}">
                                        {{ __('Agent Verification') }}
                                    </a>
                                </li>
                            @endif

                             {{-- Agent Form Sections --}}
                             @if (has_permissions('read', 'verify_customer_form'))
                                <li class="submenu-item">
                                    <a href="{{ route('agent-verification-form-sections.index') }}">{{ __('Agent Form Section') }}</a>
                                </li>
                            @endif

                            {{-- Agent Form Fields --}}
                            @if (has_permissions('read', 'verify_customer_form'))
                                <li class="submenu-item">
                                    <a href="{{ route('agent-verification-form-fields.index') }}">{{ __('Agent Form Fields') }}</a>
                                </li>
                            @endif
                        </ul>
                    </li>
                @endif

                {{-- Appointments --}}
                @if (
                        has_permissions('read', 'appointment_management') ||
                        has_permissions('read', 'appointment_reports') ||
                        has_permissions('read', 'admin_appointment_preferences')
                    )
                    <li class="sidebar-item has-sub">
                        <a href="#" class='sidebar-link'>
                            <i class="bi bi-calendar"></i>
                            <span class="menu-item">{{ __('Appointments') }}</span>
                        </a>
                        <ul class="submenu" style="padding: 0rem">
                            {{-- Appointments List --}}
                            @if (has_permissions('read', 'appointment_management'))
                                <li class="submenu-item">
                                    <a href="{{ route('appointment-management.index') }}">
                                        {{ __('Appointments List') }}
                                    </a>
                                </li>
                            @endif

                            {{-- Appointment reports --}}
                            @if (has_permissions('read', 'appointment_reports'))
                                <li class="submenu-item">
                                    <a href="{{ route('admin.appointment.reports.index') }}">{{ __('Appointment reports') }}</a>
                                </li>
                            @endif

                            {{-- Appointment settings --}}
                            @if (has_permissions('read', 'admin_appointment_preferences'))
                                <li class="submenu-item">
                                    <a href="{{ route('admin.appointment.index') }}">{{ __('Appointment settings') }}</a>
                                </li>
                            @endif
                        </ul>
                    </li>
                @endif

                {{-- Plans & billing --}}
                @if (
                        has_permissions('read', 'package') ||
                        has_permissions('read', 'package-feature') ||
                        has_permissions('create', 'package-feature') ||
                        has_permissions('create', 'package') ||
                        has_permissions('read', 'user_package') ||
                        has_permissions('read', 'payment') ||
                        has_permissions('read', 'assign_package') ||
                        has_permissions('read', 'calculator')
                    )
                    <li class="sidebar-item has-sub">
                        <a href="#" class='sidebar-link'>
                            <i class="bi bi-file-earmark-text"></i>
                            <span class="menu-item">{{ __('Plans & billing') }}</span>
                        </a>
                        <ul class="submenu" style="padding: 0rem">
                            {{-- Features --}}
                            @if (has_permissions('read', 'package-feature') || has_permissions('create', 'package-feature'))
                                <li class="submenu-item">
                                    <a href="{{ route('package-features.index') }}">{{ __('Features') }}</a>
                                </li>
                            @endif

                            {{-- Packages --}}
                            @if (has_permissions('read', 'package') || has_permissions('create', 'package'))
                                <li class="submenu-item">
                                    <a href="{{ route('package.index') }}">{{ __('Packages') }}</a>
                                </li>
                            @endif



                            {{-- Pay As You Go --}}
                            @if (has_permissions('read', 'package') || has_permissions('create', 'package'))
                                <li class="submenu-item">
                                    <a href="{{ route('pay-as-you-go.index') }}">{{ __('Pay As You Go') }}</a>
                                </li>
                            @endif

                            {{-- Users packages --}}
                            @if (has_permissions('read', 'user_package'))
                                <li class="submenu-item">
                                    <a href="{{ route('user-packages.index') }}">{{ __('Subscriptions') }}</a>
                                </li>
                            @endif

                            {{-- Assign package --}}
                            @if (has_permissions('read', 'assign_package'))
                                <li class="submenu-item">
                                    <a href="{{ route('assign-package.index') }}">{{ __('Assign package') }}</a>
                                </li>
                            @endif

                            {{-- Payment --}}
                            @if (has_permissions('read', 'payment'))
                                <li class="submenu-item">
                                    <a href="{{ route('payment.index') }}">{{ __('Payment') }}</a>
                                </li>
                            @endif

                            {{-- Calculator --}}
                            @if (has_permissions('read', 'calculator'))
                                <li class="submenu-item">
                                    <a href="{{ url('calculator') }}">{{ __('Calculator') }}</a>
                                </li>
                            @endif
                        </ul>
                    </li>
                @endif

                {{-- Communication --}}
                @if (has_permissions('read', 'chat') || has_permissions('read', 'notification'))
                    <li class="sidebar-item has-sub">
                        <a href="#" class='sidebar-link'>
                            <i class="bi bi-chat-dots"></i>
                            <span class="menu-item">{{ __('Communication') }}</span>
                        </a>
                        <ul class="submenu" style="padding: 0rem">
                            {{-- Chat --}}
                            @if (has_permissions('read', 'chat'))
                                <li class="submenu-item">
                                    <a href="{{ route('get-chat-list') }}">
                                        {{ __('Chat') }}
                                    </a>
                                </li>
                            @endif

                            {{-- Notification --}}
                            @if (has_permissions('read', 'notification'))
                                <li class="submenu-item">
                                    <a href="{{ url('notification') }}">{{ __('Notification') }}</a>
                                </li>
                            @endif
                        </ul>
                    </li>
                @endif

                {{-- User Management --}}
                @if (
                        has_permissions('read', 'customer') ||
                        has_permissions('read', 'users_inquiries') ||
                        has_permissions('read', 'user_reports') ||
                        has_permissions('read', 'report_reason')
                    )
                    <li class="sidebar-item has-sub">
                        <a href="#" class='sidebar-link'>
                            <i class="bi bi-person"></i>
                            <span class="menu-item">{{ __('User Management') }}</span>
                        </a>
                        <ul class="submenu" style="padding: 0rem">
                            {{-- Customers --}}
                            @if (has_permissions('read', 'customer'))
                                <li class="submenu-item">
                                    <a href="{{ url('customer') }}">{{ __('Customers') }}</a>
                                </li>
                            @endif

                            {{-- User Inquiries --}}
                            @if (has_permissions('read', 'users_inquiries'))
                                <li class="submenu-item">
                                    <a href="{{ url('users_inquiries') }}">{{ __('User Inquiries') }}</a>
                                </li>
                            @endif

                            {{-- User reports --}}
                            @if (has_permissions('read', 'user_reports'))
                                <li class="submenu-item">
                                    <a href="{{ url('users_reports') }}">{{ __('User reports') }}</a>
                                </li>
                            @endif

                            {{-- Report reasons --}}
                            @if (has_permissions('read', 'report_reason'))
                                <li class="submenu-item">
                                    <a href="{{ url('report-reasons') }}">{{ __('Report reasons') }}</a>
                                </li>
                            @endif
                        </ul>
                    </li>
                @endif

                {{-- Pages --}}
                @if (
                        has_permissions('read', 'about_us') ||
                        has_permissions('read', 'privacy_policy') ||
                        has_permissions('read', 'terms_conditions') ||
                        has_permissions('read', 'custom_page')
                    )
                    <li class="sidebar-item has-sub">
                        <a href="#" class='sidebar-link'>
                            <i class="bi bi-file-earmark"></i>
                            <span class="menu-item">{{ __('Pages') }}</span>
                        </a>
                        <ul class="submenu" style="padding: 0rem">
                            {{-- About us --}}
                            @if (has_permissions('read', 'about_us'))
                                <li class="submenu-item">
                                    <a href="{{ url('about-us') }}">{{ __('About us') }}</a>
                                </li>
                            @endif

                            {{-- Privacy policy --}}
                            @if (has_permissions('read', 'privacy_policy'))
                                <li class="submenu-item">
                                    <a href="{{ url('privacy-policy') }}">{{ __('Privacy policy') }}</a>
                                </li>
                            @endif

                            {{-- Terms & conditions --}}
                            @if (has_permissions('read', 'terms_conditions'))
                                <li class="submenu-item">
                                    <a href="{{ url('terms-conditions') }}">{{ __('Terms & conditions') }}</a>
                                </li>
                            @endif

                            {{-- Custom Pages --}}
                            @if (has_permissions('read', 'custom_page'))
                                <li class="submenu-item">
                                    <a href="{{ route('custom-page.index') }}">{{ __('Custom Pages') }}</a>
                                </li>
                            @endif
                        </ul>
                    </li>
                @endif

                {{-- Settings --}}
                @if (
                        has_permissions('read', 'users_accounts') ||
                        has_permissions('read', 'language') ||
                        has_permissions('read', 'system_settings') ||
                        has_permissions('read', 'payment_gateway_settings') ||
                        has_permissions('read', 'app_settings') ||
                        has_permissions('read', 'web_settings') ||
                        has_permissions('read', 'seo_settings') ||
                        has_permissions('read', 'firebase_settings') ||
                        has_permissions('read', 'notification_settings') ||
                        has_permissions('read', 'email_configurations') ||
                        has_permissions('read', 'email_templates') ||
                        has_permissions('read', 'gemini_settings')
                    )
                    <li class="sidebar-item has-sub">
                        <a href="#" class='sidebar-link'>
                            <i class="bi bi-gear"></i>
                            <span class="menu-item">{{ __('Settings') }}</span>
                        </a>
                        <ul class="submenu" style="padding: 0rem">
                            {{-- User accounts --}}
                            @if (has_permissions('read', 'users_accounts'))
                                <li class="submenu-item">
                                    <a href="{{ url('users') }}">{{ __('User accounts') }}</a>
                                </li>
                            @endif

                            {{-- Languages --}}
                            @if (has_permissions('read', 'language') || has_permissions('create', 'language') || has_permissions('update', 'language') || has_permissions('delete', 'language'))
                                <li class="submenu-item">
                                    <a href="{{ url('language') }}">{{ __('Languages') }}</a>
                                </li>
                            @endif

                            {{-- System settings --}}
                            @if (has_permissions('read', 'system_settings'))
                                <li class="submenu-item">
                                    <a href="{{ url('system-settings') }}">{{ __('System Settings') }}</a>
                                </li>
                            @endif

                            {{-- Payment Gateway settings --}}
                            @if (has_permissions('read', 'payment_gateway_settings'))
                                <li class="submenu-item">
                                    <a href="{{ route('payment-gateway-settings.index') }}">{{ __('Payment Settings') }}</a>
                                </li>
                            @endif

                            {{-- App settings --}}
                            @if (has_permissions('read', 'app_settings'))
                                <li class="submenu-item">
                                    <a href="{{ url('app-settings') }}">{{ __('App Settings') }}</a>
                                </li>
                            @endif

                            {{-- Web settings --}}
                            @if (has_permissions('read', 'web_settings'))
                                <li class="submenu-item">
                                    <a href="{{ url('web-settings') }}">{{ __('Web Settings') }}</a>
                                </li>
                            @endif

                            {{-- SEO settings --}}
                            @if (has_permissions('read', 'seo_settings'))
                                <li class="submenu-item">
                                    <a href="{{ url('seo_settings') }}">{{ __('SEO Settings') }}</a>
                                </li>
                            @endif

                            {{-- Firebase settings --}}
                            @if (has_permissions('read', 'firebase_settings'))
                                <li class="submenu-item">
                                    <a href="{{ url('firebase_settings') }}">{{ __('Firebase Settings') }}</a>
                                </li>
                            @endif

                            {{-- Notification settings --}}
                            @if (has_permissions('read', 'notification_settings'))
                                <li class="submenu-item">
                                    <a href="{{ route('notification-setting-index') }}">{{ __('Notification Settings') }}</a>
                                </li>
                            @endif

                            {{-- Email templates --}}
                            @if (has_permissions('read', 'email_templates'))
                                <li class="submenu-item">
                                    <a href="{{ route('email-templates.index') }}">{{ __('Email Templates') }}</a>
                                </li>
                            @endif

                            {{-- Email configurations --}}
                            @if (has_permissions('read', 'email_configurations'))
                                <li class="submenu-item">
                                    <a href="{{ route('email-configurations-index') }}">{{ __('Email Configurations') }}</a>
                                </li>
                            @endif

                            {{-- Watermark Settings --}}
                            @if (has_permissions('read', 'watermark_settings'))
                                <li class="submenu-item">
                                    <a href="{{ route('watermark-settings-index') }}">{{ __('Watermark Settings') }}</a>
                                </li>
                            @endif

                            {{-- Gemini AI Settings --}}
                            @if (has_permissions('read', 'gemini_settings'))
                                <li class="submenu-item">
                                    <a href="{{ route('gemini-settings.index') }}">{{ __('Gemini AI Settings') }}</a>
                                </li>
                            @endif

                            @if(has_permissions('read', 'demo_data'))
                                <li class="submenu-item">
                                    <a href="{{ route('demo-data.index') }}">{{ __('Demo Data Setup') }}</a>
                                </li>
                            @endif

                            {{-- Log viewer --}}
                            @if (has_permissions('read', 'system_settings'))
                                <li class="submenu-item">
                                    <a href="{{ url('log-viewer') }}">{{ __('Log Viewer') }}</a>
                                </li>
                            @endif

                            <!-- {{-- API Documentation --}}
                            @if (has_permissions('read', 'system_settings'))
                                <li class="submenu-item">
                                    <a href="{{ url('docs/api') }}" target="_blank">{{ __('API Documentation') }}</a>
                                </li>
                            @endif -->
                        </ul>
                    </li>
                @endif

                {{-- System --}}
                @if (has_permissions('read', 'system_update'))
                    <li class="sidebar-item has-sub">
                        <a href="#" class='sidebar-link'>
                            <i class="bi bi-tools"></i>
                            <span class="menu-item">{{ __('System') }}</span>
                        </a>
                        <ul class="submenu" style="padding: 0rem">
                            {{-- System update --}}
                            <li class="submenu-item">
                                <a href="{{ url('system-version') }}">{{ __('System Update') }}</a>
                            </li>
                        </ul>
                    </li>
                @endif
            </ul>
        </div>
    </div>
</div>