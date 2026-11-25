/**
 * JAVASCRIPT DEL SISTEMA (un solo archivo para todas las pantallas).
 * Cada parte revisa si su pantalla está abierta antes de ejecutarse:
 *   1. app.js        funciones comunes (AJAX, mensajes SweetAlert2, validación de formularios)
 *   2. lector-qr.js  lectura del código QR con la cámara (Html5-Qrcode) o lector USB
 *   3. ventanilla.js atención en ventanilla, cobro y caché local sin internet
 *   4. documentos.js registro de documentos y licencias
 *   5. dashboard.js  gráficos del panel (Chart.js)
 */
/* ================= app.js ================= */
/**
 * Utilidades compartidas del frontend (sin dependencias salvo SweetAlert2).
 *  - App.url(ruta, params)       genera URLs internas
 *  - App.post(ruta, datos)       petición AJAX con token CSRF
 *  - App.get(ruta, params)       petición AJAX GET (JSON)
 *  - App.toast / App.confirmar   mensajes con SweetAlert2
 *  - Formularios con data-confirm="..." piden confirmación antes de enviarse
 */
const App = (() => {
    const baseUrl = document.querySelector('meta[name="base-url"]')?.content || './';
    const csrf    = document.querySelector('meta[name="csrf-token"]')?.content || '';

    function url(ruta, params = {}) {
        const q = new URLSearchParams({ r: ruta, ...params });
        return `${baseUrl}index.php?${q.toString()}`;
    }

    async function manejar(resp) {
        let data = {};
        try { data = await resp.json(); } catch (_) { data = { ok: false, mensaje: 'Respuesta inesperada del servidor.' }; }
        if (resp.status === 401) {
            await Swal.fire('Sesión expirada', data.mensaje, 'warning');
            location.href = url('login');
        }
        return { status: resp.status, ...data };
    }

    async function get(ruta, params = {}) {
        const resp = await fetch(url(ruta, params), {
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
        });
        return manejar(resp);
    }

    async function post(ruta, datos = {}) {
        const body = datos instanceof FormData ? datos : new FormData();
        if (!(datos instanceof FormData)) {
            Object.entries(datos).forEach(([k, v]) =>
                Array.isArray(v) ? v.forEach(x => body.append(k + '[]', x)) : body.append(k, v));
        }
        body.append('_csrf', csrf);
        const resp = await fetch(url(ruta), {
            method: 'POST',
            body,
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
        });
        return manejar(resp);
    }

    const toastBase = Swal.mixin({ toast: true, position: 'top-end', showConfirmButton: false, timer: 3500, timerProgressBar: true });

    function toast(icono, texto) {
        toastBase.fire({ icon: icono, title: texto });
    }

    async function confirmar(titulo, texto = '', icono = 'question', botonOk = 'Sí, continuar') {
        const r = await Swal.fire({
            title: titulo, html: texto, icon: icono, showCancelButton: true,
            confirmButtonText: botonOk, cancelButtonText: 'Cancelar',
            confirmButtonColor: '#1b3a8f', cancelButtonColor: '#6c757d', reverseButtons: true,
        });
        return r.isConfirmed;
    }

    function escapar(t) {
        const d = document.createElement('div');
        d.textContent = t ?? '';
        return d.innerHTML;
    }

    function bs(monto) {
        return 'Bs ' + Number(monto || 0).toLocaleString('es-BO', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    // Mensajes flash enviados por el servidor
    function mostrarFlashes() {
        const nodo = document.getElementById('flash-data');
        if (!nodo) return;
        const lista = JSON.parse(nodo.textContent || '[]');
        const iconos = { success: 'success', error: 'error', warning: 'warning', info: 'info' };
        lista.forEach(f => {
            if (f.tipo === 'error' || f.titulo) {
                Swal.fire({ icon: iconos[f.tipo] || 'info', title: f.titulo || 'Atención', html: f.mensaje, confirmButtonColor: '#1b3a8f' });
            } else {
                toast(iconos[f.tipo] || 'info', f.mensaje);
            }
        });
    }

    // Abre la pestaña indicada en la URL (p. ej. choferes/ver#tab-docs desde «Ver expediente»)
    if (location.hash && window.bootstrap) {
        const tab = document.querySelector(`[data-bs-target="${CSS.escape(location.hash)}"]`);
        if (tab) bootstrap.Tab.getOrCreateInstance(tab).show();
    }

    // Tablas con selección de fila + botón «Ver detalle» / «Ver expediente» (Figuras 29 y 38)
    document.querySelectorAll('.tabla-seleccion').forEach(tabla => {
        const boton = document.querySelector('.btn-ver-seleccion');
        let url = null;
        tabla.addEventListener('click', (ev) => {
            const fila = ev.target.closest('tr[data-url]');
            if (!fila || ev.target.closest('a')) return;
            tabla.querySelectorAll('tr.table-active').forEach(t => t.classList.remove('table-active'));
            fila.classList.add('table-active');
            fila.querySelector('input[type=radio]').checked = true;
            url = fila.dataset.url;
            if (boton) boton.disabled = false;
        });
        tabla.addEventListener('dblclick', (ev) => {
            const fila = ev.target.closest('tr[data-url]');
            if (fila) location.href = fila.dataset.url;
        });
        boton?.addEventListener('click', () => { if (url) location.href = url; });
    });

    // Confirmación para formularios con acciones irreversibles
    document.addEventListener('submit', async (ev) => {
        const form = ev.target;
        if (!form.dataset.confirm || form.dataset.confirmado) return;
        ev.preventDefault();
        if (await confirmar(form.dataset.confirm, form.dataset.confirmTexto || '', 'warning')) {
            form.dataset.confirmado = '1';
            form.requestSubmit ? form.requestSubmit() : form.submit();
        }
    });

    // Validación visual de Bootstrap para formularios con .needs-validation
    document.addEventListener('submit', (ev) => {
        const form = ev.target;
        if (form.classList.contains('needs-validation') && !form.checkValidity()) {
            ev.preventDefault();
            ev.stopImmediatePropagation();
            form.classList.add('was-validated');
            // Tablas 19, 21 y 28: mensaje único cuando faltan datos o son inválidos
            Swal.fire({ icon: 'error', title: 'Datos incorrectos', text: 'Revise los campos marcados en rojo.', confirmButtonColor: '#1b3a8f' })
                .then(() => form.querySelector(':invalid')?.focus());
        }
    }, true);

    /*
     * Filtros de escritura según las reglas bolivianas (controlador/Validacion.php):
     *   data-solo="letras"        nombres y apellidos: no deja escribir números ni símbolos
     *   data-solo="digitos"       C.I. y celular: solo números
     *   data-solo="alfanumerico"  complemento del C.I.: letras y números en mayúscula
     */
    const filtros = {
        letras: (v) => v.replace(/[^A-Za-zÁÉÍÓÚÜÑáéíóúüñ '\-]/g, '').replace(/\s{2,}/g, ' '),
        digitos: (v) => v.replace(/\D/g, ''),
        alfanumerico: (v) => v.toUpperCase().replace(/[^0-9A-Z]/g, ''),
    };
    document.addEventListener('input', (ev) => {
        const el = ev.target, f = filtros[el.dataset?.solo];
        if (!f) return;
        const limpio = f(el.value);
        if (limpio !== el.value) {
            el.value = limpio;
            el.classList.add('is-invalid');
            setTimeout(() => el.classList.remove('is-invalid'), 700);   // aviso breve de carácter no permitido
        }
    });

    document.addEventListener('DOMContentLoaded', mostrarFlashes);

    return { url, get, post, toast, confirmar, escapar, bs, csrf };
})();

/* ================= lector-qr.js ================= */
/**
 * Componente reutilizable de lectura QR (Html5-Qrcode 2.3.8).
 * Admite dos formas de lectura (RF02):
 *   1. Cámara web del equipo o celular.
 *   2. Lector USB: actúa como teclado y escribe el código + Enter en un campo de texto.
 *
 *   const lector = new LectorQR({ contenedor: 'lector', campo: 'codigo-manual', alLeer: codigo => {...} });
 *   lector.iniciarCamara();
 */
class LectorQR {
    constructor({ contenedor, campo = null, alLeer }) {
        this.contenedorId = contenedor;
        this.alLeer = alLeer;
        this.escaner = null;
        this.activo = false;
        this.ultimo = { codigo: '', t: 0 };

        if (campo) {
            const input = document.getElementById(campo);
            input?.addEventListener('keydown', (ev) => {
                if (ev.key === 'Enter') {
                    ev.preventDefault();
                    const codigo = input.value.trim();
                    if (codigo) this.emitir(codigo);
                    input.value = '';
                }
            });
        }
    }

    /**
     * El QR de la credencial trae la dirección de la página de verificación;
     * de ese texto solo interesa el código VA-XXXXXXXXXX.
     */
    static extraerCodigo(texto) {
        const valor = texto.trim().toUpperCase();
        const m = valor.match(/VA-[A-Z0-9]{10}/);
        if (m) return m[0];
        return /\s/.test(valor) ? null : valor;   // código digitado a mano
    }

    /** Evita procesar la misma lectura varias veces seguidas. */
    emitir(texto) {
        const codigo = LectorQR.extraerCodigo(texto);
        if (!codigo) return;
        const ahora = Date.now();
        if (codigo === this.ultimo.codigo && ahora - this.ultimo.t < 3000) return;
        this.ultimo = { codigo, t: ahora };
        this.alLeer(codigo);
    }

    async iniciarCamara() {
        if (this.activo) return;
        if (!window.isSecureContext) {
            Swal.fire('Cámara no disponible', 'La cámara solo funciona en <b>localhost</b> o con <b>HTTPS</b>. Use el lector USB o escriba el código.', 'info');
            return;
        }
        this.escaner = this.escaner || new Html5Qrcode(this.contenedorId);
        try {
            await this.escaner.start(
                { facingMode: 'environment' },
                { fps: 10, qrbox: { width: 230, height: 230 } },
                (texto) => this.emitir(texto),
                () => {}   // errores de lectura por cuadro: se ignoran
            );
            this.activo = true;
            document.dispatchEvent(new CustomEvent('lector:estado', { detail: true }));
        } catch (e) {
            Swal.fire('Cámara', 'No se pudo abrir la cámara: ' + e, 'error');
        }
    }

    async detenerCamara() {
        if (this.escaner && this.activo) {
            await this.escaner.stop();
            this.escaner.clear();
            this.activo = false;
            document.dispatchEvent(new CustomEvent('lector:estado', { detail: false }));
        }
    }

    alternar() {
        return this.activo ? this.detenerCamara() : this.iniciarCamara();
    }
}

/* ================= ventanilla.js ================= */
/**
 * Pantalla de atención en ventanilla (CU03 + CU06):
 * lee el QR, carga el expediente del chofer y permite cobrar sus cuotas pendientes.
 */
(() => {
    const resultado = document.getElementById('resultado');
    const vacio     = document.getElementById('estado-vacio');
    const btnCamara = document.getElementById('btn-camara');
    if (!resultado || !btnCamara) return;   // solo en la pantalla de ventanilla
    let choferActual = null;

    /**
     * Caché local de pagos (conexión intermitente en Lahuachaca, RNF04).
     * Los pagos que no se pudieron enviar se guardan en localStorage y se
     * sincronizan al recuperar la conexión; el servidor impide duplicados.
     */
    const Cache = {
        clave: 'villa_aroma_pagos_pendientes',
        leer() { try { return JSON.parse(localStorage.getItem(this.clave)) || []; } catch (_) { return []; } },
        escribir(lista) { try { localStorage.setItem(this.clave, JSON.stringify(lista)); } catch (_) {} this.indicador(); },
        guardar(pago) { const l = this.leer(); l.push(pago); this.escribir(l); },
        indicador() {
            const n = this.leer().length, nodo = document.getElementById('cache-pendientes');
            if (!nodo) return;
            nodo.classList.toggle('d-none', n === 0);
            nodo.querySelector('b').textContent = n;
        },
        async sincronizar() {
            const lista = this.leer();
            if (!lista.length || !navigator.onLine) return;
            const quedan = [];
            let enviados = 0;
            for (const p of lista) {
                try {
                    const r = await App.post('pagos/registrar', { pagos: p.pagos });
                    if (r.ok || r.status === 409) enviados++; else quedan.push(p);
                } catch (_) { quedan.push(p); }
            }
            this.escribir(quedan);
            if (enviados) {
                App.toast('success', `${enviados} pago(s) de la caché local sincronizado(s).`);
                if (choferActual) buscar(choferActual.chofer.codigo);
            }
        },
    };
    window.addEventListener('online', () => Cache.sincronizar());
    Cache.indicador();
    Cache.sincronizar();

    const lector = new LectorQR({ contenedor: 'lector', campo: 'codigo-manual', alLeer: buscar });

    btnCamara.addEventListener('click', () => {
        document.getElementById('lector-placeholder')?.remove();
        lector.alternar();
    });
    document.addEventListener('lector:estado', (e) => {
        btnCamara.querySelector('span').textContent = e.detail ? 'Detener cámara' : 'Activar cámara';
        btnCamara.classList.toggle('btn-danger', e.detail);
        btnCamara.classList.toggle('btn-primary', !e.detail);
    });

    async function buscar(codigo) {
        const r = await App.get('choferes/buscar-qr', { qr: codigo });
        if (!r.ok) {
            Swal.fire({ icon: 'error', title: 'Credencial no encontrada', text: 'Verifique el código QR o el C.I. del chofer.', timer: 3000, confirmButtonColor: '#1b3a8f' });   // CP02
            return;
        }
        choferActual = r;
        pintar(r);
        App.toast('success', 'Chofer identificado');
    }

    function pintar({ chofer, saldo, cuotas, puedeCobrar }) {
        const esc = App.escapar;
        const lic = chofer.licencia;
        const estadoBadge = { activo: 'success', suspendido: 'warning', inactivo: 'secondary' }[chofer.estado] || 'secondary';

        const filas = cuotas.length ? cuotas.map(c => `
            <label class="list-group-item cuota-item d-flex align-items-center gap-3">
                ${puedeCobrar ? `<input class="form-check-input m-0 chk-cuota" type="checkbox" value="${c.id_pago}" data-monto="${c.monto}" checked>` : ''}
                <span class="flex-grow-1">
                    <span class="fw-semibold">${esc(c.concepto)}</span>
                    <span class="badge ${c.tipo === 'extraordinaria' ? 'text-bg-warning' : 'text-bg-light border'} ms-1">${esc(c.tipo)}</span><br>
                    <small class="${c.vencida ? 'text-danger fw-semibold' : 'text-muted'}">
                        ${c.vencida ? '<i class="bi bi-exclamation-circle"></i> Vencida el' : 'Fecha límite:'} ${esc(c.limite)}
                    </small>
                </span>
                <span class="fw-bold text-nowrap">${App.bs(c.monto)}</span>
            </label>`).join('')
            : `<div class="list-group-item text-success text-center py-4"><i class="bi bi-check-circle fs-3 d-block"></i>El chofer no tiene cuotas pendientes.</div>`;

        resultado.innerHTML = `
            <div class="card-body chofer-card">
                <div class="d-flex gap-3 align-items-center mb-3">
                    ${chofer.foto ? `<img src="${esc(chofer.foto)}" alt="Foto" class="rounded border" style="width:72px;height:86px;object-fit:cover">`
                                  : `<div class="foto">${esc(chofer.nombre.charAt(0))}</div>`}
                    <div class="flex-grow-1">
                        <h2 class="h5 mb-1">${esc(chofer.nombre)}</h2>
                        <div class="text-muted small">C.I. ${esc(chofer.ci)} · ${esc(chofer.codigo)}</div>
                        <span class="badge text-bg-${estadoBadge} text-capitalize">${esc(chofer.estado)}</span>
                        <span class="badge text-bg-primary">${esc(chofer.grupo)}</span>
                    </div>
                    <a class="btn btn-outline-primary btn-sm" href="${chofer.url}"><i class="bi bi-folder2-open"></i> Expediente</a>
                    <a class="btn btn-outline-primary btn-sm" href="${chofer.hoja}" target="_blank"><i class="bi bi-person-vcard"></i> Hoja de datos</a>
                </div>

                <div class="row g-2 mb-3 small">
                    <div class="col-sm-6">
                        <div class="border rounded p-2 h-100">
                            <div class="text-muted"><i class="bi bi-person-badge"></i> C.I. / Teléfono</div>
                            <div class="fw-semibold">${esc(chofer.ci)}${chofer.telefono ? ' · ' + esc(chofer.telefono) : ''}</div>
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="border rounded p-2 h-100 border-${lic.clase}">
                            <div class="text-muted"><i class="bi bi-person-vcard"></i> Licencia ${esc(lic.numero || '')}</div>
                            <div class="fw-semibold text-${lic.clase}">${esc(lic.texto)} ${lic.numero ? '(' + esc(lic.vencimiento) + ')' : ''}</div>
                        </div>
                    </div>
                </div>

                <div class="d-flex justify-content-between align-items-end mb-2">
                    <h3 class="h6 mb-0">Cuotas pendientes</h3>
                    <div class="text-end small">
                        Deuda total: <b class="text-danger">${App.bs(saldo.pendiente)}</b>
                        ${Number(saldo.vencido) > 0 ? `<br><span class="text-danger">en mora: ${App.bs(saldo.vencido)}</span>` : ''}
                    </div>
                </div>
                <div class="list-group mb-3">${filas}</div>

                ${puedeCobrar && cuotas.length ? `
                <div class="d-flex align-items-center justify-content-between bg-light rounded p-3">
                    <div>A cobrar: <span class="fs-4 fw-bold text-primary" id="total-cobro">${App.bs(0)}</span></div>
                    <button class="btn btn-success btn-lg" id="btn-cobrar"><i class="bi bi-cash-stack"></i> Registrar pago</button>
                </div>` : ''}
                ${chofer.estado !== 'activo' ? `<div class="alert alert-warning mt-3 mb-0"><i class="bi bi-exclamation-triangle"></i> El chofer se encuentra <b>${esc(chofer.estado)}</b>.</div>` : ''}
                ${lic.clase === 'danger' ? `<div class="alert alert-danger mt-3 mb-0"><i class="bi bi-sign-stop"></i> La licencia de conducir está vencida. Solicite su renovación.</div>` : ''}
            </div>`;

        vacio.classList.add('d-none');
        resultado.classList.remove('d-none');
        resultado.querySelectorAll('.chk-cuota').forEach(ch => ch.addEventListener('change', actualizarTotal));
        document.getElementById('btn-cobrar')?.addEventListener('click', cobrar);
        actualizarTotal();
    }

    function seleccionadas() {
        return [...resultado.querySelectorAll('.chk-cuota:checked')];
    }

    function actualizarTotal() {
        const total = seleccionadas().reduce((s, ch) => s + Number(ch.dataset.monto), 0);
        const nodo = document.getElementById('total-cobro');
        if (nodo) nodo.textContent = App.bs(total);
        const btn = document.getElementById('btn-cobrar');
        if (btn) btn.disabled = total <= 0;
    }

    async function cobrar() {
        const marcadas = seleccionadas();
        const total = marcadas.reduce((s, ch) => s + Number(ch.dataset.monto), 0);
        const ok = await App.confirmar(
            'Confirmar pago',
            `Registrar <b>${marcadas.length}</b> cuota(s) de <b>${App.escapar(choferActual.chofer.nombre)}</b><br>por un total de <b>${App.bs(total)}</b> recibidos en efectivo.`,
            'question', 'Sí, registrar pago'
        );
        if (!ok) return;

        const btn = document.getElementById('btn-cobrar');
        btn.disabled = true;
        const datos = { pagos: marcadas.map(ch => ch.value) };
        let r;
        try {
            if (!navigator.onLine) throw new TypeError('Sin conexión');
            r = await App.post('pagos/registrar', datos);
        } catch (_) {
            // Sin internet: el pago se guarda en la caché local y se sincroniza al volver la conexión
            Cache.guardar({ ...datos, chofer: choferActual.chofer.nombre, total, fecha: new Date().toISOString() });
            Swal.fire({ icon: 'warning', title: 'Sin conexión a internet',
                html: 'El pago se guardó en la <b>caché local</b> del navegador y se registrará automáticamente al restablecerse la conexión.',
                confirmButtonColor: '#1b3a8f' });
            btn.disabled = false;
            return;
        }
        if (!r.ok) {
            Swal.fire('No se registró el pago', r.mensaje, 'error');
            btn.disabled = false;
            return;
        }
        const res = await Swal.fire({
            icon: 'success', title: 'Pago registrado', html: App.escapar(r.mensaje),
            showCancelButton: true, confirmButtonText: '<i class="bi bi-printer"></i> Imprimir recibo',
            cancelButtonText: 'Cerrar', confirmButtonColor: '#1b3a8f',
        });
        if (res.isConfirmed) window.open(r.reciboUrl, '_blank');
        buscar(choferActual.chofer.codigo);   // refresca saldo y cuotas
    }
})();

/* ================= documentos.js ================= */
/**
 * Registro de documentos (Figura 28, Tabla 25):
 *  - Busca al chofer por C.I. o código QR (mismo servicio que la ventanilla).
 *  - Si el tipo es «Licencia de conducir» pide categoría y vencimiento, que alimentan las alertas.
 *  - Las actas son institucionales: se oculta el selector de chofer.
 */
(() => {
    const tipo = document.getElementById('tipo-doc');
    if (!tipo) return;
    const bloqueChofer = document.getElementById('bloque-chofer');
    const bloqueLic    = document.getElementById('bloque-licencia');
    const selChofer    = document.getElementById('sel-chofer-doc');
    const vence        = document.getElementById('vence-doc');
    const lblVence     = document.getElementById('lbl-vence-doc');
    const lblFecha     = document.getElementById('lbl-fecha-doc');
    const nroLic       = document.getElementById('nro-licencia');
    const categoria    = bloqueLic.querySelector('select[name=categoria]');

    const actualizarNro = () => { nroLic.value = selChofer.selectedOptions[0]?.dataset.ci || ''; };

    const cambiar = () => {
        const esLic = tipo.value === 'licencia';
        bloqueChofer.classList.toggle('d-none', tipo.value === 'acta');
        bloqueLic.classList.toggle('d-none', !esLic);
        vence.required = esLic;
        categoria.required = esLic;
        lblVence.textContent = esLic ? 'Vencimiento de la licencia *' : 'Vence (opcional)';
        lblFecha.textContent = esLic ? 'Fecha de emisión' : 'Fecha del documento';
        actualizarNro();
    };
    tipo.addEventListener('change', cambiar);
    selChofer.addEventListener('change', actualizarNro);
    cambiar();

    const entrada = document.getElementById('buscar-chofer-doc');
    const buscar = async () => {
        const valor = entrada.value.trim();
        if (!valor) return;
        // El lector QR puede entregar la URL completa de verificación: se extrae el código
        const codigo = (valor.match(/VA-[A-Z0-9]{10}/i) || [valor])[0];
        const r = await App.get('choferes/buscar-qr', { qr: codigo });
        if (!r.ok) {
            Swal.fire({ icon: 'error', title: 'Credencial no encontrada', text: 'Verifique el C.I. o el código QR.', confirmButtonColor: '#1b3a8f' });
            return;
        }
        selChofer.value = String(r.chofer.id);
        actualizarNro();
        App.toast('success', 'Chofer: ' + r.chofer.nombre);
    };
    document.getElementById('btn-buscar-chofer-doc').addEventListener('click', buscar);
    entrada.addEventListener('keydown', (e) => { if (e.key === 'Enter') { e.preventDefault(); buscar(); } });
})();

/* ================= dashboard.js ================= */
/**
 * Gráficos del panel principal y de reportes con Chart.js 4.5.1.
 * Lee los datos del bloque <script type="application/json" id="datos-graficos">.
 */
(() => {
    const nodo = document.getElementById('datos-graficos');
    if (!nodo || typeof Chart === 'undefined') return;
    const datos = JSON.parse(nodo.textContent);

    const AZUL = '#1b3a8f', ROJO = '#d62828', AMARILLO = '#f4c20d', VERDE = '#198754';
    const MESES = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
    Chart.defaults.font.family = getComputedStyle(document.body).fontFamily;
    const moneda = (v) => 'Bs ' + Number(v).toLocaleString('es-BO');

    const cMensual = document.getElementById('grafico-mensual');
    if (cMensual && datos.mensual) {
        new Chart(cMensual, {
            type: 'line',
            data: {
                labels: datos.mensual.map(m => { const [a, mm] = m.mes.split('-'); return `${MESES[+mm - 1]} ${a.slice(2)}`; }),
                datasets: [{
                    label: 'Ingresos (Bs)', data: datos.mensual.map(m => m.total),
                    borderColor: AZUL, backgroundColor: 'rgba(27,58,143,.12)', fill: true, tension: .3,
                    pointBackgroundColor: AMARILLO, pointBorderColor: AZUL, pointRadius: 5,
                }],
            },
            options: {
                plugins: { legend: { display: false }, tooltip: { callbacks: { label: (c) => moneda(c.parsed.y) } } },
                scales: { y: { beginAtZero: true, ticks: { callback: moneda } } },
            },
        });
    }

    const cGrupos = document.getElementById('grafico-grupos');
    if (cGrupos && datos.grupos) {
        new Chart(cGrupos, {
            type: 'bar',
            data: {
                labels: datos.grupos.map(g => g.nombre),
                datasets: [
                    { label: 'Recaudado', data: datos.grupos.map(g => +g.recaudado), backgroundColor: AZUL },
                    { label: 'Pendiente', data: datos.grupos.map(g => +g.pendiente), backgroundColor: AMARILLO },
                ],
            },
            options: {
                plugins: { tooltip: { callbacks: { label: (c) => `${c.dataset.label}: ${moneda(c.parsed.y)}` } } },
                scales: { y: { beginAtZero: true, ticks: { callback: moneda } } },
            },
        });
    }

    const cAlDia = document.getElementById('grafico-aldia');
    if (cAlDia && datos.alDia) {
        new Chart(cAlDia, {
            type: 'pie',
            data: {
                labels: ['Al día', 'En mora'],
                datasets: [{ data: [datos.alDia.al_dia, datos.alDia.en_mora], backgroundColor: [VERDE, ROJO] }],
            },
            options: { plugins: { tooltip: { callbacks: { label: (c) => `${c.label}: ${c.parsed} chofer(es)` } } } },
        });
    }

    const cMora = document.getElementById('grafico-morosidad');
    if (cMora && datos.grupos) {
        new Chart(cMora, {
            type: 'doughnut',
            data: {
                labels: datos.grupos.map(g => g.nombre),
                datasets: [{ data: datos.grupos.map(g => +g.vencido), backgroundColor: [ROJO, AZUL, AMARILLO, VERDE, '#6f42c1', '#0dcaf0'] }],
            },
            options: { plugins: { tooltip: { callbacks: { label: (c) => `${c.label}: ${moneda(c.parsed)}` } } } },
        });
    }
})();

/* ================= Tamaño máximo de archivos ================= */
// Avisa antes de enviar si el archivo pasa el límite (100 MB), para no esperar la subida en vano
document.addEventListener('change', (ev) => {
    const input = ev.target;
    if (input.type !== 'file' || !input.files.length) return;
    const max = Number(document.querySelector('meta[name="max-archivo"]')?.content || 0);
    const grande = [...input.files].find(f => max && f.size > max);
    if (grande) {
        Swal.fire({ icon: 'error', title: 'Archivo muy grande',
            text: `«${grande.name}» pesa ${(grande.size / 1048576).toFixed(1)} MB. El máximo es ${max / 1048576} MB.`, confirmButtonColor: '#1b3a8f' });
        input.value = '';
    }
});
