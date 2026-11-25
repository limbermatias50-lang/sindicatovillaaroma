<?php
/**
 * CONTROLADOR de cuotas por grupo y del cobro de pagos en ventanilla (recibos y saldos).
 */
class CuotaControlador extends Controlador   // Controlador de cuotas por grupo y del cobro en ventanilla
{
    /* ==================== Cuota ==================== */

    private Cuota $cuotas;   // Aquí se guarda el modelo Cuota

    public function __construct()   // Se ejecuta al crear el controlador
    {
        $this->cuotas = new Cuota();   // Crea el modelo de cuotas
    }

    public function index(): void   // Lista de cuotas con filtros
    {
        $grupo  = $this->inputInt('grupo');   // Grupo elegido en el filtro
        $tipo   = (string) $this->input('tipo', '');   // Tipo elegido (ordinaria o extraordinaria)
        $estado = (string) $this->input('estado', 'vigente');   // Estado elegido (por defecto solo las vigentes)

        $this->view('cuotas', [   // Muestra cuotas.php con:
            'titulo' => 'Cuotas por grupo',   // título
            'cuotas' => $this->cuotas->listar($grupo, $tipo, $estado),   // cuotas que cumplen el filtro, con su avance de cobro
            'grupos' => (new Grupo())->activos(),   // grupos
            'resumen'=> (new Reporte())->resumenGrupos(),   // resumen de lo recaudado y pendiente por grupo
            'filtro' => compact('grupo', 'tipo', 'estado'),   // filtros actuales
            'puedeParametrizar' => Auth::tiene('ventanilla'),   // si el usuario puede crear cuotas
        ]);
        limpiar_old();   // Borra los datos de un error anterior
    }

    public function guardar(): void   // Registra una cuota nueva para uno o varios grupos
    {
        $this->requirePost();   // Solo POST con token CSRF
        $datos  = $this->validar();   // Valida los datos de la cuota
        $grupos = array_filter(array_map('intval', (array) ($_POST['grupos'] ?? [])));   // Grupos marcados, convertidos a números
        if (!$grupos) {   // Si no se marcó ningún grupo...
            $this->conErrores(['Seleccione al menos un grupo de transporte.'], 'cuotas');   // ...vuelve con el error
        }

        $n = $this->cuotas->crearParaGrupos($grupos, $datos);   // Crea la cuota y la asigna a todos los choferes activos de esos grupos
        Bitacora::registrar('CUOTA_CREADA', "{$datos['tipo']}: {$datos['concepto']} ({$datos['monto']} Bs) en $n grupo(s)");   // Registra en la bitácora
        flash('success', "Cuota registrada para $n grupo(s). Los saldos de los afiliados se actualizaron.");   // Mensaje de éxito
        $this->redirect('cuotas');   // Vuelve a la lista
    }

    public function actualizar(): void   // Modifica una cuota
    {
        $this->requirePost();   // Solo POST con token CSRF
        $id = $this->inputInt('id_cuota');   // Cuota elegida
        $cuota = $this->cuotas->buscar($id) ?? $this->notFound('Cuota no encontrada');   // Busca la cuota o muestra "no encontrada"
        if ($cuota['estado'] !== 'vigente') {   // Si la cuota está anulada...
            flash('error', 'No se puede modificar una cuota anulada.');   // ...no se puede modificar
            $this->redirect('cuotas');   // Vuelve a la lista
        }
        $datos = $this->validar();   // Valida los datos nuevos
        $this->cuotas->modificar($id, $datos);   // Guarda los cambios (el monto nuevo vale para quienes aún no pagaron)
        Bitacora::registrar('CUOTA_MODIFICADA', "Cuota #$id monto {$datos['monto']}");   // Registra en la bitácora
        flash('success', 'Cuota actualizada. El nuevo monto se aplica a quienes aún no pagaron.');   // Mensaje de éxito
        $this->redirect('cuotas');   // Vuelve a la lista
    }

