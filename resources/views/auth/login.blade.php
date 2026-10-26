<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Login — SMP Absensi RFID</title>
    <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}?v=2" sizes="any" />
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet" />
    <style>
        *,
        *::before,
        *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #1e3a8a 0%, #1e40af 50%, #2563eb 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .login-card {
            background: white;
            border-radius: 16px;
            padding: 48px 40px;
            width: 100%;
            max-width: 400px;
            box-shadow: 0 25px 50px rgba(0, 0, 0, .3);
        }

        .login-logo {
            text-align: center;
            margin-bottom: 32px;
        }

        .login-logo .icon {
            display: block;
            width: 180px;
            height: auto;
            margin: 0 auto 20px;
            object-fit: contain;
        }

        .login-logo h1 {
            font-size: 22px;
            font-weight: 700;
            color: #111827;
        }

        .login-logo p {
            font-size: 13px;
            color: #6b7280;
            margin-top: 4px;
        }

        .form-group {
            margin-bottom: 18px;
        }

        .form-group label {
            display: block;
            font-size: 13px;
            font-weight: 500;
            color: #374151;
            margin-bottom: 6px;
        }

        .form-control {
            width: 100%;
            padding: 10px 14px;
            border: 1.5px solid #d1d5db;
            border-radius: 8px;
            font-size: 14px;
            color: #111827;
            transition: border-color .15s;
        }

        .form-control:focus {
            outline: none;
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, .12);
        }

        .form-control.is-invalid {
            border-color: #dc2626;
        }

        .invalid-feedback {
            font-size: 12px;
            color: #dc2626;
            margin-top: 4px;
        }

        .form-check {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 24px;
        }

        .form-check label {
            font-size: 13px;
            color: #4b5563;
            cursor: pointer;
        }

        .btn-login {
            width: 100%;
            padding: 11px;
            background: #2563eb;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: background .15s;
        }

        .btn-login:hover {
            background: #1d4ed8;
        }
    </style>
</head>

<body>
    <div class="login-card">
        <div class="login-logo">
            <img src="{{ asset('images/logo-smp.jpg') }}" alt="Logo LP Ma'arif NU" class="icon" />
            <h3>Absensi SMP Wahid Hasyim <br> Jipo Kepohbaru </h3> 
            {{-- <p>Sistem Absensi Siswa Berbasis RFID</p> --}}
        </div>

        <form method="POST" action="{{ route('login') }}">
            @csrf

            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email"
                    class="form-control {{ $errors->has('email') ? 'is-invalid' : '' }}" value="{{ old('email') }}"
                    placeholder="nama@sekolah.ac.id" autofocus required />
                @error('email')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password"
                    class="form-control {{ $errors->has('password') ? 'is-invalid' : '' }}" placeholder="••••••••"
                    required />
                @error('password')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-check">
                <input type="checkbox" id="remember" name="remember" />
                <label for="remember">Ingat saya</label>
            </div>

            <button type="submit" class="btn-login">Masuk</button>
        </form>
    </div>
</body>

</html>
