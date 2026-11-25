<?php
/** Variables que envía ChoferControlador::credencial() a esta vista. */
/** @var array $chofer */

// Al escanear el QR, el celular abre la página de verificación de la credencial
$urlDatos = url_publica('datos', ['c' => $chofer['codigo_qr']]);   // Dirección que guarda el QR: abre la hoja de datos de este chofer
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Credencial <?= e($chofer['codigo_qr']) ?></title><?php /* Título de la pestaña */ ?>
    <link rel="icon" type="image/png" href="<?= asset('vista/img/favicon.png') ?>">
    <link rel="stylesheet" href="<?= asset('vista/lib/bootstrap.min.css') ?>"><?php /* Bootstrap */ ?>
    <link rel="stylesheet" href="<?= asset('vista/lib/bootstrap-icons.min.css') ?>"><?php /* Íconos */ ?>
    <link rel="stylesheet" href="<?= asset('vista/css/estilo.css') ?>"><?php /* Estilos propios */ ?>
    <style>
        @page { size: A4; margin: 12mm; }
        @media print {
            .hoja-impresion { break-before: page; }
            .hoja-impresion.sin-imprimir { display: none; }
            body { background: #fff !important; }
        }
        /* La tarjeta en columna: el cuerpo ocupa el espacio entre la cabecera y la cinta tricolor */
        .credencial:has(.cuerpo-qr) { display: flex; flex-direction: column; }
        .credencial .cuerpo-qr {
            flex: 1; flex-direction: column; align-items: center; justify-content: center;
            padding: .5mm 2mm 2mm; gap: .8mm;   /* 2mm abajo para no quedar bajo la cinta */
        }
        .credencial:has(.cuerpo-qr) .cab { padding: 1mm 2.5mm; }
        .credencial:has(.cuerpo-qr) .cab img { width: 8mm; height: 8mm; }
        .credencial .cuerpo-qr .qr img { width: 30mm; height: 30mm; display: block; }
        .credencial .cuerpo-qr .nombre {
            font-size: 3.3mm; font-weight: 700; color: #000; text-align: center;
            line-height: 1.35; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 100%;
        }
    </style>
</head>
<body class="bg-light">
<div class="container py-4">
    <div class="no-print d-flex gap-2 mb-4 align-items-center">
        <h1 class="h5 mb-0 flex-grow-1"><i class="bi bi-qr-code"></i> Credencial del afiliado</h1>
        <button class="btn btn-primary" onclick="window.print()"><i class="bi bi-printer"></i> Imprimir</button>
        <button class="btn btn-light" onclick="window.close()">Cerrar</button>
    </div>

    <div class="d-flex flex-wrap gap-4">
        <div class="credencial">
            <div class="cab">
                <img src="<?= asset('vista/img/logo-sm.png') ?>" alt=""><?php /* Logo pequeño en la tarjeta */ ?>
                <div><b>SINDICATO MIXTO DE TRANSPORTES<br>"VILLA AROMA"</b><small>Lahuachaca · Prov. Aroma · La Paz</small></div>
            </div>
            <div class="cuerpo cuerpo-qr">
                <div class="qr" id="qr" data-texto="<?= e($urlDatos) ?>"></div><?php /* Aquí se dibuja el QR; data-texto guarda la dirección */ ?>
                <div class="nombre"><?= e($chofer['nombres'] . ' ' . $chofer['apellidos']) ?></div><?php /* Nombre del chofer debajo del QR */ ?>
            </div>
            <div class="pie cinta-bolivia"></div>
        </div>
    </div>
    <p class="no-print text-muted small mt-4">Tamaño tarjeta estándar (86 × 54 mm). Imprima en cartulina y recorte por el borde.</p>

    <!-- Vista previa de la hoja de DATOS PERSONALES que se abre al escanear el QR -->
    <div class="no-print mt-4 d-flex flex-wrap align-items-center gap-2 mb-2">
        <h2 class="h6 mb-0 flex-grow-1"><i class="bi bi-phone"></i> Vista previa: lo que se ve al escanear el QR</h2>
        <div class="form-check form-switch mb-0">
            <input class="form-check-input" type="checkbox" id="imprimir-hoja" checked>
            <label class="form-check-label small" for="imprimir-hoja">Imprimir también la hoja de datos</label>
        </div>
        <a class="btn btn-sm btn-outline-primary" href="<?= e($urlDatos) ?>" target="_blank"><i class="bi bi-box-arrow-up-right"></i> Abrir</a><?php /* Botón para abrir la dirección del QR */ ?>
    </div>
    <div class="no-print small text-muted mb-2 text-break">Dirección del QR: <code><?= e($urlDatos) ?></code> ·<?php /* Muestra la dirección que contiene el QR */ ?>
        Con sesión iniciada se ven todos los datos; un celular sin sesión ve el C.I., la fecha de nacimiento y la licencia como «Reservado».</div>
    <div id="bloque-hoja" class="hoja-impresion">
        <?php $completo = true; require VIEW_PATH . '/hoja_datos.php'; /* Vista previa de la hoja de datos completa */ ?>
    </div>
</div>
<script src="<?= asset('vista/lib/qrcode.js') ?>"></script><?php /* Librería qrcode-generator 2.0.4 (crea el QR en el navegador) */ ?>
<script>
    // Genera el código QR en el navegador con qrcode-generator (sin servicios externos)
    (function () {<?php /* Función que se ejecuta sola al cargar */ ?>
        const nodo = document.getElementById('qr');<?php /* Elemento donde va el QR */ ?>
        const qr = qrcode(0, 'M');<?php /* Crea un QR: 0 = tamaño automático; M = corrección de errores media (15%) */ ?>
        qr.addData(nodo.dataset.texto, 'Byte');<?php /* Le pone la dirección de la hoja de datos */ ?>
        qr.make();<?php /* Calcula los cuadritos del QR */ ?>
        // Margen blanco de 4 módulos (zona de silencio): sin él muchos celulares no lo leen
        nodo.innerHTML = qr.createImgTag(8, 32, 'Código QR del chofer');<?php /* Lo dibuja como imagen: cuadritos de 8 px con margen blanco de 32 px */ ?>
    })();
    document.getElementById('imprimir-hoja').addEventListener('change', (e) =><?php /* Interruptor "Imprimir también la hoja de datos" */ ?>
        document.getElementById('bloque-hoja').classList.toggle('sin-imprimir', !e.target.checked));<?php /* Oculta o muestra la hoja al imprimir */ ?>
</script>
</body>
</html>
