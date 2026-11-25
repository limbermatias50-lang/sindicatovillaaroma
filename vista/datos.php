<?php
/**
 * Página que abre el código QR de la credencial: hoja de DATOS PERSONALES del afiliado.
 * - Personal con sesión iniciada: ve la hoja completa.
 * - Cualquier otro celular: ve la misma hoja con el C.I., la fecha de nacimiento y el
 *   N° de licencia reservados (confidencialidad, Tabla 36 del documento).
 * Arriba se indica si la credencial está VÁLIDA o NO HABILITADA.
 */
/** @var array $chofer */
$completo = Auth::check();   // ¿Quien escanea tiene sesión iniciada? Entonces ve todos los datos
$activo   = $chofer['estado'] === 'activo';   // ¿El chofer está activo?
$licencia = estado_licencia($chofer['fecha_vencimiento']);   // Estado de su licencia
$valida   = $activo && $licencia['clase'] !== 'danger';   // Válida = activo y con licencia no vencida
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Datos personales · <?= e(APP_NAME) ?></title><?php /* Título de la pestaña */ ?>
    <link rel="icon" type="image/png" href="<?= asset('vista/img/favicon.png') ?>">
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; background: #e9ecf2; font-family: Calibri, Carlito, "Segoe UI", Arial, sans-serif; color: #222; }
        .marco { max-width: 900px; margin: 18px auto; }
        .estado { border-radius: 10px; padding: 12px 14px; text-align: center; color: #fff; margin-bottom: 12px; }
        .estado b { display: block; font-size: 21px; letter-spacing: .5px; }
        .estado small { font-size: 14px; opacity: .95; }
        .hd-hoja { box-shadow: 0 6px 24px rgba(0,0,0,.12); }
        .pie { margin: 10px 0 20px; font-size: 12px; color: #6c727d; text-align: center; }
        @media (max-width: 600px) { .marco { margin: 0; } .estado { border-radius: 0; margin: 0; } }
    </style>
</head>
<body>
<div class="marco">
    <div class="estado" style="background: <?= $valida ? '#198754' : '#d62828' ?>"><?php /* Verde si es válida, rojo si no */ ?>
        <b><?= $valida ? '✔ CREDENCIAL VÁLIDA' : '✖ CREDENCIAL NO HABILITADA' ?></b><?php /* Mensaje principal */ ?>
        <small><?= $activo ? 'Afiliado activo' : 'Afiliado ' . e($chofer['estado']) /* Estado del afiliado */ ?>
            · Licencia: <?= e($licencia['texto']) ?></small><?php /* Estado de la licencia */ ?>
    </div>

    <?php require VIEW_PATH . '/hoja_datos.php'; /* Incluye la hoja de DATOS PERSONALES */ ?>

    <div class="pie">Consultado el <?= date('d/m/Y H:i') ?> · Credencial <?= e($chofer['codigo_qr']) ?><br><?php /* Fecha y hora de la consulta y código de la credencial */ ?>
        <?= $completo ? 'Vista del personal del sindicato: se muestran todos los datos.'   // Mensaje según si tiene sesión o no
                      : 'Por seguridad, el C.I., la fecha de nacimiento y la licencia solo los ve el personal del sindicato.' ?></div>
</div>
</body>
</html>
