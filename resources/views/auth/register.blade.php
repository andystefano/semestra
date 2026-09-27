<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no, user-scalable=no, minimal-ui">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>Crear cuenta - {{ config('app.name') }}</title>
        <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('smartadmin/img/favicon/apple-touch-icon.png') }}">
        <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('smartadmin/img/favicon/favicon-32x32.png') }}">
        <link rel="stylesheet" media="screen, print" href="{{ asset('smartadmin/css/vendors.bundle.css') }}">
        <link rel="stylesheet" media="screen, print" href="{{ asset('smartadmin/css/app.bundle.css') }}">
        <link rel="stylesheet" media="screen, print" href="{{ asset('smartadmin/css/fa-brands.css') }}">
    </head>
    <body>
        <div class="page-wrapper">
            <div class="page-inner bg-brand-gradient">
                <div class="page-content-wrapper bg-transparent m-0">
                    <div class="height-10 w-100 shadow-lg px-4 bg-brand-gradient">
                        <div class="d-flex align-items-center container p-0">
                            <div class="page-logo width-mobile-auto m-0 align-items-center justify-content-center p-0 bg-transparent bg-img-none shadow-0 height-9">
                                <a href="{{ route('home') }}" class="page-logo-link press-scale-down d-flex align-items-center">
                                    <img src="{{ asset('smartadmin/img/logo.png') }}" alt="{{ config('app.name') }}">
                                    <span class="page-logo-text mr-1">{{ config('app.name') }}</span>
                                </a>
                            </div>
                            <span class="text-white opacity-50 ml-auto mr-2 hidden-sm-down">¿Ya tienes cuenta?</span>
                            <a href="{{ route('login') }}" class="btn-link text-white ml-auto ml-sm-0">Iniciar sesión</a>
                        </div>
                    </div>
                    <div class="flex-1" style="background: url({{ asset('smartadmin/img/svg/pattern-1.svg') }}) no-repeat center bottom fixed; background-size: cover;">
                        <div class="container py-4 py-lg-5 my-lg-5 px-4 px-sm-0">
                            <div class="row">
                                <div class="col-xl-12">
                                    <h2 class="fs-xxl fw-500 mt-4 text-white text-center">
                                        Crear cuenta
                                        <small class="h3 fw-300 mt-3 mb-5 text-white opacity-60 hidden-sm-down">
                                            Regístrate para entrar al backoffice.
                                        </small>
                                    </h2>
                                </div>
                                <div class="col-xl-6 ml-auto mr-auto">
                                    <div class="card p-4 rounded-plus bg-faded">
                                        <form method="POST" action="{{ route('register') }}">
                                            @csrf
                                            <div class="form-group">
                                                <label class="form-label" for="name">Nombre</label>
                                                <input type="text" id="name" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name') }}" required autofocus autocomplete="name">
                                                @error('name')
                                                    <div class="invalid-feedback">{{ $message }}</div>
                                                @enderror
                                            </div>
                                            <div class="form-group">
                                                <label class="form-label" for="email">Correo electrónico</label>
                                                <input type="email" id="email" name="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email') }}" required autocomplete="username">
                                                @error('email')
                                                    <div class="invalid-feedback">{{ $message }}</div>
                                                @enderror
                                            </div>
                                            <div class="form-group">
                                                <label class="form-label" for="password">Contraseña</label>
                                                <input type="password" id="password" name="password" class="form-control @error('password') is-invalid @enderror" required autocomplete="new-password">
                                                @error('password')
                                                    <div class="invalid-feedback">{{ $message }}</div>
                                                @enderror
                                            </div>
                                            <div class="form-group">
                                                <label class="form-label" for="password_confirmation">Confirmar contraseña</label>
                                                <input type="password" id="password_confirmation" name="password_confirmation" class="form-control" required autocomplete="new-password">
                                            </div>
                                            <div class="row no-gutters">
                                                <div class="col-md-4 ml-auto text-right">
                                                    <button type="submit" class="btn btn-block btn-danger btn-lg mt-3">Crear cuenta</button>
                                                </div>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="position-absolute pos-bottom pos-left pos-right p-3 text-center text-white">
                            {{ now()->year }} © {{ config('app.name') }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <script src="{{ asset('smartadmin/js/vendors.bundle.js') }}"></script>
        <script src="{{ asset('smartadmin/js/app.bundle.js') }}"></script>
    </body>
</html>
