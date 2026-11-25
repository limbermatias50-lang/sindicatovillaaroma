<?php
/**
 * CONTROLADOR BASE y funciones comunes del sistema:
 *  1. Funciones de ayuda para las vistas (e, url, bs, fecha, ...).
 *  2. Auth: sesión, roles y cierre por inactividad.
 *  3. Csrf: protección de formularios.
 *  4. Controlador: clase padre de todos los controladores (vistas, JSON, redirecciones, errores).
 */

/* ======================= 1. FUNCIONES DE AYUDA ======================= */
/**
 * Funciones de ayuda globales usadas en controladores y vistas.
 */

/** Escapa texto para mostrarlo en HTML (prevención de XSS). */
function e(mixed $valor): string   // Función e(): prepara un texto para mostrarlo en HTML sin riesgo
{
    return htmlspecialchars((string) ($valor ?? ''), ENT_QUOTES, 'UTF-8');   // Convierte < > " ' & en texto seguro: evita ataques XSS
}

/** Genera una URL interna: url('choferes/ver', ['id' => 5]) => index.php?r=choferes/ver&id=5 */
function url(string $ruta = '', array $params = []): string   // Función url(): arma una dirección interna del sistema
{
    $query = http_build_query(array_merge(['r' => $ruta], $params));   // Junta r=ruta con los demás datos, ej. r=choferes/ver&id=5
    return base_url() . 'index.php?' . $query;   // Devuelve la dirección completa: /sis_villaaroma/index.php?r=...
}

/**
 * URL completa (http://host/...) que puede abrir otro dispositivo, p. ej. un celular
 * al escanear el QR. Si se entra por "localhost" se usa la IP de la PC en la red local,
 * porque "localhost" en el celular apuntaría al propio celular.
 */
function url_publica(string $ruta, array $params = []): string   // Función url_publica(): dirección completa que abre el celular al escanear el QR
{
    $query = 'index.php?' . http_build_query(array_merge(['r' => $ruta], $params));   // Arma la parte index.php?r=...
    if (URL_PUBLICA !== '') {   // Si en index.php se escribió un dominio...
        return rtrim(URL_PUBLICA, '/') . '/' . $query;   // ...usa ese dominio (caso hosting)
    }

    $https  = ($_SERVER['HTTPS'] ?? '') !== '' && $_SERVER['HTTPS'] !== 'off';   // ¿La página se abrió con HTTPS?
    $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';   // Nombre del servidor con el que se entró (ej. localhost:80)
    $nombre = parse_url('http://' . $host, PHP_URL_HOST);   // Separa solo el nombre (sin el puerto)
    $puerto = parse_url('http://' . $host, PHP_URL_PORT);   // Separa solo el puerto

    if (in_array($nombre, ['localhost', '127.0.0.1', '::1', '[::1]'], true) && ($ip = ip_red_local())) {   // Si se entró por localhost y se conoce la IP de la PC en la red...
        $host = $ip . ($puerto ? ":$puerto" : '');   // ...usa la IP (el celular no puede abrir "localhost")
    }
    return ($https ? 'https' : 'http') . '://' . $host . base_url() . $query;   // Devuelve la dirección completa con http o https
}

/** IP de esta PC en la red local (la de la interfaz que sale a internet). */
function ip_red_local(): ?string   // Función ip_red_local(): averigua la IP de esta PC en la red local
{
    // Un socket UDP no envía nada al "conectar"; solo sirve para saber qué interfaz usaría el sistema
    $s = @stream_socket_client('udp://8.8.8.8:53', $errno, $errstr, 1);   // Abre un socket de prueba (no envía datos); @ evita avisos si no hay red
    if (!$s) {   // Si no se pudo abrir...
        return null;   // ...no hay IP disponible
    }
    $local = stream_socket_get_name($s, false);   // Lee la dirección local que usó el socket (IP:puerto)
    fclose($s);   // Cierra el socket
    $ip = $local ? substr($local, 0, strrpos($local, ':')) : '';   // Quita el puerto y deja solo la IP
    return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) && !str_starts_with($ip, '127.') ? $ip : null;   // Devuelve la IP solo si es válida y no es 127.x (la propia PC)
}

/** URL de un recurso estático dentro de /public (css, js, img). */
function asset(string $ruta): string   // Función asset(): dirección de un archivo css, js o imagen
{
    return base_url() . ltrim($ruta, '/');   // Une la carpeta base con la ruta del archivo
}

