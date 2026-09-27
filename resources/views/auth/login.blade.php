<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no, user-scalable=no, minimal-ui">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>Iniciar sesión - {{ config('app.name') }}</title>
        <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('smartadmin/img/favicon/apple-touch-icon.png') }}">
        <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('smartadmin/img/favicon/favicon-32x32.png') }}">
        <link rel="stylesheet" media="screen, print" href="{{ asset('smartadmin/css/vendors.bundle.css') }}">
        <link rel="stylesheet" media="screen, print" href="{{ asset('smartadmin/css/app.bundle.css') }}">
        <link rel="stylesheet" media="screen, print" href="{{ asset('smartadmin/css/page-login.css') }}">
    </head>
    <body>
        <div class="blankpage-form-field">
            <div class="page-logo m-0 w-100 align-items-center justify-content-center rounded border-bottom-left-radius-0 border-bottom-right-radius-0 px-4">
                <a href="{{ route('home') }}" class="page-logo-link press-scale-down d-flex align-items-center">
                    <img src="{{ asset('smartadmin/img/logo.png') }}" alt="{{ config('app.name') }}">
                    <span class="page-logo-text mr-1">{{ config('app.name') }}</span>
                </a>
            </div>
            <div class="card p-4 border-top-left-radius-0 border-top-right-radius-0">
                <form method="POST" action="{{ route('login') }}">
                    @csrf
                    <div class="form-group">
                        <label class="form-label" for="email">Correo electrónico</label>
                        <input type="email" id="email" name="email" class="form-control @error('email') is-invalid @enderror" placeholder="tu@correo.com" value="{{ old('email') }}" required autofocus autocomplete="username">
                        @error('email')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="password">Contraseña</label>
                        <input type="password" id="password" name="password" class="form-control @error('password') is-invalid @enderror" placeholder="Contraseña" required autocomplete="current-password">
                        @error('password')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="form-group text-left">
                        <div class="custom-control custom-checkbox">
                            <input type="checkbox" class="custom-control-input" id="remember" name="remember" value="1" @checked(old('remember'))>
                            <label class="custom-control-label" for="remember">Recordarme</label>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-default float-right">Iniciar sesión</button>
                </form>
            </div>
            <div class="blankpage-footer text-center">
                <a href="{{ route('register') }}"><strong>Crear cuenta</strong></a>
            </div>
        </div>
        <video poster="{{ asset('smartadmin/img/backgrounds/clouds.png') }}" id="bgvid" playsinline autoplay muted loop>
            <source src="{{ asset('smartadmin/media/video/cc.webm') }}" type="video/webm">
            <source src="{{ asset('smartadmin/media/video/cc.mp4') }}" type="video/mp4">
        </video>
        <script src="{{ asset('smartadmin/js/vendors.bundle.js') }}"></script>
        <script src="{{ asset('smartadmin/js/app.bundle.js') }}"></script>
    </body>
</html>
