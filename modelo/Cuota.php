<?php
/**
 * MODELO de cuotas por grupo y pagos de los afiliados (tablas cuota y pago_cuota).
 */

/**
 * Cuotas ordinarias y extraordinarias por grupo de transporte (RF03).
 *
 * Al crear una cuota se genera automáticamente una obligación "pendiente"
 * en pago_cuota para cada chofer activo del grupo; así los saldos y la
 * morosidad se consultan de forma inmediata (RF04).
 */
class Cuota extends Modelo   // Modelo de cuotas por grupo (tabla cuota)
{
    protected string $tabla = 'cuota';   // Tabla que maneja
    protected string $clave = 'id_cuota';   // Clave primaria

    public const TIPOS = ['ordinaria', 'extraordinaria'];   // Tipos de cuota: ordinaria (mensual) o extraordinaria (aprobada en asamblea)

    public function listar(int $idGrupo = 0, string $tipo = '', string $estado = 'vigente'): array   // Lista de cuotas con su avance de cobro
    {
        $where  = [];   // Condiciones del WHERE
        $params = [];   // Datos de la consulta preparada
        if ($idGrupo > 0)                        { $where[] = 'q.id_grupo = :g'; $params['g'] = $idGrupo; }   // Filtro por grupo
        if (in_array($tipo, self::TIPOS, true))  { $where[] = 'q.tipo = :t';     $params['t'] = $tipo; }   // Filtro por tipo (solo si es uno permitido)
        if (in_array($estado, ['vigente', 'anulada'], true)) { $where[] = 'q.estado = :e'; $params['e'] = $estado; }   // Filtro por estado (vigente o anulada)

        return $this->fetchAll(   // Consulta: cada cuota con cuántos deben pagarla, cuántos pagaron, lo cobrado y lo que falta
            "SELECT q.*, g.nombre AS grupo,
                    COUNT(p.id_pago) AS obligados,
                    SUM(p.estado = 'pagado') AS pagados,
                    COALESCE(SUM(p.monto_pagado), 0) AS recaudado,
                    COALESCE(SUM(p.estado = 'pendiente'), 0) * q.monto AS por_cobrar
             FROM cuota q
             JOIN grupo_transporte g ON g.id_grupo = q.id_grupo
             LEFT JOIN pago_cuota p ON p.id_cuota = q.id_cuota"   // (LEFT JOIN: también aparecen cuotas sin choferes)
            . ($where ? ' WHERE ' . implode(' AND ', $where) : '') .   // Agrega los filtros si hay
            ' GROUP BY q.id_cuota ORDER BY q.fecha_limite DESC, g.nombre',   // Agrupa por cuota; las más recientes primero
            $params   // Datos de la consulta preparada
        );
    }

    /**
     * Crea la cuota para uno o varios grupos (p. ej. una extraordinaria aprobada
     * para todo el sindicato) y genera las obligaciones de cada chofer.
     * @return int cantidad de cuotas creadas
     */
    public function crearParaGrupos(array $grupos, array $datos): int   // Crea la cuota en cada grupo y la asigna a sus choferes (todo junto o nada)
    {
        return $this->transaccion(function () use ($grupos, $datos) {   // Transacción: si falla un grupo, no se guarda ninguno
            foreach ($grupos as $idGrupo) {   // Para cada grupo elegido...
                $idCuota = $this->insertar($datos + ['id_grupo' => (int) $idGrupo, 'id_usuario' => Auth::id()]);   // ...crea la cuota con el grupo y el usuario que la registró
                $this->query(   // ...y crea una deuda "pendiente" para cada chofer activo del grupo
                    "INSERT INTO pago_cuota (id_cuota, id_chofer)
                     SELECT ?, id_chofer FROM chofer WHERE id_grupo = ? AND estado = 'activo'",
                    [$idCuota, $idGrupo]   // Datos: la cuota y el grupo
                );
            }
            return count($grupos);   // Devuelve cuántos grupos recibieron la cuota
        });
    }