function base_url(): string   // Función base_url(): carpeta donde está instalado el sistema
{
    static $base = null;   // static: el valor se calcula una sola vez y se recuerda
    if ($base === null) {   // Si todavía no se calculó...
        $dir  = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));   // Carpeta del index.php (ej. /sis_villaaroma)
        $base = rtrim($dir, '/') . '/';   // Le agrega la barra final: /sis_villaaroma/
    }
    return $base;   // Devuelve la carpeta base
}

/** Mensajes flash: se muestran una sola vez con SweetAlert2. */
function flash(string $tipo, string $mensaje, string $titulo = ''): void   // Función flash(): guarda un mensaje para mostrarlo en la siguiente pantalla
{
    $_SESSION['_flash'][] = ['tipo' => $tipo, 'mensaje' => $mensaje, 'titulo' => $titulo];   // Lo guarda en la sesión (tipo: success, error, warning)
}

function flashes(): array   // Función flashes(): entrega los mensajes guardados y los borra
{
    $f = $_SESSION['_flash'] ?? [];   // Toma los mensajes de la sesión (o lista vacía)
    unset($_SESSION['_flash']);   // Los borra para que no se repitan
    return $f;   // Los devuelve a la plantilla, que los muestra con SweetAlert2
}

/** Valor anterior de un campo de formulario (tras un error de validación). */
function old(string $campo, mixed $defecto = ''): string   // Función old(): devuelve lo que el usuario escribió antes de un error
{
    return e($_SESSION['_old'][$campo] ?? $defecto);   // Así el formulario no se vacía si hubo un dato incorrecto
}

function limpiar_old(): void   // Función limpiar_old(): olvida los datos anteriores del formulario
{
    unset($_SESSION['_old']);   // Los borra de la sesión
}

function bs(mixed $monto): string   // Función bs(): da formato de dinero boliviano
{
    return 'Bs ' . number_format((float) $monto, 2, ',', '.');   // Ej. 1234.5 => "Bs 1.234,50"
}

function fecha(?string $fecha, bool $conHora = false): string   // Función fecha(): convierte 2026-11-24 en 24/11/2026
{
    if (!$fecha) {   // Si no hay fecha...
        return '—';   // ...muestra una raya
    }
    return date($conHora ? 'd/m/Y H:i' : 'd/m/Y', strtotime($fecha));   // Da formato día/mes/año (con hora si se pide)
}

/** Clasifica una licencia según los días restantes (RF06). */
function estado_licencia(?string $fechaVenc): array   // Función estado_licencia(): calcula si la licencia está vigente, por vencer o vencida
{
    if (!$fechaVenc) {   // Si el chofer no tiene licencia registrada...
        return ['clase' => 'secondary', 'texto' => 'Sin registrar', 'dias' => null];   // ...la marca en gris como "Sin registrar"
    }
    $dias = (int) floor((strtotime($fechaVenc) - strtotime(date('Y-m-d'))) / 86400);   // Días que faltan para el vencimiento (negativo = ya venció)
    if ($dias < 0) {   // Si es negativo...
        return ['clase' => 'danger', 'texto' => 'Vencida hace ' . abs($dias) . ' día(s)', 'dias' => $dias];   // ...está vencida: color rojo
    }
    if ($dias <= DIAS_ALERTA_LICENCIA) {   // Si faltan 30 días o menos...
        return ['clase' => 'warning', 'texto' => "Vence en $dias día(s)", 'dias' => $dias];   // ...está por vencer: color amarillo
    }
    return ['clase' => 'success', 'texto' => 'Vigente', 'dias' => $dias];   // Si faltan más días: vigente, color verde
}

/** Marca el ítem del menú activo. 'choferes' coincide por prefijo; 'usuarios$' exige coincidencia exacta. */
function es_activa(string $patron): string   // Función es_activa(): marca en el menú la opción de la pantalla actual
{
    $r = $_GET['r'] ?? 'inicio';   // Ruta abierta en este momento
    $ok = str_ends_with($patron, '$') ? $r === rtrim($patron, '$') : str_starts_with($r, $patron);   // Compara la ruta con la opción del menú ($ = debe ser exacta)
    return $ok ? 'active' : '';   // Devuelve 'active' (clase de Bootstrap que la resalta)
}

