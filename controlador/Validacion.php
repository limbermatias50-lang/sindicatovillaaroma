<?php
/**
 * VALIDACIÓN de datos (reglas para Bolivia) y carga segura de archivos.
 *  1. Bolivia:   reglas de C.I., nombres, celular, etc. (un solo lugar para todo el sistema).
 *  2. Validator: revisa los datos de los formularios en el servidor.
 *  3. Upload:    guarda los documentos y fotos validando su tipo real.
 */

/* ======================= 1. REGLAS PARA BOLIVIA ======================= */
/**
 * REGLAS DE VALIDACIÓN PARA BOLIVIA — un solo lugar para todo el sistema.
 *
 * Cada regla tiene dos versiones de la misma expresión:
 *   - PHP  (validación en el servidor, obligatoria):  Bolivia::CI_NUMERO
 *   - HTML (aviso inmediato en el formulario):        Bolivia::html('CI_NUMERO')
 * Si una regla cambia (por ejemplo, el celular pasa a 9 dígitos), se cambia SOLO aquí.
 */
final class Bolivia   // Clase Bolivia: todas las reglas de datos bolivianos en un solo lugar
{
    /** C.I.: de 4 a 8 dígitos, sin ceros a la izquierda (ej. 4876512). */
    public const CI_NUMERO = '/^[1-9][0-9]{3,7}$/';   // C.I.: de 4 a 8 dígitos y no empieza con 0

    /** Complemento del C.I. para homónimos: un dígito y una letra (ej. 1A). Es opcional. */
    public const CI_COMPLEMENTO = '/^[0-9][A-Z]$/';   // Complemento: un número y una letra (ej. 1A)

    /** Departamento donde se expidió el C.I. */
    public const DEPARTAMENTOS = [   // Departamentos donde se expide el carnet (sigla => nombre)
        'LP' => 'La Paz',  'CB' => 'Cochabamba', 'SC' => 'Santa Cruz',
        'OR' => 'Oruro',   'PT' => 'Potosí',     'CH' => 'Chuquisaca',
        'TJ' => 'Tarija',  'BE' => 'Beni',       'PD' => 'Pando',
    ];

    /** Nombres y apellidos: solo letras (con tildes, ñ y ü), separadas por un espacio, apóstrofo o guion. */
    public const NOMBRE = "/^[A-Za-zÁÉÍÓÚÜÑáéíóúüñ]+(?:[ '\\-][A-Za-zÁÉÍÓÚÜÑáéíóúüñ]+)*$/u";   // Nombres: solo letras con tildes y ñ, separadas por espacio, apóstrofo o guion

    /** Celular: exactamente 8 dígitos. */
    public const CELULAR = '/^[0-9]{8}$/';   // Celular: exactamente 8 dígitos


    /** Color o marca: letras y espacios. */
    public const TEXTO_LETRAS = "/^[A-Za-zÁÉÍÓÚÜÑáéíóúüñ ]+$/u";   // Solo letras y espacios

    /** Fecha de fundación del sindicato (logo: "Fundado el 1 de diciembre de 2012"). */
    public const FUNDACION = '2012-12-01';   // Fecha de fundación del sindicato (01/12/2012)

    /** Edad mínima para conducir y edad máxima razonable. */
    public const EDAD_MINIMA = 18;   // Edad mínima del chofer
    public const EDAD_MAXIMA = 90;   // Edad máxima razonable

    /** Expresión para el atributo pattern de HTML (sin delimitadores ni banderas). */
    public static function html(string $regla): string   // Convierte una regla PHP al formato del atributo pattern de HTML
    {
        $regex = constant(self::class . '::' . $regla);   // Lee la constante por su nombre (ej. 'CELULAR')
        return e(preg_replace('/^\/\^?|\$?\/[a-z]*$/', '', $regex));   // Le quita las barras y los símbolos que HTML no usa
    }

    /** Une número y complemento como se guarda en la base: 4876512 o 4876512-1A. */
    public static function ci(string $numero, string $complemento = ''): string   // Une número y complemento: 4876512 o 4876512-1A
    {
        return $complemento !== '' ? "$numero-$complemento" : $numero;   // Si hay complemento lo agrega con guion
    }

    /** Separa el C.I. guardado en [número, complemento]. */
    public static function partesCi(?string $ci): array   // Separa el C.I. guardado en número y complemento
    {
        $partes = explode('-', (string) $ci, 2);   // Corta en el guion (máximo 2 partes)
        return [$partes[0], $partes[1] ?? ''];   // Devuelve [número, complemento]
    }

