import { Dropdown } from "bootstrap";
import { Toast, validarFormulario } from "../funciones";
import Swal from "sweetalert2";
import DataTable from "datatables.net-bs5";
import { lenguaje } from "../lenguaje";
import * as bootstrap from 'bootstrap';

// ========================================
// ✅ REFERENCIAS A ELEMENTOS HTML (con validación)
// ========================================
const fechaInicio = document.getElementById('fechaInicio');
const btnGenerar = document.getElementById('btnGenerar');
const btnConsultar = document.getElementById('btnConsultar');
const btnEliminarSemana = document.getElementById('btnEliminarCiclo');
const btnExportarPDF = document.getElementById('btnExportarPDF');
const btnRegresar = document.getElementById('btnRegresar');
const contenedorResultados = document.getElementById('contenedorResultados');
const loadingOverlay = document.getElementById('loadingOverlay');

// Referencias al modal de grupos (con validación)
const modalElement = document.getElementById('modalSeleccionGrupos');
const modalSeleccionGrupos = modalElement ? new bootstrap.Modal(modalElement) : null;
const btnConfirmarGeneracion = document.getElementById('btnConfirmarGeneracion');
const fechaSemanaModal = document.getElementById('fechaCicloModal');

// Referencias al modal de historial
const modalHistorialElement = document.getElementById('modalHistorialCiclos');
const modalHistorialCiclos = modalHistorialElement ? new bootstrap.Modal(modalHistorialElement) : null;
const fabHistorial = document.getElementById('fabHistorial');
const contenedorHistorial = document.getElementById('contenedorHistorial');

// 🆕 Referencias a elementos de comisiones
const btnComisionFlotante = document.getElementById('btnComisionFlotante');
const btnVerComisionesActivas = document.getElementById('btnVerComisionesActivas');
const btnVerCompensaciones = document.getElementById('btnVerCompensaciones');

const modalComisionElement = document.getElementById('modalComision');
const modalComisionBS = modalComisionElement ? new bootstrap.Modal(modalComisionElement) : null;

const modalComisionesActivasElement = document.getElementById('modalComisionesActivas');
const modalComisionesActivasBS = modalComisionesActivasElement ? new bootstrap.Modal(modalComisionesActivasElement) : null;

const modalCompensacionesElement = document.getElementById('modalCompensaciones');
const modalCompensacionesBS = modalCompensacionesElement ? new bootstrap.Modal(modalCompensacionesElement) : null;


const btnRegistrarComision = document.getElementById('btnRegistrarComision');
const personalComision = document.getElementById('personalComision');
const fechaInicioComision = document.getElementById('fechaInicioComision');
const fechaFinComision = document.getElementById('fechaFinComision');

// Estado de la vista
let estadoVista = 'seleccion';
let fechaActualInfo = null;

// ========================================
// ✨ BOTÓN FLOTANTE - MODAL DE HISTORIAL
// ========================================

// Event listener del botón flotante
if (fabHistorial) {
    fabHistorial.addEventListener('click', () => {
        if (modalHistorialCiclos) {
            modalHistorialCiclos.show();
            cargarHistorialCiclos();
        }
    });
}

// ✨ FUNCIÓN: Cargar historial de ciclos
const cargarHistorialCiclos = async () => {
    if (!contenedorHistorial) return;

    contenedorHistorial.innerHTML = `
        <div class="text-center py-4">
            <div class="spinner-border text-success" role="status">
                <span class="visually-hidden">Cargando...</span>
            </div>
            <p class="mt-3">Cargando ciclos...</p>
        </div>
    `;

    try {
        const response = await fetch('/TERCERA_CIA/API/asignaciones/obtener-todos-ciclos');

        // Verificar si la respuesta es JSON
        const contentType = response.headers.get("content-type");
        if (!contentType || !contentType.includes("application/json")) {
            throw new Error("La respuesta del servidor no es JSON");
        }

        const data = await response.json();

        if (data.codigo === 1 && data.ciclos && data.ciclos.length > 0) {
            mostrarHistorialCiclos(data.ciclos);
        } else {
            contenedorHistorial.innerHTML = `
                <div class="empty-historial">
                    <i class="bi bi-inbox"></i>
                    <h4>No hay ciclos generados</h4>
                    <p>Aún no se han generado ciclos de servicios</p>
                </div>
            `;
        }
    } catch (error) {
        console.error('Error al cargar historial:', error);
        contenedorHistorial.innerHTML = `
            <div class="alert alert-danger">
                <i class="bi bi-exclamation-triangle"></i>
                <strong>Error al cargar el historial</strong>
                <p>${error.message}</p>
                <small>Verifique que la ruta <code>/TERCERA_CIA/API/asignaciones/obtener-todos-ciclos</code> esté configurada correctamente</small>
            </div>
        `;
    }
};

// ✨ FUNCIÓN: Mostrar historial en tabla
const mostrarHistorialCiclos = (ciclos) => {
    if (!contenedorHistorial) return;

    let html = `
        <table class="tabla-historial table table-hover">
            <thead>
                <tr>
                    <th style="width: 80px;">#</th>
                    <th>Periodo del Ciclo</th>
                    <th style="width: 150px;">Estado</th>
                    <th style="width: 120px;">Acciones</th>
                </tr>
            </thead>
            <tbody>
    `;

    ciclos.forEach((ciclo, index) => {
        const numero = String(index + 1).padStart(2, '0');
        const estadoClase = ciclo.activo ? 'activo' : 'finalizado';
        const estadoTexto = ciclo.activo ? 'ACTIVO' : 'FINALIZADO';

        html += `
            <tr>
                <td class="numero-ciclo">${numero}</td>
                <td class="fechas-ciclo">
                    <strong>Ciclo del ${formatearFechaCompleta(ciclo.fecha_inicio)} al ${formatearFechaCompleta(ciclo.fecha_fin)}</strong>
                    <br>
                    <small class="text-muted">
                        <i class="bi bi-people"></i> ${ciclo.personal_involucrado || 0} personas • 
                        <i class="bi bi-list-check"></i> ${ciclo.total_asignaciones || 0} asignaciones
                    </small>
                </td>
                <td>
                    <span class="estado-badge ${estadoClase}">
                        ${estadoTexto}
                    </span>
                </td>
                <td>
                    <button 
                        class="btn btn-consultar-ciclo btn-sm"
                        onclick="consultarCicloDesdeHistorial('${ciclo.fecha_inicio}')"
                    >
                        <i class="bi bi-search"></i> Consultar
                    </button>
                </td>
            </tr>
        `;
    });

    html += `
            </tbody>
        </table>
    `;

    contenedorHistorial.innerHTML = html;
};