/** Convierte texto UTF-8 a Windows-1252 para FPDF (tildes y ñ). */
function pdf_txt(mixed $texto): string   // Función pdf_txt(): adapta el texto para FPDF
{
    return iconv('UTF-8', 'windows-1252//TRANSLIT', (string) $texto) ?: '';   // Convierte las tildes y la ñ al formato que entiende FPDF
}

/** Número con formato boliviano (1.234,50) para reportes. */
function num(mixed $valor): string   // Función num(): número con formato boliviano sin "Bs"
{
    return number_format((float) $valor, 2, ',', '.');   // Ej. 1234.5 => "1.234,50"
}

/** "2026-09" → "Septiembre 2026" (periodo de una cuota, tomado de su fecha límite). */
function nombre_periodo(?string $fecha): string   // Función nombre_periodo(): "2026-09" => "Septiembre 2026"
{
    if (!$fecha) {   // Si no hay fecha...
        return '';   // ...devuelve texto vacío
    }
    $meses = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];   // Lista de los nombres de los meses
    $t = strtotime(strlen($fecha) === 7 ? $fecha . '-01' : $fecha);   // Convierte la fecha a una marca de tiempo
    return $t ? $meses[(int) date('n', $t) - 1] . ' ' . date('Y', $t) : '';   // Devuelve el nombre del mes y el año
}

/** Estado de un chofer en el reporte de morosidad: En mora (cuotas vencidas) o Pendiente (aún en plazo). */
function estado_mora(array $fila): array   // Función estado_mora(): estado del chofer en el reporte de morosidad
{
    return (int) ($fila['cuotas_vencidas'] ?? 0) > 0   // Si tiene alguna cuota con fecha límite pasada...
        ? ['texto' => 'En mora (' . (int) $fila['dias_mora'] . ' días)', 'clase' => 'danger']   // ...está "En mora" (rojo) y se indican los días de atraso
        : ['texto' => 'Pendiente', 'clase' => 'warning'];   // ...si no, solo tiene cuotas "Pendientes" dentro del plazo (amarillo)
}

/* ======================= 2. SESIÓN Y ROLES ======================= */
/**
 * Autenticación y control de acceso basado en roles (RBAC).
 */
class Auth   // Clase Auth: maneja la sesión del usuario y sus permisos
{
    public static function iniciarSesion(): void   // Inicia la sesión de PHP con opciones seguras
    {
        if (session_status() === PHP_SESSION_ACTIVE) {   // Si la sesión ya está iniciada...
            return;   // ...no hace nada
        }
        session_name('VILLAAROMA_SID');   // Nombre propio de la cookie de sesión
        session_set_cookie_params([   // Opciones de la cookie de sesión:
            'httponly' => true,   // JavaScript no puede leer la cookie (protege contra robo por XSS)
            'samesite' => 'Lax',   // La cookie no se envía desde otros sitios (protege contra CSRF)
            'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',   // Con HTTPS, la cookie solo viaja cifrada
        ]);
        session_start();   // Inicia la sesión

        // Cierre por inactividad
        if (isset($_SESSION['usuario'], $_SESSION['_ultimo_mov'])   // Si hay un usuario y se conoce su último movimiento...
            && time() - $_SESSION['_ultimo_mov'] > SESION_INACTIVIDAD) {   // ...y pasaron más de 30 minutos sin actividad...
            self::logout();   // ...cierra la sesión
            session_start();   // Abre una sesión nueva y vacía
            flash('warning', 'Su sesión se cerró por inactividad.');   // Avisa que la sesión se cerró por inactividad
        }
        $_SESSION['_ultimo_mov'] = time();   // Guarda la hora del último movimiento
    }

    public static function login(array $usuario): void   // Guarda al usuario en la sesión cuando su contraseña es correcta
    {
        session_regenerate_id(true);   // evita fijación de sesión
        $_SESSION['usuario'] = [   // Datos del usuario que se guardan en la sesión:
            'id'     => (int) $usuario['id_usuario'],   // Su número de usuario
            'nombre' => $usuario['nombre'],   // Su nombre
            'usuario'=> $usuario['usuario'],   // Su nombre de usuario
            'rol'    => $usuario['rol'],   // Su rol (ventanilla o administrador)
        ];
    }

