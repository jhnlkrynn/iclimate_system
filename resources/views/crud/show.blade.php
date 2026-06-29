<x-app-layout>
    <section class="page-hero">
        <div class="d-flex flex-column flex-lg-row justify-content-between gap-3 align-items-lg-end">
            <div>
                <div class="eyebrow mb-2">Record Details</div>
                <h1 class="h2 fw-bold mb-2">{{ $title }} Details</h1>
                <p class="mb-0 text-white-50">Record #{{ $record->id }}</p>
            </div>
            <div class="d-flex gap-2 align-self-start align-self-lg-end action-cluster">
                <a class="btn btn-outline-secondary" href="{{ route($routeName.'.index') }}">Back</a>
                @if ($canManage)
                    <a class="btn btn-light" href="{{ route($routeName.'.edit', $record) }}">Edit</a>
                @endif
            </div>
        </div>
    </section>

    <div class="card no-lift">
        <div class="card-body">
            <dl class="row mb-0 details-list g-3">
                @foreach ($columns as $field => $label)
                    <dt class="col-sm-3">{{ $label }}</dt>
                    <dd class="col-sm-9">{{ data_get($record, $field) }}</dd>
                @endforeach
                <dt class="col-sm-3">Created</dt>
                <dd class="col-sm-9">{{ $record->created_at }}</dd>
                <dt class="col-sm-3">Updated</dt>
                <dd class="col-sm-9">{{ $record->updated_at }}</dd>
            </dl>
        </div>
    </div>
</x-app-layout>