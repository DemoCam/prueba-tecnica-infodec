# Ciudades del Mundo

Aplicación web que permite consultar, para cualquier país, sus ciudades y la población
de cada una, destacando las 10 más pobladas y las 10 menos pobladas.

Desarrollada como prueba técnica para el cargo de **Analista de Soporte Nivel 1** en
INFODEC S.A.S.

> Este README se completa al cierre del proyecto. La versión final incluye instalación
> paso a paso, origen de los datos, troubleshooting, capturas e índice de `docs/`.

## Requisitos previos

| Requisito | Versión usada |
|---|---|
| PHP | 8.4 (mínimo 8.2) |
| Composer | 2.x |
| MySQL o MariaDB | MariaDB 10.4 |
| Base de datos `world` | Ver `database/world.sql` |

## Instalación

```bash
git clone https://github.com/DemoCam/prueba-tecnica-infodec.git
cd prueba-tecnica-infodec

composer install

cp .env.example .env
php artisan key:generate
```

Luego edita el `.env` con los datos de tu servidor MySQL y tu API key de
[OpenWeatherMap](https://openweathermap.org/api):

```env
DB_DATABASE=world
DB_USERNAME=root
DB_PASSWORD=

OPENWEATHER_API_KEY=
```

Finalmente:

```bash
php artisan serve
```

La aplicación queda disponible en <http://localhost:8000>.

## Stack

- **Laravel 12** sobre PHP 8.4, con el patrón MVC
- **MySQL** con la base de ejemplo `world`
- **Bootstrap 5** por CDN, sin pipeline de build
- **Chart.js** por CDN para el gráfico del top 10
- **OpenWeatherMap** para el clima de la capital
