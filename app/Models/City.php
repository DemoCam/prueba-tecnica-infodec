<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Ciudad de la base de datos `world`.
 *
 * Es la única de las tres tablas con llave primaria autoincremental, pero la
 * columna se llama `ID` en mayúsculas y Laravel busca `id`.
 */
class City extends Model
{
    /** @var string Laravel esperaría `cities`. */
    protected $table = 'city';

    /** @var string En el dump original la columna está en mayúsculas. */
    protected $primaryKey = 'ID';

    /** @var bool La tabla no tiene columnas created_at ni updated_at. */
    public $timestamps = false;
}
