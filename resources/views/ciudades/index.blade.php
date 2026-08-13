<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name') }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        /* En móvil la tabla completa puede ser muy larga, así que se le limita
           la altura y el encabezado queda fijo al hacer scroll dentro de ella. */
        .tabla-ciudades {
            max-height: 60vh;
        }

        .tabla-ciudades thead th {
            position: sticky;
            top: 0;
            background: var(--bs-body-bg);
        }
    </style>
</head>
<body class="bg-body-tertiary">

    {{-- Barra superior --}}
    <nav class="navbar bg-primary" data-bs-theme="dark">
        <div class="container">
            <span class="navbar-brand mb-0 h1">Ciudades del Mundo</span>
        </div>
    </nav>

    <main class="container py-3 py-md-4">

        {{-- Selector de país. Envía por GET para que la consulta quede en la URL
             y se pueda compartir o recargar sin reenviar un formulario. --}}
        <form method="GET" action="{{ route('ciudades.index') }}" class="card card-body shadow-sm mb-4">
            <label for="pais" class="form-label fw-semibold">
                ¿A qué país viajas?
            </label>
            <div class="row g-2">
                <div class="col-12 col-sm">
                    <select name="pais" id="pais"
                            class="form-select @error('pais') is-invalid @enderror"
                            required>
                        <option value="">Selecciona un país…</option>
                        @foreach ($paises as $pais)
                            <option value="{{ $pais->Code }}"
                                @selected($paisSeleccionado?->Code === $pais->Code)>
                                {{ $pais->Name }}
                            </option>
                        @endforeach
                    </select>
                    @error('pais')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-12 col-sm-auto">
                    <button type="submit" class="btn btn-primary w-100">Consultar</button>
                </div>
            </div>
        </form>

        @if ($paisSeleccionado)

            <h2 class="h4 mb-3">
                {{ $paisSeleccionado->Name }}
                <span class="badge text-bg-secondary align-middle">
                    {{ $ciudades->count() }} {{ $ciudades->count() === 1 ? 'ciudad' : 'ciudades' }}
                </span>
            </h2>

            {{-- Los dos top 10 que pide el enunciado. Se apilan en móvil y pasan
                 a dos columnas desde tablet. --}}
            @if ($ciudades->isNotEmpty())
                <div class="row g-3 mb-4">
                    <div class="col-12 col-md-6">
                        <div class="card h-100 shadow-sm">
                            <div class="card-header fw-semibold">Top 10 más pobladas</div>
                            <ol class="list-group list-group-numbered list-group-flush">
                                @foreach ($masPobladas as $ciudad)
                                    <li class="list-group-item d-flex justify-content-between align-items-center gap-2">
                                        <span>{{ $ciudad->Name }}</span>
                                        <span class="badge text-bg-success rounded-pill">
                                            {{ number_format($ciudad->Population, 0, ',', '.') }}
                                        </span>
                                    </li>
                                @endforeach
                            </ol>
                        </div>
                    </div>

                    <div class="col-12 col-md-6">
                        <div class="card h-100 shadow-sm">
                            <div class="card-header fw-semibold">Top 10 menos pobladas</div>
                            <ol class="list-group list-group-numbered list-group-flush">
                                @foreach ($menosPobladas as $ciudad)
                                    <li class="list-group-item d-flex justify-content-between align-items-center gap-2">
                                        <span>{{ $ciudad->Name }}</span>
                                        <span class="badge text-bg-secondary rounded-pill">
                                            {{ number_format($ciudad->Population, 0, ',', '.') }}
                                        </span>
                                    </li>
                                @endforeach
                            </ol>
                        </div>
                    </div>
                </div>
            @endif

            {{-- Listado completo. `table-responsive` le da scroll horizontal en
                 pantallas angostas en vez de romper el ancho de la página. --}}
            @if ($ciudades->isEmpty())
                <div class="alert alert-warning">
                    La base de datos no registra ciudades para este país.
                </div>
            @else
                <div class="card shadow-sm">
                    <div class="card-header fw-semibold">Todas las ciudades</div>
                    <div class="table-responsive tabla-ciudades">
                        <table class="table table-striped table-hover align-middle mb-0">
                            <thead>
                                <tr>
                                    <th scope="col">Ciudad</th>
                                    <th scope="col" class="d-none d-sm-table-cell">Distrito</th>
                                    <th scope="col" class="text-end">Población</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($ciudades as $ciudad)
                                    <tr>
                                        <td>{{ $ciudad->Name }}</td>
                                        <td class="text-muted d-none d-sm-table-cell">{{ $ciudad->District }}</td>
                                        <td class="text-end">{{ number_format($ciudad->Population, 0, ',', '.') }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif

        @endif

    </main>

    <footer class="container text-center text-muted small py-4">
        Datos de la base de ejemplo <code>world</code> de MySQL.
    </footer>

</body>
</html>
