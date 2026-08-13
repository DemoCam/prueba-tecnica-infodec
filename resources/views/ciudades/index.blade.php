<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name') }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <main class="container py-4">

        {{-- Encabezado --}}
        <header class="mb-4">
            <h1 class="h3">Ciudades del Mundo</h1>
            <p class="text-muted mb-0">Consulta las ciudades de un país y su población.</p>
        </header>

        {{-- Selector de país. Envía por GET para que la consulta quede en la URL
             y se pueda compartir o recargar sin reenviar un formulario. --}}
        <form method="GET" action="{{ route('ciudades.index') }}" class="card card-body mb-4">
            <label for="pais" class="form-label fw-semibold">País</label>
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
            <h2 class="h4">{{ $paisSeleccionado->Name }}</h2>
        @endif

    </main>
</body>
</html>
