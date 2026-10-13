<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password | MHIS Central Payment</title>
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
                        <h4 class="mb-2 text-center">Forgot Password?</h4>
                        <p class="mb-4">Enter your email address and we will send you a password reset link.</p>

                        @if (session('status'))
                            <div class="alert alert-success" role="alert">{{ session('status') }}</div>
                        @endif

                        <form action="{{ route('password.email') }}" method="POST">
                            @csrf
                            <div class="mb-3">
                                <label for="email" class="form-label">Email</label>
                                <input required type="email" class="form-control @error('email') is-invalid @enderror"
                                    id="email" name="email" value="{{ old('email') }}" autocomplete="email" autofocus>
                                @error('email')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <button class="btn btn-primary d-grid w-100" type="submit">Email Password Reset Link</button>
                        </form>

                        <p class="text-center mt-3 mb-0">
                            <a href="{{ route('login') }}"><i class="bx bx-chevron-left scaleX-n1-rtl bx-sm"></i> Back to login</a>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
