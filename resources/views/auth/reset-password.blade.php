<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password | MHIS Central Payment</title>
    <link rel="stylesheet" href="/assets/vendor/fonts/boxicons.css">
    <link rel="stylesheet" href="/assets/vendor/css/core.css">
    <link rel="stylesheet" href="/assets/vendor/css/theme-default.css">
    <link rel="stylesheet" href="/assets/css/demo.css">
    <link rel="stylesheet" href="/assets/vendor/css/pages/page-auth.css">
</head>
<body>
    <div class="container-xxl">
        <div class="authentication-wrapper authentication-basic container-p-y">
            <div class="authentication-inner">
                <div class="card">
                    <div class="card-body">
                        <h4 class="mb-2 text-center">Reset Password</h4>
                        <form action="{{ route('password.store') }}" method="POST">
                            @csrf
                            <input type="hidden" name="token" value="{{ $token }}">
                            <div class="mb-3">
                                <label for="email" class="form-label">Email</label>
                                <input required type="email" class="form-control @error('email') is-invalid @enderror"
                                    id="email" name="email" value="{{ old('email') }}" autocomplete="email" autofocus>
                                @error('email')
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
                            <button class="btn btn-primary d-grid w-100" type="submit">Reset Password</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
