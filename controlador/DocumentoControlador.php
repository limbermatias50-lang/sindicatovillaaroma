<?php
/**
 * CONTROLADOR del resguardo documental y de las alertas de vencimiento de licencias.
 */
class DocumentoControlador extends Controlador   // Controlador del resguardo documental y de las alertas de licencias
{
    /* ==================== Documento ==================== */

    public function index(): void   // Lista de documentos y formulario para subir uno nuevo
    {
        $tipo   = (string) $this->input('tipo', '');   // Tipo elegido en el filtro (expediente, licencia, acta...)
        $buscar = (string) $this->input('buscar', '');   // Texto buscado

        $this->view('documentos', [   // Muestra documentos.php con:
            'titulo'     => 'Resguardo documental',   // título
            'documentos' => (new Expediente())->listar($tipo, $buscar),   // documentos que cumplen el filtro
            'choferes'   => (new Chofer())->listar('', 0, 'activo'),   // choferes activos para el formulario
            'filtro'     => compact('tipo', 'buscar'),   // filtros actuales
            'idChofer'   => $this->inputInt('chofer'),   // chofer ya elegido (si viene de su expediente)
        ]);
        limpiar_old();   // Borra los datos de un error anterior
    }

    public function subir(): void   // Sube un documento escaneado
    {
        $this->requirePost();   // Solo POST con token CSRF
        $tipo     = (string) $this->input('tipo');   // Tipo de documento
        $idChofer = $this->inputInt('id_chofer');   // Chofer al que pertenece (0 = documento del sindicato)
        $volver   = $this->input('volver') === 'chofer' && $idChofer ? ['choferes/ver', ['id' => $idChofer]] : ['documentos', []];   // A dónde volver: al expediente del chofer o a la lista de documentos

        $v = (new Validator($_POST))   // Reglas de validación:
            ->requerido('tipo', 'El tipo de documento')->en('tipo', array_keys(Expediente::TIPOS), 'El tipo de documento')   // Tipo obligatorio y de la lista permitida
            ->requerido('titulo', 'El título')->maximo('titulo', 150, 'El título')   // Título obligatorio, máximo 150
            ->maximo('descripcion', 255, 'La descripción')   // Descripción de máximo 255
            ->fecha('fecha_documento', 'La fecha del documento')   // Fecha del documento válida
            ->fechaEntre('fecha_documento', '1950-01-01', date('Y-m-d'), 'La fecha del documento no puede ser posterior a hoy.')   // No puede ser una fecha futura
            ->fecha('fecha_vencimiento', 'La fecha de vencimiento')   // Fecha de vencimiento válida
            ->fechaEntre('fecha_vencimiento', $this->input('fecha_documento') ?: '1950-01-01', '2100-12-31',   // No puede vencer antes de la fecha del documento
                'El vencimiento no puede ser anterior a la fecha del documento.');
        if ($tipo !== 'acta' && $tipo !== 'otro' && $idChofer === 0) {   // Si no es acta ni otro y no se eligió chofer...
            $v->agregar('id_chofer', 'Seleccione el chofer al que pertenece el documento.');   // ...error: debe elegir al chofer
        }
        // Tabla 25: al digitalizar la licencia se registran sus datos y su fecha de vencimiento
        if ($tipo === 'licencia') {   // Si es una licencia...
            $v->requerido('categoria', 'La categoría de la licencia')->en('categoria', Licencia::CATEGORIAS, 'La categoría de la licencia')   // ...la categoría es obligatoria y debe ser A, B, C, P, M o T
              ->requerido('fecha_vencimiento', 'La fecha de vencimiento de la licencia');   // ...y la fecha de vencimiento es obligatoria (para las alertas)
        }
        if ($v->falla()) {   // Si hubo errores...
            $this->conErrores($v->errores(), ...$volver);   // ...vuelve mostrándolos
        }

        try {   // Intenta guardar el archivo...
            $archivo = Upload::guardar($_FILES['archivo'] ?? [], $tipo === 'acta' ? 'actas' : $tipo . 's');   // ...en su carpeta (actas, licencias, expedientes...) validando el tipo real
        } catch (RuntimeException $e) {   // Si el archivo no es válido (ej. un .exe)...
            $this->conErrores([$e->getMessage()], ...$volver);   // CP05: formato no permitido
        }

        $id = (new Expediente())->insertar([   // Guarda el registro del documento en la base de datos:
            'id_chofer'         => $idChofer ?: null,   // chofer (o NULL si es del sindicato)
            'tipo'              => $tipo,   // tipo
            'titulo'            => $this->input('titulo'),   // título
            'descripcion'       => $this->input('descripcion') ?: null,   // descripción
            'fecha_documento'   => $this->input('fecha_documento') ?: null,   // fecha del documento
            'fecha_vencimiento' => $this->input('fecha_vencimiento') ?: null,   // fecha de vencimiento
            'ruta_archivo'      => $archivo['ruta'],   // ruta del archivo guardado
            'nombre_original'   => $archivo['nombre_original'],   // nombre original del archivo
            'id_usuario'        => Auth::id(),   // usuario que lo subió
        ]);
        Bitacora::registrar('DOCUMENTO_SUBIDO', "#$id $tipo: " . $this->input('titulo'));   // Registra en la bitácora

        if ($tipo === 'licencia' && ($chofer = (new Chofer())->buscar($idChofer))) {   // Si es una licencia y el chofer existe...
            (new Licencia())->guardar($idChofer, $chofer['ci'], (string) $this->input('categoria'),   // ...guarda también los datos de la licencia (número = C.I.)...
                $this->input('fecha_documento') ?: null, (string) $this->input('fecha_vencimiento'));   // ...con su emisión y vencimiento
            Bitacora::registrar('LICENCIA_ACTUALIZADA', "Chofer #$idChofer vence " . $this->input('fecha_vencimiento'));   // Registra en la bitácora
            flash('success', 'Licencia registrada: el sistema avisará antes de su vencimiento.');   // Avisa que se registró la licencia
        }
        flash('success', 'Documento digitalizado y resguardado correctamente.');   // Mensaje de éxito
        $this->redirect(...$volver);   // Vuelve a la pantalla de origen
    }

