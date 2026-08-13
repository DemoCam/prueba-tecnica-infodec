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

        .grafico-contenedor {
            position: relative;
            height: 340px;
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

            {{-- La bandera usa Code2 (ISO de dos letras), no Code, porque flagcdn
                 indexa por el código de dos. Tres países del dataset ya no existen
                 —Yugoslavia, Antillas Neerlandesas y Timor Oriental— y no tienen
                 bandera publicada, así que la imagen se retira sola si no carga. --}}
            <h2 class="h4 mb-3 d-flex align-items-center flex-wrap gap-2">
                <img src="https://flagcdn.com/w80/{{ strtolower($paisSeleccionado->Code2) }}.png"
                     alt="Bandera de {{ $paisSeleccionado->Name }}"
                     width="40" height="30"
                     class="rounded border"
                     onerror="this.remove()">
                <span>{{ $paisSeleccionado->Name }}</span>
                <span class="badge text-bg-secondary">
                    {{ $ciudades->count() }} {{ $ciudades->count() === 1 ? 'ciudad' : 'ciudades' }}
                </span>
            </h2>

            {{-- Idiomas oficiales. Hay 49 países que no registran ninguno. --}}
            @if ($idiomasOficiales->isNotEmpty())
                <p class="text-muted mb-3">
                    {{ $idiomasOficiales->count() === 1 ? 'Idioma oficial' : 'Idiomas oficiales' }}:
                    <span class="text-body">{{ $idiomasOficiales->implode(' · ') }}</span>
                </p>
            @endif

            {{-- Clima actual de la capital. Cada fallo posible tiene su propio
                 mensaje: la causa es distinta y lo que el usuario puede hacer al
                 respecto también. En ningún caso se interrumpe el resto. --}}
            @if ($clima['estado'] === 'ok')
                <div class="card shadow-sm mb-4">
                    <div class="card-body d-flex align-items-center flex-wrap gap-3">
                        <img src="https://openweathermap.org/img/wn/{{ $clima['icono'] }}@2x.png"
                             alt="{{ $clima['descripcion'] }}"
                             width="64" height="64">
                        <div>
                            <div class="text-muted small text-uppercase">Clima en la capital</div>
                            <div class="fs-4 fw-semibold">
                                {{ $clima['ciudad'] }} · {{ $clima['temperatura'] }} °C
                            </div>
                            <div class="text-capitalize">{{ $clima['descripcion'] }}</div>
                        </div>
                        <div class="ms-sm-auto text-muted small">
                            Sensación térmica {{ $clima['sensacion'] }} °C<br>
                            Humedad {{ $clima['humedad'] }} %
                        </div>
                    </div>
                </div>
            @elseif ($clima['estado'] === 'sin_capital')
                <div class="alert alert-secondary d-flex gap-2">
                    <strong>Sin clima.</strong>
                    La base de datos no registra una capital para este país.
                </div>
            @elseif ($clima['estado'] === 'no_encontrada')
                <div class="alert alert-secondary d-flex gap-2">
                    <strong>Sin clima.</strong>
                    El servicio meteorológico no reconoce
                    «{{ $clima['capital'] }}», que es como esta base nombra a la capital.
                </div>
            @else
                <div class="alert alert-warning d-flex gap-2">
                    <strong>Clima no disponible.</strong>
                    No se pudo consultar el servicio meteorológico. El resto de la
                    información de la pantalla no se ve afectada.
                </div>
            @endif

            {{-- Gráfico del top 10. El contenedor lleva altura fija porque
                 Chart.js dimensiona el canvas contra su padre: sin una altura
                 definida cae a su valor por defecto de 150px y las diez barras
                 quedan aplastadas. --}}
            @if ($ciudades->isNotEmpty())
                <div class="card shadow-sm mb-4">
                    <div class="card-header fw-semibold">Población de las 10 más pobladas</div>
                    <div class="card-body">
                        <div class="grafico-contenedor">
                            <canvas id="grafico-poblacion"></canvas>
                        </div>
                    </div>
                </div>
            @endif

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
                                    <th scope="col" class="text-end">% del país</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($ciudades as $ciudad)
                                    <tr>
                                        <td>{{ $ciudad->Name }}</td>
                                        <td class="text-muted d-none d-sm-table-cell">{{ $ciudad->District }}</td>
                                        <td class="text-end">{{ number_format($ciudad->Population, 0, ',', '.') }}</td>
                                        <td class="text-end text-muted">{{ number_format($ciudad->PorcentajePais, 2, ',', '.') }} %</td>
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

    @if ($paisSeleccionado && $ciudades->isNotEmpty())
        <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
        <script>
            // `indexAxis: 'y'` vuelve las barras horizontales: con nombres de
            // ciudad largos se leen mucho mejor que en vertical.
            new Chart(document.getElementById('grafico-poblacion'), {
                type: 'bar',
                data: {
                    labels: @json($masPobladas->pluck('Name')),
                    datasets: [{
                        label: 'Habitantes',
                        data: @json($masPobladas->pluck('Population')),
                        backgroundColor: '#198754',
                    }],
                },
                options: {
                    indexAxis: 'y',
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                    },
                    scales: {
                        x: {
                            ticks: {
                                callback: (valor) => valor.toLocaleString('es-CO'),
                            },
                        },
                    },
                },
            });
        </script>
    @endif

</body>
</html>