// ✨ FUNCIÓN: Consultar ciclo desde historial (GLOBAL)
window.consultarCicloDesdeHistorial = async (fechaInicioCiclo) => {
    // Cerrar modal
    if (modalHistorialCiclos) {
        modalHistorialCiclos.hide();
    }

    // Establecer fecha en el input
    if (fechaInicio) {
        fechaInicio.value = fechaInicioCiclo;
    }

    // Mostrar loading
    mostrarLoading(true);

    try {
        // Consultar servicios
        const url = `/TERCERA_CIA/API/asignaciones/obtener?fecha_inicio=${fechaInicioCiclo}`;
        const respuesta = await fetch(url);
        const data = await respuesta.json();

        mostrarLoading(false);

        if (data.codigo === 1 && data.datos.length > 0) {
            // Mostrar servicios
            mostrarServicios(data.datos, fechaInicioCiclo);
            gestionarBotones('consultando');

            // Toast de confirmación
            Toast.fire({
                icon: 'success',
                title: '✅ Ciclo cargado exitosamente',
                timer: 2000
            });

            // Scroll suave hacia arriba
            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
        } else {
            Swal.fire({
                icon: 'warning',
                title: 'No se encontraron datos',
                text: 'Este ciclo no tiene servicios disponibles',
                confirmButtonColor: '#2d5016'
            });
        }
    } catch (error) {
        mostrarLoading(false);
        console.error('Error:', error);
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'Error al cargar el ciclo'
        });
    }
};

// ✨ FUNCIÓN: Formatear fecha completa
const formatearFechaCompleta = (fecha) => {
    const date = new Date(fecha + 'T00:00:00');
    const dias = ['domingo', 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado'];
    const meses = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];

    const diaSemana = dias[date.getDay()];
    const dia = date.getDate();
    const mes = meses[date.getMonth()];
    const anio = date.getFullYear();

    return `${diaSemana}, ${dia} de ${mes} de ${anio}`;
};

// ========================================
// ✨ FUNCIONES DE PRESETS DE ROTACIÓN
// ========================================

const aplicarPreset = (tipo) => {
    const botones = document.querySelectorAll('.btn-grupo');

    switch (tipo) {
        case 'todos':
            botones.forEach(btn => btn.classList.add('active'));
            break;

        case 'rotacion_a':
            // Solo B y C disponibles (A descansa)
            botones.forEach(btn => {
                const grupo = parseInt(btn.dataset.grupo);
                if ([1, 4, 7].includes(grupo)) {
                    btn.classList.remove('active');
                } else {
                    btn.classList.add('active');
                }
            });
            break;

        case 'rotacion_b':
            // Solo A y C disponibles (B descansa)
            botones.forEach(btn => {
                const grupo = parseInt(btn.dataset.grupo);
                if ([2, 5, 8].includes(grupo)) {
                    btn.classList.remove('active');
                } else {
                    btn.classList.add('active');
                }
            });
            break;

        case 'rotacion_c':
            // Solo A y B disponibles (C descansa)
            botones.forEach(btn => {
                const grupo = parseInt(btn.dataset.grupo);
                if ([3, 6, 9].includes(grupo)) {
                    btn.classList.remove('active');
                } else {
                    btn.classList.add('active');
                }
            });
            break;

        case 'ninguno':
            botones.forEach(btn => btn.classList.remove('active'));
            break;
    }

    actualizarConteoPersonal();
};

// ========================================
// ✨ INICIALIZACIÓN DE EVENT LISTENERS
// ========================================

document.addEventListener('DOMContentLoaded', () => {
    // Event listeners para botones de grupos individuales
    const botonesGrupo = document.querySelectorAll('.btn-grupo');
    botonesGrupo.forEach(btn => {
        btn.addEventListener('click', function () {
            this.classList.toggle('active');
            actualizarConteoPersonal();
        });
    });

    // Event listeners para botones de presets
    const botonesPreset = document.querySelectorAll('.preset-btn');
    botonesPreset.forEach(btn => {
        btn.addEventListener('click', function () {
            const preset = this.dataset.preset;
            aplicarPreset(preset);
        });
    });

    // ✨ NUEVO: Cargar próxima fecha disponible al iniciar
    cargarProximaFechaDisponible();

    // 🆕 Event listeners para comisiones
    if (btnComisionFlotante) {
        btnComisionFlotante.addEventListener('click', abrirModalComision);
    }
    const btnRegistrarComisionDirecto = document.getElementById('btnRegistrarComisionDirecto');
    if (btnRegistrarComisionDirecto) {
        btnRegistrarComisionDirecto.addEventListener('click', abrirModalComision);
    }

    if (btnVerComisionesActivas) {
        btnVerComisionesActivas.addEventListener('click', abrirModalComisionesActivas);
    }

    if (btnVerCompensaciones) {
        btnVerCompensaciones.addEventListener('click', abrirModalCompensaciones);
    }

    if (btnRegistrarComision) {
        btnRegistrarComision.addEventListener('click', registrarComision);
    }

    // Event listeners para actualizar vista previa
    if (personalComision) {
        personalComision.addEventListener('change', actualizarVistaPrevia);
    }

    if (fechaInicioComision) {
        fechaInicioComision.addEventListener('change', actualizarVistaPrevia);
    }

    if (fechaFinComision) {
        fechaFinComision.addEventListener('change', actualizarVistaPrevia);
    }
});

// ========================================
// ✨ NUEVA FUNCIÓN: Cargar próxima fecha disponible
// ========================================

const cargarProximaFechaDisponible = async () => {
    try {
        mostrarLoading(true);

        const response = await fetch('/TERCERA_CIA/API/asignaciones/proxima-fecha');
        const data = await response.json();

        mostrarLoading(false);

        if (data.codigo === 1 && data.data) {
            const info = data.data;

            // Establecer la fecha en el input
            fechaInicio.value = info.proxima_fecha;

            // ✨ MODIFICADO: Mostrar con SweetAlert en lugar de alerta HTML
            if (info.tiene_ciclos) {
                Swal.fire({
                    icon: 'info',
                    title: 'Información del Sistema',
                    html: `
                        <div style="text-align: left;">
                            <p><i class="bi bi-calendar-check"></i> <strong>Último ciclo generado:</strong></p>
                            <p style="margin-left: 20px;">Del ${formatearFecha(info.ultimo_ciclo_inicio)} al ${formatearFecha(info.ultimo_ciclo_fin)}</p>
                            <hr>
                            <p><i class="bi bi-calendar-plus"></i> <strong>Próxima fecha sugerida:</strong></p>
                            <p style="margin-left: 20px; color: #2d5016; font-size: 1.2em; font-weight: bold;">${formatearFecha(info.proxima_fecha)}</p>
                        </div>
                    `,
                    confirmButtonText: 'Entendido',
                    confirmButtonColor: '#2d5016',
                    timer: 8000,
                    timerProgressBar: true
                });
            } else {
                Swal.fire({
                    icon: 'success',
                    title: '¡Sistema Listo!',
                    text: 'No hay ciclos generados. Puede comenzar desde hoy.',
                    confirmButtonText: 'Comenzar',
                    confirmButtonColor: '#2d5016',
                    timer: 5000,
                    timerProgressBar: true
                });
            }

            // Verificar si hay ciclo en la fecha cargada
            await manejarCambioFecha();
        }
    } catch (error) {
        mostrarLoading(false);
        console.error('Error al cargar próxima fecha:', error);
    }
};

// ========================================
// ✅ FUNCIONES BÁSICAS
// ========================================

const mostrarLoading = (mostrar) => {
    if (loadingOverlay) {
        loadingOverlay.style.display = mostrar ? 'flex' : 'none';
    }
};

