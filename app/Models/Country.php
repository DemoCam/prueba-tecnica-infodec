<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * País de la base de datos `world`.
 *
 * Esta tabla no sigue ninguna convención de Laravel: se llama `country` en
 * singular y su llave primaria es `Code`, un char(3) con el código ISO. Sin las
 * declaraciones de abajo, Eloquent buscaría una tabla `countries` con una
 * columna `id` entera y ninguna de las dos existe.
 */
class Country extends Model
{
    /** @var string Laravel esperaría `countries`, en plural. */
    protected $table = 'country';

    /** @var string El código ISO de tres letras: COL, ARG, BRA. */
    protected $primaryKey = 'Code';

    /** @var bool El código viene en el dato, no lo genera un AUTO_INCREMENT. */
    public $incrementing = false;

    /** @var string Sin esto Eloquent convertiría 'COL' a entero y buscaría el país 0. */
    protected $keyType = 'string';

    /** @var bool La tabla no tiene columnas created_at ni updated_at. */
    public $timestamps = false;
}