    public static function logout(): void   // Cierra la sesión por completo
    {
        $_SESSION = [];   // Vacía todos los datos de la sesión
        if (ini_get('session.use_cookies')) {   // Si la sesión usa cookie...
            $p = session_get_cookie_params();   // Lee las opciones de la cookie
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);   // Borra la cookie del navegador (fecha en el pasado)
        }
        session_destroy();   // Destruye la sesión en el servidor
    }

    public static function check(): bool   // ¿Hay un usuario con sesión iniciada?
    {
        return isset($_SESSION['usuario']);   // Verdadero si existe un usuario en la sesión
    }

    public static function user(): ?array   // Devuelve los datos del usuario con sesión
    {
        return $_SESSION['usuario'] ?? null;   // O null si no hay sesión
    }

    public static function id(): ?int   // Devuelve el número (id) del usuario con sesión
    {
        return $_SESSION['usuario']['id'] ?? null;   // O null si no hay sesión
    }

    public static function rol(): ?string   // Devuelve el rol del usuario con sesión
    {
        return $_SESSION['usuario']['rol'] ?? null;   // O null si no hay sesión
    }

    /** ¿El usuario actual tiene alguno de los roles indicados? (Tabla 16: cada rol solo accede a sus módulos) */
    public static function tiene(string ...$roles): bool   // ¿El usuario tiene alguno de estos roles?
    {
        $rol = self::rol();   // Lee el rol del usuario actual
        return $rol !== null && in_array($rol, $roles, true);   // Verdadero solo si el rol está en la lista (comparación exacta)
    }
}

/* ======================= 3. PROTECCIÓN CSRF ======================= */
/**
 * Protección contra Cross-Site Request Forgery (OWASP).
 */
class Csrf   // Clase Csrf: protege los formularios contra envíos falsos desde otros sitios
{
    public static function token(): string   // Devuelve el token secreto de la sesión
    {
        if (empty($_SESSION['_csrf'])) {   // Si todavía no existe...
            $_SESSION['_csrf'] = bin2hex(random_bytes(32));   // ...crea uno aleatorio de 64 caracteres
        }
        return $_SESSION['_csrf'];   // Lo devuelve
    }

    public static function campo(): string   // Genera el campo oculto con el token para cada formulario
    {
        return '<input type="hidden" name="_csrf" value="' . self::token() . '">';   // <input type="hidden" name="_csrf" value="...">
    }

    public static function validar(string $token): bool   // Comprueba que el token recibido sea el mismo de la sesión
    {
        return !empty($_SESSION['_csrf']) && hash_equals($_SESSION['_csrf'], $token);   // hash_equals compara de forma segura (sin dar pistas por el tiempo)
    }
}

/* ======================= 4. CONTROLADOR BASE ======================= */
class Controlador   // Clase Controlador: padre de todos los controladores del sistema
{

    /** Renderiza una vista dentro del layout principal (o sin layout si $layout = null). */
    protected function view(string $vista, array $datos = [], ?string $layout = 'main'): void   // Muestra una vista (pantalla) con sus datos
    {
        extract($datos, EXTR_SKIP);   // Convierte el arreglo de datos en variables ($chofer, $titulo...)
        ob_start();   // Empieza a guardar en memoria lo que se imprime
        require VIEW_PATH . '/' . $vista . '.php';   // Carga el archivo de la vista
        $contenido = ob_get_clean();   // Guarda en $contenido el HTML generado

        if ($layout === null) {   // Si no se quiere plantilla (páginas sueltas como la credencial)...
            echo $contenido;   // ...imprime la vista tal cual
            return;   // Termina
        }
        $simple = $layout === 'simple';   // 'simple' = plantilla sin menú (login y errores)
        require VIEW_PATH . '/plantilla.php';   // Pone la vista dentro de la plantilla con el menú
    }

    protected function json(mixed $data, int $status = 200): void   // Responde datos en formato JSON (para JavaScript)
    {
        http_response_code($status);   // Código de respuesta (200 = correcto)
        header('Content-Type: application/json; charset=utf-8');   // Indica que la respuesta es JSON
        echo json_encode($data, JSON_UNESCAPED_UNICODE);   // Convierte los datos a JSON respetando las tildes
        exit;   // Termina la ejecución
    }

    protected function redirect(string $ruta, array $params = []): void   // Envía al navegador a otra pantalla del sistema
    {
        header('Location: ' . url($ruta, $params));   // Cabecera Location: el navegador abre esa dirección
        exit;   // Termina la ejecución
    }

