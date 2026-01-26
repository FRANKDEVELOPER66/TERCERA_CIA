import { Dropdown } from "bootstrap";
import { Toast, validarFormulario } from "../funciones";
import Swal from "sweetalert2";
import DataTable from "datatables.net-bs5";
import { lenguaje } from "../lenguaje";

const fechaInicio = document.getElementById('fechaInicio');
const btnGenerar = document.getElementById('btnGenerar');
const btnConsultar = document.getElementById('btnConsultar');
const btnEliminarSemana = document.getElementById('btnEliminarSemana');
const btnExportarPDF = document.getElementById('btnExportarPDF');
const contenedorResultados = document.getElementById('contenedorResultados');
const loadingOverlay = document.getElementById('loadingOverlay');

// Establecer el próximo lunes como fecha por defecto
const establecerProximoLunes = () => {
    const hoy = new Date();
    const diaSemana = hoy.getDay(); // 0 = Domingo, 1 = Lunes, etc.

    let diasHastaLunes = 0;
    if (diaSemana === 0) { // Domingo
        diasHastaLunes = 1;
    } else if (diaSemana !== 1) { // No es lunes
        diasHastaLunes = 8 - diaSemana;
    }

    const proximoLunes = new Date(hoy);
    proximoLunes.setDate(hoy.getDate() + diasHastaLunes);

    const year = proximoLunes.getFullYear();
    const month = String(proximoLunes.getMonth() + 1).padStart(2, '0');
    const day = String(proximoLunes.getDate()).padStart(2, '0');

    fechaInicio.value = `${year}-${month}-${day}`;
};

// Validar que sea lunes
const validarLunes = (fecha) => {
    const date = new Date(fecha + 'T00:00:00');
    return date.getDay() === 1; // 1 = Lunes
};

// Mostrar/ocultar loading
const mostrarLoading = (mostrar) => {
    loadingOverlay.style.display = mostrar ? 'flex' : 'none';
};