const gestionarBotones = (contexto) => {
    // Validar que los elementos existan antes de manipularlos
    if (!btnGenerar || !btnConsultar || !btnEliminarSemana ||
        !btnExportarPDF || !btnRegresar) {
        console.warn('⚠️ Algunos botones no están disponibles en el DOM');
        return;
    }

    btnGenerar.style.display = 'none';
    btnConsultar.style.display = 'none';
    btnEliminarSemana.style.display = 'none';
    btnExportarPDF.style.display = 'none';
    btnRegresar.style.display = 'none';

    switch (contexto) {
        case 'semana_existente':
            btnConsultar.style.display = '';
            estadoVista = 'seleccion';
            break;
        case 'semana_nueva':
            btnGenerar.style.display = '';
            estadoVista = 'seleccion';
            break;
        case 'consultando':
            btnExportarPDF.style.display = '';
            btnRegresar.style.display = '';
            estadoVista = 'consultando';
            break;
        case 'generado_nuevo':
            btnExportarPDF.style.display = '';
            btnEliminarSemana.style.display = '';
            btnRegresar.style.display = '';
            estadoVista = 'generando';
            break;
        case 'inicial':
            estadoVista = 'seleccion';
            break;
        case 'fecha_ocupada':
            // No mostrar ningún botón de acción
            estadoVista = 'seleccion';
            break;
    }
};

const verificarSemanaExistente = async (fecha) => {
    try {
        const url = `/TERCERA_CIA/API/asignaciones/obtener?fecha_inicio=${fecha}`;
        const respuesta = await fetch(url);
        const data = await respuesta.json();
        return data.codigo === 1 && data.datos && data.datos.length > 0;
    } catch (error) {
        console.error('Error al verificar semana:', error);
        return false;
    }
};

// ========================================
// ✨ NUEVA FUNCIÓN: Verificar disponibilidad de fecha
// ========================================

const verificarDisponibilidadFecha = async (fecha) => {
    try {
        const url = `/TERCERA_CIA/API/asignaciones/verificar-fecha?fecha=${fecha}`;
        const respuesta = await fetch(url);
        const data = await respuesta.json();

        if (data.codigo === 1) {
            return data.data;
        }

        return null;
    } catch (error) {
        console.error('Error al verificar disponibilidad:', error);
        return null;
    }
};

// ========================================
// ✨ MODIFICADO: Manejo de cambio de fecha con SweetAlert
// ========================================

const manejarCambioFecha = async (mostrarAlertas = true) => {
    if (!fechaInicio) {
        console.warn('⚠️ Input de fecha no encontrado');
        return;
    }

    const fecha = fechaInicio.value;

    if (!fecha) {
        gestionarBotones('inicial');
        return;
    }

    mostrarLoading(true);

    // Verificar disponibilidad de la fecha
    const disponibilidad = await verificarDisponibilidadFecha(fecha);

    if (!disponibilidad) {
        mostrarLoading(false);
        return;
    }

    fechaActualInfo = disponibilidad;

    if (!disponibilidad.disponible) {
        mostrarLoading(false);

        // Solo mostrar SweetAlert si mostrarAlertas es true
        if (mostrarAlertas) {
            await Swal.fire({
                icon: 'warning',
                title: '⚠️ Fecha No Disponible',
                html: `
                    <div style="text-align: left;">
                        <p><i class="bi bi-exclamation-triangle"></i> <strong>${disponibilidad.mensaje}</strong></p>
                        <hr style="margin: 1.5rem 0;">
                        <p><i class="bi bi-calendar-x"></i> Ciclo existente:</p>
                        <p style="margin-left: 20px;">Del ${formatearFecha(disponibilidad.ciclo_inicio)} al ${formatearFecha(disponibilidad.ciclo_fin)}</p>
                        <hr style="margin: 1.5rem 0;">
                        <p><i class="bi bi-calendar-check"></i> <strong>Próxima fecha disponible:</strong></p>
                        <p style="margin-left: 20px; color: #2d5016; font-size: 1.2em; font-weight: bold;">
                            ${formatearFecha(disponibilidad.proxima_fecha_disponible)}
                        </p>
                    </div>
                `,
                showCancelButton: true,
                confirmButtonText: '<i class="bi bi-calendar-plus"></i> Ir a Próxima Fecha',
                cancelButtonText: 'Cerrar',
                confirmButtonColor: '#2d5016',
                cancelButtonColor: '#6c757d',
                reverseButtons: true
            }).then((result) => {
                if (result.isConfirmed) {
                    cargarFechaSugerida(disponibilidad.proxima_fecha_disponible);
                }
            });
        }

        gestionarBotones('fecha_ocupada');

        if (contenedorResultados) {
            contenedorResultados.innerHTML = `
                <div class="empty-state">
                    <i class="bi bi-calendar-x" style="color: #ff6b6b;"></i>
                    <h3>Fecha no disponible</h3>
                    <p>Esta fecha pertenece a un ciclo ya generado</p>
                    <p><strong>Ciclo existente:</strong> ${formatearFecha(disponibilidad.ciclo_inicio)} - ${formatearFecha(disponibilidad.ciclo_fin)}</p>
                </div>
            `;
        }

        return;
    }

    // ✨ Fecha disponible - verificar si tiene datos
    const existe = await verificarSemanaExistente(fecha);
    mostrarLoading(false);

    if (existe) {
        gestionarBotones('semana_existente');
        if (mostrarAlertas) {
            Toast.fire({
                icon: 'info',
                title: '📋 Este ciclo ya tiene servicios generados',
                timer: 3000
            });
        }
    } else {
        gestionarBotones('semana_nueva');

        if (mostrarAlertas) {
            if (disponibilidad.proxima_fecha_disponible === fecha) {
                Toast.fire({
                    icon: 'success',
                    title: '✅ Fecha disponible - Próxima sugerida',
                    timer: 3000
                });
            } else {
                Toast.fire({
                    icon: 'info',
                    title: `📅 Fecha disponible`,
                    timer: 3000
                });
            }
        }
    }

    if (estadoVista === 'seleccion' && contenedorResultados) {
        contenedorResultados.innerHTML = `
            <div class="empty-state">
                <i class="bi bi-calendar-check"></i>
                <h3>Selecciona una acción</h3>
                <p>${existe ? 'Este ciclo ya tiene servicios generados' : 'Genera los servicios para este ciclo de 10 días'}</p>
            </div>
        `;
    }
};

// ========================================
// ✨ FUNCIÓN: Cargar fecha sugerida
// ========================================

const cargarFechaSugerida = (fecha) => {
    if (fechaInicio) {
        fechaInicio.value = fecha;
        manejarCambioFecha();

        Toast.fire({
            icon: 'success',
            title: '✅ Fecha actualizada',
            timer: 2000
        });
    }
};

// Hacer la función global para que pueda ser llamada desde HTML
window.cargarFechaSugerida = cargarFechaSugerida;

