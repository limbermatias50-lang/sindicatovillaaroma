<?php
/**
 * MODELO de reportes: caja diaria, estados de cuenta, morosidad e indicadores.
 */

/**
 * Consultas de consolidación financiera (RF07): caja diaria, estado de cuenta
 * por grupo, morosidad e indicadores para los gráficos del panel.
 */
class Reporte extends Modelo   // Modelo de reportes: solo consulta datos (no guarda nada)
{
    protected string $tabla = 'pago_cuota';   // Tabla principal: los pagos
    protected string $clave = 'id_pago';   // Clave primaria

    /** Pagos cobrados en un rango de fechas (caja diaria cuando desde = hasta). */
    public function caja(string $desde, string $hasta, int $idGrupo = 0): array   // Pagos cobrados entre dos fechas (caja diaria)
    {
        $params = ['d' => $desde . ' 00:00:00', 'h' => $hasta . ' 23:59:59'];   // Datos: desde el inicio del primer día hasta el final del último
        $filtro = '';   // Filtro opcional por grupo
        if ($idGrupo > 0) {   // Si se eligió un grupo...
            $filtro = ' AND c.id_grupo = :g';   // ...agrega la condición
            $params['g'] = $idGrupo;   // ...y su valor
        }
        return $this->fetchAll(   // Consulta de los pagos con recibo, chofer, grupo y cajero
            "SELECT p.id_pago, " . Pago::SQL_RECIBO . " AS nro_recibo, p.fecha_pago, p.monto_pagado AS monto, q.concepto, q.tipo,
                    CONCAT(c.nombres, ' ', c.apellidos) AS chofer, c.ci, g.nombre AS grupo, u.nombre AS cajero
             FROM pago_cuota p
             JOIN cuota q ON q.id_cuota = p.id_cuota
             JOIN chofer c ON c.id_chofer = p.id_chofer
             JOIN grupo_transporte g ON g.id_grupo = c.id_grupo
             LEFT JOIN usuario u ON u.id_usuario = p.id_usuario
             WHERE p.estado = 'pagado' AND p.fecha_pago BETWEEN :d AND :h $filtro
             ORDER BY p.fecha_pago",
            $params   // Datos de la consulta preparada
        );
    }

    /**
     * Condición SQL sobre el periodo de la cuota (mes de su fecha límite).
     * Devuelve [sql, params] para añadir a un WHERE.
     */
    private function periodo(string $alias, ?string $desde, ?string $hasta): array   // Arma la condición de periodo según la fecha límite de las cuotas
    {
        $sql = '';   // Parte del SQL
        $p   = [];   // Valores
        if ($desde) { $sql .= " AND $alias.fecha_limite >= ?"; $p[] = $desde; }   // Si hay fecha desde, agrega "fecha_limite >= ?"
        if ($hasta) { $sql .= " AND $alias.fecha_limite <= ?"; $p[] = $hasta; }   // Si hay fecha hasta, agrega "fecha_limite <= ?"
        return [$sql, $p];   // Devuelve el SQL y sus valores
    }

    /** Estado de cuenta: por cada chofer del grupo, total asignado, pagado y pendiente en el periodo. */
    public function estadoCuentaGrupo(int $idGrupo, ?string $desde = null, ?string $hasta = null): array   // Estado de cuenta: por cada chofer del grupo, asignado, pagado, pendiente y en mora
    {
        [$per, $pp] = $this->periodo('q', $desde, $hasta);   // Condición del periodo
        return $this->fetchAll(   // Consulta agrupada por chofer
            "SELECT c.id_chofer, c.ci, CONCAT(c.apellidos, ' ', c.nombres) AS chofer, c.estado,
                    COALESCE(SUM(p.monto), 0) AS asignado,
                    COALESCE(SUM(CASE WHEN p.estado = 'pagado' THEN p.monto END), 0) AS pagado,
                    COALESCE(SUM(CASE WHEN p.estado = 'pendiente' THEN p.monto END), 0) AS pendiente,
                    COALESCE(SUM(CASE WHEN p.estado = 'pendiente' AND p.fecha_limite < CURDATE() THEN p.monto END), 0) AS vencido
             FROM chofer c
             LEFT JOIN (SELECT pc.id_chofer, COALESCE(pc.monto_pagado, q.monto) AS monto, pc.estado, q.fecha_limite
                        FROM pago_cuota pc JOIN cuota q ON q.id_cuota = pc.id_cuota AND q.estado = 'vigente' $per) p
                    ON p.id_chofer = c.id_chofer
             WHERE c.id_grupo = ?
             GROUP BY c.id_chofer ORDER BY c.apellidos, c.nombres",
            array_merge($pp, [$idGrupo])   // Datos: periodo y grupo
        );
    }

