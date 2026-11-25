<?php
/**
 * CONTROLADOR del panel de indicadores y de la emisión de reportes (caja, estado de cuenta y morosidad).
 */
class ReporteControlador extends Controlador   // Controlador del panel de indicadores y de los reportes
{
    /* ==================== Reporte ==================== */

    public const SIN_DATOS = 'No existen datos para el periodo seleccionado';   // Mensaje cuando un reporte no tiene registros (Tabla 33)

    public const TIPOS = [   // Tipos de reporte que se pueden elegir:
        'consolidado' => 'Consolidado por grupo',   // resumen de cuotas por grupo
        'caja'        => 'Caja diaria',   // pagos cobrados en el día o en un rango
        'estado'      => 'Estado de cuenta por grupo',   // cuánto debe cada chofer de un grupo
        'morosidad'   => 'Niveles de morosidad',   // choferes con cuotas pendientes
    ];

    private Reporte $reportes;   // Aquí se guarda el modelo Reporte

    public function __construct()   // Se ejecuta al crear el controlador
    {
        $this->reportes = new Reporte();   // Crea el modelo de reportes
    }

    /** Pantalla «Generar reporte»: consolidado por grupo o redirección al reporte elegido. */
    public function index(): void   // Pantalla "Generar reporte" (consolidado por grupo)
    {
        $tipo = (string) $this->input('tipo', 'consolidado');   // Tipo de reporte elegido
        $tipo = isset(self::TIPOS[$tipo]) ? $tipo : 'consolidado';   // Si el tipo no existe, usa el consolidado
        [$desde, $hasta] = $this->rango(date('Y-m-01'), date('Y-m-t'));   // Fechas desde/hasta (por defecto: el mes actual)
        $grupo = $this->inputInt('grupo');   // Grupo elegido (0 = todos)
        $pdf   = $this->input('pdf') === '1';   // ¿Se presionó "Exportar PDF"?

        $destinos = ['caja' => 'reportes/caja', 'estado' => 'reportes/estado-cuenta', 'morosidad' => 'reportes/morosidad'];   // Pantalla de cada tipo de reporte
        if (isset($destinos[$tipo])) {   // Si el tipo elegido tiene su propia pantalla...
            $this->redirect($destinos[$tipo], array_filter(['desde' => $desde, 'hasta' => $hasta, 'grupo' => $grupo, 'pdf' => $pdf ? 1 : null]));   // ...lleva allí los mismos filtros
        }

        $filas   = $this->reportes->resumenGrupos($desde, $hasta, $grupo);   // Consolidado: cuotas, pagado, pendiente y % de mora por grupo
        $conDato = array_filter($filas, fn($f) => $f['obligaciones'] > 0);   // Solo los grupos que tienen cuotas en ese periodo
        $kpi     = [   // Indicadores de arriba:
            'hoy'    => $this->reportes->totalCobradoEntre(date('Y-m-d'), date('Y-m-d'), $grupo),   // ingresos del día
            'total'  => $this->reportes->totalCobradoEntre($desde, $hasta, $grupo),   // total cobrado en el periodo
            'mora'   => $this->reportes->afiliadosEnMora($grupo),   // afiliados en mora
        ];

        if (!$conDato) {   // Si no hay datos...
            $this->avisarSinDatos();   // ...avisa "No existen datos para el periodo seleccionado"
        } elseif ($pdf) {   // Si hay datos y se pidió PDF...
            Bitacora::registrar('REPORTE_PDF', "Consolidado $desde a $hasta");   // ...registra en la bitácora
            (new ReportePdf('Consolidado por grupo de transporte', 'Periodo: ' . fecha($desde) . ' al ' . fecha($hasta)))   // crea el PDF con título y periodo
                ->tabla(   // agrega la tabla:
                    ['Grupo' => 50, 'Cuotas' => 20, 'Pagado (Bs)' => 35, 'Pendiente (Bs)' => 35, '% Mora' => 25],   // columnas y su ancho en mm
                    array_map(fn($f) => [$f['nombre'], $f['cuotas'], num($f['recaudado']), num($f['pendiente']), num($f['porc_mora']) . ' %'], $conDato),   // una fila por grupo
                    ['TOTAL PAGADO', num(array_sum(array_column($conDato, 'recaudado')))]   // fila de total
                )
                ->salida('consolidado_' . $desde . '_' . $hasta . '.pdf');   // envía el PDF al navegador
        }

        $this->view('reportes', [   // Muestra reportes.php con:
            'titulo'  => 'Emisión de reportes',   // título
            'grupos'  => (new Grupo())->activos(),   // grupos para el filtro
            'filas'   => $conDato,   // filas del consolidado
            'kpi'     => $kpi,   // indicadores
            'tipo'    => $tipo,   // tipo elegido
            'desde'   => $desde,   // fecha desde
            'hasta'   => $hasta,   // fecha hasta
            'grupo'   => $grupo,   // grupo elegido
            'sinDatos'=> !$conDato,   // si no hay datos
        ]);
    }

