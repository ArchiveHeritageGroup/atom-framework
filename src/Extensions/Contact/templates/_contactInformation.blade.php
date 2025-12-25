{{-- Contact Information Extension for Authority Records --}}
@php
use AtomFramework\Extensions\Contact\Repositories\ContactInformationRepository;
$contactRepo = new ContactInformationRepository();
$contacts = $contactRepo->getByActorId($resource->id);
@endphp

@if($contacts->isNotEmpty())
<section id="contactArea" class="card mb-4">
    <div class="card-header">
        <h5 class="card-title mb-0">
            <i class="fas fa-address-card me-2"></i>{{ __('Contact information') }}
        </h5>
    </div>
    <div class="card-body">
        @foreach($contacts as $index => $contact)
        <div class="contact-entry {{ $index < $contacts->count() - 1 ? 'mb-3 pb-3 border-bottom' : '' }}">
            @if($contact->primary_contact)
                <span class="badge bg-primary mb-2">{{ __('Primary') }}</span>
            @endif

            @if($contact->contact_person)
            <div class="mb-1">
                <strong>{{ __('Contact person') }}:</strong>
                {{ $contact->contact_person }}
            </div>
            @endif

            @if($contact->street_address || $contact->city || $contact->region || $contact->postal_code || $contact->country_code)
            <div class="mb-1">
                <strong>{{ __('Address') }}:</strong><br>
                @if($contact->street_address)
                    {{ $contact->street_address }}<br>
                @endif
                @php
                    $cityLine = array_filter([
                        $contact->city,
                        $contact->region,
                        $contact->postal_code
                    ]);
                @endphp
                @if(!empty($cityLine))
                    {{ implode(', ', $cityLine) }}<br>
                @endif
                @if($contact->country_code)
                    {{ $contact->country_code }}
                @endif
            </div>
            @endif

            @if($contact->telephone)
            <div class="mb-1">
                <strong>{{ __('Telephone') }}:</strong>
                <a href="tel:{{ $contact->telephone }}">{{ $contact->telephone }}</a>
            </div>
            @endif

            @if($contact->fax)
            <div class="mb-1">
                <strong>{{ __('Fax') }}:</strong>
                {{ $contact->fax }}
            </div>
            @endif

            @if($contact->email)
            <div class="mb-1">
                <strong>{{ __('Email') }}:</strong>
                <a href="mailto:{{ $contact->email }}">{{ $contact->email }}</a>
            </div>
            @endif

            @if($contact->website)
            <div class="mb-1">
                <strong>{{ __('Website') }}:</strong>
                <a href="{{ $contact->website }}" target="_blank" rel="noopener">
                    {{ $contact->website }}
                    <i class="fas fa-external-link-alt fa-xs"></i>
                </a>
            </div>
            @endif

            @if($contact->note)
            <div class="mb-1">
                <strong>{{ __('Note') }}:</strong>
                {!! nl2br(e($contact->note)) !!}
            </div>
            @endif
        </div>
        @endforeach
    </div>
</section>
@endif
