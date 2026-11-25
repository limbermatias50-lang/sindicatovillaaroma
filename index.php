<?php
/**
 * SISTEMA WEB SINDICATO MIXTO DE TRANSPORTE "VILLA AROMA" — LAHUACHACA
 * Punto de entrada único (patrón MVC): toda petición llega aquí como index.php?r=modulo/accion
 *
 *   controlador/  recibe la petición, valida y decide qué hacer
 *   modelo/       consulta y guarda en la base de datos MySQL
 *   vista/        muestra las pantallas (HTML + Bootstrap) y los PDF
 *
 * Este archivo tiene 4 partes: 1) configuración, 2) carga de archivos, 3) rutas y 4) despacho.
 */
declare(strict_types=1);   // Activa tipos estrictos: PHP no convierte datos de un tipo a otro sin avisar

if (PHP_VERSION_ID < 80000) {   // Si la versión de PHP es menor a 8.0, el sistema no puede funcionar
    http_response_code(500);   // Responde con el código 500 (error del servidor)
    exit('Este sistema necesita PHP 8.0 o superior. Versión instalada: ' . PHP_VERSION);   // Detiene todo y muestra qué versión de PHP está instalada
}

/* ========================= 1. CONFIGURACIÓN ========================= */
// --- Base de datos -------------------------------------------------------
define('DB_HOST', '127.0.0.1');   // Dirección del servidor MySQL (127.0.0.1 = esta misma computadora)
define('DB_PORT', '3306');   // Puerto de MySQL (3306 es el puerto normal)
define('DB_NAME', 'sindicato_villa_aroma');   // Nombre de la base de datos del sistema
define('DB_USER', 'root');      // En producción use un usuario exclusivo con privilegios limitados
define('DB_PASS', '');   // Contraseña del usuario de MySQL (en XAMPP viene vacía)

// --- Aplicación ----------------------------------------------------------
define('APP_NAME', 'Sindicato Villa Aroma');   // Nombre corto del sistema (se ve en la pestaña del navegador)
define('APP_FULL_NAME', 'Sindicato Mixto de Transporte "Villa Aroma" — Lahuachaca');   // Nombre completo que aparece en el pie de página
define('APP_DEBUG', true);            // Cambie a false en producción
define('APP_TIMEZONE', 'America/La_Paz');   // Zona horaria de Bolivia: las fechas y horas de los pagos salen correctas

// Dirección con la que los celulares abren la verificación de credencial del QR.
// Vacío = se detecta sola (IP de esta PC en la red local). En un servidor en la nube
// escriba aquí su dominio, por ejemplo: 'https://villaaroma.com/'
define('URL_PUBLICA', '');   // Vacío = el sistema arma solo la dirección del QR

// Apartado 5.2.2: en el servidor en la nube active la redirección obligatoria de HTTP a HTTPS
// (la cámara del navegador solo funciona con HTTPS o en localhost). En XAMPP déjelo en false.
define('FORZAR_HTTPS', false);   // false en XAMPP; true en el hosting para obligar a usar HTTPS

// --- Reglas de negocio ---------------------------------------------------
define('DIAS_ALERTA_LICENCIA', 30);   // Días de anticipación para alertar vencimientos (RF06)
define('SESION_INACTIVIDAD', 1800);   // Segundos (30 min) antes de cerrar sesión por inactividad
define('MAX_INTENTOS_LOGIN', 5);      // Intentos fallidos antes de bloquear temporalmente
define('BLOQUEO_LOGIN_SEG', 300);     // Duración del bloqueo (5 min)

// --- Archivos digitalizados (RF05) ---------------------------------------
define('UPLOAD_MAX_BYTES', 100 * 1024 * 1024);   // 100 MB por archivo (PHP también debe permitirlo: ver .htaccess)
define('UPLOAD_TIPOS', [                         // extensión => tipo MIME permitido
    'pdf'  => 'application/pdf',   // Documentos PDF
    'jpg'  => 'image/jpeg',   // Imágenes JPG
    'jpeg' => 'image/jpeg',   // Imágenes JPEG (mismo tipo que JPG)
    'png'  => 'image/png',   // Imágenes PNG
]);

