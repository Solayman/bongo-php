{: layout 'layouts.admin' :}

{: block 'title', __('Profile') :}

{: block 'content' :}
<?php $invalid = fn(string $field) => error($field) ? 'is-invalid' : ''; ?>
<div class="row g-3">
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header bg-white fw-semibold">Account details</div>
            <div class="card-body">
                <form method="POST" action="<?= route('profile.update') ?>" novalidate>
                    {: csrf :}
                    {: method 'PUT' :}
                    <div class="mb-3">
                        <label class="form-label" for="name">Name</label>
                        <input class="form-control <?= $invalid('name') ?>" id="name" name="name" value="{{ old('name', $user->name) }}">
                        <div class="invalid-feedback">{{ error('name') }}</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="email">Email</label>
                        <input class="form-control <?= $invalid('email') ?>" id="email" name="email" type="email" value="{{ old('email', $user->email) }}">
                        <div class="invalid-feedback">{{ error('email') }}</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="phone">Phone</label>
                        <input class="form-control <?= $invalid('phone') ?>" id="phone" name="phone" value="{{ old('phone', $user->phone ?? '') }}">
                        <div class="invalid-feedback">{{ error('phone') }}</div>
                    </div>
                    <button class="btn btn-dark" type="submit">Save profile</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header bg-white fw-semibold">Change password</div>
            <div class="card-body">
                <form method="POST" action="<?= route('profile.password') ?>" novalidate>
                    {: csrf :}
                    {: method 'PUT' :}
                    <div class="mb-3">
                        <label class="form-label" for="current_password">Current password</label>
                        <input class="form-control <?= $invalid('current_password') ?>" id="current_password" name="current_password" type="password" autocomplete="current-password">
                        <div class="invalid-feedback">{{ error('current_password') }}</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="password">New password</label>
                        <input class="form-control <?= $invalid('password') ?>" id="password" name="password" type="password" autocomplete="new-password">
                        <div class="invalid-feedback">{{ error('password') }}</div>
                        <div class="form-text">At least 8 characters.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="password_confirmation">Confirm new password</label>
                        <input class="form-control" id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password">
                    </div>
                    <button class="btn btn-dark" type="submit">Change password</button>
                </form>
            </div>
        </div>
    </div>
</div>
{: end :}