const regresarASeleccion = async () => {
    // Limpiar vista
    if (contenedorResultados) {
        contenedorResultados.innerHTML = `
            <div class="empty-state">
                <i class="bi bi-calendar-check"></i>
                <h3>Selecciona una fecha de inicio</h3>
                <p>Elige cualquier día y genera un ciclo de 10 días de servicios</p>
            </div>
        `;
    }

    // Cargar próxima fecha disponible automáticamente
    try {
        const response = await fetch('/TERCERA_CIA/API/asignaciones/proxima-fecha');
        const data = await response.json();

        if (data.codigo === 1 && data.data) {
            const info = data.data;

            // Establecer la fecha en el input sin mostrar alertas
            if (fechaInicio) {
                fechaInicio.value = info.proxima_fecha;
            }

            // Verificar si hay ciclo en esta fecha (sin mostrar alertas)
            const disponibilidad = await verificarDisponibilidadFecha(info.proxima_fecha);

            if (disponibilidad) {
                fechaActualInfo = disponibilidad;

                // Verificar si tiene datos
                const existe = await verificarSemanaExistente(info.proxima_fecha);

                if (existe) {
                    gestionarBotones('semana_existente');
                } else {
                    gestionarBotones('semana_nueva');
                }
            } else {
                gestionarBotones('inicial');
            }

            // Toast discreto
            Toast.fire({
                icon: 'info',
                title: 'Seleccione una Fecha',
                timer: 1500
            });
        }
    } catch (error) {
        console.error('Error al regresar:', error);
        gestionarBotones('inicial');
    }
};

// ========================================
// FUNCIONES DEL MODAL DE GRUPOS
// ========================================

const obtenerGruposSeleccionados = () => {
    const botonesActivos = document.querySelectorAll('.btn-grupo.active');
    const grupos = [];

    botonesActivos.forEach(btn => {
        grupos.push(parseInt(btn.dataset.grupo));
    });

    return grupos;
};

const actualizarConteoPersonal = async () => {
    const gruposSeleccionados = obtenerGruposSeleccionados();

    const resumen = document.getElementById('resumenSeleccion');
    const conteo = document.getElementById('conteoPersonal');

    if (!resumen || !conteo) {
        console.warn('⚠️ Elementos de resumen no encontrados');
        return;
    }

    if (gruposSeleccionados.length === 0) {
        resumen.style.display = 'none';
        return;
    }

    try {
        const response = await fetch('/TERCERA_CIA/API/asignaciones/contar-personal', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ grupos: gruposSeleccionados })
        });

        const data = await response.json();

        if (data.codigo === 1) {
            conteo.innerHTML = `
                <div class="row text-center">
                    <div class="col-4">
                        <strong style="color: #9B59B6;">👮 Oficiales:</strong> ${data.oficiales || 0}
                    </div>
                    <div class="col-4">
                        <strong style="color: #ff6b6b;">🔧 Especialistas:</strong> ${data.especialistas || 0}
                    </div>
                    <div class="col-4">
                        <strong style="color: #4ECDC4;">🎖️ Tropa:</strong> ${data.tropa || 0}
                    </div>
                </div>
                <hr>
                <div class="text-center">
                    <strong>Total disponible: ${data.total || 0} personas</strong>
                </div>
            `;

            resumen.style.display = 'block';
        }
    } catch (error) {
        console.error('Error al contar personal:', error);
    }
};

// ========================================
// GENERAR SERVICIOS
// ========================================

if (btnGenerar) {
    btnGenerar.addEventListener('click', (e) => {
        e.preventDefault();

        if (!fechaInicio) {
            Swal.fire({
                icon: 'warning',
                title: 'Error',
                text: 'No se encontró el campo de fecha'
            });
            return;
        }

        const fecha = fechaInicio.value;

        if (!fecha) {
            Swal.fire({
                icon: 'warning',
                title: 'Fecha requerida',
                text: 'Debe seleccionar una fecha de inicio'
            });
            return;
        }

        // ✨ Verificar si la fecha está disponible
        if (fechaActualInfo && !fechaActualInfo.disponible) {
            Swal.fire({
                icon: 'error',
                title: 'Fecha no disponible',
                html: `
                    <p>${fechaActualInfo.mensaje}</p>
                    <p><strong>Próxima fecha disponible:</strong></p>
                    <p>${formatearFecha(fechaActualInfo.proxima_fecha_disponible)}</p>
                `,
                confirmButtonText: 'Entendido'
            });
            return;
        }

        if (fechaSemanaModal) {
            fechaSemanaModal.textContent = formatearFecha(fecha);
        }

        if (modalSeleccionGrupos) {
            modalSeleccionGrupos.show();
            actualizarConteoPersonal();
        } else {
            console.error('Modal de grupos no disponible');
        }
    });
}

if (btnConfirmarGeneracion) {
    btnConfirmarGeneracion.addEventListener('click', async () => {
        if (!fechaInicio) return;

        const fecha = fechaInicio.value;
        const gruposSeleccionados = obtenerGruposSeleccionados();

        if (gruposSeleccionados.length === 0) {
            Swal.fire({
                icon: 'warning',
                title: 'Sin grupos seleccionados',
                text: 'Debe seleccionar al menos un grupo para generar servicios'
            });
            return;
        }

        if (modalSeleccionGrupos) {
            modalSeleccionGrupos.hide();
        }

        const confirmacion = await Swal.fire({
            icon: 'question',
            title: '¿Generar servicios?',
            html: `
                <p>Se generarán los servicios para el <strong>CICLO DE 10 DÍAS</strong> del:</p>
                <p><strong>${formatearFecha(fecha)}</strong></p>
                <p><strong>${gruposSeleccionados.length} grupos seleccionados</strong></p>
                <hr style="margin: 1.5rem 0; border-top: 2px solid #e2e8f0;">
                <p style="color: #ff6b6b; font-weight: 600; margin-top: 1rem;">
                    <i class="bi bi-exclamation-triangle-fill"></i> 
                    Recuerde que el personal en otra comisión (que no sea Descanso) debe ser desactivado desde el panel de Gestión de Personal antes de generar los servicios.
                </p>
            `,
            showCancelButton: true,
            confirmButtonText: 'Sí, generar',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#2d5016'
        });

        if (!confirmacion.isConfirmed) return;

        await generarServiciosConGrupos(fecha, gruposSeleccionados);
    });
}

const generarServiciosConGrupos = async (fecha, grupos) => {
    try {
        mostrarLoading(true);

        const body = new FormData();
        body.append('fecha_inicio', fecha);
        body.append('grupos_disponibles', JSON.stringify(grupos));

        const url = "/TERCERA_CIA/API/asignaciones/generar";
        const config = {
            method: 'POST',
            body
        };

        const respuesta = await fetch(url, config);
        const text = await respuesta.text();

        console.log('RESPUESTA RAW DEL SERVIDOR:', text);

        let data;
        try {
            data = JSON.parse(text);
        } catch (e) {
            mostrarLoading(false);
            throw new Error('La respuesta NO es JSON, revisa la consola');
        }

        if (data.debug) {
            console.log('=== DEBUG COMPLETO ===');
            console.log(data.debug);
        }

        if (data.errores && data.errores.length > 0) {
            console.log('=== ERRORES DETALLADOS ===');
            data.errores.forEach(error => {
                console.log(`Fecha: ${error.fecha}`);
                console.log(`Error: ${error.error}`);
            });
        }

        if (data.codigo === 1) {
            const datosGenerados = await consultarServiciosSinUI(fecha);

            mostrarLoading(false);

            if (datosGenerados && datosGenerados.length > 0) {
                Toast.fire({
                    icon: 'success',
                    title: data.mensaje
                });

                mostrarServicios(datosGenerados, fecha);
                gestionarBotones('generado_nuevo');
            } else {
                Swal.fire({
                    icon: 'warning',
                    title: 'Servicios generados',
                    text: 'Los servicios se generaron pero no se pudieron mostrar. Refresque la página.',
                    confirmButtonText: 'Recargar página'
                }).then(() => {
                    window.location.reload();
                });
            }
        } else {
            mostrarLoading(false);
            Swal.fire({
                icon: 'error',
                title: 'Error',
                html: `
                    <p>${data.mensaje}</p>
                    <small>Revisa la consola del navegador (F12) para más detalles</small>
                `
            });
        }
    } catch (error) {
        mostrarLoading(false);
        console.error('Error:', error);
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'Error al generar servicios: ' + error.message
        });
    }
};

