<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no, user-scalable=no, minimal-ui">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ $title ?? 'Panel' }} - {{ config('app.name') }}</title>

        <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('smartadmin/img/favicon/apple-touch-icon.png') }}">
        <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('smartadmin/img/favicon/favicon-32x32.png') }}">
        <link rel="mask-icon" href="{{ asset('smartadmin/img/favicon/safari-pinned-tab.svg') }}" color="#5bbad5">
        <link rel="stylesheet" media="screen, print" href="{{ asset('smartadmin/css/vendors.bundle.css') }}">
        <link rel="stylesheet" media="screen, print" href="{{ asset('smartadmin/css/app.bundle.css') }}">
        @stack('styles')
    </head>
    <body class="mod-bg-1">
        <script>
            'use strict';

            var classHolder = document.getElementsByTagName('BODY')[0],
                themeSettings = localStorage.getItem('themeSettings') ? JSON.parse(localStorage.getItem('themeSettings')) : {},
                themeURL = themeSettings.themeURL || '';

            if (themeSettings.themeOptions) {
                classHolder.className = themeSettings.themeOptions;
            }

            if (themeSettings.themeURL && ! document.getElementById('mytheme')) {
                var cssfile = document.createElement('link');
                cssfile.id = 'mytheme';
                cssfile.rel = 'stylesheet';
                cssfile.href = themeURL;
                document.getElementsByTagName('head')[0].appendChild(cssfile);
            }

            var saveSettings = function () {
                themeSettings.themeOptions = String(classHolder.className).split(/[^\w-]+/).filter(function (item) {
                    return /^(nav|header|mod|display)-/i.test(item);
                }).join(' ');

                if (document.getElementById('mytheme')) {
                    themeSettings.themeURL = document.getElementById('mytheme').getAttribute('href');
                }

                localStorage.setItem('themeSettings', JSON.stringify(themeSettings));
            };

            var resetSettings = function () {
                localStorage.setItem('themeSettings', '');
            };
        </script>

        <div class="page-wrapper">
            <div class="page-inner">
                <aside class="page-sidebar">
                    <div class="page-logo">
                        <a href="{{ route('dashboard') }}" class="page-logo-link press-scale-down d-flex align-items-center position-relative">
                            <img src="{{ asset('smartadmin/img/logo.png') }}" alt="{{ config('app.name') }}">
                            <span class="page-logo-text mr-1">{{ config('app.name') }}</span>
                        </a>
                    </div>

                    <nav id="js-primary-nav" class="primary-nav" role="navigation">
                        <div class="info-card">
                            <img src="{{ asset('smartadmin/img/demo/avatars/avatar-admin.png') }}" class="profile-image rounded-circle" alt="{{ auth()->user()->name }}">
                            <div class="info-card-text">
                                <a href="{{ route('dashboard') }}" class="d-flex align-items-center text-white">
                                    <span class="text-truncate text-truncate-sm d-inline-block">{{ auth()->user()->name }}</span>
                                </a>
                                <span class="d-inline-block text-truncate text-truncate-sm">{{ auth()->user()->email }}</span>
                            </div>
                            <img src="{{ asset('smartadmin/img/card-backgrounds/cover-2-lg.png') }}" class="cover" alt="">
                        </div>

                        <ul id="js-nav-menu" class="nav-menu">
                            <li class="{{ request()->routeIs('dashboard') ? 'active' : '' }}">
                                <a href="{{ route('dashboard') }}" title="Panel" data-filter-tags="panel">
                                    <i class="fal fa-home"></i>
                                    <span class="nav-link-text">Panel</span>
                                </a>
                            </li>
                            <li class="{{ request()->routeIs('asignaturas.*') ? 'active open' : '' }}">
                                <a href="#" title="Asignaturas" data-filter-tags="asignaturas">
                                    <i class="fal fa-book"></i>
                                    <span class="nav-link-text">Asignaturas</span>
                                </a>
                                <ul>
                                    <li class="{{ request()->routeIs('asignaturas.index') ? 'active' : '' }}">
                                        <a href="{{ route('asignaturas.index') }}" title="Listado de Asignaturas" data-filter-tags="asignaturas listado">
                                            <span class="nav-link-text">Listado de Asignaturas</span>
                                        </a>
                                    </li>
                                    <li class="{{ request()->routeIs('asignaturas.create') ? 'active' : '' }}">
                                        <a href="{{ route('asignaturas.create') }}" title="Preparar asignatura" data-filter-tags="asignaturas preparar">
                                            <span class="nav-link-text">Preparar asignatura</span>
                                        </a>
                                    </li>
                                </ul>
                            </li>
                        </ul>
                    </nav>
                </aside>

                <div class="page-content-wrapper">
                    <header class="page-header" role="banner">
                        <div class="page-logo">
                            <a href="{{ route('dashboard') }}" class="page-logo-link press-scale-down d-flex align-items-center position-relative">
                                <img src="{{ asset('smartadmin/img/logo.png') }}" alt="{{ config('app.name') }}">
                                <span class="page-logo-text mr-1">{{ config('app.name') }}</span>
                            </a>
                        </div>

                        <div class="hidden-md-down dropdown-icon-menu position-relative">
                            <a href="#" class="header-btn btn js-waves-off" data-action="toggle" data-class="nav-function-hidden" title="Ocultar menú">
                                <i class="ni ni-menu"></i>
                            </a>
                            <ul>
                                <li>
                                    <a href="#" class="btn js-waves-off" data-action="toggle" data-class="nav-function-minify" title="Comprimir menú">
                                        <i class="ni ni-minify-nav"></i>
                                    </a>
                                </li>
                                <li>
                                    <a href="#" class="btn js-waves-off" data-action="toggle" data-class="nav-function-fixed" title="Fijar menú">
                                        <i class="ni ni-lock-nav"></i>
                                    </a>
                                </li>
                            </ul>
                        </div>

                        <div class="hidden-lg-up">
                            <a href="#" class="header-btn btn press-scale-down" data-action="toggle" data-class="mobile-nav-on">
                                <i class="ni ni-menu"></i>
                            </a>
                        </div>

                        <div class="ml-auto d-flex">
                            <div>
                                <a href="#" data-toggle="dropdown" title="{{ auth()->user()->email }}" class="header-icon d-flex align-items-center justify-content-center ml-2">
                                    <img src="{{ asset('smartadmin/img/demo/avatars/avatar-admin.png') }}" class="profile-image rounded-circle" alt="{{ auth()->user()->name }}">
                                </a>
                                <div class="dropdown-menu dropdown-menu-animated dropdown-lg">
                                    <div class="dropdown-header bg-trans-gradient d-flex flex-row py-4 rounded-top">
                                        <div class="d-flex flex-row align-items-center mt-1 mb-1 color-white">
                                            <span class="mr-2">
                                                <img src="{{ asset('smartadmin/img/demo/avatars/avatar-admin.png') }}" class="rounded-circle profile-image" alt="{{ auth()->user()->name }}">
                                            </span>
                                            <div class="info-card-text">
                                                <div class="fs-lg text-truncate text-truncate-lg">{{ auth()->user()->name }}</div>
                                                <span class="text-truncate text-truncate-md opacity-80">{{ auth()->user()->email }}</span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="dropdown-divider m-0"></div>
                                    <a href="#" class="dropdown-item" data-action="app-fullscreen">
                                        <span>Pantalla completa</span>
                                    </a>
                                    <a href="#" class="dropdown-item" data-action="app-print">
                                        <span>Imprimir</span>
                                    </a>
                                    <div class="dropdown-divider m-0"></div>
                                    <form method="POST" action="{{ route('logout') }}">
                                        @csrf
                                        <button type="submit" class="dropdown-item fw-500 pt-3 pb-3">Cerrar sesión</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </header>

                    <main id="js-page-content" role="main" class="page-content">
                        {{ $slot }}
                    </main>

                    <div class="page-content-overlay" data-action="toggle" data-class="mobile-nav-on"></div>

                    <footer class="page-footer" role="contentinfo">
                        <div class="d-flex align-items-center flex-1 text-muted">
                            <span class="hidden-md-down fw-700">{{ now()->year }} © {{ config('app.name') }}</span>
                        </div>
                    </footer>
                </div>
            </div>
        </div>

        <script src="{{ asset('smartadmin/js/vendors.bundle.js') }}"></script>
        <script src="{{ asset('smartadmin/js/app.bundle.js') }}"></script>
        @stack('scripts')
    </body>
</html>
