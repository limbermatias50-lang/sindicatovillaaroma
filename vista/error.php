<?php
/** Páginas de error: 404 (no existe), 403 (sin permiso) y 500 (error del sistema). Variables: $codigo, $mensaje */
$codigo = $codigo ?? 404;   // Código de error (404 si no se indicó)
[$titulo, $icono] = [404 => ['Página no encontrada', 'bi-signpost-split'], 403 => ['Acceso denegado', 'bi-shield-lock'], 500 => ['Error del sistema', 'bi-bug']][$codigo] ?? ['Error', 'bi-exclamation-circle'];   // Título e ícono según el código
?>
<div class="card text-center p-4 mx-auto" style="max-width: 460px">
    <i class="bi <?= $icono ?> display-3 text-primary mb-2"></i><?php /* Ícono del error */ ?>
    <h1 class="h4"><?= $codigo ?> · <?= e($titulo) ?></h1><?php /* Código y título */ ?>
    <p class="text-muted"><?= e($mensaje) ?></p><?php /* Mensaje del error (escapado con e()) */ ?>
    <a href="<?= url(Auth::check() ? 'inicio' : 'login') ?>" class="btn btn-primary"><i class="bi bi-house"></i> Volver al inicio</a><?php /* Botón para volver: al panel si hay sesión, al login si no */ ?>
</div>
