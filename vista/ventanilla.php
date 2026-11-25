<?php $scripts = ['vista/lib/html5-qrcode.min.js']; /* Esta pantalla necesita la librería Html5-Qrcode 2.3.8 para leer el QR con la cámara */ ?>
<div class="row g-4">
    <!-- Lectura de la credencial -->
    <div class="col-lg-5">
        <div class="card h-100">
            <div class="card-header d-flex align-items-center">
                <i class="bi bi-qr-code-scan me-2 text-primary"></i> Identificar chofer
            </div>
            <div class="card-body">
                <div id="lector" class="lector-qr d-flex align-items-center justify-content-center mb-3">
                    <div class="text-center text-muted p-4" id="lector-placeholder">
                        <i class="bi bi-camera-video display-4 d-block mb-2"></i>
                        Presione <b>Activar cámara</b> y acerque la credencial QR
                    </div>
                </div>
                <button type="button" class="btn btn-primary w-100 mb-3" id="btn-camara">
                    <i class="bi bi-camera"></i> <span>Activar cámara</span>
                </button>

                <label class="form-label small text-muted mb-1" for="codigo-manual">
                    <i class="bi bi-usb-symbol"></i> Lector USB, código QR o C.I. del chofer (presione Enter)
                </label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-upc-scan"></i></span>
                    <input type="text" id="codigo-manual" class="form-control text-uppercase" placeholder="Código QR o C.I." autocomplete="off" autofocus>
                </div>
                <div class="form-text">Si el chofer olvidó su credencial, escriba su C.I.</div>
                <div id="cache-pendientes" class="alert alert-warning small mt-3 mb-0 d-none"><i class="bi bi-cloud-slash"></i> Pagos guardados en la caché local, pendientes de sincronizar: <b>0</b></div>
            </div>
        </div>
    </div>

    <!-- Resultado -->
    <div class="col-lg-7">
        <div class="card h-100" id="panel-resultado">
            <div class="card-body d-flex flex-column align-items-center justify-content-center text-muted text-center py-5" id="estado-vacio">
                <i class="bi bi-person-badge display-3 mb-3"></i>
                <p class="mb-0">Escanee una credencial para ver los datos del chofer,<br>su licencia y sus cuotas pendientes.</p>
            </div>
            <div id="resultado" class="d-none"></div>
        </div>
    </div>
</div>
