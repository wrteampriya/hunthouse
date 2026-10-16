@extends('layouts.main')

@section('title')
	{{ __('Add Customer') }}
@endsection

@section('page-title')
	<div class="page-title">
		<div class="row">
			<div class="col-12 col-md-6 order-md-1 order-last">
				<h4>@yield('title')</h4>
			</div>
			<div class="col-12 col-md-6 order-md-2 order-first">
			</div>
		</div>
		<div class="d-flex justify-content-end">
			<a href="{{ route('customer.index') }}" class="btn btn-primary">{{ __('Back') }}</a>
		</div>
	</div>
@endsection

@section('content')
	<section class="section">
		<div class="card">
			<div class="card-body">
				<form action="{{ route('customer.store') }}" method="POST" enctype="multipart/form-data" class="create-form" data-success-function="formSuccessFunction">
					<div class="row">
                        {{-- Name --}}
						<div class="col-md-6 mb-3">
							<label for="name" class="form-label">{{ __('Name') }} <span class="text-danger">*</span></label>
							<input type="text" name="name" id="name" class="form-control" required placeholder="{{ __('Name') }}">
						</div>

                        {{-- Email --}}
						<div class="col-md-6 mb-3">
							<label for="email" class="form-label">{{ __('Email') }} <span class="text-danger">*</span></label>
							<input type="email" name="email" id="email" class="form-control" required placeholder="{{ __('Email') }}">
						</div>

                        {{-- Password --}}
						<div class="col-md-6 mb-3">
							<label for="password" class="form-label">{{ __('Password') }} <span class="text-danger">*</span></label>
							<input type="password" name="password" id="password" class="form-control" required placeholder="{{ __('Password') }}">
						</div>

                        {{-- Re-enter Password --}}
						<div class="col-md-6 mb-3">
							<label for="re_password" class="form-label">{{ __('Re-enter Password') }} <span class="text-danger">*</span></label>
							<input type="password" name="re_password" id="re_password" class="form-control" required placeholder="{{ __('Re-enter Password') }}">
						</div>

						{{-- Country Code --}}
						<div class="col-md-2 mb-3">
							<label for="country-code" class="form-label">{{ __('Country Code') }}</label>
							<select name="country_code" id="country-code" class="form-control select2">
								<option value="">{{ __('Select country code') }}</option>
								@if(isset($countryCodes) && is_array($countryCodes))
									@foreach($countryCodes as $code)
										<option value="{{ $code }}">{{ '+' . $code }}</option>
									@endforeach
								@endif
							</select>
						</div>

                        {{-- Number --}}
						<div class="col-md-4 mb-3">
							<label for="mobile" class="form-label">{{ __('Number') }}</label>
							<input type="text" name="mobile" id="mobile" class="form-control" placeholder="{{ __('Number') }}">
						</div>


                        {{-- Profile Image --}}
						<div class="col-md-6 mb-3">
							<label for="profile" class="form-label">{{ __('Profile Image') }}</label>
							<input type="file" name="profile" id="profile" class="form-control filepond" accept="image/*" placeholder="{{ __('Profile Image') }}">
						</div>
					</div>

					<div class="d-flex justify-content-end gap-2">
						<button type="submit" class="btn btn-primary">{{ __('Create') }}</button>
					</div>
				</form>
			</div>
		</div>
	</section>
@endsection
@section('script')
	<script>
		function formSuccessFunction(response) {
			if(!response.error){
				setTimeout(() => {
					window.location.href = "{{ route('customer.index') }}";
				}, 1000);
			}
		}
	</script>
@endsection