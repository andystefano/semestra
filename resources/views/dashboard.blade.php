<x-app-layout>
    <ol class="breadcrumb page-breadcrumb">
        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">{{ config('app.name') }}</a></li>
        <li class="breadcrumb-item active">Panel</li>
        <li class="position-absolute pos-top pos-right d-none d-sm-block"><span class="js-get-date"></span></li>
    </ol>

    <div class="subheader">
        <h1 class="subheader-title">
            <i class="subheader-icon fal fa-home"></i> Hola, <span class="fw-300">{{ auth()->user()->name }}</span>
            <small>Backoffice</small>
        </h1>
    </div>

    <div class="row">
        <div class="col-sm-6 col-xl-4">
            <div class="p-3 bg-primary-300 rounded overflow-hidden position-relative text-white mb-g">
                <div>
                    <h3 class="display-4 d-block l-h-n m-0 fw-500">
                        {{ auth()->user()->name }}
                        <small class="m-0 l-h-n">Cuenta activa</small>
                    </h3>
                </div>
                <i class="fal fa-user position-absolute pos-right pos-bottom opacity-15 mb-n1 mr-n1" style="font-size: 6rem;"></i>
            </div>
        </div>
        <div class="col-sm-6 col-xl-4">
            <div class="p-3 bg-success-200 rounded overflow-hidden position-relative text-white mb-g">
                <div>
                    <h3 class="display-4 d-block l-h-n m-0 fw-500">
                        Sesión
                        <small class="m-0 l-h-n">{{ auth()->user()->email }}</small>
                    </h3>
                </div>
                <i class="fal fa-envelope position-absolute pos-right pos-bottom opacity-15 mb-n1 mr-n1" style="font-size: 6rem;"></i>
            </div>
        </div>
        <div class="col-sm-6 col-xl-4">
            <div class="p-3 bg-info-200 rounded overflow-hidden position-relative text-white mb-g">
                <div>
                    <h3 class="display-4 d-block l-h-n m-0 fw-500">
                        Listo
                        <small class="m-0 l-h-n">El panel está disponible</small>
                    </h3>
                </div>
                <i class="fal fa-check-circle position-absolute pos-right pos-bottom opacity-15 mb-n1 mr-n1" style="font-size: 6rem;"></i>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-xl-12">
            <div id="panel-1" class="panel">
                <div class="panel-hdr">
                    <h2>Inicio <span class="fw-300"><i>del backoffice</i></span></h2>
                </div>
                <div class="panel-container show">
                    <div class="panel-content">
                        <p class="mb-0">Esta es la vista principal después de iniciar sesión. El menú de la izquierda concentra las secciones del sistema.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
