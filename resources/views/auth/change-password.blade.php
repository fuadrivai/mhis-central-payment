@extends('template.main')

@section('content')
    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-6">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Change Password</h5>
                    <p class="card-text">Use a strong password with at least 12 characters, upper and lowercase letters, numbers, and symbols.</p>

                    <form action="{{ route('password.change.update') }}" method="POST">
                        @csrf
                        @method('PUT')
                        <div class="mb-3">
                            <label for="current_password" class="form-label">Current Password</label>
                            <input required type="password"
                                class="form-control @error('current_password') is-invalid @enderror" id="current_password"
                                name="current_password" autocomplete="current-password">
                            @error('current_password')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="mb-3">
                            <label for="password" class="form-label">New Password</label>
                            <input required type="password" class="form-control @error('password') is-invalid @enderror"
                                id="password" name="password" autocomplete="new-password">
                            @error('password')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="mb-3">
                            <label for="password_confirmation" class="form-label">Confirm New Password</label>
                            <input required type="password" class="form-control" id="password_confirmation"
                                name="password_confirmation" autocomplete="new-password">
                        </div>
                        <button class="btn btn-primary" type="submit">Update Password</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
