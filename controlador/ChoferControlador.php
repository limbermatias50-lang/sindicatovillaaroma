<?php
/**
 * CONTROLADOR de choferes (registro, QR, ventanilla, hoja de datos, credencial) y grupos de transporte.
 */
class ChoferControlador extends Controlador   // Controlador de choferes: registro, QR, ventanilla, hoja de datos, credencial y grupos
{
    /* ==================== Chofer ==================== */

    private Chofer $choferes;   // Aquí se guarda el modelo Chofer para usarlo en todos los métodos

    /** Campos del formulario que se guardan en la tabla chofer (lista blanca). */
    private const CAMPOS = ['ci_expedido', 'nombres', 'apellidos', 'telefono', 'direccion', 'fecha_nacimiento', 'fecha_afiliacion', 'id_grupo', 'estado'];   // Campos del formulario que se pueden guardar (lista blanca: nada más entra a la tabla)

    public function __construct()   // Se ejecuta al crear el controlador
    {
        $this->choferes = new Chofer();   // Crea el modelo de choferes
    }

    public function index(): void   // Lista de choferes con filtros
    {
        $buscar = (string) $this->input('buscar', '');   // Texto buscado (nombre, C.I. o código QR)
        $grupo  = $this->inputInt('grupo');   // Grupo elegido en el filtro
        $estado = (string) $this->input('estado', '');   // Estado elegido en el filtro

        $this->view('choferes', [   // Muestra la vista choferes.php con:
            'titulo'   => 'Choferes afiliados',   // título
            'choferes' => $this->choferes->listar($buscar, $grupo, $estado),   // choferes que cumplen los filtros
            'grupos'   => (new Grupo())->activos(),   // grupos para el filtro
            'filtro'   => compact('buscar', 'grupo', 'estado'),   // los filtros actuales
        ]);
    }

    public function crear(): void   // Formulario para registrar un chofer
    {
        $this->view('chofer_form', [   // Muestra chofer_form.php con:
            'titulo' => 'Registrar chofer',   // título
            'chofer' => null,   // null = formulario vacío (chofer nuevo)
            'grupos' => (new Grupo())->activos(),   // grupos para elegir
        ]);
        limpiar_old();   // Borra los datos de un error anterior
    }

    public function guardar(): void   // Guarda un chofer nuevo
    {
        $this->requirePost();   // Solo POST con token CSRF
        $datos = $this->validar();   // Valida todos los datos (si hay error, vuelve al formulario)
        $datos['estado'] = 'activo';   // Todo chofer nuevo empieza activo

        $foto  = $this->subirFoto('choferes/nuevo');   // Guarda la foto si se eligió una
        if ($foto) {   // Si se subió foto...
            $datos['foto'] = $foto;   // ...guarda su ruta en los datos
        }

        $id = $this->choferes->registrar($datos);   // Guarda al chofer, genera su código QR y le asigna las cuotas vigentes de su grupo
        Bitacora::registrar('CHOFER_REGISTRO', "Chofer #$id C.I. {$datos['ci']}");   // Registra en la bitácora
        flash('success', 'Chofer registrado. Revise su hoja de datos personales e imprima su credencial QR.');   // Mensaje de éxito
        $this->redirect('choferes/hoja', ['id' => $id, 'nuevo' => 1]);   // vista previa de la hoja
    }

    public function editar(): void   // Formulario para modificar un chofer
    {
        $chofer = $this->choferes->buscar($this->inputInt('id')) ?? $this->notFound('Chofer no encontrado');   // Busca al chofer o muestra "no encontrado"
        $this->view('chofer_form', [   // Muestra chofer_form.php con:
            'titulo' => 'Editar chofer',   // título
            'chofer' => $chofer,   // los datos actuales del chofer
            'grupos' => (new Grupo())->activos(),   // grupos
        ]);
        limpiar_old();   // Borra los datos de un error anterior
    }