    /** "  juan   CARLOS " => "Juan Carlos" */
    public static function normalizarNombre(string $texto): string   // Ordena un nombre: "  juan   CARLOS " => "Juan Carlos"
    {
        $texto = preg_replace('/\s+/u', ' ', trim($texto));   // Quita espacios repetidos y los de los extremos
        return mb_convert_case(mb_strtolower($texto), MB_CASE_TITLE, 'UTF-8');   // Pone mayúscula al inicio de cada palabra
    }


    /** Solo dígitos: "7123-4567" => "71234567" */
    public static function soloDigitos(string $texto): string   // Deja solo los números: "7123-4567" => "71234567"
    {
        return preg_replace('/\D/', '', $texto);   // Borra todo lo que no sea dígito
    }
}

/* ======================= 2. VALIDADOR ======================= */
/**
 * Validación sencilla del lado del servidor.
 *
 *   $v = new Validator($_POST);
 *   $v->requerido('ci', 'C.I.')->maximo('ci', 20, 'C.I.')->fecha('fecha_afiliacion', 'Fecha de afiliación');
 *   if ($v->falla()) { ... $v->errores() ... }
 */
class Validator   // Clase Validator: revisa los datos de un formulario en el servidor
{
    private array $errores = [];   // Aquí se juntan los errores encontrados (campo => mensaje)

    public function __construct(private array $datos) {}   // Recibe los datos del formulario ($_POST) al crearse

    private function valor(string $campo): string   // Lee el valor de un campo sin espacios a los lados
    {
        return trim((string) ($this->datos[$campo] ?? ''));   // Si el campo no existe, devuelve texto vacío
    }

    public function requerido(string $campo, string $etiqueta): self   // Regla: el campo no puede estar vacío
    {
        if ($this->valor($campo) === '') {   // Si está vacío...
            $this->errores[$campo] = "$etiqueta es obligatorio.";   // ...guarda el error "es obligatorio"
        }
        return $this;   // Devuelve el mismo validador para encadenar más reglas
    }

    public function maximo(string $campo, int $max, string $etiqueta): self   // Regla: largo máximo de caracteres
    {
        if (mb_strlen($this->valor($campo)) > $max) {   // Si tiene más letras de las permitidas...
            $this->errores[$campo] ??= "$etiqueta no debe superar $max caracteres.";   // ...guarda el error (??= solo si ese campo no tenía ya otro error)
        }
        return $this;   // Permite encadenar
    }

    public function minimo(string $campo, int $min, string $etiqueta): self   // Regla: largo mínimo de caracteres
    {
        $v = $this->valor($campo);   // Lee el valor
        if ($v !== '' && mb_strlen($v) < $min) {   // Si escribió algo pero es muy corto...
            $this->errores[$campo] ??= "$etiqueta debe tener al menos $min caracteres.";   // ...guarda el error
        }
        return $this;   // Permite encadenar
    }

    public function numeroPositivo(string $campo, string $etiqueta): self   // Regla: número mayor a cero (montos)
    {
        $v = $this->valor($campo);   // Lee el valor
        if ($v !== '' && (!is_numeric($v) || (float) $v <= 0)) {   // Si no es número o es 0 o negativo...
            $this->errores[$campo] ??= "$etiqueta debe ser un número mayor a cero.";   // ...guarda el error
        }
        return $this;   // Permite encadenar
    }

    public function fecha(string $campo, string $etiqueta): self   // Regla: fecha válida con formato año-mes-día
    {
        $v = $this->valor($campo);   // Lee el valor
        if ($v !== '') {   // Solo revisa si se escribió algo
            $d = DateTime::createFromFormat('Y-m-d', $v);   // Intenta leerlo como fecha
            if (!$d || $d->format('Y-m-d') !== $v) {   // Si no es una fecha real (ej. 31/02)...
                $this->errores[$campo] ??= "$etiqueta no es una fecha válida.";   // ...guarda el error
            }
        }
        return $this;   // Permite encadenar
    }

    public function en(string $campo, array $permitidos, string $etiqueta): self   // Regla: el valor debe estar en una lista permitida
    {
        $v = $this->valor($campo);   // Lee el valor
        if ($v !== '' && !in_array($v, $permitidos, true)) {   // Si no está en la lista (comparación exacta)...
            $this->errores[$campo] ??= "$etiqueta no es válido.";   // ...guarda el error
        }
        return $this;   // Permite encadenar
    }

