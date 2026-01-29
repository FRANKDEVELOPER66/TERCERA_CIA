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
const btnEliminarSemana = document.getElementById('btnEliminarCiclo'); // ← Cambiado a btnEliminarCiclo
const btnExportarPDF = document.getElementById('btnExportarPDF');
const btnRegresar = document.getElementById('btnRegresar');
const contenedorResultados = document.getElementById('contenedorResultados');
const loadingOverlay = document.getElementById('loadingOverlay');
const infoFecha = document.getElementById('infoFecha');

// Referencias al modal de grupos (con validación)
const modalElement = document.getElementById('modalSeleccionGrupos');
const modalSeleccionGrupos = modalElement ? new bootstrap.Modal(modalElement) : null;
const btnConfirmarGeneracion = document.getElementById('btnConfirmarGeneracion');
const fechaSemanaModal = document.getElementById('fechaCicloModal'); // ← Cambiado a fechaCicloModal

// Estado de la vista
let estadoVista = 'seleccion';
let fechaActualInfo = null;

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

            // Mostrar información
            if (info.tiene_ciclos) {
                mostrarInfoFecha(
                    'info',
                    `Último ciclo: ${formatearFecha(info.ultimo_ciclo_inicio)} - ${formatearFecha(info.ultimo_ciclo_fin)}<br>` +
                    `<strong>Próxima fecha sugerida: ${formatearFecha(info.proxima_fecha)}</strong>`,
                    true
                );
            } else {
                mostrarInfoFecha(
                    'success',
                    'No hay ciclos generados. Puede comenzar desde hoy.',
                    true
                );
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
// ✨ NUEVA FUNCIÓN: Mostrar información de fecha
// ========================================

const mostrarInfoFecha = (tipo, mensaje, permanente = false) => {
    if (!infoFecha) return;

    infoFecha.style.display = 'block';
    infoFecha.className = 'alert';

    const iconos = {
        'info': '<i class="bi bi-info-circle-fill"></i>',
        'warning': '<i class="bi bi-exclamation-triangle-fill"></i>',
        'success': '<i class="bi bi-check-circle-fill"></i>',
        'danger': '<i class="bi bi-x-circle-fill"></i>'
    };

    switch (tipo) {
        case 'info':
            infoFecha.classList.add('alert-info');
            break;
        case 'warning':
            infoFecha.classList.add('alert-warning');
            break;
        case 'success':
            infoFecha.classList.add('alert-success');
            break;
        case 'danger':
            infoFecha.classList.add('alert-danger');
            break;
    }

    infoFecha.innerHTML = `${iconos[tipo]} ${mensaje}`;

    if (!permanente) {
        setTimeout(() => {
            infoFecha.style.display = 'none';
        }, 8000);
    }
};

const ocultarInfoFecha = () => {
    if (infoFecha) {
        infoFecha.style.display = 'none';
    }
};

// ========================================
// FUNCIONES BÁSICAS
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
// ✨ MODIFICADO: Manejo de cambio de fecha con validación inteligente
// ========================================

const manejarCambioFecha = async () => {
    if (!fechaInicio) {
        console.warn('⚠️ Input de fecha no encontrado');
        return;
    }

    const fecha = fechaInicio.value;

    if (!fecha) {
        gestionarBotones('inicial');
        ocultarInfoFecha();
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
        // ✨ Fecha está dentro de un ciclo existente
        mostrarLoading(false);

        mostrarInfoFecha(
            'warning',
            `⚠️ <strong>Esta fecha ya está generada.</strong><br>` +
            `${disponibilidad.mensaje}<br>` +
            `<strong>Próxima fecha disponible: ${formatearFecha(disponibilidad.proxima_fecha_disponible)}</strong>`,
            true
        );

        gestionarBotones('fecha_ocupada');

        if (contenedorResultados) {
            contenedorResultados.innerHTML = `
                <div class="empty-state">
                    <i class="bi bi-calendar-x" style="color: #ff6b6b;"></i>
                    <h3>Fecha no disponible</h3>
                    <p>Esta fecha pertenece a un ciclo ya generado</p>
                    <p><strong>Ciclo existente:</strong> ${formatearFecha(disponibilidad.ciclo_inicio)} - ${formatearFecha(disponibilidad.ciclo_fin)}</p>
                    <button class="btn btn-primary mt-3" onclick="cargarFechaSugerida('${disponibilidad.proxima_fecha_disponible}')">
                        <i class="bi bi-calendar-plus"></i> Ir a próxima fecha disponible
                    </button>
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
        mostrarInfoFecha(
            'info',
            `📋 Este ciclo ya tiene servicios generados. Puede consultarlos o eliminarlos.`,
            false
        );
    } else {
        gestionarBotones('semana_nueva');

        if (disponibilidad.proxima_fecha_disponible === fecha) {
            mostrarInfoFecha(
                'success',
                `✅ Fecha disponible. Esta es la próxima fecha sugerida para generar el ciclo.`,
                false
            );
        } else {
            mostrarInfoFecha(
                'info',
                `📅 Fecha disponible. Puede generar el ciclo desde aquí.<br>` +
                `<small>Próxima fecha sugerida: ${formatearFecha(disponibilidad.proxima_fecha_disponible)}</small>`,
                false
            );
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
// ✨ NUEVA FUNCIÓN: Cargar fecha sugerida
// ========================================

window.cargarFechaSugerida = (fecha) => {
    if (fechaInicio) {
        fechaInicio.value = fecha;
        manejarCambioFecha();
    }
};

const regresarASeleccion = () => {
    if (contenedorResultados) {
        contenedorResultados.innerHTML = `
            <div class="empty-state">
                <i class="bi bi-calendar-check"></i>
                <h3>Selecciona un ciclo</h3>
                <p>Elige una fecha y genera o consulta los servicios del ciclo de 10 días</p>
            </div>
        `;
    }
    manejarCambioFecha();
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
                ocultarInfoFecha();
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
            ocultarInfoFecha();
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
// MOSTRAR SERVICIOS (igual que antes)
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
                            Servicio Ciclo Completo (${formatearFecha(fechaInicio)} - ${formatearFecha(fechaFinCiclo.toISOString().split('T')[0])})
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
                        <h4><i class="bi bi-shield-fill"></i> Ciclo Completo (10 días)</h4>
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