<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name') }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <main class="container py-5">
        <h1 class="h3">{{ config('app.name') }}</h1>
        <p class="text-muted mb-0">Proyecto inicializado. Laravel {{ app()->version() }} sobre PHP {{ PHP_VERSION }}.</p>
    </main>
</body>
</html>
