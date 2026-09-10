<x-app-layout>
    <section class="page-hero">
        <div class="d-flex flex-column flex-lg-row justify-content-between gap-3 align-items-lg-end">
            <div>
                <div class="eyebrow mb-2">Climora AI</div>
                <h1 class="h2 fw-bold mb-2">{{ $method === 'POST' ? 'Create Chatbot Response' : 'Edit Chatbot Response' }}</h1>
                <p class="mb-0" style="color: var(--ic-ink-mid);">Use comma-separated keywords and choose which roles can receive this answer.</p>
            </div>
            <a class="btn btn-light align-self-start align-self-lg-end" href="{{ route('chatbot-responses.index') }}">Back to List</a>
        </div>
    </section>

    <div class="card no-lift">
        <div class="card-body p-4">
            <form method="POST" action="{{ $action }}" data-loading="true">
                @csrf
                @if($method !== 'POST')
                    @method($method)
                @endif

                <div class="row g-3">
                    <div class="col-md-4">
                        <div class="soft-section p-3 h-100">
                            <label class="form-label fw-semibold" for="category">Question / Category</label>
                            <input id="category" name="category" class="form-control @error('category') is-invalid @enderror" value="{{ old('category', $response->category) }}" placeholder="Dashboard Help">
                            @error('category')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="soft-section p-3 h-100">
                            <label class="form-label fw-semibold" for="language">Language</label>
                            <select id="language" name="language" class="form-select @error('language') is-invalid @enderror">
                                @foreach($languages as $value => $label)
                                    <option value="{{ $value }}" @selected(old('language', $response->language ?: 'English') === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('language')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="soft-section p-3 h-100">
                            <label class="form-label fw-semibold" for="status">Status</label>
                            <select id="status" name="status" class="form-select @error('status') is-invalid @enderror">
                                @foreach($statuses as $value => $label)
                                    <option value="{{ $value }}" @selected(old('status', $response->status) === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('status')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="soft-section p-3">
                            <label class="form-label fw-semibold" for="question">Question</label>
                            <input id="question" name="question" class="form-control @error('question') is-invalid @enderror" value="{{ old('question', $response->question) }}" placeholder="How do I use the dashboard?">
                            @error('question')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="soft-section p-3">
                            <label class="form-label fw-semibold" for="answer">Answer</label>
                            <textarea id="answer" name="answer" rows="7" class="form-control @error('answer') is-invalid @enderror" placeholder="Write the friendly answer Climora should return.">{{ old('answer', $response->answer) }}</textarea>
                            @error('answer')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="soft-section p-3">
                            <label class="form-label fw-semibold" for="keywords">Keywords</label>
                            <textarea id="keywords" name="keywords" rows="3" class="form-control @error('keywords') is-invalid @enderror" placeholder="weather, forecast, rainfall, dashboard">{{ old('keywords', $response->keywords_text) }}</textarea>
                            <div class="form-text">Separate keywords or phrases with commas. Synonyms like rain/rainfall/forecast are also considered during matching.</div>
                            @error('keywords')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="soft-section p-3">
                            <label class="form-label fw-semibold" for="synonyms">Synonyms / Related Terms</label>
                            <textarea id="synonyms" name="synonyms" rows="3" class="form-control @error('synonyms') is-invalid @enderror" placeholder="pagtatanim, paano magtanim, forecast ng ulan">{{ old('synonyms', $response->synonyms_text) }}</textarea>
                            <div class="form-text">Optional comma-separated terms, misspellings, Taglish phrases, or local farming words.</div>
                            @error('synonyms')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="soft-section p-3">
                            <label class="form-label fw-semibold d-block">Role Visibility</label>
                            <div class="d-flex flex-wrap gap-3">
                                @foreach($roles as $value => $label)
                                    <div class="form-check">
                                        <input class="form-check-input" id="role_{{ \Illuminate\Support\Str::slug($value) }}" name="visible_roles[]" type="checkbox" value="{{ $value }}" @checked(in_array($value, old('visible_roles', $response->visible_roles ?? []), true))>
                                        <label class="form-check-label fw-semibold" for="role_{{ \Illuminate\Support\Str::slug($value) }}">{{ $label }}</label>
                                    </div>
                                @endforeach
                            </div>
                            <div class="form-text">Leave all unchecked to make the answer visible to every role.</div>
                            @error('visible_roles')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>
                    </div>
                </div>

                <div class="d-flex flex-column flex-sm-row gap-2 mt-4 action-cluster">
                    <button class="btn btn-primary" type="submit" data-loading-text="Saving...">Save Response</button>
                    <a class="btn btn-outline-secondary" href="{{ route('chatbot-responses.index') }}">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
