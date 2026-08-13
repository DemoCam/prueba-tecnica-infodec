<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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

    /**
     * Ciudades del país.
     *
     * Hay que nombrar las dos columnas porque Eloquent asumiría `country_code`
     * y `id`, que no son los nombres reales.
     *
     * @return HasMany
     */
    public function cities()
    {
        return $this->hasMany(City::class, 'CountryCode', 'Code');
    }

    /**
     * Idiomas registrados para el país, oficiales y no oficiales.
     *
     * @return HasMany
     */
    public function languages()
    {
        return $this->hasMany(CountryLanguage::class, 'CountryCode', 'Code');
    }

    /**
     * Ciudad capital del país.
     *
     * Va en sentido contrario a `cities()`: la columna `Capital` vive en
     * `country` y apunta a `city.ID`, por eso es belongsTo. Puede devolver null
     * porque hay siete países sin capital registrada.
     *
     * @return BelongsTo
     */
    public function capital()
    {
        return $this->belongsTo(City::class, 'Capital', 'ID');
    }
}
