<?php

namespace App\Http\Controllers;

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
    public function index()
    {
        return view('ciudades.index');
    }
}