    /**
     * Modifica la cuota. Los pendientes toman el nuevo monto automáticamente (se leen de la cuota);
     * los ya pagados conservan su monto_pagado.
     */
    public function modificar(int $id, array $datos): void   // Modifica la cuota (el monto pendiente sale de la cuota, se actualiza solo)
    {
        $this->actualizar($id, $datos);   // Actualiza los datos
    }

    /** Anula la cuota: elimina las obligaciones pendientes y conserva los pagos ya realizados. */
    public function anular(int $id): void   // Anula una cuota que ya tenía pagos
    {
        $this->transaccion(function () use ($id) {   // Transacción:
            $this->query("UPDATE cuota SET estado = 'anulada' WHERE id_cuota = ?", [$id]);   // marca la cuota como anulada
            $this->query("DELETE FROM pago_cuota WHERE id_cuota = ? AND estado = 'pendiente'", [$id]);   // y borra solo las deudas pendientes (los pagos cobrados se conservan)
        });
    }

    /** Borra una cuota que todavía no tiene ningún pago cobrado (con sus obligaciones pendientes). */
    public function eliminarSinPagos(int $id): bool   // Borra una cuota solo si nadie la pagó
    {
        return $this->transaccion(function () use ($id) {   // Transacción:
            if ((int) $this->fetchValue("SELECT COUNT(*) FROM pago_cuota WHERE id_cuota = ? AND estado = 'pagado'", [$id]) > 0) {   // Si ya tiene pagos cobrados...
                return false;   // ...no la borra
            }
            $this->query('DELETE FROM pago_cuota WHERE id_cuota = ?', [$id]);   // Borra las deudas asignadas
            $this->query('DELETE FROM cuota WHERE id_cuota = ?', [$id]);   // Borra la cuota
            return true;   // Avisa que se borró
        });
    }

    /** Asigna a un chofer nuevo las cuotas vigentes de su grupo que todavía no vencieron. */
    public function asignarVigentesAChofer(int $idChofer, int $idGrupo): void   // Asigna a un chofer nuevo las cuotas vigentes de su grupo que aún no vencieron
    {
        $this->query(   // Ejecuta:
            "INSERT IGNORE INTO pago_cuota (id_cuota, id_chofer)
             SELECT id_cuota, ? FROM cuota
             WHERE id_grupo = ? AND estado = 'vigente' AND fecha_limite >= CURDATE()",
            [$idChofer, $idGrupo]   // Datos: chofer y grupo
        );
    }
}

/**
 * Pagos de cuotas (RF04). El registro es exclusivamente manual del dinero
 * recibido en ventanilla (límite: sin pagos electrónicos ni facturación).
 */
class Pago extends Modelo   // Modelo de pagos (tabla pago_cuota)
{
    protected string $tabla = 'pago_cuota';   // Tabla que maneja
    protected string $clave = 'id_pago';   // Clave primaria

    /**
     * Columnas calculadas (no se guardan para no repetir datos):
     *  - monto:      lo cobrado si ya se pagó; si está pendiente, el monto vigente de la cuota.
     *  - nro_recibo: el id del pago con formato R-000123.
     */
    public const SQL_MONTO  = 'COALESCE(p.monto_pagado, q.monto)';   // Monto: lo cobrado si está pagado, o el monto de la cuota si está pendiente
    public const SQL_RECIBO = "CONCAT('R-', LPAD(p.id_pago, 6, '0'))";   // N° de recibo calculado del número del pago: R-000123

    /** Obligaciones del chofer (pendientes primero, luego las pagadas recientes). */
    public function delChofer(int $idChofer, ?string $estado = null): array   // Cuotas de un chofer (todas o solo las pendientes)
    {
        $params = [$idChofer];   // Primer dato: el chofer
        $filtro = '';   // Filtro opcional por estado
        if ($estado) {   // Si se pide un estado...
            $filtro = ' AND p.estado = ?';   // ...agrega la condición
            $params[] = $estado;   // ...y su valor
        }
        return $this->fetchAll(   // Consulta: cada cuota con su monto, recibo y si ya venció
            "SELECT p.*, " . self::SQL_MONTO . " AS monto, " . self::SQL_RECIBO . " AS nro_recibo,
                    q.concepto, q.tipo, q.fecha_limite, q.acta_referencia,
                    (p.estado = 'pendiente' AND q.fecha_limite < CURDATE()) AS vencida
             FROM pago_cuota p JOIN cuota q ON q.id_cuota = p.id_cuota
             WHERE p.id_chofer = ? AND q.estado = 'vigente' $filtro
             ORDER BY p.estado = 'pagado', q.fecha_limite",
            $params   // Datos de la consulta preparada
        );
    }