    /** Muestra el archivo solo a usuarios autenticados (los archivos no están en /public). */
    public function ver(): void   // Muestra el archivo de un documento (solo usuarios con sesión)
    {
        $doc  = (new Expediente())->buscar($this->inputInt('id')) ?? $this->notFound('Documento no encontrado');   // Busca el documento o muestra "no encontrado"
        $ruta = realpath(UPLOAD_PATH . '/' . $doc['ruta_archivo']);   // Ruta real del archivo
        if (!$ruta || !str_starts_with($ruta, realpath(UPLOAD_PATH)) || !is_file($ruta)) {   // Si no existe o está fuera de archivos/...
            $this->notFound('El archivo no se encuentra en el servidor.');   // ...muestra "no encontrado"
        }
        $descargar = $this->input('descargar') === '1';   // ¿Se pidió descargar en vez de ver?
        header('Content-Type: ' . Upload::mime($doc['ruta_archivo']));   // Tipo del archivo (PDF o imagen)
        header('Content-Length: ' . filesize($ruta));   // Tamaño del archivo
        header('Content-Disposition: ' . ($descargar ? 'attachment' : 'inline') . '; filename="' . rawurlencode($doc['nombre_original']) . '"');   // inline = se ve en el navegador; attachment = se descarga
        header('Cache-Control: private, max-age=0');   // No se guarda en la caché de otros equipos
        readfile($ruta);   // Envía el archivo
        exit;   // Termina
    }