    /**
     * Consolidado por grupo (Figura 44): cuotas del periodo, pagado, pendiente y % de mora
     * (obligaciones vencidas sin pagar sobre el total de obligaciones del grupo).
     */
    public function resumenGrupos(?string $desde = null, ?string $hasta = null, int $idGrupo = 0): array   // Consolidado por grupo: cuotas, pagado, pendiente y % de mora
    {
        [$per, $pp] = $this->periodo('q', $desde, $hasta);   // Condición del periodo
        $filtro = '';   // Filtro opcional por grupo
        if ($idGrupo > 0) { $filtro = ' AND g.id_grupo = ?'; }   // Si se eligió un grupo, agrega la condición
        $filas = $this->fetchAll(   // Consulta agrupada por grupo
            "SELECT g.id_grupo, g.nombre,
                    COUNT(DISTINCT q.id_cuota) AS cuotas,
                    COUNT(p.id_pago) AS obligaciones,
                    COALESCE(SUM(p.monto_pagado), 0) AS recaudado,
                    COALESCE(SUM(CASE WHEN p.estado = 'pendiente' THEN q.monto END), 0) AS pendiente,
                    COALESCE(SUM(CASE WHEN p.estado = 'pendiente' AND q.fecha_limite < CURDATE() THEN q.monto END), 0) AS vencido,
                    COUNT(CASE WHEN p.estado = 'pendiente' AND q.fecha_limite < CURDATE() THEN 1 END) AS obligaciones_vencidas
             FROM grupo_transporte g
             LEFT JOIN cuota q ON q.id_grupo = g.id_grupo AND q.estado = 'vigente' $per
             LEFT JOIN pago_cuota p ON p.id_cuota = q.id_cuota
             WHERE g.activo = 1 $filtro
             GROUP BY g.id_grupo ORDER BY g.nombre",
            $idGrupo > 0 ? array_merge($pp, [$idGrupo]) : $pp   // Datos: periodo y (si hay) grupo
        );
        foreach ($filas as &$f) {   // Para cada grupo...
            $f['porc_mora'] = $f['obligaciones'] > 0 ? round($f['obligaciones_vencidas'] * 100 / $f['obligaciones'], 1) : 0.0;   // ...calcula el % de mora: cuotas vencidas sin pagar entre el total de cuotas
        }
        return $filas;   // Devuelve los grupos
    }

    /**
     * Estado de morosidad (Figura 38): choferes con cuotas pendientes del periodo,
     * monto adeudado y estado («En mora» si alguna ya venció, «Pendiente» si aún está en plazo).
     */
    public function morosidad(int $idGrupo = 0, ?string $desde = null, ?string $hasta = null, bool $soloVencidas = false): array   // Morosidad: choferes con cuotas pendientes, monto y estado
    {
        [$per, $params] = $this->periodo('q', $desde, $hasta);   // Condición del periodo
        $filtro = '';   // Filtro opcional por grupo
        if ($idGrupo > 0) {   // Si se eligió un grupo...
            $filtro   = ' AND c.id_grupo = ?';   // ...agrega la condición
            $params[] = $idGrupo;   // ...y su valor
        }
        $having = $soloVencidas ? ' HAVING cuotas_vencidas > 0' : '';   // Opción para mostrar solo los que ya están en mora
        return $this->fetchAll(   // Consulta agrupada por chofer; los más atrasados primero
            "SELECT c.id_chofer, c.ci, CONCAT(c.apellidos, ' ', c.nombres) AS chofer, c.telefono, g.nombre AS grupo,
                    COUNT(*) AS cuotas_pendientes, SUM(q.monto) AS monto_pendiente,
                    COUNT(CASE WHEN q.fecha_limite < CURDATE() THEN 1 END) AS cuotas_vencidas,
                    COALESCE(SUM(CASE WHEN q.fecha_limite < CURDATE() THEN q.monto END), 0) AS monto_vencido,
                    MIN(q.fecha_limite) AS desde, GREATEST(0, MAX(DATEDIFF(CURDATE(), q.fecha_limite))) AS dias_mora
             FROM pago_cuota p
             JOIN cuota q ON q.id_cuota = p.id_cuota AND q.estado = 'vigente'
             JOIN chofer c ON c.id_chofer = p.id_chofer
             JOIN grupo_transporte g ON g.id_grupo = c.id_grupo
             WHERE p.estado = 'pendiente' $per $filtro
             GROUP BY c.id_chofer $having
             ORDER BY cuotas_vencidas DESC, monto_pendiente DESC",
            $params   // Datos de la consulta preparada
        );
    }

