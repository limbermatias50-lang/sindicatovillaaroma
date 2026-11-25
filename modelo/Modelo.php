<?php
/**
 * MODELO BASE: conexión a MySQL con PDO (una sola conexión para todo el sistema)
 * y operaciones comunes. Cada modelo (Chofer, Cuota, ...) hereda de esta clase.
 */
abstract class Modelo   // Clase padre de todos los modelos (abstract: no se usa sola)
{
    private static ?PDO $pdo = null;   // Aquí se guarda la única conexión a MySQL (se comparte en todo el sistema)

    /** Conexión única a la base de datos sindicato_villa_aroma (datos en index.php). */
    public static function conectar(): PDO   // Devuelve la conexión PDO (la crea la primera vez)
    {
        if (self::$pdo === null) {   // Si todavía no hay conexión...
            $dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4';   // Datos de conexión: servidor, puerto, base de datos y codificación utf8mb4 (tildes y ñ)
            self::$pdo = new PDO($dsn, DB_USER, DB_PASS, [   // Crea la conexión con el usuario y la contraseña de MySQL, y estas opciones:
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,   // si hay un error en SQL, lanza una excepción (no falla en silencio)
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,   // cada fila se entrega como arreglo con los nombres de las columnas
                PDO::ATTR_EMULATE_PREPARES   => false,   // consultas preparadas REALES de MySQL (protección contra inyección SQL)
            ]);
            self::$pdo->exec("SET time_zone = '" . date('P') . "'");   // Usa en MySQL la misma hora de Bolivia que PHP
        }
        return self::$pdo;   // Devuelve la conexión
    }

    protected PDO $db;   // Conexión que usa cada modelo
    protected string $tabla;   // Nombre de la tabla del modelo (lo define cada hijo)
    protected string $clave;   // Nombre de la clave primaria (lo define cada hijo)

    public function __construct()   // Se ejecuta al crear cualquier modelo
    {
        $this->db = self::conectar();   // Toma la conexión compartida
    }

    /** Ejecuta una consulta preparada y devuelve el statement. */
    protected function query(string $sql, array $params = []): PDOStatement   // Ejecuta una consulta preparada: el SQL va por un lado y los datos por otro
    {
        [$sql, $params] = $this->expandirParametros($sql, $params);   // Permite repetir un mismo :parametro en el SQL
        $stmt = $this->db->prepare($sql);   // Prepara la consulta (MySQL la revisa sin los datos)
        $stmt->execute($params);   // La ejecuta enviando los datos por separado: nunca se mezclan con el SQL
        return $stmt;   // Devuelve el resultado
    }

    /**
     * Con consultas preparadas nativas (EMULATE_PREPARES = false) MySQL no permite
     * repetir un mismo parámetro con nombre (:b ... :b). Este método renombra cada
     * aparición repetida (:b, :b__1, :b__2) para poder escribir SQL legible.
     */
    private function expandirParametros(string $sql, array $params): array   // Renombra los parámetros repetidos (:b, :b__1, :b__2)
    {
        if (!$params || array_keys($params) === range(0, count($params) - 1)) {   // lista posicional (compatible con PHP 8.0) · Si los parámetros son una lista con ? (posicionales)...
            return [$sql, $params];   // ...no hace falta cambiar nada
        }
        $vistos = [];   // Cuántas veces apareció cada parámetro
        $sql = preg_replace_callback('/(?<!:):([a-zA-Z_][a-zA-Z0-9_]*)/', function ($m) use (&$vistos, &$params) {   // Busca cada :nombre dentro del SQL
            $nombre = $m[1];   // Nombre encontrado
            if (!array_key_exists($nombre, $params)) {   // Si no es uno de nuestros parámetros...
                return $m[0];   // ...lo deja igual
            }
            $n = $vistos[$nombre] = ($vistos[$nombre] ?? -1) + 1;   // Cuenta cuántas veces apareció
            if ($n === 0) {   // La primera vez...
                return $m[0];   // ...lo deja igual
            }
            $params[$nombre . '__' . $n] = $params[$nombre];   // Las siguientes veces copia el valor con otro nombre
            return ':' . $nombre . '__' . $n;   // ...y lo renombra en el SQL
        }, $sql);
        return [$sql, $params];   // Devuelve el SQL y los parámetros ajustados
    }

    protected function fetchAll(string $sql, array $params = []): array   // Devuelve todas las filas de una consulta
    {
        return $this->query($sql, $params)->fetchAll();   // Lista de filas
    }

    protected function fetchOne(string $sql, array $params = []): ?array   // Devuelve una sola fila (o null si no hay)
    {
        return $this->query($sql, $params)->fetch() ?: null;   // Primera fila o null
    }

    protected function fetchValue(string $sql, array $params = []): mixed   // Devuelve un solo valor (ej. un COUNT)
    {
        return $this->query($sql, $params)->fetchColumn();   // Valor de la primera columna
    }

    public function buscar(int $id): ?array   // Busca un registro por su clave primaria
    {
        return $this->fetchOne("SELECT * FROM {$this->tabla} WHERE {$this->clave} = ?", [$id]);   // SELECT * FROM tabla WHERE clave = ?
    }

    /** Inserta un registro a partir de un arreglo campo => valor y devuelve el id. */
    public function insertar(array $datos): int   // Inserta un registro a partir de campo => valor y devuelve su número
    {
        $campos = array_keys($datos);   // Nombres de los campos
        $sql = sprintf(   // Arma el SQL:
            'INSERT INTO %s (%s) VALUES (%s)',   // plantilla del INSERT
            $this->tabla,   // nombre de la tabla
            implode(', ', $campos),   // campos separados por coma
            implode(', ', array_map(fn($c) => ':' . $c, $campos))   // :campo por cada valor (consulta preparada)
        );
        $this->query($sql, $datos);   // Ejecuta el INSERT con los valores
        return (int) $this->db->lastInsertId();   // Devuelve el número (id) del registro nuevo
    }

    /** Actualiza un registro por su clave primaria. */
    public function actualizar(int $id, array $datos): bool   // Actualiza un registro por su clave primaria
    {
        $set = implode(', ', array_map(fn($c) => "$c = :$c", array_keys($datos)));   // Arma "campo = :campo" por cada dato
        $datos['_id'] = $id;   // Agrega el número del registro
        return $this->query("UPDATE {$this->tabla} SET $set WHERE {$this->clave} = :_id", $datos)->rowCount() >= 0;   // UPDATE tabla SET ... WHERE clave = :_id
    }

    public function eliminar(int $id): bool   // Borra un registro por su clave primaria
    {
        return $this->query("DELETE FROM {$this->tabla} WHERE {$this->clave} = ?", [$id])->rowCount() > 0;   // DELETE FROM tabla WHERE clave = ?; verdadero si borró algo
    }

    public function transaccion(callable $fn): mixed   // Ejecuta varias operaciones como una sola (todas o ninguna)
    {
        $this->db->beginTransaction();   // Inicia la transacción
        try {   // Intenta...
            $r = $fn();   // ...ejecutar las operaciones
            $this->db->commit();   // Si todo salió bien, confirma los cambios
            return $r;   // Devuelve el resultado
        } catch (Throwable $e) {   // Si algo falló...
            $this->db->rollBack();   // ...deshace todos los cambios
            throw $e;   // ...y vuelve a lanzar el error
        }
    }
}