    public function actualizar(): void   // Guarda los cambios de un chofer
    {
        $this->requirePost();   // Solo POST con token CSRF
        $id = $this->inputInt('id_chofer');   // Chofer que se edita
        $actual = $this->choferes->buscar($id) ?? $this->notFound('Chofer no encontrado');   // Datos actuales (para saber si tenía foto)

        $datos = $this->validar($id);   // Valida los datos nuevos
        $foto  = $this->subirFoto('choferes/editar', ['id' => $id]);   // Guarda la foto nueva si se eligió una
        if ($foto) {   // Si hay foto nueva...
            $datos['foto'] = $foto;   // ...guarda su ruta
            if (!empty($actual['foto'])) {   // Si tenía una foto anterior...
                Upload::eliminar($actual['foto']);   // reemplaza la foto anterior
            }
        }
        $this->choferes->actualizar($id, $datos);   // Actualiza los datos en la base de datos
        Bitacora::registrar('CHOFER_EDICION', "Chofer #$id");   // Registra en la bitácora
        flash('success', 'Datos del chofer actualizados.');   // Mensaje de éxito
        $this->redirect('choferes/ver', ['id' => $id]);   // Vuelve al expediente del chofer
    }

    public function ver(): void   // Expediente del chofer
    {
        $id     = $this->inputInt('id');   // Chofer elegido
        $chofer = $this->choferes->detalle($id) ?? $this->notFound('Chofer no encontrado');   // Sus datos con grupo y licencia, o "no encontrado"

        $this->view('chofer_ver', [   // Muestra chofer_ver.php con:
            'titulo'      => 'Expediente del chofer',   // título
            'chofer'      => $chofer,   // datos del chofer
            'documentos'  => (new Expediente())->listar('', '', $id),   // sus documentos digitalizados
            'licencia'    => (new Licencia())->porChofer($id),   // su licencia de conducir
            'pagos'       => (new Pago())->delChofer($id),   // todas sus cuotas (pagadas y pendientes)
            'saldo'       => (new Pago())->saldoChofer($id),   // su saldo (pagado, pendiente y en mora)
        ]);
    }

    /** Vista previa imprimible de la hoja de DATOS PERSONALES (como la ve el personal al escanear el QR). */
    public function hoja(): void   // Vista previa de la hoja de DATOS PERSONALES
    {
        $chofer = $this->choferes->detalle($this->inputInt('id')) ?? $this->notFound('Chofer no encontrado');   // Busca al chofer o muestra "no encontrado"
        $this->view('hoja', [   // Muestra hoja.php sin plantilla (página para imprimir) con:
            'chofer' => $chofer,   // los datos del chofer
            'nuevo'  => $this->input('nuevo') === '1',   // si viene de registrarlo (muestra el aviso de registro)
        ], null);
    }

    /**
     * Foto del chofer. Se pide con el código QR (aleatorio, no adivinable), porque también
     * se muestra en la verificación pública para reconocer al afiliado.
     */
    public function foto(): void   // Entrega la foto del chofer (se pide con su código QR)
    {
        $codigo = strtoupper((string) $this->input('c', ''));   // Código QR recibido en mayúsculas
        $chofer = preg_match('/^VA-[A-Z0-9]{10}$/', $codigo) ? $this->choferes->buscarPorQR($codigo) : null;   // Solo busca si el código tiene el formato VA-XXXXXXXXXX
        $ruta   = $chofer && !empty($chofer['foto']) ? realpath(UPLOAD_PATH . '/' . $chofer['foto']) : false;   // Ruta real de la foto en el servidor
        if (!$ruta || !str_starts_with($ruta, realpath(UPLOAD_PATH)) || !is_file($ruta)) {   // Si no hay foto o la ruta no está dentro de archivos/...
            http_response_code(404);   // ...responde 404
            exit;   // ...y termina
        }
        header('Content-Type: ' . (FOTO_TIPOS[strtolower(pathinfo($ruta, PATHINFO_EXTENSION))] ?? 'image/jpeg'));   // Tipo de imagen (JPG o PNG)
        header('Content-Length: ' . filesize($ruta));   // Tamaño de la imagen
        header('Cache-Control: private, max-age=86400');   // El navegador puede guardarla un día (carga más rápido)
        readfile($ruta);   // Envía la imagen
        exit;   // Termina
    }

