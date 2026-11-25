<div class="card">
    <div class="card-header d-flex justify-content-between">
        <span><i class="bi bi-journal-text"></i> Últimas <?= count($registros) ?> operaciones registradas</span><?php /* Cantidad de registros mostrados */ ?>
        <input type="search" class="form-control form-control-sm w-auto" placeholder="Filtrar…" oninput="filtrar(this.value)">
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm table-hover mb-0 small" id="tabla-bitacora">
                <thead><tr><th>Fecha</th><th>Usuario</th><th>Acción</th><th>Detalle</th><th>IP</th></tr></thead>
                <tbody>
                <?php foreach ($registros as $r): /* Una fila por operación */ ?>
                    <tr>
                        <td class="text-nowrap"><?= fecha($r['fecha'], true) ?></td><?php /* Fecha y hora */ ?>
                        <td><?= e($r['nombre'] ?? '—') ?></td><?php /* Usuario que la hizo */ ?>
                        <td><span class="badge text-bg-<?= str_contains($r['accion'], 'FALLIDO') ? 'danger' : 'light border' ?>"><?= e($r['accion']) ?></span></td><?php /* Acción (en rojo si es un intento fallido) */ ?>
                        <td><?= e($r['detalle']) ?></td><?php /* Detalle */ ?>
                        <td class="text-muted"><?= e($r['ip']) ?></td><?php /* Dirección IP del equipo */ ?>
                    </tr>
                <?php endforeach; /* Fin */ ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<script>
    function filtrar(t) {
        t = t.toLowerCase();
        document.querySelectorAll('#tabla-bitacora tbody tr').forEach(tr =>
            tr.classList.toggle('d-none', !tr.textContent.toLowerCase().includes(t)));
    }
</script>