    /** Periodos (meses) que tienen cuotas registradas, para los filtros. */
    public function periodos(): array   // Meses que tienen cuotas (para el filtro "Periodo")
    {
        return array_column($this->fetchAll(   // Lista de meses año-mes, del más reciente al más antiguo
            "SELECT DISTINCT DATE_FORMAT(fecha_limite, '%Y-%m') AS mes FROM cuota WHERE estado = 'vigente' ORDER BY mes DESC"
        ), 'mes');
    }

    /** Número de afiliados con al menos una cuota vencida sin pagar. */
    public function afiliadosEnMora(int $idGrupo = 0): int   // Cuántos choferes tienen al menos una cuota vencida sin pagar
    {
        return (int) $this->fetchValue(   // Cuenta choferes distintos
            "SELECT COUNT(DISTINCT p.id_chofer) FROM pago_cuota p
             JOIN cuota q ON q.id_cuota = p.id_cuota AND q.estado = 'vigente'
             JOIN chofer c ON c.id_chofer = p.id_chofer
             WHERE p.estado = 'pendiente' AND q.fecha_limite < CURDATE()" . ($idGrupo > 0 ? ' AND c.id_grupo = ?' : ''),
            $idGrupo > 0 ? [$idGrupo] : []   // Dato: grupo (si se eligió)
        );
    }

    /** Choferes activos al día frente a los que están en mora (gráfico circular del panel). */
    public function alDiaVsMora(): array   // Choferes al día frente a choferes en mora (gráfico circular)
    {
        $activos = (int) $this->fetchValue("SELECT COUNT(*) FROM chofer WHERE estado = 'activo'");   // Choferes activos
        $mora    = (int) $this->fetchValue(   // Choferes activos en mora
            "SELECT COUNT(DISTINCT p.id_chofer) FROM pago_cuota p
             JOIN cuota q ON q.id_cuota = p.id_cuota AND q.estado = 'vigente'
             JOIN chofer c ON c.id_chofer = p.id_chofer AND c.estado = 'activo'
             WHERE p.estado = 'pendiente' AND q.fecha_limite < CURDATE()"
        );
        return ['al_dia' => max(0, $activos - $mora), 'en_mora' => $mora];   // Al día = activos menos los que están en mora
    }

    /** Ingresos por mes de los últimos $meses meses (gráfico de líneas). */
    public function ingresosMensuales(int $meses = 6): array   // Ingresos de los últimos N meses (gráfico de líneas)
    {
        $filas = $this->fetchAll(   // Suma lo cobrado por mes
            "SELECT DATE_FORMAT(fecha_pago, '%Y-%m') AS mes, SUM(monto_pagado) AS total
             FROM pago_cuota
             WHERE estado = 'pagado' AND fecha_pago >= DATE_SUB(DATE_FORMAT(CURDATE(), '%Y-%m-01'), INTERVAL ? MONTH)
             GROUP BY mes",
            [$meses - 1]   // Dato: cuántos meses hacia atrás
        );
        $mapa = array_column($filas, 'total', 'mes');   // Convierte el resultado en mes => total
        $res  = [];   // Resultado final
        for ($i = $meses - 1; $i >= 0; $i--) {   // Recorre los meses del más antiguo al actual...
            $mes = date('Y-m', strtotime(date('Y-m-01') . " -$i month"));   // ...calcula el año-mes
            $res[] = ['mes' => $mes, 'total' => (float) ($mapa[$mes] ?? 0)];   // ...y pone su total (0 si no hubo cobros)
        }
        return $res;   // Devuelve los meses con sus totales
    }

    /** Dinero cobrado en caja entre dos fechas (opcionalmente de un grupo). */
    public function totalCobradoEntre(string $desde, string $hasta, int $idGrupo = 0): float   // Dinero cobrado entre dos fechas (opcionalmente de un grupo)
    {
        return (float) $this->fetchValue(   // Suma lo cobrado
            "SELECT COALESCE(SUM(p.monto_pagado), 0) FROM pago_cuota p JOIN chofer c ON c.id_chofer = p.id_chofer
             WHERE p.estado = 'pagado' AND p.fecha_pago BETWEEN ? AND ?" . ($idGrupo > 0 ? ' AND c.id_grupo = ?' : ''),
            array_merge([$desde . ' 00:00:00', $hasta . ' 23:59:59'], $idGrupo > 0 ? [$idGrupo] : [])   // Datos: rango de fechas y grupo
        );
    }

    public function totalVencido(): float   // Total de deuda vencida
    {
        return (float) $this->fetchValue(   // Suma las cuotas pendientes cuya fecha límite ya pasó
            "SELECT COALESCE(SUM(q.monto), 0) FROM pago_cuota p JOIN cuota q ON q.id_cuota = p.id_cuota
             WHERE p.estado = 'pendiente' AND q.estado = 'vigente' AND q.fecha_limite < CURDATE()"
        );
    }
}