    /** Guarda la foto enviada (si la hay) y devuelve su ruta relativa. */
    private function subirFoto(string $volver, array $params = []): ?string   // Guarda la foto subida y devuelve su ruta
    {
        $archivo = $_FILES['foto'] ?? [];   // Archivo enviado en el campo "foto"
        if (($archivo['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {   // Si no se eligió ninguna foto...
            return null;   // ...no hace nada
        }
        try {   // Intenta guardarla...
            return Upload::guardar($archivo, 'fotos', FOTO_TIPOS)['ruta'];   // ...solo como JPG o PNG en archivos/fotos
        } catch (RuntimeException $e) {   // Si el archivo no es válido...
            $this->conErrores(['Foto: ' . $e->getMessage()], $volver, $params);   // ...vuelve al formulario con el error
        }
        return null;   // (no se llega aquí: conErrores ya redirigió)
    }

    /** Credencial imprimible con el código QR generado en el navegador (qrcode-generator). */
    public function credencial(): void   // Credencial QR para imprimir
    {
        $chofer = $this->choferes->detalle($this->inputInt('id')) ?? $this->notFound('Chofer no encontrado');   // Busca al chofer o muestra "no encontrado"
        $this->view('credencial', ['chofer' => $chofer], null);   // Muestra credencial.php sin plantilla
    }

    /** CU03 — Devuelve en JSON el chofer asociado al código QR leído (CP01, CP02). */
    public function buscarQr(): void   // Busca al chofer por código QR o por C.I. (lo usa la ventanilla con JavaScript)
    {
        $codigo = strtoupper(trim((string) $this->input('qr', '')));   // Código leído o escrito, en mayúsculas
        $chofer = null;   // Todavía no se encontró a nadie
        if (preg_match('/^VA-[A-Z0-9]{10}$/', $codigo)) {   // Si tiene formato de código QR (VA-XXXXXXXXXX)...
            $chofer = $this->choferes->buscarPorQR($codigo);   // ...lo busca por código
        } elseif (preg_match('/^\d{4,8}(-[0-9][A-Z])?$/', $codigo)) {   // Si tiene formato de C.I. (con complemento opcional)...
            $chofer = $this->choferes->buscarPorCi($codigo);      // el chofer olvidó su credencial
        }

        if (!$chofer) {   // Si no se encontró...
            $this->json(['ok' => false, 'mensaje' => 'Credencial no encontrada'], 404);   // Tabla 23 · ...responde en JSON "Credencial no encontrada"
        }

        $licencia = estado_licencia($chofer['fecha_vencimiento']);   // Calcula el estado de su licencia
        Bitacora::registrar('LECTURA_QR', "Chofer #{$chofer['id_chofer']}");   // Registra la lectura en la bitácora

        $this->json([   // Responde en JSON con:
            'ok'     => true,   // ok = encontrado
            'chofer' => [   // los datos del chofer:
                'id'        => (int) $chofer['id_chofer'],   // número
                'codigo'    => $chofer['codigo_qr'],   // código QR
                'nombre'    => $chofer['nombres'] . ' ' . $chofer['apellidos'],   // nombre completo
                'ci'        => trim($chofer['ci'] . ' ' . $chofer['ci_expedido']),   // C.I. con su departamento
                'telefono'  => $chofer['telefono'],   // celular
                'grupo'     => $chofer['grupo'],   // grupo
                'estado'    => $chofer['estado'],   // estado (activo, suspendido, inactivo)
                'licencia'  => [   // su licencia:
                    'numero'      => $chofer['licencia_numero'],   // número
                    'vencimiento' => fecha($chofer['fecha_vencimiento']),   // fecha de vencimiento
                    'clase'       => $licencia['clase'],   // color del estado
                    'texto'       => $licencia['texto'],   // texto del estado
                ],
                'url'       => url('choferes/ver', ['id' => $chofer['id_chofer']]),   // dirección de su expediente
                'hoja'      => url('choferes/hoja', ['id' => $chofer['id_chofer']]),   // dirección de su hoja de datos
                'foto'      => !empty($chofer['foto']) ? url('choferes/foto', ['c' => $chofer['codigo_qr']]) : null,   // dirección de su foto (si tiene)
            ],
            'saldo'   => (new Pago())->saldoChofer((int) $chofer['id_chofer']),   // su saldo
            'cuotas'  => array_map(fn($p) => [   // sus cuotas pendientes, cada una con:
                'id_pago'  => (int) $p['id_pago'],   // número del pago
                'concepto' => $p['concepto'],   // concepto
                'tipo'     => $p['tipo'],   // ordinaria o extraordinaria
                'monto'    => (float) $p['monto'],   // monto
                'limite'   => fecha($p['fecha_limite']),   // fecha límite
                'vencida'  => (bool) $p['vencida'],   // si ya venció
            ], (new Pago())->delChofer((int) $chofer['id_chofer'], 'pendiente')),   // (lista de cuotas pendientes del chofer)
            'puedeCobrar' => Auth::tiene('ventanilla'),   // si el usuario puede cobrar
        ]);
    }

    /** Pantalla de atención en ventanilla: lectura QR + cobro (Figura 11). */
    public function ventanilla(): void   // Pantalla de atención en ventanilla
    {
        $this->view('ventanilla', ['titulo' => 'Atención en ventanilla']);   // Muestra ventanilla.php
    }

    public function eliminar(): void   // Elimina un chofer
    {
        $this->requirePost();   // Solo POST con token CSRF
        $id = $this->inputInt('id');   // Chofer elegido
        if ($this->choferes->tienePagos($id)) {   // Si ya tiene pagos cobrados...
            flash('warning', 'El chofer tiene pagos registrados; no se puede eliminar. Cambie su estado a "inactivo".');   // ...no se puede borrar (se perdería el historial de caja)
            $this->redirect('choferes/ver', ['id' => $id]);   // Vuelve a su expediente
        }
        $this->choferes->eliminarCompleto($id);   // Borra al chofer, sus documentos y su foto
        Bitacora::registrar('CHOFER_ELIMINADO', "Chofer #$id");   // Registra en la bitácora
        flash('success', 'Chofer eliminado.');   // Mensaje de éxito
        $this->redirect('choferes');   // Vuelve a la lista
    }

    /** Valida el formulario y devuelve solo los campos permitidos. */
    /**
     * Valida el formulario con las reglas bolivianas (controlador/Validacion.php) y devuelve
     * solo los campos permitidos, ya normalizados.
     */
    private function validar(int $id = 0): array   // Valida el formulario del chofer y devuelve solo los campos permitidos
    {
        // 1) Normalizar lo que escribió el usuario
        $_POST['ci_numero']      = Bolivia::soloDigitos((string) ($_POST['ci_numero'] ?? ''));   // Deja solo los números del C.I.
        $_POST['ci_complemento'] = strtoupper(trim((string) ($_POST['ci_complemento'] ?? '')));   // Complemento en mayúsculas
        $_POST['nombres']        = Bolivia::normalizarNombre((string) ($_POST['nombres'] ?? ''));   // Ordena los nombres ("juan" => "Juan")
        $_POST['apellidos']      = Bolivia::normalizarNombre((string) ($_POST['apellidos'] ?? ''));   // Ordena los apellidos
        $_POST['telefono']       = preg_replace('/[\s\-]/', '', (string) ($_POST['telefono'] ?? ''));   // Quita espacios y guiones del celular

        $hoy = date('Y-m-d');   // Fecha de hoy
        $v = (new Validator($_POST))   // Reglas de validación:
            // C.I.: 4 a 8 dígitos + complemento opcional + departamento
            ->requerido('ci_numero', 'El número de C.I.')   // C.I. obligatorio
            ->patron('ci_numero', Bolivia::CI_NUMERO, 'El C.I. debe tener de 4 a 8 dígitos y no empezar con 0.')   // C.I. de 4 a 8 dígitos
            ->patron('ci_complemento', Bolivia::CI_COMPLEMENTO, 'El complemento debe ser un número y una letra (ej. 1A).')   // Complemento: número + letra
            ->requerido('ci_expedido', 'El departamento de expedición')   // Departamento obligatorio
            ->en('ci_expedido', array_keys(Bolivia::DEPARTAMENTOS), 'El departamento de expedición')   // Departamento de la lista de Bolivia
            // Nombres y apellidos: solo letras
            ->requerido('nombres', 'Los nombres')->maximo('nombres', 80, 'Los nombres')->nombre('nombres', 'Los nombres')   // Nombres: obligatorio, máximo 80, solo letras
            ->requerido('apellidos', 'Los apellidos')->maximo('apellidos', 80, 'Los apellidos')->nombre('apellidos', 'Los apellidos')   // Apellidos: obligatorio, máximo 80, solo letras
            // Celular: 8 dígitos (opcional)
            ->celular('telefono', 'El celular')   // Celular de 8 dígitos
            ->maximo('direccion', 150, 'La dirección')   // Dirección de máximo 150 caracteres
            // Fechas razonables
            ->fecha('fecha_nacimiento', 'La fecha de nacimiento')   // Fecha de nacimiento válida
            ->fechaEntre('fecha_nacimiento',   // Debe estar entre...
                date('Y-m-d', strtotime('-' . Bolivia::EDAD_MAXIMA . ' years')),   // ...hace 90 años...
                date('Y-m-d', strtotime('-' . Bolivia::EDAD_MINIMA . ' years')),   // ...y hace 18 años
                'El chofer debe tener entre ' . Bolivia::EDAD_MINIMA . ' y ' . Bolivia::EDAD_MAXIMA . ' años.')   // (mensaje si no cumple la edad)
            ->requerido('fecha_afiliacion', 'La fecha de afiliación')->fecha('fecha_afiliacion', 'La fecha de afiliación')   // Fecha de afiliación obligatoria y válida
            ->fechaEntre('fecha_afiliacion', Bolivia::FUNDACION, $hoy,   // Entre la fundación del sindicato y hoy
                'La fecha de afiliación debe estar entre la fundación del sindicato (01/12/2012) y hoy.')
            ->requerido('id_grupo', 'El grupo de transporte');   // Grupo obligatorio

        if ($id > 0) {   // Si se está editando...
            $v->en('estado', ['activo', 'suspendido', 'inactivo'], 'El estado');   // ...el estado debe ser uno de los permitidos
        }

        $ci = Bolivia::ci($_POST['ci_numero'], $_POST['ci_complemento']);   // Une número y complemento del C.I.
        if ($_POST['ci_numero'] !== '' && $this->choferes->existeCi($ci, $id)) {   // Si ese C.I. ya lo tiene otro chofer...
            $v->agregar('ci_numero', "Ya existe un chofer registrado con el C.I. $ci.");   // ...error
        }
        if ($v->falla()) {   // Si hubo errores...
            $this->conErrores($v->errores(), $id ? 'choferes/editar' : 'choferes/nuevo', $id ? ['id' => $id] : []);   // ...vuelve al formulario (nuevo o editar) mostrándolos
        }

        $datos = ['ci' => $ci];   // Datos a guardar, empezando por el C.I.
        foreach (self::CAMPOS as $c) {   // Recorre solo los campos permitidos
            if (array_key_exists($c, $_POST)) {   // Si el campo vino en el formulario...
                $valor = trim((string) $_POST[$c]);   // ...lo limpia
                $datos[$c] = $valor === '' ? null : $valor;   // Vacío se guarda como NULL
            }
        }
        $datos['id_grupo'] = (int) $datos['id_grupo'];   // El grupo como número
        return $datos;   // Devuelve los datos listos para guardar
    }

    /* ==================== Grupo ==================== */

    public function grupos(): void   // Lista de grupos de transporte
    {
        $this->view('grupos', [   // Muestra grupos.php con:
            'titulo' => 'Grupos de transporte',   // título
            'grupos' => (new Grupo())->listarConResumen(),   // grupos con cuántos choferes y cuánto deben
        ]);
    }

    public function guardarGrupo(): void   // Guarda un grupo nuevo o modificado
    {
        $this->requirePost();   // Solo POST con token CSRF
        $modelo = new Grupo();   // Modelo de grupos
        $id     = $this->inputInt('id_grupo');   // 0 = nuevo; otro número = editar

        $_POST['delegado'] = Bolivia::normalizarNombre((string) ($_POST['delegado'] ?? ''));   // Ordena el nombre del delegado
        $v = (new Validator($_POST))   // Reglas de validación:
            ->requerido('nombre', 'El nombre')->maximo('nombre', 80, 'El nombre')   // Nombre obligatorio, máximo 80
            ->maximo('ruta', 120, 'La ruta')   // Ruta de máximo 120
            ->maximo('delegado', 100, 'El delegado')->nombre('delegado', 'El nombre del delegado');   // Delegado: máximo 100 y solo letras
        if ($modelo->existeNombre((string) $this->input('nombre'), $id)) {   // Si ya existe otro grupo con ese nombre...
            $v->agregar('nombre', 'Ya existe un grupo con ese nombre.');   // ...error
        }
        if ($v->falla()) {   // Si hubo errores...
            $this->conErrores($v->errores(), 'grupos');   // ...vuelve mostrándolos
        }

        $datos = [   // Datos a guardar:
            'nombre'   => $this->input('nombre'),   // nombre
            'ruta'     => $this->input('ruta') ?: null,   // ruta (o NULL)
            'delegado' => $_POST['delegado'] ?: null,   // delegado (o NULL)
            'activo'   => isset($_POST['activo']) ? 1 : 0,   // activo si se marcó la casilla
        ];
        if ($id > 0) {   // Si se está editando...
            $modelo->actualizar($id, $datos);   // ...actualiza
        } else {
            $datos['activo'] = 1;   // Si es nuevo, siempre empieza activo
            $id = $modelo->insertar($datos);   // ...y se inserta
        }
        Bitacora::registrar('GRUPO_GUARDADO', "Grupo #$id {$datos['nombre']}");   // Registra en la bitácora
        flash('success', 'Grupo guardado correctamente.');   // Mensaje de éxito
        $this->redirect('grupos');   // Vuelve a la lista
    }

    /* ==================== Consulta ==================== */

    /** El código VA-XXXXXXXXXX es aleatorio (32^10 combinaciones) y no se puede adivinar. */
    public function datos(): void   // Página pública que abre el celular al escanear el QR
    {
        $codigo = strtoupper((string) $this->input('c', ''));   // Código QR recibido
        $chofer = preg_match('/^VA-[A-Z0-9]{10}$/', $codigo) ? (new Chofer())->buscarPorQR($codigo) : null;   // Solo busca si el formato es válido
        if (!$chofer) {   // Si no existe...
            http_response_code(404);   // ...responde 404
            $this->view('error', ['mensaje' => 'Credencial no encontrada.', 'titulo' => 'No encontrado'], 'simple');   // ...y muestra "Credencial no encontrada"
            return;   // Termina
        }
        $this->view('datos', ['chofer' => $chofer], null);   // Muestra la hoja de datos (sin sesión oculta C.I., nacimiento y licencia)
    }
}