// ========================================
// CONSULTAR SERVICIOS
// ========================================

const consultarServicios = async () => {
    const fecha = fechaInicio.value;

    if (!fecha) {
        Swal.fire({
            icon: 'warning',
            title: 'Fecha requerida',
            text: 'Debe seleccionar una fecha de inicio'
        });
        return;
    }

    try {
        mostrarLoading(true);

        const url = `/TERCERA_CIA/API/asignaciones/obtener?fecha_inicio=${fecha}`;
        const respuesta = await fetch(url);
        const data = await respuesta.json();

        mostrarLoading(false);

        if (data.codigo === 1 && data.datos.length > 0) {
            mostrarServicios(data.datos, fecha);
            gestionarBotones('consultando');
        } else {
            contenedorResultados.innerHTML = `
                <div class="empty-state">
                    <i class="bi bi-inbox"></i>
                    <h3>No hay servicios generados</h3>
                    <p>No se encontraron asignaciones para este ciclo</p>
                </div>
            `;
            gestionarBotones('inicial');
        }
    } catch (error) {
        mostrarLoading(false);
        console.error('Error:', error);
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'Error al consultar servicios'
        });
    }
};

const consultarServiciosSinUI = async (fecha) => {
    try {
        const url = `/TERCERA_CIA/API/asignaciones/obtener?fecha_inicio=${fecha}`;
        const respuesta = await fetch(url);
        const data = await respuesta.json();

        if (data.codigo === 1 && data.datos && data.datos.length > 0) {
            return data.datos;
        }
        return null;
    } catch (error) {
        console.error('Error al consultar servicios:', error);
        return null;
    }
};

// ========================================
// MOSTRAR SERVICIOS
// ========================================

const mostrarServicios = (asignaciones, fechaInicio) => {
    if (!asignaciones || asignaciones.length === 0) {
        contenedorResultados.innerHTML = `
            <div class="empty-state">
                <i class="bi bi-inbox"></i>
                <h3>No hay servicios</h3>
                <p>No se encontraron asignaciones para este ciclo</p>
            </div>
        `;
        return;
    }

    const serviciosSemana = asignaciones.filter(a => a.servicio === 'Semana');
    const serviciosDiarios = asignaciones.filter(a => a.servicio !== 'Semana');

    const serviciosPorDia = {};
    serviciosDiarios.forEach(asig => {
        if (!serviciosPorDia[asig.fecha_servicio]) {
            serviciosPorDia[asig.fecha_servicio] = [];
        }
        serviciosPorDia[asig.fecha_servicio].push(asig);
    });

    const agruparPorServicio = (asignaciones) => {
        const grupos = {};
        asignaciones.forEach(asig => {
            if (!grupos[asig.servicio]) {
                grupos[asig.servicio] = [];
            }
            grupos[asig.servicio].push(asig);
        });
        return grupos;
    };

    let html = '';

    // Mostrar servicio SEMANA
    if (serviciosSemana.length > 0) {
        const fechaFinCiclo = new Date(fechaInicio);
        fechaFinCiclo.setDate(fechaFinCiclo.getDate() + 9);

        html += `
            <div class="row mb-4">
                <div class="col-12">
                    <div class="week-service-card">
                        <h3 class="week-title">
                            <i class="bi bi-calendar-range"></i>
                            Servicios para el Ciclo Completo (${formatearFecha(fechaInicio)} al ${formatearFecha(fechaFinCiclo.toISOString().split('T')[0])})
                        </h3>
        `;

        serviciosSemana.forEach(s => {
            let gradoCompleto = s.grado;
            if (s.tipo_personal === 'ESPECIALISTA') {
                gradoCompleto += ' ESPECIALISTA';
            }

            html += `
                <div class="semana-card">
                    <div class="semana-header">
                        <h4><i class="bi bi-shield-fill"></i> Semana del Ciclo Completo (10 días)</h4>
                        <span class="badge bg-warning text-dark">10 días completos</span>
                    </div>
                    <div class="semana-content">
                        <div class="personnel-item-semana">
                            <div>
                                <strong>${gradoCompleto}</strong> ${s.nombre_completo}
                            </div>
                            <div class="text-muted">
                                <i class="bi bi-clock"></i> Todo el ciclo
                            </div>
                        </div>
                    </div>
                </div>
            `;
        });

        html += `
                    </div>
                </div>
            </div>
        `;
    }

    html += '<div class="row">';

    // Mostrar servicios por día
    Object.keys(serviciosPorDia).sort().forEach(fecha => {
        const serviciosDelDia = serviciosPorDia[fecha];
        const serviciosAgrupados = agruparPorServicio(serviciosDelDia);
        const oficialDia = serviciosDelDia[0];

        html += `
            <div class="col-12">
                <div class="day-card">
                    <div class="day-header">
                        <div>
                            <h3>
                                <i class="bi bi-calendar-day"></i> 
                                ${formatearFecha(fecha)}
                            </h3>
                            ${oficialDia.oficial_encargado ? `
                                <div class="official-day-badge">
                                    <i class="bi bi-star-fill"></i>
                                    <strong>Oficial del Día:</strong> 
                                    ${oficialDia.grado_oficial} ${oficialDia.oficial_encargado}
                                </div>
                            ` : ''}
                        </div>
                        <span class="badge bg-primary">${serviciosDelDia.length} asignaciones</span>
                    </div>
                    <div class="row">
        `;

        const ordenServicios = [
            'TACTICO',
            'TACTICO TROPA',
            'BANDERÍN',
            'RECONOCIMIENTO',
            'SERVICIO NOCTURNO',
            'CUARTELERO'
        ];

        ordenServicios.forEach((nombreServicio) => {
            if (!serviciosAgrupados[nombreServicio]) return;

            const personal = serviciosAgrupados[nombreServicio];
            const claseServicio = nombreServicio.toLowerCase().replace(/\s+/g, '');

            let colSize = 'col-md-6 col-lg-4';
            let breakAfter = '';

            if (nombreServicio === 'TACTICO' || nombreServicio === 'TACTICO TROPA' || nombreServicio === 'BANDERÍN') {
                colSize = 'col-md-6 col-lg-4';
                if (nombreServicio === 'BANDERÍN') {
                    breakAfter = '<div class="w-100"></div>';
                }
            }

            if (nombreServicio === 'RECONOCIMIENTO' || nombreServicio === 'SERVICIO NOCTURNO') {
                colSize = 'col-md-6 col-lg-6';
                if (nombreServicio === 'SERVICIO NOCTURNO') {
                    breakAfter = '<div class="w-100"></div>';
                }
            }

            if (nombreServicio === 'CUARTELERO') {
                colSize = 'col-md-6 col-lg-6 offset-lg-6';
            }

            html += `
                <div class="${colSize}">
                    <div class="service-card ${claseServicio}">
                        <h4>
                            <i class="bi bi-shield-check"></i> 
                            ${nombreServicio === 'TACTICO' ? 'TÁCTICO' : nombreServicio}
                        </h4>
                        <div class="personnel-list">
            `;

            personal.forEach((p, index) => {
                let horarioTexto = '';

                if (nombreServicio === 'SERVICIO NOCTURNO') {
                    const turnos = ['PRIMER TURNO', 'SEGUNDO TURNO', 'TERCER TURNO'];
                    horarioTexto = turnos[index] || `TURNO ${index + 1}`;
                } else if (nombreServicio === 'CUARTELERO') {
                    horarioTexto = '';
                } else {
                    horarioTexto = `${p.hora_inicio.substring(0, 5)} - ${p.hora_fin.substring(0, 5)}`;
                }

                let gradoCompleto = p.grado;
                if (p.tipo_personal === 'ESPECIALISTA') {
                    gradoCompleto += ' ESPECIALISTA';
                }

                html += `
                    <div class="personnel-item">
                        <span><strong>${gradoCompleto}</strong> ${p.nombre_completo}</span>
                        ${horarioTexto ? `<span>${horarioTexto}</span>` : ''}
                    </div>
                `;
            });

            html += `
                        </div>
                    </div>
                </div>
                ${breakAfter}
            `;
        });

        html += `
                    </div>
                </div>
            </div>
        `;
    });

    html += '</div>';
    contenedorResultados.innerHTML = html;
};