    public function patron(string $campo, string $regex, string $mensaje): self   // Regla: el valor debe cumplir una expresión regular
    {
        $v = $this->valor($campo);   // Lee el valor
        if ($v !== '' && !preg_match($regex, $v)) {   // Si escribió algo y no cumple el patrón...
            $this->errores[$campo] ??= $mensaje;   // ...guarda el mensaje indicado
        }
        return $this;   // Permite encadenar
    }

    /* ---------- Reglas bolivianas (definidas en controlador/Validacion.php) ---------- */

    /** Nombres y apellidos: solo letras. */
    public function nombre(string $campo, string $etiqueta): self   // Regla para nombres y apellidos
    {
        return $this->minimo($campo, 2, $etiqueta)   // Mínimo 2 letras...
            ->patron($campo, Bolivia::NOMBRE, "$etiqueta: escriba solo letras (sin números ni símbolos).");   // ...y solo letras (regla Bolivia::NOMBRE)
    }

    /** Celular: exactamente 8 dígitos. */
    public function celular(string $campo, string $etiqueta = 'El celular'): self   // Regla para el celular
    {
        return $this->patron($campo, Bolivia::CELULAR, "$etiqueta debe tener exactamente 8 dígitos (ej. 71234567).");   // Exactamente 8 dígitos (regla Bolivia::CELULAR)
    }

    /** Fecha dentro de un rango (formato Y-m-d). */
    public function fechaEntre(string $campo, string $min, string $max, string $mensaje): self   // Regla: fecha entre un mínimo y un máximo
    {
        $v = $this->valor($campo);   // Lee el valor
        if ($v !== '' && !isset($this->errores[$campo]) && ($v < $min || $v > $max)) {   // Si escribió algo, no tiene otro error y está fuera del rango...
            $this->errores[$campo] = $mensaje;   // ...guarda el mensaje
        }
        return $this;   // Permite encadenar
    }

    /** Número dentro de un rango. */
    public function entre(string $campo, float $min, float $max, string $mensaje): self   // Regla: número entre un mínimo y un máximo
    {
        $v = $this->valor($campo);   // Lee el valor
        if ($v !== '' && !isset($this->errores[$campo]) && (!is_numeric($v) || (float) $v < $min || (float) $v > $max)) {   // Si no es número o está fuera del rango...
            $this->errores[$campo] = $mensaje;   // ...guarda el mensaje
        }
        return $this;   // Permite encadenar
    }

    public function agregar(string $campo, string $mensaje): self   // Agrega un error calculado en el controlador (ej. C.I. repetido)
    {
        $this->errores[$campo] ??= $mensaje;   // Lo guarda si el campo no tenía ya un error
        return $this;   // Permite encadenar
    }

    public function falla(): bool   // ¿Hubo algún error?
    {
        return !empty($this->errores);   // Verdadero si la lista de errores no está vacía
    }

    public function errores(): array   // Devuelve los mensajes de error
    {
        return array_values($this->errores);   // Solo los textos, sin los nombres de los campos
    }
}

/* ======================= 3. CARGA DE ARCHIVOS ======================= */
/**
 * Carga segura de archivos digitalizados (RF05, caso de prueba CP05).
 * Valida tamaño, extensión y tipo MIME real; genera nombres aleatorios
 * y guarda fuera de la carpeta pública.
 */