    /**
     * Eliminar cuota (Sprint 3, tarea 6). Si nadie la pagó se borra; si ya tiene pagos cobrados
     * se anula, para no alterar la caja ni los recibos emitidos, y se quitan las deudas pendientes.
     */
    public function eliminar(): void   // Elimina una cuota (o la anula si ya tiene pagos)
    {
        $this->requirePost();   // Solo POST con token CSRF
        $id = $this->inputInt('id');   // Cuota elegida
        $this->cuotas->buscar($id) ?? $this->notFound('Cuota no encontrada');   // Comprueba que exista
        if ($this->cuotas->eliminarSinPagos($id)) {   // Si nadie la pagó, la borra junto con sus deudas...
            Bitacora::registrar('CUOTA_ELIMINADA', "Cuota #$id");   // ...registra en la bitácora
            flash('success', 'Cuota eliminada.');   // ...y avisa
        } else {   // Si ya tiene pagos cobrados...
            $this->cuotas->anular($id);   // ...la anula (no se borra para no alterar la caja) y quita las deudas pendientes
            Bitacora::registrar('CUOTA_ANULADA', "Cuota #$id (tenía pagos)");   // Registra en la bitácora
            flash('success', 'La cuota ya tenía pagos cobrados: se anuló y se quitaron las deudas pendientes. Los pagos registrados se conservan en caja.');   // Avisa lo que se hizo
        }
        $this->redirect('cuotas');   // Vuelve a la lista
    }

    private function validar(): array   // Valida el formulario de la cuota
    {
        $v = (new Validator($_POST))   // Reglas de validación:
            ->requerido('tipo', 'El tipo de cuota')->en('tipo', Cuota::TIPOS, 'El tipo de cuota')   // Tipo obligatorio: ordinaria o extraordinaria
            ->requerido('concepto', 'El concepto')->maximo('concepto', 120, 'El concepto')   // Concepto obligatorio, máximo 120
            ->requerido('monto', 'El monto')->numeroPositivo('monto', 'El monto')   // Monto obligatorio y mayor a cero
            ->entre('monto', 1, 10000, 'El monto debe estar entre Bs 1 y Bs 10.000.')   // Monto entre Bs 1 y Bs 10.000
            ->requerido('fecha_limite', 'La fecha límite')->fecha('fecha_limite', 'La fecha límite')   // Fecha límite (periodo) obligatoria y válida
            ->fechaEntre('fecha_limite', Bolivia::FUNDACION, date('Y-m-d', strtotime('+2 years')),   // Entre la fundación del sindicato y dentro de 2 años
                'La fecha límite debe estar entre la fundación del sindicato y los próximos 2 años.')
            ->maximo('acta_referencia', 120, 'La referencia del acta');   // Referencia del acta de asamblea, máximo 120
        if ($v->falla()) {   // Si hubo errores...
            $this->conErrores($v->errores(), 'cuotas');   // ...vuelve mostrándolos
        }
        return [   // Devuelve los datos listos para guardar:
            'tipo'            => $this->input('tipo'),   // tipo
            'concepto'        => $this->input('concepto'),   // concepto
            'monto'           => round((float) $this->input('monto'), 2),   // monto redondeado a 2 decimales
            'fecha_limite'    => $this->input('fecha_limite'),   // fecha límite
            'acta_referencia' => $this->input('acta_referencia') ?: null,   // acta de referencia (o NULL)
        ];
    }

    /* ==================== Pago ==================== */

