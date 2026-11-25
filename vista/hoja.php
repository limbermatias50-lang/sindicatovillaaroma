<?php
/**
 * Vista previa imprimible de la hoja de DATOS PERSONALES (personal de ventanilla).
 * Es la misma hoja que se ve al escanear el QR con la sesión iniciada.
 */
/** @var array $chofer */
/** @var bool $nuevo */
$completo = true;   // En esta vista previa el personal ve todos los datos
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Datos personales · <?= e($chofer['nombres'] . ' ' . $chofer['apellidos']) ?></title><?php /* Título de la pestaña con el nombre del chofer */ ?>
    <link rel="icon" type="image/png" href="<?= asset('vista/img/favicon.png') ?>">
    <link rel="stylesheet" href="<?= asset('vista/lib/bootstrap.min.css') ?>"><?php /* Librería Bootstrap (estilos) */ ?>
    <link rel="stylesheet" href="<?= asset('vista/lib/bootstrap-icons.min.css') ?>"><?php /* Íconos */ ?>
    <link rel="stylesheet" href="<?= asset('vista/css/estilo.css') ?>"><?php /* Estilos propios */ ?>
    <style>
        @page { size: A4; margin: 10mm; }
        @media print { body { background: #fff !important; } .container { max-width: none; padding: 0; } }
    </style>
</head>
<body class="bg-light">
<div class="container py-4" style="max-width: 940px">
    <div class="no-print mb-3">
        <?php if ($nuevo): /* Si se acaba de registrar al chofer... */ ?>
            <div class="alert alert-success d-flex gap-2 align-items-center">
                <i class="bi bi-check-circle-fill fs-4"></i>
                <div>Chofer registrado con el código <b><?= e($chofer['codigo_qr']) ?></b>. Esta es su hoja de datos personales; imprímala para que la firme y luego imprima su credencial QR.</div><?php /* ...muestra su código QR y qué hacer */ ?>
            </div>
        <?php endif; /* Fin */ ?>
        <div class="d-flex flex-wrap gap-2 align-items-center">
            <h1 class="h5 mb-0 flex-grow-1"><i class="bi bi-person-vcard"></i> Vista previa · Datos personales</h1>
            <button class="btn btn-primary" onclick="window.print()"><i class="bi bi-printer"></i> Imprimir hoja</button>
            <a class="btn btn-outline-primary" target="_blank" href="<?= url('choferes/credencial', ['id' => $chofer['id_chofer']]) ?>"><i class="bi bi-qr-code"></i> Credencial QR</a><?php /* Botón para imprimir su credencial QR */ ?>
            <a class="btn btn-light" href="<?= url('choferes/ver', ['id' => $chofer['id_chofer']]) ?>"><i class="bi bi-folder2-open"></i> Expediente</a><?php /* Botón para volver al expediente */ ?>
        </div>
        <?php if (empty($chofer['foto'])): /* Si no tiene foto... */ ?>
            <div class="small text-muted mt-2"><i class="bi bi-camera"></i> Sin foto: puede subirla en
                <a href="<?= url('choferes/editar', ['id' => $chofer['id_chofer']]) ?>">Editar chofer</a> o pegarla en el recuadro al imprimir.</div><?php /* ...indica dónde subirla */ ?>
        <?php endif; /* Fin */ ?>
    </div>

    <?php require VIEW_PATH . '/hoja_datos.php'; /* Incluye la hoja de DATOS PERSONALES */ ?>
</div>
<?php flashes(); /* el aviso de registro ya se muestra arriba */ ?>
</body>
</html>
