<x-app-layout>
    <x-slot:title>MAO Personnel</x-slot:title>
    {{-- Panel revision 4.4 (MaoPersonnelController): who covers which barangay. The Municipal Agriculturist adds accounts
         and edits positions, area covered and barangay assignments; everyone else reads it. --}}
    <div class="mfd itd itp" data-mao-personnel>
        <x-it.page-header kicker="Municipal Agriculture Office" title="MAO Personnel"
            lede="Each MAO account belongs to one staff member, with their position and the barangays they cover." />

        @include('partials.manuscript-tabs', ['group' => 'mao-office', 'label' => 'MAO Office'])

        @if ($unassigned->isNotEmpty())
            <p class="pr-note" data-unassigned>
                <strong>Barangays without assigned field personnel:</strong> {{ $unassigned->implode(', ') }}.
                The Municipal Agriculturist covers these directly.
            </p>
        @endif

        <div class="pr-people">
            @foreach ($personnel as $person)
                <article class="mfd-card pr-person" id="mao-{{ $person->id }}" data-mao-person="{{ $person->id }}">
                    <header class="pr-person-head">
                        <span class="pr-avatar" aria-hidden="true">{{ collect(explode(' ', $person->name))->filter()->map(fn ($p) => mb_substr($p, 0, 1))->take(2)->implode('') }}</span>
                        <div>
                            <h2 class="pr-person-name">{{ $person->name }}</h2>
                            <p class="pr-person-role">{{ $person->positionLabel() }}</p>
                        </div>
                        <span @class(['pr-badge', 'is-head' => $person->isMaoHead(), 'is-blue' => ! $person->isMaoHead()])>{{ $person->isMaoHead() ? 'Admin · Head' : 'MAO' }}</span>
                        @if ($person->must_change_password)
                            <span class="pr-badge is-off" title="Still on the default password">Must change password</span>
                        @endif
                        @if ($person->status !== \App\Models\User::STATUS_ACTIVE)
                            <span class="pr-badge is-off">{{ $person->status }}</span>
                        @endif
                    </header>
                    <dl class="pr-facts">
                        <div><dt>Station</dt><dd>{{ $person->station_type ?: '—' }}</dd></div>
                        <div><dt>Area covered</dt><dd>{{ $person->area_covered_ha !== null ? number_format((float) $person->area_covered_ha, 2).' ha' : '—' }}</dd></div>
                        <div class="pr-wide"><dt>Barangays covered</dt><dd>{{ $person->coverageLabel() }}</dd></div>
                        <div class="pr-wide"><dt>Sign-in</dt><dd>{{ $person->email }}</dd></div>
                    </dl>

                    @if ($canManage)
                        {{-- Panel revision 4.4 A: back to the default password, to be changed at the next sign-in. --}}
                        <form method="POST" action="{{ route('mao.personnel.reset-password', $person) }}" data-ic-confirm="Reset {{ $person->name }}'s password to the default? They will have to change it at the next sign-in." data-loading="true">
                            @csrf
                            <button class="btn btn-outline-secondary" type="submit" data-loading-text="Resetting...">Reset password to default</button>
                        </form>
                        <details class="pr-edit">
                            <summary class="btn btn-outline-primary">Edit account and coverage</summary>
                            <form method="POST" action="{{ route('mao.personnel.update', $person) }}" class="pr-form" data-loading="true">
                                @csrf @method('PUT')
                                @include('mao._personnel-fields', ['person' => $person])
                                <label class="form-label" for="status-{{ $person->id }}">Account status</label>
                                <select class="form-select" id="status-{{ $person->id }}" name="status" @disabled($person->is(auth()->user()))>
                                    @foreach ([\App\Models\User::STATUS_ACTIVE, \App\Models\User::STATUS_INACTIVE] as $status)
                                        <option value="{{ $status }}" @selected($person->status === $status)>{{ $status }}</option>
                                    @endforeach
                                </select>
                                @if ($person->is(auth()->user()))<input type="hidden" name="status" value="{{ \App\Models\User::STATUS_ACTIVE }}">@endif
                                <button class="btn btn-primary mt-3" type="submit" data-loading-text="Saving...">Save changes</button>
                            </form>
                        </details>
                    @endif
                </article>
            @endforeach
        </div>

        @if ($canManage)
            <section class="mfd-card pr-add" aria-labelledby="prAddTitle">
                <h2 id="prAddTitle" class="h5">Add an MAO account</h2>
                <p class="text-muted">For a new staff member. Leave the e-mail blank to make it from the name (e.g. juandelacruz@{{ config('iclimate.mao_email_domain') }}; a number is added if it is taken). Leave the password blank to use the default password. They must change it at the first sign-in.</p>
                <form method="POST" action="{{ route('mao.personnel.store') }}" class="pr-form" data-loading="true">
                    @csrf
                    @include('mao._personnel-fields', ['person' => null])
                    <div class="pr-grid">
                        <div>
                            <label class="form-label" for="new-email">E-mail (sign-in, optional)</label>
                            <input class="form-control" id="new-email" name="email" type="email" value="{{ old('email') }}" autocomplete="off" placeholder="made from the name">
                        </div>
                        <div>
                            <label class="form-label" for="new-password">First password (optional)</label>
                            <input class="form-control" id="new-password" name="password" type="password" autocomplete="new-password" placeholder="default password">
                        </div>
                    </div>
                    <button class="btn btn-primary mt-3" type="submit" data-loading-text="Adding...">Add account</button>
                </form>
            </section>
        @endif
    </div>
    <datalist id="maoPositions">
        <option value="Municipal Agriculturist">
        <option value="Agricultural Technologist">
        <option value="Agricultural Extension Worker">
        <option value="Administrative Aide I, designated Agricultural Extension Worker">
    </datalist>
</x-app-layout>