// Generar servicios de una semana
const generarServicios = async () => {
    const fecha = fechaInicio.value;

    if (!fecha) {
        Swal.fire({
            icon: 'warning',
            title: 'Fecha requerida',
            text: 'Debe seleccionar una fecha de inicio'
        });
        return;
    }

    if (!validarLunes(fecha)) {
        Swal.fire({
            icon: 'error',
            title: 'Día inválido',
            text: 'Debe seleccionar un día LUNES',
            confirmButtonText: 'Entendido'
        });
        return;
    }

    const confirmacion = await Swal.fire({
        icon: 'question',
        title: '¿Generar servicios?',
        html: `Se generarán los servicios para la semana del:<br><strong>${formatearFecha(fecha)}</strong>`,
        showCancelButton: true,
        confirmButtonText: 'Sí, generar',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#667eea'
    });

    if (!confirmacion.isConfirmed) return;

    try {
        mostrarLoading(true);

        const body = new FormData();
        body.append('fecha_inicio', fecha);

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
            throw new Error('La respuesta NO es JSON, revisa la consola');
        }

        // ⬇️ MOSTRAR DEBUG EN CONSOLA
        if (data.debug) {
            console.log('=== DEBUG COMPLETO ===');
            console.log(data.debug);

            if (data.debug.logs) {
                console.log('=== LOGS DE ASIGNACIÓN ===');
                console.log(data.debug.logs);
            }
        }

        // ⬇️ MOSTRAR ERRORES DETALLADOS
        if (data.errores && data.errores.length > 0) {
            console.log('=== ERRORES DETALLADOS ===');
            data.errores.forEach(error => {
                console.log(`Fecha: ${error.fecha}`);
                console.log(`Error: ${error.error}`);

                // Si el error tiene logs
                if (error.logs) {
                    console.log('Logs:', error.logs);
                }
            });
        }

        mostrarLoading(false);

        if (data.codigo === 1) {
            Toast.fire({
                icon: 'success',
                title: data.mensaje
            });
            consultarServicios();
        } else {
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

// Consultar servicios de una semana
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

    if (!validarLunes(fecha)) {
        Swal.fire({
            icon: 'error',
            title: 'Día inválido',
            text: 'Debe seleccionar un día LUNES'
        });
        return;
    }

    try {
        mostrarLoading(true);

        const url = `/TERCERA_CIA/API/asignaciones/obtener?fecha_inicio=${fecha}`;
        const respuesta = await fetch(url);
        const data = await respuesta.json();

        mostrarLoading(false);

        if (data.codigo === 1) {
            mostrarServicios(data.datos, fecha);
            btnEliminarSemana.style.display = data.datos.length > 0 ? '' : 'none';
            btnExportarPDF.style.display = data.datos.length > 0 ? '' : 'none';
        } else {
            contenedorResultados.innerHTML = `
                <div class="empty-state">
                    <i class="bi bi-inbox"></i>
                    <h3>No hay servicios generados</h3>
                    <p>No se encontraron asignaciones para esta semana</p>
                </div>
            `;
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

// Mostrar servicios agrupados por día
const mostrarServicios = (asignaciones, fechaInicio) => {
    if (!asignaciones || asignaciones.length === 0) {
        contenedorResultados.innerHTML = `
            <div class="empty-state">
                <i class="bi bi-inbox"></i>
                <h3>No hay servicios</h3>
                <p>No se encontraron asignaciones para esta semana</p>
            </div>
        `;
        return;
    }

    // Separar SEMANA del resto
    const serviciosSemana = asignaciones.filter(a => a.servicio === 'Semana');
    const serviciosDiarios = asignaciones.filter(a => a.servicio !== 'Semana');

    // Agrupar por fecha
    const serviciosPorDia = {};
    serviciosDiarios.forEach(asig => {
        if (!serviciosPorDia[asig.fecha_servicio]) {
            serviciosPorDia[asig.fecha_servicio] = [];
        }
        serviciosPorDia[asig.fecha_servicio].push(asig);
    });

    // Agrupar asignaciones por servicio dentro de cada día
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

    // Generar HTML
    let html = '';

    // ============ SECCIÓN DE SEMANA (ARRIBA) ============
    if (serviciosSemana.length > 0) {
        html += `
            <div class="row mb-4">
                <div class="col-12">
                    <div class="week-service-card">
                        <h3 class="week-title">
                            <i class="bi bi-calendar-range"></i>
                            Servicio Semanal (${formatearFecha(fechaInicio)} - ${formatearFecha(serviciosSemana[0].fecha_servicio)})
                        </h3>
        `;

        serviciosSemana.forEach(s => {
            html += `
                <div class="semana-card">
                    <div class="semana-header">
                        <h4><i class="bi bi-shield-fill"></i> Semana</h4>
                        <span class="badge bg-warning text-dark">7 días completos</span>
                    </div>
                    <div class="semana-content">
                        <div class="personnel-item-semana">
                            <div>
                                <strong>${s.grado}</strong> ${s.nombre_completo}
                            </div>
                            <div class="text-muted">
                                <i class="bi bi-clock"></i> Toda la semana
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

    // ============ SERVICIOS DIARIOS (ABAJO) ============
    html += '<div class="row">';

    Object.keys(serviciosPorDia).sort().forEach(fecha => {
        const serviciosDelDia = serviciosPorDia[fecha];
        const serviciosAgrupados = agruparPorServicio(serviciosDelDia);

        // Obtener el oficial del día
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

        // Mostrar cada tipo de servicio
        Object.keys(serviciosAgrupados).forEach(nombreServicio => {
            const personal = serviciosAgrupados[nombreServicio];
            const claseServicio = nombreServicio.toLowerCase().replace(/\s+/g, '');

            html += `
                <div class="col-md-6 col-lg-4">
                    <div class="service-card ${claseServicio}">
                        <h4>
                            <i class="bi bi-shield-check"></i> 
                            ${nombreServicio}
                        </h4>
                        <div class="personnel-list">
            `;

            personal.forEach((p, index) => {
                let horarioTexto = '';

                if (nombreServicio === 'SERVICIO NOCTURNO') {
                    const turnos = ['PRIMER TURNO', 'SEGUNDO TURNO', 'TERCER TURNO'];
                    horarioTexto = turnos[index] || `TURNO ${index + 1}`;
                } else {
                    horarioTexto = `${p.hora_inicio.substring(0, 5)} - ${p.hora_fin.substring(0, 5)}`;
                }

                html += `
        <div class="personnel-item">
            <span><strong>${p.grado}</strong> ${p.nombre_completo}</span>
            <span>${horarioTexto}</span>
        </div>
    `;
            });

            // ⬅️ AGREGAR SOLO ESTO DESPUÉS DEL forEach
            if (nombreServicio === 'SERVICIO NOCTURNO' && serviciosAgrupados['CUARTELERO']) {
                const cuartelero = serviciosAgrupados['CUARTELERO'][0];
                html += `
        <div class="personnel-item">
            <span><strong>${cuartelero.grado}</strong> ${cuartelero.nombre_completo}</span>
            <span>CUARTO TURNO</span>
        </div>
    `;
            }

            html += `
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
    });

    html += '</div>';
    contenedorResultados.innerHTML = html;
};

// Eliminar servicios de una semana
const eliminarSemana = async () => {
    const fecha = fechaInicio.value;

    const confirmacion = await Swal.fire({
        icon: 'warning',
        title: '¿Eliminar semana completa?',
        html: `Se eliminarán TODOS los servicios de la semana del:<br><strong>${formatearFecha(fecha)}</strong>`,
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
        const data = await respuesta.json();

        mostrarLoading(false);

        if (data.codigo === 1) {
            Toast.fire({
                icon: 'success',
                title: data.mensaje
            });

            contenedorResultados.innerHTML = `
                <div class="empty-state">
                    <i class="bi bi-calendar-check"></i>
                    <h3>Semana eliminada</h3>
                    <p>Los servicios han sido eliminados correctamente</p>
                </div>
            `;

            btnEliminarSemana.style.display = 'none';
            btnExportarPDF.style.display = 'none';
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
            text: 'Error al eliminar servicios'
        });
    }
};

// Exportar a PDF (placeholder)
const exportarPDF = async () => {
    const fecha = fechaInicio.value;

    Toast.fire({
        icon: 'info',
        title: 'Función pendiente',
        text: 'La exportación a PDF se implementará próximamente'
    });

    // Aquí iría la lógica de exportación
    // window.open(`/TERCERA_CIA/API/asignaciones/pdf?fecha_inicio=${fecha}`, '_blank');
};

// Formatear fecha a español
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

// Event Listeners
btnGenerar.addEventListener('click', generarServicios);
btnConsultar.addEventListener('click', consultarServicios);
btnEliminarSemana.addEventListener('click', eliminarSemana);
btnExportarPDF.addEventListener('click', exportarPDF);


// Al final del archivo, agrega:
document.getElementById('btnExportarPDF')?.addEventListener('click', function () {
    const fechaInicio = document.getElementById('fechaInicio').value;

    if (!fechaInicio) {
        Swal.fire('Error', 'Debes seleccionar una fecha', 'error');
        return;
    }

    // Abrir PDF directamente
    window.open(`/TERCERA_CIA/asignaciones/exportar-pdf?fecha=${fechaInicio}`, '_blank');
});

// Inicializar
establecerProximoLunes();