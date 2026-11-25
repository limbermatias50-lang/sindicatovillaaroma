<?php
/**
 * PLANTILLA de todas las pantallas.
 *  - Con sesión iniciada: menú lateral (cada rol ve solo sus módulos, Tabla 16) y barra superior.
 *  - Sin sesión ($simple = true): login y páginas de error, sin menú.
 * Variables: $titulo, $contenido (la pantalla ya generada), $scripts (librerías extra), $simple.
 */
$usuario = Auth::user();   // Usuario con sesión (o null)
$simple  = $simple ?? !$usuario;   // Sin sesión se usa la plantilla simple (sin menú)

// Menú lateral: 'roles' vacío = visible para todos los usuarios con sesión
$menu = [   // Opciones del menú agrupadas por sección (texto, ruta, ícono y roles que la ven)
    'Principal' => [
        ['texto' => 'Panel de indicadores', 'ruta' => 'inicio',     'activo' => 'inicio',     'icono' => 'bi-speedometer2', 'roles' => []],
        ['texto' => 'Ventanilla QR',        'ruta' => 'ventanilla', 'activo' => 'ventanilla', 'icono' => 'bi-qr-code-scan', 'roles' => ['ventanilla']],
    ],
    'Afiliados' => [
        ['texto' => 'Choferes', 'ruta' => 'choferes', 'activo' => 'choferes', 'icono' => 'bi-person-vcard', 'roles' => ['ventanilla']],
        ['texto' => 'Grupos',   'ruta' => 'grupos',   'activo' => 'grupos',   'icono' => 'bi-diagram-3',    'roles' => ['ventanilla']],
    ],
    'Aportes' => [
        ['texto' => 'Cuotas por grupo', 'ruta' => 'cuotas',   'activo' => 'cuotas',   'icono' => 'bi-cash-coin',      'roles' => ['ventanilla']],
        ['texto' => 'Reportes',         'ruta' => 'reportes', 'activo' => 'reportes', 'icono' => 'bi-bar-chart-line', 'roles' => ['ventanilla']],
    ],
    'Documentación' => [
        ['texto' => 'Resguardo documental',   'ruta' => 'documentos', 'activo' => 'documentos', 'icono' => 'bi-folder2-open',         'roles' => ['ventanilla']],
        ['texto' => 'Alertas de vencimiento', 'ruta' => 'alertas',    'activo' => 'alertas',    'icono' => 'bi-exclamation-triangle', 'roles' => ['ventanilla']],
    ],
    'Administración' => [
        ['texto' => 'Usuarios',            'ruta' => 'usuarios',          'activo' => 'usuarios$',         'icono' => 'bi-people',        'roles' => ['administrador']],
        ['texto' => 'Bitácora',            'ruta' => 'usuarios/bitacora', 'activo' => 'usuarios/bitacora', 'icono' => 'bi-journal-text',  'roles' => ['administrador']],
        ['texto' => 'Copias de seguridad', 'ruta' => 'respaldo',          'activo' => 'respaldo',          'icono' => 'bi-database-down', 'roles' => ['administrador']],
    ],
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="base-url" content="<?= e(base_url()) ?>"><?php /* Carpeta base del sistema (la usa JavaScript para armar direcciones) */ ?>
    <meta name="csrf-token" content="<?= e(Csrf::token()) ?>"><?php /* Token CSRF para las peticiones de JavaScript (fetch) */ ?>
    <meta name="max-archivo" content="<?= UPLOAD_MAX_BYTES ?>"><?php /* Tamaño máximo de archivo (lo usa JavaScript para avisar antes de subir) */ ?>
    <title><?= e(($titulo ?? 'Inicio') . ' · ' . APP_NAME) ?></title><?php /* Título de la pestaña del navegador */ ?>
    <link rel="icon" type="image/png" href="<?= asset('vista/img/favicon.png') ?>"><?php /* Ícono de la pestaña */ ?>
    <link rel="stylesheet" href="<?= asset('vista/lib/bootstrap.min.css') ?>"><?php /* Librería Bootstrap 5.3.8 (diseño y componentes) */ ?>
    <link rel="stylesheet" href="<?= asset('vista/lib/bootstrap-icons.min.css') ?>"><?php /* Íconos Bootstrap Icons 1.13.1 */ ?>
    <link rel="stylesheet" href="<?= asset('vista/css/estilo.css') ?>"><?php /* Estilos propios del sistema (colores del sindicato) */ ?>
</head>
<?php if ($simple): /* Plantilla simple: login y páginas de error */ ?>
<body class="pagina-simple d-flex flex-column">
    <main class="flex-grow-1 d-flex align-items-center justify-content-center p-3">
        <?= $contenido /* Aquí se coloca la pantalla generada por el controlador */ ?>
    </main>
    <footer class="text-center text-white-50 small pb-3"><?= e(APP_FULL_NAME) ?></footer><?php /* Pie de página con el nombre del sindicato */ ?>
<?php else: /* Plantilla completa: con menú lateral */ ?>
<body>
<!-- Barra lateral: fija en escritorio, desplegable en celulares -->
<aside class="sidebar offcanvas-lg offcanvas-start" tabindex="-1" id="menuLateral">
    <a class="brand" href="<?= url('inicio') ?>"><?php /* Logo y nombre: llevan al panel */ ?>
        <img src="<?= asset('vista/img/logo-sm.png') ?>" alt="Logo Villa Aroma"><?php /* Logo pequeño */ ?>
        <span><strong>Villa Aroma</strong><small>Sindicato Mixto de Transporte<br>Lahuachaca</small></span>
    </a>
    <div class="offcanvas-body d-block">
        <nav class="nav flex-column pb-4">
            <?php foreach ($menu as $seccion => $items): /* Recorre cada sección del menú */ ?>
                <?php $visibles = array_filter($items, fn($i) => empty($i['roles']) || Auth::tiene(...$i['roles'])); /* Deja solo las opciones que puede ver el rol del usuario */ ?>
                <?php if (!$visibles) continue; /* Si la sección queda vacía, no se muestra */ ?>
                <div class="nav-section"><?= e($seccion) ?></div><?php /* Nombre de la sección */ ?>
                <?php foreach ($visibles as $item): /* Recorre las opciones visibles */ ?>
                    <a class="nav-link <?= es_activa($item['activo']) ?>" href="<?= url($item['ruta']) ?>"><?php /* Enlace; se resalta si es la pantalla actual */ ?>
                        <i class="bi <?= e($item['icono']) ?>"></i> <?= e($item['texto']) /* Ícono y texto de la opción */ ?>
                    </a>
                <?php endforeach; /* Fin de las opciones */ ?>
            <?php endforeach; /* Fin de las secciones */ ?>
        </nav>
    </div>
</aside>

<div class="main-wrap d-flex flex-column min-vh-100">
    <header class="topbar px-3 py-2 d-flex align-items-center gap-2 sticky-top">
        <button class="btn btn-outline-primary d-lg-none" data-bs-toggle="offcanvas" data-bs-target="#menuLateral" aria-label="Abrir menú">
            <i class="bi bi-list"></i>
        </button>
        <h1 class="page-title flex-grow-1 text-truncate"><?= e($titulo ?? '') ?></h1><?php /* Título de la pantalla actual */ ?>
        <div class="dropdown">
            <button class="btn btn-light d-flex align-items-center gap-2" data-bs-toggle="dropdown">
                <span class="avatar"><?= e(mb_strtoupper(mb_substr($usuario['nombre'], 0, 1))) ?></span><?php /* Inicial del nombre del usuario */ ?>
                <span class="d-none d-sm-inline text-start lh-sm">
                    <small class="d-block fw-semibold"><?= e($usuario['nombre']) ?></small><?php /* Nombre del usuario */ ?>
                    <small class="text-muted text-capitalize"><?= e($usuario['rol']) ?></small><?php /* Rol del usuario */ ?>
                </span>
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
                <li>
                    <form method="post" action="<?= url('logout') ?>"><?php /* Cerrar sesión se envía por POST... */ ?>
                        <?= Csrf::campo() /* ...con el token CSRF (evita que otra página cierre la sesión) */ ?>
                        <button class="dropdown-item text-danger"><i class="bi bi-box-arrow-right me-2"></i>Cerrar sesión</button>
                    </form>
                </li>
            </ul>
        </div>
    </header>

    <main class="p-3 p-md-4 flex-grow-1">
        <?= $contenido /* Aquí se coloca la pantalla generada por el controlador */ ?>
    </main>

    <footer class="text-center text-muted small py-3"><?= e(APP_FULL_NAME) ?> · <?= date('Y') ?></footer><?php /* Pie de página con el año actual */ ?>
</div>
<?php endif; /* Fin de la elección de plantilla */ ?>

<script type="application/json" id="flash-data"><?= json_encode(flashes(), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?></script><?php /* Mensajes pendientes (flash) que JavaScript muestra con SweetAlert2 */ ?>
<script src="<?= asset('vista/lib/bootstrap.bundle.min.js') ?>"></script><?php /* JavaScript de Bootstrap (menú, ventanas modales, pestañas) */ ?>
<script src="<?= asset('vista/lib/sweetalert2.all.min.js') ?>"></script><?php /* Librería SweetAlert2 11.26.25 (mensajes y confirmaciones) */ ?>
<?php foreach ($scripts ?? [] as $s): /* Librerías extra que pide cada pantalla (Chart.js, Html5-Qrcode) */ ?>
    <script src="<?= asset($s) ?>"></script><?php /* Carga cada librería extra */ ?>
<?php endforeach; /* Fin de las librerías extra */ ?>
<script src="<?= asset('vista/js/sistema.js') ?>"></script><?php /* JavaScript propio del sistema (se carga al final) */ ?>
</body>
</html>