// ========================================
// ELIMINAR CICLO
// ========================================

const eliminarSemana = async () => {
    const fecha = fechaInicio.value;

    const confirmacion = await Swal.fire({
        icon: 'warning',
        title: '¿Eliminar ciclo completo?',
        html: `Se eliminarán TODOS los servicios del ciclo de 10 días del:<br><strong>${formatearFecha(fecha)}</strong>`,
        showCancelButton: true,
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6'
    });

    if (!confirmacion.isConfirmed) return;

    try {
        mostrarLoading(true);

        const body = new FormData();
        body.append('fecha_inicio', fecha);

        const url = "/TERCERA_CIA/API/asignaciones/eliminar";
        const config = {
            method: 'POST',
            body
        };

        const respuesta = await fetch(url, config);
        const textoRespuesta = await respuesta.text();

        mostrarLoading(false);

        let data;
        try {
            data = JSON.parse(textoRespuesta);
        } catch (e) {
            console.error('Error al parsear JSON:', e);
            Swal.fire({
                icon: 'error',
                title: 'Error del servidor',
                html: `<p>El servidor devolvió un error. Revisa la consola (F12)</p>`
            });
            return;
        }

        if (data.codigo === 1) {
            Toast.fire({
                icon: 'success',
                title: data.mensaje
            });

            // Recargar próxima fecha disponible
            await cargarProximaFechaDisponible();
            regresarASeleccion();
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: data.mensaje
            });
        }
    } catch (error) {
        mostrarLoading(false);
        console.error('Error:', error);
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'Error al eliminar servicios: ' + error.message
        });
    }
};

// ========================================
// EXPORTAR PDF
// ========================================

const exportarPDF = () => {
    const fechaSeleccionada = fechaInicio.value;

    if (!fechaSeleccionada) {
        Swal.fire('Error', 'Debes seleccionar una fecha', 'error');
        return;
    }

    window.open(`/TERCERA_CIA/asignaciones/exportar-pdf?fecha=${fechaSeleccionada}`, '_blank');
};

// ========================================
// FORMATEAR FECHA
// ========================================

const formatearFecha = (fecha) => {
    const date = new Date(fecha + 'T00:00:00');
    const opciones = {
        weekday: 'long',
        year: 'numeric',
        month: 'long',
        day: 'numeric'
    };
    return date.toLocaleDateString('es-ES', opciones);
};

// ========================================
// 🆕 FUNCIONES DE COMISIONES
// ========================================

/**
 * 🆕 ABRIR MODAL DE COMISIÓN
 */
const abrirModalComision = async () => {
    await cargarPersonalDisponible();

    // 🔍 DEBUG - Verificar que se cargó personal
    const select = document.getElementById('personalComision');
    console.log('👥 Personal cargado:', select?.options.length, 'opciones');
    console.log('📋 Primer personal:', select?.options[1]?.value, select?.options[1]?.text);


    // Establecer fecha mínima (hoy)
    const hoy = new Date().toISOString().split('T')[0];
    if (fechaInicioComision) {
        fechaInicioComision.min = hoy;
        fechaInicioComision.value = hoy;
    }

    if (fechaFinComision) {
        fechaFinComision.min = hoy;
    }

    // Limpiar formulario
    document.getElementById('formComision').reset();
    document.getElementById('destinoComision').value = 'Ciudad Capital';
    document.getElementById('serviciosAfectadosPreview').style.display = 'none';
    document.getElementById('resumenDiasComision').style.display = 'none';

    if (modalComisionBS) {
        modalComisionBS.show();
    }
};

/**
 * 🆕 CARGAR PERSONAL DISPONIBLE
 */
const cargarPersonalDisponible = async () => {
    const select = document.getElementById('personalComision');

    if (!select) {
        console.error('❌ Select personalComision no encontrado');
        return;
    }

    try {
        const response = await fetch('/TERCERA_CIA/API/personal/activos');
        const data = await response.json();

        console.log('✅ Respuesta completa del API:', data);

        if (data.codigo === 1 && Array.isArray(data.personal)) {
            select.innerHTML = '<option value="">Seleccione el personal...</option>';

            // Agrupar por tipo con validación
            const porTipo = {
                'OFICIAL': [],
                'ESPECIALISTA': [],
                'TROPA': []
            };

            data.personal.forEach(p => {
                console.log('👤 Procesando:', p); // 🔍 Ver cada persona

                // ✅ Validar que tenga tipo y que sea válido
                const tipo = p.tipo?.toUpperCase().trim();

                if (tipo && porTipo[tipo]) {
                    porTipo[tipo].push(p);
                } else {
                    console.warn('⚠️ Tipo no reconocido:', tipo, 'para persona:', p);
                }
            });

            console.log('📊 Personal agrupado:', porTipo);

            // Agregar opciones agrupadas
            Object.entries(porTipo).forEach(([tipo, personal]) => {
                if (personal.length > 0) {
                    const optgroup = document.createElement('optgroup');
                    optgroup.label = tipo;

                    personal.forEach(p => {
                        const option = document.createElement('option');
                        option.value = p.id_personal;

                        // ✅ Construir el texto con validación
                        const grado = p.grado || '';
                        const nombres = p.nombres || '';
                        const apellidos = p.apellidos || '';

                        option.textContent = `${grado} ${nombres} ${apellidos}`.trim();

                        console.log('➕ Agregando opción:', option.value, option.textContent);

                        optgroup.appendChild(option);
                    });

                    select.appendChild(optgroup);
                }
            });

            const totalOpciones = select.options.length - 1; // -1 por el placeholder
            console.log(`✅ Select poblado con ${totalOpciones} opciones`);

            if (totalOpciones === 0) {
                console.warn('⚠️ No se agregó ninguna opción al select');
            }
        } else {
            console.error('❌ Respuesta inválida del API:', data);
            Toast.fire({
                icon: 'error',
                title: 'Formato de respuesta inválido'
            });
        }
    } catch (error) {
        console.error('❌ Error al cargar personal:', error);
        console.error('Stack:', error.stack);
        Toast.fire({
            icon: 'error',
            title: 'Error al cargar personal disponible'
        });
    }
};

