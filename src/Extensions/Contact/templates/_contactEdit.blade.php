{{-- Contact Information Edit Form for Authority Records --}}
@php
use AtomFramework\Extensions\Contact\Repositories\ContactInformationRepository;
$contactRepo = new ContactInformationRepository();
$contacts = $contactRepo->getByActorId($resource->id);
@endphp

<section id="contactEditArea" class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="card-title mb-0">
            <i class="fas fa-address-card me-2"></i>{{ __('Contact information') }}
        </h5>
        <button type="button" class="btn btn-sm btn-success" id="addContactBtn">
            <i class="fas fa-plus me-1"></i>{{ __('Add contact') }}
        </button>
    </div>
    <div class="card-body">
        <div id="contactsList">
            @foreach($contacts as $index => $contact)
                @include('Extensions.Contact.templates._contactForm', ['contact' => $contact, 'index' => $index])
            @endforeach
        </div>
    </div>
</section>

<template id="contactFormTemplate">
    @include('Extensions.Contact.templates._contactForm', ['contact' => null, 'index' => '__INDEX__'])
</template>

<script>
document.addEventListener('DOMContentLoaded', function() {
    let contactIndex = {{ $contacts->count() }};

    document.getElementById('addContactBtn').addEventListener('click', function() {
        const template = document.getElementById('contactFormTemplate');
        const html = template.innerHTML.replace(/__INDEX__/g, contactIndex);
        const container = document.createElement('div');
        container.innerHTML = html;
        document.getElementById('contactsList').appendChild(container.firstElementChild);
        contactIndex++;
    });

    document.getElementById('contactsList').addEventListener('click', function(e) {
        if (e.target.closest('.remove-contact-btn')) {
            const entry = e.target.closest('.contact-form-entry');
            if (entry) {
                const deleteField = entry.querySelector('.delete-contact-field');
                if (deleteField && deleteField.value !== '') {
                    deleteField.value = '1';
                    entry.style.display = 'none';
                } else {
                    entry.remove();
                }
            }
        }
    });
});
</script>
