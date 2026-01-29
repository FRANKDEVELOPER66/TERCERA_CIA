import { Dropdown } from "bootstrap";
import { Toast, validarFormulario } from "../funciones";
import Swal from "sweetalert2";
import DataTable from "datatables.net-bs5";
import { lenguaje } from "../lenguaje";
import * as bootstrap from 'bootstrap';

const fechaInicio = document.getElementById('fechaInicio');
const btnGenerar = document.getElementById('btnGenerar');
const btnConsultar = document.getElementById('btnConsultar');
const btnEliminarCiclo = document.getElementById('btnEliminarCiclo');
const btnExportarPDF = document.getElementById('btnExportarPDF');
const btnRegresar = document.getElementById('btnRegresar');
const contenedorResultados = document.getElementById('contenedorResultados');
const loadingOverlay = document.getElementById('loadingOverlay');

// Referencias al modal de grupos
const modalSeleccionGrupos = new bootstrap.Modal(document.getElementById('modalSeleccionGrupos'));
const btnConfirmarGeneracion = document.getElementById('btnConfirmarGeneracion');
const fechaCicloModal = document.getElementById('fechaCicloModal');

// Estado de la vista
let estadoVista = 'seleccion';

// ========================================
// ✨ FUNCIONES DE PRESETS DE ROTACIÓN
// ========================================

const aplicarPreset = (tipo) => {
    const botones = document.querySelectorAll('.btn-grupo');

    switch (tipo) {
        case 'todos':
            // Marcar todos los grupos
            botones.forEach(btn => btn.classList.add('active'));
            break;

        case 'rotacion_a':
            // Solo B y C disponibles (A descansa)
            // Grupos A: 1 (Ofc), 4 (Esp), 7 (Trp)
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
            // Grupos B: 2 (Ofc), 5 (Esp), 8 (Trp)
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
            // Grupos C: 3 (Ofc), 6 (Esp), 9 (Trp)
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
            // Desmarcar todos
            botones.forEach(btn => btn.classList.remove('active'));
            break;
    }

    // Actualizar conteo después de aplicar preset
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
});

// ========================================
// FUNCIONES BÁSICAS
// ========================================

const mostrarLoading = (mostrar) => {
    loadingOverlay.style.display = mostrar ? 'flex' : 'none';
};

const gestionarBotones = (contexto) => {
    btnGenerar.style.display = 'none';
    btnConsultar.style.display = 'none';
    btnEliminarCiclo.style.display = 'none';
    btnExportarPDF.style.display = 'none';
    btnRegresar.style.display = 'none';

    switch (contexto) {
        case 'ciclo_existente':
            btnConsultar.style.display = '';
            estadoVista = 'seleccion';
            break;
        case 'ciclo_nuevo':
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
            btnEliminarCiclo.style.display = '';
            btnRegresar.style.display = '';
            estadoVista = 'generando';
            break;
        case 'inicial':
            estadoVista = 'seleccion';
            break;
    }
};

const verificarCicloExistente = async (fecha) => {
    try {
        const url = `/TERCERA_CIA/API/asignaciones/obtener?fecha_inicio=${fecha}`;
        const respuesta = await fetch(url);
        const data = await respuesta.json();
        return data.codigo === 1 && data.datos && data.datos.length > 0;
    } catch (error) {
        console.error('Error al verificar ciclo:', error);
        return false;
    }
};

const manejarCambioFecha = async () => {
    const fecha = fechaInicio.value;

    if (!fecha) {
        gestionarBotones('inicial');
        return;
    }

    mostrarLoading(true);
    const existe = await verificarCicloExistente(fecha);
    mostrarLoading(false);

    if (existe) {
        gestionarBotones('ciclo_existente');
    } else {
        gestionarBotones('ciclo_nuevo');
    }

    if (estadoVista === 'seleccion') {
        contenedorResultados.innerHTML = `
            <div class="empty-state">
                <i class="bi bi-calendar-check"></i>
                <h3>Selecciona una acción</h3>
                <p>${existe ? 'Este ciclo ya tiene servicios generados' : 'Genera los servicios para este ciclo de 10 días'}</p>
            </div>
        `;
    }
};

const regresarASeleccion = () => {
    contenedorResultados.innerHTML = `
        <div class="empty-state">
            <i class="bi bi-calendar-check"></i>
            <h3>Selecciona una fecha de inicio</h3>
            <p>Elige cualquier día y genera o consulta los servicios del ciclo de 10 días</p>
        </div>
    `;
    manejarCambioFecha();
};

