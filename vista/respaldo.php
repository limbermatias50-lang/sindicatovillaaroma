<div class="row g-4">
    <div class="col-lg-7">
        <div class="card">
            <div class="card-header"><i class="bi bi-database"></i> Contenido de la base de datos</div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0">
                    <thead><tr><th>Tabla</th><th class="text-end">Registros</th></tr></thead>
                    <tbody>
                    <?php foreach ($tablas as $t): /* Una fila por tabla de la base de datos */ ?>
                        <tr><td><?= e($t['nombre']) ?></td><td class="text-end"><?= number_format($t['filas'], 0, ',', '.') ?></td></tr><?php /* Nombre de la tabla y cantidad de registros */ ?>
                    <?php endforeach; /* Fin */ ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card mb-4">
            <div class="card-header"><i class="bi bi-database-down"></i> Generar copia de seguridad</div>
            <div class="card-body">
                <p class="small text-muted">Descarga un archivo <b>.sql</b> con la estructura y todos los datos de la base de datos.
                    Para restaurarlo, impórtelo desde phpMyAdmin (pestaña <i>Importar</i>).</p>
                <a href="<?= url('respaldo/descargar') ?>" class="btn btn-primary w-100"><i class="bi bi-download"></i> Descargar base de datos (.sql)</a><?php /* Botón para descargar la base de datos (.sql) */ ?>
                <hr>
                <p class="small"><i class="bi bi-folder2"></i> Documentos digitalizados en el servidor: <b><?= $archivos ?></b>.<?php /* Cantidad de documentos guardados */ ?>
                    Para restaurarlos, descomprima el .zip dentro de la carpeta <code>sis_villaaroma</code>.</p>
                <a href="<?= url('respaldo/documentos') ?>" class="btn btn-outline-primary w-100"><i class="bi bi-file-zip"></i> Descargar documentos digitalizados (.zip)</a><?php /* Botón para descargar los documentos (.zip) */ ?>
            </div>
        </div>
        <div class="card">
            <div class="card-header"><i class="bi bi-clock-history"></i> Últimas copias generadas</div>
            <ul class="list-group list-group-flush small">
                <?php foreach (array_slice($ultimos, 0, 5) as $r): /* Las últimas 5 copias generadas */ ?>
                    <li class="list-group-item d-flex justify-content-between"><span><?= fecha($r['fecha'], true) ?></span><span class="text-muted"><?= e($r['nombre'] ?? '') ?></span></li><?php /* Fecha y usuario */ ?>
                <?php endforeach; /* Fin */ ?>
                <?php if (!$ultimos): ?><li class="list-group-item text-muted">Aún no se generaron copias de seguridad.</li><?php endif; /* Si todavía no hay copias */ ?>
            </ul>
        </div>
    </div>
</div>
