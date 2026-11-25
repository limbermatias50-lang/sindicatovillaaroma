<?php
/**
 * MODELO de choferes, grupos de transporte y licencias (tablas chofer, grupo_transporte y licencia).
 */

/**
 * Chofer afiliado (RF01, RF02).
 */
class Chofer extends Modelo   // Modelo de choferes (tabla chofer)
{
    protected string $tabla = 'chofer';   // Tabla que maneja
    protected string $clave = 'id_chofer';   // Clave primaria

    /** Consulta base: chofer + grupo + licencia + deuda pendiente. */
    private const SELECT_DETALLE = "
        SELECT c.*, g.nombre AS grupo, g.ruta,
               l.numero AS licencia_numero, l.categoria AS licencia_categoria, l.fecha_vencimiento,
               (SELECT COALESCE(SUM(q.monto), 0) FROM pago_cuota p JOIN cuota q ON q.id_cuota = p.id_cuota
                 WHERE p.id_chofer = c.id_chofer AND p.estado = 'pendiente' AND q.estado = 'vigente') AS deuda
        FROM chofer c
        JOIN grupo_transporte g ON g.id_grupo = c.id_grupo
        LEFT JOIN licencia l ON l.id_chofer = c.id_chofer";

    public function listar(string $buscar = '', int $idGrupo = 0, string $estado = ''): array   // Lista de choferes con filtros opcionales
    {
        $where  = [];   // Condiciones del WHERE
        $params = [];   // Datos de la consulta preparada
        if ($buscar !== '') {   // Si se escribió algo en el buscador...
            $where[] = "(c.ci LIKE :b OR CONCAT(c.nombres, ' ', c.apellidos) LIKE :b OR c.codigo_qr = :qr)";   // ...busca por C.I., por nombre completo o por código QR
            $params['b']  = "%$buscar%";   // Texto con % para buscar "que contenga"
            $params['qr'] = $buscar;   // Código QR exacto
        }
        if ($idGrupo > 0) {   // Si se eligió un grupo...
            $where[] = 'c.id_grupo = :g';   // ...filtra por grupo
            $params['g'] = $idGrupo;   // Valor del grupo
        }
        if ($estado !== '') {   // Si se eligió un estado...
            $where[] = 'c.estado = :e';   // ...filtra por estado
            $params['e'] = $estado;   // Valor del estado
        }
        $sql = self::SELECT_DETALLE . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . ' ORDER BY c.apellidos, c.nombres';   // Une la consulta base con los filtros y ordena por apellido
        return $this->fetchAll($sql, $params);   // Ejecuta la consulta preparada
    }

    public function detalle(int $id): ?array   // Datos completos de un chofer (con grupo, licencia y deuda)
    {
        return $this->fetchOne(self::SELECT_DETALLE . ' WHERE c.id_chofer = ?', [$id]);   // Busca por su número
    }

    /** Búsqueda por el código leído desde la credencial QR (CU03). */
    public function buscarPorQR(string $codigo): ?array   // Busca un chofer por su código QR (ventanilla y verificación)
    {
        return $this->fetchOne(self::SELECT_DETALLE . ' WHERE c.codigo_qr = ?', [$codigo]);   // Consulta preparada con el código
    }

    /** Búsqueda en ventanilla cuando el chofer olvidó su credencial: por C.I. (con o sin complemento). */
    public function buscarPorCi(string $ci): ?array   // Busca un chofer por su C.I. (cuando olvidó su credencial)
    {
        return $this->fetchOne(self::SELECT_DETALLE . ' WHERE c.ci = ? LIMIT 1', [$ci]);   // Consulta preparada con el C.I.
    }

    public function existeCi(string $ci, int $excepto = 0): bool   // ¿Ya existe otro chofer con ese C.I.?
    {
        return (bool) $this->fetchValue('SELECT COUNT(*) FROM chofer WHERE ci = ? AND id_chofer <> ?', [$ci, $excepto]);   // Cuenta choferes con ese C.I. sin contar al que se edita
    }