    /** Caja diaria o por rango de fechas. */
    public function caja(): void   // Reporte de caja diaria
    {
        [$desde, $hasta] = $this->rango(date('Y-m-d'), null);   // Fechas (por defecto hoy)
        $grupo = $this->inputInt('grupo');   // Grupo elegido
        $filas = $this->reportes->caja($desde, $hasta, $grupo);   // Pagos cobrados en esas fechas
        $total = array_sum(array_column($filas, 'monto'));   // Suma de lo cobrado

        if (!$filas) {   // Si no hubo pagos...
            $this->avisarSinDatos();   // ...avisa
        } elseif ($this->input('pdf') === '1') {   // Si hay pagos y se pidió PDF...
            Bitacora::registrar('REPORTE_PDF', "Caja $desde a $hasta");   // ...registra en la bitácora
            (new ReportePdf('Reporte de caja', $desde === $hasta ? 'Fecha: ' . fecha($desde) : 'Del ' . fecha($desde) . ' al ' . fecha($hasta)))   // crea el PDF
                ->tabla(   // agrega la tabla:
                    ['Recibo' => 22, 'Fecha/hora' => 28, 'Chofer' => 50, 'Grupo' => 22, 'Concepto' => 48, 'Monto' => 20],   // columnas y su ancho
                    array_map(fn($f) => [$f['nro_recibo'], fecha($f['fecha_pago'], true), $f['chofer'], $f['grupo'], $f['concepto'], num($f['monto'])], $filas),   // una fila por pago
                    ['TOTAL RECAUDADO', num($total)]   // fila de total
                )
                ->salida('caja_' . $desde . '.pdf');   // envía el PDF
        }

        $this->view('reporte_caja', [   // Muestra reporte_caja.php con:
            'titulo' => 'Caja diaria',   // título
            'filas'  => $filas,   // pagos
            'total'  => $total,   // total
            'desde'  => $desde,   // desde
            'hasta'  => $hasta,   // hasta
            'grupo'  => $grupo,   // grupo elegido
            'grupos' => (new Grupo())->activos(),   // grupos
        ]);
    }

