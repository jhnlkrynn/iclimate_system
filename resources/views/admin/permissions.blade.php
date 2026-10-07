<x-app-layout>
    <x-slot:title>Roles & Permissions</x-slot:title>
    {{-- Manuscript – Naicel (2026-10-08), Phase 8: SystemSettingsController::permissions (App\Support\ModuleAccess). --}}
    <div class="mfd itd itp" data-permissions-page>
        <x-it.page-header kicker="IT Personnel" title="Roles & Permissions" lede="Which modules each role can open. Untick a box to close a module to that role; a dash means the role never has that module." />
        @include('partials.manuscript-tabs', ['group' => 'settings', 'label' => 'Settings'])

        <form method="POST" action="{{ route('admin.permissions.save') }}" data-loading="true">
            @csrf
            <div class="table-responsive mfd-card p-0">
                <table class="table table-sm align-middle mb-0" data-permission-matrix>
                    <thead><tr><th>Module</th>@foreach ($roles as $label)<th class="text-center">{{ $label }}</th>@endforeach</tr></thead>
                    <tbody>
                        @foreach ($modules as $key => [$label, $patterns, $supported, $locked])
                            <tr>
                                <td>{{ $label }}</td>
                                @foreach ($roles as $role => $roleLabel)
                                    <td class="text-center">
                                        @if (! in_array($role, $supported, true))
                                            <span class="text-muted" aria-label="Not available">—</span>
                                        @else
                                            <input class="form-check-input" type="checkbox" name="modules[{{ $key }}][]" value="{{ $role }}" aria-label="{{ $roleLabel }}: {{ $label }}"
                                                @checked(in_array($role, $allowed[$key], true)) @disabled(in_array($role, $locked, true))>
                                            @if (in_array($role, $locked, true))<input type="hidden" name="modules[{{ $key }}][]" value="{{ $role }}">@endif
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <p class="small text-muted mt-2">IT always keeps the IT modules, so the system can always be managed. A closed module sends that role back to its dashboard.</p>
            <button class="btn btn-primary" type="submit" data-loading-text="Saving...">Save permissions</button>
        </form>
    </div>
</x-app-layout>