    /** Código único e impredecible impreso en la credencial: VA-XXXXXXXXXX */
    public function generarCodigoQR(): string   // Genera el código único e impredecible de la credencial: VA-XXXXXXXXXX
    {
        $alfabeto = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';   // sin 0/O ni 1/I para evitar confusiones
        do {   // Repite...
            $codigo = 'VA-';   // ...empieza con VA-
            for ($i = 0; $i < 10; $i++) {   // ...y agrega 10 caracteres...
                $codigo .= $alfabeto[random_int(0, strlen($alfabeto) - 1)];   // ...elegidos al azar de forma segura (random_int)
            }
        } while ($this->fetchValue('SELECT COUNT(*) FROM chofer WHERE codigo_qr = ?', [$codigo]));   // ...mientras el código ya exista (así nunca se repite)
        return $codigo;   // Devuelve el código nuevo
    }

    /**
     * Registra al chofer y le asigna las cuotas vigentes de su grupo que aún no vencieron.
     */
    public function registrar(array $datos): int   // Registra un chofer nuevo dentro de una transacción
    {
        return $this->transaccion(function () use ($datos) {   // Todo lo de adentro se guarda junto o no se guarda nada
            $datos['codigo_qr'] = $this->generarCodigoQR();   // Le genera su código QR
            $id = $this->insertar($datos);   // Guarda al chofer
            (new Cuota())->asignarVigentesAChofer($id, (int) $datos['id_grupo']);   // Le asigna las cuotas vigentes de su grupo
            return $id;   // Devuelve el número del chofer nuevo
        });
    }

    /** Elimina al chofer y sus documentos digitalizados. */
    public function eliminarCompleto(int $id): void   // Elimina al chofer y sus archivos
    {
        $archivos = $this->fetchAll('SELECT ruta_archivo FROM expediente WHERE id_chofer = ?', [$id]);   // Lista de sus documentos digitalizados
        $foto     = $this->fetchValue('SELECT foto FROM chofer WHERE id_chofer = ?', [$id]);   // Su foto
        if ($foto) {   // Si tiene foto...
            $archivos[] = ['ruta_archivo' => $foto];   // ...la agrega a la lista para borrarla
        }
        $this->transaccion(function () use ($id) {   // Dentro de una transacción...
            $this->eliminar($id);   // licencia, expediente y pagos pendientes se eliminan en cascada · ...borra al chofer
        });
        foreach ($archivos as $a) {   // Después borra cada archivo del servidor
            Upload::eliminar($a['ruta_archivo']);   // Borra el archivo
        }
    }

    public function totalActivos(): int   // Cuántos choferes activos hay (panel)
    {
        return (int) $this->fetchValue("SELECT COUNT(*) FROM chofer WHERE estado = 'activo'");   // Cuenta los choferes con estado activo
    }

    public function tienePagos(int $id): bool   // ¿El chofer tiene pagos cobrados?
    {
        return (bool) $this->fetchValue("SELECT COUNT(*) FROM pago_cuota WHERE id_chofer = ? AND estado = 'pagado'", [$id]);   // Cuenta sus pagos con estado pagado
    }
}

class Grupo extends Modelo   // Modelo de grupos de transporte (tabla grupo_transporte)
{
    protected string $tabla = 'grupo_transporte';   // Tabla que maneja
    protected string $clave = 'id_grupo';   // Clave primaria

    public function activos(): array   // Grupos activos (para las listas de selección)
    {
        return $this->fetchAll('SELECT id_grupo, nombre, ruta FROM grupo_transporte WHERE activo = 1 ORDER BY nombre');   // Consulta de los grupos activos ordenados por nombre
    }

    public function listarConResumen(): array   // Grupos con cuántos choferes y cuánto deben
    {
        return $this->fetchAll(   // Consulta con subconsultas de conteo
            "SELECT g.*,
                    (SELECT COUNT(*) FROM chofer c WHERE c.id_grupo = g.id_grupo AND c.estado = 'activo') AS choferes
             FROM grupo_transporte g ORDER BY g.nombre"
        );
    }