    /** Modifica los datos de un documento y, si se adjunta, reemplaza el archivo digitalizado. */
    public function actualizar(): void   // Modifica los datos de un documento (y opcionalmente su archivo)
    {
        $this->requirePost();   // Solo POST con token CSRF
        $modelo = new Expediente();   // Modelo de documentos
        $doc    = $modelo->buscar($this->inputInt('id')) ?? $this->notFound('Documento no encontrado');   // Busca el documento o muestra "no encontrado"

        $v = (new Validator($_POST))   // Reglas de validación:
            ->requerido('titulo', 'El título')->maximo('titulo', 150, 'El título')   // Título obligatorio, máximo 150
            ->maximo('descripcion', 255, 'La descripción')   // Descripción de máximo 255
            ->fecha('fecha_vencimiento', 'La fecha de vencimiento');   // Fecha de vencimiento válida
        if ($v->falla()) {   // Si hubo errores...
            $this->conErrores($v->errores(), 'documentos');   // ...vuelve mostrándolos
        }

        $datos = [   // Datos a actualizar:
            'titulo'            => $this->input('titulo'),   // título
            'descripcion'       => $this->input('descripcion') ?: null,   // descripción (o NULL)
            'fecha_vencimiento' => $this->input('fecha_vencimiento') ?: null,   // vencimiento (o NULL)
        ];
        $nuevo = $_FILES['archivo'] ?? [];   // Archivo nuevo (si se eligió)
        if (!empty($nuevo['name'])) {   // Si se eligió un archivo nuevo...
            try {   // ...intenta guardarlo
                $archivo = Upload::guardar($nuevo, $doc['tipo'] === 'acta' ? 'actas' : $doc['tipo'] . 's');   // ...en la misma carpeta según el tipo
            } catch (RuntimeException $e) {   // Si no es válido...
                $this->conErrores([$e->getMessage()], 'documentos');   // ...vuelve con el error
            }
            $datos['ruta_archivo']    = $archivo['ruta'];   // Guarda la ruta nueva
            $datos['nombre_original'] = $archivo['nombre_original'];   // Guarda el nombre original nuevo
        }
        $modelo->actualizar((int) $doc['id_expediente'], $datos);   // Actualiza en la base de datos
        if (isset($datos['ruta_archivo'])) {   // Si se reemplazó el archivo...
            Upload::eliminar($doc['ruta_archivo']);   // ...borra el archivo anterior
        }
        Bitacora::registrar('DOCUMENTO_MODIFICADO', "#{$doc['id_expediente']} " . $datos['titulo']);   // Registra en la bitácora
        flash('success', 'Documento modificado correctamente.');   // Mensaje de éxito
        $this->back();   // Vuelve a la pantalla anterior
    }

    public function eliminar(): void   // Elimina un documento
    {
        $this->requirePost();   // Solo POST con token CSRF
        $modelo = new Expediente();   // Modelo de documentos
        $doc    = $modelo->buscar($this->inputInt('id')) ?? $this->notFound('Documento no encontrado');   // Busca el documento o muestra "no encontrado"
        $modelo->eliminar((int) $doc['id_expediente']);   // Borra el registro de la base de datos
        Upload::eliminar($doc['ruta_archivo']);   // Borra el archivo del servidor
        Bitacora::registrar('DOCUMENTO_ELIMINADO', "#{$doc['id_expediente']} {$doc['titulo']}");   // Registra en la bitácora
        flash('success', 'Documento eliminado.');   // Mensaje de éxito
        $this->back();   // Vuelve a la pantalla anterior
    }

