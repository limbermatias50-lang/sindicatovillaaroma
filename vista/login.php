<?php $titulo = 'Iniciar sesión'; /* Título de la pestaña */ ?>
<div class="card login-card w-100 shadow-lg mt-5">
    <div class="card-body p-4 pt-0 text-center">
        <img src="<?= asset('vista/img/logo.jpg') ?>" alt="Logo Sindicato Villa Aroma" class="logo mb-3"><?php /* Logo del sindicato */ ?>
        <h1 class="h5 fw-bold text-primary mb-0">Sindicato Mixto de Transporte</h1>
        <div class="h4 fw-bold mb-1" style="color: var(--va-rojo)">"Villa Aroma"</div>
        <p class="text-muted small mb-4">Control de choferes, aportes y documentación</p>

        <form method="post" action="<?= url('login/entrar') ?>" class="text-start needs-validation" novalidate><?php /* Formulario: se envía por POST a login/entrar; needs-validation = revisa los campos antes de enviar */ ?>
            <?= Csrf::campo() /* Campo oculto con el token CSRF */ ?>
            <div class="mb-3">
                <label class="form-label" for="usuario">Usuario</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-person"></i></span>
                    <input id="usuario" name="usuario" class="form-control" required autocomplete="username" value="<?= old('usuario') ?>" autofocus><?php /* Si hubo error, vuelve a mostrar el usuario escrito (nunca la contraseña) */ ?>
                </div>
            </div>
            <div class="mb-4">
                <label class="form-label" for="password">Contraseña</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-lock"></i></span>
                    <input id="password" type="password" name="password" class="form-control" required autocomplete="current-password">
                    <button class="btn btn-outline-secondary" type="button" onclick="const p=document.getElementById('password');p.type=p.type==='password'?'text':'password'" title="Mostrar contraseña"><i class="bi bi-eye"></i></button>
                </div>
            </div>
            <button class="btn btn-primary w-100 py-2"><i class="bi bi-box-arrow-in-right"></i> Ingresar</button>
        </form>
    </div>
    <div class="cinta-bolivia"></div>
</div>
<?php limpiar_old(); /* Borra los datos guardados del formulario */ ?>