    public function existeNombre(string $nombre, int $excepto = 0): bool   // ¿Ya existe otro grupo con ese nombre?
    {
        return (bool) $this->fetchValue('SELECT COUNT(*) FROM grupo_transporte WHERE nombre = ? AND id_grupo <> ?', [$nombre, $excepto]);   // Cuenta grupos con ese nombre sin contar al que se edita
    }
}

/**
 * Licencia de conducir y alertas de vencimiento (RF06).
 */
class Licencia extends Modelo   // Modelo de licencias de conducir (tabla licencia)
{
    protected string $tabla = 'licencia';   // Tabla que maneja
    protected string $clave = 'id_licencia';   // Clave primaria

    public const CATEGORIAS = ['A', 'B', 'C', 'P', 'M', 'T'];   // Categorías de licencia en Bolivia

    public function porChofer(int $idChofer): ?array   // Licencia de un chofer
    {
        return $this->fetchOne('SELECT * FROM licencia WHERE id_chofer = ?', [$idChofer]);   // Consulta preparada por chofer
    }

    /** Registra o actualiza (renovación) la licencia del chofer. */
    public function guardar(int $idChofer, string $numero, string $categoria, ?string $emision, string $vencimiento): void   // Registra la licencia o la actualiza si ya tenía una (renovación)
    {
        $this->query(   // Ejecuta:
            'INSERT INTO licencia (id_chofer, numero, categoria, fecha_emision, fecha_vencimiento)
             VALUES (:c, :n, :cat, :em, :ve)
             ON DUPLICATE KEY UPDATE numero = VALUES(numero), categoria = VALUES(categoria),
                 fecha_emision = VALUES(fecha_emision), fecha_vencimiento = VALUES(fecha_vencimiento)',
            ['c' => $idChofer, 'n' => $numero, 'cat' => $categoria, 'em' => $emision ?: null, 've' => $vencimiento]   // Datos de la consulta preparada (cada :nombre recibe su valor)
        );
    }

    /** Licencias vencidas o que vencen dentro de $dias días, ordenadas por fecha (CP06). */
    public function alertas(int $dias = DIAS_ALERTA_LICENCIA, int $idGrupo = 0): array   // Licencias vencidas o que vencen dentro de N días
    {
        $params = [$dias];   // Primer dato: los días de anticipación
        $filtro = '';   // Filtro opcional por grupo
        if ($idGrupo > 0) {   // Si se eligió un grupo...
            $filtro   = ' AND c.id_grupo = ?';   // ...agrega la condición
            $params[] = $idGrupo;   // ...y su valor
        }
        return $this->fetchAll(   // Consulta de licencias de choferes activos ordenadas por fecha de vencimiento
            "SELECT l.*, c.id_chofer, c.ci, CONCAT(c.nombres, ' ', c.apellidos) AS chofer, c.telefono, g.nombre AS grupo,
                    DATEDIFF(l.fecha_vencimiento, CURDATE()) AS dias_restantes
             FROM licencia l
             JOIN chofer c ON c.id_chofer = l.id_chofer AND c.estado = 'activo'
             JOIN grupo_transporte g ON g.id_grupo = c.id_grupo
             WHERE l.fecha_vencimiento <= DATE_ADD(CURDATE(), INTERVAL ? DAY) $filtro
             ORDER BY l.fecha_vencimiento",
            $params   // Datos de la consulta preparada
        );
    }

    public function choferesSinLicencia(): array   // Choferes activos que todavía no registraron su licencia
    {
        return $this->fetchAll(   // Consulta con LEFT JOIN: los que no tienen licencia quedan en NULL
            "SELECT c.id_chofer, c.ci, CONCAT(c.nombres, ' ', c.apellidos) AS chofer, g.nombre AS grupo
             FROM chofer c JOIN grupo_transporte g ON g.id_grupo = c.id_grupo
             LEFT JOIN licencia l ON l.id_chofer = c.id_chofer
             WHERE l.id_licencia IS NULL AND c.estado = 'activo' ORDER BY c.apellidos"
        );
    }
}