    /**
     * Registra el cobro de una obligación pendiente de forma atómica.
     * Si la obligación ya estaba pagada, no se modifica (evita pagos duplicados, CP03).
     * @return array|null datos del pago registrado o null si ya estaba pagado
     */
    public function cobrar(int $idPago): ?array   // Cobra una cuota: solo si sigue pendiente (evita cobrar dos veces)
    {
        return $this->transaccion(function () use ($idPago) {   // Transacción:
            $afectadas = $this->query(   // Actualiza el pago a "pagado" con el monto, la fecha y el cajero...
                "UPDATE pago_cuota p JOIN cuota q ON q.id_cuota = p.id_cuota
                 SET p.estado = 'pagado', p.monto_pagado = q.monto, p.fecha_pago = NOW(), p.id_usuario = ?
                 WHERE p.id_pago = ? AND p.estado = 'pendiente' AND q.estado = 'vigente'",
                [Auth::id(), $idPago]   // Datos: el cajero y el número del pago
            )->rowCount();   // Cuántas filas cambió (0 = ya estaba pagado)

            return $afectadas === 0 ? null : $this->detalle($idPago);   // Si no cambió nada devuelve null; si cobró, devuelve los datos del pago
        });
    }

    /** Revierte un pago registrado por error (solo administrador; queda en bitácora). */
    public function revertir(int $idPago): bool   // Revierte un pago cobrado por error (vuelve a pendiente)
    {
        return $this->query(   // Ejecuta el UPDATE...
            "UPDATE pago_cuota SET estado = 'pendiente', monto_pagado = NULL, fecha_pago = NULL, id_usuario = NULL
             WHERE id_pago = ? AND estado = 'pagado'",
            [$idPago]   // Dato: el número del pago
        )->rowCount() > 0;   // Verdadero si cambió algo
    }

    public function detalle(int $idPago): ?array   // Datos completos de un pago (para el recibo)
    {
        return $this->fetchOne(   // Consulta con la cuota, el chofer, el grupo y el cajero
            "SELECT p.*, " . self::SQL_MONTO . " AS monto, " . self::SQL_RECIBO . " AS nro_recibo,
                    q.concepto, q.tipo, q.fecha_limite, q.acta_referencia,
                    c.id_chofer, c.ci, c.ci_expedido, c.codigo_qr, CONCAT(c.nombres, ' ', c.apellidos) AS chofer,
                    g.nombre AS grupo, u.nombre AS cajero
             FROM pago_cuota p
             JOIN cuota q ON q.id_cuota = p.id_cuota
             JOIN chofer c ON c.id_chofer = p.id_chofer
             JOIN grupo_transporte g ON g.id_grupo = c.id_grupo
             LEFT JOIN usuario u ON u.id_usuario = p.id_usuario
             WHERE p.id_pago = ?",
            [$idPago]   // Dato: el número del pago
        );
    }

    public function saldoChofer(int $idChofer): array   // Saldo de un chofer: pendiente, en mora y pagado
    {
        return $this->fetchOne(   // Consulta que suma sus cuotas
            "SELECT COALESCE(SUM(CASE WHEN p.estado = 'pendiente' THEN q.monto END), 0) AS pendiente,
                    COALESCE(SUM(CASE WHEN p.estado = 'pendiente' AND q.fecha_limite < CURDATE() THEN q.monto END), 0) AS vencido,
                    COALESCE(SUM(p.monto_pagado), 0) AS pagado
             FROM pago_cuota p JOIN cuota q ON q.id_cuota = p.id_cuota
             WHERE p.id_chofer = ? AND q.estado = 'vigente'",
            [$idChofer]   // Dato: el chofer
        );
    }
}
