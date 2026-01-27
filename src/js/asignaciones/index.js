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
const btnRegresar = document.getElementById('btnRegresar');
const contenedorResultados = document.getElementById('contenedorResultados');
const loadingOverlay = document.getElementById('loadingOverlay');

// Estado de la vista
let estadoVista = 'seleccion'; // 'seleccion', 'consultando', 'generando'

// Establecer el próximo lunes como fecha por defecto
//const establecerProximoLunes = () => {
//    const hoy = new Date();
//    const diaSemana = hoy.getDay(); // 0 = Domingo, 1 = Lunes, etc.
//
//    let diasHastaLunes = 0;
//    if (diaSemana === 0) { // Domingo
//        diasHastaLunes = 1;
//    } else if (diaSemana !== 1) { // No es lunes
//        diasHastaLunes = 8 - diaSemana;
//    }
//
//    const proximoLunes = new Date(hoy);
//   proximoLunes.setDate(hoy.getDate() + diasHastaLunes);
//
//    const year = proximoLunes.getFullYear();
//    const month = String(proximoLunes.getMonth() + 1).padStart(2, '0');
//    const day = String(proximoLunes.getDate()).padStart(2, '0');
//
//    fechaInicio.value = `${year}-${month}-${day}`;
//};

// Validar que sea lunes
const validarLunes = (fecha) => {
    const date = new Date(fecha + 'T00:00:00');
    return date.getDay() === 1; // 1 = Lunes
};

// Mostrar/ocultar loading
const mostrarLoading = (mostrar) => {
    loadingOverlay.style.display = mostrar ? 'flex' : 'none';
};

// ✨ NUEVA FUNCIÓN: Gestionar visibilidad de botones según contexto
const gestionarBotones = (contexto) => {
    // Ocultar todos primero
    btnGenerar.style.display = 'none';
    btnConsultar.style.display = 'none';
    btnEliminarSemana.style.display = 'none';
    btnExportarPDF.style.display = 'none';
    btnRegresar.style.display = 'none';

    switch (contexto) {
        case 'semana_existente':
            // Hay servicios generados: solo consultar
            btnConsultar.style.display = '';
            estadoVista = 'seleccion';
            break;

        case 'semana_nueva':
            // No hay servicios: solo generar
            btnGenerar.style.display = '';
            estadoVista = 'seleccion';
            break;

        case 'consultando':
            // Viendo servicios existentes: exportar PDF y regresar
            btnExportarPDF.style.display = '';
            btnRegresar.style.display = '';
            estadoVista = 'consultando';
            break;

        case 'generado_nuevo':
            // Recién generados: exportar PDF, eliminar y regresar
            btnExportarPDF.style.display = '';
            btnEliminarSemana.style.display = '';
            btnRegresar.style.display = '';
            estadoVista = 'generando';
            break;

        case 'inicial':
            // Estado inicial: ocultar todo hasta que seleccionen
            estadoVista = 'seleccion';
            break;
    }
};

// ✨ NUEVA FUNCIÓN: Verificar si existe una semana en BD
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

// ✨ NUEVA FUNCIÓN: Manejar cambio de fecha
const manejarCambioFecha = async () => {
    const fecha = fechaInicio.value;

    if (!fecha) {
        gestionarBotones('inicial');
        return;
    }

    if (!validarLunes(fecha)) {
        Swal.fire({
            icon: 'error',
            title: 'Día inválido',
            text: 'Debe seleccionar un día LUNES',
            confirmButtonText: 'Entendido'
        });
        fechaInicio.value = '';
        gestionarBotones('inicial');
        return;
    }

    // Verificar si ya existe
    mostrarLoading(true);
    const existe = await verificarSemanaExistente(fecha);
    mostrarLoading(false);

    if (existe) {
        gestionarBotones('semana_existente');
    } else {
        gestionarBotones('semana_nueva');
    }

    // Limpiar resultados si había algo
    if (estadoVista === 'seleccion') {
        contenedorResultados.innerHTML = `
            <div class="empty-state">
                <i class="bi bi-calendar-check"></i>
                <h3>Selecciona una acción</h3>
                <p>${existe ? 'Esta semana ya tiene servicios generados' : 'Genera los servicios para esta semana'}</p>
            </div>
        `;
    }
};

