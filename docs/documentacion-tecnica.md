# Documentación técnica

Cómo funciona la aplicación por dentro. Escrito para alguien que abre el repositorio sin contexto
y necesita entenderlo en diez minutos.

- [1. Arquitectura](#1-arquitectura)
- [2. Modelo de datos](#2-modelo-de-datos)
- [3. Decisiones técnicas](#3-decisiones-técnicas)
- [4. Integración con la API de clima](#4-integración-con-la-api-de-clima)
- [5. Limitaciones conocidas](#5-limitaciones-conocidas)

---

## 1. Arquitectura

El proyecto sigue el patrón **MVC** tal como lo implementa Laravel. Hay una sola pantalla, así que
la estructura es deliberadamente plana: sin capas de servicio, sin repositorios, sin DTOs.

### Reparto de responsabilidades

| Pieza | Archivo | Qué hace |
|---|---|---|
| **Ruta** | `routes/web.php` | Asocia `GET /` con el controlador. Una sola línea |
| **Controlador** | `app/Http/Controllers/CityController.php` | Valida la entrada, consulta los modelos, llama a la API de clima y entrega los datos a la vista |
| **Modelos** | `app/Models/{Country,City,CountryLanguage}.php` | Traducen las tres tablas a objetos y declaran las relaciones |
| **Vista** | `resources/views/ciudades/index.blade.php` | Renderiza el HTML. No consulta la base ni toma decisiones de negocio |

### Flujo de una petición

Desde que la usuaria elige un país hasta que ve la tabla:

```
Navegador
   │  GET /?pais=COL
   ▼
routes/web.php
   │  Route::get('/', [CityController::class, 'index'])
   ▼
CityController::index()
   │
   ├─ 1. $request->validate(['pais' => [... 'exists:country,Code']])
   │     Si el código no existe → redirección 302 con el error. Fin.
   │
   ├─ 2. Country::orderBy('Name')->get()            → los 239 países del selector
   ├─ 3. Country::find('COL')                       → el país seleccionado
   ├─ 4. $pais->cities()->selectRaw(...porcentaje)  → sus 38 ciudades
   ├─ 5. $pais->languages()->where('IsOfficial')    → sus idiomas oficiales
   │
   ├─ 6. $ciudades->take(10)                        → top 10 más pobladas
   │     $ciudades->reverse()->take(10)             → top 10 menos pobladas
   │     (sin consultas nuevas: se derivan de la colección ya cargada)
   │
   └─ 7. climaDeLaCapital($pais)
         ├─ ¿Capital es NULL?    → ['estado' => 'sin_capital']
         ├─ ¿está en caché?      → se devuelve sin llamar a la API
         └─ si no → Http::timeout(5) contra OpenWeatherMap
   │
   ▼
resources/views/ciudades/index.blade.php
   │  Selector · Idiomas · Clima · Gráfico · Top 10 · Tabla completa
   ▼
HTML al navegador
```

### Costo de una carga completa

Medido con `DB::listen` sobre la pantalla de Colombia:

```
1. [23.36 ms] select count(*) from `country` where `Code` = 'COL'      ← la validación
2. [ 0.82 ms] select `Code`, `Name` from `country` order by `Name`     ← el selector
3. [ 0.33 ms] select * from `country` where `Code` = 'COL' limit 1     ← el país
4. [ 0.55 ms] select city.*, Population * 100 / 42321000 AS Porcentaje
                 from `city` where `CountryCode` = 'COL'
                 order by `Population` desc                             ← las 38 ciudades
5. [ 0.35 ms] select `Language` from `countrylanguage`
                 where `CountryCode` = 'COL' and `IsOfficial` = 'T'     ← los idiomas
6. [ 0.53 ms] select * from `city` where `ID` = 2257 limit 1            ← la capital
```

**Seis consultas.** Cinco de ellas por debajo del milisegundo; la primera es la más lenta porque es
la que abre la conexión.

Tres cosas **no** generan consulta adicional: los dos top 10 y el gráfico se derivan de la colección
del paso 4, que la tabla completa necesitaba de todos modos; el porcentaje de población se calcula
dentro de esa misma consulta en vez de en la vista; y el clima se sirve desde caché durante diez
minutos, así que la petición HTTP externa solo ocurre en la primera visita.

---

## 2. Modelo de datos

Tres tablas y dos relaciones declaradas. El diagrama y su explicación están en
el diagrama entidad-relación del documento de entrega.

```
country (239)
   │ Code (PK, char(3))
   │
   ├──< city (4.079)             city.CountryCode → country.Code   [city_ibfk_1]
   │
   ├──< countrylanguage (984)    countrylanguage.CountryCode → country.Code
   │
   └──> city                     country.Capital → city.ID   (relación lógica, sin FK)
```

### Por qué `Country` necesita tres declaraciones extra

```php
protected $table      = 'country';   // Laravel esperaría `countries`
protected $primaryKey = 'Code';      // Laravel esperaría `id`
public    $incrementing = false;     // no hay AUTO_INCREMENT
protected $keyType    = 'string';    // la llave es char(3), no int
public    $timestamps = false;       // no hay created_at ni updated_at
```

Las dos últimas son las que generan preguntas, y merecen precisión porque el efecto no es el que
se espera:

**`Country::find('COL')` funciona igual sin declararlas.** El SQL generado es idéntico y devuelve
Colombia. El problema aparece al leer el valor de vuelta.

Laravel aplica esta regla interna: *si el modelo es autoincremental, castea su llave primaria al
tipo declarado en `$keyType`*. Como `$incrementing` viene en `true` por defecto y `$keyType` en
`'int'`, se registra el cast `Code → int`. Y `(int) 'COL'` en PHP es `0`.

Comparación medida entre el modelo declarado y uno sin declarar:

```
                    declarado      sin declarar
getIncrementing()   false          true
getKeyType()        string         int
getCasts()          []             {"Code":"int"}
->Name              Colombia       Colombia
->Code              'COL'          0
```

En esta aplicación eso significaría que el selector renderizara `value="0"` en las 239 opciones.
El formulario enviaría `0`, no se encontraría nada, y **no habría excepción ni entrada en el log**.
Un fallo silencioso.

Nótese que ambas propiedades están encadenadas: el cast solo se registra *porque* `$incrementing`
es `true`. Con `$incrementing = false`, `getCasts()` queda vacío y `$keyType` deja de aplicarse.

### La llave compuesta de `countrylanguage`

Su llave primaria es `(CountryCode, Language)` y **Eloquent no soporta llaves compuestas**: espera
una sola columna. Al no declarar `$primaryKey`, asume `id`:

```
CountryLanguage::find(1)
→ SQLSTATE[42S22]: Unknown column 'countrylanguage.id' in 'where clause'
```

Quedan inutilizables `find()`, `save()` sobre un registro existente, `delete()` sobre una instancia
y el route model binding — todos construyen el mismo `WHERE <llave> = ?` de una columna.

**No estorba** porque la aplicación solo lee de esa tabla, y siempre filtrando por país:

```sql
select `Language` from `countrylanguage` where `CountryCode` = 'COL' and `IsOfficial` = 'T'
```

---

## 3. Decisiones técnicas

### Laravel 12 en lugar de Laravel 11

La rama 11.x quedó **sin soporte de seguridad**: tres advisories vigentes no tienen versión
corregida en ninguna de sus versiones.

| Advisory | Afecta | Corregido en |
|---|---|---|
| CVE-2026-48019 — CRLF injection | todo 11.x | 12.60.0 / 13.10.0 |
| Signed URL Path Confusion | todo 11.x | 12.61.1 / 13.12.0 |
| CRLF en la regla de email | todo 11.x | 12.60.0 |

No es que 11.55.1 esté desactualizado: **no existe un 11.x sin esos fallos**, y Composer se niega
a instalarlo. El enunciado no pide una versión concreta —dice *"Framework Laravel o PHP con modelo
MVC"*—, y Laravel 12 mantiene el mismo esqueleto slim que introdujo Laravel 11.

### Bootstrap y Chart.js por CDN, sin pipeline de build

Se retiró el andamiaje de npm, Vite y Tailwind que trae Laravel de fábrica. Con dos dependencias
de front que se resuelven con dos etiquetas `<link>` y `<script>`, un pipeline de build solo
añadiría un `node_modules` de cientos de megabytes y un paso de compilación entre clonar el
repositorio y verlo funcionando.

Quien clone este proyecto necesita `composer install` y nada más.

### Drivers de archivo para caché y sesión

Laravel 12 trae `database` por defecto en ambos, lo que guardaría sesiones y caché **en tablas SQL
dentro de `DB_DATABASE`** — es decir, dentro de `world`.

Eso habría fallado de inmediato (`Table 'world.sessions' doesn't exist`), y el arreglo instintivo
—`php artisan migrate`— habría creado nueve tablas de Laravel (`users`, `sessions`, `cache`,
`jobs`, `migrations`…) mezcladas con `country`, `city` y `countrylanguage`.

`world` es un dataset de referencia, de **solo lectura** para esta aplicación. Con drivers de
archivo, Laravel escribe en `storage/framework/` y la base queda intacta.

> En producción con varios servidores esto cambiaría a Redis: un caché en disco no se comparte
> entre máquinas.

### Caché de clima de 10 minutos

El clima no cambia de un minuto a otro, y la capa gratuita de OpenWeather limita las llamadas por
minuto. Diez minutos es el punto donde el dato sigue siendo veraz y la cuota alcanza.

Medido en local:

| Petición | Perú (200 OK) | Colombia (404) |
|---|---|---|
| 1ª | 1692 ms | 1500 ms |
| 2ª | 365 ms | 376 ms |
| 3ª | 379 ms | 382 ms |

La segunda columna justifica una decisión que no es obvia: **cuando la API falla se cachea `[]`, no
`null`**. `Cache::remember` interpreta `null` como "no hay nada guardado", así que con `null` cada
visita a Colombia volvería a llamar a la API para volver a fallar.

### El formulario va por GET

La pantalla solo **lee** datos, no modifica nada. GET es lo semánticamente correcto, y trae tres
ventajas: la URL `?pais=COL` es compartible, recargar no dispara el aviso de "reenviar formulario",
y el botón atrás funciona. Al no cambiar estado, tampoco necesita token CSRF.

### Sin mapa

La base `world` **no tiene columnas de latitud ni longitud** en ninguna de sus tres tablas. Un mapa
exigiría geocodificar 4.079 ciudades contra un servicio externo, lo que multiplicaría la
complejidad y los puntos de fallo sin aportar a lo que el enunciado evalúa.

---

## 4. Integración con la API de clima

Es el punto que conecta la Pregunta 1 con la Pregunta 4: **la misma API que se prueba en Postman es
la que alimenta la aplicación**, en vez de dos tareas sueltas.

### Endpoint y parámetros

```
GET https://api.openweathermap.org/data/2.5/weather
```

| Parámetro | Valor | Por qué |
|---|---|---|
| `q` | `Lima,PE` | Capital y código ISO. El código desambigua capitales repetidas, como Santiago o San José |
| `units` | `metric` | **Sin este parámetro la API devuelve grados Kelvin.** Es un error silencioso: responde 200 y el dato marca 293 en vez de 20 |
| `lang` | `es` | Descripción del clima en español |
| `appid` | *(variable)* | La key. Se lee desde `config/services.php`, nunca con `env()` en el controlador |

La capital sale del `JOIN` lógico `country.Capital → city.ID`.

### Por qué la key va en `config/` y no en `env()`

Al ejecutar `php artisan config:cache`, Laravel serializa la configuración y **las llamadas a
`env()` fuera del directorio `config/` devuelven `null`**. Es un fallo que solo aparece en
producción, donde ese comando es habitual.

### Errores contemplados

El método nunca lanza excepción ni devuelve `null`: siempre entrega un arreglo con la clave
`estado`. **En los cuatro casos la pantalla sigue funcionando** — el clima se cae solo, y la tabla,
el gráfico y los top 10 se muestran igual.

| Situación | `estado` | Qué ve la usuaria | Se registra |
|---|---|---|---|
| Todo bien | `ok` | Tarjeta con temperatura, sensación, humedad e icono | — |
| `Capital` es NULL (7 países) | `sin_capital` | *La base de datos no registra una capital para este país* | No: es un dato faltante, no un fallo |
| La API no reconoce el nombre | `no_encontrada` | *El servicio no reconoce «X», que es como esta base nombra a la capital* | No: el problema es el dato |
| Key inválida (401) o error del proveedor (5xx) | `sin_servicio` | *No se pudo consultar el servicio meteorológico* | `Log::warning` con el código HTTP |
| Timeout o API caída | `sin_servicio` | El mismo mensaje | `Log::warning` con el error de cURL |

**Por qué cuatro mensajes y no uno.** Un único *"no se pudo obtener el clima"* sería más corto, pero
las causas exigen acciones distintas: si la base no registra capital no hay nada que arreglar; si
la API no reconoce el nombre el problema es el dato; si es un 401 o un timeout el problema sí es el
servicio y hay que escalarlo.

**Por qué 401 y timeout se manejan en ramas separadas** aunque muestren el mismo mensaje: no podrían
unificarse. En un 401 **hubo respuesta HTTP** y existe un objeto con su `status()`; en un timeout
**no hay respuesta ninguna**, la conexión murió y Guzzle lanza `ConnectionException`. Una va en
`try/catch` y la otra en un `if`.

Además, el log los distingue, que es lo que decide la acción de soporte:

```
Clima no disponible para Peru: HTTP 401
Clima no disponible para Peru: cURL error 28: Operation timed out after 5014 milliseconds
```

Un 401 es propio —la key venció o fue revocada—; un timeout es del proveedor.

### El timeout de 5 segundos

Laravel trae 30 segundos por defecto (`'timeout' => 30` en `PendingRequest`). Sin el límite
explícito, un usuario que consulte Perú con la API colgada vería **la pantalla en blanco medio
minuto**, esperando por un dato secundario mientras la tabla y el gráfico ya estaban listos en el
servidor. Cinco segundos dicen: el clima es un extra y no vale la pena que retenga la pantalla.

Verificado contra un endpoint que tarda 10 segundos:

```
cURL error 28: Operation timed out after 5014 milliseconds
```

### Nombres de capital que el dataset trae desactualizados

`world` es una foto de alrededor del año 2000. Un barrido de las 232 capitales con nombre reveló
**20 que OpenWeather no reconocía**, agrupadas en causas identificables:

| Causa | Ejemplo | ¿Corregida? |
|---|---|---|
| Variante entre corchetes | `Bruxelles [Brussel]` | Sí, se recorta en el `[` |
| Acento agudo por apóstrofe | `Saint John´s` (bytes `...C2B4 73`) | Sí, `U+00B4` → `'` |
| Código ISO obsoleto | `Dili,TP` (hoy `TL`) | Sí, se reintenta sin el código |
| Nombre en idioma local | `Athenai`, `Bucuresti`, `Toskent` | Sí, tabla de renombres |
| Nombre histórico | `Santafé de Bogotá` (oficial solo 1991-2000) | Sí, tabla de renombres |
| Localidad fuera del catálogo | `Fakaofo`, `Garapan` | No |

Cada corrección se verificó una por una contra la API. Resultado:

```
antes:    212 de 232   (91,4 %)
después:  228 de 232   (98,3 %)
```

Quedan cuatro sin resolver: `N´Djaména` (Chad) y tres localidades demasiado pequeñas para el
catálogo de OpenWeather.

---

## 5. Limitaciones conocidas

Reconocerlas con criterio se lee mejor que fingir que no existen.

### Del dato

1. **Los datos son de alrededor del año 2000.** La base incluye países que ya no existen —
   Yugoslavia, Serbia y Montenegro, Antillas Neerlandesas—, y las poblaciones no corresponden a
   la realidad actual. El enunciado pide *"la mayor ciudad con población en el día de hoy"*, y la
   aplicación entrega lo más cercano posible: lo que registra la base compartida.

2. **Cuatro capitales sin clima**, por las razones de la sección anterior.

3. **Tres países sin bandera**: `an`, `yu` y `tp` son códigos ISO disueltos, y flagcdn solo publica
   banderas vigentes. La imagen se retira sola con `onerror` en lugar de mostrar un icono roto.

4. **`countrylanguage.Percentage` es un porcentaje del país, no de la ciudad.** Por eso el top 10
   de ciudades hispanohablantes filtra por `IsOfficial`. El razonamiento completo está en
   el documento de entrega.

### Del alcance

5. **Sin mapa**, porque la base no tiene coordenadas.

6. **Sin paginación en la tabla completa.** El país con más ciudades es China, con 363 filas — un
   volumen que el navegador maneja sin problema. La tabla lleva `max-height` y encabezado fijo para
   que el scroll sea cómodo.

7. **Sin autenticación.** El enunciado describe una pantalla de consulta pública sin usuarios.

8. **Pruebas mínimas.** Hay verificación manual documentada de los siete escenarios del clima, pero
   no una suite automatizada. Para el alcance de una prueba técnica de una pantalla, escribir
   tests de integración contra una API externa habría añadido más andamiaje que valor.

### Del entorno

9. **PHP en Windows necesita configurar el bundle de certificados CA.** Sin él, toda llamada HTTPS
   falla con `cURL error 60`. No es un problema del código, pero bloquea a quien clone el
   repositorio. Está documentado en el
   [troubleshooting del README](../README.md#troubleshooting).
