<?php

namespace App\Http\Controllers;

use App\Models\Country;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
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
     * Capitales cuyo nombre en el dataset ya no es el que usa el servicio de clima.
     *
     * `world` es una foto de alrededor del año 2000 y guarda nombres en idioma
     * local o que desde entonces cambiaron. Cada corrección se verificó una por
     * una contra la API.
     *
     * @var array<string, string>
     */
    private const CAPITALES_RENOMBRADAS = [
        'Santafé de Bogotá' => 'Bogotá',
        'Athenai' => 'Athens',
        'Bucuresti' => 'Bucharest',
        'Toskent' => 'Tashkent',
        'Rangoon (Yangon)' => 'Yangon',
        'Santo Domingo de Guzmán' => 'Santo Domingo',
    ];

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

        // El porcentaje se calcula en la consulta y no en la vista para no repetir
        // la misma división en cada una de las filas de la tabla.
        $ciudades = $paisSeleccionado
            ? $paisSeleccionado->cities()
                ->selectRaw('city.*, Population * 100 / ? AS PorcentajePais', [$paisSeleccionado->Population])
                ->orderByDesc('Population')
                ->get()
            : collect();

        // 49 países del dataset no registran ningún idioma oficial, así que esto
        // puede venir vacío y la vista tiene que contemplarlo.
        $idiomasOficiales = $paisSeleccionado
            ? $paisSeleccionado->languages()
                ->where('IsOfficial', 'T')
                ->orderByDesc('Percentage')
                ->pluck('Language')
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
            'idiomasOficiales',
            'clima',
        ));
    }

    /**
     * Consulta el clima actual de la capital del país en OpenWeatherMap.
     *
     * Nunca lanza excepción ni devuelve null: siempre entrega un arreglo con la
     * clave `estado`, para que la vista pueda explicarle al usuario qué pasó sin
     * que el resto de la pantalla se vea afectado.
     *
     * @return array<string, mixed> `estado` es ok, sin_capital, no_encontrada o sin_servicio.
     */
    private function climaDeLaCapital(Country $pais): array
    {
        // `Capital` es nullable: siete países del dataset no tienen capital.
        if ($pais->capital === null) {
            return ['estado' => 'sin_capital'];
        }

        // Diez minutos: el clima no cambia de un minuto a otro, y la capa gratuita
        // de OpenWeather limita las llamadas por minuto. La clave lleva el código
        // del país porque cada uno consulta una capital distinta.
        return Cache::remember("clima.{$pais->Code}", now()->addMinutes(10), function () use ($pais) {
            $capital = $this->nombreConsultable($pais->capital->Name);

            try {
                // El código ISO desambigua las capitales que se repiten entre países,
                // como Santiago o San José.
                $respuesta = $this->consultarClima($capital.','.$pais->Code2);

                // Tres códigos del dataset ya no existen (AN, TP, YU) y hacen fallar
                // la búsqueda por país, aunque la ciudad sí esté en la API.
                if ($respuesta->status() === 404) {
                    $respuesta = $this->consultarClima($capital);
                }
            } catch (ConnectionException $e) {
                // Cubre la API caída y el timeout. Se registra porque es lo primero
                // que se revisa cuando un usuario reporta que no ve el clima.
                Log::warning('Clima no disponible para '.$pais->Name.': '.$e->getMessage());

                return ['estado' => 'sin_servicio'];
            }

            if ($respuesta->status() === 404) {
                return ['estado' => 'no_encontrada', 'capital' => $pais->capital->Name];
            }

            // Aquí caen la key inválida (401) y los errores del proveedor (5xx).
            if ($respuesta->failed()) {
                Log::warning('Clima no disponible para '.$pais->Name.': HTTP '.$respuesta->status());

                return ['estado' => 'sin_servicio'];
            }

            return [
                'estado' => 'ok',
                'ciudad' => $respuesta->json('name'),
                'temperatura' => round($respuesta->json('main.temp')),
                'sensacion' => round($respuesta->json('main.feels_like')),
                'humedad' => $respuesta->json('main.humidity'),
                'descripcion' => $respuesta->json('weather.0.description'),
                'icono' => $respuesta->json('weather.0.icon'),
            ];
        });
    }

    /**
     * Lanza la petición a OpenWeatherMap con el término de búsqueda indicado.
     *
     * @throws ConnectionException Si la API no responde dentro del tiempo límite.
     */
    private function consultarClima(string $busqueda): Response
    {
        // Cinco segundos: sin límite, una API lenta dejaría la pantalla colgada
        // esperando indefinidamente por un dato que es secundario.
        return Http::timeout(5)->get(config('services.openweather.url'), [
            'q' => $busqueda,
            'units' => 'metric',
            'lang' => 'es',
            'appid' => config('services.openweather.key'),
        ]);
    }

    /**
     * Traduce el nombre que guarda el dataset al que entiende el servicio de clima.
     *
     * Son tres desajustes distintos, todos por la antigüedad de la base `world`.
     */
    private function nombreConsultable(string $nombre): string
    {
        // Variantes del nombre entre corchetes: `Bruxelles [Brussel]`.
        $nombre = trim(explode('[', $nombre)[0]);

        // El dataset escribe `Saint John´s` con acento agudo (U+00B4) donde la
        // API espera el apóstrofe ASCII. Verificado con HEX() sobre la columna.
        $nombre = str_replace('´', "'", $nombre);

        return self::CAPITALES_RENOMBRADAS[$nombre] ?? $nombre;
    }
}
