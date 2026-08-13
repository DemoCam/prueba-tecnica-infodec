<?php

namespace App\Http\Controllers;

use App\Models\Country;
use Illuminate\Http\Request;
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

        return view('ciudades.index', compact('paises', 'paisSeleccionado', 'ciudades'));
    }
}
