<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Idioma hablado en un país, según la tabla `countrylanguage`.
 *
 * Su llave primaria es compuesta —(CountryCode, Language)— y Eloquent no
 * soporta llaves compuestas: espera una sola columna. Como la aplicación
 * únicamente lee de esta tabla y siempre filtrando por país, nunca se busca un
 * registro por su clave y la limitación no llega a estorbar.
 */
class CountryLanguage extends Model
{
    /** @var string Laravel esperaría `country_languages`. */
    protected $table = 'countrylanguage';

    /** @var bool No existe columna autoincremental que Eloquent pueda usar. */
    public $incrementing = false;

    /** @var bool La tabla no tiene columnas created_at ni updated_at. */
    public $timestamps = false;

    /**
     * País donde se habla el idioma.
     *
     * @return BelongsTo
     */
    public function country()
    {
        return $this->belongsTo(Country::class, 'CountryCode', 'Code');
    }
}