    /** Registra o renueva la licencia de conducir del chofer. */
    public function licencia(): void   // Registra o renueva la licencia de conducir desde el expediente
    {
        $this->requirePost();   // Solo POST con token CSRF
        $idChofer = $this->inputInt('id_chofer');   // Chofer elegido
        $chofer = (new Chofer())->buscar($idChofer) ?? $this->notFound('Chofer no encontrado');   // Busca al chofer o muestra "no encontrado"

        // En Bolivia el N° de licencia es el mismo número del C.I. del titular
        $hoy = date('Y-m-d');   // Fecha de hoy
        $v = (new Validator($_POST))   // Reglas de validación:
            ->requerido('categoria', 'La categoría')->en('categoria', Licencia::CATEGORIAS, 'La categoría')   // Categoría obligatoria y de la lista
            ->fecha('fecha_emision', 'La fecha de emisión')   // Fecha de emisión válida
            ->fechaEntre('fecha_emision', '1990-01-01', $hoy, 'La fecha de emisión no puede ser posterior a hoy.')   // La emisión no puede ser futura
            ->requerido('fecha_vencimiento', 'La fecha de vencimiento')->fecha('fecha_vencimiento', 'La fecha de vencimiento')   // Vencimiento obligatorio y válido
            ->fechaEntre('fecha_vencimiento', max($this->input('fecha_emision') ?: '1990-01-01', '1990-01-01'),   // Debe ser después de la emisión...
                date('Y-m-d', strtotime('+10 years')), 'El vencimiento debe ser posterior a la emisión y no mayor a 10 años desde hoy.');   // ...y no más de 10 años desde hoy
        if ($v->falla()) {   // Si hubo errores...
            $this->conErrores($v->errores(), 'choferes/ver', ['id' => $idChofer]);   // ...vuelve al expediente mostrándolos
        }

        (new Licencia())->guardar(   // Guarda o actualiza la licencia con:
            $idChofer,   // el chofer
            $chofer['ci'],   // número de licencia = su C.I.
            (string) $this->input('categoria'),   // la categoría
            $this->input('fecha_emision') ?: null,   // la fecha de emisión (o NULL)
            (string) $this->input('fecha_vencimiento')   // la fecha de vencimiento
        );
        Bitacora::registrar('LICENCIA_ACTUALIZADA', "Chofer #$idChofer vence " . $this->input('fecha_vencimiento'));   // Registra en la bitácora
        flash('success', 'Licencia de conducir actualizada.');   // Mensaje de éxito
        $this->redirect('choferes/ver', ['id' => $idChofer]);   // Vuelve al expediente
    }

    /** CU08 — Alertas de licencias vencidas o por vencer, filtradas por grupo (Figura 29, Tabla 26). */
    public function alertas(): void   // Alertas de licencias vencidas o por vencer
    {
        $dias     = DIAS_ALERTA_LICENCIA;   // 30 días de anticipación
        $idGrupo  = $this->inputInt('grupo');   // Grupo elegido en el filtro
        $licencia = new Licencia();   // Modelo de licencias
        $lista    = $licencia->alertas($dias, $idGrupo);   // Licencias vencidas o que vencen en 30 días, ordenadas por fecha

        if ($this->input('pdf') === '1') {   // Si se pidió "Exportar lista"...
            Bitacora::registrar('REPORTE_PDF', 'Alertas de vencimiento de licencias');   // Registra en la bitácora
            (new ReportePdf('Alertas de vencimiento de licencias', "Vencidas y por vencer en $dias días — corte al " . date('d/m/Y')))   // Crea el PDF con título y fecha de corte
                ->tabla(   // Agrega una tabla con:
                    ['Chofer' => 55, 'Grupo' => 25, 'Nro. licencia' => 28, 'Vence' => 24, 'Días' => 16, 'Estado' => 32],   // las columnas y su ancho en milímetros
                    array_map(fn($l) => [$l['chofer'], $l['grupo'], $l['numero'], fecha($l['fecha_vencimiento']), $l['dias_restantes'], estado_licencia($l['fecha_vencimiento'])['texto']], $lista)   // una fila por licencia
                )
                ->salida('alertas_licencias.pdf');   // Envía el PDF al navegador
        }

        $this->view('alertas', [   // Muestra alertas.php con:
            'titulo'      => 'Alertas de vencimiento de licencias',   // título
            'licencias'   => $lista,   // licencias vencidas y por vencer
            'documentos'  => (new Expediente())->porVencer($dias),   // otros documentos por vencer
            'sinLicencia' => $licencia->choferesSinLicencia(),   // choferes sin licencia registrada
            'grupos'      => (new Grupo())->activos(),   // grupos para el filtro
            'grupo'       => $idGrupo,   // grupo elegido
            'dias'        => $dias,   // días de anticipación
        ]);
    }
}
