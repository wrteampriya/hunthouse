@extends('layouts.main')


@section('title')
	{{ __('Manage Customer') }}
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
            <div class="divider">
				<div class="divider-text">
					<h4>{{ __('Update Customer') }}</h4>
				</div>
			</div>
			<div class="card-body mt-4">
				<form action="{{ route('customer.update', $customer->id) }}" method="POST" enctype="multipart/form-data" class="create-form" data-success-function="successForm">
					@method('PUT')
					<div class="row">
						{{-- Name --}}
						<div class="col-md-6 mb-3">
							<label for="name" class="form-label">{{ __('Name') }} <span class="text-danger">*</span></label>
							<input type="text" name="name" id="name" class="form-control" value="{{ $customer->name }}" required placeholder="{{ __('Name') }}">
						</div>
						{{-- Email --}}
						<div class="col-md-6 mb-3">
							<label for="email" class="form-label">{{ __('Email') }} <span class="text-danger">*</span></label>
							<input type="email" name="email" id="email" class="form-control" value="{{ $customer->email }}" required placeholder="{{ __('Email') }}">
						</div>
						{{-- Country Code --}}
						<div class="col-md-2 mb-3">
							<label for="country-code" class="form-label">{{ __('Country Code') }}</label>
							<select name="country_code" id="country-code" class="form-control select2">
								<option value="">{{ __('Select country code') }}</option>
								@if(isset($countryCodes) && is_array($countryCodes))
									@foreach($countryCodes as $code)
										<option value="{{ $code }}" @if($code == $customer->country_code) selected @endif>{{ '+' . $code }}</option>
									@endforeach
								@endif
							</select>
						</div>
						{{-- Number --}}
						<div class="col-md-4 mb-3">
							<label for="mobile" class="form-label">{{ __('Number') }}</label>
							<input type="text" name="mobile" id="mobile" class="form-control" value="{{ $customer->mobile }}" placeholder="{{ __('Number') }}">
						</div>
						{{-- Profile Image --}}
						<div class="col-md-6 mb-3">
							<label for="profile" class="form-label">{{ __('Profile Image') }}</label>
							<input type="file" name="profile" id="profile" class="form-control filepond" accept="image/*">
							@if($customer->getRawOriginal('profile'))
								<div class="mt-2">
									<img src="{{ $customer->profile }}" alt="Image" hight="40" width="40">
								</div>
							@endif
						</div>
					</div>
					<div class="d-flex justify-content-end gap-2">
						<button type="submit" class="btn btn-primary">{{ __('Save') }}</button>
					</div>
				</form>
			</div>
		</div>

		<div class="card mt-4">
			<div class="divider">
				<div class="divider-text">
					<h4>{{ __('Change Password') }}</h4>
				</div>
			</div>
			<div class="card-body mt-4">
				<form action="{{ route('customer.change-password') }}" method="POST" class="create-form" data-success-function="successForm">
					<input type="hidden" name="id" value="{{ $customer->id }}">
					<div class="row">
						<div class="col-md-6 mb-3">
							<div class="form-group position-relative form-floating has-icon-right mb-4">
								<input id="password" type="password" placeholder="{{ __('Password') }}" class="form-control form-input user-password" name="password" required>
								<span class="form-text text-muted"><small>{{ __('Min Password Length Must Be of 6') }}</small></span>
								<label for="password">{{ __('Password') }}</label>
								<div class="form-control-icon icon-right">
									<i class="bi bi-eye toggle-password"></i>
								</div>
							</div>
						</div>
						<div class="col-md-6 mb-3">
							<div class="form-group position-relative form-floating has-icon-right mb-1">
								<input id="re-password" type="password" placeholder="{{ __('Re-enter Password') }}" class="form-control form-input user-password" name="re_password" required>
								<span class="form-text text-muted"><small>{{ __('Min Password Length Must Be of 6') }}</small></span>
								<label for="re-password">{{ __('Re-enter Password') }}</label>
								<div class="form-control-icon icon-right">
									<i class="bi bi-eye toggle-password"></i>
								</div>
							</div>
						</div>
					</div>
					<div class="d-flex justify-content-end gap-2">
						<button type="submit" class="btn btn-primary">{{ __('Update Password') }}</button>
					</div>
				</form>
			</div>
		</div>
		@if($customer->is_agent)
		<div class="card mt-4">
			<div class="divider">
				<div class="divider-text">
					<h4>{{ __('Agent Profile') }}</h4>
				</div>
			</div>
			<div class="card-body mt-4">
				<div class="row">
					<div class="col-md-4 mb-3">
						<label class="form-label fw-bold">{{ __('Agent Status') }}</label>
						<div>
							<span class="badge bg-success">{{ __('Agent') }}</span>
							@if($customer->is_agent_verified)
								<span class="badge bg-primary">{{ __('Verified') }}</span>
							@else
								<span class="badge bg-warning">{{ __('Not Verified') }}</span>
							@endif
						</div>
					</div>
					<div class="col-md-4 mb-3">
						<label class="form-label fw-bold">{{ __('Active Mode') }}</label>
						<div>
							<span class="badge bg-{{ $customer->is_agent ? 'info' : 'secondary' }}">
								{{ $customer->is_agent ? __('Agent') : __('User') }}
							</span>
						</div>
					</div>
				</div>
				@php $agentProfile = $customer->agent_profile; @endphp
				@if($agentProfile)
				<hr>
				<div class="row">
					<div class="col-md-4 mb-3">
						<label class="form-label fw-bold">{{ __('Agent Name') }}</label>
						<p>{{ $agentProfile->agent_name ?? '-' }}</p>
					</div>
					<div class="col-md-4 mb-3">
						<label class="form-label fw-bold">{{ __('Agent Email') }}</label>
						<p>{{ $agentProfile->agent_email ?? '-' }}</p>
					</div>
					<div class="col-md-4 mb-3">
						<label class="form-label fw-bold">{{ __('Agent Profile Photo') }}</label>
						<div>
							@if($agentProfile->getRawOriginal('agent_profile_photo'))
								<img src="{{ $agentProfile->agent_profile_photo }}" alt="Agent Photo" height="80" width="80" class="rounded">
							@else
								<span class="text-muted">-</span>
							@endif
						</div>
					</div>
				</div>
				@else
				<p class="text-muted">{{ __('No agent profile data available.') }}</p>
				@endif
			</div>
		</div>
		@endif
	</section>
@endsection

@section('script')
	<script>
		function successForm(response){
			if(!response.error){
				setTimeout(() => {
					window.location.reload();
				}, 1000);
			}
		}
	</script>
@endsection
