{{--
    Searchable Record Select Component
    Format: Title (identifier) - Level
    
    Usage:
    <x-form.searchable-record-select 
        name="information_object_id"
        :records="$records"
        placeholder="Select a record..."
        :selected="$selectedId"
    />
--}}

@props([
    'name' => 'record_id',
    'id' => null,
    'records' => [],
    'placeholder' => '-- Select a record --',
    'selected' => null,
    'required' => false,
    'disabled' => false,
    'class' => '',
])

@php
    $componentId = $id ?? 'select-' . \Illuminate\Support\Str::random(8);
@endphp

<select 
    id="{{ $componentId }}"
    name="{{ $name }}"
    class="form-select searchable-record-select {{ $class }}"
    @if($required) required @endif
    @if($disabled) disabled @endif
    data-placeholder="{{ $placeholder }}"
>
    <option value="">{{ $placeholder }}</option>
    @foreach($records as $record)
        <option 
            value="{{ $record['id'] }}"
            data-identifier="{{ $record['identifier'] ?? '' }}"
            data-level="{{ $record['level'] ?? '' }}"
            @if($selected == $record['id']) selected @endif
        >
            {{ $record['title'] }} ({{ $record['identifier'] ?? 'No identifier' }})
        </option>
    @endforeach
</select>
