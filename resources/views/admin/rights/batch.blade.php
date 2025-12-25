@extends('layouts.admin')

@section('title', 'Batch Rights')

@section('content')
<div class="container-fluid py-4">
    <div class="card">
        <div class="card-header bg-success text-white">
            <h4 class="mb-0"><i class="fas fa-gavel me-2"></i>Batch Rights</h4>
        </div>
        
        <div class="card-body">
            @if(session('success'))
                <div class="alert alert-success alert-dismissible fade show">
                    {{ session('success') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            @if(session('warning'))
                <div class="alert alert-warning alert-dismissible fade show">
                    {{ session('warning') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            @if(session('errors_list'))
                <div class="alert alert-danger">
                    <strong>Errors:</strong>
                    <ul class="mb-0 mt-2">
                        @foreach(session('errors_list') as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if($errors->any())
                <div class="alert alert-danger">
                    <ul class="mb-0">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form action="{{ route('admin.rights.batch.store') }}" method="POST">
                @csrf

                {{-- Record Selection --}}
                <fieldset class="mb-4">
                    <legend class="h5 border-bottom pb-2 mb-3">
                        <i class="fas fa-folder-open me-2 text-success"></i>Option A: Select a Fonds/Collection
                    </legend>

                    <div class="row">
                        <div class="col-md-8">
                            <div class="mb-3">
                                <label for="information_object_id" class="form-label">Select a record</label>
                                <select name="information_object_id" id="information_object_id" class="form-select">
                                    <option value="">-- Select a record --</option>
                                    @foreach($records as $record)
                                        <option value="{{ $record['id'] }}" {{ old('information_object_id') == $record['id'] ? 'selected' : '' }}>
                                            [{{ $record['identifier'] ?: 'No ID' }}] {{ $record['title'] }} ({{ $record['level'] ?: 'N/A' }})
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Apply to</label>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="scope" id="scope_selected" value="selected" checked>
                                <label class="form-check-label" for="scope_selected">Selected only</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="scope" id="scope_children" value="children">
                                <label class="form-check-label" for="scope_children">Direct children</label>
                            </div>
                            <div class="form-check mt-2">
                                <input class="form-check-input" type="checkbox" name="include_parent" id="include_parent" value="1" checked>
                                <label class="form-check-label" for="include_parent">Include the parent record itself</label>
                            </div>
                        </div>
                    </div>
                </fieldset>

                <fieldset class="mb-4">
                    <legend class="h5 border-bottom pb-2 mb-3">
                        <i class="fas fa-keyboard me-2 text-success"></i>Option B: Enter Object IDs Manually
                    </legend>
                    <div class="mb-3">
                        <label for="object_ids" class="form-label">Object IDs (comma-separated)</label>
                        <textarea class="form-control" id="object_ids" name="object_ids" rows="2" placeholder="e.g., 12345, 12346, 12347">{{ old('object_ids') }}</textarea>
                    </div>
                </fieldset>

                {{-- Rights Information --}}
                <fieldset class="mb-4">
                    <legend class="h5 border-bottom pb-2 mb-3">
                        <i class="fas fa-balance-scale me-2 text-success"></i>Rights Information
                    </legend>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="rights_basis_id" class="form-label">Rights Basis</label>
                                <select name="rights_basis_id" id="rights_basis_id" class="form-select">
                                    <option value="">-- Select --</option>
                                    @foreach($rightsBasis as $basis)
                                        <option value="{{ $basis->id }}" {{ old('rights_basis_id') == $basis->id ? 'selected' : '' }}>
                                            {{ $basis->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="copyright_status_id" class="form-label">Copyright Status</label>
                                <select name="copyright_status_id" id="copyright_status_id" class="form-select">
                                    <option value="">-- Select --</option>
                                    @foreach($copyrightStatus as $status)
                                        <option value="{{ $status->id }}" {{ old('copyright_status_id') == $status->id ? 'selected' : '' }}>
                                            {{ $status->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="rights_holder_id" class="form-label">Rights Holder</label>
                                <select name="rights_holder_id" id="rights_holder_id" class="form-select">
                                    <option value="">-- Select --</option>
                                    @foreach($rightsHolders as $holder)
                                        <option value="{{ $holder->id }}" {{ old('rights_holder_id') == $holder->id ? 'selected' : '' }}>
                                            {{ $holder->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="restriction" class="form-label">Restriction</label>
                                <select name="restriction" id="restriction" class="form-select">
                                    <option value="1" {{ old('restriction', 1) == 1 ? 'selected' : '' }}>Allow</option>
                                    <option value="0" {{ old('restriction') === '0' ? 'selected' : '' }}>Disallow</option>
                                    <option value="2" {{ old('restriction') == 2 ? 'selected' : '' }}>Conditional</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="start_date" class="form-label">Start Date</label>
                                <input type="date" class="form-control" id="start_date" name="start_date" value="{{ old('start_date') }}">
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="end_date" class="form-label">End Date</label>
                                <input type="date" class="form-control" id="end_date" name="end_date" value="{{ old('end_date') }}">
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="rights_note" class="form-label">Rights Note</label>
                        <textarea class="form-control" id="rights_note" name="rights_note" rows="3">{{ old('rights_note') }}</textarea>
                    </div>

                    <div class="mb-3">
                        <label for="copyright_note" class="form-label">Copyright Note</label>
                        <textarea class="form-control" id="copyright_note" name="copyright_note" rows="3">{{ old('copyright_note') }}</textarea>
                    </div>
                </fieldset>

                <div class="d-flex justify-content-end gap-2">
                    <a href="{{ url('/index.php/admin/rights') }}" class="btn btn-secondary">Cancel</a>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-check me-1"></i>Apply Rights
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
