<x-app-layout>
    <x-slot name="header">
        <div class="d-flex align-items-center justify-content-between">
            <div>
                <p class="text-muted small mb-1">{{ auth()->user()->roleLabel() }}</p>
                <h2 class="h4 mb-0 text-dark">{{ $title }}</h2>
            </div>
            <span class="badge text-bg-secondary">Authorized</span>
        </div>
    </x-slot>

    <div class="container py-5">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <h3 class="h5">{{ $mode }}</h3>
                <p class="text-muted mb-0">
                    This module route is protected by role-based authorization and is ready for the next feature build.
                </p>
            </div>
        </div>
    </div>
</x-app-layout>