define('FOTO_TIPOS', ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png']);   // foto del chofer · La foto del chofer solo puede ser imagen (no PDF)

// --- Carpetas del sistema (no modificar)
define('ROOT_PATH',   __DIR__);   // Carpeta raíz del sistema (donde está este index.php)
define('VIEW_PATH',   ROOT_PATH . '/vista');   // Carpeta donde están las vistas (pantallas)
define('UPLOAD_PATH', ROOT_PATH . '/archivos');   // documentos y fotos (protegida con .htaccess)

date_default_timezone_set(APP_TIMEZONE);   // Aplica la zona horaria de Bolivia a todo el sistema
ini_set('display_errors', APP_DEBUG ? '1' : '0');   // Muestra los errores en pantalla solo si APP_DEBUG es true
error_reporting(E_ALL);   // Registra todos los tipos de errores de PHP

/* ========================= 2. CARGA DE ARCHIVOS ========================= */
require ROOT_PATH . '/controlador/Controlador.php';   // ayudas, sesión, CSRF y controlador base
require ROOT_PATH . '/controlador/Validacion.php';    // reglas de Bolivia, validador y carga de archivos
foreach (['Modelo', 'Usuario', 'Chofer', 'Documento', 'Cuota', 'Reporte'] as $m) {   // Recorre la lista de modelos (uno por módulo)
    require ROOT_PATH . "/modelo/$m.php";   // Carga cada archivo de la carpeta modelo/
}
foreach (['Usuario', 'Chofer', 'Documento', 'Cuota', 'Reporte'] as $c) {   // Recorre la lista de controladores
    require ROOT_PATH . "/controlador/{$c}Controlador.php";   // Carga cada archivo de la carpeta controlador/
}
// Los PDF (FPDF) se cargan solo cuando se genera un reporte o un recibo
spl_autoload_register(function (string $clase): void {   // Registra una función que PHP llama cuando una clase todavía no existe
    if (in_array($clase, ['BasePdf', 'ReportePdf', 'ReciboPdf'], true)) {   // Si la clase que se pide es de los PDF...
        require_once ROOT_PATH . '/vista/pdf.php';   // ...carga vista/pdf.php (y FPDF) recién en ese momento
    }
});

/* ========================= 3. RUTAS ========================= */
/*
 * 'ruta' => [Controlador, método, roles permitidos]
 *   null = pública (sin sesión) · [] = cualquier usuario con sesión
 *   V = Secretaria(o) de ventanilla · A = Administrador (Tabla 16 del documento)
 */
const V = 'ventanilla';   // Constante con el nombre del rol de ventanilla
const A = 'administrador';   // Constante con el nombre del rol administrador

$rutas = [   // Tabla de rutas: cada dirección ?r=... apunta a un controlador y un método
    // Autenticación (CU01)
    'login'              => ['UsuarioControlador', 'login',  null],   // Muestra el formulario de inicio de sesión (pública)
    'login/entrar'       => ['UsuarioControlador', 'entrar', null],   // Recibe usuario y contraseña (pública)
    'logout'             => ['UsuarioControlador', 'salir',  []],   // Cierra la sesión (cualquier usuario con sesión)

    // Verificación pública de la credencial con un celular (sin datos sensibles). Tabla 36.
    'datos'              => ['ChoferControlador', 'datos',  null],   // Hoja de datos del QR (datos sensibles solo con sesión)
    'choferes/foto'      => ['ChoferControlador', 'foto',   null],   // Foto de la hoja de datos (por código QR)

    // Panel de indicadores (ambos roles, Tabla 34)
    'inicio'             => ['ReporteControlador', 'panel', []],   // Panel de indicadores: lo ven los dos roles

    // Choferes (RF01, RF02) — ventanilla registra, modifica y elimina
    'choferes'               => ['ChoferControlador', 'index',      [V]],   // Lista de choferes
    'choferes/nuevo'         => ['ChoferControlador', 'crear',      [V]],   // Formulario de chofer nuevo
    'choferes/guardar'       => ['ChoferControlador', 'guardar',    [V]],   // Guarda el chofer nuevo
    'choferes/editar'        => ['ChoferControlador', 'editar',     [V]],   // Formulario para modificar un chofer
    'choferes/actualizar'    => ['ChoferControlador', 'actualizar', [V]],   // Guarda los cambios del chofer
    'choferes/ver'           => ['ChoferControlador', 'ver',        [V]],   // Expediente del chofer
    'choferes/credencial'    => ['ChoferControlador', 'credencial', [V]],   // Credencial con código QR para imprimir
    'choferes/hoja'          => ['ChoferControlador', 'hoja',       [V]],   // Hoja de DATOS PERSONALES
    'choferes/buscar-qr'     => ['ChoferControlador', 'buscarQr',   [V]],   // JSON
    'choferes/eliminar'      => ['ChoferControlador', 'eliminar',   [V]],
    'ventanilla'             => ['ChoferControlador', 'ventanilla', [V]],   // Pantalla de atención en ventanilla

    // Grupos de transporte
    'grupos'                 => ['ChoferControlador', 'grupos',   [V]],   // Lista y formulario de grupos
    'grupos/guardar'         => ['ChoferControlador', 'guardarGrupo', [V]],   // Guarda un grupo nuevo o modificado

    // Cuotas por grupo (RF03) — ventanilla parametriza, modifica y anula
    'cuotas'                 => ['CuotaControlador', 'index',      [V]],   // Lista de cuotas por grupo
    'cuotas/guardar'         => ['CuotaControlador', 'guardar',    [V]],   // Registra una cuota nueva
    'cuotas/actualizar'      => ['CuotaControlador', 'actualizar', [V]],   // Modifica una cuota
    'cuotas/eliminar'        => ['CuotaControlador', 'eliminar',   [V]],   // Elimina (o anula si ya tiene pagos) una cuota

    // Pagos (RF04) — ventanilla cobra
    'pagos/registrar'        => ['CuotaControlador', 'registrarPago', [V]],   // JSON
    'pagos/recibo'           => ['CuotaControlador', 'recibo',    [V]],       // PDF
    'pagos/revertir'         => ['CuotaControlador', 'revertir',  [V]],   // corrige un cobro por error (queda en bitácora)

    // Documentos y alertas (RF05, RF06)
    'documentos'             => ['DocumentoControlador', 'index',     [V]],   // Lista de documentos digitalizados
    'documentos/subir'       => ['DocumentoControlador', 'subir',     [V]],   // Sube un documento escaneado
    'documentos/ver'         => ['DocumentoControlador', 'ver',       [V]],   // Muestra el archivo del documento (solo con sesión)
    'documentos/actualizar'  => ['DocumentoControlador', 'actualizar', [V]],   // Modifica los datos de un documento
    'documentos/eliminar'    => ['DocumentoControlador', 'eliminar',  [V]],   // Elimina un documento
    'documentos/licencia'    => ['DocumentoControlador', 'licencia',  [V]],   // Registra o renueva la licencia de conducir
    'alertas'                => ['DocumentoControlador', 'alertas',   [V]],   // Alertas de licencias vencidas o por vencer

    // Reportes (RF07, RF09)
    'reportes'               => ['ReporteControlador', 'index',     [V]],   // Emisión de reportes (consolidado por grupo)
    'reportes/caja'          => ['ReporteControlador', 'caja',      [V]],   // Reporte de caja diaria
    'reportes/estado-cuenta' => ['ReporteControlador', 'estadoCuenta', [V]],   // Estado de cuenta por grupo
    'reportes/morosidad'     => ['ReporteControlador', 'morosidad', [V]],   // Estado de morosidad

    // Usuarios (solo administrador)
    'usuarios'               => ['UsuarioControlador', 'usuarios',      [A]],   // Lista de usuarios del sistema
    'usuarios/guardar'       => ['UsuarioControlador', 'guardar',    [A]],   // Crea o modifica un usuario
    'usuarios/estado'        => ['UsuarioControlador', 'cambiarEstado', [A]],   // Activa o desactiva un usuario
    'usuarios/eliminar'      => ['UsuarioControlador', 'eliminar',   [A]],   // Elimina un usuario
    'usuarios/bitacora'      => ['UsuarioControlador', 'bitacora',   [A]],   // Bitácora de operaciones

    // Copias de seguridad (RNF04, solo administrador)
    'respaldo'               => ['UsuarioControlador', 'respaldo',     [A]],   // Pantalla de copias de seguridad
    'respaldo/descargar'     => ['UsuarioControlador', 'respaldoBd', [A]],   // Descarga la base de datos en un archivo .sql
    'respaldo/documentos'    => ['UsuarioControlador', 'respaldoDocumentos', [A]],   // Descarga los documentos en un archivo .zip
];

/* ========================= 4. DESPACHO ========================= */
// Redirección obligatoria de HTTP a HTTPS en el servidor en la nube (apartado 5.2.2)
$esHttps = ($_SERVER['HTTPS'] ?? '') === 'on' || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';   // ¿La conexión es segura (HTTPS)? Revisa también si viene de un proxy del hosting
if (FORZAR_HTTPS && !$esHttps) {   // Si se exige HTTPS y la conexión no es segura...
    header('Location: https://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . ($_SERVER['REQUEST_URI'] ?? '/'), true, 301);   // ...redirige a la misma dirección pero con https:// (301 = redirección permanente)
    exit;   // Termina aquí: el navegador volverá a pedir la página con HTTPS
}
header('X-Frame-Options: SAMEORIGIN');   // Prohíbe que otra página meta el sistema dentro de un marco (evita clickjacking)
header('X-Content-Type-Options: nosniff');   // El navegador no debe adivinar el tipo de archivo (evita ejecutar archivos disfrazados)
header('Referrer-Policy: same-origin');   // No envía la dirección del sistema a otras páginas

Auth::iniciarSesion();   // Inicia la sesión (y la cierra si pasaron 30 minutos sin actividad)

function responderError(string $mensaje, int $codigo): void   // Responde un error en formato JSON cuando la petición viene de JavaScript
{
    if (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest') {   // ¿La petición la hizo JavaScript (AJAX)?
        http_response_code($codigo);   // Pone el código de error (401 sin sesión, 403 sin permiso)
        header('Content-Type: application/json; charset=utf-8');   // Indica que la respuesta es JSON
        echo json_encode(['ok' => false, 'mensaje' => $mensaje], JSON_UNESCAPED_UNICODE);   // Envía el mensaje de error en JSON
        exit;   // Termina la ejecución
    }
}

try {   // Todo el despacho va dentro de try para capturar cualquier error
    $ruta = trim((string) ($_GET['r'] ?? ''), '/') ?: (Auth::check() ? 'inicio' : 'login');   // Lee la ruta pedida (?r=...); si no hay, va al inicio o al login

    if (!isset($rutas[$ruta])) {   // Si la ruta no existe en la tabla...
        http_response_code(404);   // ...responde 404 (no encontrado)
        (new Controlador())->noEncontrado();   // ...y muestra la página de error
        exit;   // Termina aquí
    }
    [$clase, $metodo, $roles] = $rutas[$ruta];   // Saca de la tabla el controlador, el método y los roles de esa ruta

    if ($roles !== null && !Auth::check()) {                 // ruta protegida sin sesión
        responderError('Su sesión expiró. Vuelva a iniciar sesión.', 401);   // Si es una petición de JavaScript, responde 401 en JSON
        flash('warning', 'Inicie sesión para continuar.');   // Guarda el aviso para mostrarlo en la pantalla de login
        header('Location: ' . url('login'));   // Envía al usuario a la pantalla de inicio de sesión
        exit;   // Termina aquí
    }
    if ($roles && !Auth::tiene(...$roles)) {                 // rol no autorizado (apartado 5.2.3)
        responderError('No tiene permisos para esta acción.', 403);   // Si es de JavaScript, responde 403 en JSON
        Bitacora::registrar('ACCESO_DENEGADO', 'Ruta: ' . $ruta);   // Guarda en la bitácora el intento de entrar sin permiso
        flash('error', 'Su rol no tiene permiso para acceder a este módulo.', 'Acceso denegado');   // Prepara el mensaje "Acceso denegado"
        header('Location: ' . url(Auth::rol() === V ? 'ventanilla' : 'inicio'));   // Devuelve al usuario a su pantalla de inicio según su rol
        exit;   // Termina aquí
    }

    (new $clase())->$metodo();                               // ejecuta el controlador

} catch (PDOException $e) {   // Si falló la base de datos (MySQL apagado, contraseña mala...)
    error_log('[BD] ' . $e->getMessage());   // Guarda el error en el registro de errores del servidor
    http_response_code(500);   // Responde 500 (error del servidor)
    (new Controlador())->servidor(APP_DEBUG ? $e->getMessage() : 'No se pudo conectar con la base de datos.');   // Muestra el error (detallado solo si APP_DEBUG es true)
} catch (Throwable $e) {   // Cualquier otro error inesperado
    error_log('[APP] ' . $e->getMessage() . ' en ' . $e->getFile() . ':' . $e->getLine());   // Guarda el error con el archivo y la línea donde ocurrió
    http_response_code(500);   // Responde 500
    (new Controlador())->servidor(APP_DEBUG ? $e->getMessage() : 'Ocurrió un error inesperado.');   // Muestra la página de error
}
