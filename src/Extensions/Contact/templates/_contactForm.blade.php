{{-- Single Contact Form Fields --}}
@php
$prefix = "contacts[{$index}]";
$id = $contact->id ?? '';
@endphp

<div class="contact-form-entry card mb-3">
    <div class="card-header d-flex justify-content-between align-items-center py-2">
        <span>{{ __('Contact') }} #{{ $index + 1 }}</span>
        <button type="button" class="btn btn-sm btn-outline-danger remove-contact-btn">
            <i class="fas fa-times"></i>
        </button>
    </div>
    <div class="card-body">
        <input type="hidden" name="{{ $prefix }}[id]" value="{{ $id }}">
        <input type="hidden" name="{{ $prefix }}[delete]" value="" class="delete-contact-field">

        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label">{{ __('Contact person') }}</label>
                <input type="text" name="{{ $prefix }}[contact_person]" class="form-control"
                       value="{{ $contact->contact_person ?? '' }}">
            </div>
            <div class="col-md-6">
                <div class="form-check mt-4">
                    <input type="checkbox" name="{{ $prefix }}[primary_contact]" value="1"
                           class="form-check-input" id="primary_{{ $index }}"
                           {{ ($contact->primary_contact ?? 0) ? 'checked' : '' }}>
                    <label class="form-check-label" for="primary_{{ $index }}">
                        {{ __('Primary contact') }}
                    </label>
                </div>
            </div>

            <div class="col-12">
                <label class="form-label">{{ __('Street address') }}</label>
                <textarea name="{{ $prefix }}[street_address]" class="form-control" rows="2">{{ $contact->street_address ?? '' }}</textarea>
            </div>

            <div class="col-md-4">
                <label class="form-label">{{ __('City') }}</label>
                <input type="text" name="{{ $prefix }}[city]" class="form-control"
                       value="{{ $contact->city ?? '' }}">
            </div>
            <div class="col-md-4">
                <label class="form-label">{{ __('Region') }}</label>
                <input type="text" name="{{ $prefix }}[region]" class="form-control"
                       value="{{ $contact->region ?? '' }}">
            </div>
            <div class="col-md-4">
                <label class="form-label">{{ __('Postal code') }}</label>
                <input type="text" name="{{ $prefix }}[postal_code]" class="form-control"
                       value="{{ $contact->postal_code ?? '' }}">
            </div>

            <div class="col-md-6">
                <label class="form-label">{{ __('Country') }}</label>
                <input type="text" name="{{ $prefix }}[country_code]" class="form-control"
                       value="{{ $contact->country_code ?? '' }}" placeholder="e.g. ZA, US, GB">
            </div>

            <div class="col-md-6">
                <label class="form-label">{{ __('Telephone') }}</label>
                <input type="tel" name="{{ $prefix }}[telephone]" class="form-control"
                       value="{{ $contact->telephone ?? '' }}">
            </div>

            <div class="col-md-6">
                <label class="form-label">{{ __('Fax') }}</label>
                <input type="tel" name="{{ $prefix }}[fax]" class="form-control"
                       value="{{ $contact->fax ?? '' }}">
            </div>

            <div class="col-md-6">
                <label class="form-label">{{ __('Email') }}</label>
                <input type="email" name="{{ $prefix }}[email]" class="form-control"
                       value="{{ $contact->email ?? '' }}">
            </div>

            <div class="col-12">
                <label class="form-label">{{ __('Website') }}</label>
                <input type="url" name="{{ $prefix }}[website]" class="form-control"
                       value="{{ $contact->website ?? '' }}" placeholder="https://">
            </div>

            <div class="col-12">
                <label class="form-label">{{ __('Note') }}</label>
                <textarea name="{{ $prefix }}[note]" class="form-control" rows="2">{{ $contact->note ?? '' }}</textarea>
            </div>
        </div>
    </div>
</div>