    protected function back(): void   // Vuelve a la pantalla anterior
    {
        $ref = $_SERVER['HTTP_REFERER'] ?? url('inicio');   // Página de la que vino el usuario (o el inicio)
        header('Location: ' . $ref);   // Redirige allí
        exit;   // Termina la ejecución
    }

    /** Exige método POST con token CSRF válido. */
    protected function requirePost(): void   // Exige que el formulario se envíe por POST y con token CSRF válido
    {
        // Si el archivo supera el límite de PHP (post_max_size), PHP descarta todo el formulario
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$_POST && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {   // Si llegó un POST vacío pero con datos (archivo demasiado grande)...
            flash('error', 'El archivo supera el tamaño máximo permitido de ' . (UPLOAD_MAX_BYTES / 1048576) . ' MB.', 'Archivo muy grande');   // ...avisa que el archivo supera el máximo
            $this->back();   // ...y vuelve a la pantalla anterior
        }
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !Csrf::validar($_POST['_csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')) {   // Si no es POST o el token CSRF no coincide...
            if ($this->esAjax()) {   // Si la petición es de JavaScript...
                $this->json(['ok' => false, 'mensaje' => 'Solicitud no válida. Recargue la página.'], 419);   // ...responde el error en JSON (419 = solicitud vencida)
            }
            flash('error', 'La solicitud expiró o no es válida. Intente nuevamente.');   // Si es un formulario normal, prepara el aviso
            $this->back();   // ...y vuelve a la pantalla anterior sin hacer nada
        }
    }

    protected function esAjax(): bool   // ¿La petición la hizo JavaScript (AJAX)?
    {
        return ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest'   // Revisa la cabecera que envía fetch del sistema...
            || str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json');   // ...o si se pide una respuesta JSON
    }

    protected function input(string $clave, mixed $defecto = null): mixed   // Lee un dato del formulario (POST) o de la dirección (GET)
    {
        $v = $_POST[$clave] ?? $_GET[$clave] ?? $defecto;   // Busca primero en POST, luego en GET; si no está, usa el valor por defecto
        return is_string($v) ? trim($v) : $v;   // Quita los espacios del inicio y del final
    }

    protected function inputInt(string $clave, int $defecto = 0): int   // Lee un dato y lo convierte en número entero
    {
        return (int) ($this->input($clave, $defecto));   // (int) evita que se cuele texto donde se espera un número
    }

    /** Guarda los datos del formulario para volver a mostrarlos si hay errores. */
    protected function conErrores(array $errores, string $ruta, array $params = []): void   // Vuelve al formulario mostrando los errores de validación
    {
        $_SESSION['_old'] = $_POST;   // Guarda lo escrito para no perderlo
        flash('error', implode('<br>', array_map('e', $errores)), 'Datos incorrectos');   // Tablas 21 y 28
        $this->redirect($ruta, $params);   // Regresa al formulario
    }

    protected function notFound(string $mensaje = 'Registro no encontrado'): void   // Muestra la página "no encontrado" y termina
    {
        http_response_code(404);   // Código 404
        $this->view('error', ['mensaje' => $mensaje, 'codigo' => 404]);   // Muestra la vista de error
        exit;   // Termina la ejecución
    }

    /* ----- Páginas de error (404, 403, 500) ----- */

    public function noEncontrado(): void   // Página 404: la dirección no existe
    {
        $this->pagina('error', ['mensaje' => 'La página solicitada no existe.', 'codigo' => 404]);   // Muestra el error 404
    }

    public function prohibido(): void   // Página 403: el rol no tiene permiso
    {
        $this->pagina('error', ['mensaje' => 'Su rol no tiene permiso para acceder a este módulo.', 'codigo' => 403]);   // Muestra el error 403
    }

    public function servidor(string $mensaje): void   // Página 500: error del sistema
    {
        $this->pagina('error', ['mensaje' => $mensaje, 'codigo' => 500]);   // Muestra el error 500
    }

    /** Usa el layout principal solo si hay sesión; si no, una página simple. */
    protected function pagina(string $vista, array $datos): void   // Elige la plantilla para las páginas de error
    {
        $this->view($vista, $datos, Auth::check() ? 'main' : 'simple');   // Con sesión: plantilla con menú; sin sesión: plantilla simple
    }
}