/**
 * 🆕 ACTUALIZAR VISTA PREVIA
 */
const actualizarVistaPrevia = async () => {
    const idPersonal = personalComision?.value;
    const fechaInicio = fechaInicioComision?.value;
    const fechaFin = fechaFinComision?.value;

    const previewDiv = document.getElementById('serviciosAfectadosPreview');
    const listaDiv = document.getElementById('listaServiciosAfectados');
    const resumenDiv = document.getElementById('resumenDiasComision');
    const totalDiasSpan = document.getElementById('totalDiasComision');
    const alertaCompensacion = document.getElementById('alertaCompensacion');

    if (!idPersonal || !fechaInicio || !fechaFin) {
        if (previewDiv) previewDiv.style.display = 'none';
        if (resumenDiv) resumenDiv.style.display = 'none';
        return;
    }

    // Calcular días totales
    const inicio = new Date(fechaInicio);
    const fin = new Date(fechaFin);
    const diasTotales = Math.ceil((fin - inicio) / (1000 * 60 * 60 * 24)) + 1;

    if (totalDiasSpan) {
        totalDiasSpan.textContent = diasTotales;
    }

    if (resumenDiv) {
        resumenDiv.style.display = 'block';
    }

    // Mostrar alerta si es comisión larga
    if (alertaCompensacion) {
        alertaCompensacion.style.display = diasTotales >= 10 ? 'block' : 'none';
    }

    try {
        const response = await fetch(
            `/TERCERA_CIA/API/asignaciones/servicios-afectados?` +
            `id_personal=${idPersonal}&fecha_inicio=${fechaInicio}&fecha_fin=${fechaFin}`
        );
        const data = await response.json();

        if (data.codigo === 1 && data.servicios.length > 0) {
            listaDiv.innerHTML = '';

            data.servicios.forEach(s => {
                const div = document.createElement('div');
                div.className = 'servicio-item';
                div.innerHTML = `
                    <strong>${formatearFecha(s.fecha_servicio)}</strong> - ${s.servicio}
                    <br>
                    <small class="text-muted">
                        <i class="bi bi-clock"></i> ${s.hora_inicio.substring(0, 5)} - ${s.hora_fin.substring(0, 5)}
                    </small>
                `;
                listaDiv.appendChild(div);
            });

            previewDiv.style.display = 'block';
        } else {
            listaDiv.innerHTML = '<p class="text-muted mb-0">No tiene servicios programados en estas fechas</p>';
            previewDiv.style.display = 'block';
        }
    } catch (error) {
        console.error('Error:', error);
    }
};

/**
 * 🆕 REGISTRAR COMISIÓN
 */
const registrarComision = async () => {
    const datos = {
        id_personal: personalComision?.value,
        fecha_inicio: fechaInicioComision?.value,
        fecha_fin: fechaFinComision?.value,
        destino: document.getElementById('destinoComision')?.value || 'Ciudad Capital',
        numero_oficio: document.getElementById('numeroOficio')?.value,
        motivo: document.getElementById('motivoComision')?.value
    };

    // 🔍 DEBUG - Ver qué se está enviando
    console.log('📤 Datos a enviar:', datos);

    // Validaciones
    if (!datos.id_personal || !datos.fecha_inicio || !datos.fecha_fin || !datos.numero_oficio) {
        Swal.fire({
            icon: 'warning',
            title: 'Datos incompletos',
            text: 'Complete todos los campos obligatorios marcados con *',
            confirmButtonColor: '#f39c12'
        });
        return;
    }

    // 🔍 Verificar que el ID sea un número válido
    if (isNaN(parseInt(datos.id_personal))) {
        Swal.fire({
            icon: 'error',
            title: 'Personal inválido',
            text: 'Debe seleccionar un personal válido',
            confirmButtonColor: '#e74c3c'
        });
        return;
    }

    // Confirmación
    const confirmacion = await Swal.fire({
        icon: 'question',
        title: '¿Registrar comisión oficial?',
        html: `
            <div style="text-align: left;">
                <p><strong>Personal:</strong> ${personalComision.options[personalComision.selectedIndex].text}</p>
                <p><strong>Destino:</strong> ${datos.destino}</p>
                <p><strong>Fechas:</strong> ${formatearFecha(datos.fecha_inicio)} al ${formatearFecha(datos.fecha_fin)}</p>
                <p><strong>Oficio:</strong> ${datos.numero_oficio}</p>
                <hr>
                <p class="text-warning mb-0">
                    <i class="bi bi-info-circle"></i> 
                    El sistema buscará reemplazos automáticamente siguiendo criterios de equidad.
                </p>
            </div>
        `,
        showCancelButton: true,
        confirmButtonText: '<i class="bi bi-check-circle"></i> Sí, registrar',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#f39c12',
        cancelButtonColor: '#6c757d'
    });

    if (!confirmacion.isConfirmed) return;

    try {
        mostrarLoading(true);

        const formData = new FormData();
        Object.keys(datos).forEach(key => {
            formData.append(key, datos[key]);
        });

        const response = await fetch('/TERCERA_CIA/API/asignaciones/registrar-comision', {
            method: 'POST',
            body: formData
        });

        const data = await response.json();

        mostrarLoading(false);

        if (data.codigo === 1) {
            if (modalComisionBS) {
                modalComisionBS.hide();
            }

            // Mostrar resultado detallado
            await Swal.fire({
                icon: 'success',
                title: '✅ Comisión Registrada',
                html: generarHTMLResultado(data.data),
                confirmButtonText: 'Entendido',
                confirmButtonColor: '#27ae60',
                width: '600px'
            });

            // Recargar vista si está consultando servicios
            if (typeof consultarServicios === 'function') {
                consultarServicios();
            }
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Error al registrar',
                text: data.mensaje,
                confirmButtonColor: '#e74c3c'
            });
        }
    } catch (error) {
        mostrarLoading(false);
        console.error('Error:', error);
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'Error al registrar comisión: ' + error.message,
            confirmButtonColor: '#e74c3c'
        });
    }
};

/**
 * 🆕 GENERAR HTML DE RESULTADO
 */
