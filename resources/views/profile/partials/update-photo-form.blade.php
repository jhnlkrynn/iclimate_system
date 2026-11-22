{{-- Profile photo: shown in the top bar's account button; without one, the initials show. --}}
<section class="ic-photo-form" aria-labelledby="profilePhotoTitle">
    <x-user-avatar :user="$user" :size="64" class="ic-photo-current" />
    <div class="ic-photo-body">
        <h2 id="profilePhotoTitle" class="ic-photo-title">Profile photo</h2>
        <p class="ic-photo-hint">A square JPG, PNG or WebP image, up to 2 MB. It appears at the top right of every page.</p>
        <div class="ic-photo-actions">
            <form method="POST" action="{{ route('profile.photo.update') }}" enctype="multipart/form-data">
                @csrf
                <label class="btn btn-primary mb-0" for="profilePhotoInput">{{ $user->avatarUrl() ? 'Change photo' : 'Upload photo' }}</label>
                <input id="profilePhotoInput" class="visually-hidden" type="file" name="photo" accept="image/jpeg,image/png,image/webp" data-autosubmit>
            </form>
            @if ($user->avatarUrl())
                <form method="POST" action="{{ route('profile.photo.destroy') }}">
                    @csrf
                    @method('delete')
                    <button type="submit" class="btn btn-light">Remove</button>
                </form>
            @endif
        </div>
        @error('photo')<p class="ic-photo-error" role="alert">{{ $message }}</p>@enderror
        @if (session('status') === 'photo-updated')<p class="ic-photo-ok" role="status">Photo saved.</p>@endif
        @if (session('status') === 'photo-removed')<p class="ic-photo-ok" role="status">Photo removed.</p>@endif
    </div>
</section>