    /** Cobra una o varias obligaciones pendientes (petición AJAX desde ventanilla). */
    public function registrarPago(): void   // Cobra una o varias cuotas (lo llama JavaScript desde la ventanilla)
    {
        $this->requirePost();   // Solo POST con token CSRF
        $ids = array_filter(array_map('intval', (array) ($_POST['pagos'] ?? [])));   // Números de las cuotas marcadas
        if (!$ids) {   // Si no se marcó ninguna...
            $this->json(['ok' => false, 'mensaje' => 'Seleccione al menos una cuota para cobrar.'], 422);   // ...responde el error en JSON
        }

        $modelo    = new Pago();   // Modelo de pagos
        $cobrados  = [];   // Lista de pagos cobrados
        $omitidos  = 0;   // Contador de cuotas que ya estaban pagadas
        foreach ($ids as $id) {   // Para cada cuota marcada...
            $pago = $modelo->cobrar($id);   // ...intenta cobrarla (solo si sigue pendiente)
            if ($pago) {   // Si se cobró...
                $cobrados[] = $pago;   // ...la agrega a la lista
                Bitacora::registrar('PAGO_REGISTRADO', "{$pago['nro_recibo']} chofer #{$pago['id_chofer']} " . bs($pago['monto']));   // ...y registra el pago en la bitácora
            } else {
                $omitidos++;   // Si ya estaba pagada, solo la cuenta
            }
        }

        if (!$cobrados) {   // Si no se cobró ninguna (todas ya estaban pagadas)...
            $this->json(['ok' => false, 'mensaje' => 'El periodo ya fue cancelado'], 409);   // ...responde "El periodo ya fue cancelado" (409 = conflicto)
        }

        $idChofer = (int) $cobrados[0]['id_chofer'];   // Chofer de los pagos
        $this->json([   // Responde en JSON:
            'ok'       => true,   // ok = correcto
            'mensaje'  => count($cobrados) . ' pago(s) registrado(s) por ' . bs(array_sum(array_column($cobrados, 'monto')))   // mensaje con cuántos pagos y el total...
                          . ($omitidos ? " ($omitidos ya estaban pagados)" : ''),   // ...y cuántos ya estaban pagados
            'recibos'  => array_map(fn($p) => [   // lista de recibos, cada uno con:
                'nro' => $p['nro_recibo'],   // su número R-000123
                'url' => url('pagos/recibo', ['id' => $p['id_pago']]),   // su dirección para imprimirlo
            ], $cobrados),
            'reciboUrl'=> url('pagos/recibo', ['ids' => implode(',', array_column($cobrados, 'id_pago'))]),   // dirección de un solo recibo con todos los pagos
            'saldo'    => $modelo->saldoChofer($idChofer),   // saldo actualizado del chofer
        ]);
    }

    /** Recibo imprimible en PDF (uno o varios pagos del mismo chofer). */
    public function recibo(): void   // Genera el recibo en PDF
    {
        $ids = array_filter(array_map('intval', explode(',', (string) $this->input('ids', $this->input('id', '')))));   // Números de pago pedidos (uno o varios separados por coma)
        $modelo = new Pago();   // Modelo de pagos
        $pagos  = [];   // Lista de pagos para el recibo
        foreach ($ids as $id) {   // Para cada número...
            $p = $modelo->detalle($id);   // ...busca el pago con su chofer y cuota
            if ($p && $p['estado'] === 'pagado') {   // Si existe y está pagado...
                $pagos[] = $p;   // ...lo agrega
            }
        }
        if (!$pagos) {   // Si no hay ninguno...
            $this->notFound('Recibo no encontrado');   // ...muestra "no encontrado"
        }
        (new ReciboPdf())->generar($pagos);   // Crea el recibo con FPDF y lo envía al navegador
    }

    /** Revierte un pago registrado por error (personal de ventanilla). */
    public function revertir(): void   // Revierte un pago registrado por error
    {
        $this->requirePost();   // Solo POST con token CSRF
        $id   = $this->inputInt('id');   // Pago elegido
        $pago = (new Pago())->detalle($id) ?? $this->notFound('Pago no encontrado');   // Busca el pago o muestra "no encontrado"
        if ((new Pago())->revertir($id)) {   // Si se pudo revertir (vuelve a pendiente)...
            Bitacora::registrar('PAGO_REVERTIDO', "{$pago['nro_recibo']} chofer #{$pago['id_chofer']} " . bs($pago['monto']));   // ...registra en la bitácora quién y cuál
            flash('success', "Pago {$pago['nro_recibo']} revertido; la cuota volvió a estado pendiente.");   // ...y avisa
        }
        $this->redirect('choferes/ver', ['id' => $pago['id_chofer']]);   // Vuelve al expediente del chofer
    }
}
