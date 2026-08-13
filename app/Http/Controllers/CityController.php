<?php

namespace App\Http\Controllers;

use App\Models\Country;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\View\View;

/**
 * Pantalla única de la aplicación: consulta de ciudades por país.
 *
 * La prueba pide una sola pantalla, así que este controlador tiene un solo
 * método. Toda la consulta vive aquí, sin capas intermedias.
 */
class CityController extends Controller
{
    /**
     * Muestra la pantalla de consulta.
     *
     * @return View
     */
    public function index(Request $request)
    {
        // `exists` deja que la propia base valide el código: si alguien manda un
        // país inventado en la URL, la petición se rechaza antes de consultar.
        $datos = $request->validate([
            'pais' => ['nullable', 'string', 'size:3', 'exists:country,Code'],
        ]);

        $paises = Country::orderBy('Name')->get(['Code', 'Name']);

        $paisSeleccionado = isset($datos['pais'])
            ? Country::find($datos['pais'])
            : null;

        $ciudades = $paisSeleccionado
            ? $paisSeleccionado->cities()->orderByDesc('Population')->get()
            : collect();

        // Los dos top 10 salen de la coleccion ya cargada en lugar de dos
        // consultas nuevas: son las mismas filas, solo ordenadas al reves.
        $masPobladas = $ciudades->take(10);
        $menosPobladas = $ciudades->reverse()->take(10)->values();

        $clima = $paisSeleccionado
            ? $this->climaDeLaCapital($paisSeleccionado)
            : null;

        return view('ciudades.index', compact(
            'paises',
            'paisSeleccionado',
            'ciudades',
            'masPobladas',
            'menosPobladas',
            'clima',
        ));
    }

    /**
     * Consulta el clima actual de la capital del país en OpenWeatherMap.
     *
     * @return array<string, mixed>|null Datos del clima, o null si no se pudo obtener.
     */
    private function climaDeLaCapital(Country $pais): ?array
    {
        // `Capital` es nullable: siete países del dataset no tienen capital.
        if ($pais->capital === null) {
            return null;
        }

        // Diez minutos: el clima no cambia de un minuto a otro, y la capa gratuita
        // de OpenWeather limita las llamadas por minuto. La clave lleva el código
        // del país porque cada uno consulta una capital distinta.
        return Cache::remember("clima.{$pais->Code}", now()->addMinutes(10), function () use ($pais) {
            // Se envía el código ISO junto al nombre para desambiguar las capitales
            // que se repiten entre países, como Santiago o San José.
            $respuesta = Http::get(config('services.openweather.url'), [
                'q' => $pais->capital->Name.','.$pais->Code2,
                'units' => 'metric',
                'lang' => 'es',
                'appid' => config('services.openweather.key'),
            ]);

            // Se cachea un arreglo vacío y no null: `remember` considera null como
            // "no hay nada guardado" y volvería a llamar a la API en cada visita.
            if ($respuesta->failed()) {
                return [];
            }

            return [
                'ciudad' => $respuesta->json('name'),
                'temperatura' => round($respuesta->json('main.temp')),
                'sensacion' => round($respuesta->json('main.feels_like')),
                'humedad' => $respuesta->json('main.humidity'),
                'descripcion' => $respuesta->json('weather.0.description'),
                'icono' => $respuesta->json('weather.0.icon'),
            ];
        });
    }
}
