@extends('installer::install')

@section('step')
    @if($errors->any())
        @foreach($errors->all() as $error)
            <div class="bg-red-100 border-l-4 border-red-500 p-4 mb-3">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <svg class="h-5 w-5 text-red-500" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                        </svg>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm leading-5 text-red-700">
                            {!! $error !!}
                        </p>
                    </div>
                </div>
            </div>
        @endforeach
    @else
        <div class="bg-green-100 border-l-4 border-green-500 p-4 mb-3">
            <div class="flex">
                <div class="flex-shrink-0">
                    <svg class="h-5 w-5 text-green-500" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                    </svg>
                </div>
                <div class="ml-3">
                    <p class="text-sm leading-5 text-green-700">
                        Migrations loaded successfully
                    </p>
                </div>
            </div>
        </div>
    @endif

    {{-- Symlink Status Check --}}
    @php
        $symlinkEnabled = \App\Http\Controllers\InstallerController::isSymlinkEnabled();
    @endphp

    @if($symlinkEnabled)
        <div class="bg-green-100 border-l-4 border-green-500 p-4 mb-3">
            <div class="flex">
                <div class="flex-shrink-0">
                    <svg class="h-5 w-5 text-green-500" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                    </svg>
                </div>
                <div class="ml-3">
                    <p class="text-sm leading-5 text-green-700">
                        Symlink is supported on this server. Storage link will be created automatically.
                    </p>
                </div>
            </div>
        </div>
    @else
        <div class="bg-yellow-100 border-l-4 border-yellow-500 p-4 mb-3">
            <div class="flex">
                <div class="flex-shrink-0">
                    <svg class="h-5 w-5 text-yellow-500" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                    </svg>
                </div>
                <div class="ml-3">
                    <p class="text-sm leading-5 text-yellow-700 font-bold">
                        Symlink function is disabled on this server.
                    </p>
                    <p class="text-sm leading-5 text-yellow-700 mt-2">
                        Please follow these steps to enable it:
                    </p>
                    <ol class="text-sm leading-5 text-yellow-700 mt-2 list-decimal list-inside space-y-1">
                        <li>Open your hosting control panel (cPanel / Plesk / DirectAdmin)</li>
                        <li>Go to <strong>PHP Settings</strong> or <strong>Select PHP Version</strong></li>
                        <li>Find <strong>disable_functions</strong> and remove <code class="bg-yellow-200 px-1 rounded">symlink</code> from the list</li>
                        <li>Save changes and restart PHP if needed</li>
                        <li>Refresh this page to verify</li>
                    </ol>
                    <p class="text-sm leading-5 text-yellow-700 mt-2">
                        <strong>Alternative:</strong> Run via SSH after installation:
                    </p>
                    <code class="block bg-yellow-200 px-2 py-1 rounded mt-1 text-sm text-yellow-800">
                        ln -s ../storage/app/public public/storage
                    </code>
                </div>
            </div>
        </div>
    @endif

    <p class="pb-3 text-gray-800">The encryption keys of the application and the api connection must be generated.</p>
    <p class="pb-3 text-gray-800">The storage link will also be generated.</p>
    <p class="pb-3 text-gray-800">Enter the base url of the application.</p>
    <form method="post" action="{{ route('install.keys') }}">
        @csrf
        <div class="mb-3">
            <x-installer::label for="app_url" :required="true">App url</x-installer::label>
            <x-installer::input
                id="app_url"
                name="app_url"
                type="url"
                value="{{ old('app_url', url('/')) }}"
                :required="true"
            />
        </div>
        <div class="flex justify-end">
            @if($errors->any())
                <x-installer::button type="submit" color="red">
                    Try again
                    <svg class="fill-current w-5 h-5 ml-3" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" />
                    </svg>
                </x-installer::button>
            @elseif(!$symlinkEnabled)
                <a href="{{ url('/install/keys') }}" class="bg-yellow-500 hover:bg-yellow-700 text-white font-bold py-2 px-4 rounded inline-flex items-center mr-2">
                    <svg class="fill-current w-5 h-5 mr-2" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M4 2a1 1 0 011 1v2.101a7.002 7.002 0 0111.601 2.566 1 1 0 11-1.885.666A5.002 5.002 0 005.999 7H9a1 1 0 010 2H4a1 1 0 01-1-1V3a1 1 0 011-1zm.008 9.057a1 1 0 011.276.61A5.002 5.002 0 0014.001 13H11a1 1 0 110-2h5a1 1 0 011 1v5a1 1 0 11-2 0v-2.101a7.002 7.002 0 01-11.601-2.566 1 1 0 01.61-1.276z" clip-rule="evenodd" />
                    </svg>
                    Re-check Symlink
                </a>
                <x-installer::button type="submit">
                    Skip & Continue
                    <svg class="fill-current w-5 h-5 ml-3" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" />
                    </svg>
                </x-installer::button>
            @else
                <x-installer::button type="submit" color="green">
                    Link Storage & Continue
                    <svg class="fill-current w-5 h-5 ml-3" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M12.586 4.586a2 2 0 112.828 2.828l-3 3a2 2 0 01-2.828 0 1 1 0 00-1.414 1.414 4 4 0 005.656 0l3-3a4 4 0 00-5.656-5.656l-1.5 1.5a1 1 0 101.414 1.414l1.5-1.5zm-5 5a2 2 0 012.828 0 1 1 0 101.414-1.414 4 4 0 00-5.656 0l-3 3a4 4 0 105.656 5.656l1.5-1.5a1 1 0 10-1.414-1.414l-1.5 1.5a2 2 0 11-2.828-2.828l3-3z" clip-rule="evenodd" />
                    </svg>
                </x-installer::button>
            @endif
        </div>
    </form>
@endsection
