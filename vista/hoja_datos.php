<?php
/**
 * Hoja de DATOS PERSONALES del afiliado (mismo formato que la ficha en papel del sindicato).
 * Se usa en: la página que abre el QR, la vista previa del personal y la impresión de la credencial.
 *
 * @var array $chofer   detalle del chofer (Chofer::detalle / buscarPorQR)
 * @var bool  $completo true = personal con sesión (todos los datos); false = celular cualquiera
 *                      (C.I., fecha de nacimiento y N° de licencia reservados, Tabla 36).
 */
$completo  = $completo ?? false;   // true = personal (ve todo); false = celular sin sesión
$reservado = '<span class="hd-reservado">Reservado</span>';   // Texto que reemplaza a los datos reservados
$mayus     = fn(?string $t) => e(mb_strtoupper((string) $t, 'UTF-8'));   // Función: texto en MAYÚSCULAS y escapado
$ingreso   = $chofer['fecha_afiliacion'] === Bolivia::FUNDACION ? 'FUNDADOR' : fecha($chofer['fecha_afiliacion']);   // Si ingresó el día de la fundación muestra "FUNDADOR"; si no, la fecha
$expedido  = Bolivia::DEPARTAMENTOS[$chofer['ci_expedido']] ?? $chofer['ci_expedido'];   // Nombre del departamento (LP => La Paz)
$fotoUrl   = !empty($chofer['foto']) ? url('choferes/foto', ['c' => $chofer['codigo_qr'], 'v' => substr(md5($chofer['foto']), 0, 8)]) : null;   // Dirección de la foto (si tiene)
?>
<style>
    /* La hoja toma la altura de su contenido: sin espacios vacíos al final */
    .hd-hoja {
        position: relative; overflow: hidden; background: #fff; color: #222;
        width: 100%; max-width: 900px; margin: 0 auto; padding: 34px 44px 26px;
        border: 1.5px solid #333; font-family: Calibri, Carlito, "Segoe UI", Arial, sans-serif; font-size: 16px;
    }
    /* marca de agua con el logo (imagen real para que también salga impresa) */
    .hd-agua { position: absolute !important; left: 50%; top: 60%; transform: translate(-50%, -50%);
               width: min(70%, 470px); opacity: .09; pointer-events: none; }
    .hd-hoja > *:not(.hd-agua) { position: relative; }
    .hd-logo { display: block; width: 130px; margin: 0 auto 10px; }
    .hd-sindicato { text-align: center; font-family: Arial, sans-serif; font-weight: 700; font-size: 1.25em;
                    margin: 0 auto 30px; padding: 0 12px 4px; border-bottom: 2.5px solid #1f4e79; width: fit-content; }
    .hd-titulo { text-align: center; font-family: Arial, sans-serif; font-weight: 700; font-size: 1.5em;
                 text-decoration: underline; text-underline-offset: 5px; margin: 0 0 30px; }
    .hd-cuerpo { display: grid; grid-template-columns: 1fr 150px; column-gap: 26px; align-items: start; }
    .hd-fila { display: flex; align-items: center; flex-wrap: wrap; gap: 8px 14px; margin-bottom: 24px; }
    .hd-fila:last-child { margin-bottom: 0; }
    .hd-et { white-space: nowrap; }
    .hd-par { display: inline-flex; align-items: center; gap: 8px; white-space: nowrap; }
    .hd-caja { border: 1.6px solid #222; padding: 5px 12px; min-height: 2.1em; text-align: center;
               font-family: Arial, sans-serif; font-size: .95em; font-weight: 600; display: inline-flex; align-items: center; justify-content: center;
               background: rgba(255,255,255,.6); min-width: 6em; }
    .hd-nombre { flex: 1; min-width: 12em; }
    .hd-sub { width: 100%; text-align: center; margin-top: -4px; font-size: .9em; color: #444; }
    .hd-lado { display: flex; flex-direction: column; align-items: flex-end; gap: 18px; }
    .hd-nro { display: flex; align-items: center; gap: 8px; font-weight: 700; }
    .hd-nro .hd-caja { min-width: 3.5em; }
    .hd-foto { width: 100%; aspect-ratio: 3 / 3.6; border: 1.8px solid #222; border-radius: 18px; overflow: hidden;
               display: flex; align-items: center; justify-content: center; font-family: Arial, sans-serif; font-size: .9em; background: #fff; }
    .hd-foto img { width: 100%; height: 100%; object-fit: cover; }
    .hd-firma { margin: 52px 0 0 22%; font-size: 1.15em; }
    .hd-firma span { display: inline-block; width: 42%; border-bottom: 2px dotted #222; }
    .hd-pie { margin-top: 26px; font-size: .7em; letter-spacing: .5px; color: #9a9a9a; }
    .hd-reservado { color: #8a8f99; font-style: italic; font-weight: 400; letter-spacing: .3px; }
    @media (max-width: 600px) {
        .hd-hoja { font-size: 14px; padding: 20px 14px; }
        .hd-cuerpo { grid-template-columns: 1fr; }
        .hd-lado { order: -1; flex-direction: row; justify-content: space-between; align-items: flex-start; margin-bottom: 18px; }
        .hd-foto { width: 105px; }
        .hd-par { white-space: normal; }
        .hd-firma { margin: 36px 0 0; }
    }
    /* Al imprimir, la hoja ocupa toda la página A4 y la firma queda abajo */
    @media print {
        .hd-hoja { border: 0; max-width: none; font-size: 14.5px; min-height: 250mm; display: flex; flex-direction: column; padding: 10mm 6mm 4mm; }
        .hd-agua { top: 55%; }
        .hd-logo { width: 150px; }
        .hd-sindicato { margin-bottom: 14mm; }
        .hd-titulo { margin-bottom: 14mm; }
        .hd-cuerpo { grid-template-columns: 1fr 125px; }
        .hd-fila { margin-bottom: 12mm; }
        .hd-firma { margin-top: auto; padding-bottom: 14mm; }
        .hd-pie { margin-top: 0; }
    }
</style>
<div class="hd-hoja">
    <img class="hd-agua" src="<?= asset('vista/img/logo.jpg') ?>" alt=""><?php /* Logo grande y transparente de fondo (marca de agua) */ ?>
    <img class="hd-logo" src="<?= asset('vista/img/logo.jpg') ?>" alt="Logo del sindicato"><?php /* Logo arriba */ ?>
    <div class="hd-sindicato">SINDICATO MIXTO DE TRANSPORTES “VILLA AROMA”</div>
    <div class="hd-titulo">DATOS PERSONALES</div>

    <div class="hd-cuerpo">
        <div>
            <div class="hd-fila">
                <span class="hd-et">Yo:</span>
                <span class="hd-caja hd-nombre"><?= $mayus($chofer['nombres'] . ' ' . $chofer['apellidos']) ?></span><?php /* Nombres y apellidos en mayúsculas */ ?>
                <span class="hd-sub">Nombres y apellidos</span>
            </div>
            <div class="hd-fila">
                <span class="hd-par"><span class="hd-et">Con documento de identidad N°:</span> <span class="hd-caja"><?= $completo ? e($chofer['ci']) : $reservado ?></span></span><?php /* C.I.: se ve solo con sesión; si no, "Reservado" */ ?>
                <span class="hd-par"><span class="hd-et">Expedido en:</span> <span class="hd-caja"><?= $mayus($expedido) ?></span></span><?php /* Departamento de expedición */ ?>
            </div>
            <div class="hd-fila">
                <span class="hd-par"><span class="hd-et">Fecha de Nacimiento:</span> <span class="hd-caja"><?= $completo ? ($chofer['fecha_nacimiento'] ? fecha($chofer['fecha_nacimiento']) : '—') : $reservado ?></span></span><?php /* Fecha de nacimiento: solo con sesión */ ?>
                <span class="hd-par"><span class="hd-et">Licencia de conducir:</span> <span class="hd-caja"><?= $completo ? e($chofer['licencia_numero'] ?: '—') : $reservado ?></span></span><?php /* N° de licencia: solo con sesión */ ?>
            </div>
            <div class="hd-fila">
                <span class="hd-par"><span class="hd-et">Fecha de Ingreso:</span> <span class="hd-caja"><?= e($ingreso) ?></span></span><?php /* Fecha de ingreso o "FUNDADOR" */ ?>
                <span class="hd-par"><span class="hd-et">Grupo:</span> <span class="hd-caja" style="min-width:4em"><?= $mayus($chofer['grupo']) ?></span></span><?php /* Grupo */ ?>
            </div>
        </div>
        <div class="hd-lado">
            <div class="hd-nro"><span>N°</span><span class="hd-caja"><?= str_pad((string) $chofer['id_chofer'], 2, '0', STR_PAD_LEFT) ?></span></div><?php /* Número del afiliado con dos cifras (01, 02...) */ ?>
            <div class="hd-foto"><?= $fotoUrl ? '<img src="' . e($fotoUrl) . '" alt="Foto del afiliado">' : 'FOTO' ?></div><?php /* Foto del chofer o el texto "FOTO" */ ?>
        </div>
    </div>

    <div class="hd-firma">Firma del Afiliado <span></span></div>
    <div class="hd-pie">SECRETARÍA GESTIÓN <?= date('Y') ?></div><?php /* Año de la gestión */ ?>
</div>
