# Base de datos `world`

Este directorio contiene `world.sql`, el script de creación e importación de la base
de datos de ejemplo **world** de MySQL, sobre la que funciona toda la aplicación.

## Contenido

El script crea la base `world` con tres tablas:

| Tabla | Registros | Descripción |
|---|---|---|
| `country` | 239 | Países. Llave primaria `Code`, un `char(3)` con el código ISO |
| `city` | 4.079 | Ciudades. `CountryCode` es llave foránea hacia `country.Code` |
| `countrylanguage` | 984 | Idiomas por país. Llave primaria compuesta `(CountryCode, Language)` |

## Importación

### Por línea de comandos

```bash
mysql -u root -p < database/world.sql
```

El script ya incluye `CREATE DATABASE world`, así que no hace falta crearla antes.

En Windows, si `mysql` no está en el PATH, invócalo por ruta completa. Por ejemplo,
con XAMPP:

```bash
C:\xampp\mysql\bin\mysql.exe -u root < database/world.sql
```

### Por interfaz gráfica

En **phpMyAdmin**, **DBeaver**, **HeidiSQL** o **MySQL Workbench**: crea una conexión
al servidor, abre `world.sql` y ejecútalo.

## Verificación

Después de importar, confirma que los datos quedaron completos:

```sql
SELECT
    (SELECT COUNT(*) FROM country)         AS paises,
    (SELECT COUNT(*) FROM city)            AS ciudades,
    (SELECT COUNT(*) FROM countrylanguage) AS idiomas;
```

El resultado esperado es `239`, `4079` y `984`. Si alguno da menos, la importación
quedó incompleta y conviene repetirla.

## Origen de los datos

El archivo procede de la documentación oficial de MySQL:

<https://dev.mysql.com/doc/index-other.html> → *Example Databases* → *world database*

Enlace directo al ZIP: <https://downloads.mysql.com/docs/world-db.zip>

Se incluye aquí para que el proyecto sea reproducible sin depender de una descarga
externa.
