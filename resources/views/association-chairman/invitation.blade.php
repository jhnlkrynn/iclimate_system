<x-guest-layout>
<div class="container py-4"><h1>Association chairman invitation</h1><p>Association: {{ $invitation->assignment->association->name }}</p>
<form method="POST">@csrf <label>Password</label><input class="form-control" type="password" name="password" required minlength="12"><label class="mt-2">Confirm password</label><input class="form-control" type="password" name="password_confirmation" required minlength="12"><button class="btn btn-primary mt-3">Activate account</button></form></div>
</x-guest-layout>