    /** Estado de cuenta por grupo de transporte, filtrable por fechas (periodo de las cuotas). */
    public function estadoCuenta(): void   // Estado de cuenta por grupo
    {
        $grupos  = (new Grupo())->activos();   // Grupos activos
        $idGrupo = $this->inputInt('grupo') ?: (int) ($grupos[0]['id_grupo'] ?? 0);   // Grupo elegido (o el primero)
        $grupo   = (new Grupo())->buscar($idGrupo);   // Datos del grupo
        $desde   = $this->fechaOpcional('desde');   // Fecha desde (opcional)
        $hasta   = $this->fechaOpcional('hasta');   // Fecha hasta (opcional)
        $filas   = $grupo ? $this->reportes->estadoCuentaGrupo($idGrupo, $desde, $hasta) : [];   // Por cada chofer: asignado, pagado, pendiente y en mora
        $totales = [   // Totales del grupo:
            'asignado'  => array_sum(array_column($filas, 'asignado')),   // total asignado
            'pagado'    => array_sum(array_column($filas, 'pagado')),   // total pagado
            'pendiente' => array_sum(array_column($filas, 'pendiente')),   // total pendiente
            'vencido'   => array_sum(array_column($filas, 'vencido')),   // total en mora
        ];
        $sinDatos = $totales['asignado'] <= 0;   // Sin cuotas asignadas = sin datos

        if ($sinDatos) {   // Si no hay datos...
            $this->avisarSinDatos();   // ...avisa
        } elseif ($grupo && $this->input('pdf') === '1') {   // Si hay datos y se pidió PDF...
            Bitacora::registrar('REPORTE_PDF', "Estado de cuenta {$grupo['nombre']}");   // ...registra en la bitácora
            $sub = ($desde || $hasta) ? 'Periodo: ' . ($desde ? fecha($desde) : 'inicio') . ' al ' . ($hasta ? fecha($hasta) : 'hoy') : 'Corte al ' . date('d/m/Y');   // Subtítulo con el periodo o la fecha de corte
            (new ReportePdf('Estado de cuenta — ' . $grupo['nombre'], $sub))   // crea el PDF
                ->tabla(   // agrega la tabla:
                    ['C.I.' => 22, 'Chofer' => 62, 'Asignado' => 25, 'Pagado' => 25, 'Pendiente' => 25, 'En mora' => 25],   // columnas y su ancho
                    array_map(fn($f) => [$f['ci'], $f['chofer'], num($f['asignado']), num($f['pagado']), num($f['pendiente']), num($f['vencido'])], $filas),   // una fila por chofer
                    ['TOTALES', num($totales['asignado']), num($totales['pagado']), num($totales['pendiente']), num($totales['vencido'])]   // fila de totales
                )
                ->salida('estado_cuenta_' . $idGrupo . '.pdf');   // envía el PDF
        }

        $this->view('reporte_estado', [   // Muestra reporte_estado.php con:
            'titulo'   => 'Estado de cuenta por grupo',   // título
            'grupos'   => $grupos,   // grupos
            'grupo'    => $grupo,   // grupo elegido
            'filas'    => $filas,   // filas
            'totales'  => $totales,   // totales
            'desde'    => $desde,   // desde
            'hasta'    => $hasta,   // hasta
            'sinDatos' => $sinDatos,   // si no hay datos
        ]);
    }

    /** Estado de morosidad por grupo y periodo (Figura 38, Tabla 30). */
    public function morosidad(): void   // Estado de morosidad por grupo y periodo
    {
        $idGrupo = $this->inputInt('grupo');   // Grupo elegido
        $periodo = (string) $this->input('periodo', '');   // Periodo elegido (año-mes)
        $desde   = $hasta = null;   // Fechas del periodo
        if (preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $periodo)) {   // Si el periodo tiene formato año-mes válido...
            $desde = $periodo . '-01';   // ...empieza el día 1
            $hasta = date('Y-m-t', strtotime($desde));   // ...y termina el último día del mes
        } else {
            $periodo = '';   // Si no, sin periodo
            $desde   = $this->fechaOpcional('desde');   // ...usa desde/hasta si se escribieron
            $hasta   = $this->fechaOpcional('hasta');
        }
        $filas = $this->reportes->morosidad($idGrupo, $desde, $hasta);   // Choferes con cuotas pendientes en ese periodo
        $total = array_sum(array_column($filas, 'monto_pendiente'));   // Total adeudado

        if (!$filas) {   // Si no hay nadie...
            $this->avisarSinDatos();   // ...avisa
        } elseif ($this->input('pdf') === '1') {   // Si hay datos y se pidió PDF...
            Bitacora::registrar('REPORTE_PDF', 'Morosidad');   // ...registra en la bitácora
            $sub = $periodo !== '' ? 'Periodo: ' . nombre_periodo($periodo) : 'Corte al ' . date('d/m/Y');   // Subtítulo con el periodo o la fecha de corte
            (new ReportePdf('Estado de morosidad', $sub))   // crea el PDF
                ->tabla(   // agrega la tabla:
                    ['Chofer' => 58, 'C.I.' => 24, 'Grupo' => 26, 'Cuotas pend.' => 22, 'Monto (Bs)' => 24, 'Estado' => 32],   // columnas y su ancho
                    array_map(fn($f) => [$f['chofer'], $f['ci'], $f['grupo'], $f['cuotas_pendientes'], num($f['monto_pendiente']), estado_mora($f)['texto']], $filas),   // una fila por chofer
                    ['TOTAL ADEUDADO', num($total)]   // fila de total
                )
                ->salida('morosidad.pdf');   // envía el PDF
        }