// ✨ NUEVA FUNCIÓN: Regresar a selección
const regresarASeleccion = () => {
    contenedorResultados.innerHTML = `
        <div class="empty-state">
            <i class="bi bi-calendar-check"></i>
            <h3>Selecciona una semana</h3>
            <p>Elige un lunes y genera o consulta los servicios</p>
        </div>
    `;

    // Re-verificar el estado de la fecha actual
    manejarCambioFecha();
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
        html: `
        <p>Se generarán los servicios para la semana del</p>
        <p><strong>${formatearFecha(fecha)}</strong></p>
        <hr style="margin: 1.5rem 0; border-top: 2px solid #e2e8f0;">
        <p style="color: #ff6b6b; font-weight: 600; margin-top: 1rem;">
            <i class="bi bi-exclamation-triangle-fill"></i> 
            Importante: Esta acción solo puede realizarse una vez. Al generar la semana, no podrá crear otra posteriormente.
        </p>
    `,
        showCancelButton: true,
        confirmButtonText: 'Sí, generar',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#2d5016'
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

        if (data.debug) {
            console.log('=== DEBUG COMPLETO ===');
            console.log(data.debug);

            if (data.debug.logs) {
                console.log('=== LOGS DE ASIGNACIÓN ===');
                console.log(data.debug.logs);
            }
        }

        if (data.errores && data.errores.length > 0) {
            console.log('=== ERRORES DETALLADOS ===');
            data.errores.forEach(error => {
                console.log(`Fecha: ${error.fecha}`);
                console.log(`Error: ${error.error}`);

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

            // ✅ SOLUCIÓN: Consultar los datos recién generados
            const datosGenerados = await consultarServiciosSinUI(fecha);

            if (datosGenerados && datosGenerados.length > 0) {
                mostrarServicios(datosGenerados, fecha);
                gestionarBotones('generado_nuevo');
            } else {
                // Fallback: si no se obtienen datos, recargar
                console.warn('No se obtuvieron datos, recargando...');
                await consultarServicios();
            }
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

        if (data.codigo === 1 && data.datos.length > 0) {
            mostrarServicios(data.datos, fecha);
            gestionarBotones('consultando'); // ✨ Cambio de contexto
        } else {
            contenedorResultados.innerHTML = `
                <div class="empty-state">
                    <i class="bi bi-inbox"></i>
                    <h3>No hay servicios generados</h3>
                    <p>No se encontraron asignaciones para esta semana</p>
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


// ✨ NUEVA FUNCIÓN: Consultar servicios sin mostrar UI (solo obtener datos)
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
            let gradoCompleto = s.grado;
            if (s.tipo_personal === 'ESPECIALISTA') {
                gradoCompleto += ' ESPECIALISTA';
            }

            html += `
                <div class="semana-card">
                    <div class="semana-header">
                        <h4><i class="bi bi-shield-fill"></i> Semana</h4>
                        <span class="badge bg-warning text-dark">7 días completos</span>
                    </div>
                    <div class="semana-content">
                        <div class="personnel-item-semana">
                            <div>
                                <strong>${gradoCompleto}</strong> ${s.nombre_completo}
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
        const textoRespuesta = await respuesta.text();

        console.log('=== RESPUESTA RAW DEL SERVIDOR ===');
        console.log(textoRespuesta);

        mostrarLoading(false);

        let data;
        try {
            data = JSON.parse(textoRespuesta);
        } catch (e) {
            console.error('Error al parsear JSON:', e);
            Swal.fire({
                icon: 'error',
                title: 'Error del servidor',
                html: `
                    <p>El servidor devolvió un error. Revisa la consola (F12)</p>
                    <details>
                        <summary>Ver error</summary>
                        <pre style="text-align: left; font-size: 10px; max-height: 300px; overflow: auto;">${textoRespuesta.substring(0, 1000)}</pre>
                    </details>
                `
            });
            return;
        }

        if (data.codigo === 1) {
            Toast.fire({
                icon: 'success',
                title: data.mensaje
            });

            regresarASeleccion(); // ✨ Regresar a selección después de eliminar
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

// Exportar a PDF
const exportarPDF = () => {
    const fechaSeleccionada = fechaInicio.value;

    if (!fechaSeleccionada) {
        Swal.fire('Error', 'Debes seleccionar una fecha', 'error');
        return;
    }

    window.open(`/TERCERA_CIA/asignaciones/exportar-pdf?fecha=${fechaSeleccionada}`, '_blank');
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
btnRegresar.addEventListener('click', regresarASeleccion); // ✨ Nuevo
fechaInicio.addEventListener('change', manejarCambioFecha); // ✨ Nuevo

// Inicializar
//establecerProximoLunes();
gestionarBotones('inicial');