// ========================================
// ✨ FUNCIONES DEL MODAL DE GRUPOS
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

    if (gruposSeleccionados.length === 0) {
        document.getElementById('resumenSeleccion').style.display = 'none';
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
            const resumen = document.getElementById('resumenSeleccion');
            const conteo = document.getElementById('conteoPersonal');

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

btnGenerar.addEventListener('click', (e) => {
    e.preventDefault();

    const fecha = fechaInicio.value;

    if (!fecha) {
        Swal.fire({
            icon: 'warning',
            title: 'Fecha requerida',
            text: 'Debe seleccionar una fecha de inicio para el ciclo de 10 días'
        });
        return;
    }

    fechaCicloModal.textContent = formatearRangoCiclo(fecha);
    modalSeleccionGrupos.show();
    actualizarConteoPersonal();
});

btnConfirmarGeneracion.addEventListener('click', async () => {
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

    modalSeleccionGrupos.hide();

    const confirmacion = await Swal.fire({
        icon: 'question',
        title: '¿Generar servicios?',
        html: `
            <p>Se generarán los servicios para el ciclo de:</p>
            <p><strong>${formatearRangoCiclo(fecha)}</strong></p>
            <p><strong>${gruposSeleccionados.length} grupos seleccionados</strong></p>
            <p style="color: #ff9966; font-weight: 600; margin-top: 1rem;">
                <i class="bi bi-calendar-range"></i> 
                Total: 10 días de servicios
            </p>
            <hr style="margin: 1.5rem 0; border-top: 2px solid #e2e8f0;">
            <p style="color: #ff6b6b; font-weight: 600; margin-top: 1rem;">
                <i class="bi bi-exclamation-triangle-fill"></i> 
                Recuerde que el personal que está en otra comisión que no sea Descanso, debe ser desactivado desde el panel de Gestión de Personal antes de generar los servicios.
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
            text: 'Debe seleccionar una fecha de inicio para el ciclo'
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

// Mostrar servicios agrupados por día
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

    if (serviciosSemana.length > 0) {
        const fechaFin = calcularFechaFin(fechaInicio, 9);

        html += `
            <div class="row mb-4">
                <div class="col-12">
                    <div class="week-service-card">
                        <h3 class="week-title">
                            <i class="bi bi-calendar-range"></i>
                            Servicio Semanal (${formatearFecha(fechaInicio)} - ${formatearFecha(fechaFin)})
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
                        <h4><i class="bi bi-shield-fill"></i> Semana</h4>
                        <span class="badge bg-warning text-dark">10 días completos</span>
                    </div>
                    <div class="semana-content">
                        <div class="personnel-item-semana">
                            <div>
                                <strong>${gradoCompleto}</strong> ${s.nombre_completo}
                            </div>
                            <div class="text-muted">
                                <i class="bi bi-clock"></i> Todo el ciclo (10 días)
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

const eliminarCiclo = async () => {
    const fecha = fechaInicio.value;

    const confirmacion = await Swal.fire({
        icon: 'warning',
        title: '¿Eliminar ciclo completo?',
        html: `Se eliminarán TODOS los servicios del ciclo de 10 días que inicia:<br><strong>${formatearFecha(fecha)}</strong><br><br><small>Esto incluye todos los días del ciclo</small>`,
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
        Swal.fire('Error', 'Debes seleccionar una fecha de inicio', 'error');
        return;
    }

    window.open(`/TERCERA_CIA/asignaciones/exportar-pdf?fecha=${fechaSeleccionada}`, '_blank');
};

// ========================================
// FUNCIONES AUXILIARES DE FECHAS
// ========================================

const calcularFechaFin = (fechaInicio, dias) => {
    const fecha = new Date(fechaInicio + 'T00:00:00');
    fecha.setDate(fecha.getDate() + dias);
    return fecha.toISOString().split('T')[0];
};

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

const formatearRangoCiclo = (fechaInicio) => {
    const fechaFin = calcularFechaFin(fechaInicio, 9);
    return `${formatearFecha(fechaInicio)} - ${formatearFecha(fechaFin)}`;
};

// ========================================
// EVENT LISTENERS
// ========================================

btnConsultar.addEventListener('click', consultarServicios);
btnEliminarCiclo.addEventListener('click', eliminarCiclo);
btnExportarPDF.addEventListener('click', exportarPDF);
btnRegresar.addEventListener('click', regresarASeleccion);
fechaInicio.addEventListener('change', manejarCambioFecha);

// ========================================
// INICIALIZAR
// ========================================

gestionarBotones('inicial');
fechaInicio.value = '';