class Upload   // Clase Upload: guarda los archivos subidos de forma segura
{
    /**
     * @return array{ruta:string, nombre_original:string}
     * @throws RuntimeException con mensaje legible para el usuario
     */
    public static function guardar(array $archivo, string $subcarpeta, ?array $tipos = null): array   // Guarda un archivo y devuelve su ruta y su nombre original
    {
        $tipos ??= UPLOAD_TIPOS;   // Si no se indican tipos permitidos, usa PDF, JPG y PNG
        if (($archivo['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {   // Si PHP informó un error al recibir el archivo...
            throw new RuntimeException(match ($archivo['error'] ?? UPLOAD_ERR_NO_FILE) {   // ...lanza un error con un mensaje claro según el caso:
                UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'El archivo supera el tamaño permitido.',   // El archivo pasó el límite de PHP
                UPLOAD_ERR_NO_FILE => 'Debe seleccionar un archivo.',   // No se eligió ningún archivo
                default => 'No se pudo cargar el archivo.',   // Cualquier otro problema
            });
        }
        if ($archivo['size'] > UPLOAD_MAX_BYTES) {   // Si el archivo pesa más de 100 MB...
            throw new RuntimeException('El archivo supera el máximo de ' . (UPLOAD_MAX_BYTES / 1048576) . ' MB.');   // ...lo rechaza
        }

        $ext  = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));   // Extensión del archivo en minúsculas (pdf, jpg, png)
        $mime = self::tipoReal($archivo['tmp_name']);   // Tipo REAL del archivo leyendo su contenido (no se fía del nombre)

        if (!isset($tipos[$ext]) || $tipos[$ext] !== $mime) {   // Si la extensión no está permitida o no coincide con el contenido real...
            throw new RuntimeException('Formato no permitido. Solo se aceptan: ' . strtoupper(implode(', ', array_keys($tipos))) . '.');   // ...lo rechaza (así un .exe renombrado a .pdf no pasa)
        }

        $subcarpeta = preg_replace('/[^a-z]/', '', $subcarpeta);   // Limpia el nombre de la subcarpeta (solo letras minúsculas)
        $destinoDir = UPLOAD_PATH . '/' . $subcarpeta;   // Carpeta destino dentro de archivos/
        if (!is_dir($destinoDir)) {   // Si la carpeta no existe...
            mkdir($destinoDir, 0775, true);   // ...la crea
        }

        $nombre = date('Ymd_His') . '_' . bin2hex(random_bytes(8)) . '.' . $ext;   // Nombre nuevo aleatorio: fecha + 16 caracteres al azar (no se puede adivinar)
        if (!move_uploaded_file($archivo['tmp_name'], $destinoDir . '/' . $nombre)) {   // Mueve el archivo de la carpeta temporal a archivos/...
            throw new RuntimeException('No se pudo guardar el archivo en el servidor.');   // ...si falla, avisa
        }

        return [   // Devuelve:
            'ruta'            => $subcarpeta . '/' . $nombre,   // la ruta donde quedó guardado
            'nombre_original' => mb_substr(basename($archivo['name']), 0, 150),   // y el nombre original (para mostrarlo y al descargar)
        ];
    }

    /**
     * Tipo real del archivo según sus primeros bytes (firma), sin depender de la
     * extensión fileinfo, que en algunas instalaciones de XAMPP viene desactivada.
     */
    private static function tipoReal(string $ruta): string   // Averigua el tipo real del archivo por sus primeros bytes
    {
        if (class_exists('finfo')) {   // Si PHP tiene la extensión fileinfo...
            return (string) (new finfo(FILEINFO_MIME_TYPE))->file($ruta);   // ...la usa para leer el tipo real
        }
        $cabecera = (string) file_get_contents($ruta, false, null, 0, 8);   // Si no la tiene, lee los primeros 8 bytes del archivo
        return match (true) {   // y compara con las firmas conocidas:
            str_starts_with($cabecera, '%PDF')              => 'application/pdf',   // los PDF empiezan con %PDF
            str_starts_with($cabecera, "\x89PNG\r\n\x1a\n") => 'image/png',   // los PNG empiezan con esta firma
            str_starts_with($cabecera, "\xFF\xD8\xFF")       => 'image/jpeg',   // los JPG empiezan con FF D8 FF
            default                                         => 'desconocido',   // cualquier otra cosa es desconocida (se rechaza)
        };
    }

    /** Tipo MIME de un archivo guardado, según su extensión (ya validada al subirlo). */
    public static function mime(string $rutaRelativa): string   // Tipo MIME de un archivo guardado según su extensión
    {
        $ext = strtolower(pathinfo($rutaRelativa, PATHINFO_EXTENSION));   // Extensión del archivo
        return UPLOAD_TIPOS[$ext] ?? 'application/octet-stream';   // Tipo correspondiente (para mostrarlo en el navegador)
    }

    public static function eliminar(string $rutaRelativa): void   // Borra un archivo guardado
    {
        $ruta = realpath(UPLOAD_PATH . '/' . $rutaRelativa);   // Ruta real y completa del archivo
        if ($ruta && str_starts_with($ruta, realpath(UPLOAD_PATH)) && is_file($ruta)) {   // Solo borra si está dentro de archivos/ y existe (evita borrar otros archivos)
            unlink($ruta);   // Elimina el archivo
        }
    }
}
