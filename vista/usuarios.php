<?php $colores = ['administrador' => 'danger', 'ventanilla' => 'success']; /* Color de cada rol: rojo administrador, verde ventanilla */ ?>
<div class="row g-4">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead><tr><th>Nombre</th><th>Usuario</th><th>Rol</th><th>Último acceso</th><th>Estado</th><th></th></tr></thead>
                        <tbody>
                        <?php foreach ($usuarios as $u): /* Una fila por usuario */ ?>
                            <tr class="<?= $u['activo'] ? '' : 'text-muted' ?>"><?php /* Gris si está desactivado */ ?>
                                <td class="fw-semibold"><?= e($u['nombre']) ?></td><?php /* Nombre */ ?>
                                <td><?= e($u['usuario']) ?></td><?php /* Usuario */ ?>
                                <td><span class="badge text-bg-<?= $colores[$u['rol']] ?> text-capitalize"><?= e($u['rol']) ?></span></td><?php /* Rol con su color */ ?>
                                <td class="small"><?= fecha($u['ultimo_acceso'], true) ?></td><?php /* Fecha y hora de su último ingreso */ ?>
                                <td><span class="badge text-bg-<?= $u['activo'] ? 'success' : 'secondary' ?>"><?= $u['activo'] ? 'Activo' : 'Inactivo' ?></span></td><?php /* Activo o inactivo */ ?>
                                <td class="text-end text-nowrap">
                                    <button class="btn btn-sm btn-light btn-editar" data-usuario='<?= e(json_encode($u)) ?>' title="Editar"><i class="bi bi-pencil"></i></button><?php /* Editar: lleva los datos del usuario en JSON (sin la contraseña) */ ?>
                                    <?php if ($u['id_usuario'] != Auth::id()): /* No se muestran estos botones en el propio usuario conectado */ ?>
                                        <form method="post" action="<?= url('usuarios/estado') ?>" class="d-inline" data-confirm="¿<?= $u['activo'] ? 'Desactivar' : 'Activar' ?> a <?= e($u['nombre']) ?>?"><?php /* Activar o desactivar (pide confirmación) */ ?>
                                            <?= Csrf::campo() ?><input type="hidden" name="id" value="<?= $u['id_usuario'] ?>"><?php /* Token CSRF y número del usuario */ ?>
                                            <button class="btn btn-sm btn-light" title="<?= $u['activo'] ? 'Desactivar' : 'Activar' ?>"><i class="bi bi-<?= $u['activo'] ? 'person-slash text-warning' : 'person-check text-success' ?>"></i></button><?php /* Ícono según el estado */ ?>
                                        </form>
                                        <form method="post" action="<?= url('usuarios/eliminar') ?>" class="d-inline" data-confirm="¿Eliminar a <?= e($u['nombre']) ?>?" data-confirm-texto="Sus registros de pagos y de la bitácora se conservarán."><?php /* Eliminar (pide confirmación) */ ?>
                                            <?= Csrf::campo() ?><input type="hidden" name="id" value="<?= $u['id_usuario'] ?>"><?php /* Token CSRF y número del usuario */ ?>
                                            <button class="btn btn-sm btn-light text-danger" title="Eliminar"><i class="bi bi-trash"></i></button>
                                        </form>
                                    <?php endif; /* Fin */ ?>
                                </td>
                            </tr>
                        <?php endforeach; /* Fin de la lista */ ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="card mt-4">
            <div class="card-header"><i class="bi bi-shield-lock"></i> Matriz de roles y permisos</div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0 small">
                    <thead><tr><th>Módulo</th><th>Secretario(a) de Ventanilla</th><th>Administrador</th></tr></thead>
                    <tbody>
                    <tr><td>Choferes y credencial QR</td><td>Añadir, modificar y eliminar</td><td>Total</td></tr>
                    <tr><td>Lectura QR en ventanilla</td><td>Sí</td><td>Sí</td></tr>
                    <tr><td>Documentos y alertas</td><td>Digitalizar, modificar y eliminar</td><td>Total</td></tr>
                    <tr><td>Cuotas y pagos</td><td>Parametrizar cuotas y registrar pagos</td><td>Total</td></tr>
                    <tr><td>Emisión de reportes</td><td>Todos los reportes</td><td>Total</td></tr>
                    <tr><td>Usuarios, bitácora y copias de seguridad</td><td>No</td><td>Total</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card">
            <div class="card-header" id="form-titulo"><i class="bi bi-person-plus"></i> Nuevo usuario</div>
            <div class="card-body">
                <form method="post" action="<?= url('usuarios/guardar') ?>" id="form-usuario" class="needs-validation" novalidate><?php /* Envía a usuarios/guardar */ ?>
                    <?= Csrf::campo() /* Token CSRF */ ?>
                    <input type="hidden" name="id_usuario" value="0">
                    <div class="mb-3"><label class="form-label">Nombre completo *</label><input name="nombre" class="form-control" required maxlength="100" pattern="<?= Bolivia::html('NOMBRE') ?>" data-solo="letras" value="<?= old('nombre') ?>"><div class="invalid-feedback">Solo letras (nombre y apellidos).</div></div><?php /* Nombre completo */ ?>
                    <div class="mb-3"><label class="form-label">Usuario *</label><input name="usuario" class="form-control" required maxlength="50" pattern="[a-zA-Z0-9._\-]+" autocomplete="off" value="<?= old('usuario') ?>"></div><?php /* Nombre de usuario */ ?>
                    <div class="mb-3">
                        <label class="form-label">Rol *</label>
                        <select name="rol" class="form-select" required>
                            <?php foreach ($roles as $r): ?><option value="<?= $r ?>" <?= old('rol') === $r ? 'selected' : '' ?>><?= ucfirst($r) ?></option><?php endforeach; /* Una opción por rol */ ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Contraseña <span id="pass-req">*</span></label>
                        <input type="password" name="password" class="form-control" minlength="8" required autocomplete="new-password">
                        <div class="form-text" id="pass-ayuda">Mínimo 8 caracteres. Se guarda cifrada (bcrypt).</div>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-light" id="btn-nuevo">Limpiar</button>
                        <button class="btn btn-primary flex-grow-1"><i class="bi bi-save"></i> Guardar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<?php limpiar_old(); /* Borra los datos de un error anterior */ ?>
<script>
    (() => {
        const f = document.getElementById('form-usuario');
        document.querySelectorAll('.btn-editar').forEach(btn => btn.addEventListener('click', () => {
            const u = JSON.parse(btn.dataset.usuario);
            f.id_usuario.value = u.id_usuario; f.nombre.value = u.nombre; f.usuario.value = u.usuario; f.rol.value = u.rol;
            f.password.value = ''; f.password.required = false;
            document.getElementById('pass-req').textContent = '';
            document.getElementById('pass-ayuda').textContent = 'Déjela vacía para mantener la contraseña actual.';
            document.getElementById('form-titulo').innerHTML = '<i class="bi bi-pencil"></i> Editar ' + App.escapar(u.nombre);
        }));
        document.getElementById('btn-nuevo').addEventListener('click', () => {
            f.reset(); f.id_usuario.value = 0; f.password.required = true;
            document.getElementById('pass-req').textContent = '*';
            document.getElementById('pass-ayuda').textContent = 'Mínimo 8 caracteres. Se guarda cifrada (bcrypt).';
            document.getElementById('form-titulo').innerHTML = '<i class="bi bi-person-plus"></i> Nuevo usuario';
        });
    })();
</script>