        $this->view('reporte_morosidad', [   // Muestra reporte_morosidad.php con:
            'titulo'   => 'Estado de morosidad',   // título
            'filas'    => $filas,   // filas
            'total'    => $total,   // total adeudado
            'grupo'    => $idGrupo,   // grupo elegido
            'periodo'  => $periodo,   // periodo elegido
            'periodos' => $this->reportes->periodos(),   // periodos que tienen cuotas (para el filtro)
            'grupos'   => (new Grupo())->activos(),   // grupos
        ]);
    }

    /** Tabla 33: si el reporte pedido no tiene registros se avisa con un mensaje. */
    private function avisarSinDatos(): void   // Muestra el aviso "No existen datos" solo si el usuario pidió un reporte
    {
        if (array_intersect(['desde', 'hasta', 'grupo', 'periodo', 'pdf', 'tipo'], array_keys($_GET))) {   // Si en la dirección vienen filtros...
            flash('warning', self::SIN_DATOS, 'Sin registros');   // ...prepara el aviso
        }
    }

    /** Lee desde/hasta del formulario; si vienen invertidas las ordena. */
    private function rango(string $desdeDefecto, ?string $hastaDefecto): array   // Lee las fechas desde/hasta del formulario
    {
        $desde = $this->fechaOpcional('desde') ?? $desdeDefecto;   // Fecha desde (o la de por defecto)
        $hasta = $this->fechaOpcional('hasta') ?? ($hastaDefecto ?? $desde);   // Fecha hasta (o la de por defecto, o igual a desde)
        return $hasta < $desde ? [$hasta, $desde] : [$desde, $hasta];   // Si están al revés, las ordena
    }

    private function fechaOpcional(string $campo): ?string   // Lee una fecha y la devuelve solo si es válida
    {
        $f = (string) $this->input($campo, '');   // Texto recibido
        $d = DateTime::createFromFormat('Y-m-d', $f);   // Intenta leerlo como fecha año-mes-día
        return $d && $d->format('Y-m-d') === $f ? $f : null;   // Si es una fecha real la devuelve; si no, null
    }

    /* ==================== Dashboard ==================== */

    public function panel(): void   // Panel de indicadores (pantalla de inicio)
    {
        $reporte = new Reporte();   // Modelo de reportes
        $alertas = (new Licencia())->alertas();   // Licencias vencidas o por vencer
        $hoy     = date('Y-m-d');   // Fecha de hoy

        $this->view('panel', [   // Muestra panel.php con:
            'titulo'          => 'Panel de indicadores',   // título
            'totalChoferes'   => (new Chofer())->totalActivos(),   // choferes activos
            'cajaMes'         => $reporte->totalCobradoEntre(date('Y-m-01'), $hoy),   // recaudado en el mes
            'cajaHoy'         => $reporte->totalCobradoEntre($hoy, $hoy),   // recaudado hoy
            'afiliadosMora'   => $reporte->afiliadosEnMora(),   // afiliados en mora
            'licPorVencer'    => count($alertas),   // cantidad de licencias por vencer
            'alertas'         => $alertas,   // lista de esas licencias
            'resumenGrupos'   => $reporte->resumenGrupos(),   // recaudado y pendiente por grupo (gráfico de barras)
            'alDiaMora'       => $reporte->alDiaVsMora(),   // choferes al día vs en mora (gráfico circular)
            'mensual'         => $reporte->ingresosMensuales(6),   // ingresos de los últimos 6 meses (gráfico de líneas)
        ]);
    }
}