const generarHTMLResultado = (data) => {
    let html = `
        <div style="text-align: left;">
            <div class="alert alert-success">
                <strong>📋 Resumen de la comisión:</strong>
                <ul class="mb-0 mt-2">
                    <li><strong>Oficio:</strong> ${data.numero_oficio}</li>
                    <li><strong>Duración:</strong> ${data.dias_comision} días</li>
                    <li><strong>Servicios afectados:</strong> ${data.servicios_afectados}</li>
                    <li><strong>Reemplazos realizados:</strong> ${data.reemplazos_realizados}</li>
                </ul>
            </div>
    `;

    if (data.reemplazos_realizados > 0) {
        html += `
            <div class="alert alert-info">
                <strong>✅ Reemplazos asignados:</strong>
                <ul class="mb-0 mt-2">
        `;

        data.detalles_reemplazos.forEach(r => {
            html += `
                <li>
                    <strong>${formatearFecha(r.fecha)}</strong> - ${r.servicio}
                    <br>
                    <small>Reemplazo: ${r.grado} ${r.reemplazo}</small>
                </li>
            `;
        });

        html += `
                </ul>
            </div>
        `;
    }

    if (data.servicios_sin_reemplazo > 0) {
        html += `
            <div class="alert alert-warning">
                <strong>⚠️ Servicios sin reemplazo (requieren atención manual):</strong>
                <ul class="mb-0 mt-2">
        `;

        data.detalles_sin_reemplazo.forEach(s => {
            html += `
                <li><strong>${formatearFecha(s.fecha)}</strong> - ${s.servicio}</li>
            `;
        });

        html += `
                </ul>
                <hr>
                <small>
                    <i class="bi bi-info-circle"></i> 
                    Estos servicios no pudieron ser cubiertos automáticamente. 
                    Deberá gestionarse manualmente o ajustar la distribución.
                </small>
            </div>
        `;
    }

    if (data.dias_comision >= 10) {
        html += `
            <div class="alert alert-primary">
                <i class="bi bi-gift-fill"></i>
                <strong>Compensación otorgada:</strong>
                <p class="mb-0">
                    Por ser una comisión larga, el personal recibirá prioridad adicional al regresar.
                </p>
            </div>
        `;
    }

    html += '</div>';
    return html;
};

/**
 * 🆕 ABRIR MODAL DE COMISIONES ACTIVAS
 */
const abrirModalComisionesActivas = async () => {
    if (modalComisionesActivasBS) {
        modalComisionesActivasBS.show();
        await cargarComisionesActivas();
    }
};

const cargarComisionesActivas = async () => {
    const contenedor = document.getElementById('contenedorComisionesActivas');

    if (!contenedor) return;

    contenedor.innerHTML = `
        <div class="text-center py-4">
            <div class="spinner-border text-warning" role="status">
                <span class="visually-hidden">Cargando...</span>
            </div>
            <p class="mt-3">Cargando comisiones activas...</p>
        </div>
    `;

    try {
        const response = await fetch('/TERCERA_CIA/API/asignaciones/comisiones-activas');
        const data = await response.json();

        if (data.codigo === 1 && data.comisiones.length > 0) {
            let html = `
                <table class="tabla-comisiones table table-hover">
                    <thead>
                        <tr>
                            <th>Personal</th>
                            <th>Destino</th>
                            <th>Fechas</th>
                            <th>Días</th>
                            <th>Oficio</th>
                            <th>Reemplazos</th>
                        </tr>
                    </thead>
                    <tbody>
            `;

            data.comisiones.forEach(c => {
                html += `
                    <tr>
                        <td>
                            <strong>${c.grado}</strong><br>
                            ${c.nombre_completo}
                        </td>
                        <td>${c.destino}</td>
                        <td>
                            <small>
                                ${formatearFecha(c.fecha_inicio)}<br>
                                al ${formatearFecha(c.fecha_fin)}
                            </small>
                        </td>
                        <td>
                            <span class="badge bg-primary">${c.dias_totales} días</span>
                        </td>
                        <td>
                            <code>${c.numero_oficio}</code>
                        </td>
                        <td>
                            <span class="badge-reemplazos">
                                ${c.reemplazos_realizados}/${c.servicios_afectados}
                            </span>
                        </td>
                    </tr>
                `;
            });

            html += `
                    </tbody>
                </table>
            `;

            contenedor.innerHTML = html;
        } else {
            contenedor.innerHTML = `
                <div class="empty-state">
                    <i class="bi bi-inbox" style="font-size: 3rem; opacity: 0.3;"></i>
                    <h4>No hay comisiones activas</h4>
                    <p>No hay personal en comisión actualmente</p>
                </div>
            `;
        }
    } catch (error) {
        console.error('Error:', error);
        contenedor.innerHTML = `
            <div class="alert alert-danger">
                <i class="bi bi-exclamation-triangle"></i>
                Error al cargar comisiones activas
            </div>
        `;
    }
};

/**
 * 🆕 ABRIR MODAL DE COMPENSACIONES
 */
const abrirModalCompensaciones = async () => {
    if (modalCompensacionesBS) {
        modalCompensacionesBS.show();
        await cargarPersonalConCompensacion();
    }
};

const cargarPersonalConCompensacion = async () => {
    const contenedor = document.getElementById('contenedorCompensaciones');

    if (!contenedor) return;

    contenedor.innerHTML = `
        <div class="text-center py-4">
            <div class="spinner-border text-success" role="status">
                <span class="visually-hidden">Cargando...</span>
            </div>
            <p class="mt-3">Cargando compensaciones...</p>
        </div>
    `;

    try {
        const response = await fetch('/TERCERA_CIA/API/asignaciones/personal-con-compensacion');
        const data = await response.json();

        if (data.codigo === 1 && data.personal.length > 0) {
            let html = '';

            data.personal.forEach(p => {
                html += `
                    <div class="compensacion-card">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="mb-1">
                                    <i class="bi bi-person-fill"></i>
                                    ${p.grado} ${p.nombre_completo}
                                </h6>
                                <small class="text-muted">
                                    Ha sido reemplazo ${p.servicios_como_reemplazo} veces
                                </small>
                            </div>
                            <div>
                                <span class="badge bg-success">
                                    ${p.compensaciones_pendientes} compensación(es) pendiente(s)
                                </span>
                            </div>
                        </div>
                    </div>
                `;
            });

            contenedor.innerHTML = html;
        } else {
            contenedor.innerHTML = `
                <div class="empty-state">
                    <i class="bi bi-check-circle" style="font-size: 3rem; opacity: 0.3; color: #27ae60;"></i>
                    <h4>No hay compensaciones pendientes</h4>
                    <p>Todo el personal está al día</p>
                </div>
            `;
        }
    } catch (error) {
        console.error('Error:', error);
        contenedor.innerHTML = `
            <div class="alert alert-danger">
                <i class="bi bi-exclamation-triangle"></i>
                Error al cargar compensaciones
            </div>
        `;
    }
};

// ========================================
// EVENT LISTENERS
// ========================================

if (btnConsultar) {
    btnConsultar.addEventListener('click', consultarServicios);
}

if (btnEliminarSemana) {
    btnEliminarSemana.addEventListener('click', eliminarSemana);
}

if (btnExportarPDF) {
    btnExportarPDF.addEventListener('click', exportarPDF);
}

if (btnRegresar) {
    btnRegresar.addEventListener('click', regresarASeleccion);
}

if (fechaInicio) {
    fechaInicio.addEventListener('change', manejarCambioFecha);
}

// ========================================
// INICIALIZAR
// ========================================

gestionarBotones('inicial');