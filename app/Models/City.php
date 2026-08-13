<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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

    /**
     * País al que pertenece la ciudad.
     *
     * Es el lado inverso de Country::cities(): la llave foránea `CountryCode`
     * está en esta tabla, respaldada por la restricción `city_ibfk_1`.
     *
     * @return BelongsTo
     */
    public function country()
    {
        return $this->belongsTo(Country::class, 'CountryCode', 'Code');
    }
}
